<?php
/**
 * Tentang Data — transparansi keterbatasan data (DASHBOARD_SPEC 3.5).
 * Angka diambil dari summary.json (agregat aman), bukan dari _quality_report mentah.
 */
$s = isset($summary) ? $summary : array();
$koord = isset($s['koordinat']) ? $s['koordinat'] : array();
$lem   = isset($s['lembaga']) ? $s['lembaga'] : array();
$total = isset($lem['total_baris']) ? $lem['total_baris'] : 0;
$asli  = isset($koord['asli']) ? $koord['asli'] : 0;
$persen = isset($koord['persen_asli']) ? $koord['persen_asli'] : 0;
?>
			<div class="container-fluid">
				<div class="d-sm-flex align-items-center justify-content-between mb-3">
					<h1 class="h3 mb-0 text-gray-800">Tentang Data</h1>
					<a href="<?= site_url('dashboard') ?>" class="btn btn-sm btn-outline-primary shadow-sm">
						<i class="fas fa-map fa-sm"></i> Kembali ke Peta
					</a>
				</div>

				<div class="row">
					<div class="col-lg-8">
						<div class="card shadow mb-4">
							<div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Keterbatasan yang perlu Anda ketahui</h6></div>
							<div class="card-body">
								<h6 class="font-weight-bold">1. Cakupan koordinat</h6>
								<p>Hanya <strong><?= $asli ?> dari <?= $total ?> (<?= $persen ?>%)</strong> titik memakai koordinat asli.
								Sisanya adalah <strong>aproksimasi</strong> (centroid kabupaten/kota atau provinsi) dan ditandai
								dengan marker <em>dashed</em> + label "Lokasi perkiraan". Jangan perlakukan titik aproksimasi sebagai lokasi presisi.</p>

								<ul>
									<li>Koordinat asli: <strong><?= $koord['asli'] ?? 0 ?></strong></li>
									<li>Centroid kota (aproksimasi): <strong><?= $koord['centroid_kota'] ?? 0 ?></strong></li>
									<li>Centroid provinsi (aproksimasi kasar): <strong><?= $koord['centroid_provinsi'] ?? 0 ?></strong></li>
								</ul>

								<hr>
								<h6 class="font-weight-bold">2. Lembaga duplikat</h6>
								<p>Terdapat <strong><?= $lem['duplikat_ditandai'] ?? 0 ?></strong> baris duplikat
								(lembaga sama mendaftar dengan email berbeda). Semua hitungan &amp; agregat default hanya
								menghitung baris "terbaik" tiap grup (<strong><?= $lem['unik_primary'] ?? 0 ?></strong> lembaga unik).
								Halaman detail/pencarian bisa menampilkan seluruh <?= $total ?> baris dengan badge duplikat.</p>

								<hr>
								<h6 class="font-weight-bold">3. Utang teknis</h6>
								<ul>
									<li>Choropleth kabupaten/kota belum tersedia — 184 nama kota belum dipetakan ke kode BPS.</li>
									<li>Geocoding presisi tinggi belum dijalankan untuk <?= ($koord['centroid_kota'] ?? 0) + ($koord['centroid_provinsi'] ?? 0) ?> lembaga.</li>
									<li>Beberapa masalah berakar di sistem input sumber (nama kota terpotong, field free-text). Perlu perbaikan di hulu.</li>
								</ul>
							</div>
						</div>
					</div>

					<div class="col-lg-4">
						<div class="card shadow mb-4">
							<div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Ringkasan Sumber</h6></div>
							<div class="card-body">
								<p class="mb-1"><small class="text-muted">Digenerate:</small><br><?= html_escape($s['generated_at'] ?? '-') ?></p>
								<p class="mb-1"><small class="text-muted">Sumber:</small></p>
								<ul class="pl-3">
									<?php foreach (($s['sumber'] ?? array()) as $src): ?>
										<li><small><?= html_escape($src) ?></small></li>
									<?php endforeach; ?>
								</ul>
								<p class="text-muted mb-0"><small>Pipeline: <code>clean_vokasi.py</code></small></p>
							</div>
						</div>
					</div>
				</div>
			</div>
