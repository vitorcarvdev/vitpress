<?php
namespace Hub\Core\Services\Conversions\Events;

use Hub\Core\Database;
use Hub\Core\Services\Conversions\ConversionProviderInterface;
use Hub\Core\Services\Conversions\GoogleAdsProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LeadCreatedEvent {

	/**
	 * @var ConversionProviderInterface[]
	 */
	private $providers = [];

	public function __construct() {
		$this->register_providers();
	}

	private function register_providers() {
		$settings = get_option( 'hub_settings', [] );
		$integrations = $settings['integrations'] ?? [];

		if ( isset( $integrations['google_ads'] ) && ! empty( $integrations['google_ads']['status'] ) && 'disabled' !== $integrations['google_ads']['status'] ) {
			$this->providers[] = new GoogleAdsProvider( $integrations['google_ads'] );
		}
		// Future: MetaAdsProvider, etc.
	}

	public function handle( $lead_id ) {
		if ( empty( $this->providers ) ) {
			return;
		}

		$db = Database::db();
		$lead = $db->get_row( $db->prepare( "SELECT * FROM " . Database::table( 'leads' ) . " WHERE id = %d", $lead_id ), ARRAY_A );

		if ( ! $lead ) {
			return;
		}

		foreach ( $this->providers as $provider ) {
			$provider->send_lead_conversion( $lead );
		}
	}
}
