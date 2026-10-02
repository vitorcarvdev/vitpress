<?php
namespace Hub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrapper de manipulação e execução de queries com $wpdb.
 */
class Database {

	/**
	 * Retorna a instância global $wpdb.
	 *
	 * @return \wpdb
	 */
	public static function db() {
		global $wpdb;
		return $wpdb;
	}

	/**
	 * Nome da tabela com prefixo do WordPress.
	 *
	 * @param string $name Nome sem o prefixo 'hub_'.
	 * @return string Nome completo da tabela.
	 */
	public static function table( $name ) {
		return self::db()->prefix . 'hub_' . $name;
	}

	/**
	 * Salva ou atualiza uma configuração na tabela hub_settings.
	 *
	 * @param string $key Chave de configuração.
	 * @param mixed  $value Valor a armazenar.
	 * @return bool|int
	 */
	public static function set_setting( $key, $value ) {
		$table = self::table( 'settings' );
		$val   = maybe_serialize( $value );

		return self::db()->replace(
			$table,
			array(
				'setting_key'   => sanitize_key( $key ),
				'setting_value' => $val,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);
	}

	/**
	 * Recupera uma configuração da tabela hub_settings.
	 *
	 * @param string $key Chave.
	 * @param mixed  $default Valor padrão caso não encontrada.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = false ) {
		$table = self::table( 'settings' );
		$query = self::db()->prepare( "SELECT setting_value FROM {$table} WHERE setting_key = %s", sanitize_key( $key ) );
		$res   = self::db()->get_var( $query );

		if ( null === $res ) {
			return $default;
		}

		return maybe_unserialize( $res );
	}

	/**
	 * Registra um evento no histórico para auditoria/timeline.
	 *
	 * @param string $entity_type 'lead', 'client', 'event', etc.
	 * @param int    $entity_id ID da entidade.
	 * @param string $action Ação realizada.
	 * @param string $description Descrição do histórico.
	 */
	public static function log_history( $entity_type, $entity_id, $action, $description ) {
		$table = self::table( 'history' );
		return self::db()->insert(
			$table,
			array(
				'entity_type' => sanitize_text_field( $entity_type ),
				'entity_id'   => absint( $entity_id ),
				'action'      => sanitize_text_field( $action ),
				'description' => sanitize_textarea_field( $description ),
				'user_id'     => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);
	}
}
