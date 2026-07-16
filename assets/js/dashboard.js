/* global L, Chart */
/**
 * dashboard.js — logika dashboard peta vokasi.
 * Berjalan hanya jika elemen #map ada di halaman (halaman dashboard utama).
 *
 * Sumber data: endpoint /api/* (lihat controller Api.php).
 * Aturan visual kritis:
 *   - radius marker = log10(kapasitas+1) (DASHBOARD_SPEC 2.4)
 *   - marker aproksimasi (cs='c') = dashed + opacity 0.5 (2.3)
 *   - warna = ownership
 */
(function () {
	'use strict';

	var mapEl = document.getElementById('map');
	if (!mapEl || typeof L === 'undefined') { return; }

	var API = window.APP.apiBase;

	// Warna ownership
	var COLOR = { P: '#4e73df', N: '#f6c23e' }; // Pemerintah / Non Pemerintah

	// -----------------------------------------------------------------
	// State filter (tersinkron ke URL)
	// -----------------------------------------------------------------
	var state = {
		q: '', ownership: '', status_legalitas: '', pulau: '', provinsi: '',
		kota_slug: '', kapasitas_min: '', kapasitas_max: '',
		sektor_slug: [], jabatan_slug: [], only_original: false
	};

	// -----------------------------------------------------------------
	// Peta
	// -----------------------------------------------------------------
	var map = L.map('map').setView(window.APP.mapCenter, window.APP.mapZoom);
	L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		maxZoom: 19,
		attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
	}).addTo(map);

	var cluster = L.markerClusterGroup({ chunkedLoading: true, maxClusterRadius: 50 });
	map.addLayer(cluster);

	// Legend
	var legend = L.control({ position: 'bottomright' });
	legend.onAdd = function () {
		var div = L.DomUtil.create('div', 'map-legend');
		div.innerHTML =
			'<div><span class="legend-dot" style="background:' + COLOR.P + '"></span>Pemerintah</div>' +
			'<div><span class="legend-dot" style="background:' + COLOR.N + '"></span>Non Pemerintah</div>' +
			'<div><span class="legend-dot approx"></span>Lokasi perkiraan</div>' +
			'<div class="text-muted mt-1" style="font-size:.7rem">ukuran = kapasitas (log)</div>';
		return div;
	};
	legend.addTo(map);

	function radiusFor(k) {
		return Math.max(4, Math.log10((k || 0) + 1) * 4);
	}

	function buildMarker(pt) {
		var isApprox = pt.cs === 'c';
		var m = L.circleMarker([pt.lat, pt.lng], {
			radius: radiusFor(pt.k),
			color: isApprox ? '#888' : COLOR[pt.o] || COLOR.N,
			weight: isApprox ? 2 : 1,
			dashArray: isApprox ? '3,3' : null,
			fillColor: COLOR[pt.o] || COLOR.N,
			fillOpacity: isApprox ? 0.25 : 0.75,
			opacity: isApprox ? 0.6 : 1
		});
		m.bindPopup('<strong>' + esc(pt.n) + '</strong><br><small class="text-muted">memuat…</small>');
		m.on('click', function () { loadPopup(m, pt.id); });
		return m;
	}

	function loadPopup(marker, id) {
		fetch(API + '/lembaga/' + id).then(function (r) { return r.json(); }).then(function (d) {
			var L0 = d.lembaga || {};
			var sektorSet = {}, jabatanList = [];
			(d.sektor || []).forEach(function (s) {
				sektorSet[s.sektor] = true;
				jabatanList.push(s.jabatan);
			});
			var approx = L0.coord_source !== 'original';
			var html = '<div style="min-width:220px">' +
				'<strong>' + esc(L0.nama) + '</strong>' +
				(approx ? ' <span class="badge badge-warning">Lokasi perkiraan</span>' : '') +
				'<br><small>' + esc(L0.kota || '') + ', ' + esc(L0.provinsi || '') + '</small>' +
				'<hr class="my-1">' +
				'<div><b>Ownership:</b> ' + esc(L0.ownership || '-') + '</div>' +
				'<div><b>Kapasitas:</b> ' + (L0.kapasitas == null ? '—' : numfmt(L0.kapasitas)) + '</div>' +
				'<div class="mt-1">' + statusBadge('Legalitas', L0.status_legalitas) +
					' ' + statusBadge('Fasilitas', L0.status_fasilitas) +
					' ' + statusBadge('Program', L0.status_program) + '</div>' +
				'<div class="mt-1"><b>Sektor:</b> ' + Object.keys(sektorSet).map(esc).join(', ') + '</div>' +
				'<div class="mt-1"><b>Jabatan (' + jabatanList.length + '):</b> <small>' +
					jabatanList.slice(0, 8).map(esc).join(' · ') + (jabatanList.length > 8 ? ' …' : '') + '</small></div>' +
				(d.duplikat && d.duplikat.length ? '<div class="mt-1"><span class="badge badge-secondary">' + d.duplikat.length + ' duplikat</span></div>' : '') +
				'</div>';
			marker.setPopupContent(html);
		}).catch(function () {
			marker.setPopupContent('<span class="text-danger">Gagal memuat detail.</span>');
		});
	}

	function statusBadge(label, v) {
		var cls = { accepted: 'success', rejected: 'danger', pending: 'warning', revised: 'info', not_submitted: 'secondary' }[v] || 'secondary';
		return '<span class="badge badge-' + cls + '">' + label + ': ' + (v || 'n/a') + '</span>';
	}

	// -----------------------------------------------------------------
	// Charts (Chart.js v2)
	// -----------------------------------------------------------------
	var charts = {};
	Chart.defaults.global.defaultFontSize = 10;

	function barChart(id, horizontal, color) {
		var ctx = document.getElementById(id);
		if (!ctx) return null;
		return new Chart(ctx.getContext('2d'), {
			type: horizontal ? 'horizontalBar' : 'bar',
			data: { labels: [], datasets: [{ data: [], backgroundColor: color || '#4e73df' }] },
			options: {
				maintainAspectRatio: false, legend: { display: false },
				scales: { xAxes: [{ ticks: { beginAtZero: true } }], yAxes: [{ ticks: { autoSkip: false } }] },
				tooltips: { intersect: false }
			}
		});
	}

	function initCharts() {
		charts.sektor = barChart('chartSektor', true, '#4e73df');
		charts.jabatan = barChart('chartJabatan', true, '#1cc88a');
		charts.provinsi = barChart('chartProvinsi', true, '#36b9cc');
		charts.ownership = new Chart(document.getElementById('chartOwnership').getContext('2d'), {
			type: 'doughnut',
			data: { labels: ['Pemerintah', 'Non Pemerintah'], datasets: [{ data: [0, 0], backgroundColor: [COLOR.P, COLOR.N] }] },
			options: { maintainAspectRatio: false, legend: { position: 'bottom' } }
		});
		// Funnel verifikasi = stacked horizontalBar 3 tahap
		charts.funnel = new Chart(document.getElementById('chartFunnel').getContext('2d'), {
			type: 'horizontalBar',
			data: {
				labels: ['Legalitas', 'Fasilitas', 'Program'],
				datasets: [
					{ label: 'Accepted', backgroundColor: '#1cc88a', data: [0, 0, 0] },
					{ label: 'Rejected', backgroundColor: '#e74a3b', data: [0, 0, 0] },
					{ label: 'Pending', backgroundColor: '#f6c23e', data: [0, 0, 0] },
					{ label: 'Revised', backgroundColor: '#36b9cc', data: [0, 0, 0] },
					{ label: 'Not submitted', backgroundColor: '#b7b9cc', data: [0, 0, 0] }
				]
			},
			options: {
				maintainAspectRatio: false,
				legend: { position: 'bottom', labels: { boxWidth: 10 } },
				scales: { xAxes: [{ stacked: true }], yAxes: [{ stacked: true }] }
			}
		});
	}

	function updateChart(chart, labels, data) {
		if (!chart) return;
		chart.data.labels = labels;
		chart.data.datasets[0].data = data;
		chart.update();
	}

	function renderStats(s) {
		updateChart(charts.sektor, pluck(s.top_sektor, 'label'), pluck(s.top_sektor, 'value'));
		updateChart(charts.jabatan, pluck(s.top_jabatan, 'label'), pluck(s.top_jabatan, 'value'));
		updateChart(charts.provinsi, pluck(s.top_provinsi, 'label'), pluck(s.top_provinsi, 'value'));
		charts.ownership.data.datasets[0].data = [s.ownership['Pemerintah'] || 0, s.ownership['Non Pemerintah'] || 0];
		charts.ownership.update();

		var f = s.verifikasi;
		var order = ['legalitas', 'fasilitas', 'program'];
		var keys = ['accepted', 'rejected', 'pending', 'revised', 'not_submitted'];
		keys.forEach(function (key, di) {
			charts.funnel.data.datasets[di].data = order.map(function (t) { return (f[t] && f[t][key]) || 0; });
		});
		charts.funnel.update();
	}

	// -----------------------------------------------------------------
	// Muat data (points + stats) sesuai filter
	// -----------------------------------------------------------------
	var loadingEl = document.getElementById('mapLoading');
	var resultEl = document.getElementById('resultCount');

	function queryString() {
		var qs = [];
		Object.keys(state).forEach(function (k) {
			var v = state[k];
			if (Array.isArray(v)) {
				if (v.length) qs.push(k + '=' + encodeURIComponent(v.join(',')));
			} else if (k === 'only_original') {
				if (v) qs.push('only_original=1');
			} else if (v !== '' && v != null) {
				qs.push(k + '=' + encodeURIComponent(v));
			}
		});
		return qs.join('&');
	}

	function refresh() {
		var qs = queryString();
		syncUrl(qs);
		if (loadingEl) loadingEl.style.display = 'flex';

		fetch(API + '/points' + (qs ? '?' + qs : '')).then(function (r) { return r.json(); }).then(function (pts) {
			cluster.clearLayers();
			var markers = [];
			pts.forEach(function (p) { markers.push(buildMarker(p)); });
			cluster.addLayers(markers);
			if (resultEl) resultEl.textContent = numfmt(pts.length);
			if (loadingEl) loadingEl.style.display = 'none';
		}).catch(function () { if (loadingEl) loadingEl.style.display = 'none'; });

		fetch(API + '/stats' + (qs ? '?' + qs : '')).then(function (r) { return r.json(); }).then(renderStats);
	}

	var refreshDebounced = debounce(refresh, 300);

	// -----------------------------------------------------------------
	// Isi filter dari /api/refs
	// -----------------------------------------------------------------
	var allKota = [];
	var allJabatan = [];

	function initFilters() {
		fetch(API + '/refs').then(function (r) { return r.json(); }).then(function (refs) {
			// Pulau
			fillSelect('fPulau', refs.pulau, 'nama', 'nama');
			// Provinsi (pakai kode BPS sbg value)
			fillSelect('fProvinsi', refs.provinsi, 'kode', 'nama', 'jumlah_lembaga');
			// Kota
			allKota = refs.kota || [];
			renderKota('');
			// Sektor checkbox
			renderChecklist('fSektor', refs.sektor, 'slug', 'nama', 'jumlah_lembaga', 'sektor_slug');
			// Jabatan (searchable checklist)
			allJabatan = refs.jabatan || [];
			renderJabatan('');

			bindEvents();
			applyStateToInputs();
			refresh();
		});
	}

	function fillSelect(id, list, valKey, labelKey, countKey) {
		var el = document.getElementById(id);
		(list || []).forEach(function (o) {
			var opt = document.createElement('option');
			opt.value = o[valKey];
			opt.textContent = o[labelKey] + (countKey && o[countKey] != null ? ' (' + o[countKey] + ')' : '');
			el.appendChild(opt);
		});
	}

	function renderKota(term) {
		var el = document.getElementById('fKota');
		var cur = state.kota_slug;
		el.innerHTML = '<option value="">Semua</option>';
		var t = term.toLowerCase();
		allKota.filter(function (k) { return !t || k.nama.toLowerCase().indexOf(t) >= 0; })
			.slice(0, 300)
			.forEach(function (k) {
				var opt = document.createElement('option');
				opt.value = k.slug; opt.textContent = k.nama;
				if (k.slug === cur) opt.selected = true;
				el.appendChild(opt);
			});
	}

	function renderChecklist(id, list, valKey, labelKey, countKey, stateKey) {
		var el = document.getElementById(id);
		el.innerHTML = '';
		(list || []).forEach(function (o) {
			var wrap = document.createElement('div');
			wrap.className = 'form-check';
			var checked = state[stateKey].indexOf(o[valKey]) >= 0 ? 'checked' : '';
			wrap.innerHTML = '<input class="form-check-input" type="checkbox" value="' + o[valKey] +
				'" id="' + id + '_' + o[valKey] + '" ' + checked + '>' +
				'<label class="form-check-label" for="' + id + '_' + o[valKey] + '">' +
				esc(o[labelKey]) + (countKey && o[countKey] != null ? ' <span class="text-muted">(' + o[countKey] + ')</span>' : '') + '</label>';
			wrap.querySelector('input').addEventListener('change', function () {
				toggleArray(stateKey, o[valKey], this.checked);
				refreshDebounced();
			});
			el.appendChild(wrap);
		});
	}

	function renderJabatan(term) {
		var el = document.getElementById('fJabatan');
		el.innerHTML = '';
		var t = term.toLowerCase();
		allJabatan.filter(function (j) { return !t || j.nama.toLowerCase().indexOf(t) >= 0; })
			.slice(0, 200)
			.forEach(function (j) {
				var wrap = document.createElement('div');
				wrap.className = 'form-check';
				var checked = state.jabatan_slug.indexOf(j.slug) >= 0 ? 'checked' : '';
				wrap.innerHTML = '<input class="form-check-input" type="checkbox" value="' + j.slug +
					'" id="jab_' + j.slug + '" ' + checked + '>' +
					'<label class="form-check-label" for="jab_' + j.slug + '">' + esc(j.nama) +
					' <span class="text-muted">(' + (j.jumlah_lembaga || 0) + ')</span></label>';
				wrap.querySelector('input').addEventListener('change', function () {
					toggleArray('jabatan_slug', j.slug, this.checked);
					refreshDebounced();
				});
				el.appendChild(wrap);
			});
	}

	// -----------------------------------------------------------------
	// Binding input filter
	// -----------------------------------------------------------------
	function bindEvents() {
		bindInput('fQ', 'q');
		bindInput('fOwnership', 'ownership');
		bindInput('fLegalitas', 'status_legalitas');
		bindInput('fPulau', 'pulau');
		bindInput('fProvinsi', 'provinsi');
		bindInput('fKota', 'kota_slug');
		bindInput('fKapMin', 'kapasitas_min');
		bindInput('fKapMax', 'kapasitas_max');

		document.getElementById('fOnlyOriginal').addEventListener('change', function () {
			state.only_original = this.checked; refreshDebounced();
		});
		document.getElementById('fKotaSearch').addEventListener('input', debounce(function () {
			renderKota(this.value);
		}, 200));
		document.getElementById('fJabatanSearch').addEventListener('input', debounce(function () {
			renderJabatan(this.value);
		}, 200));
		document.getElementById('btnReset').addEventListener('click', resetFilters);
	}

	function bindInput(id, key) {
		var el = document.getElementById(id);
		var ev = (el.tagName === 'SELECT') ? 'change' : 'input';
		el.addEventListener(ev, function () { state[key] = this.value; refreshDebounced(); });
	}

	function resetFilters() {
		state = {
			q: '', ownership: '', status_legalitas: '', pulau: '', provinsi: '',
			kota_slug: '', kapasitas_min: '', kapasitas_max: '',
			sektor_slug: [], jabatan_slug: [], only_original: false
		};
		applyStateToInputs();
		renderKota('');
		document.querySelectorAll('#fSektor input, #fJabatan input').forEach(function (c) { c.checked = false; });
		refresh();
	}

	function applyStateToInputs() {
		setVal('fQ', state.q); setVal('fOwnership', state.ownership);
		setVal('fLegalitas', state.status_legalitas); setVal('fPulau', state.pulau);
		setVal('fProvinsi', state.provinsi); setVal('fKota', state.kota_slug);
		setVal('fKapMin', state.kapasitas_min); setVal('fKapMax', state.kapasitas_max);
		document.getElementById('fOnlyOriginal').checked = !!state.only_original;
	}

	// -----------------------------------------------------------------
	// URL state (shareable link)
	// -----------------------------------------------------------------
	function syncUrl(qs) {
		var url = window.location.pathname + (qs ? '?' + qs : '');
		window.history.replaceState(null, '', url);
	}

	function loadStateFromUrl() {
		var p = new URLSearchParams(window.location.search);
		p.forEach(function (v, k) {
			if (!(k in state)) return;
			if (Array.isArray(state[k])) state[k] = v ? v.split(',') : [];
			else if (k === 'only_original') state.only_original = (v === '1');
			else state[k] = v;
		});
	}

	// -----------------------------------------------------------------
	// Util
	// -----------------------------------------------------------------
	function toggleArray(key, val, on) {
		var i = state[key].indexOf(val);
		if (on && i < 0) state[key].push(val);
		if (!on && i >= 0) state[key].splice(i, 1);
	}
	function setVal(id, v) { var el = document.getElementById(id); if (el) el.value = v; }
	function pluck(arr, key) { return (arr || []).map(function (o) { return o[key]; }); }
	function numfmt(n) { return (n || 0).toLocaleString('id-ID'); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function debounce(fn, ms) {
		var t; return function () { var ctx = this, a = arguments; clearTimeout(t); t = setTimeout(function () { fn.apply(ctx, a); }, ms); };
	}

	// -----------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------
	loadStateFromUrl();
	initCharts();
	initFilters();
})();
