<?php
namespace Hub\Core\Jobs;

use Hub\Core\Services\BackupService;
use Hub\Core\Services\S3Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class S3BackupWorker {

	const CHUNK_SIZE = 10485760; // 10 MB
	const MAX_RETRIES = 3;

	public function __construct() {
		add_action( 'hub_s3_worker', array( $this, 'process_job' ) );
	}

	public function process_job() {
		$state = get_option( 'hub_backup_job_state', false );
		if ( ! $state ) return;

		// Evita overlap garantindo que ninguem esta rodando (basico)
		$lock = get_transient( 'hub_s3_worker_lock' );
		if ( $lock ) return;
		set_transient( 'hub_s3_worker_lock', 1, 60 ); // lock de 60s

		$time_start = time();

		try {
			while ( time() - $time_start < 15 ) {
				$state = get_option( 'hub_backup_job_state' );
				if ( ! $state || $state['status'] === 'done' || $state['status'] === 'error' ) {
					break;
				}

				if ( $state['status'] === 'pending_generation' ) {
					$this->step_generate( $state );
				} elseif ( $state['status'] === 'init_upload' ) {
					$this->step_init_upload( $state );
				} elseif ( $state['status'] === 'uploading' ) {
					$this->step_upload_chunk( $state );
				} elseif ( $state['status'] === 'completing' ) {
					$this->step_complete( $state );
				} elseif ( $state['status'] === 'cleanup' ) {
					$this->step_cleanup( $state );
				} elseif ( $state['status'] === 'aborting' ) {
					$this->step_abort( $state );
				}
			}
		} catch ( \Exception $e ) {
			$state['status'] = 'error';
			$state['error']  = $e->getMessage();
			update_option( 'hub_backup_job_state', $state );
		}

		delete_transient( 'hub_s3_worker_lock' );
	}

	private function step_generate( &$state ) {
		$backup_service = new BackupService();
		
		if ( $state['type'] === 'db' ) {
			$filepath = $backup_service->generate_db_file();
		} else {
			$filepath = $backup_service->generate_full_zip_file();
		}

		if ( is_wp_error( $filepath ) ) {
			throw new \Exception( $filepath->get_error_message() );
		}

		$state['filepath'] = $filepath;
		$state['filesize'] = filesize( $filepath );
		$state['status']   = 'init_upload';
		
		$host = wp_parse_url( site_url(), PHP_URL_HOST );
		$date = gmdate( 'Y-m-d-His' );
		$ext  = $state['type'] === 'db' ? 'sql' : 'zip';
		
		$state['key'] = "hub-vitagencia/{$host}/{$state['type']}/{$host}-{$state['type']}-{$date}.{$ext}";

		update_option( 'hub_backup_job_state', $state );
	}

	private function get_client() {
		$settings = get_option( 'hub_settings', [] );
		$s3 = $settings['s3'] ?? [];
		if ( empty( $s3['access_key'] ) || empty( $s3['secret_key'] ) || empty( $s3['bucket'] ) ) {
			throw new \Exception( 'Credenciais S3 não configuradas.' );
		}
		return new S3Client( $s3['access_key'], $s3['secret_key'], $s3['region'], $s3['bucket'] );
	}

	private function step_init_upload( &$state ) {
		$client = $this->get_client();
		
		if ( $state['filesize'] < self::CHUNK_SIZE * 2 ) {
			// Menor que 20MB faz upload direto
			$state['is_multipart'] = false;
			$res = $client->putObject( $state['key'], $state['filepath'] );
			if ( is_wp_error( $res ) ) {
				throw new \Exception( 'PutObject Error: ' . $res->get_error_message() );
			}
			$state['status'] = 'cleanup';
		} else {
			$state['is_multipart'] = true;
			$uploadId = $client->createMultipartUpload( $state['key'] );
			if ( is_wp_error( $uploadId ) ) {
				throw new \Exception( 'CreateMultipart Error: ' . $uploadId->get_error_message() );
			}
			$state['upload_id']   = $uploadId;
			$state['status']      = 'uploading';
			$state['next_offset'] = 0;
			$state['part_number'] = 1;
			$state['parts']       = [];
			$state['retries']     = 0;
		}
		update_option( 'hub_backup_job_state', $state );
	}

	private function step_upload_chunk( &$state ) {
		$client = $this->get_client();
		
		$length = min( self::CHUNK_SIZE, $state['filesize'] - $state['next_offset'] );
		
		$etag = $client->uploadPart( 
			$state['key'], 
			$state['upload_id'], 
			$state['part_number'], 
			$state['filepath'], 
			$state['next_offset'], 
			$length 
		);

		if ( is_wp_error( $etag ) ) {
			$state['retries']++;
			if ( $state['retries'] > self::MAX_RETRIES ) {
				$state['status'] = 'aborting';
				$state['error']  = 'UploadPart falhou após ' . self::MAX_RETRIES . ' tentativas. ' . $etag->get_error_message();
			}
		} else {
			$state['parts'][] = [
				'PartNumber' => $state['part_number'],
				'ETag'       => $etag,
			];
			$state['next_offset'] += $length;
			$state['part_number']++;
			$state['retries'] = 0; // reset
			
			if ( $state['next_offset'] >= $state['filesize'] ) {
				$state['status'] = 'completing';
			}
		}
		update_option( 'hub_backup_job_state', $state );
	}

	private function step_complete( &$state ) {
		$client = $this->get_client();
		$res = $client->completeMultipartUpload( $state['key'], $state['upload_id'], $state['parts'] );
		if ( is_wp_error( $res ) ) {
			throw new \Exception( 'CompleteMultipart Error: ' . $res->get_error_message() );
		}
		$state['status'] = 'cleanup';
		update_option( 'hub_backup_job_state', $state );
	}

	private function step_cleanup( &$state ) {
		@unlink( $state['filepath'] );
		
		$settings = get_option( 'hub_settings', [] );
		$s3 = $settings['s3'] ?? [];
		$client = $this->get_client();
		
		$host = wp_parse_url( site_url(), PHP_URL_HOST );
		$prefix = "hub-vitagencia/{$host}/{$state['type']}/";
		
		$retention = $state['type'] === 'db' ? (int)($s3['retention_db'] ?? 30) : (int)($s3['retention_full'] ?? 4);
		
		$objects = $client->listObjectsV2( $prefix );
		if ( ! is_wp_error( $objects ) && count( $objects ) > $retention ) {
			// Ordena por data (mais antigo primeiro)
			usort( $objects, function($a, $b) {
				return strtotime($a['LastModified']) - strtotime($b['LastModified']);
			});
			
			$to_delete = count( $objects ) - $retention;
			for ( $i = 0; $i < $to_delete; $i++ ) {
				$client->deleteObject( $objects[$i]['Key'] );
			}
		}

		$state['status'] = 'done';
		$state['updated_at'] = gmdate('Y-m-d H:i:s');
		update_option( 'hub_backup_job_state', $state );
	}

	private function step_abort( &$state ) {
		@unlink( $state['filepath'] );
		if ( ! empty( $state['upload_id'] ) ) {
			$client = $this->get_client();
			$client->abortMultipartUpload( $state['key'], $state['upload_id'] );
		}
		$state['status'] = 'error';
		update_option( 'hub_backup_job_state', $state );
	}
}
