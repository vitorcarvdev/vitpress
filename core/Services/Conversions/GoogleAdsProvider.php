<?php
namespace Hub\Core\Services\Conversions;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Google Data Manager API provider for offline Google Ads conversions. */
class GoogleAdsProvider implements ConversionProviderInterface {
	private $config;
	private $oauth;

	public function __construct( array $config ) {
		$this->config = $config;
		$this->oauth  = new GoogleAdsOAuthService( $config );
	}

	public function send_lead_conversion( array $lead_data ) {
		$this->process_event( $lead_data, $this->config['conversion_lead'] ?? '', 'Lead' );
	}

	public function send_client_conversion( array $lead_data, float $value = 0.0 ) {
		$this->process_event( $lead_data, $this->config['conversion_client'] ?? '', 'Cliente', $value );
	}

	private function process_event( array $lead, $action, $type, float $value = 0.0 ) {
		$transaction_id = strtoupper( $type ) . '-' . absint( $lead['id'] ?? 0 );
		$base = array( 'lead_id' => absint( $lead['id'] ?? 0 ), 'lead_name' => $lead['name'] ?? '', 'platform' => 'Google Ads', 'conversion_type' => $type, 'conversion_action' => $action, 'transaction_id' => $transaction_id, 'gclid' => $lead['gclid'] ?? '' );
		$status = $this->config['status'] ?? 'disabled';

		if ( 'disabled' === $status ) { $this->log( $base, 'skipped', array( 'error_message' => 'Integracao desativada.' ) ); return; }
		if ( ! preg_match( '/^\d+$/', (string) $action ) ) { $this->log( $base, 'skipped', array( 'error_message' => 'Conversion Action ID invalido; informe somente o ID numerico.' ) ); return; }
		if ( empty( $this->config['customer_id'] ) ) { $this->log( $base, 'skipped', array( 'error_message' => 'Operating Account (Customer ID) nao configurado.' ) ); return; }
		if ( empty( $lead['gclid'] ) && empty( $lead['gbraid'] ) && empty( $lead['wbraid'] ) ) { $this->log( $base, 'skipped', array( 'error_message' => 'Nao enviado: GCLID, GBRAID ou WBRAID ausente.' ) ); return; }
		if ( ConversionLogger::is_duplicate( $transaction_id, $action ) ) { return; }

		$payload = $this->build_payload( $lead, $action, $transaction_id, $value, 'simulation' === $status );
		$endpoint = 'https://datamanager.googleapis.com/v1/events:ingest';
		if ( 'simulation' === $status ) {
			$this->log( $base, 'validation', array( 'endpoint' => $endpoint, 'http_status' => '200', 'request_payload' => $payload, 'api_response' => wp_json_encode( array( 'requestId' => 'SIMULATION-' . wp_generate_uuid4(), 'message' => 'Payload validado apenas localmente; configure o modo Ativo para a validacao remota.' ) ), 'error_message' => 'Modo simulacao: validateOnly=true.' ) );
			return;
		}

		$token = $this->oauth->get_access_token();
		if ( is_wp_error( $token ) ) { $this->log( $base, 'error', array( 'endpoint' => $endpoint, 'request_payload' => $payload, 'error_code' => $token->get_error_code(), 'error_message' => $token->get_error_message() ) ); return; }
		$response = $this->request( $endpoint, $payload, $token );
		if ( is_wp_error( $response ) ) { $this->log( $base, 'retry', array( 'endpoint' => $endpoint, 'request_payload' => $payload, 'error_code' => $response->get_error_code(), 'error_message' => $response->get_error_message(), 'next_retry_at' => gmdate( 'Y-m-d H:i:s', time() + 300 ) ) ); return; }
		$code = (int) wp_remote_retrieve_response_code( $response ); $body = wp_remote_retrieve_body( $response ); $data = json_decode( $body, true );
		$request_id = is_array( $data ) ? ( $data['requestId'] ?? '' ) : '';
		if ( $code >= 200 && $code < 300 && $request_id ) { $this->log( $base, 'processing', array( 'endpoint' => $endpoint, 'http_status' => (string) $code, 'request_payload' => $payload, 'api_response' => $body, 'request_id' => $request_id, 'events_accepted' => 1 ) ); return; }
		$this->log( $base, $this->is_transient_http( $code ) ? 'retry' : 'error', array( 'endpoint' => $endpoint, 'http_status' => (string) $code, 'request_payload' => $payload, 'api_response' => $body, 'error_code' => is_array( $data ) ? ( $data['error']['status'] ?? '' ) : '', 'error_message' => is_array( $data ) ? ( $data['error']['message'] ?? 'Resposta invalida da Data Manager API.' ) : 'Resposta invalida da Data Manager API.', 'next_retry_at' => $this->is_transient_http( $code ) ? gmdate( 'Y-m-d H:i:s', time() + 300 ) : '' ) );
	}

