<?php
/**
 * Menu "Daftar Pendataan" — tabel lembaga vokasi dari DB.
 * Semua kolom di tabel ini tersedia di `dashboard_vokasi_detail`.
 * Kolom "Kategori (K/L)", "Target 40.000", "Program cocok target" milik dashboard
 * sumber TIDAK ditampilkan karena tidak ada di DB (lihat ANALISIS_REPLIKASI...md).
 */
$rows = isset($rows) ? $rows : array();

// Opsi dropdown filter (distinct dari data).
$optProv = $optJenis = $optOwn = $optEv = array();
foreach ($rows as $r) {
	if ($r['provinsi'] !== '')  $optProv[$r['provinsi']]  = TRUE;
	if ($r['jenis'] !== '')     $optJenis[$r['jenis']]    = TRUE;
	if ($r['ownership'] !== '') $optOwn[$r['ownership']]  = TRUE;
	$optEv[$r['evokasi']] = TRUE;
}
ksort($optProv); ksort($optJenis); ksort($optOwn); ksort($optEv);
?>
<div class="container-fluid">

	<div class="d-sm-flex align-items-center justify-content-between mb-1">
		<div>
			<h1 class="h3 mb-0 text-gray-800">Daftar Pendataan</h1>
			<p class="mb-0 text-muted small"><strong><?= number_format(count($rows),0,',','.') ?></strong> lembaga vokasi dari database.</p>
		</div>
		<a href="<?= site_url('daftar-pendataan/export') ?>" class="btn btn-sm btn-success shadow-sm">
			<i class="fas fa-file-excel fa-sm"></i> Unduh Excel
		</a>
	</div>

	<div class="alert alert-info py-2 small mb-3">
		<i class="fas fa-info-circle"></i>
		Seluruh kolom tabel ini <strong>langsung dari database</strong>. Kolom "Kategori K/L",
		"Target 40.000", dan "Program cocok target" dari dashboard sumber tidak ditampilkan karena
		belum tersedia di DB.
	</div>

	<!-- Filter -->
	<div class="card shadow mb-3">
		<div class="card-body py-2">
			<div class="d-flex flex-wrap align-items-end" style="gap:.5rem;">
				<div class="flex-grow-1" style="min-width:200px;">
					<label class="text-muted mb-1" style="font-size:.65rem;letter-spacing:.05em;">CARI</label>
					<input type="text" id="fCari" class="form-control form-control-sm" placeholder="Cari lembaga, provinsi, email...">
				</div>
				<div style="min-width:160px;">
					<label class="text-muted mb-1" style="font-size:.65rem;letter-spacing:.05em;">PROVINSI</label>
					<select id="fProv" class="form-control form-control-sm" data-col="1">
						<option value="">Semua Provinsi</option>
						<?php foreach ($optProv as $v => $_): ?><option value="<?= html_escape($v) ?>"><?= html_escape($v) ?></option><?php endforeach; ?>
					</select>
				</div>
				<div style="min-width:150px;">
					<label class="text-muted mb-1" style="font-size:.65rem;letter-spacing:.05em;">JENIS LEMBAGA</label>
					<select id="fJenis" class="form-control form-control-sm" data-col="3">
						<option value="">Semua Jenis</option>
						<?php foreach ($optJenis as $v => $_): ?><option value="<?= html_escape($v) ?>"><?= html_escape($v) ?></option><?php endforeach; ?>
					</select>
				</div>
				<div style="min-width:150px;">
					<label class="text-muted mb-1" style="font-size:.65rem;letter-spacing:.05em;">KEPEMILIKAN</label>
					<select id="fOwn" class="form-control form-control-sm" data-col="4">
						<option value="">Semua</option>
						<?php foreach ($optOwn as $v => $_): ?><option value="<?= html_escape($v) ?>"><?= html_escape($v) ?></option><?php endforeach; ?>
					</select>
				</div>
				<div style="min-width:170px;">
					<label class="text-muted mb-1" style="font-size:.65rem;letter-spacing:.05em;">STATUS e-VOKASI</label>
					<select id="fEv" class="form-control form-control-sm" data-col="6">
						<option value="">Semua Status</option>
						<?php foreach ($optEv as $v => $_): ?><option value="<?= html_escape($v) ?>"><?= html_escape($v) ?></option><?php endforeach; ?>
					</select>
				</div>
			</div>
		</div>
	</div>

	<!-- Tabel -->
	<div class="card shadow mb-4">
		<div class="card-body">
			<div class="table-responsive">
				<table class="table table-bordered table-hover table-sm" id="tblPendataan" width="100%" cellspacing="0">
					<thead class="thead-light">
						<tr>
							<th>Nama Lembaga</th>
							<th>Provinsi</th>
							<th>Kab/Kota</th>
							<th>Jenis</th>
							<th>Kepemilikan</th>
							<th class="text-right">Kapasitas</th>
							<th>Status e-Vokasi</th>
							<th class="text-center">Aksi</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rows as $r):
							$evClass = 'secondary';
							if ($r['evokasi'] === 'Layer 3 (program)') $evClass = 'success';
							elseif ($r['evokasi'] === 'Layer 2 (fasilitas)') $evClass = 'info';
							elseif ($r['evokasi'] === 'Layer 1 (legalitas)') $evClass = 'primary';
						?>
						<tr>
							<td><?= html_escape($r['nama']) ?></td>
							<td><?= html_escape($r['provinsi']) ?></td>
							<td><?= html_escape($r['kota']) ?></td>
							<td><?= html_escape($r['jenis']) ?></td>
							<td><?= html_escape($r['ownership']) ?></td>
							<td class="text-right"><?= $r['kapasitas'] === NULL ? '-' : number_format($r['kapasitas'],0,',','.') ?></td>
							<td><span class="badge badge-<?= $evClass ?>"><?= html_escape($r['evokasi']) ?></span></td>
							<td class="text-center">
								<button type="button" class="btn btn-xs btn-outline-primary btn-detail" data-id="<?= $r['id'] ?>" style="font-size:.72rem;padding:.1rem .4rem;">Detail</button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<!-- Modal Detail -->
