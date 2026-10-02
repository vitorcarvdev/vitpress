<?php
namespace Hub\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utilitário para assinar requisições REST da AWS utilizando AWS Signature Version 4.
 */
class AwsV4Signer {

	private $access_key;
	private $secret_key;
	private $region;
	private $service;

	public function __construct( $access_key, $secret_key, $region = 'sa-east-1', $service = 's3' ) {
		$this->access_key = $access_key;
		$this->secret_key = $secret_key;
		$this->region     = $region;
		$this->service    = $service;
	}

	/**
	 * Retorna os headers necessários para a requisição autenticada.
	 *
	 * @param string $method GET, PUT, POST, DELETE, etc.
	 * @param string $host   ex: bucketname.s3.sa-east-1.amazonaws.com
	 * @param string $path   ex: /caminho/do/arquivo.zip
	 * @param array  $query  Query string parameters.
	 * @param string $payload_hash SHA256 do corpo da requisição ou 'UNSIGNED-PAYLOAD'
	 * @param array  $extra_headers Cabeçalhos adicionais que devem ser assinados (ex: x-amz-acl)
	 * @return array Headers (incluindo Authorization, x-amz-date e x-amz-content-sha256)
	 */
	public function sign( $method, $host, $path, $query = [], $payload_hash = 'UNSIGNED-PAYLOAD', $extra_headers = [] ) {
		$amz_date = gmdate( 'Ymd\THis\Z' );
		$date_stamp = gmdate( 'Ymd' );

		// 1. Headers que serão assinados
		$headers = [
			'host'                 => $host,
			'x-amz-content-sha256' => $payload_hash,
			'x-amz-date'           => $amz_date,
		];
		
		foreach ( $extra_headers as $k => $v ) {
			$headers[ strtolower( $k ) ] = $v;
		}

		ksort( $headers );

		$canonical_headers = '';
		$signed_headers    = [];
		foreach ( $headers as $k => $v ) {
			$canonical_headers .= $k . ':' . trim( $v ) . "\n";
			$signed_headers[]  = $k;
		}
		$signed_headers_str = implode( ';', $signed_headers );

		// 2. Query String Canonical
		ksort( $query );
		$canonical_query = [];
		foreach ( $query as $k => $v ) {
			$canonical_query[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
		}
		$canonical_query_str = implode( '&', $canonical_query );

		// 3. Canonical Request
		$canonical_uri = '/' . ltrim( $path, '/' );
		
		// S3 não normaliza URIs da mesma forma que outros serviços (não aplica rawurlencode nas barras)
		// A AWS pede para dar rawurlencode em cada segmento do caminho.
		$uri_segments = explode( '/', ltrim( $canonical_uri, '/' ) );
		$uri_segments = array_map( 'rawurlencode', $uri_segments );
		$canonical_uri = '/' . implode( '/', $uri_segments );

		$canonical_request = $method . "\n"
			. $canonical_uri . "\n"
			. $canonical_query_str . "\n"
			. $canonical_headers . "\n"
			. $signed_headers_str . "\n"
			. $payload_hash;

		// 4. String to Sign
		$algorithm = 'AWS4-HMAC-SHA256';
		$credential_scope = $date_stamp . '/' . $this->region . '/' . $this->service . '/aws4_request';
		$string_to_sign = $algorithm . "\n"
			. $amz_date . "\n"
			. $credential_scope . "\n"
			. hash( 'sha256', $canonical_request );

		// 5. Calculate Signature
		$k_secret   = 'AWS4' . $this->secret_key;
		$k_date     = hash_hmac( 'sha256', $date_stamp, $k_secret, true );
		$k_region   = hash_hmac( 'sha256', $this->region, $k_date, true );
		$k_service  = hash_hmac( 'sha256', $this->service, $k_region, true );
		$k_signing  = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
		
		$signature  = hash_hmac( 'sha256', $string_to_sign, $k_signing );

		// 6. Build Authorization Header
		$authorization = $algorithm . ' '
			. 'Credential=' . $this->access_key . '/' . $credential_scope . ', '
			. 'SignedHeaders=' . $signed_headers_str . ', '
			. 'Signature=' . $signature;

		// 7. Retorna array com todos os headers necessários
		$final_headers = [];
		foreach ( $headers as $k => $v ) {
			$final_headers[] = $k . ': ' . $v;
		}
		$final_headers[] = 'Authorization: ' . $authorization;

		return $final_headers;
	}
}
