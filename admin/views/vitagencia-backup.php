<?php
/**
 * admin/views/vitagencia-backup.php
 * Aba de Backup e Migração na Área VitAgência.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$backup_service = new \Hub\Core\Services\BackupService();
$legacy_backups = $backup_service->get_legacy_backups();
?>
<div class="card" style="max-width: 800px; padding: 20px;">
	<h2>Backup e Migração</h2>
	<p class="description">
		O Hub não armazena backups persistentes neste servidor devido às políticas de segurança e limite de disco. 
		Todas as operações abaixo geram arquivos temporários que são excluídos logo após o download.
	</p>

	<?php if ( isset( $_GET['message'] ) && 'restored' === $_GET['message'] ) : ?>
		<div class="notice notice-success inline"><p>Restauração concluída com sucesso!</p></div>
	<?php endif; ?>
	
	<?php if ( isset( $_GET['message'] ) && 'legacy_deleted' === $_GET['message'] ) : ?>
		<div class="notice notice-success inline"><p>Backups antigos removidos com sucesso.</p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['message'] ) && 's3_saved' === $_GET['message'] ) : ?>
		<div class="notice notice-success inline"><p>Configurações do Amazon S3 salvas com sucesso.</p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['message'] ) && 's3_success' === $_GET['message'] ) : ?>
		<div class="notice notice-success inline"><p>Teste de Conexão S3 realizado com sucesso! As credenciais estão corretas.</p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['error'] ) ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $legacy_backups ) ) : ?>
		<div class="notice notice-warning inline" style="padding: 15px; background: #fff8e5; border-left: 4px solid #f0b849; margin-bottom: 20px;">
			<h3 style="margin-top: 0;">Atenção: Backups Legados Encontrados</h3>
			<p>Existem <strong><?php echo count( $legacy_backups ); ?></strong> backups antigos armazenados neste servidor gerados por versões anteriores. A política atual não permite a permanência desses arquivos.</p>
			
			<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
				<thead>
					<tr>
						<th>Data</th>
						<th>Arquivo</th>
						<th>Tamanho</th>
						<th>Ações</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $legacy_backups as $lb ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', $lb['date'] ) ); ?></td>
							<td><?php echo esc_html( $lb['name'] ); ?></td>
							<td><?php echo esc_html( $lb['size'] ); ?></td>
							<td>
								<div style="display: flex; gap: 5px;">
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="hub_download_legacy">
										<input type="hidden" name="file_name" value="<?php echo esc_attr( $lb['name'] ); ?>">
										<?php wp_nonce_field( 'hub_legacy_action', 'hub_nonce' ); ?>
										<button type="submit" class="button button-small">Baixar</button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Tem certeza?');">
										<input type="hidden" name="action" value="hub_delete_legacy">
										<input type="hidden" name="file_name" value="<?php echo esc_attr( $lb['name'] ); ?>">
										<?php wp_nonce_field( 'hub_legacy_action', 'hub_nonce' ); ?>
										<button type="submit" class="button button-small button-link-delete" style="color:#b32d2e;">Excluir</button>
									</form>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Apagar TODOS os backups legados?');" style="margin-top: 10px;">
				<input type="hidden" name="action" value="hub_delete_legacy">
				<input type="hidden" name="file_name" value="ALL">
				<?php wp_nonce_field( 'hub_legacy_action', 'hub_nonce' ); ?>
				<button type="submit" class="button button-secondary">Excluir Todos os Arquivos Antigos</button>
			</form>
		</div>
	<?php endif; ?>

	<hr style="margin: 30px 0;">

	<h3>BACKUP COMPLETO</h3>
	<p>Gere uma cópia completa do site para migração ou segurança.<br>O arquivo <code>.zip</code> é temporário e removido automaticamente após o download.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank" onsubmit="setTimeout(function(){window.location.reload();}, 3000);">
		<input type="hidden" name="action" value="hub_run_backup_full">
		<?php wp_nonce_field( 'hub_run_backup', 'hub_nonce' ); ?>
		<button type="submit" class="button button-primary">Gerar e Baixar Backup Completo (.zip)</button>
	</form>

	<hr style="margin: 30px 0;">

	<h3>BANCO DE DADOS</h3>
	<p>Gere manualmente uma cópia do banco de dados.<br>O arquivo não permanece armazenado no servidor.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank" onsubmit="setTimeout(function(){window.location.reload();}, 3000);">
		<input type="hidden" name="action" value="hub_run_backup_db">
		<?php wp_nonce_field( 'hub_run_backup', 'hub_nonce' ); ?>
		<button type="submit" class="button button-primary">Gerar e Baixar Banco de Dados</button>
	</form>

	<hr style="margin: 30px 0;">

	<h3>RESTAURAR / MIGRAR SITE</h3>
	<p>Importe um <code>.zip</code> criado pelo VitPress. Backups antigos do Hub VitAgência continuam válidos.</p>
	<form id="hub-chunked-upload-form" enctype="multipart/form-data">
		<?php wp_nonce_field( 'hub_upload_backup', 'hub_nonce_upload' ); ?>
		<input type="file" id="hubbackup_file" accept=".zip" required style="margin-bottom: 10px; display: block;">
		<button type="submit" id="hub-btn-upload-chunked" class="button button-secondary">Restaurar Site (Upload Seguro)</button>
		<div id="hub-upload-progress-container" style="display:none; margin-top: 15px; background:#eee; height:20px; width:100%; border-radius:10px; overflow:hidden;">
			<div id="hub-upload-progress-bar" style="background:#2271b1; height:100%; width:0%; transition: width 0.1s;"></div>
		</div>
		<p id="hub-upload-status-text" style="display:none; font-weight:bold; color:#2271b1;"></p>
	</form>

	<script>
	jQuery(document).ready(function($) {
		$('#hub-chunked-upload-form').on('submit', async function(e) {
			e.preventDefault();
			var fileInput = document.getElementById('hubbackup_file');
			if (!fileInput.files.length) return;
			
			var file = fileInput.files[0];
			var chunkSize = 2 * 1024 * 1024; // 2MB por requisição
			var totalChunks = Math.ceil(file.size / chunkSize);
			var nonce = $('#hub_nonce_upload').val();
			
			$('#hub-btn-upload-chunked').prop('disabled', true).text('Enviando...');
			$('#hub-upload-progress-container').show();
			var $status = $('#hub-upload-status-text').show().text('Enviando 0%');
			var $bar = $('#hub-upload-progress-bar');
			
			for (var i = 0; i < totalChunks; i++) {
				var start = i * chunkSize;
				var end = Math.min(start + chunkSize, file.size);
				var chunk = file.slice(start, end);
				
				var formData = new FormData();
				formData.append('action', 'hub_upload_backup_chunk');
				formData.append('hub_nonce', nonce);
				formData.append('chunk', chunk);
				formData.append('chunk_index', i);
				formData.append('total_chunks', totalChunks);
				formData.append('filename', file.name);
				
				try {
					var response = await $.ajax({
						url: ajaxurl,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false
					});
					
					if (!response.success) {
						alert('Erro no envio: ' + (response.data || 'Falha desconhecida.'));
						$('#hub-btn-upload-chunked').prop('disabled', false).text('Restaurar Site (Upload Seguro)');
						return;
					}
					
					var percent = Math.round(((i + 1) / totalChunks) * 100);
					$bar.css('width', percent + '%');
					
					if (i === totalChunks - 1) {
						$status.text('Verificando integridade e preparando restauração...');
						if (response.data && response.data.redirect) {
							window.location.href = response.data.redirect;
						}
					} else {
						$status.text('Enviando ' + percent + '%');
					}
					
				} catch (err) {
					alert('Erro de conexão ao enviar o arquivo.');
					$('#hub-btn-upload-chunked').prop('disabled', false).text('Restaurar Site (Upload Seguro)');
					return;
				}
			}
		});
	});
	</script>
	
	</div>

<?php 
$s3 = $settings['s3'] ?? []; 
$s3_enabled = ! empty( $s3['enabled'] );
$s3_access  = $s3['access_key'] ?? '';
$s3_secret  = $s3['secret_key'] ?? '';
$s3_region  = $s3['region'] ?? 'sa-east-1';
$s3_bucket  = $s3['bucket'] ?? '';
$retention_db = $s3['retention_db'] ?? 30;
$retention_full = $s3['retention_full'] ?? 4;

$display_secret = !empty($s3_secret) ? str_repeat('*', 36) . substr($s3_secret, -4) : '';
?>
<div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
	<h2>Integração Amazon S3 (Backups Automáticos)</h2>
	<p class="description">
		Configure o envio automático de backups para o seu bucket no Amazon S3. Quando ativado, o Hub fará o backup do banco de dados <strong>diariamente</strong> e o backup completo <strong>semanalmente</strong>.
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="hub_save_s3_backup">
		<?php wp_nonce_field( 'hub_s3_backup_nonce', 'hub_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row">Status</th>
				<td>
					<label>
						<input type="checkbox" name="s3[enabled]" value="1" <?php checked( $s3_enabled ); ?>>
						Habilitar rotinas de backup automático para o S3
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s3_access">Chave de Acesso (Access Key)</label></th>
				<td>
					<input name="s3[access_key]" type="text" id="s3_access" value="<?php echo esc_attr( $s3_access ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s3_secret">Chave Secreta (Secret Key)</label></th>
				<td>
					<input name="s3[secret_key]" type="password" id="s3_secret" value="<?php echo esc_attr( $display_secret ); ?>" class="regular-text" placeholder="Deixe em branco para manter a atual">
					<p class="description">A chave secreta não é exibida integralmente por motivos de segurança.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s3_region">Região</label></th>
				<td>
					<input name="s3[region]" type="text" id="s3_region" value="<?php echo esc_attr( $s3_region ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s3_bucket">Bucket</label></th>
				<td>
					<input name="s3[bucket]" type="text" id="s3_bucket" value="<?php echo esc_attr( $s3_bucket ); ?>" class="regular-text" placeholder="ex: bkps-sites-vcsis">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s3_retention_db">Retenção de BD</label></th>
				<td>
					Manter os últimos <input name="s3[retention_db]" type="number" id="s3_retention_db" value="<?php echo esc_attr( $retention_db ); ?>" class="small-text"> backups de Banco de Dados armazenados na Amazon S3.
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="s3_retention_full">Retenção Completo</label></th>
				<td>
					Manter os últimos <input name="s3[retention_full]" type="number" id="s3_retention_full" value="<?php echo esc_attr( $retention_full ); ?>" class="small-text"> backups Completos armazenados na Amazon S3.
				</td>
			</tr>
		</table>

		<p class="submit" style="display: flex; gap: 15px; align-items: center; max-width: 800px; flex-wrap: wrap;">
			<button type="submit" name="submit_action" value="save" class="button button-primary">Salvar Configurações S3</button>
			<span style="border-left: 1px solid #ccc; height: 30px;"></span>
			<button type="submit" name="submit_action" value="test" class="button button-secondary">Testar Conexão S3</button>
		</p>
	</form>

	<hr>
	<h3>Diagnóstico do Worker</h3>
	<?php
	$job_state = get_option( 'hub_backup_job_state', false );
	if ( $job_state ) :
		$status = $job_state['status'] ?? 'N/A';
		$color = 'black';
		if ($status === 'done') $color = 'green';
		if ($status === 'error') $color = 'red';
		if (in_array($status, ['uploading', 'pending_generation', 'init_upload', 'completing'])) $color = 'orange';
	?>
		<p><strong>Último Job:</strong> <?php echo esc_html($job_state['type'] ?? ''); ?></p>
		<p><strong>Status:</strong> <span style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo esc_html($status); ?></span></p>
		<p><strong>Atualizado em:</strong> <?php echo esc_html($job_state['updated_at'] ?? $job_state['start'] ?? 'N/A'); ?></p>
		<?php if ( ! empty($job_state['error']) ) : ?>
			<p style="color:red;"><strong>Último Erro:</strong> <?php echo esc_html($job_state['error']); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p>Nenhum job de backup registrado ainda.</p>
	<?php endif; ?>
</div>

<?php if ( isset( $_GET['restore_file'] ) ) : ?>
<div id="hub-restore-overlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:99999; display:flex; align-items:center; justify-content:center; color:#fff;">
	<div style="background:#fff; color:#333; padding:30px; border-radius:8px; width:500px; max-width:90%; text-align:center;">
				<h2>Restaurando e Migrando...</h2>
		<style>@keyframes hubspin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
		<div style="border: 4px solid #f3f3f3; border-top: 4px solid #2271b1; border-radius: 50%; width: 40px; height: 40px; animation: hubspin 1s linear infinite; margin: 0 auto 15px auto;"></div>
		<p id="hub-restore-msg">Iniciando processo...</p>
		<div style="background:#eee; height:20px; width:100%; border-radius:10px; margin-top:20px; overflow:hidden;">
			<div id="hub-restore-bar" style="background:#2271b1; height:100%; width:0%; transition: width 0.3s;"></div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	var file = '<?php echo esc_js( sanitize_text_field( $_GET['restore_file'] ) ); ?>';
	var nonce = '<?php echo wp_create_nonce( 'hub_restore_chunk' ); ?>';

	function processStep(step) {
		$.post(ajaxurl, {
			action: 'hub_restore_chunk',
			hub_nonce: nonce,
			file: file,
			step: step
		}, function(response) {
			if (response.success) {
				var data = response.data;
				$('#hub-restore-msg').text(data.msg);
				$('#hub-restore-bar').css('width', data.progress + '%');

				if (data.next_step === 'done') {
					$('#hub-restore-msg').text('Finalizado! Recarregando página...');
					setTimeout(function() {
						window.location.href = '<?php echo admin_url( 'admin.php?page=hub-vitagencia&tab=backup&message=restored' ); ?>';
					}, 2000);
				} else {
					setTimeout(function() {
						processStep(data.next_step);
					}, 1000); // pequeno delay visual
				}
			} else {
				alert('Erro: ' + response.data);
				$('#hub-restore-overlay').hide();
				window.location.href = '<?php echo admin_url( 'admin.php?page=hub-vitagencia&tab=backup' ); ?>';
			}
		}).fail(function(jqXHR, textStatus, errorThrown) {
			var respText = jqXHR.responseText ? jqXHR.responseText.substring(0, 200) : '';
			alert('Falha na etapa [' + step + ']\nStatus: ' + textStatus + '\nHTTP: ' + jqXHR.status + '\nErro: ' + errorThrown + '\nResposta: ' + respText);
			$('#hub-restore-overlay').hide();
			window.location.href = '<?php echo admin_url( 'admin.php?page=hub-vitagencia&tab=backup' ); ?>';
		});
	}

	processStep('init');
});
</script>
<?php endif; ?>
