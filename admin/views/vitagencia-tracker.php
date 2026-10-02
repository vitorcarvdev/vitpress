<?php
if ( ! defined( "ABSPATH" ) ) {
	exit;
}
?>

<div class="card" style="max-width: 1000px; padding: 20px;">
	<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #ccc; padding-bottom: 15px; margin-bottom: 20px;">
		<h2 style="margin: 0;">Diagnóstico Inteligente: Tracker</h2>
		<div style="display: flex; align-items: center; gap: 10px;">
			<input type="text" id="hub_test_url" class="regular-text" placeholder="URL p/ teste (Ex: https://site.com/lp)" value="" style="width: 300px;">
			<button class="button button-secondary" id="hub_btn_test_real">
				<span class="dashicons dashicons-external" style="margin-top:4px;"></span> Testar Fluxo Real
			</button>
			<button class="button button-primary" id="hub_btn_test_sim">
				<span class="dashicons dashicons-update" style="margin-top:4px;"></span> Simular Tracker
			</button>
		</div>
	</div>

	<div id="hub_tracker_diag_loading" style="text-align: center; padding: 40px; display: none;">
		<span class="spinner is-active" style="float:none; width: 40px; height: 40px; background-size: 40px;"></span>
		<p style="font-size: 16px; margin-top: 15px;" id="hub_tracker_diag_msg">Avaliando captura de origem...</p>
	</div>

	<div id="hub_tracker_diag_content" style="display: none;">
		
		<div id="hub_tracker_score_box" style="text-align: center; margin-bottom: 30px; padding: 20px; border-radius: 8px; border: 2px solid #ccc;">
			<h3 style="margin: 0 0 10px; font-size: 18px;">Status do Tracker</h3>
			<div id="hub_tracker_indicator" style="font-size: 22px; font-weight: bold; margin-top: 5px;"></div>
		</div>

		<div style="display: flex; gap: 20px; margin-bottom: 30px;">
			<div style="flex: 2;">
				<h3 style="margin-top: 0;">Indicadores de Captura</h3>
				<div id="hub_tracker_items_list">
					<!-- Os itens do diagnóstico serão injetados aqui via JS -->
				</div>
			</div>
			<div style="flex: 1;">
				<div style="background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #ddd;">
					<h3 style="margin-top: 0; font-size: 15px;">Última captura</h3>
					<div id="hub_tracker_summary">
						<!-- Resumo da captura será injetado aqui -->
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
.hub-diag-item {
	border: 1px solid #ccd0d4;
	background: #fff;
	border-left: 4px solid #ccd0d4;
	padding: 12px 15px;
	margin-bottom: 10px;
	border-radius: 4px;
	display: flex;
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
.hub-diag-info {
	flex: 1;
}
.hub-diag-info h4 {
	margin: 0 0 2px;
	font-size: 14px;
	display: flex;
	align-items: center;
}
.hub-diag-info p {
	margin: 0;
	color: #666;
	font-size: 12px;
	padding-left: 29px;
}
.hub-summary-item {
	margin-bottom: 10px;
	font-size: 13px;
}
.hub-summary-item strong {
	display: block;
	color: #444;
	font-size: 12px;
	text-transform: uppercase;
	margin-bottom: 2px;
}
.hub-summary-item span {
	color: #111;
	word-break: break-all;
}
</style>

<script>
jQuery(document).ready(function($) {
	
	function runTrackerDiagnostic(simulate) {
		$("#hub_tracker_diag_content").hide();
		$("#hub_tracker_diag_msg").text(simulate ? "Simulando ambiente..." : "Lendo cookies e histórico...");
		$("#hub_tracker_diag_loading").show();
		
		$("#hub_btn_test_sim").prop("disabled", true);
		$("#hub_btn_test_real").prop("disabled", true);
		
		var testUrlPath = $("#hub_test_url").val().trim();

		$.post(ajaxurl, {
			action: "hub_run_tracker_diagnostic",
			simulate: simulate ? 1 : 0,
			test_url: testUrlPath,
			hub_nonce: "<?php echo esc_js( wp_create_nonce("hub_diagnostic_nonce") ); ?>"
		}, function(response) {
			$("#hub_tracker_diag_loading").hide();
			$("#hub_btn_test_sim").prop("disabled", false);
			$("#hub_btn_test_real").prop("disabled", false);

			if (response.success) {
				renderTrackerDiagnostic(response.data);
			} else {
				alert("Erro ao rodar diagnóstico: " + (response.data.message || "Erro desconhecido."));
			}
		}).fail(function() {
			$("#hub_tracker_diag_loading").hide();
			$("#hub_btn_test_sim").prop("disabled", false);
			$("#hub_btn_test_real").prop("disabled", false);
			alert("Falha na comunicação com o servidor.");
		});
	}

	function renderTrackerDiagnostic(data) {
		var scoreBox = $("#hub_tracker_score_box");
		var indicatorEl = $("#hub_tracker_indicator");
		var itemsList = $("#hub_tracker_items_list");
		var summary = $("#hub_tracker_summary");
		
		itemsList.empty();
		summary.empty();

		// Score Global
		if (data.status === "ok") {
			scoreBox.css("border-color", "#46b450");
			indicatorEl.css("color", "#46b450").html("✅ Saudável (Captura Completa)");
		} else if (data.status === "warning") {
			scoreBox.css("border-color", "#ffb900");
			indicatorEl.css("color", "#ffb900").html("⚠️ Atenção (Faltam UTMs ou Origem)");
		} else {
			scoreBox.css("border-color", "#dc3232");
			indicatorEl.css("color", "#dc3232").html("❌ Problemas (Nenhum dado encontrado)");
		}

		// Items
		$.each(data.results, function(key, item) {
			var icon = "";
			if (item.status === "ok") icon = "<span class=\"dashicons dashicons-yes-alt\" style=\"color:#46b450; font-size:24px; width:24px; height:24px; margin-right:5px;\"></span>";
			else if (item.status === "warning") icon = "<span class=\"dashicons dashicons-warning\" style=\"color:#ffb900; font-size:24px; width:24px; height:24px; margin-right:5px;\"></span>";
			else if (item.status === "info") icon = "<span class=\"dashicons dashicons-info\" style=\"color:#00a0d2; font-size:24px; width:24px; height:24px; margin-right:5px;\"></span>";
			else icon = "<span class=\"dashicons dashicons-dismiss\" style=\"color:#dc3232; font-size:24px; width:24px; height:24px; margin-right:5px;\"></span>";

			var html = "<div class=\"hub-diag-item status-" + item.status + "\">";
			html += "<div class=\"hub-diag-info\">";
			html += "<h4>" + icon + item.title + "</h4>";
			if (item.desc) {
				html += "<p>" + item.desc + "</p>";
			}
			html += "</div>"; // close info
			html += "</div>"; // close item

			itemsList.append(html);
		});

		// Summary
		if (data.summary && Object.keys(data.summary).length > 0) {
			$.each(data.summary, function(label, value) {
				if (value) {
					summary.append("<div class=\"hub-summary-item\"><strong>" + label + "</strong><span>" + value + "</span></div>");
				}
			});
		} else {
			summary.append("<div class=\"hub-summary-item\"><span>Nenhum dado capturado na sua sessão atual.</span></div>");
			summary.append("<p style=\"font-size:12px; color:#666; margin-top:10px;\">Clique em \"Simular Tracker\" ou \"Testar Fluxo Real\" para gerar dados de teste.</p>");
		}

		$("#hub_tracker_diag_content").fadeIn();
	}

	$("#hub_btn_test_sim").on("click", function() {
		runTrackerDiagnostic(true);
	});

	$("#hub_btn_test_real").on("click", function() {
		var customPath = $("#hub_test_url").val().trim();
		var baseSiteUrl = "<?php echo esc_js( site_url() ); ?>";
		var testUrl = baseSiteUrl;
		
		if (customPath) {
			if (customPath.startsWith("http://") || customPath.startsWith("https://")) {
				testUrl = customPath;
			} else {
				if (!customPath.startsWith("/")) {
					customPath = "/" + customPath;
				}
				testUrl += customPath;
			}
		} else {
			testUrl += "/";
		}
		
		testUrl += (testUrl.indexOf("?") !== -1 ? "&" : "?") + "gclid=HUB_TEST_GCLID_REAL&utm_source=google&utm_medium=cpc&utm_campaign=tracker_test_real";
		
		alert("Uma nova aba será aberta com a URL informada.\n\nVocê deverá:\n1. Aguardar a página carregar (para o Tracker agir).\n2. Voltar a esta tela.\n3. Recarregar a página (F5) para ver o resultado.");
		window.open(testUrl, "_blank");
	});

	// Run on load sem simular (apenas lê a sessão atual)
	runTrackerDiagnostic(false);
});
</script>