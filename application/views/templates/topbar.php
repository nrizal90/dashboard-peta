			<!-- Topbar -->
			<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

				<!-- Sidebar Toggle (Topbar) -->
				<button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
					<i class="fa fa-bars"></i>
				</button>

				<!-- Topbar Search -->
				<form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
					<div class="input-group">
						<input type="text" class="form-control bg-light border-0 small" placeholder="Cari lokasi..."
							aria-label="Search">
						<div class="input-group-append">
							<button class="btn btn-primary" type="button">
								<i class="fas fa-search fa-sm"></i>
							</button>
						</div>
					</div>
				</form>

				<!-- Topbar Navbar -->
				<ul class="navbar-nav ml-auto">

					<!-- Pindah ke mode Command Center (kiosk) -->
					<li class="nav-item mr-3 d-flex align-items-center">
						<a href="<?= site_url('wall') ?>" target="_blank" rel="noopener"
							class="btn btn-outline-primary btn-sm shadow-sm" title="Buka mode command center (kiosk) di tab baru">
							<i class="fas fa-tv fa-sm"></i> Mode Layar Penuh
						</a>
					</li>
				</ul>

			</nav>
			<!-- End of Topbar -->
