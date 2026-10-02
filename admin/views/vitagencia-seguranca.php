<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'hub_save_vitagencia', 'hub_nonce' ); ?>
	<input type="hidden" name="action" value="hub_save_vitagencia">
	<input type="hidden" name="vitagencia_tab" value="seguranca">
	
	<div class="card" style="max-width: 800px; padding: 20px;">
		<h2>WordPress (Acesso e Comentários)</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="disable_comments">Desativar comentários</label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="disable_comments" name="wordpress[disable_comments]" value="1" <?php checked( $disable_comments ); ?>>
						<strong>Habilitado (Bloquear Comentários)</strong>
					</label>
					<p class="description">Desativa comentários, pingbacks e trackbacks.</p>
				</td>
			</tr>
		</table>

		<hr style="margin: 40px 0;">

		<h3>Segurança de acesso</h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="hide_login">Ocultar URL padrão de login</label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="hide_login" name="wordpress[hide_login]" value="1" <?php checked( $hide_login ); ?>>
						<strong>Habilitado (Ocultar wp-admin e wp-login)</strong>
					</label>
					<p class="description">Protege o acesso ao painel ocultando as URLs padrão.</p>
				</td>
			</tr>
		</table>

		<?php if ( $hide_login ) : ?>
			<div style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
				<p style="margin-top: 0;"><strong>URL de acesso ao painel:</strong></p>
				<div style="display: flex; align-items: center; gap: 10px;">
					<code id="vcsis_login_url" style="font-size: 14px; padding: 5px 10px;"><?php echo esc_url( home_url( '/vcsis/' ) ); ?></code>
					<button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( home_url( '/vcsis/' ) ); ?>'); alert('Copiado!');">Copiar URL</button>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
		<h2>Proteção de Plugins</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="plugin_protection_enabled">Habilitar restrição</label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="plugin_protection_enabled" name="plugin_protection_enabled" value="1" <?php checked( $pp_enabled ); ?>>
						Restringir gerenciamento de plugins aos administradores autorizados
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="plugin_protection_admins">Administradores autorizados</label>
				</th>
				<td>
					<?php if ( empty( $all_admins ) ) : ?>
						<p class="description">Nenhum administrador encontrado.</p>
					<?php else : ?>
						<select id="plugin_protection_admins" name="plugin_protection_admins[]" multiple size="<?php echo min( count( $all_admins ), 8 ); ?>" style="min-width:280px;">
							<?php foreach ( $all_admins as $admin_user ) : ?>
								<option value="<?php echo esc_attr( $admin_user->ID ); ?>" <?php echo in_array( (int) $admin_user->ID, $pp_admins, true ) ? 'selected' : ''; ?>>
									<?php echo esc_html( $admin_user->user_login . ( $admin_user->display_name && $admin_user->display_name !== $admin_user->user_login ? ' (' . $admin_user->display_name . ')' : '' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">Segure Ctrl (ou Cmd no Mac) para selecionar mais de um.</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
	</div>

	<p class="submit" style="max-width: 800px;">
		<?php submit_button( __( 'Salvar Configurações de Segurança', 'hub' ), 'primary', 'submit', false ); ?>
	</p>
</form>
