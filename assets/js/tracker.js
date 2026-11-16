(function () {
	function sendStatEvent(eventType, district, label) {
		if (typeof ktTrackerData === 'undefined' || !ktTrackerData.ajaxUrl || !district) {
			return;
		}

		var formData = new FormData();
		formData.append('action', 'kt_track_district_event');
		formData.append('event_type', eventType);
		formData.append('district', district);
		formData.append('label', label || '');

		if (navigator.sendBeacon) {
			navigator.sendBeacon(ktTrackerData.ajaxUrl, formData);
		} else {
			fetch(ktTrackerData.ajaxUrl, {
				method: 'POST',
				body: formData,
				keepalive: true
			});
		}
	}

	function isTicketLink(link) {
		if (!link || !link.href) {
			return false;
		}

		if (link.classList.contains('kt-ticket-btn-link')) {
			return true;
		}

		var hrefLower = link.href.toLowerCase();
		if (hrefLower.indexOf('http://') !== 0 && hrefLower.indexOf('https://') !== 0) {
			return false;
		}

		var domains = Array.isArray(ktTrackerData.ticketDomains) ? ktTrackerData.ticketDomains : [];
		for (var i = 0; i < domains.length; i++) {
			if (domains[i] && hrefLower.indexOf(domains[i]) !== -1) {
				return true;
			}
		}

		var img = link.querySelector('img');
		if (img && img.src && ktTrackerData.defaultImgUrl) {
			var defaultFile = ktTrackerData.defaultImgUrl.split('/').pop().replace(/(-\d+x\d+)?\.[a-z0-9]+$/i, '').toLowerCase();
			if (defaultFile && img.src.toLowerCase().indexOf(defaultFile) !== -1) {
				return true;
			}
		}

		return false;
	}

	// Sayfa önbelleğe (cache) alınmış olsa dahi ilçe sayfası görüntülenmesini AJAX ile kaydet
	if (typeof ktTrackerData !== 'undefined' && parseInt(ktTrackerData.trackView, 10) === 1 && ktTrackerData.defaultDistrict) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function () {
				sendStatEvent('views', ktTrackerData.defaultDistrict, ktTrackerData.defaultLabel);
			});
		} else {
			sendStatEvent('views', ktTrackerData.defaultDistrict, ktTrackerData.defaultLabel);
		}
	}

	document.addEventListener('click', function (e) {
		if (typeof ktTrackerData === 'undefined') {
			return;
		}

		var link = e.target.closest('a');
		if (!link || !isTicketLink(link)) {
			return;
		}

		var district = link.getAttribute('data-kt-district') || ktTrackerData.defaultDistrict || '';
		var label = link.getAttribute('data-kt-label') || ktTrackerData.defaultLabel || '';

		sendStatEvent('clicks', district, label);
	});
})();
