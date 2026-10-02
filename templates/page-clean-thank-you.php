<?php
/**
 * Template Name: VitPress - Obrigado Pelo Contato
 * 
 * Página limpa de confirmação, disparando a conversão Google Ads
 * e redirecionando para o WhatsApp.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$token = isset( $_GET['hub_token'] ) ? sanitize_text_field( $_GET['hub_token'] ) : '';
$final_url = '';
$is_valid  = false;

if ( $token ) {
	$transient_key = 'hub_redir_' . $token;
	$saved_url     = get_transient( $transient_key );
	
	if ( $saved_url !== false ) {
		$is_valid  = true;
		$final_url = ( $saved_url === 'none' ) ? home_url() : $saved_url;
		// Consome o token para evitar duplo disparo ou reuso
		delete_transient( $transient_key );
	}
}

// Resgata configuração do Google Ads
$settings = get_option( 'hub_settings', array() );
$gads     = isset( $settings['integrations']['google_ads_contact'] ) ? $settings['integrations']['google_ads_contact'] : false;
$has_gads = $gads && ! empty( $gads['conversion_id'] ) && ! empty( $gads['conversion_label'] );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Obrigado pelo contato!', 'hub' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<meta name="robots" content="noindex, nofollow">
	
	<style>
		body {
			margin: 0;
			padding: 0;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background-color: #f0f2f5;
			display: flex;
			justify-content: center;
			align-items: center;
			height: 100vh;
			text-align: center;
		}
		.hub-thank-you-card {
			background: #fff;
			padding: 40px;
			border-radius: 8px;
			box-shadow: 0 4px 15px rgba(0,0,0,0.05);
			max-width: 500px;
			width: 90%;
		}
		.hub-icon {
			font-size: 60px;
			color: #28a745;
			margin-bottom: 20px;
		}
		h1 {
			margin: 0 0 15px;
			color: #333;
			font-size: 24px;
		}
		p {
			color: #666;
			line-height: 1.5;
			margin-bottom: 25px;
		}
		.hub-spinner {
			display: inline-block;
			width: 40px;
			height: 40px;
			border: 4px solid rgba(0,0,0,0.1);
			border-left-color: #0073aa;
			border-radius: 50%;
			animation: hub-spin 1s linear infinite;
		}
		@keyframes hub-spin {
			to { transform: rotate(360deg); }
		}
	</style>

	<?php if ( $is_valid && $has_gads ) : ?>
		<!-- Google Ads Global Tag -->
		<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $gads['conversion_id'] ); ?>"></script>
		<script>
		  window.dataLayer = window.dataLayer || [];
		  function gtag(){dataLayer.push(arguments);}
		  gtag('js', new Date());
		  gtag('config', '<?php echo esc_attr( $gads['conversion_id'] ); ?>');
		</script>
	<?php endif; ?>
</head>
<body>

	<div class="hub-thank-you-card">
		<?php if ( $is_valid ) : ?>
			<div class="hub-icon">✓</div>
			<h1>Obrigado!</h1>
			<p>Seu contato foi recebido com sucesso. Em instantes você será redirecionado para o nosso atendimento.</p>
			<div class="hub-spinner"></div>
			
			<?php if ( $has_gads ) : ?>
				<!-- Disparo do Evento de Conversão -->
				<script>
				  gtag('event', 'conversion', {
					  'send_to': '<?php echo esc_js( $gads['conversion_id'] ); ?>/<?php echo esc_js( $gads['conversion_label'] ); ?>'
				  });
				</script>
			<?php endif; ?>

			<script>
				setTimeout(function() {
					window.location.href = "<?php echo esc_url_raw( $final_url ); ?>";
				}, 1000);
			</script>

		<?php else : ?>
			
			<div class="hub-icon" style="color: #dc3545;">!</div>
			<h1>Link Expirado</h1>
			<p>Esta página de confirmação já foi utilizada ou o tempo expirou. Se precisar falar conosco, acesse novamente o nosso site.</p>
			<a href="<?php echo esc_url( home_url() ); ?>" style="display:inline-block; padding:10px 20px; background:#0073aa; color:#fff; text-decoration:none; border-radius:4px; font-weight:bold;">Voltar para o site</a>

		<?php endif; ?>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
