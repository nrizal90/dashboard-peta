<?php
/**
 * Menu "Dashboard Vokasi" — replika ringkasan pendataan.
 *
 * Sumber angka:
 *   - DB (real)   : total lembaga, provinsi tercakup, e-Vokasi layer, per provinsi,
 *                   per jenis, ownership.  → dari $stats (Vokasi_repo::pendataanStats)
 *   - HARDCODE    : Kerja Sama, Target 40.000, Kategori K/L, Total LSP.
 *                   Angka menyamai dashboard sumber (dashboard-vokasi.kisut.workers.dev)
 *                   karena belum tersedia di view DB. Lihat dokumen
 *                   dokumen/ANALISIS_REPLIKASI_DASHBOARD_VOKASI.md.
 */
$st = isset($stats) ? $stats : array();
$total       = isset($st['total']) ? (int) $st['total'] : 0;
$provCakup   = isset($st['provinsi_tercakup']) ? (int) $st['provinsi_tercakup'] : 0;
$ev          = isset($st['evokasi']) ? $st['evokasi'] : array('belum'=>0,'layer1'=>0,'layer2'=>0,'layer3'=>0);
$evMasuk     = isset($st['evokasi_masuk']) ? (int) $st['evokasi_masuk'] : 0;
$evBelum     = isset($st['evokasi_belum']) ? (int) $st['evokasi_belum'] : 0;
$own         = isset($st['ownership']) ? $st['ownership'] : array('Pemerintah'=>0,'Non Pemerintah'=>0);
$perProv     = isset($st['per_provinsi']) ? $st['per_provinsi'] : array();
$perJenis    = isset($st['per_jenis']) ? $st['per_jenis'] : array();
$isDb        = ! empty($st['is_db']);

$pct = function ($n, $d) { return $d > 0 ? round($n / $d * 100) : 0; };
$evMasukPct = $pct($evMasuk, $total);
$evBelumPct = $pct($evBelum, $total);

