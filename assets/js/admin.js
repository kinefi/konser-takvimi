document.addEventListener('DOMContentLoaded', function () {
	var tableBody = document.querySelector('#subadap-concerts-table tbody');
	var addBtn = document.getElementById('add-concert-row');

	if (tableBody && addBtn) {
		function slugifyTurkish(text) {
			return text
				.toString()
				.trim()
				.replace(/İ/g, 'i')
				.replace(/I/g, 'i')
				.replace(/Ğ/g, 'g')
				.replace(/Ü/g, 'u')
				.replace(/Ş/g, 's')
				.replace(/Ö/g, 'o')
				.replace(/Ç/g, 'c')
				.toLowerCase()
				.replace(/ğ/g, 'g')
				.replace(/ü/g, 'u')
				.replace(/ş/g, 's')
				.replace(/ı/g, 'i')
				.replace(/ö/g, 'o')
				.replace(/ç/g, 'c')
				.replace(/[^a-z0-9\s-]/g, '')
				.replace(/\s+/g, '-')
				.replace(/-+/g, '-')
				.replace(/^-+|-+$/g, '');
		}

		function updateRowSlug(row) {
			var dateInput = row.querySelector('input[name="concert_date[]"]');
			var cityInput = row.querySelector('input[name="concert_city[]"]');
			var slugInput = row.querySelector('input[name="concert_slug[]"]');

			if (!dateInput || !cityInput || !slugInput) {
				return;
			}

			if (slugInput.dataset.auto === '1') {
				var dateVal = dateInput.value.trim();
				var citySlug = slugifyTurkish(cityInput.value);

				if (dateVal && citySlug) {
					slugInput.value = dateVal + '-' + citySlug;
				} else if (citySlug) {
					slugInput.value = citySlug;
				} else {
					slugInput.value = '';
				}
			}
		}

		tableBody.addEventListener('input', function (e) {
			var target = e.target;
			var row = target.closest('tr');
			if (!row) {
				return;
			}

			if (target.name === 'concert_slug[]') {
				target.dataset.auto = target.value.trim() === '' ? '1' : '0';
				return;
			}

			if (target.name === 'concert_date[]' || target.name === 'concert_city[]') {
				updateRowSlug(row);
			}
		});

		addBtn.addEventListener('click', function () {
			var row = document.createElement('tr');
			row.innerHTML =
				'<td><input type="date" name="concert_date[]" required style="width: 100%;"></td>' +
				'<td><input type="time" name="concert_time[]" value="15:00" required style="width: 100%;"></td>' +
				'<td><input type="text" name="concert_city[]" placeholder="Örn: Bursa" required style="width: 100%;"></td>' +
				'<td><input type="text" name="concert_slug[]" data-auto="1" placeholder="Otomatik: YYYY-AA-GG-ilce" style="width: 100%;"></td>' +
				'<td><button type="button" class="button remove-row" style="color: #b32d2e;">Sil</button></td>';
			tableBody.appendChild(row);
		});

		tableBody.addEventListener('click', function (e) {
			if (e.target.classList.contains('remove-row')) {
				e.target.closest('tr').remove();
			}
		});
	}

	// Arşivlenen konserler için sayfalama (sayfa başına 10 kayıt)
	var archiveRows = Array.from(document.querySelectorAll('#subadap-archive-table tbody tr.archive-row'));
	var paginationContainer = document.getElementById('subadap-archive-pagination');

	if (archiveRows.length > 0 && paginationContainer) {
		var perPage = 10;
		var currentPage = 1;
		var totalPages = Math.ceil(archiveRows.length / perPage);

		function renderArchivePage(page) {
			currentPage = page;
			var start = (page - 1) * perPage;
			var end = start + perPage;

			archiveRows.forEach(function (row, idx) {
				row.style.display = (idx >= start && idx < end) ? '' : 'none';
			});

			paginationContainer.innerHTML = '';
			if (totalPages <= 1) {
				return;
			}

			var info = document.createElement('span');
			info.style.color = '#50575e';
			info.textContent = 'Sayfa ' + currentPage + ' / ' + totalPages + ' (Toplam ' + archiveRows.length + ' arşiv kaydı)';

			var controls = document.createElement('div');

			var prevBtn = document.createElement('button');
			prevBtn.type = 'button';
			prevBtn.className = 'button';
			prevBtn.textContent = '« Önceki';
			prevBtn.disabled = currentPage === 1;
			prevBtn.style.marginRight = '6px';
			prevBtn.addEventListener('click', function () {
				if (currentPage > 1) renderArchivePage(currentPage - 1);
			});

			var nextBtn = document.createElement('button');
			nextBtn.type = 'button';
			nextBtn.className = 'button';
			nextBtn.textContent = 'Sonraki »';
			nextBtn.disabled = currentPage === totalPages;
			nextBtn.addEventListener('click', function () {
				if (currentPage < totalPages) renderArchivePage(currentPage + 1);
			});

			controls.append(prevBtn, nextBtn);
			paginationContainer.append(info, controls);
		}

		renderArchivePage(1);
	}

	// Düğme Biçimleri & Renkler Sekmesi - Canlı Önizleme
	var previewBox = document.getElementById('subadap-live-preview-box');
	if (previewBox) {
		var radiusInput = document.getElementById('border_radius');
		var heightInput = document.getElementById('badge_height');
		var minWInput = document.getElementById('card_min_w');
		var gapInput = document.getElementById('grid_gap');
		var dateTextInput = document.getElementById('date_text');
		var cityTextInput = document.getElementById('city_text');
		var previewBadges = previewBox.querySelectorAll('.preview-badge');

		function hexToPastel(hex) {
			hex = hex.replace('#', '');
			if (hex.length !== 6) return '#f3f4f6';
			var r = parseInt(hex.substring(0, 2), 16);
			var g = parseInt(hex.substring(2, 4), 16);
			var b = parseInt(hex.substring(4, 6), 16);
			var pr = Math.round(r + (255 - r) * 0.82);
			var pg = Math.round(g + (255 - g) * 0.82);
			var pb = Math.round(b + (255 - b) * 0.82);
			return '#' + [pr, pg, pb].map(function (x) {
				var h = x.toString(16);
				return h.length === 1 ? '0' + h : h;
			}).join('');
		}

		function updatePreview() {
			var rVal = radiusInput.value;
			var hVal = heightInput.value;
			var wVal = minWInput.value;
			var gVal = gapInput.value;

			document.getElementById('border_radius_val').textContent = rVal + ' px';
			document.getElementById('badge_height_val').textContent = hVal + ' px';
			document.getElementById('card_min_w_val').textContent = wVal + ' px';
			document.getElementById('grid_gap_val').textContent = gVal + ' px';

			previewBox.style.gap = gVal + 'px';

			previewBadges.forEach(function (badge, idx) {
				badge.style.borderRadius = rVal + 'px';
				badge.style.height = hVal + 'px';
				badge.style.width = wVal + 'px';

				var dPart = badge.querySelector('.preview-date');
				var cPart = badge.querySelector('.preview-city');
				dPart.style.color = dateTextInput.value;
				cPart.style.color = cityTextInput.value;

				var card = document.querySelector('.palette-item-card[data-palette-idx="' + idx + '"]');
				if (card) {
					dPart.style.backgroundColor = card.querySelector('.palette-date-input').value;
					cPart.style.backgroundColor = card.querySelector('.palette-city-input').value;
				}
			});
		}

		[radiusInput, heightInput, minWInput, gapInput, dateTextInput, cityTextInput].forEach(function (el) {
			if (el) el.addEventListener('input', updatePreview);
		});

		document.querySelectorAll('.palette-item-card').forEach(function (card) {
			var dInput = card.querySelector('.palette-date-input');
			var cInput = card.querySelector('.palette-city-input');

			dInput.addEventListener('input', function () {
				cInput.value = hexToPastel(dInput.value);
				updatePreview();
			});

			cInput.addEventListener('input', updatePreview);
		});
	}
});
