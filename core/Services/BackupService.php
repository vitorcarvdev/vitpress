<?php
namespace Hub\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serviço de Backups e Migração.
 * Apenas sob demanda. Nenhum backup fica armazenado no servidor.
 */
class BackupService {

	private $temp_dir;
	private $legacy_db_dir;
	private $legacy_full_dir;

	public function __construct() {
		$this->temp_dir        = WP_CONTENT_DIR . '/hub-backups/temp/';
		$this->legacy_db_dir   = WP_CONTENT_DIR . '/hub-backups/database/';
		$this->legacy_full_dir = WP_CONTENT_DIR . '/hub-backups/full/';

		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'admin_init', array( $this, 'cleanup_abandoned_backups' ) );
		
		// Remover cron antigo, caso exista na transição
		add_action( 'init', array( $this, 'unschedule_legacy_crons' ) );

		add_action( 'admin_post_hub_run_backup_db', array( $this, 'handle_manual_db_backup' ) );
		add_action( 'admin_post_hub_run_backup_full', array( $this, 'handle_manual_full_backup' ) );
		add_action( 'admin_post_hub_upload_backup', array( $this, 'handle_upload_backup' ) );
		add_action( 'wp_ajax_hub_restore_chunk', array( $this, 'ajax_restore_chunk' ) );
		add_action( 'wp_ajax_hub_upload_backup_chunk', array( $this, 'ajax_upload_chunk' ) );
		
		// Ações para legacy
		add_action( 'admin_post_hub_download_legacy', array( $this, 'handle_download_legacy' ) );
		add_action( 'admin_post_hub_delete_legacy', array( $this, 'handle_delete_legacy' ) );

