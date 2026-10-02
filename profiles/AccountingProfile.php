<?php
namespace Hub\Profiles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Perfil de Segmento: Contabilidade
 * Destinado a Escritórios Contábeis e Contadores.
 */
class AccountingProfile extends AbstractProfile {

	public function get_id() {
		return 'contabilidade';
	}

	public function get_name() {
		return 'Contabilidade';
	}

	public function get_description() {
		return 'Escritórios Contábeis, Contadores Autônomos e Consultorias Fiscais.';
	}

	public function get_icon() {
		return 'dashicons-chart-pie';
	}

	public function get_labels() {
		return array(
			'client_singular' => 'Empresa',
			'client_plural'   => 'Empresas',
			'lead_singular'   => 'Prospect',
			'lead_plural'     => 'Prospects',
			'event_singular'  => 'Reunião',
			'event_plural'    => 'Agenda',
			'pipeline_title'  => 'Pipeline',
			'conversions'     => 'Conversões',
			'new_client'      => 'Nova Empresa',
			'new_lead'        => 'Novo Prospect',
			'new_event'       => 'Agendar Reunião',
		);
	}



	public function get_statuses() {
		return array(
			'lead'   => array(
				'novo'            => 'Novo Lead',
				'em_qualificacao' => 'Em Qualificação Fiscal',
				'convertido'      => 'Contrato Fechado',
				'perdido'         => 'Perdido',
			),
			'client' => array(
				'ativo'      => 'Regular / Ativo',
				'pendencia'  => 'Com Pendência Documental',
				'suspenso'   => 'Inativo / Encerrado',
			),
			'event'  => array(
				'agendado'   => 'Agendado',
				'realizado'  => 'Concluído',
				'cancelado'  => 'Cancelado',
			),
		);
	}

	public function get_custom_fields( $entity = 'client' ) {
		if ( 'client' === $entity ) {
			return array(
				'cnpj_cpf'           => array(
					'label' => 'CNPJ / CPF da Empresa',
					'type'  => 'text',
				),
				'regime_tributario'  => array(
					'label' => 'Regime Tributário (Simples, Lucro Presumido, Real, MEI)',
					'type'  => 'text',
				),
				'faturamento_medio'  => array(
					'label' => 'Faturamento Médio Mensal',
					'type'  => 'text',
				),
				'observacoes_fiscais' => array(
					'label' => 'Particularidades Fiscais e Tributárias',
					'type'  => 'textarea',
				),
			);
		}
		return array();
	}
}
