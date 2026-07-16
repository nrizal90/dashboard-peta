<?php
/**
 * Command Center — kiosk fullscreen dengan auto-rotasi panel (Peta → Gap → Chart).
 * Tetap bisa dioperasikan: operator dapat pause, navigasi manual, klik marker, zoom.
 * View mandiri (tidak memakai layout SB Admin).
 */
$s       = isset($summary) ? $summary : array();
$gap     = isset($gap) ? $gap : array();
$sektor  = isset($sektor) ? $sektor : array();
$kap     = $s['kapasitas']['total'] ?? 0;
$lem     = $s['lembaga']['unik_primary'] ?? 0;
$prov    = $s['wilayah']['provinsi'] ?? 0;
$sekN    = $s['sektor']['total_sektor'] ?? 0;
$jabN    = $s['sektor']['total_jabatan'] ?? 0;
$presisi = $s['koordinat']['persen_asli'] ?? 0;
$kosong  = $s['gap_sektor']['sel_kosong'] ?? 0;

usort($gap, function ($a, $b) { return $b['sektor_tersedia'] - $a['sektor_tersedia']; });
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title><?= html_escape($title) ?></title>

	<link href="<?= base_url('assets/vendor/fontawesome-free/css/all.min.css') ?>" rel="stylesheet">
	<link href="<?= base_url('assets/vendor/leaflet/leaflet.css') ?>" rel="stylesheet">
	<link href="<?= base_url('assets/vendor/leaflet.markercluster/MarkerCluster.css') ?>" rel="stylesheet">
	<link href="<?= base_url('assets/vendor/leaflet.markercluster/MarkerCluster.Default.css') ?>" rel="stylesheet">

	<style>
		:root { --bg:#0f1226; --bg2:#1a1f3a; --card:#1c2242; --line:#2a3260; --accent:#4e73df; --txt:#eef1ff; --muted:#8b91c4; }
		* { box-sizing: border-box; }
		html, body { margin:0; padding:0; height:100%; background:var(--bg); color:var(--txt); font-family:'Segoe UI',Arial,sans-serif; overflow:hidden; }

		#wall { display:flex; flex-direction:column; height:100vh; }

		/* ===== Header + KPI banner (selalu tampak) ===== */
		#head { display:flex; align-items:center; justify-content:space-between; padding:14px 26px; background:linear-gradient(180deg,#141833,#0f1226); border-bottom:1px solid var(--line); }
		#head .brand { font-size:1.7rem; font-weight:800; letter-spacing:.4px; }
		#head .brand i { color:var(--accent); margin-right:12px; }
		#head .clock { text-align:right; }
		#head .clock .time { font-size:1.7rem; font-weight:700; }
		#head .clock .date { font-size:1rem; color:var(--muted); }

		#kpis { display:flex; gap:14px; padding:14px 26px; background:var(--bg2); border-bottom:1px solid var(--line); }
		.kpi { flex:1; background:var(--card); border-radius:12px; padding:14px 18px; border-left:5px solid var(--accent); }
		.kpi .label { font-size:.8rem; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
		.kpi .val { font-size:2rem; font-weight:800; line-height:1.1; margin-top:4px; }
		.kpi.green { border-color:#1cc88a; } .kpi.cyan { border-color:#36b9cc; }
		.kpi.yellow { border-color:#f6c23e; } .kpi.red { border-color:#e74a3b; }

		/* ===== Stage (panel yang berotasi) ===== */
		#stage { position:relative; flex:1; overflow:hidden; }
		.scene { position:absolute; inset:0; opacity:0; visibility:hidden; transition:opacity .5s ease; }
		.scene.active { opacity:1; visibility:visible; }
		.scene-title { position:absolute; top:14px; left:26px; z-index:600; font-size:1.3rem; font-weight:700; text-shadow:0 2px 8px rgba(0,0,0,.7); pointer-events:none; }

		/* Scene peta */
		#map { position:absolute; inset:0; height:100%; width:100%; background:#0f1226; }
		.map-legend { background:rgba(20,24,50,.9); color:var(--txt); padding:10px 14px; border-radius:10px; font-size:1rem; line-height:1.7; }
		.legend-dot { display:inline-block; width:14px; height:14px; border-radius:50%; margin-right:8px; vertical-align:-2px; }
		.legend-dot.approx { border:2px dashed #aaa; opacity:.6; background:transparent; }
		.leaflet-popup-content { font-size:1rem; } .leaflet-popup-content strong { font-size:1.15rem; }

		/* Scene gap */
		#sceneGap { padding:56px 26px 26px; overflow:auto; }
		table.gap { width:100%; border-collapse:collapse; }
		table.gap th, table.gap td { text-align:center; font-size:.82rem; padding:5px 4px; border:1px solid var(--line); white-space:nowrap; }
		table.gap th.prov { text-align:left; position:sticky; left:0; background:#141833; z-index:2; }
		table.gap thead th { position:sticky; top:0; background:#141833; z-index:1; }
		.g-empty { background:#3a1620; color:#3a1620; }
		.g-fill { background:#123a24; color:#7ef0ad; font-weight:700; }

		/* Scene chart */
		#sceneChart { padding:56px 26px 26px; display:grid; grid-template-columns:1fr 1fr; grid-template-rows:1fr 1fr; gap:18px; }
		.chart-box { background:var(--card); border-radius:12px; padding:14px 16px; display:flex; flex-direction:column; }
		.chart-box h3 { margin:0 0 8px; font-size:1.05rem; color:#c8ccf5; font-weight:700; }
		.chart-box .cwrap { position:relative; flex:1; min-height:0; }

		/* ===== Control bar bawah ===== */
		#ctl { display:flex; align-items:center; gap:18px; padding:10px 26px; background:#141833; border-top:1px solid var(--line); }
		#ctl .dots { display:flex; gap:10px; }
		#ctl .dot { width:12px; height:12px; border-radius:50%; background:#39406e; cursor:pointer; transition:background .3s,transform .2s; }
		#ctl .dot.on { background:var(--accent); transform:scale(1.3); }
		#ctl button { background:var(--card); color:var(--txt); border:1px solid var(--line); border-radius:8px; padding:7px 14px; font-size:1rem; cursor:pointer; }
		#ctl button:hover { background:var(--line); }
		#ctl .sceneName { font-weight:700; font-size:1.05rem; min-width:150px; }
		#ctl .progress { flex:1; height:6px; background:#2a3160; border-radius:3px; overflow:hidden; }
		#ctl .progress > i { display:block; height:100%; width:0; background:var(--accent); }
		#ctl .mode { font-size:.95rem; color:var(--muted); }
		#ctl .mode.manual { color:#f6c23e; }
	</style>
</head>
<body>
<div id="wall">

	<!-- Header -->
	<div id="head">
		<div class="brand"><i class="fas fa-map-marked-alt"></i><?= html_escape($title) ?></div>
		<div class="clock"><div class="time" id="clock">--:--:--</div><div class="date" id="today"></div></div>
	</div>

	<!-- KPI banner -->
	<div id="kpis">
		<div class="kpi"><div class="label">Lembaga</div><div class="val"><?= number_format($lem,0,',','.') ?></div></div>
		<div class="kpi green"><div class="label">Total Kapasitas</div><div class="val"><?= number_format($kap,0,',','.') ?></div></div>
		<div class="kpi cyan"><div class="label">Provinsi</div><div class="val"><?= $prov ?></div></div>
		<div class="kpi yellow"><div class="label">Sektor / Jabatan</div><div class="val"><?= $sekN ?> / <?= $jabN ?></div></div>
		<div class="kpi red"><div class="label">Koordinat Presisi</div><div class="val"><?= $presisi ?>%</div></div>
	</div>

	<!-- Stage -->
	<div id="stage">
		<!-- Scene 1: Peta -->
		<section class="scene active" id="sceneMap" data-name="Peta Sebaran">
			<div class="scene-title"><i class="fas fa-map"></i> Peta Sebaran Lembaga</div>
			<div id="map"></div>
		</section>

		<!-- Scene 2: Gap Analysis -->
		<section class="scene" id="sceneGap" data-name="Gap Analysis">
			<div class="scene-title"><i class="fas fa-th"></i> Gap Sektor per Provinsi — <?= $kosong ?> sel kosong</div>
			<table class="gap">
				<thead>
					<tr>
						<th class="prov">Provinsi</th>
						<?php foreach ($sektor as $sk): ?>
							<th title="<?= html_escape($sk['nama']) ?>"><?= html_escape(mb_substr($sk['nama'],0,8)) ?></th>
						<?php endforeach; ?>
						<th>Σ</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($gap as $row): ?>
						<tr>
							<th class="prov"><?= html_escape($row['provinsi']) ?></th>
							<?php foreach ($sektor as $sk):
								$v = (int) ($row['sektor'][$sk['slug']] ?? 0); ?>
								<td class="<?= $v>0?'g-fill':'g-empty' ?>"><?= $v>0?$v:'·' ?></td>
							<?php endforeach; ?>
							<td style="font-weight:700"><?= (int) $row['sektor_tersedia'] ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>

		<!-- Scene 3: Chart -->
		<section class="scene" id="sceneChart" data-name="Statistik">
			<div class="scene-title" style="position:static;padding-bottom:0"></div>
			<div class="chart-box"><h3>Backlog Verifikasi</h3><div class="cwrap"><canvas id="wFunnel"></canvas></div></div>
			<div class="chart-box"><h3>Top 10 Sektor</h3><div class="cwrap"><canvas id="wSektor"></canvas></div></div>
			<div class="chart-box"><h3>Ownership</h3><div class="cwrap"><canvas id="wOwnership"></canvas></div></div>
			<div class="chart-box"><h3>Top 10 Provinsi</h3><div class="cwrap"><canvas id="wProvinsi"></canvas></div></div>
		</section>
	</div>

	<!-- Control bar -->
	<div id="ctl">
		<div class="dots" id="dots"></div>
		<span class="sceneName" id="sceneName">Peta Sebaran</span>
		<button id="btnPrev" title="Sebelumnya"><i class="fas fa-step-backward"></i></button>
		<button id="btnPlay" title="Play/Pause"><i class="fas fa-pause"></i></button>
		<button id="btnNext" title="Berikutnya"><i class="fas fa-step-forward"></i></button>
		<div class="progress"><i id="progressBar"></i></div>
		<span class="mode" id="modeText">Auto-rotasi</span>
	</div>
</div>

<script src="<?= base_url('assets/vendor/leaflet/leaflet.js') ?>"></script>
<script src="<?= base_url('assets/vendor/leaflet.markercluster/leaflet.markercluster.js') ?>"></script>
<script src="<?= base_url('assets/vendor/chart.js/Chart.min.js') ?>"></script>
<script>
	window.WALL = {
		apiBase: '<?= site_url('api') ?>',
		mapCenter: <?= json_encode($map_center) ?>,
		mapZoom: <?= (int) $map_zoom ?>,
		rotateMs: <?= (int) $rotate_ms ?>
	};
</script>
<script src="<?= base_url('assets/js/wall.js') ?>"></script>
</body>
</html>
