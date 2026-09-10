<?php
/**
 * Monitoring Pelatihan PMI — KPI + chart peserta pelatihan PMI.
 *
 * Seluruh angka dari DB: view dashboard_pelatihan_detail (Vokasi_repo::pelatihanStats).
 * Tata letak & palet mengikuti dashboard utama (dg-card, tema emas config/tema.php).
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
$kVer   = isset($kpi['terverifikasi'])  ? $kpi['terverifikasi']  : array('nilai' => NULL, 'persen' => NULL);
$kSer   = isset($kpi['tersertifikasi']) ? $kpi['tersertifikasi'] : array('nilai' => NULL, 'persen' => NULL);

$kpis = array(
	array('Total Peserta',          $kTotal,       NULL,           'fa-users',            'Seluruh pendaftaran pelatihan PMI yang tercatat.'),
	array('Peserta Terverifikasi',  $kVer['nilai'], $kVer['persen'], 'fa-user-check',       'Peserta yang telah lulus verifikasi direktorat.'),
	array('Peserta Tersertifikasi', $kSer['nilai'], $kSer['persen'], 'fa-certificate',      'Peserta yang telah menyelesaikan pelatihan dan bersertifikat.'),
);

// Pisah label/nilai untuk Chart.js. Negara: Top-10 + "Lainnya" agar sumbu tidak penuh.
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

list($negaraLabels, $negaraData) = $split($topN(isset($st['negara']) ? $st['negara'] : array(), 10));
list($sektorLabels, $sektorData) = $split(isset($st['sektor']) ? $st['sektor'] : array());
list($genderLabels, $genderData) = $split(isset($st['gender']) ? $st['gender'] : array());

// Status: label Indonesia yang ramah dibaca (nilai mentah: pending/accepted/rejected/revised).
$statusNama = array(
	'pending'  => 'Menunggu',
	'accepted' => 'Diterima',
	'rejected' => 'Ditolak',
	'revised'  => 'Revisi',
);
$statusRows = array();
foreach ((isset($st['status']) ? $st['status'] : array()) as $r)
{
	$k = strtolower($r['label']);
	$statusRows[] = array('label' => isset($statusNama[$k]) ? $statusNama[$k] : ucfirst($r['label']), 'value' => $r['value']);
}
list($statusLabels, $statusData) = $split($statusRows);

$trenRows   = isset($st['tren']) ? $st['tren'] : array();
$trenLabels = array_map(function ($x) { return $x['nama']; }, $trenRows);
$trenData   = array_map(function ($x) { return (int) $x['value']; }, $trenRows);

$isEmpty = ($kTotal === NULL || $kTotal === 0);
?>

<style>
	.dg-wrap { background: #f7f7fb; }
	.dg-card { border: 0; border-radius: 1rem; box-shadow: 0 6px 22px rgba(0,0,0,.05); }
	.dg-card .card-body { padding: 1.25rem 1.4rem; }
	.dg-title { font-weight: 800; color: #2b2b33; letter-spacing: -.01em; }
	.dg-sub { color: #9a9aa6; font-size: .78rem; margin-bottom: 1rem; }

	/* KPI */
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
	.chart-box.h-sm  { height: 210px; }
	.chart-box.h-md  { height: 260px; }
	.chart-box.h-lg  { height: 320px; }
	.chart-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #b7b9cc; font-size: .8rem; }

	@media (max-width: 575.98px) {
		.dg-card .card-body { padding: 1rem 1.05rem; }
		.dg-kpi .kpi-num { font-size: 1.6rem; }
		.dg-legend-total { font-size: 1.6rem; }
		.chart-box.h-md { height: 240px; }
		.chart-box.h-lg { height: 260px; }
	}
</style>

