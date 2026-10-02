<?php
namespace Hub\Profiles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Perfil de Segmento: Saúde
 * Destinado a Psicólogos, Clínicas, Nutricionistas, Fisioterapeutas, etc.
 */
class HealthProfile extends AbstractProfile {

	public function get_id() {
		return 'saude';
	}

	public function get_name() {
		return 'Saúde';
	}

	public function get_description() {
		return 'Psicólogos, Clínicas, Nutricionistas, Fisioterapeutas e profissionais da saúde com agenda de pacientes.';
	}

	public function get_icon() {
		return 'dashicons-heart';
	}

	public function get_labels() {
		return array(
			'client_singular' => 'Paciente',
			'client_plural'   => 'Pacientes',
			'lead_singular'   => 'Interessado',
			'lead_plural'     => 'Interessados',
			'event_singular'  => 'Consulta',
			'event_plural'    => 'Consultas',
			'pipeline_title'  => 'Pipeline',
			'conversions'     => 'Conversões',
			'new_client'      => 'Novo Paciente',
			'new_lead'        => 'Novo Interessado',
			'new_event'       => 'Agendar Consulta',
		);
	}



	public function get_statuses() {
		return array(
			'lead'   => array(
				'novo'          => 'Novo Contato',
				'em_atendimento' => 'Em Atendimento',
				'convertido'    => 'Convertido em Paciente',
				'arquivado'     => 'Arquivado',
			),
			'client' => array(
				'ativo'     => 'Em Acompanhamento',
				'inativo'   => 'Alta / Pausado',
				'retorno'   => 'Aguardando Retorno',
			),
			'event'  => array(
				'agendado'  => 'Agendado',
				'confirmado' => 'Confirmado pelo Paciente',
				'realizado' => 'Sessão Realizada',
				'faltou'    => 'Paciente Faltou',
				'cancelado' => 'Cancelado',
			),
		);
	}

	public function get_custom_fields( $entity = 'client' ) {
		if ( 'client' === $entity ) {
			return array(
				'convenio'       => array(
					'label' => 'Convênio / Particular',
					'type'  => 'text',
				),
				'especialidade'  => array(
					'label' => 'Especialidade / Tipo de Tratamento',
					'type'  => 'text',
				),
				'historico_medico' => array(
					'label' => 'Resumo da Anamnese / Observações Clínicas',
					'type'  => 'textarea',
				),
			);
		}
		return array();
	}
}
