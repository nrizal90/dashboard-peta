<?php
/**
 * Dashboard utama (redesign) — ringkasan verifikasi lembaga vokasi.
 *
 * CATATAN: seluruh angka di halaman ini masih HARDCODE untuk keperluan
 * penyusunan UI. Setelah tampilan final disetujui, sumber data akan
 * disambungkan ke DB/endpoint. Palet warna: application/config/tema.php.
 *
 * Versi peta sebelumnya disimpan di index.php.<timestamp>.bak (folder ini).
 */

// ====== DATA HARDCODE (sementara) ======
// Palet emas — SATU SUMBER di application/config/tema.php (autoload).
// CSS-nya sudah ditulis sebagai :root{--dg-*} di templates/header.php;
// variabel PHP di bawah hanya dipakai untuk mewarnai Chart.js & Leaflet.
$tema      = $this->config->item('tema');
$gold      = $tema['gold'];
$goldDark  = $tema['gold_dark'];
$goldDeep  = $tema['gold_deep'];
$goldLight = $tema['gold_light'];

// Formatter angka: tampilkan '-' bila data DB tidak tersedia (bukan angka contoh).
$fmtNum = function ($v) { return ($v === NULL) ? '-' : number_format($v, 0, ',', '.'); };
$fmtPct = function ($v) { return ($v === NULL) ? '-' : ((int) $v) . '%'; };

// Kartu sambutan — terverifikasi legalitas, unik per email (DB); fallback '-'.
$h = isset($header) ? $header : array();
$verifLegalitas = isset($h['terverifikasi_legalitas']) ? (int) $h['terverifikasi_legalitas'] : NULL;

// KPI atas — angka NYATA dari DB (view dashboard_vokasi_detail); fallback '-'.
$kv = isset($kpi) ? $kpi : array();
$kvGet = function ($key) use ($kv) {
	return isset($kv[$key]) ? array((int) $kv[$key]['nilai'], (int) $kv[$key]['persen']) : array(NULL, NULL);
};
$kFas = $kvGet('fasilitas');
$kPro = $kvGet('program');
$kKes = $kvGet('keseluruhan');

$kpis = array(
	array('Terverifikasi Fasilitas',   $kFas[0], $kFas[1], 'Jumlah Lembaga yang Telah Lulus Verifikasi Data Fasilitas.'),
	array('Terverifikasi Program',     $kPro[0], $kPro[1], 'Jumlah Lembaga yang Telah Lulus Verifikasi Data Program Pelatihan.'),
	array('Terverifikasi Keseluruhan', $kKes[0], $kKes[1], 'Jumlah Lembaga yang Telah Lulus Seluruh Tahapan Verifikasi.'),
);

$km = isset($komposisi) ? $komposisi : array();

// Status Lembaga Vokasi (donut) — DB (distribusi legalitas).
$kmStatus = isset($km['status']) ? $km['status'] : array();
$statusTotal  = isset($kmStatus['total']) ? (int) $kmStatus['total'] : NULL;
$statusLabels = array('Terverifikasi Legalitas', 'Dalam Proses', 'Ditolak');
$statusData   = array(
	isset($kmStatus['terverifikasi']) ? (int) $kmStatus['terverifikasi'] : 0,
	isset($kmStatus['proses'])        ? (int) $kmStatus['proses']        : 0,
	isset($kmStatus['ditolak'])       ? (int) $kmStatus['ditolak']       : 0,
);

// Akreditasi — HARDCODE (belum ada kolom akreditasi di view dashboard_vokasi_*).
$akreditasiLabels = array('Akreditasi A', 'Akreditasi B', 'Belum Terakreditasi', 'Akreditasi C');
$akreditasiData   = array(644, 459, 357, 72);

// Bentuk Lembaga (bar) — DB (vok_institution_form).
$kmBentuk = ( ! empty($km['bentuk'])) ? $km['bentuk'] : array(
	array('label' => 'Pendidikan dan Pelatihan', 'value' => 882),
	array('label' => 'Pelatihan',                'value' => 463),
	array('label' => 'Pendidikan',               'value' => 280),
);
$bentukLabels = array_map(function ($x) { return $x['label']; }, $kmBentuk);
$bentukData   = array_map(function ($x) { return (int) $x['value']; }, $kmBentuk);

