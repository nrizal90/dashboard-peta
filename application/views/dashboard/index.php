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
$presisi = isset($s['koordinat']['persen_asli']) ? $s['koordinat']['persen_asli'] : 0;
?>
			<!-- Begin Page Content -->
			<div class="container-fluid">

				<!-- Page Heading -->
				<div class="d-sm-flex align-items-center justify-content-between mb-3">
					<h1 class="h3 mb-0 text-gray-800">Peta Sebaran Lembaga Vokasi</h1>
					<a href="<?= site_url('gap') ?>" class="btn btn-sm btn-outline-primary shadow-sm">
						<i class="fas fa-th fa-sm"></i> Gap Analysis
					</a>
				</div>

				<!-- ===== KPI HEADER ===== -->
				<div class="row">
					<?php
					$kpis = array(
						array('Lembaga (primary)', number_format($lem, 0, ',', '.'), 'fa-building', 'primary'),
						array('Total Kapasitas', number_format($kap, 0, ',', '.'), 'fa-users', 'success'),
						array('Provinsi', $prov, 'fa-map-marked-alt', 'info'),
						array('Sektor / Jabatan', $sekt . ' / ' . $jab, 'fa-layer-group', 'warning'),
						array('Koordinat Presisi', $presisi . '%', 'fa-crosshairs', 'danger'),
					);
					foreach ($kpis as $k): ?>
					<div class="col-md col-6 mb-3">
						<div class="card border-left-<?= $k[3] ?> shadow h-100 py-2">
							<div class="card-body py-2">
								<div class="row no-gutters align-items-center">
									<div class="col mr-2">
										<div class="text-xs font-weight-bold text-<?= $k[3] ?> text-uppercase mb-1"><?= $k[0] ?></div>
										<div class="h6 mb-0 font-weight-bold text-gray-800"><?= $k[1] ?></div>
									</div>
									<div class="col-auto"><i class="fas <?= $k[2] ?> fa-lg text-gray-300"></i></div>
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
								<div class="text-center mb-2">
									<span class="badge badge-primary result-badge px-3 py-2">
										<span id="resultCount">—</span> lembaga
									</span>
								</div>

								<div class="filter-group">
									<label class="head">Cari nama</label>
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
									<label class="head">Status Legalitas</label>
									<select id="fLegalitas" class="form-control form-control-sm">
										<option value="">Semua</option>
										<option value="accepted">Accepted</option>
										<option value="pending">Pending</option>
										<option value="rejected">Rejected</option>
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
							<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Backlog Verifikasi</h6></div>
							<div class="card-body py-2"><canvas id="chartFunnel"></canvas></div>
						</div>
					</div>
					<div class="col-6 col-lg mb-4">
						<div class="card shadow mini-chart-card h-100">
							<div class="card-header py-2"><h6 class="m-0 font-weight-bold text-primary">Ownership</h6></div>
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
			<div class="modal fade" id="modalList" tabindex="-1" role="dialog" aria-labelledby="modalListLabel" aria-hidden="true">
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
			<div class="modal fade" id="modalDetail" tabindex="-1" role="dialog" aria-labelledby="modalDetailLabel" aria-hidden="true">
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
