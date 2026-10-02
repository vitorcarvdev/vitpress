<?php
namespace Hub\Modules\Agenda;

use Hub\Modules\AbstractModule;
use Hub\Core\Database;
use Hub\Core\ProfileManager;
use Hub\Admin\ListTables\AgendaListTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Módulo de Agenda e Compromissos.
 */
class AgendaModule extends AbstractModule {

	public function get_id() {
		return 'agenda';
	}

	public function get_title() {
		return 'Agenda';
	}

	public function get_order() {
		return 4;
	}

	public function init() {
		add_action( 'admin_post_hub_save_event', array( $this, 'handle_save_event' ) );
	}

	public function render() {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		$profile = ProfileManager::get_instance()->get_active_profile();

		if ( 'delete' === $action && $id > 0 ) {
			check_admin_referer( 'delete_event_' . $id );
			Database::db()->delete( Database::table( 'events' ), array( 'id' => $id ), array( '%d' ) );
			echo '<div class="notice notice-success is-dismissible"><p>Agendamento excluído!</p></div>';
			$action = 'list';
		}

		if ( 'new' === $action || 'edit' === $action ) {
			$event = null;
			if ( $id > 0 ) {
				$event = Database::db()->get_row( Database::db()->prepare( "SELECT * FROM " . Database::table( 'events' ) . " WHERE id = %d", $id ) );
			}

			// Carrega lista de clientes para vincular ao agendamento
			$clients = Database::db()->get_results( "SELECT id, name FROM " . Database::table( 'clients' ) . " ORDER BY name ASC" );

			require_once HUB_PATH . 'admin/views/agenda-form.php';
			return;
		}

		$list_table = new AgendaListTable();
		$list_table->prepare_items();

		require_once HUB_PATH . 'admin/views/agenda-list.php';
	}

	public function handle_save_event() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}

		check_admin_referer( 'hub_save_event_nonce', 'hub_nonce' );

		$id         = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$client_id  = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;
		$title      = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
		$event_date = isset( $_POST['event_date'] ) ? sanitize_text_field( $_POST['event_date'] ) : current_time( 'Y-m-d' );
		$event_time = isset( $_POST['event_time'] ) ? sanitize_text_field( $_POST['event_time'] ) : '09:00:00';
		$status     = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'agendado';
		$notes      = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

		$profile      = ProfileManager::get_instance()->get_active_profile();
		$profile_type = $profile ? $profile->get_id() : 'saude';

		// Se selecionou um cliente e não digitou o título, puxa o nome do cliente
		if ( empty( $title ) && $client_id > 0 ) {
			$client_name = Database::db()->get_var( Database::db()->prepare( "SELECT name FROM " . Database::table( 'clients' ) . " WHERE id = %d", $client_id ) );
			if ( $client_name ) {
				$title = 'Consulta/Sessão: ' . $client_name;
			}
		}

		$data = array(
			'profile_type' => $profile_type,
			'client_id'    => $client_id > 0 ? $client_id : null,
			'title'        => $title,
			'event_date'   => $event_date,
			'event_time'   => $event_time,
			'status'       => $status,
			'notes'        => $notes,
		);

		if ( $id > 0 ) {
			Database::db()->update( Database::table( 'events' ), $data, array( 'id' => $id ) );
			Database::log_history( 'event', $id, 'edit', 'Agendamento atualizado.' );
		} else {
			Database::db()->insert( Database::table( 'events' ), $data );
			$new_id = Database::db()->insert_id;
			Database::log_history( 'event', $new_id, 'create', 'Novo agendamento criado.' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=hub-agenda&message=saved' ) );
		exit;
	}
}