$sb = isset($sebaran) ? $sebaran : array();

// Provinsi Top 5 (bar) — DB (legalitas accepted).
$sbProv = ( ! empty($sb['provinsi_top'])) ? $sb['provinsi_top'] : array(
	array('label' => 'Jawa Tengah', 'value' => 166),
	array('label' => 'Jawa Barat',  'value' => 142),
	array('label' => 'Jawa Timur',  'value' => 113),
	array('label' => 'Bali',        'value' => 59),
	array('label' => 'Daerah Istimewa Yogyakarta', 'value' => 48),
);
$provLabels = array_map(function ($x) { return $x['label']; }, $sbProv);
$provData   = array_map(function ($x) { return (int) $x['value']; }, $sbProv);

$js = isset($jenis_sektor) ? $jenis_sektor : array();

// Jenis Lembaga Vokasi (bar) — DB (type_name).
$jsJenis = ( ! empty($js['jenis'])) ? $js['jenis'] : array(
	array('label' => 'LPK', 'value' => 967), array('label' => 'SMK', 'value' => 190),
	array('label' => 'LKP', 'value' => 148), array('label' => 'Politeknik', 'value' => 93),
	array('label' => 'Universitas', 'value' => 92), array('label' => 'BLK', 'value' => 73),
	array('label' => 'Balai', 'value' => 29), array('label' => 'BLKLN', 'value' => 19),
	array('label' => 'LSP', 'value' => 8), array('label' => 'SMA', 'value' => 7),
);
$jenisLabels = array_map(function ($x) { return $x['label']; }, $jsJenis);
$jenisData   = array_map(function ($x) { return (int) $x['value']; }, $jsJenis);

// Sektor Spesialisasi Top 5 (bar) — DB (jumlah lembaga unik per sektor).
$jsSektor = ( ! empty($js['sektor'])) ? $js['sektor'] : array(
	array('label' => 'Bahasa', 'value' => 716),
	array('label' => 'Tourism, Travel, dan Hospitality', 'value' => 417),
	array('label' => 'Teknologi Informasi', 'value' => 311),
	array('label' => 'Bisnis dan Manajemen', 'value' => 247),
	array('label' => 'Kesehatan', 'value' => 239),
);
$sektorLabels = array_map(function ($x) { return $x['label']; }, $jsSektor);
$sektorData   = array_map(function ($x) { return (int) $x['value']; }, $jsSektor);

