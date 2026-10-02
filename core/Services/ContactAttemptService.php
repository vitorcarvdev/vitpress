<?php
namespace Hub\Core\Services;

use Hub\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serviço responsável por gerenciar as tentativas de contato e cálculo de métricas.
 */
class ContactAttemptService {

	/**
	 * Registra uma tentativa de contato para o lead.
	 *
	 * @param int    $lead_id ID do lead.
	 * @param string $channel Canal ('whatsapp', 'telefone', etc).
	 * @return bool|\WP_Error
	 */
	public static function record_attempt( $lead_id, $channel ) {
		global $wpdb;

		$lead_id = absint( $lead_id );
		$channel = sanitize_key( $channel );

		if ( ! $lead_id || empty( $channel ) ) {
			return new \WP_Error( 'invalid_data', 'Dados inválidos para registrar tentativa de contato.' );
		}

		$lock_key = 'hub_attempt_lock_' . $lead_id;

		// 1. Proteção de concorrência / idempotência via WordPress Transient
		if ( get_transient( $lock_key ) ) {
			return new \WP_Error( 'concurrency_lock', 'Uma tentativa já está sendo registrada para este lead. Por favor, aguarde.' );
		}
		set_transient( $lock_key, 1, 5 ); // Trava por 5 segundos

		// Inicia Transação do Banco de Dados para consistência atômica
		$wpdb->query( 'START TRANSACTION' );

		try {
			$table_leads    = Database::table( 'leads' );
			$table_attempts = Database::table( 'lead_contact_attempts' );

			// 2. Bloqueio a nível de linha no MySQL (FOR UPDATE) para evitar race conditions
			$lead = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, stage, created_at, first_contact_at, attempts_count FROM {$table_leads} WHERE id = %d FOR UPDATE",
					$lead_id
				)
			);

			if ( ! $lead ) {
				throw new \Exception( 'Lead não encontrado.' );
			}

			// 3. Fallback de proteção temporal (evita duplicados no intervalo de 2s)
			$last_attempt_time = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MAX(created_at) FROM {$table_attempts} WHERE lead_id = %d AND channel = %s",
					$lead_id,
					$channel
				)
			);

			if ( $last_attempt_time && ( time() - strtotime( $last_attempt_time ) < 2 ) ) {
				throw new \Exception( 'Tentativa de contato duplicada detectada.' );
			}

			// Determina o número sequencial da tentativa
			$current_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_attempts} WHERE lead_id = %d",
					$lead_id
				)
			);
			$next_attempt_number = $current_count + 1;

			// 4. Insere a tentativa de contato no histórico auditável
			$now = current_time( 'mysql' );
			$inserted = $wpdb->insert(
				$table_attempts,
				array(
					'lead_id'        => $lead_id,
					'channel'        => $channel,
					'stage'          => $lead->stage,
					'attempt_number' => $next_attempt_number,
					'created_at'     => $now,
				),
				array( '%d', '%s', '%s', '%d', '%s' )
			);

			if ( false === $inserted ) {
				throw new \Exception( 'Falha ao registrar tentativa de contato no banco de dados.' );
			}

			// 5. Atualiza o cache de performance no Lead
			$update_data = array(
				'attempts_count' => $next_attempt_number,
			);

			// Registra a primeira tentativa permanentemente se ainda for nula
			if ( empty( $lead->first_contact_at ) || '0000-00-00 00:00:00' === $lead->first_contact_at ) {
				$update_data['first_contact_at'] = $now;
			}

			$updated = $wpdb->update(
				$table_leads,
				$update_data,
				array( 'id' => $lead_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				throw new \Exception( 'Falha ao atualizar contador de tentativas no Lead.' );
			}

			// 6. Registra no histórico imutável (hub_history)
			$channel_label = ( 'whatsapp' === $channel ) ? 'WhatsApp' : ( ( 'telefone' === $channel ) ? 'Telefone' : ucfirst( $channel ) );
			Database::log_history(
				'lead',
				$lead_id,
				'contact_attempt',
				sprintf( 'Tentativa de contato #%d via %s.', $next_attempt_number, $channel_label )
			);

			$wpdb->query( 'COMMIT' );
			delete_transient( $lock_key );
			return true;

		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			delete_transient( $lock_key );
			return new \WP_Error( 'attempt_failed', $e->getMessage() );
		}
	}

	/**
	 * Retorna a classificação visual e textual baseada no tempo de resposta em minutos.
	 *
	 * @param int|null $minutes Diferença em minutos entre a criação do lead e o primeiro contato.
	 * @return array Classificação e cores de destaque.
	 */
	public static function get_classification( $minutes ) {
		if ( null === $minutes || $minutes < 0 ) {
			return array(
				'label' => 'SEM TENTATIVA REGISTRADA',
				'color' => '#d63638', // vermelho
				'class' => 'sem-tentativa',
			);
		}

		if ( $minutes <= 15 ) {
			return array(
				'label' => 'RÁPIDO',
				'color' => '#46b450', // verde
				'class' => 'rapido',
			);
		}

		if ( $minutes <= 60 ) {
			return array(
				'label' => 'MODERADO',
				'color' => '#46b450', // verde
				'class' => 'moderado',
			);
		}

		if ( $minutes <= 240 ) {
			return array(
				'label' => 'DEMORADO',
				'color' => '#dba617', // amarelo
				'class' => 'demorado',
			);
		}

		return array(
			'label' => 'MUITO DEMORADO',
			'color' => '#d63638', // vermelho
			'class' => 'muito-demorado',
		);
	}
}
