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
 * Tabela nativa do WP para a Agenda / Sessões / Compromissos.
 */
class AgendaListTable extends \WP_List_Table {

	private $profile;

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'event',
				'plural'   => 'events',
				'ajax'     => false,
			)
		);
		$this->profile = ProfileManager::get_instance()->get_active_profile();
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'event_date' => 'Data',
			'event_time' => 'Horário',
			'title'      => 'Título / Paciente / Projeto',
			'status'     => 'Status',
			'created_at' => 'Agendado em',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'event_date' => array( 'event_date', true ),
			'event_time' => array( 'event_time', false ),
		);
	}

	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'event_date':
				return esc_html( date_i18n( 'd/m/Y', strtotime( $item['event_date'] ) ) );
			case 'event_time':
				return esc_html( date( 'H:i', strtotime( $item['event_time'] ) ) );
			case 'status':
				$statuses = $this->profile && isset( $this->profile->get_statuses()['event'] ) ? $this->profile->get_statuses()['event'] : array();
				$label    = isset( $statuses[ $item['status'] ] ) ? $statuses[ $item['status'] ] : ucfirst( $item['status'] );
				return sprintf( '<span class="hub-status-pill status-%s">%s</span>', esc_attr( $item['status'] ), esc_html( $label ) );
			case 'created_at':
				return esc_html( date_i18n( 'd/m/Y H:i', strtotime( $item['created_at'] ) ) );
			default:
				return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
		}
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="event_id[]" value="%d" />', $item['id'] );
	}

	protected function column_title( $item ) {
		$page     = isset( $_REQUEST['page'] ) ? sanitize_key( $_REQUEST['page'] ) : 'hub-agenda';
		$edit_url = admin_url( 'admin.php?page=' . $page . '&action=edit&id=' . $item['id'] );
		$del_url  = admin_url( 'admin.php?page=' . $page . '&action=delete&id=' . $item['id'] . '&_wpnonce=' . wp_create_nonce( 'delete_event_' . $item['id'] ) );

		$actions = array(
			'edit'   => sprintf( '<a href="%s">Editar / Atualizar Status</a>', esc_url( $edit_url ) ),
			'delete' => sprintf( '<a href="%s" onclick="return confirm(\'Remover agendamento?\')">Cancelar/Excluir</a>', esc_url( $del_url ) ),
		);

		return sprintf( '<strong><a href="%s">%s</a></strong> %s', esc_url( $edit_url ), esc_html( $item['title'] ), $this->row_actions( $actions ) );
	}

	public function prepare_items() {
		$table    = Database::table( 'events' );
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
			$where .= $db->prepare( ' AND (title LIKE %s OR notes LIKE %s)', '%' . $db->esc_like( $search ) . '%', '%' . $db->esc_like( $search ) . '%' );
		}

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'event_date';
		$order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC';

		$total_items = $db->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
		$data        = $db->get_results( "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}, event_time {$order} LIMIT {$per_page} OFFSET {$offset}", ARRAY_A );

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
