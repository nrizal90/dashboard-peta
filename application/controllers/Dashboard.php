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

	/**
	 * Menu "Dashboard Vokasi" — replika ringkasan pendataan.
	 * Angka yang tersedia diambil dari DB (pendataanStats); yang belum ada di DB
	 * (Kerja Sama, Target 40.000, Kategori K/L, LSP) di-hardcode di view menyamai
	 * dashboard sumber. Lihat dokumen ANALISIS_REPLIKASI_DASHBOARD_VOKASI.md.
	 */
	public function pendataan()
	{
		$data = array(
			'title'  => 'Dashboard Vokasi',
			'active' => 'pendataan',
			'stats'  => $this->repo->pendataanStats(),
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/pendataan', $data);
		$this->load->view('templates/footer', $data);
	}

	/**
	 * Menu "Daftar Pendataan" — tabel lembaga dari DB (dashboard_vokasi_detail)
	 * dengan pencarian/sortir/paginasi (DataTables) + detail modal + export.
	 */
	public function daftar_pendataan()
	{
		$data = array(
			'title'  => 'Daftar Pendataan',
			'active' => 'daftar-pendataan',
			'rows'   => $this->repo->pendataanRows(),
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/daftar_pendataan', $data);
		$this->load->view('templates/footer', $data);
	}

	/** Halaman detail 1 lembaga (progress e-Vokasi + program sektor/jabatan). */
	public function lembaga_detail($id = NULL)
	{
		if ($id === NULL || ! ctype_digit((string) $id))
		{
			show_404();
			return;
		}

		$row = $this->repo->find($id);
		if ($row === NULL)
		{
			show_404();
			return;
		}

		$data = array(
			'title'  => 'Detail: ' . $row['nama'],
			'active' => 'daftar-pendataan',
			'row'    => $row,
			'sektor' => $this->repo->sektorOf($id),
			'from'   => $this->input->get('from', TRUE),
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/lembaga_detail', $data);
		$this->load->view('templates/footer', $data);
	}

	/** Export "Daftar Pendataan" ke CSV (dibuka Excel). */
	public function daftar_pendataan_export()
	{
		$rows = $this->repo->pendataanRows();

		$this->output->set_content_type('text/csv; charset=utf-8');
		$this->output->set_header('Content-Disposition: attachment; filename="daftar_pendataan_' . date('Ymd') . '.csv"');

		$out = fopen('php://temp', 'r+');
		// BOM agar Excel membaca UTF-8 dengan benar
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, array('ID', 'Nama Lembaga', 'Email', 'Provinsi', 'Kabupaten/Kota', 'Jenis Lembaga', 'Kepemilikan', 'Kapasitas', 'Status e-Vokasi'));
		foreach ($rows as $r)
		{
			fputcsv($out, array(
				$r['id'], $r['nama'], $r['email'], $r['provinsi'], $r['kota'],
				$r['jenis'], $r['ownership'],
				$r['kapasitas'] === NULL ? '' : $r['kapasitas'],
				$r['evokasi'],
			));
		}
		rewind($out);
		$this->output->set_output(stream_get_contents($out));
		fclose($out);
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
