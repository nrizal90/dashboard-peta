<?php
/**
 * Gap Analysis — heatmap tabel provinsi x sektor.
 * Sel kosong (nilai 0) = sektor tidak tersedia di provinsi tsb.
 */
$gap    = isset($gap) ? $gap : array();
$sektor = isset($sektor) ? $sektor : array();
$s      = isset($summary) ? $summary : array();
$totalKosong = isset($s['gap_sektor']['sel_kosong']) ? $s['gap_sektor']['sel_kosong'] : 0;
$totalSel    = isset($s['gap_sektor']['total_sel']) ? $s['gap_sektor']['total_sel'] : 0;

// Urutkan provinsi berdasar jumlah sektor tersedia (paling lengkap dulu)
usort($gap, function ($a, $b) { return $b['sektor_tersedia'] - $a['sektor_tersedia']; });
?>
			<div class="container-fluid">
				<div class="d-sm-flex align-items-center justify-content-between mb-3">
					<h1 class="h3 mb-0 text-gray-800">Gap Analysis — Sektor per Provinsi</h1>
					<a href="<?= site_url('dashboard') ?>" class="btn btn-sm btn-outline-primary shadow-sm">
						<i class="fas fa-map fa-sm"></i> Kembali ke Peta
					</a>
				</div>

				<div class="alert alert-info">
					Dari <strong><?= $totalSel ?></strong> kombinasi (<?= count($gap) ?> provinsi × <?= count($sektor) ?> sektor),
					<strong><?= $totalSel - $totalKosong ?></strong> terisi dan
					<strong class="text-danger"><?= $totalKosong ?> kosong</strong>.
					Sel merah = sektor tidak tersedia di provinsi itu — insight kebijakan paling bernilai.
				</div>

				<div class="card shadow mb-4">
					<div class="card-header py-3">
						<h6 class="m-0 font-weight-bold text-primary">Matriks Ketersediaan Sektor</h6>
					</div>
					<div class="card-body">
						<div class="table-responsive">
							<table class="table table-bordered gap-table mb-0">
								<thead>
									<tr>
										<th class="prov">Provinsi</th>
										<?php foreach ($sektor as $sk): ?>
											<th title="<?= html_escape($sk['nama']) ?>"><?= html_escape(mb_substr($sk['nama'], 0, 10)) ?></th>
										<?php endforeach; ?>
										<th>Terisi</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($gap as $row): ?>
										<tr>
											<th class="prov"><?= html_escape($row['provinsi']) ?></th>
											<?php foreach ($sektor as $sk):
												$v = isset($row['sektor'][$sk['slug']]) ? (int) $row['sektor'][$sk['slug']] : 0;
												$cls = $v > 0 ? 'gap-cell-fill' : 'gap-cell-empty';
											?>
												<td class="<?= $cls ?>" title="<?= html_escape($sk['nama']) ?>: <?= $v ?>"><?= $v > 0 ? $v : '·' ?></td>
											<?php endforeach; ?>
											<td class="font-weight-bold"><?= (int) $row['sektor_tersedia'] ?>/<?= count($sektor) ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
