document.addEventListener('DOMContentLoaded', function () {
	var tableBody = document.querySelector('#kt-concerts-table tbody');
	var addBtn = document.getElementById('add-concert-row');
	var postsDatalist = document.getElementById('kt-existing-posts-list');

	var existingSlugsMap = {};
	if (postsDatalist) {
		Array.from(postsDatalist.options).forEach(function (opt) {
			if (opt.value) {
				existingSlugsMap[opt.value.trim().toLowerCase()] = opt.textContent.trim();
			}
		});
	}

	function checkSlugExistence(row) {
		var slugInput = row.querySelector('input[name="concert_slug[]"]');
		var statusSpan = row.querySelector('.kt-slug-status');
		if (!slugInput || !statusSpan) {
			return;
		}

		var val = slugInput.value.trim().toLowerCase();
		slugInput.classList.remove('is-valid-page', 'is-missing-page');
		statusSpan.classList.remove('is-valid-page', 'is-missing-page');

		if (!val) {
			statusSpan.textContent = '';
			return;
		}

		if (Object.prototype.hasOwnProperty.call(existingSlugsMap, val)) {
			slugInput.classList.add('is-valid-page');
			statusSpan.classList.add('is-valid-page');
			statusSpan.textContent = '✓ Mevcut: ' + existingSlugsMap[val];
		} else {
			slugInput.classList.add('is-missing-page');
			statusSpan.classList.add('is-missing-page');
			statusSpan.textContent = '✕ Bu uzantıda sayfa/yazı bulunamadı';
		}
	}

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
			var cityInput = row.querySelector('input[name="concert_city[]"]');
			var slugInput = row.querySelector('input[name="concert_slug[]"]');

			if (!cityInput || !slugInput) {
				return;
			}

			if (slugInput.dataset.auto === '1') {
				var citySlug = slugifyTurkish(cityInput.value);
				slugInput.value = citySlug ? citySlug + '-konseri' : '';
				checkSlugExistence(row);
			}
		}

		Array.from(tableBody.querySelectorAll('tr')).forEach(function (row) {
			checkSlugExistence(row);
		});

		tableBody.addEventListener('input', function (e) {
			var target = e.target;
			var row = target.closest('tr');
			if (!row) {
				return;
			}

			if (target.name === 'concert_slug[]') {
				target.dataset.auto = target.value.trim() === '' ? '1' : '0';
				checkSlugExistence(row);
				return;
			}

			if (target.name === 'concert_city[]') {
				updateRowSlug(row);
			}
		});

		addBtn.addEventListener('click', function () {
			var row = document.createElement('tr');
			row.innerHTML =
				'<td style="vertical-align: top;"><input type="date" name="concert_date[]" required style="width: 100%;"></td>' +
				'<td style="vertical-align: top;"><input type="time" name="concert_time[]" value="15:00" required style="width: 100%;"></td>' +
				'<td style="vertical-align: top;"><input type="time" name="concert_time2[]" value="" style="width: 100%;"></td>' +
				'<td style="vertical-align: top;"><input type="text" name="concert_city[]" placeholder="Örn: Urla" required style="width: 100%;"></td>' +
				'<td style="vertical-align: top;">' +
					'<input type="text" class="kt-slug-input" name="concert_slug[]" list="kt-existing-posts-list" data-auto="1" placeholder="ilce-konseri veya ara..." autocomplete="off">' +
					'<span class="kt-slug-status"></span>' +
				'</td>' +
				'<td style="vertical-align: top;"><button type="button" class="button remove-row" style="color: #b32d2e;">Sil</button></td>';
			tableBody.appendChild(row);
		});

		tableBody.addEventListener('click', function (e) {
			if (e.target.classList.contains('remove-row')) {
				e.target.closest('tr').remove();
			}
		});
	}

	var archiveRows = Array.from(document.querySelectorAll('#kt-archive-table tbody tr.archive-row'));
	var paginationContainer = document.getElementById('kt-archive-pagination');

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

	var chartCanvas = document.getElementById('kt-stats-canvas');
	var chartJsonEl = document.getElementById('kt-chart-json-data');
	var chartSelect = document.getElementById('kt-chart-district-select');

	if (chartCanvas && chartJsonEl) {
		var chartData = JSON.parse(chartJsonEl.textContent || '{}');

		function drawStatsChart(districtKey) {
			var seriesObj = (chartData.series && chartData.series[districtKey]) ? chartData.series[districtKey] : chartData.series.all;
			if (!seriesObj) {
				return;
			}

			var labels = chartData.labels || [];
			var visitors = seriesObj.visitors || [];
			var clicks = seriesObj.clicks || [];

			var dpr = window.devicePixelRatio || 1;
			var rect = chartCanvas.getBoundingClientRect();
			var width = Math.max(600, Math.floor(rect.width || 980));
			var height = 250;

			chartCanvas.width = width * dpr;
			chartCanvas.height = height * dpr;

			var ctx = chartCanvas.getContext('2d');
			ctx.scale(dpr, dpr);
			ctx.clearRect(0, 0, width, height);

			var padLeft = 42;
			var padRight = 18;
			var padTop = 22;
			var padBottom = 34;
			var plotW = width - padLeft - padRight;
			var plotH = height - padTop - padBottom;

			var maxVal = 5;
			for (var i = 0; i < labels.length; i++) {
				if (visitors[i] > maxVal) maxVal = visitors[i];
				if (clicks[i] > maxVal) maxVal = clicks[i];
			}
			maxVal = Math.ceil(maxVal * 1.15);

			ctx.font = '11px system-ui, -apple-system, sans-serif';
			ctx.textAlign = 'right';
			ctx.textBaseline = 'middle';

			var gridSteps = 4;
			for (var s = 0; s <= gridSteps; s++) {
				var val = Math.round((maxVal / gridSteps) * s);
				var y = padTop + plotH - (plotH * (s / gridSteps));

				ctx.strokeStyle = s === 0 ? '#cbd5e1' : '#f1f5f9';
				ctx.lineWidth = 1;
				ctx.beginPath();
				ctx.moveTo(padLeft, y);
				ctx.lineTo(width - padRight, y);
				ctx.stroke();

				ctx.fillStyle = '#64748b';
				ctx.fillText(String(val), padLeft - 8, y);
			}

			var count = labels.length;
			var slotW = plotW / count;
			var barW = Math.max(6, Math.min(18, (slotW - 10) / 2));

			for (var j = 0; j < count; j++) {
				var centerX = padLeft + slotW * j + slotW / 2;

				var visH = (visitors[j] / maxVal) * plotH;
				var visX = centerX - barW - 1.5;
				var visY = padTop + plotH - visH;
				ctx.fillStyle = '#2271b1';
				ctx.fillRect(visX, visY, barW, visH);

				if (visitors[j] > 0) {
					ctx.fillStyle = '#1e3a8a';
					ctx.textAlign = 'center';
					ctx.textBaseline = 'bottom';
					ctx.fillText(String(visitors[j]), visX + barW / 2, visY - 3);
				}

				var clkH = (clicks[j] / maxVal) * plotH;
				var clkX = centerX + 1.5;
				var clkY = padTop + plotH - clkH;
				ctx.fillStyle = '#16a34a';
				ctx.fillRect(clkX, clkY, barW, clkH);

				if (clicks[j] > 0) {
					ctx.fillStyle = '#14532d';
					ctx.textAlign = 'center';
					ctx.textBaseline = 'bottom';
					ctx.fillText(String(clicks[j]), clkX + barW / 2, clkY - 3);
				}

				ctx.fillStyle = '#475569';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'top';
				ctx.fillText(labels[j], centerX, padTop + plotH + 8);
			}
		}

		drawStatsChart('all');

		if (chartSelect) {
			chartSelect.addEventListener('change', function () {
				drawStatsChart(chartSelect.value);
			});
		}

		window.addEventListener('resize', function () {
			drawStatsChart(chartSelect ? chartSelect.value : 'all');
		});
	}

	var previewBox = document.getElementById('kt-live-preview-box');
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

	var selectImgBtn = document.getElementById('kt-select-ticket-img');
	var ticketImgInput = document.getElementById('ticket_btn_img');
	var ticketImgPreview = document.getElementById('kt-ticket-img-preview');

	if (selectImgBtn && ticketImgInput && typeof wp !== 'undefined' && wp.media) {
		var mediaFrame;
		selectImgBtn.addEventListener('click', function (e) {
			e.preventDefault();
			if (mediaFrame) {
				mediaFrame.open();
				return;
			}
			mediaFrame = wp.media({
				title: 'Varsayılan Bilet Düğmesi Görselini Seçin',
				button: { text: 'Bu Görseli Kullan' },
				multiple: false
			});
			mediaFrame.on('select', function () {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				var chosenUrl = (attachment.sizes && attachment.sizes.large) ? attachment.sizes.large.url : attachment.url;
				ticketImgInput.value = chosenUrl;
				if (ticketImgPreview) {
					ticketImgPreview.innerHTML = '<img src="' + chosenUrl + '" alt="Bilet Düğmesi Önizleme" style="max-width: 360px; height: auto; border: 1px solid #dcdcde; border-radius: 4px; padding: 4px; background: #f8fafc;">';
				}
			});
			mediaFrame.open();
		});
	}
});
