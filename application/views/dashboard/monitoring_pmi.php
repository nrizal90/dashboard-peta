<?php
/**
 * Monitoring PMI — tabel peserta pelatihan + flag akun / proses penempatan / E-KPMI.
 * Sumber: DB SISKO P2MI via Vokasi_repo::pmiRows(). Filter GET berlaku ke tabel & export.
 */
$rows    = isset($rows) ? $rows : array();
$options = isset($options) ? $options : array();
$filter  = isset($filter) ? $filter : array();
$fv      = function ($k) use ($filter) { return isset($filter[$k]) ? $filter[$k] : ''; };
$yn      = function ($v) { return $v ? '<span class="badge badge-success">Ya</span>' : '<span class="badge badge-light">Tidak</span>'; };
$selects = array('provinsi' => 'Provinsi', 'kabupaten' => 'Kabupaten/Kota', 'bp3mi' => 'BP3MI');
$flags   = array('has_akun' => 'Akun SiskoP2MI', 'has_pen' => 'Proses Penempatan', 'has_epmi' => 'E-KPMI');
$exportUrl = site_url('monitoring-pmi/export') . ($filter ? '?' . http_build_query($filter) : '');
?>

<style>
	.dg-wrap { background: #f7f7fb; }
	.dg-card { border: 0; border-radius: 1rem; box-shadow: 0 6px 22px rgba(0,0,0,.05); }
	.dg-card .card-body { padding: 1.25rem 1.4rem; }
	.dg-title { font-weight: 800; color: #2b2b33; letter-spacing: -.01em; }
	.dg-sub { color: #9a9aa6; font-size: .78rem; margin-bottom: 1rem; }
</style>

<div class="container-fluid dg-wrap py-2">

	<div class="d-sm-flex align-items-center justify-content-between mb-3">
		<div>
			<h1 class="h4 dg-title mb-1">Monitoring PMI</h1>
			<div class="text-muted small">Peserta pelatihan: akun SiskoP2MI, proses penempatan, E-KPMI (sumber: SISKO P2MI)</div>
		</div>
		<a href="<?= $exportUrl ?>" class="btn btn-sm btn-warning font-weight-bold mt-2 mt-sm-0">
			<i class="fas fa-file-excel mr-1"></i> Export Excel
		</a>
	</div>

	<!-- ===== FILTER (GET) — berlaku untuk tabel dan export ===== -->
	<div class="card dg-card mb-4">
		<div class="card-body py-3">
			<form method="get" class="form-row align-items-end">
				<div class="col-lg-2 col-md-4 col-sm-6 col-12 mb-2">
					<label class="small font-weight-bold text-muted mb-1" for="f_nama">Nama</label>
					<input type="text" class="form-control form-control-sm" id="f_nama" name="nama" value="<?= html_escape($fv('nama')) ?>" placeholder="Cari nama…">
				</div>
				<div class="col-lg-2 col-md-4 col-sm-6 col-12 mb-2">
					<label class="small font-weight-bold text-muted mb-1" for="f_nik">NIK</label>
					<input type="text" class="form-control form-control-sm" id="f_nik" name="nik" value="<?= html_escape($fv('nik')) ?>" placeholder="Cari NIK…">
				</div>
				<?php foreach ($selects as $key => $label): ?>
				<div class="col-lg-2 col-md-4 col-sm-6 col-12 mb-2">
					<label class="small font-weight-bold text-muted mb-1" for="f_<?= $key ?>"><?= $label ?></label>
					<select class="form-control form-control-sm" id="f_<?= $key ?>" name="<?= $key ?>">
						<option value="">Semua</option>
						<?php foreach ((isset($options[$key]) ? $options[$key] : array()) as $v): ?>
						<option value="<?= html_escape($v) ?>"<?= $fv($key) === $v ? ' selected' : '' ?>><?= html_escape($v) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endforeach; ?>
				<?php foreach ($flags as $key => $label): ?>
				<div class="col-lg-2 col-md-4 col-sm-6 col-12 mb-2">
					<label class="small font-weight-bold text-muted mb-1" for="f_<?= $key ?>"><?= $label ?></label>
					<select class="form-control form-control-sm" id="f_<?= $key ?>" name="<?= $key ?>">
						<option value="">Semua</option>
						<option value="1"<?= $fv($key) === '1' ? ' selected' : '' ?>>Ya</option>
						<option value="0"<?= $fv($key) === '0' ? ' selected' : '' ?>>Tidak</option>
					</select>
				</div>
				<?php endforeach; ?>
				<div class="col-lg-2 col-12 mb-2">
					<button type="submit" class="btn btn-sm btn-warning font-weight-bold mr-1"><i class="fas fa-filter mr-1"></i> Terapkan</button>
					<a href="<?= site_url('monitoring-pmi') ?>" class="btn btn-sm btn-light">Reset</a>
				</div>
			</form>
		</div>
	</div>

	<!-- ===== TABEL ===== -->
	<div class="card dg-card mb-4">
		<div class="card-body">
			<div class="d-flex justify-content-between align-items-center mb-1">
				<div class="dg-title h6 mb-0">Data PMI</div>
				<input type="search" class="form-control form-control-sm w-auto" id="tCari" placeholder="Cari…">
			</div>
			<div class="dg-sub"><?= number_format(count($rows), 0, ',', '.') ?> baris sesuai filter.</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover" id="tblPmi" style="width:100%">
					<thead class="thead-light">
						<tr><th>No</th><th>Nama</th><th>NIK</th><th>Provinsi</th><th>Kabupaten/Kota</th><th>Akun SiskoP2MI</th><th>Proses Penempatan</th><th>E-KPMI</th></tr>
					</thead>
					<tbody>
					<?php foreach ($rows as $i => $r): ?>
						<tr>
							<td><?= $i + 1 ?></td>
							<td><?= html_escape($r['nama']) ?></td>
							<td><?= html_escape($r['nik']) ?></td>
							<td><?= html_escape($r['provinsi']) ?></td>
							<td><?= html_escape($r['kabupaten']) ?></td>
							<td><?= $yn($r['has_akun']) ?></td>
							<td><?= $yn($r['has_pen']) ?></td>
							<td><?= $yn($r['has_epmi']) ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

</div>
<!-- /.container-fluid -->

<!-- DataTables CSS (page-specific) -->
<link href="<?= base_url('assets/vendor/datatables/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">
<script>
window.addEventListener('load', function () {
	var base = '<?= base_url() ?>';
	function loadScript(src, cb) { var s = document.createElement('script'); s.src = src; s.onload = cb; document.body.appendChild(s); }
	loadScript(base + 'assets/vendor/datatables/jquery.dataTables.min.js', function () {
		loadScript(base + 'assets/vendor/datatables/dataTables.bootstrap4.min.js', function () {
			var $ = window.jQuery;
			var dt = $('#tblPmi').DataTable({
				pageLength: 25,
				lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
				order: [[0, 'asc']],
				language: {
					lengthMenu: 'Tampil _MENU_ baris',
					info: 'Menampilkan _START_–_END_ dari _TOTAL_ baris',
					infoFiltered: '(disaring dari _MAX_)',
					infoEmpty: 'Tidak ada data',
					zeroRecords: 'Tidak ada data yang cocok',
					paginate: { previous: '‹', next: '›' }
				},
				dom: "<'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>"
			});
			$('#tCari').on('keyup', function () { dt.search(this.value).draw(); });
		});
	});
});
</script>
