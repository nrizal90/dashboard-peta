		</div>
		<!-- End of Main Content -->

		<!-- Footer -->
		<footer class="sticky-footer bg-white">
			<div class="container my-auto">
				<div class="copyright text-center my-auto">
					<span>Dashboard Peta &copy; <?= date('Y') ?></span>
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

	<!-- Leaflet -->
	<script src="<?= base_url('assets/vendor/leaflet/leaflet.js') ?>"></script>

	<script>
		// Konfigurasi peta dari controller.
		var MAP_CONFIG = {
			center: <?= json_encode(isset($map_center) ? $map_center : array(-2.5489, 118.0149)) ?>,
			zoom:   <?= isset($map_zoom) ? (int) $map_zoom : 5 ?>,
			apiUrl: '<?= site_url('api/lokasi') ?>'
		};

		(function () {
			var map = L.map('map').setView(MAP_CONFIG.center, MAP_CONFIG.zoom);

			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
			}).addTo(map);

			// Ambil data lokasi (GeoJSON) lalu gambar marker.
			fetch(MAP_CONFIG.apiUrl)
				.then(function (res) { return res.json(); })
				.then(function (geojson) {
					var layer = L.geoJSON(geojson, {
						onEachFeature: function (feature, lyr) {
							var p = feature.properties || {};
							var html = '<strong>' + (p.nama || '-') + '</strong>' +
								'<br><span class="badge badge-primary">' + (p.kategori || '') + '</span>' +
								'<br>' + (p.keterangan || '');
							lyr.bindPopup(html);
						}
					}).addTo(map);

					if (geojson.features && geojson.features.length) {
						map.fitBounds(layer.getBounds().pad(0.2));
					}
				})
				.catch(function (err) { console.error('Gagal memuat data lokasi:', err); });
		})();
	</script>

</body>
</html>
