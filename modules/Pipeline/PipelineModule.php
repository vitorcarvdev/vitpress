<?php
namespace Hub\Modules\Pipeline;

use Hub\Modules\AbstractModule;
use Hub\Core\Database;
use Hub\Core\ProfileManager;
use Hub\Core\Services\ContactAttemptService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Módulo de Pipeline Comercial (Exclusivamente Visualização / Read-Only).
 *
 * O Hub apenas exibe o Pipeline, cartões e métricas.
 * Operações comerciais e movimentações são realizadas exclusivamente no VitZap.
 */
class PipelineModule extends AbstractModule {

	public function get_id() {
		return 'pipeline';
	}

	public function get_title() {
		return 'Pipeline';
	}

	public function get_order() {
		return 1;
	}

	public function init() {
		// Interface estritamente somente leitura.
		// Sem handlers AJAX de escrita ou alteração de estado nesta tela.
		add_action( 'wp_ajax_hub_delete_lead', array( $this, 'ajax_delete_lead' ) );
	}

	public function ajax_delete_lead() {
		check_ajax_referer( 'hub_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permissão negada. Apenas administradores podem excluir leads.' );
		}

		$lead_id = isset( $_POST['lead_id'] ) ? intval( $_POST['lead_id'] ) : 0;
		if ( $lead_id <= 0 ) {
			wp_send_json_error( 'ID inválido.' );
		}

		global $wpdb;
		$table_leads = Database::table( 'leads' );

