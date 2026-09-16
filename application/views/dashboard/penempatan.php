<?php
/**
 * Monitoring Penempatan Peserta Pelatihan — KPI + chart.
 *
 * Seluruh angka dari DB SISKO P2MI ($db['sisko']) via Vokasi_repo::penempatanStats().
 * Tata letak & palet mengikuti Monitoring Pelatihan (tema emas config/tema.php).
 */
$tema      = $this->config->item('tema');
$gold      = $tema['gold'];
$goldDark  = $tema['gold_dark'];
$goldDeep  = $tema['gold_deep'];
$goldLight = $tema['gold_light'];

$fmtNum = function ($v) { return ($v === NULL) ? '-' : number_format($v, 0, ',', '.'); };

$st  = isset($stats) ? $stats : array();
$kpi = isset($st['kpi']) ? $st['kpi'] : array();
$kTotal = isset($kpi['total']) ? (int) $kpi['total'] : NULL;
$get    = function ($k) use ($kpi) { return isset($kpi[$k]) ? $kpi[$k] : array('nilai' => NULL, 'persen' => NULL); };

// 3 KPI sesuai dokumen requirement.
$kpis = array(
	array('Punya Akun SiskoP2MI', $get('akun')['nilai'],       $get('akun')['persen'],       'fa-id-card',   'Peserta pelatihan yang telah memiliki akun SiskoP2MI.'),
	array('Punya Penempatan',     $get('penempatan')['nilai'], $get('penempatan')['persen'], 'fa-map-signs', 'Peserta yang telah memiliki penempatan (status penempatan aktif).'),
	array('Sudah E-KPMI',         $get('ekpmi')['nilai'],      $get('ekpmi')['persen'],      'fa-passport',  'Peserta dengan status E-KPMI.'),
);

// Pisah label/nilai + Top-N ("Lainnya" digabung) agar sumbu tidak penuh.
$split = function ($rows) {
	return array(
		array_map(function ($x) { return $x['label']; }, $rows),
		array_map(function ($x) { return (int) $x['value']; }, $rows),
	);
};
$topN = function ($rows, $n) {
	if (count($rows) <= $n) { return $rows; }
	$head = array_slice($rows, 0, $n);
	$rest = 0;
	foreach (array_slice($rows, $n) as $r) { $rest += (int) $r['value']; }
	$head[] = array('label' => 'Lainnya', 'value' => $rest);
	return $head;
};

list($provLabels, $provData)   = $split($topN(isset($st['provinsi'])      ? $st['provinsi']      : array(), 12));
list($kabLabels, $kabData)     = $split($topN(isset($st['kabupaten'])     ? $st['kabupaten']     : array(), 12));
list($penyLabels, $penyData)   = $split($topN(isset($st['penyelenggara']) ? $st['penyelenggara'] : array(), 10));
list($p3miLabels, $p3miData)   = $split($topN(isset($st['p3mi'])          ? $st['p3mi']          : array(), 10));
list($jabLabels, $jabData)     = $split($topN(isset($st['jabatan'])       ? $st['jabatan']       : array(), 10));
list($negLabels, $negData)     = $split($topN(isset($st['negara'])        ? $st['negara']        : array(), 10));
list($statLabels, $statData)   = $split(isset($st['status']) ? $st['status'] : array());
?>

