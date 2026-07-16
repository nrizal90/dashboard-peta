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

	<style>
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
