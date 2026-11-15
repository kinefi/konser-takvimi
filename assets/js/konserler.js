(function () {
	function initSubadapGrid() {
		var gridContainer = document.getElementById('subadapConcertGrid');
		if (!gridContainer || typeof subadapKonserData === 'undefined') {
			return;
		}

		var manualEvents = subadapKonserData.events || [];
		var baseUrl = subadapKonserData.baseUrl || 'https://www.subadapcocuk.org/';
		var maxItems = parseInt(subadapKonserData.maxItems, 10) || 16;

		var monthNames = [
			'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
			'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'
		];
		var dayNames = [
			'Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'
		];

		var now = new Date();
		var upcomingEvents = manualEvents
			.filter(function (event) {
				return new Date(event.datetime) >= now;
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
			var trDate = new Date(
				eventDate.toLocaleString('en-US', { timeZone: 'Europe/Istanbul' })
			);

			var day = trDate.getDate();
			var month = monthNames[trDate.getMonth()];
			var dayName = dayNames[trDate.getDay()];
			var hours = String(trDate.getHours()).padStart(2, '0');
			var minutes = String(trDate.getMinutes()).padStart(2, '0');

			var colorClass = 'style-' + ((index % 12) + 1);

			var link = document.createElement('a');
			link.href = baseUrl + encodeURIComponent(event.slug);
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			link.className = 'concert-badge ' + colorClass;

			var datePart = document.createElement('div');
			datePart.className = 'date-part';

			var dayMonthDiv = document.createElement('div');
			dayMonthDiv.className = 'day-month';
			dayMonthDiv.textContent = day + ' ' + month;

			var dayNameDiv = document.createElement('div');
			dayNameDiv.className = 'day-name';
			dayNameDiv.textContent = dayName;

			var timeDiv = document.createElement('div');
			timeDiv.className = 'time';
			timeDiv.textContent = hours + ':' + minutes;

			datePart.append(dayMonthDiv, dayNameDiv, timeDiv);

			var cityPart = document.createElement('div');
			cityPart.className = 'city-part';

			var cityNameDiv = document.createElement('div');
			cityNameDiv.className = 'city-name';
			cityNameDiv.textContent = event.city;

			cityPart.appendChild(cityNameDiv);
			link.append(datePart, cityPart);
			gridContainer.appendChild(link);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSubadapGrid);
	} else {
		initSubadapGrid();
	}
})();
