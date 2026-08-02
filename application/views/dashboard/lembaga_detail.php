<?php
/**
 * Halaman detail 1 lembaga vokasi — replika halaman detail dashboard sumber.
 * Progress e-Vokasi (Layer 1/2/3) dari status verifikasi DB + tabel Program.
 */
$r = isset($row) ? $row : array();
$sektor = isset($sektor) ? $sektor : array();
$from = isset($from) ? $from : '';

$g = function ($k, $d = '') use ($r) { return (isset($r[$k]) && $r[$k] !== '' && $r[$k] !== NULL) ? $r[$k] : $d; };

// Status verifikasi per layer.
$sLeg = $g('status_legalitas'); $sFas = $g('status_fasilitas'); $sPro = $g('status_program');

// Layer saat ini (0..3).
$current = 0;
if ($sPro !== '')                 $current = 3;
elseif ($sFas !== '')             $current = 2;
elseif ($sLeg === 'accepted')     $current = 1;

$layerLabel = array(0 => 'Belum e-Vokasi', 1 => 'Layer 1/legalitas', 2 => 'Layer 2/fasilitas', 3 => 'Layer 3/program');

// Peta status -> label Indonesia.
$statusLabel = function ($v) {
	switch ($v) {
		case 'accepted': return 'Diterima';
		case 'rejected': return 'Ditolak';
		case 'pending':  return 'Menunggu';
		case 'revised':  return 'Revisi';
		default:         return '';
	}
};

$backUrl   = ($from === 'daftar') ? site_url('daftar-pendataan') : site_url('pendataan');
$backLabel = ($from === 'daftar') ? 'Kembali ke Daftar Pendataan' : 'Kembali ke Ringkasan';

