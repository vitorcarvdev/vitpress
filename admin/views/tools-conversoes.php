<?php
use Hub\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = Database::db();
$table = Database::table( 'conversions_log' );

// [FORCED_INSTALL] Garantir que a tabela existe, pois dbDelta ou ativação pode ter falhado
$table_exists = $db->get_var("SHOW TABLES LIKE '{$table}'");
if ( $table_exists !== $table ) {
	\Hub\Core\Installer::install();
	/*
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset_collate = $db->get_charset_collate();
	$sql = "CREATE TABLE {$table} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		lead_id bigint(20) UNSIGNED NOT NULL,
		lead_name varchar(191) NOT NULL,
		platform varchar(50) NOT NULL,
		conversion_type varchar(50) NOT NULL,
		status varchar(50) NOT NULL,
		request_payload longtext NULL,
		api_response longtext NULL,
		error_code varchar(191) NULL,
		gclid varchar(191) NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY platform_status (platform, status),
		KEY lead_id (lead_id)
	) {$charset_collate};";
	dbDelta( $sql );
	*/
}

// Resumo Diário
$today = current_time( 'Y-m-d' );
$summary_query = $db->get_results( $db->prepare( "
	SELECT platform, status, COUNT(*) as total 
	FROM {$table} 
	WHERE DATE(created_at) = %s 
	GROUP BY platform, status
", $today ) );

$stats = array(
	'Google Ads' => array( 'success' => 0, 'error' => 0, 'warning' => 0 ),
	'Meta Ads'   => array( 'success' => 0, 'error' => 0, 'warning' => 0 ),
);

foreach ( $summary_query as $row ) {
	$platform = $row->platform;
	$status   = $row->status;
	if ( isset( $stats[ $platform ] ) ) {
		$stats[ $platform ][ $status ] += (int) $row->total;
	}
}

// Paginação
$per_page = 20;
$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset = ( $paged - 1 ) * $per_page;

$total_items = (int) $db->get_var( "SELECT COUNT(*) FROM {$table}" );
$total_pages = ceil( $total_items / $per_page );

$logs = $db->get_results( $db->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
?>

<div class="wrap hub-wrap">
	<h2>Diagnóstico de Conversões Offline</h2>
	<p>Acompanhe em tempo real as tentativas de envio de eventos para as plataformas de anúncios.</p>

	<!-- Resumo Hoje -->
	<div style="display: flex; gap: 20px; margin-bottom: 30px; margin-top: 20px;">
		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px 20px; border-radius: 4px; min-width: 200px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h3 style="margin-top: 0; font-size: 15px;">Google Ads <span style="color:#666; font-weight:normal; font-size: 13px;">(Hoje)</span></h3>
			<div style="display:flex; justify-content: space-between; margin-bottom: 5px;">
				<span>🟢 Aceitas:</span>
				<strong><?php echo $stats['Google Ads']['success'] ?? 0; ?></strong>
			</div>
			<div style="display:flex; justify-content: space-between; margin-bottom: 5px;">
				<span>🟡 Pendentes/Simulação:</span>
				<strong><?php echo $stats['Google Ads']['pending'] ?? 0; ?></strong>
			</div>
			<div style="display:flex; justify-content: space-between; margin-bottom: 5px;">
				<span>🔴 Erros/Rejeitadas:</span>
				<strong><?php echo $stats['Google Ads']['error'] ?? 0; ?></strong>
			</div>
			<div style="display:flex; justify-content: space-between;">
				<span>⚪ Não Enviadas (S/ GCLID):</span>
				<strong><?php echo $stats['Google Ads']['skipped'] ?? 0; ?></strong>
			</div>
		</div>

		<div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px 20px; border-radius: 4px; min-width: 200px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<h3 style="margin-top: 0; font-size: 15px;">Meta Ads <span style="color:#666; font-weight:normal; font-size: 13px;">(Hoje)</span></h3>
			<div style="display:flex; justify-content: space-between; margin-bottom: 5px;">
				<span>🟢 Aceitas:</span>
				<strong><?php echo $stats['Meta Ads']['success'] ?? 0; ?></strong>
			</div>
			<div style="display:flex; justify-content: space-between; margin-bottom: 5px;">
				<span>🟡 Pendentes:</span>
				<strong><?php echo $stats['Meta Ads']['pending'] ?? 0; ?></strong>
			</div>
			<div style="display:flex; justify-content: space-between;">
				<span>🔴 Erros/Rejeitadas:</span>
				<strong><?php echo $stats['Meta Ads']['error'] ?? 0; ?></strong>
			</div>
		</div>
	</div>

	<!-- Tabela de Logs -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 140px;">Data e Hora</th>
				<th>Lead</th>
				<th style="width: 150px;">Plataforma</th>
				<th style="width: 120px;">Conversão</th>
				<th style="width: 150px;">Status</th>
				<th style="width: 100px;">Detalhes</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="6">Nenhuma conversão registrada ainda.</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : 
					$status_html = '⚪ Desconhecido';
					if ( $log->status === 'success' ) $status_html = '🟢 Aceito';
					elseif ( $log->status === 'pending' ) $status_html = '🟡 Pendente/Simulação';
					elseif ( $log->status === 'error' ) $status_html = '🔴 Rejeitado/Erro';
					elseif ( $log->status === 'skipped' ) $status_html = '⚪ Não Enviado';

					$json_request = htmlspecialchars( $log->request_payload, ENT_QUOTES, 'UTF-8' );
					$json_response = htmlspecialchars( $log->api_response, ENT_QUOTES, 'UTF-8' );
					$gclid_masked = strlen($log->gclid) > 10 ? substr($log->gclid, 0, 10) . '********' : $log->gclid;
				?>
					<tr>
						<td><?php echo wp_date( 'd/m H:i', strtotime( $log->created_at ) ); ?></td>
						<td><?php echo esc_html( $log->lead_name ); ?></td>
						<td><?php echo esc_html( $log->platform ); ?></td>
						<td><?php echo esc_html( $log->conversion_type ); ?></td>
						<td><?php echo $status_html; ?></td>
						<td>
							<button type="button" class="button button-small hub-view-log-btn" 
								data-lead="<?php echo esc_attr( $log->lead_name ); ?>"
								data-platform="<?php echo esc_attr( $log->platform ); ?>"
								data-conversion="<?php echo esc_attr( $log->conversion_type ); ?>"
								data-status="<?php echo esc_attr( $status_html ); ?>"
								data-gclid="<?php echo esc_attr( $gclid_masked ); ?>"
								data-error="<?php echo esc_attr( $log->error_message ); ?>"
								data-reqid="<?php echo esc_attr( $log->request_id ); ?>"
								data-endpoint="<?php echo esc_attr( $log->endpoint ); ?>"
								data-request="<?php echo $json_request; ?>"
								data-response="<?php echo $json_response; ?>">
								Ver Detalhes
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<span class="displaying-num"><?php echo $total_items; ?> itens</span>
				<span class="pagination-links">
					<?php
					echo paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $paged,
					) );
					?>
				</span>
			</div>
		</div>
	<?php endif; ?>
</div>

<!-- Modal Detalhes -->
<div id="hub-log-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:99999;">
	<div style="background:#fff; width: 700px; max-width:90%; margin: 50px auto; border-radius:4px; box-shadow:0 3px 6px rgba(0,0,0,0.3); display:flex; flex-direction:column; max-height: calc(100vh - 100px);">
		<div style="padding: 15px 20px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
			<h2 style="margin:0;">Detalhes da Conversão</h2>
			<button type="button" id="hub-close-modal" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
		</div>
		
		<div style="padding: 20px; overflow-y:auto; flex:1;">
			<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
				<div><strong>Lead:</strong> <span id="modal-lead"></span></div>
				<div><strong>Plataforma:</strong> <span id="modal-platform"></span></div>
				<div><strong>Conversão:</strong> <span id="modal-conversion"></span></div>
				<div><strong>Status:</strong> <span id="modal-status"></span></div>
				<div><strong>GCLID/FBCLID:</strong> <span id="modal-gclid"></span></div>
				<div><strong>Request ID:</strong> <span id="modal-reqid"></span></div>
				<div style="grid-column: span 2;"><strong>Endpoint:</strong> <span id="modal-endpoint"></span></div>
				<div style="color:#dc3232; grid-column: span 2;"><strong>Mensagem (Erro/Alerta):</strong> <span id="modal-error"></span></div>
			</div>

			<h3 style="font-size:14px; margin-bottom:5px;">Payload Enviado (Request)</h3>
			<pre id="modal-request" style="background:#f1f1f1; padding:10px; border:1px solid #ccc; border-radius:3px; font-size:12px; white-space:pre-wrap; word-wrap:break-word; max-height:200px; overflow:auto; margin-bottom:20px;"></pre>

			<h3 style="font-size:14px; margin-bottom:5px;">Resposta da API</h3>
			<pre id="modal-response" style="background:#23282d; color:#00ff00; padding:10px; border-radius:3px; font-size:12px; white-space:pre-wrap; word-wrap:break-word; max-height:300px; overflow:auto; margin:0;"></pre>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('.hub-view-log-btn').on('click', function() {
		var btn = $(this);
		
		$('#modal-lead').text(btn.data('lead'));
		$('#modal-platform').text(btn.data('platform'));
		$('#modal-conversion').text(btn.data('conversion'));
		$('#modal-status').text(btn.data('status'));
		$('#modal-gclid').text(btn.data('gclid') || '-');
		$('#modal-reqid').text(btn.data('reqid') || '-');
		$('#modal-endpoint').text(btn.data('endpoint') || '-');
		$('#modal-error').text(btn.data('error') || '-');

		var reqStr = btn.data('request');
		if (typeof reqStr === 'object') {
			$('#modal-request').text(JSON.stringify(reqStr, null, 2));
		} else {
			try {
				var reqObj = JSON.parse(reqStr);
				$('#modal-request').text(JSON.stringify(reqObj, null, 2));
			} catch(e) {
				$('#modal-request').text(reqStr || 'Nenhum payload registrado.');
			}
		}

		var resStr = btn.data('response');
		if (typeof resStr === 'object') {
			$('#modal-response').text(JSON.stringify(resStr, null, 2));
		} else {
			try {
				var resObj = JSON.parse(resStr);
				$('#modal-response').text(JSON.stringify(resObj, null, 2));
			} catch(e) {
				$('#modal-response').text(resStr || 'Nenhuma resposta da API registrada.');
			}
		}

		$('#hub-log-modal').fadeIn(200);
	});

	$('#hub-close-modal').on('click', function() {
		$('#hub-log-modal').fadeOut(200);
	});
});
</script>
