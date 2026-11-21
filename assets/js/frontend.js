(function () {
	function getIstanbulYMD(dateObj) {
		var parts = new Intl.DateTimeFormat('en-CA', {
			timeZone: 'Europe/Istanbul',
			year: 'numeric',
			month: '2-digit',
			day: '2-digit'
		}).formatToParts(dateObj);

		var map = {};
		parts.forEach(function (p) {
			map[p.type] = p.value;
		});
		return map.year + '-' + map.month + '-' + map.day;
	}

	function escapeIcsText(str) {
		return String(str || '')
			.replace(/\\/g, '\\\\')
			.replace(/;/g, '\\;')
			.replace(/,/g, '\\,')
			.replace(/\r?\n/g, '\\n');
	}

	function downloadIcsFile(btn) {
		var title = btn.getAttribute('data-title') || 'Konser';
		var location = btn.getAttribute('data-location') || '';
		var desc = btn.getAttribute('data-desc') || '';
		var startUtc = btn.getAttribute('data-start') || '';
		var endUtc = btn.getAttribute('data-end') || '';
		var filename = btn.getAttribute('data-filename') || 'konser.ics';

		if (!startUtc || !endUtc) {
			return;
		}

		var nowStamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
		var uid = nowStamp + '-' + Math.random().toString(36).slice(2, 10) + '@konsertakvimi';

		var icsLines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Subadap Cocuk//Konser Takvimi//TR',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:' + uid,
			'DTSTAMP:' + nowStamp,
			'DTSTART:' + startUtc,
			'DTEND:' + endUtc,
			'SUMMARY:' + escapeIcsText(title),
			'LOCATION:' + escapeIcsText(location),
			'DESCRIPTION:' + escapeIcsText(desc),
			'END:VEVENT',
			'END:VCALENDAR'
		];

		var blob = new Blob([icsLines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		setTimeout(function () {
			URL.revokeObjectURL(url);
		}, 1000);
	}

	function initAddToCalendarButtons() {
		document.addEventListener('click', function (e) {
			var icsBtn = e.target.closest('.kt-cal-ics-btn');
			if (icsBtn) {
				e.preventDefault();
				downloadIcsFile(icsBtn);
				var parentDetails = icsBtn.closest('details.kt-cal-dropdown');
				if (parentDetails) {
					parentDetails.removeAttribute('open');
				}
				return;
			}

			var openDropdowns = document.querySelectorAll('details.kt-cal-dropdown[open]');
			openDropdowns.forEach(function (det) {
				if (!det.contains(e.target)) {
					det.removeAttribute('open');
				}
			});
		});
	}

	function initConcertGrid() {
		var gridContainer = document.getElementById('ktConcertGrid');
		if (!gridContainer || typeof ktConcertData === 'undefined') {
			return;
		}

		var manualEvents = ktConcertData.events || [];
		var baseUrl = ktConcertData.baseUrl || '/';
		var maxItems = parseInt(ktConcertData.maxItems, 10) || 16;

		var monthNames = [
			'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
			'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
		];
		var dayNames = [
			'Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'
		];

		var now = new Date();
		var todayYMD = getIstanbulYMD(now);
		var tomorrowDate = new Date(now.getTime() + 24 * 60 * 60 * 1000);
		var tomorrowYMD = getIstanbulYMD(tomorrowDate);

		var upcomingEvents = manualEvents
			.filter(function (event) {
				var checkDate = event.endDatetime ? event.endDatetime : event.datetime;
				return new Date(checkDate) >= now;
			})
			.slice(0, maxItems);

		gridContainer.textContent = '';

		if (upcomingEvents.length === 0) {
			var emptyBox = document.createElement('div');
			emptyBox.className = 'empty-concerts';
			emptyBox.textContent = 'Yakın tarihte planlanmış konser bulunmamaktadır.';
			gridContainer.appendChild(emptyBox);
			return;
		}

		upcomingEvents.forEach(function (event, index) {
			var eventDate = new Date(event.datetime);
			var eventYMD = getIstanbulYMD(eventDate);
			var trDate = new Date(
				eventDate.toLocaleString('en-US', { timeZone: 'Europe/Istanbul' })
			);

			var day = trDate.getDate();
			var month = monthNames[trDate.getMonth()];
			var dayName = dayNames[trDate.getDay()];
			var hours = String(trDate.getHours()).padStart(2, '0');
			var minutes = String(trDate.getMinutes()).padStart(2, '0');

			var formattedTime1 = hours + '.' + minutes;
			var timeText = formattedTime1;
			var hasTwoSessions = false;

			if (event.time2 && /^\d{2}:\d{2}$/.test(event.time2)) {
				var formattedTime2 = event.time2.replace(':', '.');
				timeText = formattedTime1 + ' ve ' + formattedTime2;
				hasTwoSessions = true;
			}

			var dayBadgeType = '';
			var dayBadgeText = '';
			if (eventYMD === todayYMD) {
				dayBadgeType = 'is-today has-day-ribbon';
				dayBadgeText = 'BUGÜN';
			} else if (eventYMD === tomorrowYMD) {
				dayBadgeType = 'is-tomorrow has-day-ribbon';
				dayBadgeText = 'YARIN';
			}

			var colorClass = 'style-' + ((index % 12) + 1);

			var link = document.createElement('a');
			link.href = baseUrl + encodeURIComponent(event.slug);
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.className = 'concert-badge ' + colorClass + (dayBadgeType ? ' ' + dayBadgeType : '');

			var datePart = document.createElement('div');
			datePart.className = 'date-part';

			var dayMonthDiv = document.createElement('div');
			dayMonthDiv.className = 'day-month';
			dayMonthDiv.textContent = day + ' ' + month;

			var dayNameDiv = document.createElement('div');
			dayNameDiv.className = 'day-name';
			dayNameDiv.textContent = dayName;

			var timeDiv = document.createElement('div');
			timeDiv.className = hasTwoSessions ? 'time has-two-sessions' : 'time';
			timeDiv.textContent = timeText;

			datePart.append(dayMonthDiv, dayNameDiv, timeDiv);

			var cityPart = document.createElement('div');
			cityPart.className = 'city-part';

			if (dayBadgeText) {
				var ribbon = document.createElement('span');
				ribbon.className = 'kt-day-ribbon ' + (eventYMD === todayYMD ? 'is-today' : 'is-tomorrow');
				ribbon.textContent = dayBadgeText;
				cityPart.appendChild(ribbon);
			}

			var cityNameDiv = document.createElement('div');
			cityNameDiv.className = 'city-name';
			cityNameDiv.textContent = event.city;

			cityPart.appendChild(cityNameDiv);
			link.append(datePart, cityPart);
			gridContainer.appendChild(link);
		});
	}

	function boot() {
		initAddToCalendarButtons();
		initConcertGrid();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
