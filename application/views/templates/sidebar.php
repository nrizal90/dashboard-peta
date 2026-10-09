<?php $active = isset($active) ? $active : ''; ?>
	<!-- Sidebar -->
	<ul class="navbar-nav bg-gradient-gold sidebar sidebar-dark accordion" id="accordionSidebar">

		<!-- Sidebar - Brand -->
		<a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= site_url('dashboard') ?>">
			<div class="sidebar-brand-icon rotate-n-15">
				<i class="fas fa-map-marked-alt"></i>
			</div>
			<div class="sidebar-brand-text mx-3">Dashboard Pemantauan SMK Go Global</div>
		</a>

		<hr class="sidebar-divider my-0">

<?php
		// Menu bergrup: [id collapse, label, icon, [[active, url, label], ...]]
		$menus = [
			['menuVokasi', 'Monitoring Lembaga Vokasi', 'fa-school', [
				['dashboard', 'dashboard', 'Dashboard'],
				['peta', 'peta', 'Peta Sebaran'],
				['daftar-pendataan', 'daftar-pendataan', 'Lembaga Vokasi'],
			]],
			['menuPelatihan', 'Monitoring Pelatihan', 'fa-user-graduate', [
				['pelatihan', 'pelatihan', 'Dashboard'],
			]],
			['menuPenempatan', 'Monitoring Penempatan', 'fa-map-signs', [
				['penempatan', 'penempatan', 'Dashboard'],
				['penempatan-v2', 'penempatan-v2', 'Dashboard (Versi 2)'],
				['monitoring-pmi', 'monitoring-pmi', 'Tracking Penempatan'],
				['monitoring-pmi-v2', 'monitoring-pmi-v2', 'Tracking Penempatan (Versi 2)'],
			]],
		];
		foreach ($menus as $m):
			$open = in_array($active, array_column($m[3], 0), true);
		?>
		<li class="nav-item <?= $open ? 'active' : '' ?>">
			<a class="nav-link <?= $open ? '' : 'collapsed' ?>" href="#" data-toggle="collapse" data-target="#<?= $m[0] ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= $m[0] ?>">
				<i class="fas fa-fw <?= $m[2] ?>"></i>
				<span><?= $m[1] ?></span>
			</a>
			<div id="<?= $m[0] ?>" class="collapse <?= $open ? 'show' : '' ?>" data-parent="#accordionSidebar">
				<div class="bg-white py-2 collapse-inner rounded">
					<?php foreach ($m[3] as $sub): ?>
					<a class="collapse-item <?= $active === $sub[0] ? 'active' : '' ?>" href="<?= site_url($sub[1]) ?>"><?= $sub[2] ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		</li>
		<?php endforeach; ?>

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
