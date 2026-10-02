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
 * Tabela nativa do WP para listagem de Leads.
 */
class PersonsListTable extends \WP_List_Table {

	private $profile;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'lead',
				'plural'   => 'leads',
				'ajax'     => false,
			)
		);
		$this->profile = ProfileManager::get_instance()->get_active_profile();
	}

	public function get_columns() {
		$lead_singular = $this->profile ? $this->profile->get_label( 'lead_singular', 'Lead' ) : 'Lead';

		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => $lead_singular,
			'email'      => 'E-mail',
			'phone'      => 'Telefone',
			'stage'      => 'Estágio do Pipeline',
			'status'     => 'Status',
			'created_at' => 'Data de Cadastro',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name'       => array( 'name', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'email':
				return esc_html( $item['email'] );
			case 'phone':
				return esc_html( $item['phone'] );
			case 'stage':
				$stages = $this->profile ? $this->profile->get_pipeline_stages() : array();
				return isset( $stages[ $item['stage'] ] ) ? esc_html( $stages[ $item['stage'] ] ) : esc_html( $item['stage'] );
			case 'status':
				return sprintf( '<span class="hub-status-pill status-%s">%s</span>', esc_attr( $item['status'] ), esc_html( ucfirst( $item['status'] ) ) );
			case 'created_at':
				return esc_html( date_i18n( 'd/m/Y H:i', strtotime( $item['created_at'] ) ) );
			default:
				return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
		}
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="lead_id[]" value="%d" />', $item['id'] );
	}

	protected function column_name( $item ) {
		$edit_link = esc_url( admin_url( "admin.php?page=hub-persons&action=edit&id=" . $item['id'] ) );
		$del_link  = wp_nonce_url( admin_url( "admin.php?page=hub-persons&action=delete&id=" . $item['id'] ), 'delete_person_' . $item['id'] );

		$actions = array(
			'edit'   => sprintf( '<a href="%s">Editar</a>', $edit_link ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'Tem certeza?\');">Excluir</a>', $del_link ),
		);

		$tracking_badge = '';
		if ( ! empty( $item['gclid'] ) || ! empty( $item['gbraid'] ) || ! empty( $item['wbraid'] ) ) {
			$tracking_badge = ' <span class="dashicons dashicons-google" title="Lead vindo do Google Ads (Possui Click ID)" style="color: #4285f4; font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span>';
		}

		return sprintf( '<strong><a href="%s">%s</a></strong>%s %s', esc_url( $edit_link ), esc_html( $item['name'] ), $tracking_badge, $this->row_actions( $actions ) );
	}

	public function prepare_items() {
		$table    = Database::table( 'leads' );
		$db       = Database::db();
		$per_page = 15;

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$paged   = isset( $_REQUEST['paged'] ) ? max( 1, absint( $_REQUEST['paged'] ) ) : 1;
		$offset  = ( $paged - 1 ) * $per_page;
		$search  = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		$where = 'WHERE 1=1';
		if ( ! empty( $search ) ) {
			$where .= $db->prepare( ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s)', '%' . $db->esc_like( $search ) . '%', '%' . $db->esc_like( $search ) . '%', '%' . $db->esc_like( $search ) . '%' );
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'id';
		$order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC';

		$total_items = $db->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
		$data        = $db->get_results( "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT {$per_page} OFFSET {$offset}", ARRAY_A );

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
