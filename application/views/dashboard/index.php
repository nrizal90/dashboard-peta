			<!-- Begin Page Content -->
			<div class="container-fluid">

				<!-- Page Heading -->
				<div class="d-sm-flex align-items-center justify-content-between mb-4">
					<h1 class="h3 mb-0 text-gray-800">Peta Sebaran Lokasi</h1>
				</div>

				<!-- Kartu ringkasan -->
				<div class="row">
					<div class="col-xl-3 col-md-6 mb-4">
						<div class="card border-left-primary shadow h-100 py-2">
							<div class="card-body">
								<div class="row no-gutters align-items-center">
									<div class="col mr-2">
										<div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
											Total Lokasi</div>
										<div class="h5 mb-0 font-weight-bold text-gray-800">
											<?= isset($total_lokasi) ? (int) $total_lokasi : 0 ?>
										</div>
									</div>
									<div class="col-auto">
										<i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Peta -->
				<div class="card shadow mb-4">
					<div class="card-header py-3">
						<h6 class="m-0 font-weight-bold text-primary">Peta Interaktif</h6>
					</div>
					<div class="card-body">
						<div id="map"></div>
					</div>
				</div>

			</div>
			<!-- /.container-fluid -->
