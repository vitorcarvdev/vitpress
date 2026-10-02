<?php
namespace Hub\Core\Services;

use Hub\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serviço responsável por registrar logs de tentativas de envio de Conversões Offline.
 * Desacoplado de qualquer plataforma específica (Google Ads, Meta Ads, etc).
 */
class ConversionLogService {

	/**
	 * Registra uma tentativa de conversão offline no banco de dados.
	 *
	 * @param int    $lead_id         ID do Lead.
	 * @param string $lead_name       Nome do Lead (para cache de exibição rápida).
	 * @param string $platform        Ex: 'Google Ads', 'Meta Ads'
	 * @param string $conversion_type Ex: 'Lead', 'Cliente'
	 * @param string $status          Ex: 'success', 'error'
	 * @param string $api_response    Resposta crua da API (geralmente JSON).
	 * @param string $request_payload Payload exato enviado para a API.
	 * @param string $error_code      Código de erro (se houver).
	 * @param string $gclid           Parâmetro de clique (GCLID/FBCLID/WBRAID) utilizado.
	 * @return bool|int Retorna o ID inserido ou false em caso de falha.
	 */
	public static function log_conversion( $lead_id, $lead_name, $platform, $conversion_type, $status, $api_response = '', $request_payload = '', $error_code = '', $gclid = '' ) {
		$table = Database::table( 'conversions_log' );
		
		return Database::db()->insert(
			$table,
			array(
				'lead_id'         => absint( $lead_id ),
				'lead_name'       => sanitize_text_field( $lead_name ),
				'platform'        => sanitize_text_field( $platform ),
				'conversion_type' => sanitize_text_field( $conversion_type ),
				'status'          => sanitize_text_field( $status ),
				'request_payload' => $request_payload,
				'api_response'    => $api_response,
				'error_code'      => sanitize_text_field( $error_code ),
				'gclid'           => sanitize_text_field( $gclid ),
				'created_at'      => current_time( 'mysql' ),
			),
			array(
				'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
			)
		);
	}
}
