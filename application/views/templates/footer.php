		</div>
		<!-- End of Main Content -->

		<!-- Footer -->
		<footer class="sticky-footer bg-white">
			<div class="container my-auto">
				<div class="copyright text-center my-auto">
					<span>Dashboard Peta Vokasi &copy; <?= date('Y') ?> · Sumber: hasil cleaning <code>clean_vokasi.py</code></span>
				</div>
			</div>
		</footer>
		<!-- End of Footer -->

		</div>
		<!-- End of Content Wrapper -->

	</div>
	<!-- End of Page Wrapper -->

	<!-- Scroll to Top Button-->
	<a class="scroll-to-top rounded" href="#page-top">
		<i class="fas fa-angle-up"></i>
	</a>

	<!-- Core plugin JavaScript -->
	<script src="<?= base_url('assets/vendor/jquery/jquery.min.js') ?>"></script>
	<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
	<script src="<?= base_url('assets/vendor/jquery-easing/jquery.easing.min.js') ?>"></script>

	<!-- SB Admin 2 -->
	<script src="<?= base_url('assets/js/sb-admin-2.min.js') ?>"></script>

	<!-- Chart.js -->
	<script src="<?= base_url('assets/vendor/chart.js/Chart.min.js') ?>"></script>

	<!-- Leaflet + plugin -->
	<script src="<?= base_url('assets/vendor/leaflet/leaflet.js') ?>"></script>
	<script src="<?= base_url('assets/vendor/leaflet.markercluster/leaflet.markercluster.js') ?>"></script>

	<script>
		// Konfigurasi global untuk dashboard.js
		window.APP = {
			apiBase: '<?= site_url('api') ?>',
			siteUrl: '<?= rtrim(site_url(), '/') ?>',
			mapCenter: <?= json_encode(isset($map_center) ? $map_center : array(-2.5, 118.0)) ?>,
			mapZoom: <?= isset($map_zoom) ? (int) $map_zoom : 5 ?>
		};
	</script>

	<!-- Logika dashboard (map cluster, filter, chart) -->
	<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>

</body>
</html>
