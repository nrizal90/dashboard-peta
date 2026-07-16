<?php $active = isset($active) ? $active : ''; ?>
	<!-- Sidebar -->
	<ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

		<!-- Sidebar - Brand -->
		<a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('dashboard') ?>">
			<div class="sidebar-brand-icon rotate-n-15">
				<i class="fas fa-map-marked-alt"></i>
			</div>
			<div class="sidebar-brand-text mx-3">Dashboard Peta</div>
		</a>

		<hr class="sidebar-divider my-0">

		<!-- Nav Item - Dashboard -->
		<li class="nav-item <?= $active === 'dashboard' ? 'active' : '' ?>">
			<a class="nav-link" href="<?= site_url('dashboard') ?>">
				<i class="fas fa-fw fa-map"></i>
				<span>Peta</span>
			</a>
		</li>

		<hr class="sidebar-divider">

		<div class="sidebar-heading">Data</div>

		<li class="nav-item">
			<a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapseData"
				aria-expanded="true" aria-controls="collapseData">
				<i class="fas fa-fw fa-database"></i>
				<span>Master Lokasi</span>
			</a>
			<div id="collapseData" class="collapse" aria-labelledby="headingData" data-parent="#accordionSidebar">
				<div class="bg-white py-2 collapse-inner rounded">
					<h6 class="collapse-header">Kelola:</h6>
					<a class="collapse-item" href="#">Daftar Lokasi</a>
					<a class="collapse-item" href="#">Tambah Lokasi</a>
				</div>
			</div>
		</li>

		<hr class="sidebar-divider d-none d-md-block">

		<div class="text-center d-none d-md-inline">
			<button class="rounded-circle border-0" id="sidebarToggle"></button>
		</div>

	</ul>
	<!-- End of Sidebar -->

	<!-- Content Wrapper -->
	<div id="content-wrapper" class="d-flex flex-column">

		<!-- Main Content -->
		<div id="content">
