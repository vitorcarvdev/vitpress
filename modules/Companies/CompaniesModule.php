<?php
namespace Hub\Modules\Companies;

use Hub\Modules\AbstractModule;
use Hub\Core\Database;
use Hub\Core\ProfileManager;
use Hub\Admin\ListTables\CompaniesListTable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Módulo de Gestão de Empresas.
 */
class CompaniesModule extends AbstractModule {

	public function get_id() {
		return 'companies';
	}

	public function get_title() {
		return 'Empresas';
	}

	public function get_menu_slug() {
		return 'hub-empresas';
	}

	public function get_order() {
		return 3;
	}

	public function init() {
		// As empresas são salvas através do formulário de Pessoa (PersonsModule)
	}

	public function render() {
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
		$id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		$profile = ProfileManager::get_instance()->get_active_profile();

		if ( 'delete' === $action && $id > 0 ) {
			check_admin_referer( 'delete_company_' . $id );
			Database::db()->delete( Database::table( 'clients' ), array( 'id' => $id ), array( '%d' ) );
			echo '<div class="notice notice-success is-dismissible"><p>Empresa removida com sucesso!</p></div>';
			$action = 'list';
		}

		$list_table = new CompaniesListTable();
		$list_table->prepare_items();

		require_once HUB_PATH . 'admin/views/companies-list.php';
	}
}
