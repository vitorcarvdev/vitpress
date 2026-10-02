<?php
namespace Hub\Core\Services\Conversions;

use Hub\Core\Services\Conversions\Events\LeadCreatedEvent;
use Hub\Core\Services\Conversions\Events\ClientWonEvent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serviço responsável por registrar os hooks de conversões e disparar os eventos.
 */
class ConversionsService {

	private static $instance = null;

	private function __construct() {}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'hub/lead_created', array( $this, 'handle_lead_created' ), 10, 1 );
		add_action( 'hub/pipeline_client_won', array( $this, 'handle_pipeline_won' ), 10, 1 );
	}

	public function handle_lead_created( $lead_id ) {
		$event = new LeadCreatedEvent();
		$event->handle( $lead_id );
	}

	public function handle_pipeline_won( $lead_id ) {
		$event = new ClientWonEvent();
		// O valor padrão no Pipeline antigo era 0, mas podemos obter da lead->revenue_value se quisermos
		$event->handle( $lead_id, 0.0 );
	}
}
