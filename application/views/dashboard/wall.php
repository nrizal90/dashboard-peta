<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?= html_escape($title) ?></title>

	<!-- Leaflet -->
	<link href="<?= base_url('assets/vendor/leaflet/leaflet.css') ?>" rel="stylesheet">
	<link href="<?= base_url('assets/vendor/fontawesome-free/css/all.min.css') ?>" rel="stylesheet">

	<style>
		* { box-sizing: border-box; }
		html, body { margin: 0; padding: 0; height: 100%; background: #1a1a2e; font-family: 'Segoe UI', Arial, sans-serif; }

		/* Peta memenuhi seluruh layar */
		#map { position: absolute; inset: 0; height: 100%; width: 100%; z-index: 0; }

		/* Bar judul overlay di atas peta */
		#topbar {
			position: absolute; top: 0; left: 0; right: 0; z-index: 1000;
			display: flex; align-items: center; justify-content: space-between;
			padding: 18px 28px; color: #fff;
			background: linear-gradient(180deg, rgba(20,20,40,.85) 0%, rgba(20,20,40,0) 100%);
			pointer-events: none;
		}
		#topbar .title { font-size: 2rem; font-weight: 700; letter-spacing: .5px; text-shadow: 0 2px 6px rgba(0,0,0,.6); }
		#topbar .title i { color: #4e73df; margin-right: 12px; }
		#topbar .meta { text-align: right; font-size: 1.1rem; line-height: 1.5; text-shadow: 0 2px 6px rgba(0,0,0,.6); }
		#clock { font-size: 1.8rem; font-weight: 600; }

		/* Indikator status refresh (kiri bawah) */
		#status {
			position: absolute; left: 20px; bottom: 20px; z-index: 1000;
			display: flex; align-items: center; gap: 10px;
			padding: 10px 18px; border-radius: 999px;
			background: rgba(20,20,40,.85); color: #fff; font-size: 1rem;
		}
		#status .dot { width: 12px; height: 12px; border-radius: 50%; background: #1cc88a; transition: background .3s; }
		#status .dot.stale { background: #f6c23e; }
		#status .dot.error { background: #e74a3b; }
		#status .dot.loading { background: #4e73df; animation: pulse 1s infinite; }
		@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }

		/* Popup & marker lebih besar agar terbaca dari jauh */
		.leaflet-popup-content { font-size: 1.05rem; }
		.leaflet-popup-content strong { font-size: 1.2rem; }
	</style>
