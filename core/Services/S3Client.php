<?php
namespace Hub\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cliente REST simples para Amazon S3 utilizando cURL nativo.
 */
class S3Client {

	private $bucket;
	private $region;
	private $signer;
	private $host;

	public function __construct( $access_key, $secret_key, $region, $bucket ) {
		$this->bucket = $bucket;
		$this->region = $region;
		$this->host   = $bucket . '.s3.' . $region . '.amazonaws.com';
		$this->signer = new AwsV4Signer( $access_key, $secret_key, $region, 's3' );
	}

	private function request( $method, $path, $query = [], $file_pointer = null, $file_size = 0, $xml_payload = null ) {
		$url = 'https://' . $this->host . '/' . ltrim( $path, '/' );
		if ( ! empty( $query ) ) {
			$url .= '?' . http_build_query( $query );
		}

		$payload_hash = 'UNSIGNED-PAYLOAD';
		if ( $xml_payload !== null ) {
			$payload_hash = hash( 'sha256', $xml_payload );
		}

		$headers = $this->signer->sign( $method, $this->host, $path, $query, $payload_hash );

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_HEADER, true ); // Para ler a ETag no UploadPart

		if ( $file_pointer ) {
			curl_setopt( $ch, CURLOPT_PUT, true );
			curl_setopt( $ch, CURLOPT_INFILE, $file_pointer );
			curl_setopt( $ch, CURLOPT_INFILESIZE, $file_size );
		} elseif ( $xml_payload !== null ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $xml_payload );
		}

		$response = curl_exec( $ch );
		
		if ( curl_errno( $ch ) ) {
			$error = curl_error( $ch );
			curl_close( $ch );
			return new \WP_Error( 'curl_error', 'cURL Error: ' . $error );
		}

		$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		$http_code   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$header_text = substr( $response, 0, $header_size );
		$body        = substr( $response, $header_size );
		
		curl_close( $ch );

		if ( $http_code >= 400 ) {
			return new \WP_Error( 's3_error', "HTTP {$http_code} - " . $body );
		}

		return [
			'code'    => $http_code,
			'headers' => $header_text,
			'body'    => $body,
		];
	}

	public function putObject( $key, $filepath ) {
		$fp = fopen( $filepath, 'rb' );
		if ( ! $fp ) return new \WP_Error( 'file_error', 'Cannot read file.' );
		
		$size = filesize( $filepath );
		$res = $this->request( 'PUT', $key, [], $fp, $size );
		fclose( $fp );
		return $res;
	}

	public function createMultipartUpload( $key ) {
		$res = $this->request( 'POST', $key, [ 'uploads' => '' ] );
		if ( is_wp_error( $res ) ) return $res;

		$xml = simplexml_load_string( $res['body'] );
		if ( ! $xml || ! isset( $xml->UploadId ) ) {
			return new \WP_Error( 'xml_error', 'Failed to parse UploadId' );
		}
		return (string) $xml->UploadId;
	}

	public function uploadPart( $key, $uploadId, $partNumber, $filepath, $offset, $length ) {
		$fp = fopen( $filepath, 'rb' );
		if ( ! $fp ) return new \WP_Error( 'file_error', 'Cannot read file.' );
		
		fseek( $fp, $offset );
		
		$query = [
			'partNumber' => $partNumber,
			'uploadId'   => $uploadId,
		];

		$res = $this->request( 'PUT', $key, $query, $fp, $length );
		fclose( $fp );
		
		if ( is_wp_error( $res ) ) return $res;

		// Extract ETag from headers
		if ( preg_match( '/^ETag:\s*"?([^"\r\n]+)"?/im', $res['headers'], $matches ) ) {
			return $matches[1];
		}

		return new \WP_Error( 'etag_missing', 'ETag not found in response headers.' );
	}

	public function completeMultipartUpload( $key, $uploadId, $parts ) {
		$xml = "<CompleteMultipartUpload>\n";
		foreach ( $parts as $part ) {
			$xml .= "  <Part>\n";
			$xml .= "    <PartNumber>{$part['PartNumber']}</PartNumber>\n";
			$xml .= "    <ETag>\"{$part['ETag']}\"</ETag>\n"; // S3 needs quotes around ETag if we extracted it without them
			$xml .= "  </Part>\n";
		}
		$xml .= "</CompleteMultipartUpload>";

		$query = [ 'uploadId' => $uploadId ];
		return $this->request( 'POST', $key, $query, null, 0, $xml );
	}

	public function abortMultipartUpload( $key, $uploadId ) {
		$query = [ 'uploadId' => $uploadId ];
		return $this->request( 'DELETE', $key, $query );
	}

	public function deleteObject( $key ) {
		return $this->request( 'DELETE', $key );
	}

	public function listObjectsV2( $prefix ) {
		$query = [
			'list-type' => '2',
			'prefix'    => $prefix,
		];
		$res = $this->request( 'GET', '', $query );
		if ( is_wp_error( $res ) ) return $res;

		$xml = simplexml_load_string( $res['body'] );
		$objects = [];
		if ( $xml && isset( $xml->Contents ) ) {
			foreach ( $xml->Contents as $content ) {
				$objects[] = [
					'Key'          => (string) $content->Key,
					'LastModified' => (string) $content->LastModified,
				];
			}
		}
		return $objects;
	}
}