// --- Angka HARDCODE (belum ada di DB) — samakan dg dashboard sumber ---
$hc = array(
	'ks_total'   => 5,  'ks_selesai' => 3, 'ks_proses' => 2,
	'ks_migrant' => 4,  'ks_nonmig'  => 1, 'ks_luar'   => 0,
	'lsp_total'  => 622,
	'target_sesuai' => 1366, 'target_luar' => 725,
	'kat_kementerian' => 286, 'kat_nonkementerian' => 924,
	'kat_pemda' => 182, 'kat_migrant' => 17,
);
$badgeContoh = '<span class="badge badge-warning ml-1" title="Angka contoh — belum tersedia di database" style="font-size:.6rem;vertical-align:middle;">contoh</span>';
?>
<div class="container-fluid">

	<!-- Heading -->
	<div class="d-sm-flex align-items-center justify-content-between mb-1">
		<div>
			<h1 class="h3 mb-0 text-gray-800">Dashboard Lembaga Vokasi</h1>
			<p class="mb-0 text-muted small">Gambaran data lembaga vokasi dan kerja sama.</p>
		</div>
		<div>
			<span class="badge badge-<?= $isDb ? 'success' : 'secondary' ?> p-2">
				<i class="fas fa-database"></i> Sumber: <?= $isDb ? 'Database (live)' : 'JSON (fallback)' ?>
			</span>
		</div>
	</div>

	<div class="alert alert-info py-2 small mb-3">
		<i class="fas fa-info-circle"></i>
		Angka <strong>Total lembaga, Provinsi, e-Vokasi, per Provinsi, per Jenis, Pemerintah/Non</strong>
		diambil <strong>langsung dari database</strong>. Bagian <strong>Kerja Sama, Target 40.000,
		Kategori K/L, dan Total LSP</strong> masih <strong>angka contoh</strong> (belum tersedia di DB) —
		ditandai <?= $badgeContoh ?>.
	</div>

	<!-- Filter bar (visual — belum interaktif) -->
	<div class="card shadow mb-3">
		<div class="card-body py-2">
			<div class="d-flex flex-wrap align-items-end" style="gap:.5rem;">
				<div class="flex-grow-1" style="min-width:180px;">
					<label class="head text-muted mb-1" style="font-size:.65rem;letter-spacing:.05em;">CARI</label>
					<input type="text" class="form-control form-control-sm" placeholder="Cari lembaga, provinsi, jenis, email..." disabled>
				</div>
				<?php foreach (array('Provinsi','Pemerintah/Non','Jenis Lembaga','Sektor','Jabatan') as $f): ?>
				<div style="min-width:140px;">
					<label class="head text-muted mb-1" style="font-size:.65rem;letter-spacing:.05em;"><?= strtoupper($f) ?></label>
					<select class="form-control form-control-sm" disabled><option>Semua <?= html_escape($f) ?></option></select>
				</div>
				<?php endforeach; ?>
				<span class="badge badge-light border ml-1" title="Filter interaktif menyusul">segera</span>
			</div>
		</div>
	</div>

	<!-- KPI cards -->
	<div class="row">
		<?php
		$kpis = array(
			array('Total lembaga', number_format($total,0,',','.'), 'Berdasarkan data DB', 'primary', 'fa-building', false),
			array('Provinsi tercakup', $provCakup, 'Jumlah provinsi dari data', 'success', 'fa-map-marked-alt', false),
			array('Masuk e-Vokasi', $evMasukPct.'%', number_format($evMasuk,0,',','.').' lembaga (Layer 1–3)', 'info', 'fa-layer-group', false),
			array('Kerja sama selesai', '60%', $hc['ks_selesai'].' selesai dari '.$hc['ks_total'].' total', 'warning', 'fa-handshake', true),
			array('Total LSP', number_format($hc['lsp_total'],0,',','.'), '983 relasi · 8 sektor · 30 provinsi', 'secondary', 'fa-certificate', true),
		);
		foreach ($kpis as $k): ?>
		<div class="col-xl col-md-4 col-sm-6 mb-4">
			<div class="card border-left-<?= $k[3] ?> shadow h-100 py-2">
				<div class="card-body py-2">
					<div class="row no-gutters align-items-center">
						<div class="col mr-2">
							<div class="text-xs font-weight-bold text-<?= $k[3] ?> text-uppercase mb-1">
								<?= html_escape($k[0]) ?><?= $k[5] ? $badgeContoh : '' ?>
							</div>
							<div class="h5 mb-0 font-weight-bold text-gray-800"><?= $k[1] ?></div>
							<div class="text-muted" style="font-size:.7rem;"><?= html_escape($k[2]) ?></div>
						</div>
						<div class="col-auto"><i class="fas <?= $k[4] ?> fa-2x text-gray-300"></i></div>
					</div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Ringkasan Pendataan -->
	<h5 class="text-gray-800 mb-2">Ringkasan Pendataan</h5>
	<div class="row">
		<?php
		$mini = array(
			array('Total lembaga', number_format($total,0,',','.'), 'Semua lembaga di DB', false),
			array('Masuk e-Vokasi', number_format($evMasuk,0,',','.'), $evMasukPct.'% · Layer 1/2/3', false),
			array('Belum e-Vokasi', number_format($evBelum,0,',','.'), $evBelumPct.'% belum masuk', false),
			array('Sesuai Target 40.000', number_format($hc['target_sesuai'],0,',','.'), '65% sesuai target', true),
			array('Di luar Target 40.000', number_format($hc['target_luar'],0,',','.'), '35% di luar target', true),
			array('Kementerian', number_format($hc['kat_kementerian'],0,',','.'), '14%', true),
			array('Non Kementerian', number_format($hc['kat_nonkementerian'],0,',','.'), '44%', true),
			array('Pemerintah Daerah', number_format($hc['kat_pemda'],0,',','.'), '9%', true),
			array('Migrant Center', number_format($hc['kat_migrant'],0,',','.'), '1%', true),
		);
		foreach ($mini as $m): ?>
		<div class="col-lg-3 col-md-4 col-sm-6 mb-3">
			<div class="card shadow-sm h-100">
				<div class="card-body py-2">
					<div class="text-xs text-uppercase text-muted font-weight-bold mb-1"><?= html_escape($m[0]) ?><?= $m[3] ? $badgeContoh : '' ?></div>
					<div class="h5 mb-0 font-weight-bold text-gray-800"><?= $m[1] ?></div>
					<div class="text-muted" style="font-size:.7rem;"><?= html_escape($m[2]) ?></div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Analisis Pendataan -->
	<h5 class="text-gray-800 mb-2 mt-2">Analisis Pendataan</h5>
	<div class="row">
		<div class="col-lg-6 mb-4">
			<div class="card shadow h-100">
				<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Status e-Vokasi</h6></div>
				<div class="card-body"><div style="height:260px;"><canvas id="chEvokasi"></canvas></div></div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card shadow h-100">
				<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Lembaga per Kategori <?= $badgeContoh ?></h6></div>
				<div class="card-body"><div style="height:260px;"><canvas id="chKategori"></canvas></div></div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card shadow h-100">
				<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Lembaga per Jenis</h6></div>
				<div class="card-body"><div style="height:340px;"><canvas id="chJenis"></canvas></div></div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card shadow h-100">
				<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Pemerintah vs Non Pemerintah</h6></div>
				<div class="card-body"><div style="height:340px;"><canvas id="chOwn"></canvas></div></div>
			</div>
		</div>
	</div>

	<!-- Lembaga per Provinsi + ranking -->
	<div class="row">
		<div class="col-lg-7 mb-4">
			<div class="card shadow h-100">
				<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Lembaga per Provinsi (Top 15)</h6></div>
				<div class="card-body"><div style="height:420px;"><canvas id="chProvinsi"></canvas></div></div>
			</div>
		</div>
		<div class="col-lg-5 mb-4">
			<div class="card shadow h-100">
				<div class="card-header py-2 d-flex justify-content-between">
					<h6 class="m-0 font-weight-bold text-primary">Peringkat Provinsi</h6>
					<span class="text-muted small"><?= $provCakup ?> provinsi · <?= number_format($total,0,',','.') ?> lembaga</span>
				</div>
				<div class="card-body p-0" style="max-height:420px;overflow-y:auto;">
					<table class="table table-sm table-hover mb-0">
						<tbody>
							<?php foreach ($perProv as $i => $p): ?>
							<tr>
								<td class="text-muted" style="width:2.2rem;"><?= $i+1 ?></td>
								<td><?= html_escape($p['label']) ?></td>
								<td class="text-right font-weight-bold"><?= number_format($p['value'],0,',','.') ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<!-- Ringkasan Kerja Sama (HARDCODE) -->
	<h5 class="text-gray-800 mb-2 mt-2">Ringkasan Kerja Sama <?= $badgeContoh ?></h5>
	<div class="row">
		<?php
		$ks = array(
			array('Total kerja sama', $hc['ks_total'], 'Semua kerja sama aktif', 'primary'),
			array('Migrant Center', $hc['ks_migrant'], '80%', 'info'),
			array('Non-Migrant Center', $hc['ks_nonmig'], '20%', 'secondary'),
			array('Kerja Sama Luar Negeri', $hc['ks_luar'], '0%', 'warning'),
			array('Dalam proses', $hc['ks_proses'], '40%', 'warning'),
			array('Selesai', $hc['ks_selesai'], '60%', 'success'),
		);
		foreach ($ks as $k): ?>
		<div class="col-lg-2 col-md-4 col-6 mb-3">
			<div class="card border-left-<?= $k[3] ?> shadow-sm h-100">
				<div class="card-body py-2">
					<div class="text-xs text-uppercase text-muted font-weight-bold mb-1"><?= html_escape($k[0]) ?></div>
					<div class="h5 mb-0 font-weight-bold text-gray-800"><?= $k[1] ?></div>
					<div class="text-muted" style="font-size:.7rem;"><?= html_escape($k[2]) ?></div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<div class="row">
		<div class="col-lg-6 mb-4">
			<div class="card shadow h-100">
				<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Tipe Kerja Sama <?= $badgeContoh ?></h6></div>
				<div class="card-body"><div style="height:240px;"><canvas id="chKsTipe"></canvas></div></div>
			</div>
		</div>
		<div class="col-lg-6 mb-4">
			<div class="card shadow h-100">
				<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Progress Kerja Sama <?= $badgeContoh ?></h6></div>
				<div class="card-body"><div style="height:240px;"><canvas id="chKsProgress"></canvas></div></div>
			</div>
		</div>
	</div>

	<p class="text-muted small">
		<i class="fas fa-exclamation-triangle"></i>
		Bagian bertanda <?= $badgeContoh ?> memakai angka contoh menyamai dashboard sumber karena
		datanya belum ada di database (lihat <code>dokumen/ANALISIS_REPLIKASI_DASHBOARD_VOKASI.md</code>).
	</p>
