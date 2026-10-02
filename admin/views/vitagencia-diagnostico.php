<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="card" style="max-width: 1000px; padding: 20px;">
	<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 15px; margin-bottom: 20px;">
		<h2 style="margin: 0;">Diagnóstico Inteligente</h2>
		<div>
			<button class="button button-primary" id="hub_btn_run_diag">
				<span class="dashicons dashicons-update" style="margin-top:4px;"></span> Reavaliar Sistema
			</button>
		</div>
	</div>

	<div id="hub_diag_loading" style="text-align: center; padding: 40px;">
		<span class="spinner is-active" style="float:none; width: 40px; height: 40px; background-size: 40px;"></span>
		<p style="font-size: 16px; margin-top: 15px;">Analisando a saúde do seu WordPress...</p>
	</div>

	<div id="hub_diag_content" style="display: none;">
		
		<div id="hub_diag_score_box" style="text-align: center; margin-bottom: 30px; padding: 20px; border-radius: 8px; border: 2px solid #ccc;">
			<h3 style="margin: 0 0 10px; font-size: 18px;">Saúde Geral</h3>
			<div id="hub_diag_score" style="font-size: 48px; font-weight: bold;"></div>
			<div id="hub_diag_indicator" style="font-size: 18px; margin-top: 5px;"></div>
		</div>

		<div id="hub_diag_items_list">
			<!-- Os itens do diagnóstico serão injetados aqui via JS -->
		</div>

	</div>
</div>