<!-- Begin Page Content -->
<div class="container-fluid dg-wrap py-2">

	<div class="d-sm-flex align-items-center justify-content-between mb-3">
		<div>
			<h1 class="h4 dg-title mb-1">Monitoring Pelatihan PMI</h1>
			<div class="text-muted small">Ringkasan peserta pelatihan Pekerja Migran Indonesia</div>
		</div>
	</div>

	<?php if ($isEmpty): ?>
	<div class="alert alert-warning small">
		<i class="fas fa-info-circle mr-1"></i> Belum ada data peserta pelatihan pada view.
	</div>
	<?php endif; ?>

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

	<!-- ===== BARIS 2: NEGARA TUJUAN + STATUS ===== -->
	<div class="row">
		<div class="col-lg-7 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Negara Tujuan</div>
					<div class="dg-sub">Jumlah peserta per negara tujuan penempatan<?= (isset($st['negara']) && count($st['negara']) > 10) ? ' (10 terbanyak, sisanya digabung "Lainnya")' : '' ?>.</div>
					<div class="chart-box h-lg"><canvas id="chNegara"></canvas></div>
				</div>
			</div>
		</div>
		<div class="col-lg-5 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Status</div>
					<div class="dg-sub">Komposisi status pendaftaran pelatihan peserta.</div>
					<div class="chart-box h-lg"><canvas id="chStatus"></canvas></div>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== BARIS 3: SEKTOR + JENIS KELAMIN ===== -->
	<div class="row">
		<div class="col-lg-7 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Sektor</div>
					<div class="dg-sub">Jumlah peserta per sektor pelatihan yang diikuti.</div>
					<div class="chart-box h-md"><canvas id="chSektor"></canvas></div>
				</div>
			</div>
		</div>
		<div class="col-lg-5 mb-4">
			<div class="card dg-card h-100">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Peserta berdasarkan Jenis Kelamin</div>
					<div class="dg-sub">Komposisi peserta laki-laki dan perempuan.</div>
					<div class="chart-box h-md"><canvas id="chGender"></canvas></div>
				</div>
			</div>
		</div>
	</div>

	<!-- ===== BARIS 4: TREN BULANAN ===== -->
	<div class="row">
		<div class="col-12 mb-4">
			<div class="card dg-card">
				<div class="card-body">
					<div class="dg-title h6 mb-1">Tren Peserta per Bulan</div>
					<div class="dg-sub">Jumlah pendaftaran pelatihan baru tiap bulan (berdasarkan tanggal dibuat).</div>
					<div class="chart-box h-md"><canvas id="chTren"></canvas></div>
				</div>
			</div>
		</div>
	</div>

</div>
<!-- /.container-fluid -->

<script>
window.DP = {
	gold: '<?= $gold ?>', goldDark: '<?= $goldDark ?>', goldDeep: '<?= $goldDeep ?>', goldLight: '<?= $goldLight ?>',
	total:  <?= $kTotal === NULL ? 'null' : (int) $kTotal ?>,
	negara: { labels: <?= json_encode($negaraLabels) ?>, data: <?= json_encode($negaraData) ?> },
	status: { labels: <?= json_encode($statusLabels) ?>, data: <?= json_encode($statusData) ?> },
	sektor: { labels: <?= json_encode($sektorLabels) ?>, data: <?= json_encode($sektorData) ?> },
	gender: { labels: <?= json_encode($genderLabels) ?>, data: <?= json_encode($genderData) ?> },
	tren:   { labels: <?= json_encode($trenLabels) ?>, data: <?= json_encode($trenData) ?> }
};