<div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white">
				<h5 class="modal-title"><i class="fas fa-university"></i> Detail Lembaga Vokasi</h5>
				<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			</div>
			<div class="modal-body" id="modalDetailBody">
				<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
			</div>
		</div>
	</div>
</div>

<!-- DataTables CSS (page-specific) -->
<link href="<?= base_url('assets/vendor/datatables/dataTables.bootstrap4.min.css') ?>" rel="stylesheet">

<script>
window.DP_API = '<?= site_url('api') ?>';
window.DP_BASE = '<?= base_url() ?>';

window.addEventListener('load', function () {
	// jQuery sudah tersedia (dimuat di footer). Muat DataTables berurutan lalu init.
	function loadScript(src, cb) { var s = document.createElement('script'); s.src = src; s.onload = cb; document.body.appendChild(s); }

	loadScript(window.DP_BASE + 'assets/vendor/datatables/jquery.dataTables.min.js', function () {
		loadScript(window.DP_BASE + 'assets/vendor/datatables/dataTables.bootstrap4.min.js', initTable);
	});

	function initTable() {
		var $ = window.jQuery;
		var dt = $('#tblPendataan').DataTable({
			pageLength: 25,
			lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
			order: [[0, 'asc']],
			columnDefs: [{ orderable: false, targets: 7 }],
			language: {
				search: '', searchPlaceholder: 'Cari…',
				lengthMenu: 'Tampil _MENU_ baris',
				info: 'Menampilkan _START_–_END_ dari _TOTAL_ lembaga',
				infoFiltered: '(disaring dari _MAX_)',
				infoEmpty: 'Tidak ada data',
				zeroRecords: 'Tidak ada lembaga yang cocok',
				paginate: { previous: '‹', next: '›' }
			},
			dom: "<'row'<'col-sm-12'tr>><'row'<'col-sm-5'i><'col-sm-7'p>>" // sembunyikan search bawaan; pakai filter kustom
		});

		// Cari global kustom
		$('#fCari').on('keyup', function () { dt.search(this.value).draw(); });

		// Dropdown filter -> pencarian kolom (exact match via regex)
		$('#fProv, #fJenis, #fOwn, #fEv').on('change', function () {
			var col = parseInt(this.getAttribute('data-col'), 10);
			var v = this.value ? ('^' + this.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$') : '';
			dt.column(col).search(v, true, false).draw();
		});

		// Klik Detail -> modal + fetch /api/lembaga/{id}
		$('#tblPendataan tbody').on('click', '.btn-detail', function () {
			var id = this.getAttribute('data-id');
			var $body = $('#modalDetailBody');
			$body.html('<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>');
			$('#modalDetail').modal('show');
			fetch(window.DP_API + '/lembaga/' + id)
				.then(function (r) { return r.json(); })
				.then(function (d) { $body.html(renderDetail(d)); })
				.catch(function () { $body.html('<div class="alert alert-danger">Gagal memuat detail.</div>'); });
		});

		function esc(s) { return (s === null || s === undefined) ? '' : String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

		function renderDetail(d) {
			var L = d.lembaga || {};
			var field = function (label, val) {
				return '<div class="col-md-6 mb-2"><div class="text-xs text-uppercase text-muted font-weight-bold">' + label + '</div><div>' + (esc(val) || '<span class="text-muted">—</span>') + '</div></div>';
			};
			var html = '<h6 class="text-primary font-weight-bold border-bottom pb-1"><i class="fas fa-university"></i> Lembaga</h6><div class="row">';
			html += field('Nama Lembaga', L.nama);
			html += field('Kepemilikan', L.ownership);
			html += field('Provinsi', L.provinsi);
			html += field('Jenis', L.jenis);
			html += field('Kabupaten/Kota', L.kota);
			html += field('Jenis Lembaga (tipe)', L.tipe_lembaga);
			html += field('Email', L.email);
			html += field('Kapasitas', L.kapasitas);
			html += field('Nomor Registrasi', L.nomor_registrasi);
			html += field('Nomor Legalitas', L.nomor_legalitas);
			html += '</div>';

			// Sektor & Jabatan
			html += '<h6 class="text-primary font-weight-bold border-bottom pb-1 mt-3"><i class="fas fa-briefcase"></i> Sektor &amp; Jabatan</h6>';
			var sek = d.sektor || [];
			if (!sek.length) { html += '<p class="text-muted">Belum ada data sektor.</p>'; }
			else {
				var grp = {};
				sek.forEach(function (s) { (grp[s.sektor] = grp[s.sektor] || []).push(s.jabatan); });
				html += '<div class="row">';
				Object.keys(grp).forEach(function (k) {
					html += '<div class="col-md-6 mb-2"><div class="font-weight-bold">' + esc(k) + '</div><ul class="mb-1">' + grp[k].map(function (j) { return '<li>' + esc(j) + '</li>'; }).join('') + '</ul></div>';
				});
				html += '</div>';
			}

			// Katalog
			html += '<h6 class="text-primary font-weight-bold border-bottom pb-1 mt-3"><i class="fas fa-book"></i> Katalog Pelatihan</h6>';
			var kat = d.katalog || [];
			if (!kat.length) { html += '<p class="text-muted">Belum ada katalog pelatihan.</p>'; }
			else {
				html += '<ul>';
				kat.forEach(function (k) { html += '<li>' + esc(k.judul) + (k.tanggal_mulai ? ' <span class="text-muted small">(' + esc(k.tanggal_mulai) + ')</span>' : '') + '</li>'; });
				html += '</ul>';
			}
			return html;
		}
	}
});
</script>