	private function build_payload( array $lead, $action, $transaction_id, $value, $validate_only ) {
		$event = array( 'transactionId' => $transaction_id, 'eventTimestamp' => gmdate( 'Y-m-d\\TH:i:s\\Z' ), 'eventSource' => 'WEB', 'adIdentifiers' => array(), 'consent' => array( 'adUserData' => strtoupper( $lead['ad_user_data_consent'] ?? '' ) === 'GRANTED' ? 'GRANTED' : 'DENIED' ) );
		foreach ( array( 'gclid', 'gbraid', 'wbraid' ) as $id ) { if ( ! empty( $lead[ $id ] ) ) { $event['adIdentifiers'][ $id ] = $lead[ $id ]; } }
		$identifiers = array();
		if ( ! empty( $lead['email'] ) ) { $identifiers[] = array( 'emailAddress' => $this->hash_data( $lead['email'] ) ); }
		if ( ! empty( $lead['phone'] ) ) { $identifiers[] = array( 'phoneNumber' => $this->hash_data( $lead['phone'], true ) ); }
		if ( $identifiers ) { $event['userData'] = array( 'userIdentifiers' => $identifiers ); }
		if ( $value > 0 ) { $event['conversionValue'] = (float) $value; $event['currency'] = sanitize_text_field( $this->config['currency'] ?? 'BRL' ); }
		$destination = array( 'operatingAccount' => array( 'accountType' => 'GOOGLE_ADS', 'accountId' => $this->account_id( $this->config['customer_id'] ) ), 'productDestinationId' => (string) $action );
		if ( ! empty( $this->config['login_account'] ) ) { $destination['loginAccount'] = array( 'accountType' => 'GOOGLE_ADS', 'accountId' => $this->account_id( $this->config['login_account'] ) ); }
		return array( 'destinations' => array( $destination ), 'events' => array( $event ), 'validateOnly' => (bool) $validate_only, 'encoding' => 'HEX' );
	}

	private function request( $endpoint, array $payload, $token ) {
		$response = wp_remote_post( $endpoint, array( 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ), 'timeout' => 20 ) );
		if ( is_wp_error( $response ) || 401 !== (int) wp_remote_retrieve_response_code( $response ) ) { return $response; }
		GoogleAdsOAuthService::clear_cache(); $token = $this->oauth->get_access_token( true );
		return is_wp_error( $token ) ? $token : wp_remote_post( $endpoint, array( 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $payload ), 'timeout' => 20 ) );
	}

	private function log( array $base, $status, array $data = array() ) { ConversionLogger::log( array_merge( $base, $data, array( 'status' => $status ) ) ); }
	private function account_id( $id ) { return preg_replace( '/\D/', '', (string) $id ); }
	private function is_transient_http( $code ) { return 0 === $code || 408 === $code || 429 === $code || $code >= 500; }
	private function hash_data( $value, $phone = false ) { $value = strtolower( trim( $value ) ); if ( $phone ) { $value = preg_replace( '/\D/', '', $value ); } return hash( 'sha256', $value ); }
}