window.addEventListener('load', function () {
	if (typeof Chart === 'undefined') { return; }
	var DP = window.DP;

	Chart.defaults.global.defaultFontColor = '#8a8a95';
	Chart.defaults.global.defaultFontFamily = 'Nunito, sans-serif';

	// Palet emas bertingkat (urutan tetap) untuk deret bar/donut.
	var STOPS = [DP.gold, '#DDB646', DP.goldDark, '#B78B24', DP.goldDeep, '#8C6A18', DP.goldLight];
	function goldScale(n) {
		var out = [];
		for (var i = 0; i < n; i++) { out.push(STOPS[Math.min(i, STOPS.length - 1)]); }
		return out;
	}
	function fmt(n) { return Number(n).toLocaleString('id-ID'); }

	// Tooltip: "label: nilai peserta (xx%)"
	var tipPct = {
		callbacks: {
			label: function (item, data) {
				var ds = data.datasets[item.datasetIndex];
				var v = ds.data[item.index];
				var sum = ds.data.reduce(function (a, b) { return a + b; }, 0) || 1;
				var lbl = data.labels[item.index] || '';
				return ' ' + lbl + ': ' + fmt(v) + ' peserta (' + Math.round(v / sum * 100) + '%)';
			}
		}
	};

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
		tooltips: tipPct
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

	// 1. Negara tujuan (horizontal bar)
	if (DP.negara.data.length) {
		new Chart(document.getElementById('chNegara'), {
			type: 'horizontalBar',
			data: { labels: DP.negara.labels,
				datasets: [{ data: DP.negara.data, backgroundColor: DP.gold, hoverBackgroundColor: DP.goldDark, borderRadius: 4, barPercentage: .7 }] },
			options: hBarOpts
		});
	} else { emptyNote('chNegara'); }

	// 2. Status (doughnut) + total di tengah
	if (DP.status.data.length) {
		new Chart(document.getElementById('chStatus'), {
			type: 'doughnut',
			data: { labels: DP.status.labels,
				datasets: [{ data: DP.status.data, backgroundColor: goldScale(DP.status.data.length), borderWidth: 2, borderColor: '#fff' }] },
			options: donutOpts
		});
		centerTotal('chStatus', DP.total, 'Peserta');
	} else { emptyNote('chStatus'); }

	// 3. Sektor (horizontal bar)
	if (DP.sektor.data.length) {
		new Chart(document.getElementById('chSektor'), {
			type: 'horizontalBar',
			data: { labels: DP.sektor.labels,
				datasets: [{ data: DP.sektor.data, backgroundColor: DP.gold, hoverBackgroundColor: DP.goldDark, borderRadius: 4, barPercentage: .7 }] },
			options: hBarOpts
		});
	} else { emptyNote('chSektor'); }

	// 4. Jenis kelamin (doughnut)
	if (DP.gender.data.length) {
		new Chart(document.getElementById('chGender'), {
			type: 'doughnut',
			data: { labels: DP.gender.labels,
				datasets: [{ data: DP.gender.data, backgroundColor: [DP.goldDark, DP.goldLight, DP.goldDeep], borderWidth: 2, borderColor: '#fff' }] },
			options: donutOpts
		});
		centerTotal('chGender', DP.total, 'Peserta');
	} else { emptyNote('chGender'); }

	// 5. Tren bulanan (line)
	if (DP.tren.data.length) {
		new Chart(document.getElementById('chTren'), {
			type: 'line',
			data: { labels: DP.tren.labels,
				datasets: [{
					label: 'Peserta', data: DP.tren.data,
					borderColor: DP.goldDark, backgroundColor: 'rgba(' + getComputedStyle(document.documentElement).getPropertyValue('--dg-gold-rgb').trim() + ', .18)',
					borderWidth: 2, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#fff', pointBorderColor: DP.goldDeep, pointBorderWidth: 2,
					lineTension: .3, fill: true
				}] },
			options: {
				maintainAspectRatio: false,
				legend: { display: false },
				tooltips: { mode: 'index', intersect: false,
					callbacks: { label: function (item) { return ' ' + fmt(item.yLabel) + ' peserta'; } } },
				hover: { mode: 'index', intersect: false },
				scales: {
					xAxes: [{ gridLines: { display: false, drawBorder: false } }],
					yAxes: [{ ticks: { beginAtZero: true, precision: 0, maxTicksLimit: 6 }, gridLines: { color: '#f0f0f4', drawBorder: false } }]
				}
			}
		});
	} else { emptyNote('chTren'); }
});
</script>
