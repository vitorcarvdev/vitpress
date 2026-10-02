<?php
namespace Hub\Core\Services\Conversions;

if ( ! defined( "ABSPATH" ) ) { exit; }

interface ConversionProviderInterface {
	public function is_configured();
	public function send_event( $event_name, array $lead_data, array $event_data = array(), $transaction_id = "" );
}

