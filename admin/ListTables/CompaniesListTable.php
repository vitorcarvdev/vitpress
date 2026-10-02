<?php
namespace Hub\Admin\ListTables;

use Hub\Core\Database;
use Hub\Core\ProfileManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Tabela nativa do WP para listagem de Empresas.
 */
class CompaniesListTable extends \WP_List_Table {

	private $profile;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'company',
				'plural'   => 'companies',
				'ajax'     => false,
			)
		);
		$this->profile = ProfileManager::get_instance()->get_active_profile();
	}

	public function get_columns() {
		return array(
			'cb'          => '<input type="checkbox" />',
			'name'        => 'Razão Social / Fantasia',
			'document'    => 'CNPJ/CPF',
			'person_name' => 'Responsável (Lead)',
			'phone'       => 'Telefone',
			'city'        => 'Cidade',
			'status'      => 'Status',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name' => array( 'name', false ),
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'name':
			case 'email':
			case 'phone':
			case 'status':
			case 'city':
			case 'document':
				return esc_html( $item[ $column_name ] );
			case 'person_name':
				return $item['person_name'] ? esc_html( $item['person_name'] ) : '-';
			default:
				return '-';
		}
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="company_id[]" value="%d" />', $item['id'] );
	}

	protected function column_name( $item ) {
		// When clicking a company, they go to the Person's edit form where the Empresa tab is
		$edit_link = esc_url( admin_url( "admin.php?page=hub-persons&action=edit&id=" . $item['lead_id'] . "#tab-empresa" ) );
		$del_link  = wp_nonce_url( admin_url( "admin.php?page=hub-empresas&action=delete&id=" . $item['id'] ), 'delete_company_' . $item['id'] );

		$actions = array(
			'edit'   => sprintf( '<a href="%s">Editar Lead/Empresa</a>', $edit_link ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'Tem certeza que deseja desvincular/excluir a Empresa?\');">Excluir</a>', $del_link ),
		);

		return sprintf(
			'<strong><a href="%1$s">%2$s</a></strong>%3$s',
			$edit_link,
			esc_html( $item['name'] ),
			$this->row_actions( $actions )
		);
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$db    = Database::db();
		$table = Database::table( 'clients' );
		$leads = Database::table( 'leads' );
		
		$profile      = $this->profile;
		$profile_type = $profile ? $profile->get_id() : 'saude';

		$per_page = 15;
		$paged    = isset( $_REQUEST['paged'] ) ? max( 1, absint( $_REQUEST['paged'] ) ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$query = "SELECT c.*, l.name as person_name FROM {$table} c LEFT JOIN {$leads} l ON c.lead_id = l.id WHERE c.profile_type = %s";
		$count_query = "SELECT COUNT(*) FROM {$table} c WHERE c.profile_type = %s";
		$query_params = array( $profile_type );

		if ( ! empty( $search ) ) {
			$search_sql = ' AND (c.name LIKE %s OR c.document LIKE %s)';
			$query .= $search_sql;
			$count_query .= $search_sql;
			$query_params[] = '%' . $db->esc_like( $search ) . '%';
			$query_params[] = '%' . $db->esc_like( $search ) . '%';
		}
		
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'id';
		$order   = isset( $_REQUEST['order'] ) && 'desc' === strtolower( $_REQUEST['order'] ) ? 'DESC' : 'ASC';
		$query .= " ORDER BY c.{$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}";

		$total_items = $db->get_var( $db->prepare( $count_query, ...$query_params ) );
		$data        = $db->get_results( $db->prepare( $query, ...$query_params ), ARRAY_A );

		$this->items = $data;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}
}
