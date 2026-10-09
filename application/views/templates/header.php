<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="description" content="Dashboard Peta Lembaga Vokasi Indonesia">
	<meta name="author" content="">

	<title><?= isset($title) ? html_escape($title) : 'Dashboard Peta Vokasi' ?></title>

	<!-- Custom fonts -->
	<link href="<?= base_url('assets/vendor/fontawesome-free/css/all.min.css') ?>" rel="stylesheet" type="text/css">
	<link href="<?= base_url('assets/vendor/nunito/nunito.css') ?>" rel="stylesheet">

	<!-- SB Admin 2 -->
	<link href="<?= base_url('assets/css/sb-admin-2.min.css') ?>" rel="stylesheet">

	<!-- Leaflet -->
	<link href="<?= base_url('assets/vendor/leaflet/leaflet.css') ?>" rel="stylesheet">
	<!-- Leaflet.markercluster (self-hosted) -->
	<link href="<?= base_url('assets/vendor/leaflet.markercluster/MarkerCluster.css') ?>" rel="stylesheet">
	<link href="<?= base_url('assets/vendor/leaflet.markercluster/MarkerCluster.Default.css') ?>" rel="stylesheet">

<?php
	// Palet emas — SATU SUMBER di application/config/tema.php (autoload).
	// Ditulis sekali di sini sebagai CSS custom property, dipakai semua view.
	$tema = $this->config->item('tema');
	// Komponen R,G,B dari warna utama — dipakai untuk rgba() (mis. shadow focus input).
	$goldRgb = implode(', ', sscanf($tema['gold'], '#%02x%02x%02x'));
	?>
	<style>
		:root {
			--dg-gold: <?= $tema['gold'] ?>;
			--dg-gold-dark: <?= $tema['gold_dark'] ?>;
			--dg-gold-deep: <?= $tema['gold_deep'] ?>;
			--dg-gold-light: <?= $tema['gold_light'] ?>;
			--dg-gold-rgb: <?= $goldRgb ?>;
		}

		/* Sidebar emas (senada tema dashboard) — ganti bg-gradient-warning yg terlalu kuning */
		.bg-gradient-gold {
			background-color: var(--dg-gold-dark);
			background-image: linear-gradient(180deg, var(--dg-gold-dark) 10%, var(--dg-gold-deep) 100%);
			background-size: cover;
		}
		/* Sedikit pertegas garis pemisah & heading di sidebar emas */
		.sidebar-dark .sidebar-heading { color: rgba(255, 255, 255, .7); }
		.sidebar-dark hr.sidebar-divider { border-top-color: rgba(255, 255, 255, .2); }
		/* Brand 2-3 baris: tinggi mengikuti teks (default SB Admin dikunci 4.375rem → terpotong) */
		.sidebar .sidebar-brand { height: auto; min-height: 4.375rem; padding: .9rem .75rem; }
		.sidebar .sidebar-brand .sidebar-brand-text { font-size: .72rem; line-height: 1.3; white-space: normal; text-align: left; }
		.sidebar .sidebar-brand .sidebar-brand-icon { flex-shrink: 0; }
		/* Submenu: aktif & hover emas (default SB Admin biru) */
		.sidebar .nav-item .collapse .collapse-inner .collapse-item.active,
		.sidebar .nav-item .collapsing .collapse-inner .collapse-item.active { color: var(--dg-gold-deep); font-weight: 800; }
		.sidebar .nav-item .collapse .collapse-inner .collapse-item:hover,
		.sidebar .nav-item .collapsing .collapse-inner .collapse-item:hover { background-color: #faf6e9; color: var(--dg-gold-deep); }
		/* Desktop, sidebar terbuka: lebih lebar + submenu menyatu dgn latar emas (bukan kotak putih).
		   Mode HP/toggled tetap popup putih bawaan SB Admin (melayang di atas konten). */
		@media (min-width: 768px) {
			.sidebar:not(.toggled) { width: 16.5rem !important; }
			/* Ikon · label · panah sejajar tengah (default: panah float → jatuh ke baris terakhir label) */
			.sidebar:not(.toggled) .nav-item .nav-link { display: flex; align-items: center; }
			.sidebar:not(.toggled) .nav-item .nav-link i { flex-shrink: 0; }
			.sidebar:not(.toggled) .nav-item .nav-link span { flex: 1; }
			.sidebar:not(.toggled) .nav-item .nav-link[data-toggle=collapse]::after { float: none; flex-shrink: 0; margin-left: .5rem; }
			.sidebar:not(.toggled) .nav-item .collapse .collapse-inner,
			.sidebar:not(.toggled) .nav-item .collapsing .collapse-inner { background-color: rgba(0, 0, 0, .12) !important; }
			.sidebar:not(.toggled) .nav-item .collapse .collapse-inner .collapse-item,
			.sidebar:not(.toggled) .nav-item .collapsing .collapse-inner .collapse-item { color: rgba(255, 255, 255, .85); white-space: normal; }
			.sidebar:not(.toggled) .nav-item .collapse .collapse-inner .collapse-item:hover,
			.sidebar:not(.toggled) .nav-item .collapsing .collapse-inner .collapse-item:hover { background-color: rgba(255, 255, 255, .15); color: #fff; }
			.sidebar:not(.toggled) .nav-item .collapse .collapse-inner .collapse-item.active,
			.sidebar:not(.toggled) .nav-item .collapsing .collapse-inner .collapse-item.active { background-color: rgba(255, 255, 255, .22); color: #fff; }
		}
		/* Tombol cari topbar senada tema */
		.topbar .btn-primary { background-color: var(--dg-gold-dark); border-color: var(--dg-gold-dark); }
		.topbar .btn-primary:hover { background-color: var(--dg-gold-deep); border-color: var(--dg-gold-deep); }

		#map { height: calc(100vh - 210px); min-height: 460px; width: 100%; border-radius: .35rem; z-index: 0; }

		/* Panel filter & chart bisa discroll independen */
		.dash-scroll { max-height: calc(100vh - 200px); overflow-y: auto; }
		.filter-group { border-bottom: 1px solid #eaecf4; padding: .6rem 0; }
		.filter-group:last-child { border-bottom: 0; }
		.filter-group label.head { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #b7b9cc; font-weight: 700; margin-bottom: .35rem; }
		.checklist { max-height: 180px; overflow-y: auto; }
		.checklist.tall { max-height: 240px; }
		.checklist .form-check { margin-bottom: .15rem; }
		.checklist .form-check-label { font-size: .82rem; }

		/* Legend peta */
		.map-legend { background: #fff; padding: 8px 10px; border-radius: .35rem; box-shadow: 0 0 12px rgba(0,0,0,.15); font-size: .78rem; line-height: 1.5; }
		.legend-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 6px; vertical-align: -1px; }
		.legend-dot.approx { border: 2px dashed #888; opacity: .6; background: transparent; }

		.result-badge { font-size: .95rem; }
		.mini-chart-card { margin-bottom: 1rem; }
		.mini-chart-card canvas { max-height: 190px; }
		/* Baris chart horizontal di bawah peta: beri tinggi tetap agar Chart.js tidak collapse */
		#colChart .mini-chart-card .card-body { height: 210px; }
		#colChart .mini-chart-card canvas { max-height: 100%; }

		/* Sel gap analysis */
		.gap-table td, .gap-table th { text-align: center; font-size: .72rem; padding: .3rem .25rem; white-space: nowrap; }
		.gap-table th.prov { text-align: left; position: sticky; left: 0; background: #f8f9fc; z-index: 1; }
		.gap-cell-empty { background: #f8d7da; color: #f8d7da; }
		.gap-cell-fill { background: #d4edda; color: #155724; font-weight: 700; }

		.loading-overlay { position: absolute; inset: 0; background: rgba(255,255,255,.6); display: flex; align-items: center; justify-content: center; z-index: 500; border-radius: .35rem; }
	</style>
</head>

<body id="page-top">

	<!-- Page Wrapper -->
	<div id="wrapper">