</div>

<script>
// Data DB dikirim ke JS (aman, tanpa Chart). Chart dibangun saat 'load' (Chart.js dimuat di footer).
window.PD = {
	evokasi: <?= json_encode(array($ev['belum'],$ev['layer1'],$ev['layer2'],$ev['layer3'])) ?>,
	kategori: <?= json_encode(array($hc['kat_nonkementerian'],$hc['kat_kementerian'],$hc['kat_pemda'],$hc['kat_migrant'])) ?>,
	jenis: <?= json_encode($perJenis) ?>,
	ownership: <?= json_encode(array((int)$own['Pemerintah'],(int)$own['Non Pemerintah'])) ?>,
	provinsi: <?= json_encode(array_slice($perProv, 0, 15)) ?>,
	ksTipe: <?= json_encode(array($hc['ks_migrant'],$hc['ks_nonmig'])) ?>,
	ksProgress: <?= json_encode(array($hc['ks_selesai'],$hc['ks_proses'])) ?>
};

window.addEventListener('load', function () {
	if (typeof Chart === 'undefined') { return; }
	var C = { primary:'#4e73df', success:'#1cc88a', info:'#36b9cc', warning:'#f6c23e', danger:'#e74a3b', secondary:'#858796', light:'#dddfeb' };
	var pd = window.PD;
	var labelsFrom = function (arr) { return arr.map(function (x) { return x.label; }); };
	var valuesFrom = function (arr) { return arr.map(function (x) { return x.value; }); };

	// Status e-Vokasi (doughnut)
	new Chart(document.getElementById('chEvokasi'), {
		type: 'doughnut',
		data: { labels: ['Belum e-Vokasi','Layer 1 (legalitas)','Layer 2 (fasilitas)','Layer 3 (program)'],
			datasets: [{ data: pd.evokasi, backgroundColor: [C.light, C.info, C.warning, C.success] }] },
		options: { maintainAspectRatio:false, legend:{ position:'bottom' } }
	});

	// Lembaga per Kategori (bar, hardcode)
	new Chart(document.getElementById('chKategori'), {
		type: 'bar',
		data: { labels: ['Non Kementerian','Kementerian','Pemerintah Daerah','Migrant Center'],
			datasets: [{ data: pd.kategori, backgroundColor: C.primary }] },
		options: { maintainAspectRatio:false, legend:{ display:false }, scales:{ yAxes:[{ ticks:{ beginAtZero:true } }] } }
	});

	// Lembaga per Jenis (horizontal bar, DB)
	new Chart(document.getElementById('chJenis'), {
		type: 'horizontalBar',
		data: { labels: labelsFrom(pd.jenis), datasets: [{ data: valuesFrom(pd.jenis), backgroundColor: C.info }] },
		options: { maintainAspectRatio:false, legend:{ display:false }, scales:{ xAxes:[{ ticks:{ beginAtZero:true } }] } }
	});

	// Ownership (doughnut, DB)
	new Chart(document.getElementById('chOwn'), {
		type: 'doughnut',
		data: { labels: ['Pemerintah','Non Pemerintah'], datasets: [{ data: pd.ownership, backgroundColor: [C.primary, C.warning] }] },
		options: { maintainAspectRatio:false, legend:{ position:'bottom' } }
	});

	// Lembaga per Provinsi Top 15 (horizontal bar, DB)
	new Chart(document.getElementById('chProvinsi'), {
		type: 'horizontalBar',
		data: { labels: labelsFrom(pd.provinsi), datasets: [{ data: valuesFrom(pd.provinsi), backgroundColor: C.success }] },
		options: { maintainAspectRatio:false, legend:{ display:false }, scales:{ xAxes:[{ ticks:{ beginAtZero:true } }] } }
	});

	// Tipe Kerja Sama (doughnut, hardcode)
	new Chart(document.getElementById('chKsTipe'), {
		type: 'doughnut',
		data: { labels: ['Migrant Center','Non-Migrant Center'], datasets: [{ data: pd.ksTipe, backgroundColor: [C.info, C.secondary] }] },
		options: { maintainAspectRatio:false, legend:{ position:'bottom' } }
	});

	// Progress Kerja Sama (bar, hardcode)
	new Chart(document.getElementById('chKsProgress'), {
		type: 'bar',
		data: { labels: ['Selesai','Dalam proses'], datasets: [{ data: pd.ksProgress, backgroundColor: [C.success, C.warning] }] },
		options: { maintainAspectRatio:false, legend:{ display:false }, scales:{ yAxes:[{ ticks:{ beginAtZero:true, stepSize:1 } }] } }
	});
});
</script>
