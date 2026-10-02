<?php
namespace Hub\Core\Services\Conversions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Obtains and caches a Data Manager API OAuth access token. */
class GoogleAdsOAuthService {
	const TRANSIENT_KEY = 'hub_gads_datamanager_access_token';

	private $config;

	public function __construct( array $config ) {
		$this->config = $config;
	}

	/**
	 * @return string|\WP_Error
	 */
	public function get_access_token( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}

		foreach ( array( 'client_id', 'client_secret', 'refresh_token' ) as $field ) {
			if ( empty( $this->config[ $field ] ) ) {
				return new \WP_Error( 'hub_google_ads_oauth_config', 'OAuth do Google Ads incompleto: configure Client ID, Client Secret e Refresh Token.' );
			}
		}

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 15,
			'body'    => array(
				'client_id'     => $this->config['client_id'],
				'client_secret' => $this->config['client_secret'],
				'refresh_token' => $this->config['refresh_token'],
				'grant_type'    => 'refresh_token',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || empty( $body['access_token'] ) ) {
			return new \WP_Error( 'hub_google_ads_oauth_failed', ! empty( $body['error_description'] ) ? sanitize_text_field( $body['error_description'] ) : 'Nao foi possivel obter o access token do Google.' );
		}

		$ttl = max( 60, min( 55 * MINUTE_IN_SECONDS, (int) ( $body['expires_in'] ?? 3600 ) - 60 ) );
		set_transient( self::TRANSIENT_KEY, $body['access_token'], $ttl );
		return $body['access_token'];
	}

	public static function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
