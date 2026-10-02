<?php
namespace Hub\Core\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PrivacyService {

	public function init() {
		$settings = get_option( 'hub_settings', array() );
		$privacy  = isset( $settings['privacy'] ) ? $settings['privacy'] : array();

		if ( empty( $privacy['banner_enabled'] ) ) {
			return;
		}

		// Inject GCM v2 default before everything else
		add_action( 'wp_head', array( $this, 'inject_gcm_default' ), 1 );

		// Render banner and JS
		add_action( 'wp_footer', array( $this, 'render_banner' ) );
	}

	public function inject_gcm_default() {
		?>
		<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		
		// Set default consent state
		gtag('consent', 'default', {
			'ad_storage': 'denied',
			'analytics_storage': 'denied',
			'ad_user_data': 'denied',
			'ad_personalization': 'denied',
			'wait_for_update': 500
		});
		
		dataLayer.push({
			'event': 'default_consent'
		});
		</script>
		<?php
	}

	public function render_banner() {
		$settings = get_option( 'hub_settings', array() );
		$privacy  = isset( $settings['privacy'] ) ? $settings['privacy'] : array();
		
		$text = ! empty( $privacy['banner_text'] ) ? $privacy['banner_text'] : 'Utilizamos cookies para medir o desempenho de nossas campanhas. Saiba mais em nossa Política de Privacidade.';
		
		$wp_policy_page = get_option('wp_page_for_privacy_policy');
		$policy_page_id = $wp_policy_page ? $wp_policy_page : (! empty($privacy['policy_page']) ? $privacy['policy_page'] : 0);
		
		if ( $policy_page_id ) {
			$policy_url = get_permalink( $policy_page_id );
			if ( $policy_url ) {
				$text = str_replace( 'Política de Privacidade', '<a href="' . esc_url( $policy_url ) . '" target="_blank">Política de Privacidade</a>', $text );
			}
		}

		wp_enqueue_style( 'hub-privacy-css', HUB_URL . 'assets/css/hub-privacy.css', array(), HUB_VERSION );
		wp_enqueue_script( 'hub-privacy-js', HUB_URL . 'assets/js/hub-privacy.js', array(), HUB_VERSION, true );

		?>
		<div id="hub-consent-banner" class="hub-consent-banner" style="display: none;">
			<div class="hub-consent-container">
				<div class="hub-consent-text">
					<?php echo wp_kses_post( $text ); ?>
				</div>
				<div class="hub-consent-actions">
					<button id="hub-consent-accept" class="hub-btn hub-btn-accept">Aceitar</button>
					<button id="hub-consent-reject" class="hub-btn hub-btn-reject">Recusar</button>
				</div>
			</div>
		</div>
		<?php
	}
}