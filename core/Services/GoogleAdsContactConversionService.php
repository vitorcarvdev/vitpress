<?php
namespace Hub\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serviço responsável por gerenciar a configuração simplificada do Google Ads
 * (rastreamento de formulário em página de obrigado).
 */
class GoogleAdsContactConversionService {

	/**
	 * Extrai o Conversion ID e Label de um snippet JS colado.
	 *
	 * @param string $snippet Snippet copiado do painel do Google Ads.
	 * @return array|false Array com as chaves 'conversion_id' e 'conversion_label', ou false se inválido.
	 */
	public function parse_snippet( $snippet ) {
		if ( empty( $snippet ) ) {
			return false;
		}

		// Expressão regular para encontrar o padrão AW-XXXXXXX/YYYYYYYY
		// Procura por send_to: 'AW-18304064550/5p6tCOuL-8scEKa4h5hE' (com ou sem aspas simples/duplas)
		$pattern = '/[\'"]?(AW-\d+)[\/]([A-Za-z0-9\-_]+)[\'"]?/';

		if ( preg_match( $pattern, $snippet, $matches ) ) {
			return array(
				'conversion_id'    => sanitize_text_field( $matches[1] ),
				'conversion_label' => sanitize_text_field( $matches[2] ),
			);
		}

		return false;
	}

	/**
	 * Salva as configurações de conversão validadas no banco de dados.
	 *
	 * @param string $snippet_raw O snippet cru enviado via formulário POST.
	 * @return array Array com índice 'success' indicando o resultado, e mensagens ou dados formatados.
	 */
	public function save_configuration( $snippet_raw ) {
		$settings = get_option( 'hub_settings', array() );

		// Se enviar vazio, limpa a configuração.
		if ( trim( $snippet_raw ) === '' ) {
			$settings['integrations']['google_ads_contact'] = array();
			update_option( 'hub_settings', $settings );
			return array(
				'success' => true,
				'message' => 'Configuração de conversão limpa.',
			);
		}

		$parsed = $this->parse_snippet( $snippet_raw );

		if ( $parsed ) {
			if ( ! isset( $settings['integrations'] ) ) {
				$settings['integrations'] = array();
			}
			$settings['integrations']['google_ads_contact'] = $parsed;
			update_option( 'hub_settings', $settings );
			
			return array(
				'success' => true,
				'message' => 'Conversão identificada com sucesso.',
				'data'    => $parsed
			);
		}

		// Se enviou algo mas não bateu na regex, não salva e retorna erro.
		return array(
			'success' => false,
			'message' => 'Não foi possível identificar o Conversion ID e o Rótulo no snippet fornecido. Certifique-se de colar o bloco gtag de conversão fornecido pelo Google.',
		);
	}

	/**
	 * Recupera a configuração atual da conversão.
	 *
	 * @return array|false Configuração salva ou false se não existir/estiver vazia.
	 */
	public function get_configuration() {
		$settings = get_option( 'hub_settings', array() );
		
		if ( isset( $settings['integrations']['google_ads_contact']['conversion_id'] ) && 
		     isset( $settings['integrations']['google_ads_contact']['conversion_label'] ) ) {
			return $settings['integrations']['google_ads_contact'];
		}
		
		return false;
	}
}
