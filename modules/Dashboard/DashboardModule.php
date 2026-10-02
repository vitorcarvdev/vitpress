<?php
namespace Hub\Modules\Dashboard;

use Hub\Modules\AbstractModule;
use Hub\Core\Database;
use Hub\Core\ProfileManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Módulo Dashboard Simples e de Alta Performance.
 */
class DashboardModule extends AbstractModule {

	public function get_id() {
		return 'dashboard';
	}

	public function get_title() {
		return 'Painel Geral';
	}

	public function get_order() {
		return 1;
	}

	public function init() {
		// Hooks do dashboard se necessário
	}

	public function render() {
		$profile    = ProfileManager::get_instance()->get_active_profile();
		$profile_id = $profile ? $profile->get_id() : '';

		$today = current_time( 'Y-m-d' );
		$db    = Database::db();

		// Metrics
		$table_events = Database::table( 'events' );
		$table_leads  = Database::table( 'leads' );
		$table_clients = Database::table( 'clients' );

		// 1. Sessões hoje
		$events_today = (int) $db->get_var(
			$db->prepare(
				"SELECT COUNT(*) FROM {$table_events} WHERE event_date = %s",
				$today
			)
		);

		// 2. Novos Leads (mês / total ativos)
		$new_leads = (int) $db->get_var(
			"SELECT COUNT(*) FROM {$table_leads} WHERE status = 'novo'"
		);

		// 3. Conversões pendentes (Leads em fase de fechamento)
		$pending_conversions = (int) $db->get_var(
			"SELECT COUNT(*) FROM {$table_leads} WHERE status = 'em_atendimento' OR status = 'em_elaboracao' OR status = 'em_qualificacao'"
		);

		// 4. Clientes aguardando retorno
		$clients_waiting = (int) $db->get_var(
			"SELECT COUNT(*) FROM {$table_clients} WHERE status = 'retorno' OR status = 'pendencia'"
		);

		// Lista de compromissos de hoje
		$today_schedule = $db->get_results(
			$db->prepare(
				"SELECT * FROM {$table_events} WHERE event_date = %s ORDER BY event_time ASC LIMIT 10",
				$today
			)
		);

		require_once HUB_PATH . 'admin/views/dashboard.php';
	}
}