</head>
<body>

	<div id="map"></div>

	<div id="topbar">
		<div class="title"><i class="fas fa-map-marked-alt"></i><?= html_escape($title) ?></div>
		<div class="meta">
			<div id="clock">--:--:--</div>
			<div id="today"></div>
		</div>
	</div>

	<div id="status">
		<span class="dot loading" id="statusDot"></span>
		<span id="statusText">Memuat data…</span>
	</div>

	<script src="<?= base_url('assets/vendor/leaflet/leaflet.js') ?>"></script>
	<script>
	(function () {
		'use strict';

		var CONFIG = {
			center:    <?= json_encode($map_center) ?>,
			zoom:      <?= (int) $map_zoom ?>,
			apiUrl:    '<?= site_url('api/lokasi') ?>',
			refreshMs: <?= (int) $refresh_ms ?>
		};

		// --- Inisialisasi peta (dibuat SEKALI saja) ---
		var map = L.map('map', { zoomControl: true, attributionControl: true })
			.setView(CONFIG.center, CONFIG.zoom);

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap'
		}).addTo(map);

		// Satu layer marker yang dipakai ulang — tidak pernah dibuat ulang.
		var markerLayer = L.geoJSON(null, {
			onEachFeature: function (feature, lyr) {
				var p = feature.properties || {};
				lyr.bindPopup(
					'<strong>' + esc(p.nama) + '</strong>' +
					'<br><span>' + esc(p.kategori) + '</span>' +
					'<br>' + esc(p.keterangan)
				);
			}
		}).addTo(map);

		var firstLoad = true;

		// --- Status indikator ---
		var dot = document.getElementById('statusDot');
		var statusText = document.getElementById('statusText');
		function setStatus(state, text) {
			dot.className = 'dot ' + state;
			statusText.textContent = text;
		}

		function esc(s) {
			return String(s == null ? '' : s)
				.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		}

		// --- Auto-refresh ringan ---
		// - hanya menarik JSON kecil, bukan reload halaman
		// - guard `busy` mencegah request menumpuk kalau jaringan lambat
		// - AbortController membatalkan request yang kelamaan (timeout)
		// - kalau gagal, data lama tetap dipertahankan
		var busy = false;
		var timer = null;
		var lastEtag = null;   // ETag data terakhir; dikirim balik sbagai If-None-Match
		var lastCount = 0;     // jumlah lokasi terakhir (untuk teks status saat 304)

		function refresh() {
			if (busy) { return; }
			busy = true;
			if (!firstLoad) { setStatus('loading', 'Memperbarui…'); }

			var ctrl = new AbortController();
			var to = setTimeout(function () { ctrl.abort(); }, 15000);

			var headers = {};
			// Kirim ETag terakhir → server balas 304 kalau data tak berubah.
			if (lastEtag) { headers['If-None-Match'] = lastEtag; }

			fetch(CONFIG.apiUrl, { signal: ctrl.signal, cache: 'no-store', headers: headers })
				.then(function (res) {
					// 304 = data tidak berubah. Body kosong, layer TIDAK disentuh.
					if (res.status === 304) {
						setStatus('', lastCount + ' lokasi • tetap ' + timeNow());
						firstLoad = false;
						return null;
					}
					if (!res.ok) { throw new Error('HTTP ' + res.status); }

					lastEtag = res.headers.get('ETag') || lastEtag;
					return res.json();
				})
				.then(function (geojson) {
					if (geojson === null) { return; } // kasus 304, sudah ditangani

					// Perbarui isi layer tanpa menyentuh objek peta.
					markerLayer.clearLayers();
					markerLayer.addData(geojson);

					// fitBounds HANYA saat pertama kali (biar tidak "meloncat").
					if (firstLoad && geojson.features && geojson.features.length) {
						map.fitBounds(markerLayer.getBounds().pad(0.2));
					}
					firstLoad = false;

					lastCount = (geojson.features || []).length;
					setStatus('', lastCount + ' lokasi • diperbarui ' + timeNow());
				})
				.catch(function (err) {
					// Pertahankan data lama; hanya tandai statusnya.
					setStatus('error', 'Gagal memuat — memakai data terakhir');
					console.error('Refresh gagal:', err);
				})
				.finally(function () {
					clearTimeout(to);
					busy = false;
				});
		}

		// --- Penjadwalan yang hemat: berhenti saat tab tidak terlihat ---
		function start() {
			if (timer) { return; }
			refresh();                     // tarik segera saat mulai
			timer = setInterval(refresh, CONFIG.refreshMs);
		}
		function stop() {
			if (timer) { clearInterval(timer); timer = null; }
		}

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				stop();                    // hemat resource saat layar/tab disembunyikan
			} else {
				start();                   // langsung segarkan begitu terlihat lagi
			}
		});

		// --- Jam ---
		function pad(n) { return (n < 10 ? '0' : '') + n; }
		function timeNow() {
			var d = new Date();
			return pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
		}
		var clockEl = document.getElementById('clock');
		var todayEl = document.getElementById('today');
		var hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
		var bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
		function tick() {
			var d = new Date();
			clockEl.textContent = timeNow();
			todayEl.textContent = hari[d.getDay()] + ', ' + d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear();
		}
		tick();
		setInterval(tick, 1000);

		// Mulai
		start();
	})();
	</script>
</body>
</html>