<style>
.hub-diag-item {
	border: 1px solid #ccd0d4;
	background: #fff;
	border-left: 4px solid #ccd0d4;
	padding: 15px 20px;
	margin-bottom: 15px;
	border-radius: 4px;
	display: flex;
	justify-content: space-between;
	align-items: center;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.hub-diag-item.status-ok {
	border-left-color: #46b450;
}
.hub-diag-item.status-warning {
	border-left-color: #ffb900;
}
.hub-diag-item.status-error {
	border-left-color: #dc3232;
}
.hub-diag-item.status-info {
	border-left-color: #00a0d2;
}
.hub-diag-info h4 {
	margin: 0 0 5px;
	font-size: 16px;
}
.hub-diag-info p {
	margin: 0;
	color: #666;
	font-size: 13px;
}
.hub-diag-metrics {
	display: flex;
	gap: 20px;
	margin-top: 10px;
}
.hub-diag-metric {
	background: #f0f0f1;
	padding: 5px 10px;
	border-radius: 3px;
	font-size: 12px;
}
.hub-diag-metric strong {
	display: block;
	color: #333;
}
.hub-diag-action {
	text-align: right;
	min-width: 250px;
}
</style>

<script>
jQuery(document).ready(function($) {
	
	function runDiagnostic() {
		$('#hub_diag_content').hide();
		$('#hub_diag_loading').show();
		$('#hub_btn_run_diag').prop('disabled', true);

		$.post(ajaxurl, {
			action: 'hub_run_diagnostic',
			hub_nonce: '<?php echo esc_js( wp_create_nonce("hub_diagnostic_nonce") ); ?>'
		}, function(response) {
			$('#hub_diag_loading').hide();
			$('#hub_btn_run_diag').prop('disabled', false);

			if (response.success) {
				renderDiagnostic(response.data);
			} else {
				alert('Erro ao rodar diagnóstico: ' + (response.data.message || 'Erro desconhecido.'));
			}
		}).fail(function() {
			$('#hub_diag_loading').hide();
			$('#hub_btn_run_diag').prop('disabled', false);
			alert('Falha na comunicação com o servidor.');
		});
	}

	function renderDiagnostic(data) {
		var scoreBox = $('#hub_diag_score_box');
		var scoreEl = $('#hub_diag_score');
		var indicatorEl = $('#hub_diag_indicator');
		var itemsList = $('#hub_diag_items_list');
		
		itemsList.empty();

		// Score
		scoreEl.text(data.score + '%');
		if (data.score === 100) {
			scoreBox.css('border-color', '#46b450');
			scoreEl.css('color', '#46b450');
			indicatorEl.html('🟢 Excelente');
		} else if (data.score >= 80) {
			scoreBox.css('border-color', '#ffb900');
			scoreEl.css('color', '#ffb900');
			indicatorEl.html('🟡 Atenção');
		} else {
			scoreBox.css('border-color', '#dc3232');
			scoreEl.css('color', '#dc3232');
			indicatorEl.html('🔴 Problemas encontrados');
		}

		// Items
		$.each(data.results, function(key, item) {
			var icon = '';
			if (item.status === 'ok') icon = '<span class="dashicons dashicons-yes-alt" style="color:#46b450; font-size:24px; width:24px; height:24px; margin-right:5px; vertical-align:middle;"></span>';
			else if (item.status === 'warning') icon = '<span class="dashicons dashicons-warning" style="color:#ffb900; font-size:24px; width:24px; height:24px; margin-right:5px; vertical-align:middle;"></span>';
			else if (item.status === 'info') icon = '<span class="dashicons dashicons-info" style="color:#00a0d2; font-size:24px; width:24px; height:24px; margin-right:5px; vertical-align:middle;"></span>';
			else icon = '<span class="dashicons dashicons-dismiss" style="color:#dc3232; font-size:24px; width:24px; height:24px; margin-right:5px; vertical-align:middle;"></span>';

			var html = '<div class="hub-diag-item status-' + item.status + '" id="diag_item_' + key + '">';
			html += '<div class="hub-diag-info">';
			html += '<h4>' + icon + item.title + '</h4>';
			html += '<p>' + item.desc + '</p>';
			
			html += '<div class="hub-diag-metrics">';
			html += '<div class="hub-diag-metric">Atual: <strong>' + item.current + '</strong></div>';
			html += '<div class="hub-diag-metric">Recomendado: <strong>' + item.recommended + '</strong></div>';
			html += '</div>'; // close metrics

			html += '</div>'; // close info
			
			html += '<div class="hub-diag-action">';
			if (item.can_fix && item.fix_label) {
				html += '<button type="button" class="button button-secondary hub-btn-fix" data-key="' + key + '">✨ ' + item.fix_label + '</button>';
				html += '<span class="spinner" style="float:none; margin-top:0;"></span>';
			} else if (item.status === 'ok') {
				html += '<span style="color:#46b450; font-weight:bold;">OK</span>';
			}
			html += '</div>'; // close action

			html += '</div>'; // close item

			itemsList.append(html);
		});

		$('#hub_diag_content').fadeIn();
	}

	$('#hub_btn_run_diag').on('click', function() {
		runDiagnostic();
	});

	// Handle Fixes
	$(document).on('click', '.hub-btn-fix', function() {
		var btn = $(this);
		var key = btn.data('key');
		var parent = btn.closest('.hub-diag-item');
		var spinner = parent.find('.spinner');

		if (!confirm('Deseja executar a ação recomendada? Esta ação poderá criar backups e alterar o sistema automaticamente.')) {
			return;
		}

		btn.prop('disabled', true);
		spinner.addClass('is-active');

		$.post(ajaxurl, {
			action: 'hub_fix_diagnostic',
			item: key,
			hub_nonce: '<?php echo esc_js( wp_create_nonce("hub_diagnostic_nonce") ); ?>'
		}, function(response) {
			spinner.removeClass('is-active');
			
			if (response.success) {
				alert(response.data.message);
				// Re-run diagnostic automatically to update UI
				runDiagnostic();
			} else {
				alert('Erro: ' + (response.data.message || 'Falha ao aplicar configuração.'));
				btn.prop('disabled', false);
			}
		}).fail(function() {
			spinner.removeClass('is-active');
			btn.prop('disabled', false);
			alert('Falha na comunicação com o servidor.');
		});
	});

	// Run on load
	runDiagnostic();
});
</script>
