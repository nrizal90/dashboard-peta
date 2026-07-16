<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard Peta
 *
 * Controller utama yang merender halaman dashboard dan menyediakan
 * data lokasi dalam format GeoJSON untuk digambar oleh Leaflet.
 */
class Dashboard extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('Lokasi_model', 'lokasi');
	}

	/**
	 * Halaman dashboard peta.
	 */
	public function index()
	{
		$data = array(
			'title'        => 'Dashboard Peta',
			'active'       => 'dashboard',
			// Titik tengah & zoom awal peta (default: Indonesia).
			'map_center'   => array(-2.5489, 118.0149),
			'map_zoom'     => 5,
			'total_lokasi' => $this->lokasi->count_all(),
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/index', $data);
		$this->load->view('templates/footer', $data);
	}

	/**
	 * Mode "wall" / kiosk untuk video wall command center.
	 * Peta fullscreen tanpa sidebar/topbar, dengan auto-refresh ringan.
	 * Akses: /wall  (atau /dashboard/wall)
	 */
	public function wall()
	{
		$data = array(
			'title'        => 'Command Center — Peta',
			'map_center'   => array(-2.5489, 118.0149),
			'map_zoom'     => 5,
			// Interval auto-refresh data (milidetik). Ubah sesuai kebutuhan.
			'refresh_ms'   => 30000,
			'total_lokasi' => $this->lokasi->count_all(),
		);

		// View mandiri (tidak memakai layout header/sidebar/footer).
		$this->load->view('dashboard/wall', $data);
	}

	/**
	 * Endpoint JSON (GeoJSON FeatureCollection) untuk marker peta.
	 * Dipanggil oleh Leaflet via AJAX: GET /api/lokasi
	 */
	public function lokasi()
	{
		$json = json_encode($this->lokasi->as_geojson());

		// ETag = sidik jari isi data. Kalau isi tidak berubah, ETag sama,
		// sehingga refresh berikutnya cukup dibalas 304 (tanpa body).
		$etag = '"' . md5($json) . '"';

		$client_etag = $this->input->get_request_header('If-None-Match');

		$this->output->set_header('Cache-Control: no-cache, must-revalidate');
		$this->output->set_header('ETag: ' . $etag);

		// Data tidak berubah → 304 Not Modified, body kosong (paling hemat).
		if ($client_etag !== NULL && trim($client_etag) === $etag)
		{
			$this->output->set_status_header(304);
			return;
		}

		$this->output
			->set_content_type('application/json')
			->set_output($json);
	}
}
