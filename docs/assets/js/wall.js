/* global L, Chart */
/**
 * wall.js — logika Command Center (kiosk auto-rotasi, tetap bisa dioperasikan).
 *
 * Scene: Peta → Gap Analysis → Chart, berotasi otomatis tiap WALL.rotateMs.
 * Operator dapat: pause/play, navigasi manual (prev/next/dot), klik marker, zoom.
 * Interaksi manual otomatis MEM-PAUSE rotasi; tekan play untuk melanjutkan.
 */
(function () {
	'use strict';

	var API = window.WALL.apiBase;
	var COLOR = { P: '#4e73df', N: '#f6c23e' };

	// -----------------------------------------------------------------
	// Peta + cluster (dibuat sekali)
	// -----------------------------------------------------------------
	var map = L.map('map', { zoomControl: true }).setView(window.WALL.mapCenter, window.WALL.mapZoom);
	L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png', {
		maxZoom: 19, attribution: '&copy; OpenStreetMap &copy; CARTO'
	}).addTo(map);

	var cluster = L.markerClusterGroup({ chunkedLoading: true, maxClusterRadius: 55 });
	map.addLayer(cluster);

	var legend = L.control({ position: 'bottomright' });
	legend.onAdd = function () {
		var d = L.DomUtil.create('div', 'map-legend');
		d.innerHTML =
			'<div><span class="legend-dot" style="background:' + COLOR.P + '"></span>Pemerintah</div>' +
			'<div><span class="legend-dot" style="background:' + COLOR.N + '"></span>Non Pemerintah</div>' +
			'<div><span class="legend-dot approx"></span>Lokasi perkiraan</div>';
		return d;
	};
	legend.addTo(map);

	function radiusFor(k) { return Math.max(5, Math.log10((k || 0) + 1) * 5); }

	function loadPoints() {
		fetch(API + '/points').then(function (r) { return r.json(); }).then(function (pts) {
			var markers = [];
			pts.forEach(function (p) {
				var approx = p.cs === 'c';
				var m = L.circleMarker([p.lat, p.lng], {
					radius: radiusFor(p.k),
					color: approx ? '#aaa' : (COLOR[p.o] || COLOR.N),
					weight: approx ? 2 : 1,
					dashArray: approx ? '3,3' : null,
					fillColor: COLOR[p.o] || COLOR.N,
					fillOpacity: approx ? 0.25 : 0.8,
					opacity: approx ? 0.6 : 1
				});
				m.bindPopup('<strong>' + esc(p.n) + '</strong><br><small>memuat…</small>');
				m.on('click', function () { popupDetail(m, p.id); });
				markers.push(m);
			});
			cluster.addLayers(markers);
		});
	}

	function popupDetail(marker, id) {
		fetch(API + '/lembaga/' + id).then(function (r) { return r.json(); }).then(function (d) {
			var l = d.lembaga || {};
			var sektor = {}; (d.sektor || []).forEach(function (s) { sektor[s.sektor] = true; });
			var approx = l.coord_source !== 'original';
			marker.setPopupContent(
				'<div style="min-width:220px"><strong>' + esc(l.nama) + '</strong>' +
				(approx ? ' <span style="background:#f6c23e;color:#000;border-radius:4px;padding:0 5px;font-size:.8rem">Lokasi perkiraan</span>' : '') +
				'<br><small>' + esc(l.kota) + ', ' + esc(l.provinsi) + '</small><hr style="margin:6px 0">' +
				'<div><b>Ownership:</b> ' + esc(l.ownership) + '</div>' +
				'<div><b>Kapasitas:</b> ' + (l.kapasitas == null ? '—' : numfmt(l.kapasitas)) + '</div>' +
				'<div style="margin-top:4px"><b>Sektor:</b> ' + Object.keys(sektor).map(esc).join(', ') + '</div></div>'
			);
		});
	}

	// -----------------------------------------------------------------
	// Chart (dibuat sekali, diisi dari /api/stats tanpa filter)
	// -----------------------------------------------------------------
	Chart.defaults.global.defaultFontColor = '#c8ccf5';
	Chart.defaults.global.defaultFontSize = 12;
	var charts = {};

	function hbar(id, color) {
		return new Chart(document.getElementById(id).getContext('2d'), {
			type: 'horizontalBar',
			data: { labels: [], datasets: [{ data: [], backgroundColor: color }] },
			options: {
				maintainAspectRatio: false, legend: { display: false },
				scales: {
					xAxes: [{ ticks: { beginAtZero: true, fontColor: '#8b91c4' }, gridLines: { color: '#2a3260' } }],
					yAxes: [{ ticks: { fontColor: '#c8ccf5', autoSkip: false }, gridLines: { color: '#2a3260' } }]
				}
			}
		});
	}

	function initCharts() {
		charts.sektor = hbar('wSektor', '#4e73df');
		charts.provinsi = hbar('wProvinsi', '#36b9cc');
		charts.ownership = new Chart(document.getElementById('wOwnership').getContext('2d'), {
			type: 'doughnut',
			data: { labels: ['Pemerintah', 'Non Pemerintah'], datasets: [{ data: [0, 0], backgroundColor: [COLOR.P, COLOR.N] }] },
			options: { maintainAspectRatio: false, legend: { position: 'bottom' } }
		});
		charts.funnel = new Chart(document.getElementById('wFunnel').getContext('2d'), {
			type: 'horizontalBar',
			data: {
				labels: ['Legalitas', 'Fasilitas', 'Program'],
				datasets: [
					{ label: 'Accepted', backgroundColor: '#1cc88a', data: [0, 0, 0] },
					{ label: 'Rejected', backgroundColor: '#e74a3b', data: [0, 0, 0] },
					{ label: 'Pending', backgroundColor: '#f6c23e', data: [0, 0, 0] },
					{ label: 'Revised', backgroundColor: '#36b9cc', data: [0, 0, 0] },
					{ label: 'Not submitted', backgroundColor: '#6b7099', data: [0, 0, 0] }
				]
			},
			options: {
				maintainAspectRatio: false, legend: { position: 'bottom', labels: { boxWidth: 12 } },
				scales: {
					xAxes: [{ stacked: true, ticks: { fontColor: '#8b91c4' }, gridLines: { color: '#2a3260' } }],
					yAxes: [{ stacked: true, ticks: { fontColor: '#c8ccf5' }, gridLines: { color: '#2a3260' } }]
				}
			}
		});
	}

	function loadStats() {
		fetch(API + '/stats').then(function (r) { return r.json(); }).then(function (s) {
			setBar(charts.sektor, s.top_sektor);
			setBar(charts.provinsi, s.top_provinsi);
			charts.ownership.data.datasets[0].data = [s.ownership['Pemerintah'] || 0, s.ownership['Non Pemerintah'] || 0];
			charts.ownership.update();
			var f = s.verifikasi, order = ['legalitas', 'fasilitas', 'program'];
			['accepted', 'rejected', 'pending', 'revised', 'not_submitted'].forEach(function (key, di) {
				charts.funnel.data.datasets[di].data = order.map(function (t) { return (f[t] && f[t][key]) || 0; });
			});
			charts.funnel.update();
		});
	}

	function setBar(c, arr) {
		c.data.labels = (arr || []).map(function (o) { return o.label; });
		c.data.datasets[0].data = (arr || []).map(function (o) { return o.value; });
		c.update();
	}

	// -----------------------------------------------------------------
	// Rotasi scene
	// -----------------------------------------------------------------
	var scenes = Array.prototype.slice.call(document.querySelectorAll('.scene'));
	var idx = 0, playing = true, timer = null, progTimer = null, elapsed = 0;
	var dotsEl = document.getElementById('dots');
	var nameEl = document.getElementById('sceneName');
	var progEl = document.getElementById('progressBar');
	var modeEl = document.getElementById('modeText');
	var playIcon = document.querySelector('#btnPlay i');

	scenes.forEach(function (sc, i) {
		var dot = document.createElement('span');
		dot.className = 'dot' + (i === 0 ? ' on' : '');
		dot.addEventListener('click', function () { go(i, true); });
		dotsEl.appendChild(dot);
	});

	function show(i) {
		scenes.forEach(function (sc, j) { sc.classList.toggle('active', j === i); });
		dotsEl.querySelectorAll('.dot').forEach(function (d, j) { d.classList.toggle('on', j === i); });
		nameEl.textContent = scenes[i].dataset.name;
		idx = i;
		// Kalau scene peta, pastikan Leaflet menghitung ulang ukurannya.
		if (scenes[i].id === 'sceneMap') { setTimeout(function () { map.invalidateSize(); }, 60); }
	}

	function go(i, manual) {
		show((i + scenes.length) % scenes.length);
		resetProgress();
		if (manual) { pause(); }
	}

	function next() { go(idx + 1, false); }

	function resetProgress() { elapsed = 0; progEl.style.width = '0%'; }

	function tickProgress() {
		if (!playing) { return; }
		elapsed += 100;
		var pct = Math.min(100, (elapsed / window.WALL.rotateMs) * 100);
		progEl.style.width = pct + '%';
		if (elapsed >= window.WALL.rotateMs) { next(); }
	}

	function play() {
		playing = true;
		playIcon.className = 'fas fa-pause';
		modeEl.textContent = 'Auto-rotasi'; modeEl.classList.remove('manual');
		resetProgress();
	}
	function pause() {
		playing = false;
		playIcon.className = 'fas fa-play';
		modeEl.textContent = 'Manual (dijeda)'; modeEl.classList.add('manual');
		progEl.style.width = '0%';
	}
	function toggle() { playing ? pause() : play(); }

	document.getElementById('btnPlay').addEventListener('click', toggle);
	document.getElementById('btnPrev').addEventListener('click', function () { go(idx - 1, true); });
	document.getElementById('btnNext').addEventListener('click', function () { go(idx + 1, true); });

	// Keyboard untuk operator: ← → navigasi, spasi play/pause
	document.addEventListener('keydown', function (e) {
		if (e.key === 'ArrowRight') go(idx + 1, true);
		else if (e.key === 'ArrowLeft') go(idx - 1, true);
		else if (e.key === ' ') { e.preventDefault(); toggle(); }
	});

	// -----------------------------------------------------------------
	// Jam
	// -----------------------------------------------------------------
	var hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
	var bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
	var clockEl = document.getElementById('clock'), todayEl = document.getElementById('today');
	function pad(n) { return (n < 10 ? '0' : '') + n; }
	function tickClock() {
		var d = new Date();
		clockEl.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
		todayEl.textContent = hari[d.getDay()] + ', ' + d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear();
	}

	// -----------------------------------------------------------------
	// Util
	// -----------------------------------------------------------------
	function numfmt(n) { return (n || 0).toLocaleString('id-ID'); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	// -----------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------
	initCharts();
	loadPoints();
	loadStats();
	show(0);
	tickClock();
	setInterval(tickClock, 1000);
	progTimer = setInterval(tickProgress, 100);
})();
