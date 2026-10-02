<?php
/**
 * Template Name: Página de Conversão Limpa (VitPress)
 * 
 * Template injetado automaticamente pelo FormService do VitPress.
 * Layout isolado do tema focado apenas em conversão.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Carrega as configurações para verificar depoimento
$settings = get_option( 'hub_settings', array() );
$forms    = isset( $settings['forms'] ) ? $settings['forms'] : array();

$show_testimonial = ! empty( $forms['show_testimonial'] );
$testimonial_name = isset( $forms['testimonial_name'] ) ? $forms['testimonial_name'] : '';
$testimonial_text = isset( $forms['testimonial_text'] ) ? $forms['testimonial_text'] : '';

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>
	<?php wp_head(); ?>
	<style>
		/* Estilos limpos para resetar o tema ativo */
		html, body {
			margin: 0;
			padding: 0;
			background: #f4f6f8;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			color: #333;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		
		#wpadminbar { display: none !important; }
		html { margin-top: 0 !important; }

		.hub-conversion-wrapper {
			background: #ffffff;
			max-width: 500px;
			width: 90%;
			margin: 40px auto;
			padding: 40px;
			border-radius: 12px;
			box-shadow: 0 10px 30px rgba(0,0,0,0.05);
			text-align: center;
		}

		.hub-conversion-title {
			font-size: 28px;
			font-weight: 800;
			margin: 0 0 15px;
			color: #111;
		}

		.hub-conversion-subtitle {
			font-size: 16px;
			line-height: 1.5;
			color: #555;
			margin: 0 0 30px;
		}

		.hub-lgpd-notice {
			font-size: 13px;
			color: #777;
			margin-top: 20px;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
		}

		.hub-whatsapp-notice {
			font-size: 13px;
			color: #128C7E;
			background: #e8f5e9;
			padding: 10px;
			border-radius: 6px;
			margin-top: 15px;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			font-weight: 500;
		}

		.hub-testimonial {
			margin-top: 35px;
			padding-top: 25px;
			border-top: 1px solid #eee;
			text-align: center;
		}

		.hub-stars {
			color: #FFB900;
			font-size: 20px;
			letter-spacing: 2px;
			margin-bottom: 10px;
		}

		.hub-testimonial-text {
			font-size: 15px;
			font-style: italic;
			color: #666;
			margin-bottom: 10px;
			line-height: 1.4;
		}

		.hub-testimonial-name {
			font-size: 14px;
			font-weight: bold;
			color: #333;
		}
	</style>
</head>
<body class="hub-clean-conversion-page">

	<div class="hub-conversion-wrapper">
		<h1 class="hub-conversion-title">Estamos quase lá!</h1>
		<p class="hub-conversion-subtitle">
			Preencha seus dados abaixo. É rápido, seguro e, em instantes, você será direcionado ao nosso WhatsApp para continuar o atendimento.
		</p>

		<div class="hub-form-container" style="text-align: left;">
			<?php echo do_shortcode( '[hub_form]' ); ?>
		</div>

		<?php if ( $show_testimonial && ! empty( $testimonial_name ) && ! empty( $testimonial_text ) ) : ?>
			<div class="hub-testimonial">
				<div class="hub-stars">★★★★★</div>
				<div class="hub-testimonial-text">"<?php echo esc_html( $testimonial_text ); ?>"</div>
				<div class="hub-testimonial-name"><?php echo esc_html( $testimonial_name ); ?></div>
			</div>
		<?php endif; ?>

		<div class="hub-whatsapp-notice">
			<span>📱</span> Após enviar seus dados, você será redirecionado automaticamente para o nosso WhatsApp para agilizar seu atendimento.
		</div>
	</div>

<?php wp_footer(); ?>
</body>
</html>
