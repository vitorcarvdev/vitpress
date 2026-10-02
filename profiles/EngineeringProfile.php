<?php
namespace Hub\Profiles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Perfil de Segmento: Engenharia
 * Destinado a Engenheiros, Escritórios de Engenharia e Construtoras.
 */
class EngineeringProfile extends AbstractProfile {

	public function get_id() {
		return 'engenharia';
	}

	public function get_name() {
		return 'Engenharia';
	}

	public function get_description() {
		return 'Engenheiros, Escritórios de Engenharia, Projetistas e Gestores de Obras/Vistorias.';
	}

	public function get_icon() {
		return 'dashicons-hammer';
	}

	public function get_labels() {
		return array(
			'client_singular' => 'Cliente',
			'client_plural'   => 'Clientes',
			'lead_singular'   => 'Projeto',
			'lead_plural'     => 'Projetos',
			'event_singular'  => 'Visita',
			'event_plural'    => 'Visitas',
			'pipeline_title'  => 'Pipeline',
			'conversions'     => 'Conversões',
			'new_client'      => 'Novo Cliente',
			'new_lead'        => 'Novo Projeto',
			'new_event'       => 'Agendar Visita',
		);
	}



	public function get_statuses() {
		return array(
			'lead'   => array(
				'novo'          => 'Novo Contato',
				'em_elaboracao' => 'Proposta em Elaboração',
				'convertido'    => 'Proposta Aprovada',
				'recusado'      => 'Recusado',
			),
			'client' => array(
				'ativo'      => 'Em Execução',
				'pausado'    => 'Aguardando Licenciamento',
				'concluido'  => 'Entregue / Concluído',
			),
			'event'  => array(
				'agendado'   => 'Visita Agendada',
				'realizado'  => 'Vistoria Realizada',
				'remarcado'  => 'Remarcado por Clima/Logística',
				'cancelado'  => 'Cancelado',
			),
		);
	}

	public function get_custom_fields( $entity = 'client' ) {
		if ( 'client' === $entity ) {
			return array(
				'tipo_obra'        => array(
					'label' => 'Tipo de Serviço / Obra',
					'type'  => 'text',
				),
				'registro_crea'    => array(
					'label' => 'ART / RRT / Registro Técnico',
					'type'  => 'text',
				),
				'endereco_obra'    => array(
					'label' => 'Endereço da Obra / Imóvel',
					'type'  => 'text',
				),
				'detalhes_projeto' => array(
					'label' => 'Especificações Técnicas e Escopo',
					'type'  => 'textarea',
				),
			);
		}
		return array();
	}
}
