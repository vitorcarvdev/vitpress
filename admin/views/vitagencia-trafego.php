<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'hub_save_vitagencia', 'hub_nonce' ); ?>
	<input type="hidden" name="action" value="hub_save_vitagencia">
	<input type="hidden" name="vitagencia_tab" value="trafego">
	
	<div class="card" style="max-width: 800px; padding: 20px;">
		<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 15px; margin-bottom: 15px;">
			<h2 style="margin: 0;">Página de Conversão Limpa</h2>
			<div id="hub_page_generator_wrapper">
				<?php if ( $page_exists ) : ?>
					<span style="color: #28a745; font-weight: bold; margin-right: 15px;">✅ Página de Conversão Criada</span>
					<a href="<?php echo esc_url( home_url( '/novo-lead' ) ); ?>" target="_blank" class="button button-secondary">[ Abrir Página ]</a>
				<?php else : ?>
					<button type="button" id="hub_btn_generate_page" class="button button-primary">✨ Gerar Página de Conversão</button>
					<span class="spinner" id="hub_page_spinner" style="float: none; margin-top: 0;"></span>
				<?php endif; ?>
			</div>
		</div>

		<h2>Formulário de Captação</h2>
		<p>Utilize o formulário oficial do VitPress para capturar leads e integrar automaticamente com o funil, Google Ads e Meta Ads.</p>
		
		<div style="background: #f0f0f1; border: 1px solid #ccd0d4; padding: 15px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
			<div>
				<strong>Shortcode do Formulário:</strong><br>
				<code id="hub_shortcode_text" style="font-size: 16px; padding: 5px 10px; display: inline-block; margin-top: 5px;">[hub_form]</code>
			</div>
			<button type="button" class="button" onclick="navigator.clipboard.writeText('[hub_form]'); alert('Copiado para a área de transferência!');">Copiar</button>
		</div>

		<h3 style="margin-top: 30px;">Configurações de Redirecionamento e Notificações</h3>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="form_whatsapp">WhatsApp de Destino</label></th>
				<td>
					<input type="text" name="forms[whatsapp]" id="form_whatsapp" class="regular-text" value="<?php echo esc_attr( $whatsapp ); ?>" placeholder="Ex: 5511999999999" pattern="\d+" title="Apenas números">
					<p class="description">Somente números, incluindo o código do país (ex: 55).</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="form_whatsapp_msg">Mensagem Automática</label></th>
				<td>
					<input type="text" name="forms[whatsapp_msg]" id="form_whatsapp_msg" class="large-text" value="<?php echo esc_attr( $whatsapp_msg ); ?>">
					<p class="description">Variáveis permitidas: <code>{nome}</code></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="form_receive_email">Receber Leads em (E-mail Interno)</label></th>
				<td>
					<input type="email" name="forms[receive_email]" id="form_receive_email" class="regular-text" value="<?php echo esc_attr( $receive_email ); ?>">
					<p class="description">E-mail da equipe que será notificado a cada novo lead capturado.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Confirmação para o Visitante</th>
				<td>
					<label>
						<input type="checkbox" name="forms[send_confirm]" value="1" <?php checked( $send_confirm ); ?>>
						Enviar confirmação automática para o e-mail do lead após o cadastro.
					</label>
				</td>
			</tr>
		</table>

		<h3 style="margin-top: 40px;">Depoimento na Página de Conversão</h3>
		<table class="form-table">
			<tr>
				<th scope="row">Exibir Depoimento?</th>
				<td>
					<label>
						<input type="checkbox" name="forms[show_testimonial]" value="1" <?php checked( $show_testimonial ); ?>>
						Mostrar um depoimento (5 estrelas fixas) logo abaixo do formulário na página `/novo-lead`.
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="form_testimonial_name">Nome do Cliente</label></th>
				<td>
					<input type="text" name="forms[testimonial_name]" id="form_testimonial_name" class="regular-text" value="<?php echo esc_attr( $testimonial_name ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="form_testimonial_text">Texto do Depoimento</label></th>
				<td>
					<textarea name="forms[testimonial_text]" id="form_testimonial_text" rows="3" class="large-text"><?php echo esc_textarea( $testimonial_text ); ?></textarea>
				</td>
			</tr>
		</table>
	</div>

	<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
		<h2>Google Ads — Conversão de Contato</h2>
		<p>Rastreamento simplificado na página de obrigado para eventos de conversão de Contato.</p>
		
		<table class="form-table">
			<tr>
				<th scope="row"><label for="gads_contact_snippet">Snippet de evento de conversão — Contato</label></th>
				<td>
					<textarea name="integrations[google_ads_contact_snippet]" id="gads_contact_snippet" rows="6" class="large-text" placeholder="Cole o código gtag de conversão..."></textarea>
					<?php if ( ! empty( $gads_contact['conversion_id'] ) && ! empty( $gads_contact['conversion_label'] ) ) : ?>
						<div style="margin-top: 10px; padding: 10px; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 4px; color: #2e7d32;">
							<strong>Conversão identificada:</strong> <?php echo esc_html( $gads_contact['conversion_id'] ); ?> &middot; 
							<strong>Rótulo:</strong> <?php echo esc_html( $gads_contact['conversion_label'] ); ?>
						</div>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
		<h2>Google Ads — Compras offline por CSV</h2>
		
		<table class="form-table">
			<tr>
				<th scope="row"><label for="gads_offline_name">Nome da conversão offline</label></th>
				<td>
					<input type="text" name="integrations[google_ads_offline][conversion_name]" id="gads_offline_name" class="regular-text" value="<?php echo esc_attr( $offline_name ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="gads_offline_currency">Moeda</label></th>
				<td>
					<input type="text" maxlength="3" name="integrations[google_ads_offline][currency]" id="gads_offline_currency" class="small-text" value="<?php echo esc_attr( $offline_currency ); ?>">
				</td>
			</tr>
		</table>

		<hr style="margin: 30px 0;">

		<h2>Meta Ads - Conversions API</h2>
		<p>Integração oficial server-side com a API de Conversões da Meta (Facebook/Instagram).</p>
		
		<?php
			$meta_ads = isset( $settings['meta_ads'] ) ? $settings['meta_ads'] : array();
			$meta_enabled = ! empty( $meta_ads['enabled'] );
			$meta_pixel_id = $meta_ads['pixel_id'] ?? '';
			$meta_access_token = $meta_ads['access_token'] ?? '';
			$meta_debug = ! empty( $meta_ads['debug_mode'] );
			$meta_test_code = $meta_ads['test_event_code'] ?? '';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Integração Meta</th>
				<td>
					<label for="meta_enabled">
						<input type="checkbox" name="meta_ads[enabled]" id="meta_enabled" value="1" <?php checked( $meta_enabled, true ); ?>>
						Ativar Meta Pixel (Browser) e Conversions API (Servidor)
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="meta_pixel_id">Pixel ID (Dataset)</label></th>
				<td>
					<input type="text" name="meta_ads[pixel_id]" id="meta_pixel_id" class="regular-text" value="<?php echo esc_attr( $meta_pixel_id ); ?>">
					<p class="description"><strong>Onde encontrar:</strong> Meta Events Manager > Conjuntos de dados > [Nome do Pixel] > Configurações > Identificação do conjunto de dados.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="meta_access_token">Access Token (CAPI)</label></th>
				<td>
					<input type="password" name="meta_ads[access_token]" id="meta_access_token" class="regular-text" value="<?php echo esc_attr( $meta_access_token ); ?>">
					<p class="description">Token da API de Conversões do lado do servidor (mantido 100% invisível ao navegador).<br><strong>Onde encontrar:</strong> Meta Events Manager > Conjuntos de dados > [Nome do Pixel] > Configurações > API de Conversões > Gerar token de acesso.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Modo de Teste</th>
				<td>
					<label for="meta_debug">
						<input type="checkbox" name="meta_ads[debug_mode]" id="meta_debug" value="1" <?php checked( $meta_debug, true ); ?>>
						Ativar Modo Debug
					</label>
					<p class="description">Se ativo, exibe logs avançados no Console do navegador e anexa o Test Event Code no CAPI.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="meta_test_code">Test Event Code</label></th>
				<td>
					<input type="text" name="meta_ads[test_event_code]" id="meta_test_code" class="small-text" value="<?php echo esc_attr( $meta_test_code ); ?>">
					<p class="description">Ex: TEST83214. Utilizado apenas quando o Modo Debug está habilitado.</p>
				</td>
			</tr>
		</table>
	</div>

	<p class="submit" style="max-width: 800px;">
		<?php submit_button( __( 'Salvar Configurações de Tráfego', 'hub' ), 'primary', 'submit', false ); ?>
	</p>
</form>
<script>
jQuery(document).ready(function($) {
	$('#hub_btn_generate_page').on('click', function() {
		if (!confirm('Deseja gerar a página limpa de conversão em /novo-lead agora?')) return;
		var btn = $(this), spinner = $('#hub_page_spinner');
		btn.prop('disabled', true).text('Gerando...'); spinner.addClass('is-active');
		$.post(ajaxurl, { action: 'hub_generate_page', hub_nonce: '<?php echo wp_create_nonce("hub_generate_page_nonce"); ?>' }, function(r) {
			spinner.removeClass('is-active');
			if(r.success) $('#hub_page_generator_wrapper').html('<span style="color:#28a745;font-weight:bold;margin-right:15px;">✅ Criada</span> <a href="'+r.data.url+'" target="_blank" class="button button-secondary">[ Abrir Página ]</a>');
			else { alert(r.data.message || 'Erro'); btn.prop('disabled', false).text('✨ Gerar Página'); }
		}).fail(function() { spinner.removeClass('is-active'); alert('Erro'); btn.prop('disabled', false).text('✨ Gerar Página'); });
	});
});
</script>
