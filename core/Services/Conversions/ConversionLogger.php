<?php
namespace Hub\Core\Services\Conversions;
use Hub\Core\Database;
if ( ! defined( 'ABSPATH' ) ) { exit; }

class ConversionLogger {
	public static function log( $args = array() ) {
		$table = Database::table( 'conversions_log' );
		$data = wp_parse_args( $args, array( 'lead_id'=>0, 'lead_name'=>'', 'platform'=>'', 'conversion_type'=>'', 'conversion_action'=>'', 'status'=>'', 'endpoint'=>'', 'http_status'=>'', 'request_payload'=>'', 'api_response'=>'', 'error_code'=>'', 'error_message'=>'', 'gclid'=>'', 'request_id'=>'', 'transaction_id'=>'', 'events_accepted'=>0, 'events_rejected'=>0, 'attempts'=>0, 'next_retry_at'=>null, 'processed_at'=>null ) );
		$row = array( 'lead_id'=>absint($data['lead_id']), 'lead_name'=>sanitize_text_field($data['lead_name']), 'platform'=>sanitize_text_field($data['platform']), 'conversion_type'=>sanitize_text_field($data['conversion_type']), 'conversion_action'=>sanitize_text_field($data['conversion_action']), 'status'=>sanitize_text_field($data['status']), 'endpoint'=>sanitize_text_field($data['endpoint']), 'http_status'=>sanitize_text_field($data['http_status']), 'request_payload'=>self::mask_secrets($data['request_payload']), 'api_response'=>self::mask_secrets($data['api_response']), 'error_code'=>sanitize_text_field($data['error_code']), 'error_message'=>sanitize_textarea_field($data['error_message']), 'gclid'=>sanitize_text_field($data['gclid']), 'request_id'=>sanitize_text_field($data['request_id']), 'transaction_id'=>sanitize_text_field($data['transaction_id']), 'events_accepted'=>absint($data['events_accepted']), 'events_rejected'=>absint($data['events_rejected']), 'attempts'=>absint($data['attempts']), 'next_retry_at'=>$data['next_retry_at'] ? sanitize_text_field($data['next_retry_at']) : null, 'processed_at'=>$data['processed_at'] ? sanitize_text_field($data['processed_at']) : null, 'created_at'=>current_time('mysql') );
		return Database::db()->insert( $table, $row );
	}

	public static function is_duplicate( $transaction_id, $conversion_action ) {
		$db = Database::db(); $table = Database::table( 'conversions_log' );
		return (bool) $db->get_var( $db->prepare( "SELECT id FROM {$table} WHERE transaction_id = %s AND conversion_action = %s AND status IN ('processing','success','partial_success','validation','retry') LIMIT 1", $transaction_id, $conversion_action ) );
	}

	public static function update_log( $id, array $args ) {
		$allowed = array( 'status','http_status','api_response','error_code','error_message','events_accepted','events_rejected','attempts','next_retry_at','processed_at','request_id' ); $data = array();
		foreach ( $allowed as $field ) { if ( array_key_exists( $field, $args ) ) { $data[$field] = in_array($field,array('events_accepted','events_rejected','attempts'),true) ? absint($args[$field]) : ( in_array($field,array('api_response','error_message'),true) ? self::mask_secrets($args[$field]) : sanitize_text_field($args[$field]) ); } }
		return $data ? Database::db()->update( Database::table('conversions_log'), $data, array('id'=>absint($id)) ) : false;
	}

	private static function mask_secrets( $payload ) {
		if ( is_array( $payload ) ) { $payload = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ); }
		if ( ! is_string( $payload ) ) { return $payload; }
		return preg_replace( '/("(?:developer-token|authorization|client_secret|refresh_token|access_token)"\\s*:\\s*")[^"]+("|$)/i', '${1}***MASKED***${2}', $payload );
	}
}
