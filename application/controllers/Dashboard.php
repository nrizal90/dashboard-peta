<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard Peta Lembaga Vokasi
 *
 * Merender halaman dashboard peta interaktif. Data disajikan lewat controller
 * Api (JSON) dan digambar di sisi klien oleh dashboard.js (Leaflet + Chart.js).
 */
class Dashboard extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('Vokasi_repo', NULL, 'repo');
	}

	/** Halaman dashboard utama. */
	public function index()
	{
		$data = array(
			'title'      => 'Dashboard Peta Vokasi',
			'active'     => 'dashboard',
			'map_center' => array(-2.5, 118.0),
			'map_zoom'   => 5,
			'summary'    => $this->repo->summary(),
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/index', $data);
		$this->load->view('templates/footer', $data);
	}

	/**
	 * Mode Command Center (kiosk fullscreen, auto-rotasi panel).
	 * Peta → Gap Analysis → Chart, bergantian otomatis, tetap bisa dioperasikan.
	 * Akses: /wall
	 */
	public function wall()
	{
		$data = array(
			'title'      => 'Command Center — Peta Vokasi',
			'map_center' => array(-2.5, 118.0),
			'map_zoom'   => 5,
			'rotate_ms'  => 20000, // durasi tiap panel sebelum rotasi
			'summary'    => $this->repo->summary(),
			'gap'        => $this->repo->aggProvinsiSektor(),
			'sektor'     => $this->repo->refs()['sektor'],
		);

		// View mandiri (tanpa layout header/sidebar/footer).
		$this->load->view('dashboard/wall', $data);
	}

	/** Halaman gap analysis (provinsi x sektor). */
	public function gap()
	{
		$data = array(
			'title'   => 'Gap Analysis — Sektor per Provinsi',
			'active'  => 'gap',
			'summary' => $this->repo->summary(),
			'gap'     => $this->repo->aggProvinsiSektor(),
			'sektor'  => $this->repo->refs()['sektor'],
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/gap', $data);
		$this->load->view('templates/footer', $data);
	}

	/** Halaman "Tentang Data" — keterbatasan data secara jujur. */
	public function tentang()
	{
		$data = array(
			'title'   => 'Tentang Data',
			'active'  => 'tentang',
			'summary' => $this->repo->summary(),
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/tentang', $data);
		$this->load->view('templates/footer', $data);
	}
}
