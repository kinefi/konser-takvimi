(function () {
	document.addEventListener('click', function (e) {
		var link = e.target.closest('a.kt-ticket-btn-link');
		if (!link || typeof ktTrackerData === 'undefined' || !ktTrackerData.ajaxUrl) {
			return;
		}

		var district = link.getAttribute('data-kt-district') || '';
		var label = link.getAttribute('data-kt-label') || '';
		if (!district) {
			return;
		}

		var formData = new FormData();
		formData.append('action', 'kt_track_ticket_click');
		formData.append('district', district);
		formData.append('label', label);

		if (navigator.sendBeacon) {
			navigator.sendBeacon(ktTrackerData.ajaxUrl, formData);
		} else {
			fetch(ktTrackerData.ajaxUrl, {
				method: 'POST',
				body: formData,
				keepalive: true
			});
		}
	});
})();
