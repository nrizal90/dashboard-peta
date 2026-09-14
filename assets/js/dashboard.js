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

	// Palet emas — SATU SUMBER di application/config/tema.php, dikirim ke sini
	// lewat window.APP.tema (templates/footer.php). Fallback dipakai bila
	// script ini dimuat di halaman yang belum mengekspor tema.
	var TEMA = (window.APP && window.APP.tema) || {};
	var GOLD = {
		base:  TEMA.gold       || '#E8C457',
		dark:  TEMA.gold_dark  || '#C79A2E',
		deep:  TEMA.gold_deep  || '#A87F1E',
		light: TEMA.gold_light || '#F2DD8E'
	};

	// Warna ownership (emas pekat vs emas muda, tetap terbedakan di peta)
	var COLOR = { P: GOLD.deep, N: GOLD.base }; // Pemerintah / Non Pemerintah

	// -----------------------------------------------------------------
	// State filter (tersinkron ke URL)
	// -----------------------------------------------------------------
	var state = {
		q: '', ownership: '', tahapan: '', pulau: '', provinsi: '',
		kota_slug: '', kapasitas_min: '', kapasitas_max: '',
		sektor_slug: [], jabatan_slug: [], only_original: false
	};

	// -----------------------------------------------------------------
	// Deep-link dari peta choropleth di dashboard utama:
	//   ?provinsi_nama=Papua&bbox=south,west,north,east
	// provinsi_nama diresolve ke kode BPS setelah /api/refs termuat,
	// bbox dipakai langsung untuk zoom peta. Keduanya BUKAN bagian state
	// filter, jadi tidak ikut dikirim ke endpoint /api/*.
	// -----------------------------------------------------------------
	var deepLink = (function () {
		var p = new URLSearchParams(window.location.search);
		var raw = (p.get('bbox') || '').split(',').map(Number);
		var ok = raw.length === 4 && raw.every(function (n) { return isFinite(n); });
		return {
			provinsiNama: p.get('provinsi_nama') || '',
			bounds: ok ? [[raw[0], raw[1]], [raw[2], raw[3]]] : null
		};
	})();

	/** Normalisasi nama provinsi -> key kanonik (mirror Vokasi_repo::provKey). */
	function provKey(name) {
		var k = String(name == null ? '' : name).toLowerCase().replace(/[^a-z0-9]/g, '');
		var alias = {
			daerahistimewayogyakarta: 'yogyakarta',
			diyogyakarta: 'yogyakarta',
			daerahkhususibukotajakarta: 'jakartaraya',
			dkijakarta: 'jakartaraya',
			jakarta: 'jakartaraya',
			kepulauanbangkabelitung: 'bangkabelitung',
			papuabaratdaya: 'papuabarat'
		};
		return alias[k] || k;
	}

	// -----------------------------------------------------------------
	// Peta
	// -----------------------------------------------------------------
	// CATATAN PENTING (jangan diubah tanpa menguji marker):
	// Tanpa tileLayer, map.getMaxZoom() bernilai Infinity dan Leaflet.markercluster
	// gagal membangun grid cluster-nya (marker tidak muncul sama sekali), jadi
	// minZoom/maxZoom WAJIB ditetapkan eksplisit di sini. markercluster juga
	// mengharuskan level zoom bilangan bulat — zoomSnap pecahan membuat marker hilang.
	var map = L.map('map', {
		attributionControl: false,
		minZoom: 4,
		maxZoom: 18
	}).setView(window.APP.mapCenter, window.APP.mapZoom);

	// Basemap: polygon provinsi Indonesia — daratan putih di atas laut abu-abu muda
	// (gaya peta referensi), menggantikan tile OpenStreetMap supaya tidak tampil
	// seperti peta jalan/globe. Non-interaktif agar klik/hover tetap milik marker.
	var basemap = L.geoJSON(null, {
		interactive: false,
		style: function () {
			return { fillColor: '#ffffff', color: '#d5dae1', weight: 1, fillOpacity: 1 };
		}
	}).addTo(map);

	// Zoom deep-link diterapkan lebih dulu supaya tetap jalan walau basemap gagal dimuat.
	if (deepLink.bounds) { map.fitBounds(deepLink.bounds, { padding: [20, 20] }); }

	fetch(window.APP.geojsonUrl).then(function (r) { return r.json(); }).then(function (gj) {
		basemap.addData(gj);
		basemap.bringToBack();
		// Deep-link bbox (klik provinsi dari dashboard utama) menang atas fit nasional.
		fitBase();
		var rt;
		window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(fitBase, 200); });
	}).catch(function () { /* basemap gagal dimuat — marker tetap tampil di latar putih */ });

	function fitBase() {
		map.invalidateSize();
		if (deepLink.bounds) { map.fitBounds(deepLink.bounds, { padding: [20, 20] }); return; }
		var b = basemap.getBounds();
		if (b.isValid()) { map.fitBounds(b, { padding: [8, 8] }); }
	}

	var cluster = L.markerClusterGroup({
		chunkedLoading: true,
		maxClusterRadius: 50,
		zoomToBoundsOnClick: false,  // klik cluster -> tampilkan List Lembaga (Hasil Review #4)
		spiderfyOnMaxZoom: false,
		// Bubble cluster: lingkaran emas polos + angka putih (gaya peta referensi),
		// menggantikan warna hijau/kuning/oranye bawaan markercluster. Style di peta.php.
		iconCreateFunction: function (c) {
			var n = c.getChildCount();
			var size = n >= 100 ? 44 : n >= 10 ? 38 : 32;
			return L.divIcon({
				html: '<span>' + n + '</span>',
				className: 'dg-cluster',
				iconSize: L.point(size, size)
			});
		}
	});
	map.addLayer(cluster);

	// Klik bubble cluster (angka jumlah lembaga) -> popup List Lembaga Vokasi.
	cluster.on('clusterclick', function (a) {
		openList(a.layer.getAllChildMarkers());
	});

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
		m._lid = pt.id; // dipakai saat cluster diklik (List Lembaga)
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
				'<div class="mt-2"><button class="btn btn-sm btn-primary py-0" data-detail="' + L0.id + '">Lihat Detail</button></div>' +
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
	// Hasil Review #4 — klik cluster -> List Lembaga -> Detail Lembaga
	// -----------------------------------------------------------------
	function ownBadge(o) {
		var cls = (o === 'Pemerintah') ? 'primary' : 'secondary';
		return '<span class="badge badge-' + cls + '">' + esc(o || '-') + '</span>';
	}

	function katBadge(v) {
		var cls = { accepted: 'success', rejected: 'danger', pending: 'warning', revised: 'info' }[v] || 'secondary';
		return '<span class="badge badge-' + cls + '">' + esc(v || 'n/a') + '</span>';
	}

	function fmtDate(s) {
		if (!s) return '-';
		var d = new Date(s);
		if (isNaN(d.getTime())) return esc(s);
		return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
	}

	function rupiah(n) { return 'Rp' + (Number(n) || 0).toLocaleString('id-ID'); }

	// Buka popup List Lembaga dari marker-marker anak sebuah cluster.
	function openList(childMarkers) {
		var ids = [];
		(childMarkers || []).forEach(function (m) { if (m._lid != null) ids.push(m._lid); });
		if (!ids.length) return;

		var body = document.getElementById('listBody');
		body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Memuat…</td></tr>';
		document.getElementById('listTotal').textContent = '…';
		window.jQuery && window.jQuery('#modalList').modal('show');

		fetch(API + '/lembaga_list?ids=' + ids.join(',')).then(function (r) { return r.json(); }).then(function (rows) {
			renderList(rows);
		}).catch(function () {
			body.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Gagal memuat data.</td></tr>';
		});
	}

	function renderList(rows) {
		var body = document.getElementById('listBody');
		if (!rows || !rows.length) {
			body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
			document.getElementById('listTotal').textContent = '0';
			return;
		}
		body.innerHTML = rows.map(function (r, i) {
			return '<tr>' +
				'<td class="text-center">' + (i + 1) + '</td>' +
				'<td>' + esc(r.nama) + '</td>' +
				'<td>' + esc(r.provinsi || '-') + '</td>' +
				'<td>' + esc(r.kota || '-') + '</td>' +
				'<td>' + ownBadge(r.ownership) + '</td>' +
				'<td>' + esc(r.nomor_registrasi || '-') + '</td>' +
				'<td>' + esc(r.nomor_legalitas || '-') + '</td>' +
				'<td class="text-center"><button class="btn btn-sm btn-outline-primary py-0" data-detail="' + r.id + '">Detail</button></td>' +
				'</tr>';
		}).join('');
		document.getElementById('listTotal').textContent = numfmt(rows.length);
	}

	// Buka modal Detail Lembaga Vokasi (3 seksi: Lembaga · Sektor&Jabatan · Katalog).
	function openDetail(id) {
		var body = document.getElementById('detailBody');
		body.innerHTML = '<div class="text-center text-muted py-4">Memuat…</div>';
		window.jQuery && window.jQuery('#modalDetail').modal('show');

		fetch(API + '/lembaga/' + id).then(function (r) { return r.json(); }).then(function (d) {
			body.innerHTML = renderDetail(d);
		}).catch(function () {
			body.innerHTML = '<div class="text-center text-danger py-4">Gagal memuat detail.</div>';
		});
	}

	function renderDetail(d) {
		var L0 = d.lembaga || {};

		function field(label, val) {
			return '<div class="col-md-6 mb-2">' +
				'<div class="text-xs text-uppercase text-muted font-weight-bold">' + label + '</div>' +
				'<div>' + esc(val == null || val === '' ? '-' : val) + '</div></div>';
		}

		// Seksi 2: Sektor & Jabatan (dikelompokkan per sektor)
		var groups = {};
		(d.sektor || []).forEach(function (s) {
			if (!groups[s.sektor]) groups[s.sektor] = [];
			groups[s.sektor].push(s.jabatan);
		});
		var sektorKeys = Object.keys(groups);
		var sektorHtml = sektorKeys.length
			? sektorKeys.map(function (sk) {
				return '<div class="col-md-6 mb-3">' +
					'<div class="font-weight-bold text-primary">' + esc(sk) + '</div>' +
					'<ul class="mb-0 pl-3">' + groups[sk].map(function (j) { return '<li>' + esc(j) + '</li>'; }).join('') + '</ul>' +
					'</div>';
			}).join('')
			: '<div class="col-12 text-muted">Tidak ada data sektor/jabatan.</div>';

		// Seksi 3: Katalog Pelatihan (bisa banyak per lembaga)
		var katHtml = (d.katalog && d.katalog.length)
			? d.katalog.map(function (k) {
				return '<div class="border rounded p-2 mb-2">' +
					'<div class="font-weight-bold">' + esc(k.judul) + ' ' + katBadge(k.status) + '</div>' +
					(k.deskripsi ? '<div class="small text-muted mb-1">' + esc(k.deskripsi) + '</div>' : '') +
					'<div class="small">' +
						'<b>Periode:</b> ' + fmtDate(k.tanggal_mulai) + ' – ' + fmtDate(k.tanggal_selesai) +
						' &middot; <b>Biaya:</b> ' + rupiah(k.biaya) +
						' &middot; <b>Jam:</b> ' + numfmt(k.jam_pelatihan) + ' JP' +
						' &middot; <b>Kuota:</b> ' + numfmt(k.kuota) +
					'</div></div>';
			}).join('')
			: '<div class="text-muted">Belum ada katalog pelatihan.</div>';

		return '' +
			'<h6 class="text-primary font-weight-bold border-bottom pb-1 mb-3"><i class="fas fa-landmark mr-1"></i> Lembaga</h6>' +
			'<div class="row mb-2">' +
				field('Nama Lembaga Vokasi', L0.nama) + field('Kepemilikan', L0.ownership) +
				field('Provinsi', L0.provinsi) + field('Jenis', L0.jenis) +
				field('Kabupaten/Kota', L0.kota) + field('Nomor Registrasi', L0.nomor_registrasi) +
				field('Email', L0.email) + field('Nomor Legalitas', L0.nomor_legalitas) +
			'</div>' +
			'<h6 class="text-primary font-weight-bold border-bottom pb-1 mb-3 mt-4"><i class="fas fa-briefcase mr-1"></i> Sektor &amp; Jabatan</h6>' +
			'<div class="row mb-2">' + sektorHtml + '</div>' +
			'<h6 class="text-primary font-weight-bold border-bottom pb-1 mb-3 mt-4"><i class="fas fa-book mr-1"></i> Katalog Pelatihan</h6>' +
			katHtml;
	}

	// Delegasi klik tombol "Detail" (di popup marker maupun tabel List).
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-detail]') : null;
		if (btn) { openDetail(btn.getAttribute('data-detail')); }
	});

	// Dukung modal bertumpuk (List -> Detail) agar backdrop & z-index benar.
	if (window.jQuery) {
		window.jQuery(document).on('show.bs.modal', '.modal', function () {
			var z = 1040 + (10 * window.jQuery('.modal:visible').length);
			window.jQuery(this).css('z-index', z);
			setTimeout(function () {
				window.jQuery('.modal-backdrop').not('.modal-stack').css('z-index', z - 1).addClass('modal-stack');
			}, 0);
		});
	}

	// -----------------------------------------------------------------
	// Charts (Chart.js v2)
	// -----------------------------------------------------------------
	var charts = {};
	Chart.defaults.global.defaultFontSize = 10;
	Chart.defaults.global.defaultFontColor = '#8a8a95';
	Chart.defaults.global.defaultFontFamily = 'Nunito, sans-serif';

	function barChart(id, horizontal, color) {
		var ctx = document.getElementById(id);
		if (!ctx) return null;
		return new Chart(ctx.getContext('2d'), {
			type: horizontal ? 'horizontalBar' : 'bar',
			data: { labels: [], datasets: [{ data: [], backgroundColor: color || GOLD.base }] },
			options: {
				maintainAspectRatio: false, legend: { display: false },
				scales: {
					xAxes: [{ ticks: { beginAtZero: true }, gridLines: { color: '#f0f0f4', drawBorder: false } }],
					yAxes: [{ ticks: { autoSkip: false }, gridLines: { display: false, drawBorder: false } }]
				},
				tooltips: { intersect: false }
			}
		});
	}

	function initCharts() {
		charts.sektor = barChart('chartSektor', true, GOLD.base);
		charts.jabatan = barChart('chartJabatan', true, GOLD.dark);
		charts.provinsi = barChart('chartProvinsi', true, GOLD.deep);
		charts.ownership = new Chart(document.getElementById('chartOwnership').getContext('2d'), {
			type: 'doughnut',
			data: { labels: ['Pemerintah', 'Non Pemerintah'], datasets: [{ data: [0, 0], backgroundColor: [COLOR.P, COLOR.N] }] },
			options: { maintainAspectRatio: false, legend: { position: 'bottom' } }
		});
		// Funnel verifikasi = stacked horizontalBar 3 tahap
		charts.funnel = new Chart(document.getElementById('chartFunnel').getContext('2d'), {
			type: 'horizontalBar',
			data: {
				labels: ['Layer 1 (Legalitas)', 'Layer 2 (Fasilitas)', 'Layer 3 Program'],
				datasets: [
					{ label: 'Accepted', backgroundColor: GOLD.deep, data: [0, 0, 0] },
					{ label: 'Rejected', backgroundColor: '#c9534f', data: [0, 0, 0] },
					{ label: 'Pending', backgroundColor: GOLD.base, data: [0, 0, 0] },
					{ label: 'Revised', backgroundColor: GOLD.light, data: [0, 0, 0] },
					{ label: 'Not submitted', backgroundColor: '#dcdce4', data: [0, 0, 0] }
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
			fillSelect('fProvinsi', refs.provinsi, 'kode', 'nama');
			// Kota
			allKota = refs.kota || [];
			renderKota('');
			// Sektor checkbox
			renderChecklist('fSektor', refs.sektor, 'slug', 'nama', 'jumlah_lembaga', 'sektor_slug');
			// Jabatan (searchable checklist)
			allJabatan = refs.jabatan || [];
			renderJabatan('');

			// Deep-link provinsi (dari klik peta dashboard utama) -> set filter provinsi.
			if (deepLink.provinsiNama) {
				var want = provKey(deepLink.provinsiNama);
				var hit = (refs.provinsi || []).filter(function (o) { return provKey(o.nama) === want; })[0];
				if (hit) { state.provinsi = String(hit.kode); }
			}

			bindEvents();
			applyStateToInputs();
			refresh();
		});
	}

	function fillSelect(id, list, valKey, labelKey, countKey) {
		var el = document.getElementById(id);
		(list || []).slice().sort(function (a, b) {
			return String(a[labelKey]).localeCompare(String(b[labelKey]), 'id', { sensitivity: 'base' });
		}).forEach(function (o) {
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
			.sort(function (a, b) { return a.nama.localeCompare(b.nama, 'id', { sensitivity: 'base' }); })
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
		bindInput('fTahapan', 'tahapan');
		bindInput('fPulau', 'pulau');
		bindInput('fProvinsi', 'provinsi');
		bindInput('fKota', 'kota_slug');
		bindInput('fKapMin', 'kapasitas_min');
		bindInput('fKapMax', 'kapasitas_max');

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
			q: '', ownership: '', tahapan: '', pulau: '', provinsi: '',
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
		setVal('fTahapan', state.tahapan); setVal('fPulau', state.pulau);
		setVal('fProvinsi', state.provinsi); setVal('fKota', state.kota_slug);
		setVal('fKapMin', state.kapasitas_min); setVal('fKapMax', state.kapasitas_max);
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
	// Mode "Perbesar Peta" — sembunyikan panel filter & chart, peta dominan
	// (berguna saat dashboard dipajang di layar command center)
	// -----------------------------------------------------------------
	(function () {
		var btn = document.getElementById('btnToggleFilter');
		if (!btn) return;
		var colFilter = document.getElementById('colFilter');
		var colChart = document.getElementById('colChart');
		var colMap = document.getElementById('colMap');
		var expanded = false;
		btn.addEventListener('click', function () {
			expanded = !expanded;
			colFilter.classList.toggle('d-none', expanded);
			colChart.classList.toggle('d-none', expanded);
			colMap.classList.toggle('col-lg-9', !expanded);
			colMap.classList.toggle('col-lg-12', expanded);
			btn.innerHTML = expanded
				? '<i class="fas fa-compress-arrows-alt"></i> Tampilkan Panel'
				: '<i class="fas fa-expand-arrows-alt"></i> Perbesar Peta';
			// Leaflet perlu hitung ulang ukuran setelah lebar kontainer berubah.
			setTimeout(function () { map.invalidateSize(); }, 60);
		});
	})();

	// -----------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------
	loadStateFromUrl();
	initCharts();
	initFilters();
})();
