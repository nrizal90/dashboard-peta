<?php
/**
 * Dashboard peta vokasi — layout: KPI header · filter kiri · peta tengah · chart kanan.
 * Data diisi oleh assets/js/dashboard.js lewat endpoint /api/*.
 */
$s = isset($summary) ? $summary : array();
$kap = isset($s['kapasitas']['total']) ? $s['kapasitas']['total'] : 0;
$lem = isset($s['lembaga']['unik_primary']) ? $s['lembaga']['unik_primary'] : 0;
$prov = isset($s['wilayah']['provinsi']) ? $s['wilayah']['provinsi'] : 0;
$sekt = isset($s['sektor']['total_sektor']) ? $s['sektor']['total_sektor'] : 0;
$jab = isset($s['sektor']['total_jabatan']) ? $s['sektor']['total_jabatan'] : 0;
?>

<!-- ===== TEMA EMAS (senada dashboard utama; warnanya dari config/tema.php) ===== -->
<style>
	.dg-wrap { background: #f7f7fb; }

	/* Kartu: sudut membulat + bayangan lembut (sama dgn .dg-card di dashboard utama) */
	.dg-wrap .card { border: 0; border-radius: 1rem; box-shadow: 0 6px 22px rgba(0,0,0,.05); }
	.dg-wrap .card-header { background: #fff; border-bottom: 1px solid #f0f0f4; border-radius: 1rem 1rem 0 0; }
	.dg-wrap .card-header h6 { font-weight: 800; color: #2b2b33; letter-spacing: -.01em; }
	.dg-wrap .card-header .text-primary { color: #2b2b33 !important; }

	.dg-title { font-weight: 800; color: #2b2b33; letter-spacing: -.01em; }
	.dg-sub { color: #9a9aa6; font-size: .78rem; }

	/* KPI header — gaya kartu KPI dashboard utama */
	.dg-kpi .kpi-name { font-size: .72rem; color: #9a9aa6; font-weight: 700; text-transform: none; letter-spacing: 0; }
	.dg-kpi .kpi-num  { font-size: 1.5rem; font-weight: 800; color: #2b2b33; line-height: 1.1; }
	.dg-kpi .kpi-ico {
		width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
		background: var(--dg-gold-light); color: var(--dg-gold-deep);
	}

	/* Tombol & aksen emas (mengganti biru bawaan SB Admin, fungsi tidak berubah) */
	.dg-wrap .btn-outline-primary {
		color: var(--dg-gold-deep); border-color: var(--dg-gold);
	}
	.dg-wrap .btn-outline-primary:hover,
	.dg-wrap .btn-outline-primary:focus {
		background: var(--dg-gold); border-color: var(--dg-gold); color: #6a5308;
	}
	.dg-wrap .btn-outline-secondary { color: #8a8a95; border-color: #e3e3ea; }
	.dg-wrap .btn-outline-secondary:hover { background: #f0f0f4; color: #2b2b33; border-color: #e3e3ea; }
	.dg-wrap .form-control:focus {
		border-color: var(--dg-gold); box-shadow: 0 0 0 .2rem rgba(var(--dg-gold-rgb), .25);
	}
	.dg-wrap .form-check-input:checked { background-color: var(--dg-gold); border-color: var(--dg-gold); }

	/* Peta & panel filter */
	/* Peta tanpa tile OSM — laut abu-abu muda, daratan putih (polygon di dashboard.js). */
	.dg-wrap #map { border-radius: .75rem; background: #e6e9ed; }

	/* Bubble cluster: lingkaran emas + angka putih (iconCreateFunction di dashboard.js) */
	.dg-wrap .dg-cluster {
		background: var(--dg-gold-dark); border: 3px solid rgba(255,255,255,.85); border-radius: 50%;
		box-shadow: 0 2px 8px rgba(0,0,0,.18);
		display: flex; align-items: center; justify-content: center;
	}
	.dg-wrap .dg-cluster span { color: #fff; font-weight: 800; font-size: .8rem; line-height: 1; }
	.dg-wrap .leaflet-popup-content .btn-primary {
		background: var(--dg-gold-dark); border-color: var(--dg-gold-dark); color: #fff;
	}
	.dg-wrap .leaflet-popup-content .badge-primary { background: var(--dg-gold-deep); }
	.dg-wrap .loading-overlay .spinner-border { color: var(--dg-gold-dark) !important; }
	.dg-wrap .filter-group label.head { color: #b0b0bc; }
	.dg-wrap .checklist .form-check-label { color: #6b6b76; }

	/* Modal: header emas (menggantikan bg-primary) */
	.dg-modal .modal-content { border: 0; border-radius: 1rem; overflow: hidden; }
	.dg-modal .modal-header {
		background: linear-gradient(90deg, var(--dg-gold-dark), var(--dg-gold-deep)) !important;
	}
	.dg-modal .table thead.thead-light th {
		background: #faf6e9; color: #8a6d1a; border-color: #f0e6c8;
		font-size: .74rem; text-transform: uppercase; letter-spacing: .03em;
	}
	/* Isi modal dirender oleh dashboard.js — samakan aksen birunya jadi emas. */
	.dg-modal .text-primary { color: var(--dg-gold-deep) !important; }
	.dg-modal .badge-primary { background: var(--dg-gold-deep); }
	.dg-modal .btn-primary {
		background: var(--dg-gold-dark); border-color: var(--dg-gold-dark); color: #fff;
	}
	.dg-modal .btn-primary:hover { background: var(--dg-gold-deep); border-color: var(--dg-gold-deep); }
	.dg-modal .btn-outline-primary { color: var(--dg-gold-deep); border-color: var(--dg-gold); }
	.dg-modal .btn-outline-primary:hover { background: var(--dg-gold); border-color: var(--dg-gold); color: #6a5308; }

	@media (max-width: 575.98px) {
		.dg-kpi .kpi-num { font-size: 1.25rem; }
	}
</style>

			<!-- Begin Page Content -->
			<div class="container-fluid dg-wrap py-2">

				<!-- Page Heading -->
				<div class="d-sm-flex align-items-center justify-content-between mb-3">
					<div>
						<h1 class="h4 mb-0 dg-title">Peta Sebaran Lembaga Vokasi</h1>
						<div class="dg-sub">Persebaran Lembaga Vokasi Beserta Kapasitas, Sektor, dan Tahapan Verifikasinya.</div>
					</div>
					<a href="<?= site_url('gap') ?>" class="btn btn-sm btn-outline-primary shadow-sm">
						<i class="fas fa-th fa-sm"></i> Gap Analysis
					</a>
				</div>

				<!-- ===== KPI HEADER ===== -->
				<div class="row">
					<?php
					$kpis = array(
						array('Lembaga', number_format($lem, 0, ',', '.'), 'fa-building'),
						array('Total Kapasitas', number_format($kap, 0, ',', '.'), 'fa-users'),
						array('Provinsi', $prov, 'fa-map-marked-alt'),
						array('Sektor / Jabatan', $sekt . ' / ' . $jab, 'fa-layer-group'),
					);
					foreach ($kpis as $k): ?>
					<div class="col-md col-6 mb-3">
						<div class="card dg-kpi h-100">
							<div class="card-body py-3">
								<div class="row no-gutters align-items-center">
									<div class="col mr-2">
										<div class="kpi-name mb-1"><?= $k[0] ?></div>
										<div class="kpi-num"><?= $k[1] ?></div>
									</div>
									<div class="col-auto"><div class="kpi-ico"><i class="fas <?= $k[2] ?>"></i></div></div>
								</div>
							</div>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<div class="row" id="dashRow">
					<!-- ===== SIDEBAR FILTER (kiri) ===== -->
					<div class="col-lg-3 mb-4" id="colFilter">
						<div class="card shadow h-100">
							<div class="card-header py-3 d-flex justify-content-between align-items-center">
								<h6 class="m-0 font-weight-bold text-primary">Filter</h6>
								<button id="btnReset" class="btn btn-sm btn-outline-secondary py-0">Reset</button>
							</div>
							<div class="card-body dash-scroll">

								<div class="filter-group">
									<label class="head">Cari nama lembaga</label>
									<input type="text" id="fQ" class="form-control form-control-sm" placeholder="ketik nama lembaga…">
								</div>

								<div class="filter-group">
									<div class="form-check">
										<input type="checkbox" class="form-check-input" id="fOnlyOriginal">
										<label class="form-check-label" for="fOnlyOriginal">Hanya koordinat asli (presisi)</label>
									</div>
								</div>

								<div class="filter-group">
									<label class="head">Ownership</label>
									<select id="fOwnership" class="form-control form-control-sm">
										<option value="">Semua</option>
										<option value="Non Pemerintah">Non Pemerintah</option>
										<option value="Pemerintah">Pemerintah</option>
									</select>
								</div>

								<div class="filter-group">
									<label class="head">Tahapan Verifikasi</label>
									<select id="fTahapan" class="form-control form-control-sm">
										<option value="">Semua</option>
										<option value="layer1">Layer 1 (Legalitas)</option>
										<option value="layer2">Layer 2 (Fasilitas)</option>
										<option value="layer3">Layer 3 (Program)</option>
										<option value="belum">Menunggu Verifikasi</option>
									</select>
								</div>

								<div class="filter-group">
									<label class="head">Pulau</label>
									<select id="fPulau" class="form-control form-control-sm"><option value="">Semua</option></select>
								</div>

								<div class="filter-group">
									<label class="head">Provinsi</label>
									<select id="fProvinsi" class="form-control form-control-sm"><option value="">Semua</option></select>
								</div>

								<div class="filter-group">
									<label class="head">Kota / Kabupaten</label>
									<input type="text" id="fKotaSearch" class="form-control form-control-sm mb-1" placeholder="cari kota…">
									<select id="fKota" class="form-control form-control-sm"><option value="">Semua</option></select>
								</div>

								<div class="filter-group">
									<label class="head">Kapasitas</label>
									<div class="d-flex align-items-center" style="gap:.4rem">
										<input type="number" id="fKapMin" class="form-control form-control-sm" placeholder="min" min="0">
										<span>–</span>
										<input type="number" id="fKapMax" class="form-control form-control-sm" placeholder="max" min="0">
									</div>
								</div>

								<div class="filter-group">
									<label class="head">Sektor</label>
									<div id="fSektor" class="checklist"></div>
								</div>

								<div class="filter-group">
									<label class="head">Jabatan / Program</label>
									<input type="text" id="fJabatanSearch" class="form-control form-control-sm mb-1" placeholder="cari jabatan (310)…">
									<div id="fJabatan" class="checklist tall"></div>
								</div>
							</div>
						</div>
					</div>

					<!-- ===== PETA (kanan, lebar) ===== -->
					<div class="col-lg-9 mb-4" id="colMap">
						<div class="card shadow h-100">
							<div class="card-header py-3 d-flex justify-content-between align-items-center">
								<h6 class="m-0 font-weight-bold text-primary">Peta Interaktif</h6>
								<div class="d-flex align-items-center">
									<small class="text-muted mr-3 d-none d-xl-inline">marker cluster · warna = ownership</small>
									<button id="btnToggleFilter" class="btn btn-sm btn-outline-secondary py-0" title="Sembunyikan panel filter agar peta lebih dominan">
										<i class="fas fa-expand-arrows-alt"></i> Perbesar Peta
									</button>
								</div>
							</div>
							<div class="card-body position-relative">
								<div id="map"></div>
								<div id="mapLoading" class="loading-overlay" style="display:none">
									<div class="spinner-border text-primary" role="status"></div>
								</div>
							</div>
						</div>
					</div>

				</div>

				<!-- ===== CHART PANEL (baris bawah, horizontal) ===== -->
				<div class="row" id="colChart">
					<div class="col-6 col-lg mb-4">
						<div class="card shadow mini-chart-card h-100">
							<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Tahapan Verifikasi</h6></div>
							<div class="card-body py-2"><canvas id="chartFunnel"></canvas></div>
						</div>
					</div>
					<div class="col-6 col-lg mb-4">
						<div class="card shadow mini-chart-card h-100">
							<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Penyelenggara Pelatihan</h6></div>
							<div class="card-body py-2"><canvas id="chartOwnership"></canvas></div>
						</div>
					</div>
					<div class="col-6 col-lg mb-4">
						<div class="card shadow mini-chart-card h-100">
							<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Top 10 Sektor</h6></div>
							<div class="card-body py-2"><canvas id="chartSektor"></canvas></div>
						</div>
					</div>
					<div class="col-6 col-lg mb-4">
						<div class="card shadow mini-chart-card h-100">
							<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Top 10 Jabatan</h6></div>
							<div class="card-body py-2"><canvas id="chartJabatan"></canvas></div>
						</div>
					</div>
					<div class="col-6 col-lg mb-4">
						<div class="card shadow mini-chart-card h-100">
							<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Top 10 Provinsi</h6></div>
							<div class="card-body py-2"><canvas id="chartProvinsi"></canvas></div>
						</div>
					</div>
				</div>

			</div>
			<!-- /.container-fluid -->

			<!-- ===== MODAL: List Lembaga Vokasi (muncul saat cluster peta diklik) ===== -->
			<div class="modal fade dg-modal" id="modalList" tabindex="-1" role="dialog" aria-labelledby="modalListLabel" aria-hidden="true">
				<div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
					<div class="modal-content">
						<div class="modal-header bg-primary text-white py-2">
							<h5 class="modal-title" id="modalListLabel"><i class="fas fa-university mr-2"></i>List Lembaga Vokasi</h5>
							<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						</div>
						<div class="modal-body p-0">
							<div class="table-responsive">
								<table class="table table-sm table-hover mb-0">
									<thead class="thead-light">
										<tr>
											<th class="text-center">No</th>
											<th>Nama</th>
											<th>Provinsi</th>
											<th>Kabupaten/Kota</th>
											<th>Kepemilikan</th>
											<th>No Registrasi</th>
											<th>No Legalitas</th>
											<th class="text-center">Aksi</th>
										</tr>
									</thead>
									<tbody id="listBody"></tbody>
								</table>
							</div>
						</div>
						<div class="modal-footer py-2 justify-content-between">
							<small class="text-muted">Total data: <b id="listTotal">0</b> lembaga</small>
							<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
						</div>
					</div>
				</div>
			</div>

			<!-- ===== MODAL: Detail Lembaga Vokasi ===== -->
			<div class="modal fade dg-modal" id="modalDetail" tabindex="-1" role="dialog" aria-labelledby="modalDetailLabel" aria-hidden="true">
				<div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
					<div class="modal-content">
						<div class="modal-header bg-primary text-white py-2">
							<h5 class="modal-title" id="modalDetailLabel"><i class="fas fa-university mr-2"></i>Detail Lembaga Vokasi</h5>
							<button type="button" class="close text-white" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						</div>
						<div class="modal-body" id="detailBody">
							<div class="text-center text-muted py-4">Memuat…</div>
						</div>
						<div class="modal-footer py-2">
							<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
						</div>
					</div>
				</div>
			</div>
