<?php
/**
 * Tracking Penempatan — tabel peserta pelatihan + flag akun / proses penempatan / E-PMI.
 * Sumber: DB SISKO P2MI via Vokasi_repo::pmiRows($filter, $versi). Filter GET berlaku ke tabel & export.
 * Versi 2 (M_PMI_VOKASI) tidak punya penyelenggara → filter & kolom BP3MI disembunyikan.
 */
$rows    = isset($rows) ? $rows : array();
$options = isset($options) ? $options : array();
$filter  = isset($filter) ? $filter : array();
$v2      = isset($versi) && (int) $versi === 2;
$base    = $v2 ? 'monitoring-pmi-v2' : 'monitoring-pmi';
$fv      = function ($k) use ($filter) { return isset($filter[$k]) ? $filter[$k] : ''; };
$flag    = function ($v) {
	return $v
		? '<span class="dg-flag yes" title="Ya"><i class="fas fa-check"></i></span>'
		: '<span class="dg-flag no" title="Tidak"><i class="fas fa-times"></i></span>';
};
$selects = array('provinsi' => array('Provinsi', '-- Semua Provinsi --'));
if ( ! $v2) { $selects['bp3mi'] = array('BP3MI', '-- Semua BP3MI --'); }
$exportUrl = site_url($base . '/export') . ($filter ? '?' . http_build_query($filter) : '');
?>

<style>
	.dg-wrap { background: #f7f7fb; }
	.dg-card { border: 0; border-radius: 1rem; box-shadow: 0 6px 22px rgba(0,0,0,.05); }
	.dg-card .card-body { padding: 1.25rem 1.4rem; }
	.dg-title { font-weight: 800; color: #2b2b33; letter-spacing: -.01em; }
	.dg-crumb { color: #9a9aa6; font-size: .75rem; }
	.dg-wrap label.small { color: #6b6b76 !important; }
	#tblPmi thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #6b6b76; }
	#tblPmi td, #tblPmi th { vertical-align: middle; }
	.dg-flag {
		display: inline-flex; align-items: center; justify-content: center;
		width: 26px; height: 26px; border-radius: 50%; font-size: .75rem;
	}
	.dg-flag.yes { background: var(--dg-gold-light); color: var(--dg-gold-deep); }
	.dg-flag.no  { background: #f1f1f5; color: #b0b0bc; }
</style>

<div class="container-fluid dg-wrap py-2">

	<div class="mb-3">
		<h1 class="h4 dg-title mb-1">Tracking Penempatan<?= $v2 ? ' <span class="badge badge-warning align-middle">Versi 2</span>' : '' ?></h1>
		<div class="dg-crumb">Home / Monitoring Penempatan / Tracking Penempatan</div>
	</div>

	<!-- ===== FILTER (GET) — berlaku untuk tabel dan export ===== -->
	<div class="card dg-card mb-4">
		<div class="card-body py-3">
			<div class="dg-title h6 mb-3">Filter Data</div>
			<form method="get">
				<div class="form-row">
					<div class="col-md-4 col-sm-6 col-12 mb-2">
						<label class="small font-weight-bold mb-1" for="f_tgl_awal">Tanggal Awal</label>
						<input type="date" class="form-control form-control-sm" id="f_tgl_awal" name="tgl_awal" value="<?= html_escape($fv('tgl_awal')) ?>">
					</div>
					<div class="col-md-4 col-sm-6 col-12 mb-2">
						<label class="small font-weight-bold mb-1" for="f_tgl_akhir">Tanggal Akhir</label>
						<input type="date" class="form-control form-control-sm" id="f_tgl_akhir" name="tgl_akhir" value="<?= html_escape($fv('tgl_akhir')) ?>">
					</div>
					<div class="col-md-4 col-sm-6 col-12 mb-2">
						<label class="small font-weight-bold mb-1" for="f_nama">Nama</label>
						<input type="text" class="form-control form-control-sm" id="f_nama" name="nama" value="<?= html_escape($fv('nama')) ?>" placeholder="Masukkan nama peserta">
					</div>
					<div class="col-md-4 col-sm-6 col-12 mb-2">
						<label class="small font-weight-bold mb-1" for="f_nik">NIK</label>
						<input type="text" class="form-control form-control-sm" id="f_nik" name="nik" value="<?= html_escape($fv('nik')) ?>" placeholder="Masukkan NIK">
					</div>
					<?php foreach ($selects as $key => $s): ?>
					<div class="col-md-4 col-sm-6 col-12 mb-2">
						<label class="small font-weight-bold mb-1" for="f_<?= $key ?>"><?= $s[0] ?></label>
						<select class="form-control form-control-sm" id="f_<?= $key ?>" name="<?= $key ?>">
							<option value=""><?= $s[1] ?></option>
							<?php foreach ((isset($options[$key]) ? $options[$key] : array()) as $v): ?>
							<option value="<?= html_escape($v) ?>"<?= $fv($key) === $v ? ' selected' : '' ?>><?= html_escape($v) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="mt-2">
					<button type="submit" class="btn btn-sm btn-warning font-weight-bold mr-1"><i class="fas fa-search mr-1"></i> Cari</button>
					<a href="<?= site_url($base) ?>" class="btn btn-sm btn-light">Reset</a>
				</div>
			</form>
		</div>
	</div>

	<!-- ===== TABEL ===== -->
	<div class="card dg-card mb-4">
		<div class="card-body">
			<div class="d-flex justify-content-between align-items-center mb-3">
				<input type="search" class="form-control form-control-sm w-auto" id="tCari" placeholder="Cari…">
				<a href="<?= $exportUrl ?>" class="btn btn-sm btn-warning font-weight-bold">
					<i class="fas fa-file-excel mr-1"></i> Export Excel
				</a>
			</div>
			<div class="table-responsive">
				<table class="table table-sm table-hover" id="tblPmi" style="width:100%">
					<thead class="thead-light">
						<tr>
							<th>No</th><th>Nama</th><th>NIK</th><th>Provinsi</th>
							<?php if ( ! $v2): ?><th>BP3MI</th><?php endif; ?>
							<th class="text-center">Akun Sisko P2MI</th><th class="text-center">Proses Penempatan</th><th class="text-center">EPMI</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($rows as $i => $r): ?>
						<tr>
							<td><?= $i + 1 ?></td>
							<td><?= html_escape($r['nama']) ?></td>
							<td><?= html_escape($r['nik']) ?></td>
							<td><?= html_escape($r['provinsi']) ?></td>
							<?php if ( ! $v2): ?><td><?= html_escape($r['bp3mi']) ?></td><?php endif; ?>
							<td class="text-center" data-order="<?= (int) $r['has_akun'] ?>"><?= $flag($r['has_akun']) ?></td>
							<td class="text-center" data-order="<?= (int) $r['has_pen'] ?>"><?= $flag($r['has_pen']) ?></td>
							<td class="text-center" data-order="<?= (int) $r['has_epmi'] ?>"><?= $flag($r['has_epmi']) ?></td>
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
					info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
					infoFiltered: '(disaring dari _MAX_)',
					infoEmpty: 'Tidak ada data',
					zeroRecords: 'Tidak ada data yang cocok',
					emptyTable: 'Tidak ada data',
					paginate: { previous: '‹', next: '›' }
				},
				dom: "<'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>"
			});
			$('#tCari').on('keyup', function () { dt.search(this.value).draw(); });
		});
	});
});
</script>
