window.HubConsent = (function() {
	var cookieName = 'hub_consent_preferences';

	function setCookie(name, value, days) {
		var expires = "";
		if (days) {
			var date = new Date();
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = "; expires=" + date.toUTCString();
		}
		document.cookie = name + "=" + encodeURIComponent(JSON.stringify(value)) + expires + "; path=/; SameSite=Lax";
	}

	function getCookie(name) {
		var nameEQ = name + "=";
		var ca = document.cookie.split(';');
		for (var i = 0; i < ca.length; i++) {
			var c = ca[i];
			while (c.charAt(0) === ' ') c = c.substring(1, c.length);
			if (c.indexOf(nameEQ) === 0) {
				try {
					return JSON.parse(decodeURIComponent(c.substring(nameEQ.length, c.length)));
				} catch (e) {
					return null;
				}
			}
		}
		return null;
	}

	function removeCookie(name) {
		document.cookie = name + '=; Max-Age=-99999999; path=/';
	}

	var state = getCookie(cookieName);

	var consentApi = {
		getStatus: function() {
			return state ? state.status : 'unknown';
		},
		hasConsent: function() {
			return this.getStatus() === 'granted';
		},
		update: function(status) {
			state = {
				status: status,
				timestamp: new Date().getTime()
			};
			setCookie(cookieName, state, 365);
			
			// Atualiza GCM v2
			if (typeof gtag === 'function') {
				gtag('consent', 'update', {
					'ad_storage': status === 'granted' ? 'granted' : 'denied',
					'analytics_storage': status === 'granted' ? 'granted' : 'denied',
					'ad_user_data': status === 'granted' ? 'granted' : 'denied',
					'ad_personalization': status === 'granted' ? 'granted' : 'denied'
				});
			}

			// Dispara evento para módulos como o Tracker saberem que atualizou (mesmo sem reload)
			var event = new CustomEvent('hub:consent-updated', { detail: { status: status } });
			document.dispatchEvent(event);
		},
		reset: function() {
			removeCookie(cookieName);
			state = null;
			window.location.reload();
		}
	};

	document.addEventListener('DOMContentLoaded', function() {
		var banner = document.getElementById('hub-consent-banner');
		var btnAccept = document.getElementById('hub-consent-accept');
		var btnReject = document.getElementById('hub-consent-reject');

		if (banner && consentApi.getStatus() === 'unknown') {
			banner.style.display = 'block';
		}

		if (btnAccept) {
			btnAccept.addEventListener('click', function() {
				consentApi.update('granted');
				if (banner) banner.style.display = 'none';
			});
		}

		if (btnReject) {
			btnReject.addEventListener('click', function() {
				consentApi.update('denied');
				if (banner) banner.style.display = 'none';
			});
		}

		// Botões externos de reset (ex: inseridos via shortcode ou HTML em política de privacidade)
		document.addEventListener('click', function(e) {
			if (e.target && e.target.classList && e.target.classList.contains('hub-reset-consent')) {
				e.preventDefault();
				consentApi.reset();
			}
		});
	});

	// Se já houver consentimento na carga da página, re-atesta para o Google Tags disparar corretamente
	if (consentApi.hasConsent()) {
		if (typeof gtag === 'function') {
			gtag('consent', 'update', {
				'ad_storage': 'granted',
				'analytics_storage': 'granted',
				'ad_user_data': 'granted',
				'ad_personalization': 'granted'
			});
		}
	}

	return consentApi;
})();