$steps = array(
	array('no' => 1, 'title' => 'Layer 1', 'sub' => 'Legalitas', 'status' => $sLeg),
	array('no' => 2, 'title' => 'Layer 2', 'sub' => 'Fasilitas', 'status' => $sFas),
	array('no' => 3, 'title' => 'Layer 3', 'sub' => 'Program',   'status' => $sPro),
);
?>
<style>
	.evk-wrap { position: relative; padding: .5rem 0 0; }
	.evk-line { position: absolute; top: 18px; left: 8%; right: 8%; height: 3px; background: #e3e6f0; z-index: 0; }
	.evk-circle { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; position: relative; z-index: 1; background: #e3e6f0; color: #fff; font-weight: 700; }
	.evk-circle.done { background: #4e73df; }
	.evk-circle.pending { background: #fff; border: 2px solid #d1d3e2; color: #b7b9cc; }
	.detail-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; color: #b7b9cc; font-weight: 700; }
	.detail-val { font-size: 1rem; color: #3a3b45; margin-bottom: 1rem; }
</style>

<div class="container-fluid">

	<a href="<?= $backUrl ?>" class="btn btn-link pl-0 mb-2"><i class="fas fa-arrow-left"></i> <?= $backLabel ?></a>

	<!-- Kartu utama -->
	<div class="card shadow mb-4">
		<div class="card-body">
			<div class="mb-2">
				<span class="badge badge-dark p-2"><?= html_escape($layerLabel[$current]) ?></span>
				<span class="badge badge-secondary p-2"><?= html_escape($g('ownership', '-')) ?></span>
			</div>
			<h3 class="font-weight-bold text-gray-800 mb-1"><?= html_escape($g('nama', '(Tanpa nama)')) ?></h3>
			<p class="text-muted mb-4"><?= html_escape(trim($g('provinsi') . ($g('kota') ? ', ' . $g('kota') : ''), ', ')) ?></p>

			<!-- Progress e-Vokasi -->
			<div class="d-flex justify-content-between align-items-baseline">
				<div>
					<div class="font-weight-bold text-gray-800">Progress e-Vokasi</div>
					<div class="text-muted small">Posisi saat ini: <?= html_escape($layerLabel[$current]) ?></div>
				</div>
				<div class="text-muted small"><?= $current > 0 ? 'Sudah sampai Layer ' . $current : 'Belum masuk e-Vokasi' ?></div>
			</div>

			<div class="evk-wrap mb-4">
				<div class="evk-line"></div>
				<div class="row text-center">
					<?php foreach ($steps as $s):
						$reached = ($s['no'] <= $current);
						$lbl = $statusLabel($s['status']);
					?>
					<div class="col-4">
						<div class="evk-circle <?= $reached ? 'done' : 'pending' ?>">
							<?= $reached ? '<i class="fas fa-check"></i>' : $s['no'] ?>
						</div>
						<div class="mt-2 font-weight-bold text-gray-800"><?= $s['title'] ?></div>
						<div class="text-muted small"><?= $s['sub'] ?></div>
						<?php if ($lbl !== ''): ?>
							<span class="badge badge-<?= $s['status'] === 'accepted' ? 'dark' : ($s['status'] === 'rejected' ? 'danger' : 'warning') ?> mt-1"><?= $lbl ?></span>
						<?php endif; ?>
						<?php if ($s['no'] === $current && $current > 0): ?>
							<div class="text-muted small mt-1">Posisi sekarang</div>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
			</div>

			<hr>

			<!-- Field detail -->
			<div class="row">
				<div class="col-md-6">
					<div class="detail-label">Provinsi</div>
					<div class="detail-val"><?= html_escape($g('provinsi', '-')) ?></div>
					<div class="detail-label">Email / Kontak</div>
					<div class="detail-val"><?= html_escape($g('email', '-')) ?></div>
					<div class="detail-label">Jenis Lembaga</div>
					<div class="detail-val"><?= html_escape($g('tipe_lembaga', '-')) ?></div>
					<div class="detail-label">Pemerintah/Non</div>
					<div class="detail-val"><?= html_escape($g('ownership', '-')) ?></div>
				</div>
				<div class="col-md-6">
					<div class="detail-label">Kab/Kota</div>
					<div class="detail-val"><?= html_escape($g('kota', '-')) ?></div>
					<div class="detail-label">Nomor Telepon</div>
					<div class="detail-val"><?= html_escape($g('telepon', '-')) ?></div>
					<div class="detail-label">Bentuk Lembaga</div>
					<div class="detail-val"><?= html_escape($g('jenis', '-')) ?></div>
					<div class="detail-label">Sumber Data</div>
					<div class="detail-val">Database <code>main_db</code> · view <code>dashboard_vokasi_detail</code></div>
				</div>
			</div>
		</div>
	</div>

	<!-- Program (Sektor & Jabatan) -->
	<div class="card shadow mb-4">
		<div class="card-body">
			<h5 class="font-weight-bold text-gray-800 mb-0">Program (Sektor &amp; Jabatan)</h5>
			<p class="text-muted small mb-3"><?= count($sektor) ?> program</p>
			<?php if (empty($sektor)): ?>
				<p class="text-muted">Belum ada data sektor/jabatan.</p>
			<?php else: ?>
			<div class="table-responsive">
				<table class="table table-sm mb-0">
					<thead>
						<tr class="text-muted">
							<th>Sektor</th>
							<th>Jabatan</th>
							<th class="text-right">Kapasitas</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($sektor as $s): ?>
						<tr>
							<td><?= html_escape(isset($s['sektor']) ? $s['sektor'] : '-') ?></td>
							<td><?= html_escape(isset($s['jabatan']) ? $s['jabatan'] : '-') ?></td>
							<td class="text-right"><?= $g('kapasitas') === '' ? '-' : number_format((int) $g('kapasitas'), 0, ',', '.') ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="text-muted small mt-2 mb-0"><i class="fas fa-info-circle"></i> Kapasitas ditampilkan per lembaga (DB belum menyimpan kapasitas per program).</p>
			<?php endif; ?>
		</div>
	</div>
</div>
