<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="description" content="Dashboard Peta">
	<meta name="author" content="">

	<title><?= isset($title) ? html_escape($title) : 'Dashboard Peta' ?></title>

	<!-- Custom fonts -->
	<link href="<?= base_url('assets/vendor/fontawesome-free/css/all.min.css') ?>" rel="stylesheet" type="text/css">
	<link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

	<!-- SB Admin 2 -->
	<link href="<?= base_url('assets/css/sb-admin-2.min.css') ?>" rel="stylesheet">

	<!-- Leaflet -->
	<link href="<?= base_url('assets/vendor/leaflet/leaflet.css') ?>" rel="stylesheet">

	<style>
		#map { height: 70vh; width: 100%; border-radius: .35rem; z-index: 0; }
	</style>
</head>

<body id="page-top">

	<!-- Page Wrapper -->
	<div id="wrapper">
