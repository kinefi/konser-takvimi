(function () {
	var pageStartMs = Date.now();
	var activeMs = 0;
	var lastResumeMs = document.hidden ? null : pageStartMs;
	var durationSent = false;

	function sendStatEvent(eventType, district, label, extraParams) {
		if (typeof ktTrackerData === 'undefined' || !ktTrackerData.ajaxUrl || !district) {
			return;
		}

		var formData = new FormData();
		formData.append('action', 'kt_track_district_event');
		formData.append('event_type', eventType);
		formData.append('district', district);
		formData.append('label', label || '');

		if (extraParams) {
			Object.keys(extraParams).forEach(function (k) {
				formData.append(k, extraParams[k]);
			});
		}

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

	function checkDailyUniqueVisitor(district, todayStr) {
		if (!district || !todayStr) {
			return '1';
		}
		try {
			var storageKey = 'kt_vis_' + district + '_' + todayStr;
			if (window.localStorage.getItem(storageKey) === '1') {
				return '0';
			}
			window.localStorage.setItem(storageKey, '1');
			return '1';
		} catch (err) {
			return '1';
		}
	}

	// True the first time this browser clicks a ticket link for the district today (same daily window as unique visitors).
	function checkDailyUniqueClicker(district, todayStr) {
		if (!district || !todayStr) {
			return '1';
		}
		try {
			var storageKey = 'kt_clk_' + district + '_' + todayStr;
			if (window.localStorage.getItem(storageKey) === '1') {
				return '0';
			}
			window.localStorage.setItem(storageKey, '1');
			return '1';
		} catch (err) {
			return '1';
		}
	}

	// Reads utm_source / utm_medium / utm_campaign from the current page URL (only when capturing is enabled).
	function readIncomingUtm() {
		var found = {};
		if (typeof ktTrackerData === 'undefined' || parseInt(ktTrackerData.captureUtm, 10) !== 1) {
			return found;
		}

		try {
			var params = new URLSearchParams(window.location.search);
			['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (name) {
				var value = params.get(name);
				if (value) {
					found[name] = value.slice(0, 100);
				}
			});
		} catch (err) {
			// URLSearchParams is unavailable: skip UTM capture silently.
		}

		return found;
	}

	// Adds utm_* parameters to an external ticket link. Existing utm_* values on the link are never overwritten.
	function decorateTicketLink(link) {
		var cfg = (typeof ktTrackerData !== 'undefined') ? ktTrackerData.utm : null;
		if (!cfg || parseInt(cfg.enabled, 10) !== 1 || !cfg.source || !link || !link.href) {
			return;
		}

		var url;
		try {
			url = new URL(link.href, window.location.href);
		} catch (err) {
			return;
		}

		// Only external http(s) links are tagged; internal links must keep the visitor's original traffic source.
		if ((url.protocol !== 'http:' && url.protocol !== 'https:') || url.hostname === window.location.hostname) {
			return;
		}

		var district = link.getAttribute('data-kt-district') || ktTrackerData.defaultDistrict || '';
		var values = {
			utm_source: cfg.source,
			utm_medium: cfg.medium,
			utm_campaign: cfg.campaign || district,
			utm_content: link.classList.contains('kt-ticket-btn-link') ? 'ticket-button' : 'ticket-link'
		};

		var changed = false;
		Object.keys(values).forEach(function (name) {
			if (values[name] && !url.searchParams.has(name)) {
				url.searchParams.set(name, values[name]);
				changed = true;
			}
		});

		if (changed) {
			link.href = url.toString();
		}
	}

	function decorateAllTicketLinks() {
		if (typeof ktTrackerData === 'undefined') {
			return;
		}
		Array.prototype.forEach.call(document.querySelectorAll('a[href]'), function (link) {
			if (isTicketLink(link)) {
				decorateTicketLink(link);
			}
		});
	}

	function flushActiveDuration() {
		if (durationSent || typeof ktTrackerData === 'undefined' || parseInt(ktTrackerData.trackView, 10) !== 1) {
			return;
		}
		if (!ktTrackerData.defaultDistrict) {
			return;
		}

		var now = Date.now();
		if (lastResumeMs !== null) {
			activeMs += (now - lastResumeMs);
			lastResumeMs = null;
		}

		var seconds = Math.round(activeMs / 1000);
		if (seconds >= 1) {
			durationSent = true;
			sendStatEvent('duration', ktTrackerData.defaultDistrict, ktTrackerData.defaultLabel, {
				seconds: String(seconds)
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

	if (typeof ktTrackerData !== 'undefined' && parseInt(ktTrackerData.trackView, 10) === 1 && ktTrackerData.defaultDistrict) {
		var recordViewOnce = function () {
			var isUnique = checkDailyUniqueVisitor(ktTrackerData.defaultDistrict, ktTrackerData.todayStr);
			var viewParams = readIncomingUtm();
			viewParams.is_unique = isUnique;
			sendStatEvent('views', ktTrackerData.defaultDistrict, ktTrackerData.defaultLabel, viewParams);
		};

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', recordViewOnce);
		} else {
			recordViewOnce();
		}

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				if (lastResumeMs !== null) {
					activeMs += (Date.now() - lastResumeMs);
					lastResumeMs = null;
				}
				flushActiveDuration();
			} else if (!durationSent) {
				lastResumeMs = Date.now();
			}
		});

		window.addEventListener('pagehide', flushActiveDuration);
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

		decorateTicketLink(link);
		var clickParams = readIncomingUtm();
		clickParams.is_unique = checkDailyUniqueClicker(district, ktTrackerData.todayStr);
		sendStatEvent('clicks', district, label, clickParams);
		flushActiveDuration();
	});

	// Tag ticket links on load so that "open in new tab" and copied links carry the UTM parameters too.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', decorateAllTicketLinks);
	} else {
		decorateAllTicketLinks();
	}
})();
