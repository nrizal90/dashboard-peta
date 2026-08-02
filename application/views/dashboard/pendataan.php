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
$perSektor   = isset($st['per_sektor']) ? $st['per_sektor'] : array();
$sektorCount = isset($st['sektor_count']) ? (int) $st['sektor_count'] : 0;
$jabatanCount= isset($st['jabatan_count']) ? (int) $st['jabatan_count'] : 0;
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
		// [nama, nilai, subteks, warna, ikon, is_contoh, is_klik]
		$kpis = array(
			array('Total lembaga', number_format($total,0,',','.'), 'Klik untuk rincian', 'primary', 'fa-building', false, true),
			array('Provinsi tercakup', $provCakup, 'Jumlah provinsi dari data', 'success', 'fa-map-marked-alt', false, false),
			array('Masuk e-Vokasi', $evMasukPct.'%', number_format($evMasuk,0,',','.').' lembaga (Layer 1–3)', 'info', 'fa-layer-group', false, false),
			array('Kerja sama selesai', '60%', $hc['ks_selesai'].' selesai dari '.$hc['ks_total'].' total', 'warning', 'fa-handshake', true, false),
			array('Total LSP', number_format($hc['lsp_total'],0,',','.'), '983 relasi · 8 sektor · 30 provinsi', 'secondary', 'fa-certificate', true, false),
		);
		foreach ($kpis as $k):
			$klik = ! empty($k[6]);
		?>
		<div class="col-xl col-md-4 col-sm-6 mb-4">
			<div class="card border-left-<?= $k[3] ?> shadow h-100 py-2<?= $klik ? ' kpi-klik' : '' ?>"
				<?= $klik ? 'role="button" tabindex="0" data-toggle="modal" data-target="#modalRingkas" style="cursor:pointer;"' : '' ?>>
				<div class="card-body py-2">
					<div class="row no-gutters align-items-center">
						<div class="col mr-2">
							<div class="text-xs font-weight-bold text-<?= $k[3] ?> text-uppercase mb-1">
								<?= html_escape($k[0]) ?><?= $k[5] ? $badgeContoh : '' ?>
							</div>
							<div class="h5 mb-0 font-weight-bold text-gray-800"><?= $k[1] ?></div>
							<div class="text-muted" style="font-size:.7rem;"><?= $klik ? '<i class="fas fa-mouse-pointer"></i> ' : '' ?><?= html_escape($k[2]) ?></div>
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