<style>
	.dg-wrap { background: #f7f7fb; }
	.dg-card { border: 0; border-radius: 1rem; box-shadow: 0 6px 22px rgba(0,0,0,.05); }
	.dg-card .card-body { padding: 1.25rem 1.4rem; }
	.dg-title { font-weight: 800; color: #2b2b33; letter-spacing: -.01em; }
	.dg-sub { color: #9a9aa6; font-size: .78rem; margin-bottom: 1rem; }

	.dg-kpi .kpi-name { font-size: .82rem; color: #6b6b76; font-weight: 700; }
	.dg-kpi .kpi-num  { font-size: 2rem; font-weight: 800; color: #2b2b33; line-height: 1.1; }
	.dg-kpi .kpi-desc { font-size: .72rem; color: #a2a2ad; margin-top: .5rem; line-height: 1.35; }
	.dg-kpi .kpi-icon {
		width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
		background: var(--dg-gold-light); color: var(--dg-gold-deep); font-size: 1.1rem;
	}
	.dg-badge {
		background: var(--dg-gold); color: #6a5308; font-weight: 800; font-size: .7rem;
		padding: .18rem .5rem; border-radius: 999px;
	}
	.dg-legend-total { font-size: 1.9rem; font-weight: 800; color: #2b2b33; }
	.dg-legend-total small { display:block; font-size:.7rem; font-weight:600; color:#9a9aa6; }

	.chart-box { position: relative; }
	.chart-box.h-md  { height: 260px; }
	.chart-box.h-lg  { height: 340px; }
	.chart-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #b7b9cc; font-size: .8rem; }

	@media (max-width: 575.98px) {
		.dg-card .card-body { padding: 1rem 1.05rem; }
		.dg-kpi .kpi-num { font-size: 1.6rem; }
		.dg-legend-total { font-size: 1.6rem; }
		.chart-box.h-lg { height: 280px; }
	}
</style>

<!-- Begin Page Content -->
<div class="container-fluid dg-wrap py-2">

	<div class="d-sm-flex align-items-center justify-content-between mb-3">
		<div>
			<h1 class="h4 dg-title mb-1">Monitoring Penempatan Peserta Pelatihan</h1>
			<div class="text-muted small">Ringkasan penempatan peserta pelatihan (sumber: SISKO P2MI)</div>
		</div>
	</div>

	<!-- ===== BARIS 1: KPI ===== -->
	<div class="row">
		<?php foreach ($kpis as $k): ?>
		<div class="col-lg-4 col-md-6 col-12 mb-4">
			<div class="card dg-card dg-kpi h-100">
				<div class="card-body">
					<div class="d-flex justify-content-between align-items-start mb-2">
						<span class="kpi-name"><?= html_escape($k[0]) ?></span>
						<span class="kpi-icon"><i class="fas <?= $k[3] ?>"></i></span>
					</div>
					<div class="d-flex align-items-center justify-content-between">
						<span class="kpi-num"><?= $fmtNum($k[1]) ?></span>
						<?php if ($k[2] !== NULL): ?>
						<span class="dg-badge" title="Persentase terhadap total peserta"><?= (int) $k[2] ?>%</span>
						<?php endif; ?>
					</div>
					<div class="kpi-desc"><?= html_escape($k[4]) ?></div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- ===== BARIS 2: PROVINSI + KABUPATEN ===== -->
	<div class="row">
		<div class="col-lg-6 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Provinsi</div>
					<div class="dg-sub">Jumlah peserta per provinsi asal (12 terbanyak).</div>
					<div class="chart-box h-lg"><canvas id="chProv"></canvas></div>
				</div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Kabupaten/Kota</div>
					<div class="dg-sub">Jumlah peserta per kabupaten/kota asal (12 terbanyak).</div>
					<div class="chart-box h-lg"><canvas id="chKab"></canvas></div>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== BARIS 3: PENYELENGGARA + P3MI ===== -->
	<div class="row">
		<div class="col-lg-6 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Penyelenggara</div>
					<div class="dg-sub">Jumlah peserta per penyelenggara pelatihan (10 terbanyak).</div>
					<div class="chart-box h-md"><canvas id="chPeny"></canvas></div>
				</div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan P3MI</div>
					<div class="dg-sub">Jumlah peserta per P3MI penempatan (10 terbanyak).</div>
					<div class="chart-box h-md"><canvas id="chP3mi"></canvas></div>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== BARIS 4: JABATAN + NEGARA ===== -->
	<div class="row">
		<div class="col-lg-6 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Jabatan</div>
					<div class="dg-sub">Jumlah peserta per jabatan penempatan (10 terbanyak).</div>
					<div class="chart-box h-md"><canvas id="chJab"></canvas></div>
				</div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Negara</div>
					<div class="dg-sub">Jumlah peserta per negara tujuan penempatan (10 terbanyak).</div>
					<div class="chart-box h-md"><canvas id="chNeg"></canvas></div>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== BARIS 5: STATUS ===== -->
	<div class="row">
		<div class="col-lg-6 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Status</div>
					<div class="dg-sub">Komposisi status penempatan peserta.</div>
					<div class="chart-box h-lg"><canvas id="chStatus"></canvas></div>
				</div>
			</div>
		</div>
	</div>

</div>
<!-- /.container-fluid -->

<script>
window.DP2 = {
	gold: '<?= $gold ?>', goldDark: '<?= $goldDark ?>', goldDeep: '<?= $goldDeep ?>', goldLight: '<?= $goldLight ?>',
	total:  <?= $kTotal === NULL ? 'null' : (int) $kTotal ?>,
	prov:   { labels: <?= json_encode($provLabels) ?>, data: <?= json_encode($provData) ?> },
	kab:    { labels: <?= json_encode($kabLabels) ?>,  data: <?= json_encode($kabData) ?> },
	peny:   { labels: <?= json_encode($penyLabels) ?>, data: <?= json_encode($penyData) ?> },
	p3mi:   { labels: <?= json_encode($p3miLabels) ?>, data: <?= json_encode($p3miData) ?> },
	jab:    { labels: <?= json_encode($jabLabels) ?>,  data: <?= json_encode($jabData) ?> },
	neg:    { labels: <?= json_encode($negLabels) ?>,  data: <?= json_encode($negData) ?> },
	status: { labels: <?= json_encode($statLabels) ?>, data: <?= json_encode($statData) ?> }
};

window.addEventListener('load', function () {
	if (typeof Chart === 'undefined') { return; }
	var DP = window.DP2;

	Chart.defaults.global.defaultFontColor = '#8a8a95';
	Chart.defaults.global.defaultFontFamily = 'Nunito, sans-serif';

	var STOPS = [DP.gold, '#DDB646', DP.goldDark, '#B78B24', DP.goldDeep, '#8C6A18', DP.goldLight];
	function goldScale(n) {
		var out = [];
		for (var i = 0; i < n; i++) { out.push(STOPS[Math.min(i, STOPS.length - 1)]); }
		return out;
	}
	function fmt(n) { return Number(n).toLocaleString('id-ID'); }

	var hBarOpts = {
		maintainAspectRatio: false,
		legend: { display: false },
		tooltips: { callbacks: { label: function (item) { return ' ' + fmt(item.xLabel) + ' peserta'; } } },
		scales: {
			xAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: '#f0f0f4', drawBorder: false } }],
			yAxes: [{ gridLines: { display: false, drawBorder: false } }]
		}
	};
	var donutOpts = {
		maintainAspectRatio: false, cutoutPercentage: 70,
		legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } },
		tooltips: {
			callbacks: {
				label: function (item, data) {
					var ds = data.datasets[item.datasetIndex];
					var v = ds.data[item.index];
					var sum = ds.data.reduce(function (a, b) { return a + b; }, 0) || 1;
					var lbl = data.labels[item.index] || '';
					return ' ' + lbl + ': ' + fmt(v) + ' peserta (' + Math.round(v / sum * 100) + '%)';
				}
			}
		}
	};

	function emptyNote(id) {
		var box = document.getElementById(id).parentNode;
		var d = document.createElement('div');
		d.className = 'chart-empty';
		d.textContent = 'Tidak ada data';
		box.appendChild(d);
	}
	function centerTotal(id, total, caption) {
		var box = document.getElementById(id).parentNode;
		var c = document.createElement('div');
		c.style.cssText = 'position:absolute;top:42%;left:0;right:0;text-align:center;pointer-events:none;transform:translateY(-50%);';
		c.innerHTML = '<div class="dg-legend-total">' + (total === null ? '-' : fmt(total)) + '<small>' + caption + '</small></div>';
		box.appendChild(c);
	}

	// Bar horizontal generik untuk 6 dimensi.
	function hbar(id, series) {
		if (!series.data.length) { emptyNote(id); return; }
		new Chart(document.getElementById(id), {
			type: 'horizontalBar',
			data: { labels: series.labels,
				datasets: [{ data: series.data, backgroundColor: DP.gold, hoverBackgroundColor: DP.goldDark, borderRadius: 4, barPercentage: .7 }] },
			options: hBarOpts
		});
	}

	hbar('chProv', DP.prov);
	hbar('chKab',  DP.kab);
	hbar('chPeny', DP.peny);
	hbar('chP3mi', DP.p3mi);
	hbar('chJab',  DP.jab);
	hbar('chNeg',  DP.neg);

	// Status (doughnut) + total di tengah.
	if (DP.status.data.length) {
		new Chart(document.getElementById('chStatus'), {
			type: 'doughnut',
			data: { labels: DP.status.labels,
				datasets: [{ data: DP.status.data, backgroundColor: goldScale(DP.status.data.length), borderWidth: 2, borderColor: '#fff' }] },
			options: donutOpts
		});
		centerTotal('chStatus', DP.total, 'Peserta');
	} else { emptyNote('chStatus'); }
});
</script>
