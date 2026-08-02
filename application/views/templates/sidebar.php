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

		<!-- Nav Item - Peta -->
		<li class="nav-item <?= $active === 'dashboard' ? 'active' : '' ?>">
			<a class="nav-link" href="<?= site_url('dashboard') ?>">
				<i class="fas fa-fw fa-map"></i>
				<span>Peta</span>
			</a>
		</li>

		<!-- Nav Item - Dashboard Vokasi (ringkasan pendataan) -->
		<li class="nav-item <?= $active === 'pendataan' ? 'active' : '' ?>">
			<a class="nav-link" href="<?= site_url('pendataan') ?>">
				<i class="fas fa-fw fa-chart-pie"></i>
				<span>Dashboard Vokasi</span>
			</a>
		</li>

		<!-- Nav Item - Daftar Pendataan (tabel) -->
		<li class="nav-item <?= $active === 'daftar-pendataan' ? 'active' : '' ?>">
			<a class="nav-link" href="<?= site_url('daftar-pendataan') ?>">
				<i class="fas fa-fw fa-table"></i>
				<span>Daftar Pendataan</span>
			</a>
		</li>

		<hr class="sidebar-divider">

		<div class="sidebar-heading">Analisis</div>

		<li class="nav-item <?= $active === 'gap' ? 'active' : '' ?>">
			<a class="nav-link" href="<?= site_url('gap') ?>">
				<i class="fas fa-fw fa-th"></i>
				<span>Gap Analysis</span>
			</a>
		</li>

		<li class="nav-item <?= $active === 'tentang' ? 'active' : '' ?>">
			<a class="nav-link" href="<?= site_url('tentang') ?>">
				<i class="fas fa-fw fa-info-circle"></i>
				<span>Tentang Data</span>
			</a>
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