// Tanggal Bahasa Indonesia (tanpa strftime yang sudah deprecated)
$hariID  = array('Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu');
$bulanID = array(1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember');
$tanggalID = $hariID[(int) date('w')] . ', ' . date('d') . ' ' . $bulanID[(int) date('n')] . ' ' . date('Y');

// Peta choropleth — DB { key_provinsi => jumlah accepted }. Fallback contoh bila kosong.
$mapChoropleth = ( ! empty($sb['choropleth'])) ? $sb['choropleth'] : array(
	'jawatengah' => 166, 'jawabarat' => 142, 'jawatimur' => 113, 'bali' => 59, 'yogyakarta' => 48,
);
?>

<!-- ===== TEMA EMAS (warnanya dari config/tema.php via header.php) ===== -->
<style>
	.dg-wrap { background: #f7f7fb; }
	.dg-card { border: 0; border-radius: 1rem; box-shadow: 0 6px 22px rgba(0,0,0,.05); }
	.dg-card .card-body { padding: 1.25rem 1.4rem; }
	.dg-title { font-weight: 800; color: #2b2b33; letter-spacing: -.01em; }
	.dg-sub { color: #9a9aa6; font-size: .78rem; margin-bottom: 1rem; }

	/* Kartu sambutan */
	.dg-hero { background: #fff; }
	.dg-hero-logo { width: 92px; height: 92px; object-fit: contain; }
	.dg-hero .stat-num { font-size: 1.6rem; font-weight: 800; color: #2b2b33; }
	.dg-hero .stat-lbl { font-size: .72rem; color: #9a9aa6; text-transform: none; font-weight: 700; }

	/* KPI */
	.dg-kpi .kpi-name { font-size: .82rem; color: #6b6b76; font-weight: 700; }
	.dg-kpi .kpi-num  { font-size: 2rem; font-weight: 800; color: #2b2b33; line-height: 1.1; }
	.dg-kpi .kpi-desc { font-size: .72rem; color: #a2a2ad; margin-top: .5rem; line-height: 1.35; }
	.dg-badge {
		background: var(--dg-gold); color: #6a5308; font-weight: 800; font-size: .7rem;
		padding: .18rem .5rem; border-radius: 999px;
	}

	.dg-legend-total { font-size: 1.9rem; font-weight: 800; color: #2b2b33; }
	.dg-legend-total small { display:block; font-size:.7rem; font-weight:600; color:#9a9aa6; }

	#dgMap { height: 380px; border-radius: .75rem; z-index: 0; background: #ffffff; }
	#dgMap .leaflet-interactive { cursor: pointer; }
	#dgMap .leaflet-container { background: #ffffff; }

	/* Catatan "data contoh" untuk kartu yang belum tersambung DB */
	.dg-note-dummy {
		background: #fff8e1; border: 1px solid #f3e2a9; color: #8a6d1a;
		font-size: .72rem; line-height: 1.3; border-radius: .5rem;
		padding: .4rem .6rem; margin-bottom: .6rem;
	}

	.chart-box { position: relative; }
	.chart-box.h-sm  { height: 210px; }
	.chart-box.h-md  { height: 260px; }
	.chart-box.h-lg  { height: 300px; }

	/* ===== Responsive: tablet ke bawah ===== */
	@media (max-width: 991.98px) {
		#dgMap { height: 340px; }
	}
	/* ===== Responsive: HP (xs) ===== */
	@media (max-width: 575.98px) {
		.dg-card .card-body { padding: 1rem 1.05rem; }
		.dg-hero-logo { width: 60px; height: 60px; }
		.dg-hero .stat-num { font-size: 1.35rem; }
		.dg-kpi .kpi-num { font-size: 1.6rem; }
		.dg-legend-total { font-size: 1.6rem; }
		#dgMap { height: 300px; }
		.chart-box.h-md { height: 240px; }
		.chart-box.h-lg { height: 260px; }
	}
</style>

<!-- Begin Page Content -->
<div class="container-fluid dg-wrap py-2">

	<!-- ===== BARIS 1: SAMBUTAN + KPI ===== -->
	<div class="row">
		<!-- Kartu sambutan -->
		<div class="col-xl-4 col-lg-5 mb-4">
			<div class="card dg-card dg-hero h-100">
				<div class="card-body d-flex flex-column">
					<div class="d-flex align-items-center justify-content-between">
						<div>
							<h5 class="dg-title mb-1">Hi, Admin Pusdatin <span style="font-size:1rem;">👋</span></h5>
							<div class="text-muted small font-italic mb-0" id="dgClock">
								<?= $tanggalID ?> pukul <?= date('h.i A') ?>
							</div>
						</div>
						<img src="<?= base_url('assets/img/logo-color.webp') ?>" alt="Logo" class="dg-hero-logo">
					</div>
					<div class="row mt-auto pt-3">
						<div class="col-12">
							<div class="stat-lbl">Terverifikasi Legalitas</div>
							<div class="stat-num"><?= $fmtNum($verifLegalitas) ?></div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- 3 KPI -->
		<div class="col-xl-8 col-lg-7 mb-4">
			<div class="row h-100">
				<?php foreach ($kpis as $k): ?>
				<div class="col-xl-4 col-sm-4 col-12 mb-3 mb-xl-0">
					<div class="card dg-card dg-kpi h-100">
						<div class="card-body">
							<div class="d-flex justify-content-between align-items-start mb-2">
								<span class="kpi-name"><?= html_escape($k[0]) ?></span>
							</div>
							<div class="d-flex align-items-center justify-content-between">
								<span class="kpi-num"><?= $fmtNum($k[1]) ?></span>
								<span class="dg-badge"><?= $fmtPct($k[2]) ?></span>
							</div>
							<div class="kpi-desc"><?= html_escape($k[3]) ?></div>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- ===== BARIS 2: STATUS · AKREDITASI · BENTUK ===== -->
	<div class="row">
		<div class="col-md-6 col-lg-4 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Status Lembaga Vokasi</div>
					<div class="dg-sub">Komposisi Lembaga Vokasi Berdasarkan Status Lembaga.</div>
					<div class="chart-box h-md"><canvas id="chStatus"></canvas></div>
				</div>
			</div>
		</div>
		<div class="col-md-6 col-lg-4 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Akreditasi Lembaga
						<span class="badge badge-warning ml-1" style="font-size:.6rem;vertical-align:middle;" title="Angka contoh — kolom akreditasi belum tersedia di database">contoh</span>
					</div>
					<div class="dg-sub">Distribusi Status Akreditasi Sebagai Indikator Kualitas Lembaga Vokasi.</div>
					<div class="dg-note-dummy">
						<i class="fas fa-info-circle mr-1"></i>
						Data masih <b>contoh</b> — belum tersedia di database, jadi angka di sini bukan data sebenarnya.
					</div>
					<div class="chart-box h-md"><canvas id="chAkreditasi"></canvas></div>
				</div>
			</div>
		</div>
		<div class="col-md-6 col-lg-4 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Bentuk Lembaga</div>
					<div class="dg-sub">Distribusi Lembaga Berdasarkan Bentuk Penyelenggaraan.</div>
					<div class="chart-box h-md"><canvas id="chBentuk"></canvas></div>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== BARIS 3: PETA + PROVINSI TOP 5 ===== -->
	<div class="row">
		<div class="col-lg-7 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peta Persebaran Verifikasi Lembaga</div>
					<div class="dg-sub">Visualisasi Persebaran Lembaga yang Telah Melalui Proses Verifikasi Legalitas.</div>
					<div id="dgMap"></div>
				</div>
			</div>
		</div>
		<div class="col-lg-5 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Sebaran Lembaga berdasarkan Provinsi (Top 5)</div>
					<div class="dg-sub">Lima Provinsi dengan Jumlah Lembaga Terbanyak berdasarkan Verifikasi Legalitas.</div>
					<div class="chart-box h-md"><canvas id="chProvinsi"></canvas></div>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== BARIS 4: JENIS + SEKTOR ===== -->
	<div class="row">
		<div class="col-lg-6 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Jenis Lembaga Vokasi</div>
					<div class="dg-sub">Distribusi Lembaga Vokasi Berdasarkan Jenis Kelembagaan yang Telah Diverifikasi.</div>
					<div class="chart-box h-md"><canvas id="chJenis"></canvas></div>
				</div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Sektor Spesialisasi Lembaga (Top 5)</div>
					<div class="dg-sub">Lima Sektor Spesialisasi dengan Jumlah Lembaga Terbanyak.</div>
					<div class="chart-box h-md"><canvas id="chSektor"></canvas></div>
				</div>
			</div>
		</div>
	</div>

</div>
<!-- /.container-fluid -->

<script>
window.DG = {
	gold: '<?= $gold ?>', goldDark: '<?= $goldDark ?>', goldDeep: '<?= $goldDeep ?>', goldLight: '<?= $goldLight ?>',
	status:     { labels: <?= json_encode($statusLabels) ?>, data: <?= json_encode($statusData) ?>, total: <?= $statusTotal === NULL ? 'null' : $statusTotal ?> },
	akreditasi: { labels: <?= json_encode($akreditasiLabels) ?>, data: <?= json_encode($akreditasiData) ?> },
	bentuk:     { labels: <?= json_encode($bentukLabels) ?>, data: <?= json_encode($bentukData) ?> },
	provinsi:   { labels: <?= json_encode($provLabels) ?>, data: <?= json_encode($provData) ?> },
	jenis:      { labels: <?= json_encode($jenisLabels) ?>, data: <?= json_encode($jenisData) ?> },
	sektor:     { labels: <?= json_encode($sektorLabels) ?>, data: <?= json_encode($sektorData) ?> },
	choropleth: <?= json_encode($mapChoropleth) ?>,
	geojsonUrl: '<?= base_url('assets/vendor/geojson/indonesia-provinsi.json') ?>',
	petaUrl: '<?= site_url('peta') ?>'
};

// Jam berjalan kartu sambutan — selalu ikut waktu perangkat pengguna.
(function () {
	var HARI  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
	var BULAN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
	function tick() {
		var el = document.getElementById('dgClock');
		if (!el) { return; }
		var d = new Date();
		var h = d.getHours(), m = d.getMinutes();
		var ampm = h >= 12 ? 'PM' : 'AM';
		var h12 = h % 12; if (h12 === 0) { h12 = 12; }
		var mm = (m < 10 ? '0' : '') + m;
		el.textContent = HARI[d.getDay()] + ', ' + ('0' + d.getDate()).slice(-2) + ' ' +
			BULAN[d.getMonth()] + ' ' + d.getFullYear() + ' pukul ' + h12 + '.' + mm + ' ' + ampm;
	}
	tick();
	setInterval(tick, 15000);
})();

window.addEventListener('load', function () {
	var DG = window.DG;

	// --- Palet emas bertingkat untuk deret bar ---
	function goldScale(n) {
		var stops = [DG.gold, '#DDB646', DG.goldDark, '#B78B24', DG.goldDeep];
		var out = [];
		for (var i = 0; i < n; i++) { out.push(stops[Math.min(i, stops.length - 1)]); }
		return out;
	}

	// ---------------- Chart.js ----------------
	if (typeof Chart !== 'undefined') {
		Chart.defaults.global.defaultFontColor = '#8a8a95';
		Chart.defaults.global.defaultFontFamily = 'Nunito, sans-serif';

		var hBarOpts = {
			maintainAspectRatio: false,
			legend: { display: false },
			scales: {
				xAxes: [{ ticks: { beginAtZero: true }, gridLines: { color: '#f0f0f4', drawBorder: false } }],
				yAxes: [{ gridLines: { display: false, drawBorder: false } }]
			}
		};
		var vBarOpts = {
			maintainAspectRatio: false,
			legend: { display: false },
			scales: {
				xAxes: [{ gridLines: { display: false, drawBorder: false } }],
				yAxes: [{ ticks: { beginAtZero: true }, gridLines: { color: '#f0f0f4', drawBorder: false } }]
			}
		};

		// Status (doughnut)
		new Chart(document.getElementById('chStatus'), {
			type: 'doughnut',
			data: { labels: DG.status.labels,
				datasets: [{ data: DG.status.data, backgroundColor: [DG.gold, DG.goldLight, DG.goldDeep], borderWidth: 0 }] },
			options: {
				maintainAspectRatio: false, cutoutPercentage: 72,
				legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } }
			}
		});
		// Angka total di tengah donut
		(function () {
			var box = document.getElementById('chStatus').parentNode;
			var c = document.createElement('div');
			c.style.cssText = 'position:absolute;top:44%;left:0;right:0;text-align:center;pointer-events:none;transform:translateY(-50%);';
			var totalTxt = (DG.status.total === null) ? '-' : DG.status.total.toLocaleString('id-ID');
			c.innerHTML = '<div class="dg-legend-total">' + totalTxt +
				'<small>Lembaga Vokasi</small></div>';
			box.appendChild(c);
		})();

		// Akreditasi (horizontal bar)
		new Chart(document.getElementById('chAkreditasi'), {
			type: 'horizontalBar',
			data: { labels: DG.akreditasi.labels,
				datasets: [{ data: DG.akreditasi.data, backgroundColor: goldScale(DG.akreditasi.data.length), borderRadius: 6 }] },
			options: hBarOpts
		});

		// Bentuk (horizontal bar)
		new Chart(document.getElementById('chBentuk'), {
			type: 'horizontalBar',
			data: { labels: DG.bentuk.labels,
				datasets: [{ data: DG.bentuk.data, backgroundColor: goldScale(DG.bentuk.data.length), borderRadius: 6 }] },
			options: hBarOpts
		});

		// Provinsi Top 5 (vertical bar)
		new Chart(document.getElementById('chProvinsi'), {
			type: 'bar',
			data: { labels: DG.provinsi.labels,
				datasets: [{ data: DG.provinsi.data, backgroundColor: DG.gold, borderRadius: 6 }] },
			options: vBarOpts
		});

		// Jenis (vertical bar)
		new Chart(document.getElementById('chJenis'), {
			type: 'bar',
			data: { labels: DG.jenis.labels,
				datasets: [{ data: DG.jenis.data, backgroundColor: DG.gold, borderRadius: 6 }] },
			options: vBarOpts
		});

		// Sektor Top 5 (horizontal bar)
		new Chart(document.getElementById('chSektor'), {
			type: 'horizontalBar',
			data: { labels: DG.sektor.labels,
				datasets: [{ data: DG.sektor.data, backgroundColor: goldScale(DG.sektor.data.length), borderRadius: 6 }] },
			options: hBarOpts
		});
	}

	// ---------------- Leaflet: choropleth provinsi (tanpa basemap) ----------------
	if (typeof L !== 'undefined' && document.getElementById('dgMap')) {
		var map = L.map('dgMap', {
			scrollWheelZoom: false, attributionControl: false, zoomControl: true,
			zoomSnap: 0.1, zoomDelta: 0.5   // izinkan zoom pecahan → fitBounds mengisi penuh
		}).setView([-2.5, 118.0], 4.3);

		// Normalisasi nama provinsi GeoJSON -> key (sama dgn key server).
		function keyOf(s) { return String(s).toLowerCase().replace(/[^a-z0-9]/g, ''); }

		var vals = DG.choropleth || {};
		var maxV = 1;
		Object.keys(vals).forEach(function (k) { if (vals[k] > maxV) { maxV = vals[k]; } });

		// Skala warna emas: 0/kosong = pucat; makin banyak = makin pekat (skala akar).
		function fillColor(v) {
			if (!v) { return '#e9edf3'; }
			var t = Math.sqrt(v) / Math.sqrt(maxV);
			var a = [245, 224, 150], b = [150, 100, 20]; // light gold -> deep gold
			var r = Math.round(a[0] + (b[0] - a[0]) * t);
			var g = Math.round(a[1] + (b[1] - a[1]) * t);
			var bl = Math.round(a[2] + (b[2] - a[2]) * t);
			return 'rgb(' + r + ',' + g + ',' + bl + ')';
		}

		fetch(DG.geojsonUrl).then(function (r) { return r.json(); }).then(function (gj) {
			var layer = L.geoJSON(gj, {
				style: function (f) {
					var v = vals[keyOf(f.properties.state)] || 0;
					return { fillColor: fillColor(v), weight: 1, color: '#ffffff', fillOpacity: .9 };
				},
				onEachFeature: function (f, lyr) {
					var v = vals[keyOf(f.properties.state)] || 0;
					lyr.bindTooltip('<b>' + f.properties.state + '</b><br>' +
						v.toLocaleString('id-ID') + ' lembaga terverifikasi' +
						'<br><span style="color:#8a6d1a">klik untuk buka peta sebaran provinsi ini</span>', { sticky: true });
					lyr.on({
						mouseover: function (e) { e.target.setStyle({ weight: 2, color: '#8a6d1a', fillOpacity: 1 }); },
						mouseout:  function (e) { layer.resetStyle(e.target); },
						// Klik provinsi -> buka halaman Peta Interaktif di tab baru,
						// terfilter + ter-zoom ke provinsi yang diklik (bbox polygon).
						click: function (e) {
							var b = e.target.getBounds();
							var bbox = [b.getSouth(), b.getWest(), b.getNorth(), b.getEast()]
								.map(function (n) { return n.toFixed(4); }).join(',');
							var url = DG.petaUrl + '?provinsi_nama=' + encodeURIComponent(f.properties.state) +
								'&bbox=' + bbox;
							window.open(url, '_blank', 'noopener');
						}
					});
				}
			}).addTo(map);

			function fitMap() { map.invalidateSize(); map.fitBounds(layer.getBounds(), { padding: [4, 4] }); }
			fitMap();

			// Re-fit saat viewport/kontainer berubah (rotate HP, resize, toggle sidebar).
			var rt;
			window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(fitMap, 200); });
			var sbToggle = document.getElementById('sidebarToggle');
			if (sbToggle) { sbToggle.addEventListener('click', function () { setTimeout(fitMap, 300); }); }
		}).catch(function () {
			document.getElementById('dgMap').innerHTML =
				'<div class="text-muted small p-3">Peta gagal dimuat (GeoJSON tidak tersedia).</div>';
		});
	}
});
</script>