		// S3 Cron triggers
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
		add_action( 'init', array( $this, 'schedule_crons' ) );
		add_action( 'hub_schedule_daily_db', array( $this, 'schedule_s3_db_backup' ) );
		add_action( 'hub_schedule_weekly_full', array( $this, 'schedule_s3_full_backup' ) );
	}

	public function add_cron_intervals( $schedules ) {
		$schedules['hub_five_minutes'] = array(
			'interval' => 300,
			'display'  => 'A cada 5 minutos (Hub)',
		);
		$schedules['hub_weekly'] = array(
			'interval' => 604800,
			'display'  => 'Semanalmente (Hub)',
		);
		return $schedules;
	}

	public function schedule_crons() {
		if ( ! wp_next_scheduled( 'hub_schedule_daily_db' ) ) {
			wp_schedule_event( strtotime('02:00:00'), 'daily', 'hub_schedule_daily_db' );
		}
		if ( ! wp_next_scheduled( 'hub_schedule_weekly_full' ) ) {
			wp_schedule_event( strtotime('next sunday 03:00:00'), 'hub_weekly', 'hub_schedule_weekly_full' );
		}
		if ( ! wp_next_scheduled( 'hub_s3_worker' ) ) {
			wp_schedule_event( time(), 'hub_five_minutes', 'hub_s3_worker' );
		}
	}

	public function unschedule_legacy_crons() {
		$crons = array( 'hub_database_backup_event', 'hub_full_backup_event' );
		foreach ( $crons as $cron ) {
			$timestamp = wp_next_scheduled( $cron );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $cron );
			}
		}
	}

	public function schedule_s3_db_backup() {
		$settings = get_option( 'hub_settings', [] );
		if ( empty( $settings['s3']['enabled'] ) ) return;

		$state = get_option( 'hub_backup_job_state', false );
		if ( $state && in_array( $state['status'], ['pending_generation', 'init_upload', 'uploading', 'completing'] ) ) {
			return; // Job running
		}
		
		update_option('hub_backup_job_state', [
			'status' => 'pending_generation',
			'type'   => 'db',
			'start'  => current_time('mysql')
		]);
	}

	public function schedule_s3_full_backup() {
		$settings = get_option( 'hub_settings', [] );
		if ( empty( $settings['s3']['enabled'] ) ) return;

		$state = get_option( 'hub_backup_job_state', false );
		if ( $state && in_array( $state['status'], ['pending_generation', 'init_upload', 'uploading', 'completing'] ) ) {
			return; // Job running
		}
		
		update_option('hub_backup_job_state', [
			'status' => 'pending_generation',
			'type'   => 'full',
			'start'  => current_time('mysql')
		]);
	}

	private function prepare_temp_dir() {
		if ( ! file_exists( $this->temp_dir ) ) {
			wp_mkdir_p( $this->temp_dir );
			file_put_contents( $this->temp_dir . '.htaccess', "Deny from all\n" );
			file_put_contents( $this->temp_dir . 'index.php', "<?php\n// Silence is golden.\n" );
		}
	}

	public function cleanup_abandoned_backups() {
		// Somente faz garbage collection de arquivos antigos na temp dir
		if ( ! file_exists( $this->temp_dir ) ) {
			return;
		}

		$files = glob( $this->temp_dir . '*' );
		if ( ! $files ) return;

		$now = time();
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				$ext = pathinfo( $file, PATHINFO_EXTENSION );
				// Ignora htaccess e index
				if ( in_array( $ext, array( 'htaccess', 'php' ), true ) ) continue;

				$age = $now - filemtime( $file );
				// Se for mais velho que 60 minutos, exclui
				if ( $age > 3600 ) {
					@unlink( $file );
				}
			}
		}
	}

	private function generate_raw_sql() {
		global $wpdb;
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		
		$tmp_file = wp_tempnam( 'hub_db_dump' );
		$handle   = fopen( $tmp_file, 'w' );
		
		fwrite( $handle, "-- Hub Database Backup (Raw SQL)\n" );
		fwrite( $handle, "-- Gerado em: " . current_time( 'mysql' ) . "\n\n" );

		foreach ( $tables as $table ) {
			$create_table = $wpdb->get_row( "SHOW CREATE TABLE `$table`", ARRAY_N );
			if ( ! empty( $create_table[1] ) ) {
				fwrite( $handle, "DROP TABLE IF EXISTS `$table`;\n" );
				fwrite( $handle, $create_table[1] . ";\n\n" );

				$rows = $wpdb->get_results( "SELECT * FROM `$table`", ARRAY_N );
				if ( $rows ) {
					foreach ( $rows as $row ) {
						$row_data = array();
						foreach ( $row as $val ) {
							if ( is_null( $val ) ) {
								$row_data[] = 'NULL';
							} else {
								$row_data[] = "'" . esc_sql( $val ) . "'";
							}
						}
						fwrite( $handle, "INSERT INTO `$table` VALUES (" . implode( ',', $row_data ) . ");\n" );
					}
					fwrite( $handle, "\n" );
				}
			}
		}

		fclose( $handle );
		return $tmp_file;
	}

	public function generate_db_file() {
		$this->cleanup_abandoned_backups();
		$this->prepare_temp_dir();
		
		$tmp_sql = $this->generate_raw_sql();
		$filename = sanitize_title( get_bloginfo( 'name' ) ) . '-db-' . current_time( 'Y-m-d_H-i-s' ) . '.sql';
		$filepath = $this->temp_dir . $filename;
		rename( $tmp_sql, $filepath );
		
		return $filepath;
	}

	public function generate_full_zip_file() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'no_zip', 'A extensão ZipArchive não está ativa neste servidor.' );
		}

		$this->cleanup_abandoned_backups();
		$this->prepare_temp_dir();

		$filename = 'backup-' . sanitize_title( get_bloginfo( 'name' ) ) . '-' . current_time( 'Y-m-d_H-i-s' ) . '.zip';
		$filepath = $this->temp_dir . $filename;

		$zip = new \ZipArchive();
		if ( $zip->open( $filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
			return new \WP_Error( 'zip_failed', 'Erro ao criar arquivo ZIP.' );
		}

		// 1. Database
		$tmp_sql = $this->generate_raw_sql();
		$zip->addFile( $tmp_sql, 'database.sql' );

		// 2. Manifest
		global $wpdb;
		$settings = get_option( 'hub_settings', [] );
		$manifest = [
			'generator'        => 'VitPress',
			'backup_schema'    => 1,
			'plugin_version'   => defined('HUB_VERSION') ? HUB_VERSION : '1.7.0',
			'wp_version'       => $GLOBALS['wp_version'],
			'php_version'      => PHP_VERSION,
			'original_url'     => site_url(),
			'table_prefix'     => $wpdb->prefix,
			'created_at'       => current_time( 'mysql' ),
			'uuid'             => $settings['installation_uuid'] ?? '',
		];
		$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );

		// 3. WP-Content
		$source = realpath( WP_CONTENT_DIR );
		$files  = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		// Diretórios a ignorar
		$exclude_paths = array();
		$hub_backups_dir = realpath( WP_CONTENT_DIR . '/hub-backups' );
		if ( $hub_backups_dir ) $exclude_paths[] = $hub_backups_dir;
		$cache_dir = realpath( WP_CONTENT_DIR . '/cache' );
		if ( $cache_dir ) $exclude_paths[] = $cache_dir;

		foreach ( $files as $file ) {
			$realpath = $file->getRealPath();

			// Verifica se o arquivo está dentro de alguma pasta ignorada
			$skip = false;
			foreach ( $exclude_paths as $ex_path ) {
				if ( strpos( $realpath, $ex_path ) === 0 ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}

			$relative_path = substr( $realpath, strlen( $source ) + 1 );
			$relative_path = str_replace( '\\', '/', $relative_path );

			if ( $file->isDir() ) {
				$zip->addEmptyDir( 'wp-content/' . $relative_path );
			} elseif ( $file->isFile() ) {
				$zip->addFile( $realpath, 'wp-content/' . $relative_path );
			}
		}

		$zip->close();
		@unlink( $tmp_sql );

		return $filepath;
	}

	public function handle_manual_db_backup() {
		if ( ! current_user_can( 'hub_vitagencia_manage_internal' ) ) wp_die( 'Acesso negado.' );
		check_admin_referer( 'hub_run_backup', 'hub_nonce' );

		$filepath = $this->generate_db_file();
		if ( is_wp_error( $filepath ) ) wp_die( $filepath->get_error_message() );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/sql' );
		header( 'Content-Disposition: attachment; filename="' . basename( $filepath ) . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $filepath ) );
		readfile( $filepath );
		
		@unlink( $filepath );
		exit;
	}

	public function handle_manual_full_backup() {
		if ( ! current_user_can( 'hub_vitagencia_manage_internal' ) ) wp_die( 'Acesso negado.' );
		check_admin_referer( 'hub_run_backup', 'hub_nonce' );

		$filepath = $this->generate_full_zip_file();
		if ( is_wp_error( $filepath ) ) wp_die( $filepath->get_error_message() );

		// Envia por streaming e deleta em seguida
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $filepath ) . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $filepath ) );
		readfile( $filepath );

		@unlink( $filepath );
		exit;
	}

		public function ajax_upload_chunk() {
		check_ajax_referer( 'hub_upload_backup', 'hub_nonce' );
		if ( ! current_user_can( 'hub_vitagencia_manage_internal' ) ) { @ob_end_clean(); wp_send_json_error( 'Sem permissão.' ); }

		$this->prepare_temp_dir();

		if ( empty( $_FILES['chunk'] ) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK ) { @ob_end_clean(); wp_send_json_error( 'Erro ao enviar o bloco do arquivo.' ); }

		$filename = sanitize_file_name( $_POST['filename'] );
		$chunk_index = intval( $_POST['chunk_index'] );
		$total_chunks = intval( $_POST['total_chunks'] );
		
		// Use a safe temp filename based on the session/user to avoid collisions
		$safe_filename = 'upload_' . md5( get_current_user_id() . $filename ) . '.zip';
		$destination = $this->temp_dir . $safe_filename;

		// If first chunk, ensure file is empty
		if ( $chunk_index === 0 && file_exists( $destination ) ) {
			unlink( $destination );
		}

		// Append chunk
		$out = fopen( $destination, $chunk_index === 0 ? "wb" : "ab" );
		if ( $out ) {
			$in = fopen( $_FILES['chunk']['tmp_name'], "rb" );
			if ( $in ) {
				while ( $buff = fread( $in, 4096 ) ) { fwrite( $out, $buff ); }
				fclose( $in );
			}
			fclose( $out );
		} else {
			@ob_end_clean(); wp_send_json_error( 'Falha ao escrever arquivo no servidor.' );
		}

		// If this is the last chunk, validate the zip file
		if ( $chunk_index === $total_chunks - 1 ) {
			$zip = new \ZipArchive();
			if ( $zip->open( $destination ) !== true ) {
				unlink( $destination );
				@ob_end_clean(); wp_send_json_error( 'Arquivo .zip corrompido ou formato inválido após junção.' );
			}
			
			$manifest_content = $zip->getFromName( 'manifest.json' );
			if ( ! $manifest_content ) {
				$zip->close(); unlink( $destination );
				@ob_end_clean(); wp_send_json_error( 'Manifesto não encontrado. Não é um backup válido do VitPress.' );
			}
			
			$manifest = json_decode( $manifest_content, true );
			if ( ! $manifest || empty( $manifest['generator'] ) || ! in_array( $manifest['generator'], array( 'VitPress', 'Hub VitAgencia' ), true ) ) {
				$zip->close(); unlink( $destination );
				@ob_end_clean(); wp_send_json_error( 'O manifesto é inválido ou não foi gerado pelo VitPress.' );
			}
			
			if ( $zip->locateName('database.sql') === false ) {
				$zip->close(); unlink( $destination );
				@ob_end_clean(); wp_send_json_error( 'database.sql ausente no backup.' );
			}
			
			for( $i = 0; $i < $zip->numFiles; $i++ ) {
				$stat = $zip->statIndex( $i );
				if ( strpos( $stat['name'], '../' ) !== false || substr( $stat['name'], 0, 1 ) === '/' ) {
					$zip->close(); unlink( $destination );
					@ob_end_clean(); wp_send_json_error( 'Rejeitado por segurança (Path Traversal).' );
				}
			}
			$zip->close();
			
			// Rename to final name
			$final_name = 'upload-restore-' . time() . '.zip';
			rename( $destination, $this->temp_dir . $final_name );
			
			@ob_end_clean(); wp_send_json_success( array( 'redirect' => admin_url( "admin.php?page=hub-vitagencia&tab=backup&restore_file={$final_name}" ) ) );
		}

		@ob_end_clean(); wp_send_json_success( array( 'message' => 'Chunk recebido.' ) );
	}

	public function handle_upload_backup() {
		if ( ! current_user_can( 'hub_vitagencia_manage_internal' ) ) wp_die();
		check_admin_referer( 'hub_upload_backup', 'hub_nonce' );

		$this->cleanup_abandoned_backups();
		$this->prepare_temp_dir();

		if ( empty( $_FILES['hubbackup_file'] ) || $_FILES['hubbackup_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_die( 'Erro no upload do arquivo.' );
		}

		$tmp_name = $_FILES['hubbackup_file']['tmp_name'];
		
		$zip = new \ZipArchive();
		if ( $zip->open( $tmp_name ) !== true ) {
			wp_die( 'Arquivo .zip corrompido ou formato inválido.' );
		}
		
		$manifest_content = $zip->getFromName( 'manifest.json' );
		if ( ! $manifest_content ) {
			$zip->close();
			wp_die( 'Manifesto não encontrado. O arquivo enviado não é um backup gerado pelo VitPress.' );
		}
		
		$manifest = json_decode( $manifest_content, true );
		if ( ! $manifest || empty( $manifest['generator'] ) || ! in_array( $manifest['generator'], array( 'VitPress', 'Hub VitAgencia' ), true ) ) {
			$zip->close();
			wp_die( 'O manifesto é inválido ou o ZIP não foi gerado pelo VitPress.' );
		}

		if ( empty($manifest['backup_schema']) || $manifest['backup_schema'] > 1 ) {
			$zip->close();
			wp_die( 'Este backup exige uma versão mais recente do VitPress para ser restaurado (Schema não suportado).' );
		}

		// Valida se o banco de dados existe
		if ( $zip->locateName('database.sql') === false ) {
			$zip->close();
			wp_die( 'O arquivo database.sql não foi encontrado dentro do backup.' );
		}

		// Zip Slip Validation: Checar se nenhum arquivo possui path absoluto ou "../"
		for( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = $stat['name'];
			if ( strpos( $name, '../' ) !== false || strpos( $name, '..\\' ) !== false || substr( $name, 0, 1 ) === '/' ) {
				$zip->close();
				wp_die( 'Arquivo ZIP rejeitado por motivo de segurança (Tentativa de Path Traversal).' );
			}
		}

		$zip->close();

		$filename = 'upload-restore-' . time() . '.zip';
		$destination = $this->temp_dir . $filename;

		move_uploaded_file( $tmp_name, $destination );

		wp_safe_redirect( admin_url( "admin.php?page=hub-vitagencia&tab=backup&restore_file={$filename}" ) );
		exit;
	}

	private function instant_move($src, $dst) {
		$dir = opendir($src);
		@mkdir($dst, 0755, true);
		while (false !== ( $item = readdir($dir)) ) {
			if ($item != '.' && $item != '..') {
				// Prevent the plugin from deleting itself during restoration
				if ($item === 'hub-vitagencia') continue;
				$src_path = $src . '/' . $item;
				$dst_path = $dst . '/' . $item;
				
				if (is_dir($src_path)) {
					// We only instantly move subdirectories directly (like plugins/elementor)
					// So if we are at wp-content level, we dive in
					if ( basename($src) === 'wp-content' ) {
						$this->instant_move($src_path, $dst_path);
					} else {
						if (file_exists($dst_path)) {
							$this->delete_dir($dst_path);
						}
						@rename($src_path, $dst_path);
					}
				} else {
					if (file_exists($dst_path)) @unlink($dst_path);
					@rename($src_path, $dst_path);
				}
			}
		}
		closedir($dir);
	}
	
	private function delete_dir($dir) {
		if (!is_dir($dir)) return;
		$items = scandir($dir);
		foreach ($items as $item) {
			if ($item == '.' || $item == '..') continue;
			$path = $dir . '/' . $item;
			if (is_dir($path)) $this->delete_dir($path);
			else @unlink($path);
		}
		@rmdir($dir);
	}
	

	private function recursive_unserialize_replace( $data, $search_arr, $replace_arr ) {
		if ( is_string( $data ) ) {
			return str_replace( $search_arr, $replace_arr, $data );
		} elseif ( is_array( $data ) ) {
			$new_data = array();
			foreach ( $data as $key => $value ) {
				$new_data[ $key ] = $this->recursive_unserialize_replace( $value, $search_arr, $replace_arr );
			}
			return $new_data;
		} elseif ( is_object( $data ) ) {
			$new_data = clone $data;
			foreach ( $data as $key => $value ) {
				$new_data->$key = $this->recursive_unserialize_replace( $value, $search_arr, $replace_arr );
			}
			return $new_data;
		}
		return $data;
	}

	private function hub_safe_replace( $string, $search_arr, $replace_arr ) {
		if ( is_serialized( $string ) ) {
			$unserialized = @unserialize( $string );
			if ( $unserialized !== false ) {
				$replaced = $this->recursive_unserialize_replace( $unserialized, $search_arr, $replace_arr );
				return serialize( $replaced );
			}
		}
		return str_replace( $search_arr, $replace_arr, $string );
	}

	private function run_full_database_replace( $old_url, $new_url ) {
		global $wpdb;
		$old_http = str_replace('https://', 'http://', $old_url);
		$old_https = str_replace('http://', 'https://', $old_url);
		$search_arr = [ $old_https, $old_http, str_replace('/', '\\/', $old_https), str_replace('/', '\\/', $old_http) ];
		$replace_arr = [ $new_url, $new_url, str_replace('/', '\\/', $new_url), str_replace('/', '\\/', $new_url) ];

		$tables = [
			$wpdb->options => [ 'id' => 'option_id', 'column' => 'option_value' ],
			$wpdb->postmeta => [ 'id' => 'meta_id', 'column' => 'meta_value' ],
			$wpdb->posts => [ 'id' => 'ID', 'column' => 'post_content' ],
			$wpdb->usermeta => [ 'id' => 'umeta_id', 'column' => 'meta_value' ]
		];

		foreach ( $tables as $table => $col_data ) {
			$id_col = $col_data['id'];
			$val_col = $col_data['column'];
			$domain_only = str_replace(['https://', 'http://'], '', $old_url);
			$query = $wpdb->prepare( "SELECT {$id_col}, {$val_col} FROM {$table} WHERE {$val_col} LIKE %s", '%' . $wpdb->esc_like($domain_only) . '%' );
			$rows = $wpdb->get_results( $query );
			if ( $rows ) {
				foreach ( $rows as $row ) {
					$old_val = $row->$val_col;
					$new_val = $this->hub_safe_replace( $old_val, $search_arr, $replace_arr );
					if ( $old_val !== $new_val ) {
						$wpdb->update( $table, [ $val_col => $new_val ], [ $id_col => $row->$id_col ] );
					}
				}
			}
		}
		update_option( 'elementor_css_print_method', 'internal' );
		delete_post_meta_by_key( '_elementor_css' );
	}

	public function ajax_restore_chunk() {
		@set_time_limit(0);
		@ini_set('memory_limit', '1024M');
		// Capture destination URL BEFORE database is overwritten
		$destination_url = site_url();
		@ignore_user_abort(true);
		ob_start(); // Prevent PHP warnings from corrupting JSON
		check_ajax_referer( 'hub_restore_chunk', 'hub_nonce' );
		if ( ! current_user_can( 'hub_vitagencia_manage_internal' ) ) { @ob_end_clean(); wp_send_json_error( 'Sem permissão.' ); }

		$file = isset( $_POST['file'] ) ? sanitize_text_field( $_POST['file'] ) : '';
		$step = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : 'init';

		$filepath = $this->temp_dir . basename( $file );
		if ( ! file_exists( $filepath ) ) { @ob_end_clean(); wp_send_json_error( 'Arquivo de backup temporário não encontrado.' ); }

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		$extract_dir = $this->temp_dir . 'restore-extract/';

		if ( 'init' === $step ) {
			// Save destination URL before DB is overwritten
			file_put_contents($this->temp_dir . 'hub_dest_url.txt', site_url());
			// Fast cleanup of previous failed attempts
			$this->delete_dir( $extract_dir );
			@mkdir( $extract_dir, 0755, true );
			
			if ( class_exists('ZipArchive') ) {
				$zip = new \ZipArchive();
				if ($zip->open($filepath) === true) {
					$zip->extractTo($extract_dir);
					$zip->close();
				} else {
					@ob_end_clean(); wp_send_json_error( 'Erro nativo ao abrir o ZIP.' );
					exit;
				}
			} else {
				$result = unzip_file( $filepath, $extract_dir );
				if ( is_wp_error( $result ) ) { @ob_end_clean(); wp_send_json_error( 'Erro WP ao descompactar: ' . $result->get_error_message() ); exit; }
			}
			
			@ob_end_clean(); wp_send_json_success( array( 'next_step' => 'files', 'progress' => 30, 'msg' => 'Arquivos extraídos localmente. Iniciando cópia...' ) );
		} 
		
		if ( 'database' === $step ) {
			global $wpdb;
			$sql_file = $extract_dir . 'database.sql';
			if ( ! file_exists( $sql_file ) ) { @ob_end_clean(); wp_send_json_error( 'Arquivo database.sql não encontrado na extração.' ); }
			
			// Load manifest to know the old prefix
			$manifest_file = $extract_dir . 'manifest.json';
			$old_prefix = 'wp_';
			if ( file_exists( $manifest_file ) ) {
				$manifest = json_decode( file_get_contents( $manifest_file ), true );
				if ( ! empty( $manifest['table_prefix'] ) ) {
					$old_prefix = $manifest['table_prefix'];
				}
			}

			// Accelerate InnoDB inserts by disabling autocommit and checks
			$wpdb->query("SET autocommit = 0;");
			$wpdb->query("SET unique_checks = 0;");
			$wpdb->query("SET foreign_key_checks = 0;");
			
			// Read and execute SQL using statement boundaries
			$handle = fopen( $sql_file, 'r' );
			if ( $handle ) {
				$query = '';
				while ( ($line = fgets( $handle )) !== false ) {
					$trimmed = trim($line);
					if ( empty($trimmed) || strpos($trimmed, '--') === 0 ) continue;
					
					// Detect start of new statement
					if ( strpos($line, 'DROP ') === 0 || strpos($line, 'CREATE ') === 0 || strpos($line, 'INSERT ') === 0 ) {
						// Execute accumulated query
						if ( ! empty($query) ) {
							$wpdb->query($query);
						}
						$query = '';
					}
					
					// Replace prefixes safely
					if ( $old_prefix !== $wpdb->prefix ) {
						$line = preg_replace("/\`{$old_prefix}([a-zA-Z0-9_]+)\`/", "`{$wpdb->prefix}$1`", $line);
					}
					
					$query .= $line;
				}
				// Execute last query
				if ( ! empty($query) ) {
					$wpdb->query($query);
				}
				fclose( $handle );
				
				// Commit the transaction
				$wpdb->query("COMMIT;");
				$wpdb->query("SET autocommit = 1;");
				$wpdb->query("SET unique_checks = 1;");
				$wpdb->query("SET foreign_key_checks = 1;");
			} else {
				@ob_end_clean(); wp_send_json_error( 'Falha ao ler o arquivo de banco de dados.' );
			}
			
			// Fix User Roles & Capabilities prefixes
			if ( $old_prefix !== $wpdb->prefix ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_name = %s WHERE option_name = %s", $wpdb->prefix . 'user_roles', $old_prefix . 'user_roles' ) );
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s", $wpdb->prefix . 'capabilities', $old_prefix . 'capabilities' ) );
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s", $wpdb->prefix . 'user_level', $old_prefix . 'user_level' ) );
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s", $wpdb->prefix . 'dashboard_quick_press_last_post_id', $old_prefix . 'dashboard_quick_press_last_post_id' ) );
			}
			
			// Update site URL if it changed
			$new_url = file_exists($this->temp_dir . 'hub_dest_url.txt') ? file_get_contents($this->temp_dir . 'hub_dest_url.txt') : '';
			if ( empty($new_url) ) {
				// Fallback if not saved in init
				$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
				$new_url = $protocol . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])); // /wp-admin/admin-ajax.php -> /
				$new_url = rtrim($new_url, '/');
			}
			
			if ( ! empty( $new_url ) ) {
				// 1. Unconditionally overwrite siteurl and home to guarantee login access
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = 'home' OR option_name = 'siteurl'", $new_url ) );
				
				if ( ! empty( $manifest['original_url'] ) && $manifest['original_url'] !== $new_url ) {
					// 2. Run full robust serialized search and replace (All-in-One WP Migration style)
					$this->run_full_database_replace( $manifest['original_url'], $new_url );
					
					// 3. Keep standard GUID replace just for good measure (GUIDs aren't serialized)
					$old_http = str_replace('https://', 'http://', $manifest['original_url']);
					$old_https = str_replace('http://', 'https://', $manifest['original_url']);
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET guid = replace(guid, %s, %s)", $old_http, $new_url ) );
					$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET guid = replace(guid, %s, %s)", $old_https, $new_url ) );
				}
			}

			// Cleanup
			$this->delete_dir( $extract_dir );
			@unlink( $filepath );
			@ob_end_clean(); wp_send_json_success( array( 'next_step' => 'done', 'progress' => 100, 'msg' => 'Restauração 100% concluída!' ) );
		}

		if ( 'files' === $step ) {
			$source_wp_content = $extract_dir . 'wp-content/';
			if ( is_dir( $source_wp_content ) ) {
				// Instant rename move to bypass ANY execution time limit
				$this->instant_move($source_wp_content, WP_CONTENT_DIR);
			}
			
			// Protect localhost from 500 Internal Server Errors caused by security plugins
			// like Really Simple Security that use outdated Apache 2.2 syntax in the uploads folder
			$uploads_htaccess = WP_CONTENT_DIR . '/uploads/.htaccess';
			if ( file_exists($uploads_htaccess) ) {
				@rename($uploads_htaccess, WP_CONTENT_DIR . '/uploads/.htaccess.bak');
			}
			
			@ob_end_clean(); wp_send_json_success( array( 'next_step' => 'database', 'progress' => 70, 'msg' => 'Arquivos copiados! Importando banco de dados...' ) );
		}

		@ob_end_clean(); wp_send_json_error( 'Step inválido.' );
	}

	// -------------------------------------------------------------------------------- //
	// LEGACY BACKUPS (Exclusão da arquitetura antiga)
	// -------------------------------------------------------------------------------- //

	public function get_legacy_backups() {
		$files = [];
		foreach ( array( $this->legacy_db_dir, $this->legacy_full_dir ) as $dir ) {
			if ( file_exists( $dir ) ) {
				$found = glob( $dir . '*.*' );
				if ( $found ) {
					foreach ( $found as $f ) {
						if ( in_array( pathinfo( $f, PATHINFO_EXTENSION ), array('htaccess','php') ) ) continue;
						$files[] = array(
							'path' => $f,
							'name' => basename( $f ),
							'size' => size_format( filesize( $f ) ),
							'date' => filemtime( $f )
						);
					}
				}
			}
		}
		usort( $files, function($a, $b) { return $b['date'] - $a['date']; } );
		return $files;
	}

	public function handle_download_legacy() {
		if ( ! current_user_can( 'hub_vitagencia_manage_internal' ) ) wp_die();
		check_admin_referer( 'hub_legacy_action', 'hub_nonce' );

		$file_name = sanitize_text_field( $_POST['file_name'] ?? '' );
		$file_path = '';

		// Busca nas pastas legacy se o arquivo existe e o nome bate
		foreach ( array( $this->legacy_db_dir, $this->legacy_full_dir ) as $dir ) {
			$check = $dir . basename( $file_name );
			if ( file_exists( $check ) ) {
				$file_path = $check;
				break;
			}
		}

		if ( ! $file_path ) wp_die( 'Arquivo legacy não encontrado.' );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		readfile( $file_path );
		exit;
	}

	public function handle_delete_legacy() {
		if ( ! current_user_can( 'hub_vitagencia_manage_internal' ) ) wp_die();
		check_admin_referer( 'hub_legacy_action', 'hub_nonce' );

		$file_name = sanitize_text_field( $_POST['file_name'] ?? '' );

		if ( $file_name === 'ALL' ) {
			foreach ( array( $this->legacy_db_dir, $this->legacy_full_dir ) as $dir ) {
				if ( file_exists( $dir ) ) {
					$found = glob( $dir . '*.*' );
					if ( $found ) {
						foreach ( $found as $f ) {
							if ( in_array( pathinfo( $f, PATHINFO_EXTENSION ), array('htaccess','php') ) ) continue;
							@unlink( $f );
						}
					}
				}
			}
		} else {
			foreach ( array( $this->legacy_db_dir, $this->legacy_full_dir ) as $dir ) {
				$check = $dir . basename( $file_name );
				if ( file_exists( $check ) ) {
					@unlink( $check );
					break;
				}
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=hub-vitagencia&tab=backup&message=legacy_deleted' ) );
		exit;
	}
}