		$lead = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_leads} WHERE id = %d", $lead_id ) );
		if ( ! $lead ) {
			wp_send_json_error( 'Lead não encontrado no banco local.' );
		}

		$central_id = isset( $lead->pipeline_central_id ) ? $lead->pipeline_central_id : '';

		// 1. Deleção na Central (se integrado). Não chama o VitZap.
		if ( ! empty( $central_id ) && class_exists( '\VCSis\PipelineCentral\Application\Services\PipelineService' ) && class_exists( '\VCSis\PipelineCentral\Infrastructure\Auth\AuthService' ) ) {
			try {
				$tenant_id = \VCSis\PipelineCentral\Infrastructure\Auth\AuthService::get_or_create_default_tenant_id();
				$auth = new \VCSis\PipelineCentral\Infrastructure\Auth\AuthContext( $tenant_id, 'Hub VitAgência Admin', 'wp_session', 'WP Admin', true );
				
				$pipelineService = new \VCSis\PipelineCentral\Application\Services\PipelineService();
				$res = $pipelineService->deleteOpportunity( $auth, $central_id );
				
				if ( $res['status_code'] !== 200 && $res['status_code'] !== 404 ) {
					wp_send_json_error( 'Erro ao excluir no Pipeline Central: ' . ( $res['data']['message'] ?? 'Desconhecido' ) );
				}
			} catch ( \Throwable $e ) {
				wp_send_json_error( 'Exceção ao excluir no Pipeline Central: ' . $e->getMessage() );
			}
		}

		// 2. Deleção física local
		$deleted = $wpdb->delete( $table_leads, array( 'id' => $lead_id ), array( '%d' ) );
		if ( false === $deleted ) {
			wp_send_json_error( 'Não foi possível excluir o lead.' );
		}

		$wpdb->suppress_errors( true );
		$wpdb->delete( Database::table( 'lead_contact_attempts' ), array( 'lead_id' => $lead_id ), array( '%d' ) );
		$wpdb->suppress_errors( false );

		wp_send_json_success( 'Excluído com sucesso.' );
	}

	/**
	 * Retorna as 5 etapas canônicas do Pipeline.
	 *
	 * @return array
	 */
	public static function get_canonical_stages() {
		return array(
			'novo_interessado' => 'Novo Interessado',
			'avaliacao'        => 'Avaliação',
			'proposta_enviada' => 'Proposta Enviada',
			'cliente'          => 'Cliente',
			'perdido'          => 'Perdido',
		);
	}

	/**
	 * Normaliza estágios legados para uma das 5 etapas oficiais.
	 *
	 * @param string $stage
	 * @return string
	 */
	public static function normalize_stage( $stage ) {
		$stage = sanitize_key( $stage );
		$mapping = array(
			'novo'             => 'novo_interessado',
			'novo_contato'     => 'novo_interessado',
			'lead_inicial'     => 'novo_interessado',
			'interessado'      => 'novo_interessado',
			'novo_interessado' => 'novo_interessado',
			'avaliacao'        => 'avaliacao',
			'qualificado'      => 'avaliacao',
			'em_atendimento'   => 'avaliacao',
			'proposta_enviada' => 'proposta_enviada',
			'proposta'         => 'proposta_enviada',
			'negociacao'       => 'proposta_enviada',
			'cliente'          => 'cliente',
			'ganho'            => 'cliente',
			'fechado'          => 'cliente',
			'perdido'          => 'perdido',
			'descartado'       => 'perdido',
			'arquivado'        => 'perdido',
		);

		return isset( $mapping[ $stage ] ) ? $mapping[ $stage ] : 'novo_interessado';
	}

	/**
	 * Formata o telefone para exibição visual amigável.
	 *
	 * @param string $phone
	 * @return string
	 */
	public static function format_phone( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}

		$digits = preg_replace( '/\D/', '', $phone );
		if ( strlen( $digits ) === 11 ) {
			return '(' . substr( $digits, 0, 2 ) . ') ' . substr( $digits, 2, 5 ) . '-' . substr( $digits, 7 );
		} elseif ( strlen( $digits ) === 10 ) {
			return '(' . substr( $digits, 0, 2 ) . ') ' . substr( $digits, 2, 4 ) . '-' . substr( $digits, 6 );
		}

		return $phone;
	}

	/**
	 * Identifica e formata o rótulo amigável da origem do Lead.
	 *
	 * @param object $lead
	 * @return string
	 */
	public static function format_source_label( $lead ) {
		$source     = ! empty( $lead->source ) ? strtolower( trim( $lead->source ) ) : '';
		$utm_source = ! empty( $lead->utm_source ) ? strtolower( trim( $lead->utm_source ) ) : '';
		$utm_medium = ! empty( $lead->utm_medium ) ? strtolower( trim( $lead->utm_medium ) ) : '';

		if ( 'captador_site' === $source ) {
			return 'Captador do Site';
		}

		if ( stripos( $utm_medium, 'organic' ) !== false || 'organico' === $source ) {
			return 'Orgânico';
		}

		if ( ! empty( $lead->gclid ) || ( stripos( $utm_source, 'google' ) !== false && stripos( $utm_medium, 'cpc' ) !== false ) ) {
			return 'Google Ads';
		}

		if ( ! empty( $lead->fbclid ) || stripos( $utm_source, 'facebook' ) !== false || stripos( $utm_source, 'meta' ) !== false || stripos( $utm_source, 'instagram' ) !== false ) {
			return 'Meta Ads';
		}

		if ( ! empty( $lead->gclid ) || stripos( $utm_source, 'google' ) !== false ) {
			return 'Google Ads';
		}

		if ( 'site' === $source ) {
			return 'Formulário do Site';
		}
		if ( 'direto' === $source ) {
			return 'Contato Direto';
		}
		if ( 'indicacao' === $source ) {
			return 'Indicação';
		}
		if ( 'redes_sociais' === $source ) {
			return 'Redes Sociais';
		}

		if ( ! empty( $source ) ) {
			return ucwords( str_replace( array( '_', '-' ), ' ', $source ) );
		}

		return 'Direto';
	}

	/**
	 * Monta a representação canônica do Cartão do Lead (LeadCardData).
	 *
	 * @param object      $lead
	 * @param string|null $cutoff_date
	 * @param int|null    $now_ts
	 * @return array
	 */
	public static function format_lead_card( $lead, $cutoff_date = null, $now_ts = null ) {
		if ( null === $cutoff_date ) {
			$cutoff_date = get_option( 'hub_atendimento_start_date', '2026-08-31 00:00:00' );
		}
		if ( null === $now_ts ) {
			$now_ts = current_time( 'timestamp' );
		}

		$created_ts = strtotime( $lead->created_at );
		$diff_sec   = max( 0, $now_ts - $created_ts );
		$diff_min   = round( $diff_sec / 60 );

		// 1. Data Real e Idade Relativa
		$formatted_date = date_i18n( 'd/m/Y \à\s H:i', $created_ts );
		if ( $diff_min < 1 ) {
			$relative_age = 'Recebido agora';
		} elseif ( $diff_min < 60 ) {
			$relative_age = 'Recebido há ' . $diff_min . ' min';
		} elseif ( $diff_min < 1440 ) {
			$hours = round( $diff_min / 60 );
			$relative_age = 'Recebido há ' . $hours . 'h';
		} else {
			$days = round( $diff_min / 1440 );
			$relative_age = 'Recebido há ' . $days . ( $days == 1 ? ' dia' : ' dias' );
		}

		// 2. Métricas de Atendimento
		$attempts_count = (int) ( isset( $lead->attempts_count ) ? $lead->attempts_count : 0 );
		$is_legacy = ( $created_ts < strtotime( $cutoff_date ) );

		if ( $attempts_count > 0 ) {
			$first_contact_ts = ! empty( $lead->first_contact_at ) ? strtotime( $lead->first_contact_at ) : $created_ts;
			$resp_sec = max( 0, $first_contact_ts - $created_ts );
			$resp_min = round( $resp_sec / 60 );

			if ( $resp_min < 60 ) {
				$time_str = max( 1, $resp_min ) . ' min';
			} else {
				$hrs  = floor( $resp_min / 60 );
				$mins = $resp_min % 60;
				$time_str = $hrs . 'h' . ( $mins > 0 ? ' ' . $mins . 'min' : '' );
			}

			$classification = ContactAttemptService::get_classification( $resp_min );
			$attendance = array(
				'has_attempts' => true,
				'status_text'  => '1ª tentativa após ' . $time_str,
				'badge_text'   => ( 1 === $attempts_count ) ? '1 tentativa registrada' : $attempts_count . ' tentativas registradas',
				'color'        => $classification['color'],
				'class'        => $classification['class'],
				'label'        => $classification['label'],
			);
		} else {
			if ( $is_legacy ) {
				$attendance = array(
					'has_attempts' => false,
					'status_text'  => 'Sem tentativa registrada',
					'badge_text'   => '',
					'color'        => '#646970',
					'class'        => 'legacy-no-attempt',
					'label'        => 'SEM REGISTRO',
				);
			} else {
				if ( $diff_min < 60 ) {
					$time_no_contact = max( 1, $diff_min ) . ' min';
				} elseif ( $diff_min < 1440 ) {
					$time_no_contact = round( $diff_min / 60 ) . 'h';
				} else {
					$days = round( $diff_min / 1440 );
					$time_no_contact = $days . ( $days == 1 ? ' dia' : ' dias' );
				}

				$classification = ContactAttemptService::get_classification( $diff_min );
				$attendance = array(
					'has_attempts' => false,
					'status_text'  => 'Sem tentativa registrada há ' . $time_no_contact,
					'badge_text'   => 'Aguardando 1º contato',
					'color'        => $classification['color'],
					'class'        => $classification['class'],
					'label'        => $classification['label'],
				);
			}
		}

		// 3. Respostas do Atendente Virtual / Custom Data
		$custom_data = array();
		if ( ! empty( $lead->custom_data ) ) {
			if ( is_array( $lead->custom_data ) ) {
				$custom_data = $lead->custom_data;
			} else {
				$decoded = json_decode( $lead->custom_data, true );
				if ( is_array( $decoded ) ) {
					$custom_data = $decoded;
				}
			}
		}

		$captador_answers = isset( $custom_data['captador_answers'] ) && is_array( $custom_data['captador_answers'] )
			? $custom_data['captador_answers']
			: array();

		$normalized_stage = self::normalize_stage( $lead->stage );
		$stages_labels    = self::get_canonical_stages();

		return array(
			'id'                => (int) $lead->id,
			'name'              => $lead->name ?: 'Lead Sem Nome',
			'phone'             => $lead->phone ? trim( $lead->phone ) : '',
			'formatted_phone'   => self::format_phone( $lead->phone ),
			'email'             => $lead->email ? trim( $lead->email ) : '',
			'created_at'        => $lead->created_at,
			'formatted_date'    => $formatted_date,
			'relative_age'      => $relative_age,
			'source'            => $lead->source,
			'source_label'      => self::format_source_label( $lead ),
			'stage'             => $normalized_stage,
			'stage_label'       => isset( $stages_labels[ $normalized_stage ] ) ? $stages_labels[ $normalized_stage ] : 'Novo Interessado',
			'attempts_count'    => $attempts_count,
			'first_contact_at'  => $lead->first_contact_at,
			'attendance'        => $attendance,
			'lost_reason'       => ! empty( $lead->lost_reason ) ? $lead->lost_reason : '',
			'lost_at'           => ! empty( $lead->lost_at ) ? $lead->lost_at : '',
			'revenue_value'     => ! empty( $lead->revenue_value ) ? (float) $lead->revenue_value : 0.0,
			'notes'             => ! empty( $lead->notes ) ? $lead->notes : '',
			'captador_answers'  => $captador_answers,
			'custom_data'       => $custom_data,
			'tracking'          => array(
				'utm_source'   => ! empty( $lead->utm_source ) ? $lead->utm_source : '',
				'utm_medium'   => ! empty( $lead->utm_medium ) ? $lead->utm_medium : '',
				'utm_campaign' => ! empty( $lead->utm_campaign ) ? $lead->utm_campaign : '',
				'gclid'        => ! empty( $lead->gclid ) ? $lead->gclid : '',
				'fbclid'       => ! empty( $lead->fbclid ) ? $lead->fbclid : '',
				'landing_path' => ! empty( $lead->landing_path ) ? $lead->landing_path : '',
				'device_type'  => ! empty( $lead->device_type ) ? $lead->device_type : '',
			),
		);
	}

	public function render() {
		$stages      = self::get_canonical_stages();
		$table_leads = Database::table( 'leads' );
		$db          = Database::db();

		$cutoff_date = get_option( 'hub_atendimento_start_date', '2026-08-31 00:00:00' );
		$now_ts      = current_time( 'timestamp' );

		// Filtros simples e eficientes (Sem N+1)
		$period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'all';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		$where_clauses = array();
		$params        = array();

		if ( '30days' === $period ) {
			$where_clauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
		} elseif ( '90days' === $period ) {
			$where_clauses[] = "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
		} elseif ( 'year' === $period ) {
			$where_clauses[] = "YEAR(created_at) = YEAR(NOW())";
		}

		if ( ! empty( $search ) ) {
			$like_term = '%' . $db->esc_like( $search ) . '%';
			$where_clauses[] = "(name LIKE %s OR phone LIKE %s OR email LIKE %s)";
			$params[] = $like_term;
			$params[] = $like_term;
			$params[] = $like_term;
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
		$query = "SELECT * FROM {$table_leads} {$where_sql} ORDER BY created_at DESC LIMIT 500";

		if ( ! empty( $params ) ) {
			$raw_leads = $db->get_results( $db->prepare( $query, $params ) );
		} else {
			$raw_leads = $db->get_results( $query );
		}

		// Agrupa os leads por etapa canônica
		$leads_by_stage = array(
			'novo_interessado' => array(),
			'avaliacao'        => array(),
			'proposta_enviada' => array(),
			'cliente'          => array(),
			'perdido'          => array(),
		);

		$waiting_attempts_count = 0;

		if ( ! empty( $raw_leads ) ) {
			foreach ( $raw_leads as $raw_lead ) {
				$card = self::format_lead_card( $raw_lead, $cutoff_date, $now_ts );
				$target_stage = $card['stage'];

				if ( ! isset( $leads_by_stage[ $target_stage ] ) ) {
					$target_stage = 'novo_interessado';
				}

				$leads_by_stage[ $target_stage ][] = $card;

				if ( 'novo_interessado' === $target_stage && 0 === $card['attempts_count'] && strtotime( $card['created_at'] ) >= strtotime( $cutoff_date ) ) {
					$waiting_attempts_count++;
				}
			}
		}

		require_once HUB_PATH . 'admin/views/pipeline.php';
	}
}
