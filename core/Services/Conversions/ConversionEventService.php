<?php
namespace Hub\Core\Services\Conversions;

use Hub\Core\Database;

if ( ! defined( "ABSPATH" ) ) { exit; }

class ConversionEventService {
	private static $instance = null;
	private function __construct() {}
	public static function get_instance() {
		if ( null === self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	public function init() {
		add_action( "hub/pipeline_stage_changed", array( $this, "handle_pipeline_stage_changed" ), 10, 3 );
		add_action( "hub/lead_created", array( $this, "handle_lead_created" ), 10, 1 );
	}
	public function handle_lead_created( $lead_id ) {
		$db = Database::db(); $table = Database::table( "leads" );
		$lead = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $lead_id ), ARRAY_A );
		if ( ! $lead ) { return; }
		$this->process_conversion_event( $lead_id, $lead["stage"], "", $lead );
	}
	public function handle_pipeline_stage_changed( $lead_id, $new_stage, $old_stage ) {
		$db = Database::db(); $table = Database::table( "leads" );
		$lead = $db->get_row( $db->prepare( "SELECT * FROM {$table} WHERE id = %d", $lead_id ), ARRAY_A );
		if ( ! $lead ) { return; }
		$this->process_conversion_event( $lead_id, $new_stage, $old_stage, $lead );
	}
	private function process_conversion_event( $lead_id, $new_stage, $old_stage, $lead ) {
		$settings = get_option( "hub_settings", array() );
		$mappings = isset( $settings["conversions_mapping"] ) ? $settings["conversions_mapping"] : array();
		if ( empty( $mappings ) ) {
			$mappings = array(
				"novo_interessado" => "LEAD", "novo_contato" => "LEAD", 
				"avaliacao" => "QUALIFIED_LEAD", "cliente" => "PURCHASE", "perdido" => "NONE"
			);
		}
		$event_name = isset( $mappings[ $new_stage ] ) ? $mappings[ $new_stage ] : "NONE";
		if ( "NONE" === $event_name || empty( $event_name ) ) { return; }

		$event_data = array();
		$transaction_id = $event_name . "-" . $lead_id;

		if ( "PURCHASE" === $event_name ) {
			$db = Database::db(); $sales_table = Database::table( "sales" );
			$sale = $db->get_row( $db->prepare( "SELECT * FROM {$sales_table} WHERE lead_id = %d ORDER BY created_at DESC LIMIT 1", $lead_id ), ARRAY_A );
			if ( $sale ) {
				$event_data["value"] = (float) $sale["value"];
				$event_data["currency"] = $sale["currency"];
				$event_data["service_name"] = $sale["service_name"];
				$event_data["sale_id"] = $sale["id"];
				$transaction_id = "PURCHASE-" . $sale["id"];
			}
		}

		$log_table = Database::table( "conversions_log" );
		$existing_log = Database::db()->get_row( Database::db()->prepare(
			"SELECT id, status FROM {$log_table} WHERE transaction_id = %s LIMIT 1", $transaction_id
		) );
		if ( $existing_log ) { return; }

		$meta_provider = new \Hub\Core\Services\Conversions\MetaConversionsProvider();
		if ( $meta_provider->is_configured() ) {
			$meta_provider->send_event( $event_name, $lead, $event_data, $transaction_id );
		}
	}
}