<!-- Modal drill-down "Total lembaga" -->
<div class="modal fade" id="modalRingkas" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<div class="text-xs text-uppercase text-muted font-weight-bold">Ringkasan Pendataan</div>
					<h5 class="modal-title mb-0">Semua lembaga</h5>
					<div class="text-muted small"><?= number_format($total,0,',','.') ?> lembaga.</div>
				</div>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body">

				<!-- 4 angka utama -->
				<div class="row text-center mb-3">
					<?php
					$boxes = array(
						array('Lembaga', number_format($total,0,',','.'), 'fa-database'),
						array('Provinsi', $provCakup, 'fa-map-marker-alt'),
						array('Sektor', $sektorCount, 'fa-sitemap'),
						array('Jabatan', $jabatanCount, 'fa-layer-group'),
					);
					foreach ($boxes as $b): ?>
					<div class="col-6 col-md-3 mb-2">
						<div class="border rounded py-2 h-100">
							<div class="text-muted text-xs text-uppercase"><i class="fas <?= $b[2] ?>"></i> <?= $b[0] ?></div>
							<div class="h4 mb-0 font-weight-bold text-gray-800"><?= $b[1] ?></div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Layer e-Vokasi -->
				<div class="font-weight-bold text-gray-800 mb-2">Rincian per layer e-Vokasi</div>
				<div class="row mb-3">
					<?php
					$layers = array(
						array('Belum e-Vokasi', $ev['belum'], 'secondary'),
						array('Layer 1 / legalitas diterima', $ev['layer1'], 'primary'),
						array('Layer 2 / fasilitas', $ev['layer2'], 'info'),
						array('Layer 3 / program', $ev['layer3'], 'success'),
					);
					foreach ($layers as $l): ?>
					<div class="col-md-6 mb-2">
						<div class="d-flex justify-content-between align-items-center border-left-<?= $l[2] ?> border rounded px-3 py-2">
							<span><?= html_escape($l[0]) ?></span>
							<span class="font-weight-bold"><?= number_format($l[1],0,',','.') ?> <span class="text-muted small">lembaga</span></span>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Kategori (hardcode) -->
				<div class="font-weight-bold text-gray-800 mb-2">Pilihan kategori lembaga <?= $badgeContoh ?></div>
				<div class="row mb-3">
					<?php
					$kats = array(
						array('Non Kementerian', $hc['kat_nonkementerian']),
						array('Kementerian', $hc['kat_kementerian']),
						array('Pemerintah Daerah', $hc['kat_pemda']),
						array('Migrant Center', $hc['kat_migrant']),
					);
					foreach ($kats as $c): ?>
					<div class="col-md-6 mb-2">
						<div class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
							<span><?= html_escape($c[0]) ?></span>
							<span class="font-weight-bold"><?= number_format($c[1],0,',','.') ?> <span class="text-muted small">lembaga</span></span>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<!-- Rincian sektor (real DB) -->
				<div class="font-weight-bold text-gray-800 mb-2">Rincian sektor <span class="text-muted small font-weight-normal">(<?= $sektorCount ?> sektor · jumlah lembaga unik)</span></div>
				<div class="row" style="max-height:260px;overflow-y:auto;">
					<?php foreach ($perSektor as $s): ?>
					<div class="col-md-6 mb-2">
						<div class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
							<span><?= html_escape($s['label']) ?></span>
							<span class="font-weight-bold"><?= number_format($s['value'],0,',','.') ?> <span class="text-muted small">lembaga</span></span>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<hr>
				<style>.pl-item:hover{background:#f8f9fc;} #plList a:last-child{border-bottom:0!important;}</style>
				<!-- Daftar lembaga vokasi (lazy-load via /api/pendataan_list) -->
				<div class="font-weight-bold text-gray-800 mb-1">Daftar lembaga vokasi</div>
				<div class="text-muted small mb-2">Maksimal 25 lembaga per halaman.</div>
				<input type="text" id="plCari" class="form-control form-control-sm mb-2" placeholder="Cari lembaga (minimal 2 karakter)…" autocomplete="off">
				<div id="plList" class="border rounded" style="min-height:140px;">
					<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>
				</div>
				<div class="d-flex justify-content-between align-items-center mt-2">
					<span id="plInfo" class="text-muted small"></span>
					<span>
						<button type="button" id="plPrev" class="btn btn-sm btn-outline-secondary" disabled>Sebelumnya</button>
						<span id="plPage" class="text-muted small mx-1"></span>
						<button type="button" id="plNext" class="btn btn-sm btn-outline-secondary" disabled>Berikutnya</button>
					</span>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
			</div>
		</div>
	</div>
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

// Daftar lembaga vokasi di dalam modal drill-down (lazy load + cari + paginasi).
window.addEventListener('load', function () {
	var $ = window.jQuery;
	if (!$) { return; }
	var API = '<?= site_url('api') ?>';
	var DETAIL = '<?= site_url('lembaga') ?>';
	var data = null, filtered = [], page = 1, PER = 25, loaded = false;

	function esc(s) { return (s === null || s === undefined) ? '' : String(s).replace(/[&<>"]/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]; }); }

	function render() {
		var totalPage = Math.max(1, Math.ceil(filtered.length / PER));
		if (page > totalPage) page = totalPage;
		var start = (page - 1) * PER;
		var slice = filtered.slice(start, start + PER);
		var html = slice.map(function (x, i) {
			var no = start + i + 1;
			var sek = x.sektor && x.sektor.length ? ' · Sektor: ' + x.sektor.map(esc).join(', ') : '';
			return '<a href="' + DETAIL + '/' + x.id + '?from=ringkasan" class="d-block border-bottom px-3 py-2 text-reset pl-item" style="text-decoration:none;">' +
				'<div><span class="text-muted mr-2">' + no + '</span><strong>' + esc(x.nama) + '</strong></div>' +
				'<div class="small text-muted">' + esc(x.own) + (x.prov ? ' · ' + esc(x.prov) : '') + '</div>' +
				'<div class="small text-muted">' + esc(x.ev) + sek + '</div>' +
				(x.email ? '<div class="small text-muted">Kontak: ' + esc(x.email) + '</div>' : '') +
				'</a>';
		}).join('');
		document.getElementById('plList').innerHTML = html || '<div class="text-center text-muted py-4">Tidak ada lembaga yang cocok.</div>';
		document.getElementById('plInfo').textContent = filtered.length.toLocaleString('id-ID') + ' lembaga';
		document.getElementById('plPage').textContent = page + ' / ' + totalPage;
		document.getElementById('plPrev').disabled = page <= 1;
		document.getElementById('plNext').disabled = page >= totalPage;
	}

	function applySearch() {
		if (!data) return;
		var q = (document.getElementById('plCari').value || '').trim().toLowerCase();
		if (q.length >= 2) {
			filtered = data.filter(function (x) {
				return x.nama.toLowerCase().indexOf(q) >= 0 ||
					(x.prov || '').toLowerCase().indexOf(q) >= 0 ||
					(x.email || '').toLowerCase().indexOf(q) >= 0;
			});
		} else { filtered = data; }
		page = 1; render();
	}

	function load() {
		if (loaded) return; loaded = true;
		fetch(API + '/pendataan_list')
			.then(function (r) { return r.json(); })
			.then(function (d) { data = d; filtered = d; render(); })
			.catch(function () { document.getElementById('plList').innerHTML = '<div class="text-danger p-3">Gagal memuat daftar.</div>'; loaded = false; });
	}

	$('#modalRingkas').on('shown.bs.modal', load);
	document.getElementById('plCari').addEventListener('keyup', applySearch);
	document.getElementById('plPrev').addEventListener('click', function () { if (page > 1) { page--; render(); } });
	document.getElementById('plNext').addEventListener('click', function () { page++; render(); });
});
</script>
