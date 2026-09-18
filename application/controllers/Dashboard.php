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
			// Kartu sambutan (redesign) — angka nyata dari view dashboard_vokasi_detail.
			'header'     => $this->repo->headerStats(),
			// 3 KPI verifikasi (fasilitas/program/keseluruhan) — DB, tanpa join.
			'kpi'        => $this->repo->verifikasiKpi(),
			// Komposisi Status (donut) + Bentuk Lembaga (bar) — DB, tanpa join.
			'komposisi'  => $this->repo->komposisiStatusBentuk(),
			// Peta persebaran + Sebaran Provinsi Top 5 (legalitas accepted) — DB, tanpa join.
			'sebaran'    => $this->repo->sebaranLegalitas(5),
			// Jenis Lembaga (bar) + Sektor Spesialisasi Top 5 (bar) — DB, tanpa join.
			'jenis_sektor' => $this->repo->jenisDanSektor(5),
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/index', $data);
		$this->load->view('templates/footer', $data);
	}

	/**
	 * Peta Sebaran Lembaga Vokasi (dashboard peta interaktif LAMA).
	 * Dipindah dari index() ke menu tersendiri; data disajikan Api (JSON) +
	 * digambar dashboard.js (Leaflet cluster + filter + chart).
	 */
	public function peta()
	{
		$data = array(
			'title'      => 'Peta Sebaran Lembaga Vokasi',
			'active'     => 'peta',
			'map_center' => array(-2.5, 118.0),
			'map_zoom'   => 5,
			'summary'    => $this->repo->summary(),
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/peta', $data);
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
	 * Menu "Monitoring Pelatihan PMI" — KPI + chart peserta pelatihan PMI.
	 * Seluruh angka dari view dashboard_pelatihan_detail (repo->pelatihanStats()).
	 */
	public function pelatihan()
	{
		$data = array(
			'title'  => 'Monitoring Pelatihan PMI',
			'active' => 'pelatihan',
			'stats'  => $this->repo->pelatihanStats(),
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/pelatihan', $data);
		$this->load->view('templates/footer', $data);
	}

	/**
	 * Menu "Monitoring Penempatan Peserta Pelatihan" — KPI + chart penempatan.
	 * Sumber: DB SISKO P2MI ($db['sisko']) lewat repo->penempatanStats().
	 * Lihat dokumen "Requirement Dashboard Penempatan Peserta Pelatihan.docx".
	 */
	public function penempatan()
	{
		$filter = $this->penempatanFilter();
		$all    = $this->repo->penempatanRows();
		$rows   = $filter ? $this->repo->penempatanRows($filter) : $all;

		$data = array(
			'title'   => 'Monitoring Penempatan Peserta Pelatihan',
			'active'  => 'penempatan',
			'stats'   => $this->repo->penempatanStats($rows),
			'rows'    => $rows,
			'options' => $this->repo->penempatanOptions($all),
			'filter'  => $filter,
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/penempatan', $data);
		$this->load->view('templates/footer', $data);
	}

	/** Filter penempatan dari query string (hanya dimensi yang dikenal, nilai kosong dibuang). */
	private function penempatanFilter()
	{
		$f = array();
		foreach (Vokasi_repo::$penempatanDims as $key)
		{
			$v = trim((string) $this->input->get($key, TRUE));
			if ($v !== '') { $f[$key] = $v; }
		}
		return $f;
	}

	/**
	 * Kirim tabel sebagai file .xls (HTML table, content-type Excel). Tanpa
	 * dependency; Excel/LibreOffice membukanya langsung.
	 * ponytail: bukan xlsx asli — pakai PhpSpreadsheet bila butuh styling/formula.
	 */
	private function sendXls($filename, array $head, array $rows)
	{
		$this->output->set_content_type('application/vnd.ms-excel; charset=utf-8');
		$this->output->set_header('Content-Disposition: attachment; filename="' . $filename . '"');

		$td = function ($cells, $tag) {
			$h = '';
			foreach ($cells as $c) { $h .= "<$tag>" . html_escape((string) $c) . "</$tag>"; }
			return "<tr>$h</tr>\n";
		};
		$html = "<html><head><meta charset=\"utf-8\"></head><body><table border=\"1\">\n" . $td($head, 'th');
		foreach ($rows as $r) { $html .= $td($r, 'td'); }
		$this->output->set_output($html . "</table></body></html>");
	}

	/** Export detail penempatan (sesuai filter) ke .xls. */
	public function penempatan_export()
	{
		$rows = $this->repo->penempatanRows($this->penempatanFilter());
		$out = array();
		foreach ($rows as $r)
		{
			$out[] = array(
				$r['nama'], $r['provinsi'], $r['kabupaten'], $r['bp3mi'], $r['p3mi'], $r['negara'], $r['jabatan'], $r['status'],
				'Ya', // join akun inner → semua baris punya akun
				$r['has_penempatan'] ? 'Ya' : 'Tidak',
				$r['has_ekpmi'] ? 'Ya' : 'Tidak',
			);
		}
		$this->sendXls('penempatan_peserta_' . date('Ymd') . '.xls',
			array('Nama PMI', 'Provinsi', 'Kabupaten/Kota', 'BP3MI', 'P3MI', 'Negara', 'Jabatan', 'Status',
				'Telah memiliki akun', 'Telah memiliki penempatan', 'Telah EKPMI'),
			$out);
	}

	/**
	 * Menu "Monitoring PMI" — tabel peserta pelatihan + flag akun/proses
	 * penempatan/E-PMI (DB sisko, repo->pmiRows()) dengan filter + export .xls.
	 */
	public function monitoring_pmi()
	{
		$filter = $this->pmiFilter();
		$all    = $this->repo->pmiRows();
		$rows   = $filter ? $this->repo->pmiRows($filter) : $all;

		$data = array(
			'title'   => 'Monitoring PMI',
			'active'  => 'monitoring-pmi',
			'rows'    => $rows,
			'options' => $this->repo->pmiOptions($all),
			'filter'  => $filter,
		);

		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view('dashboard/monitoring_pmi', $data);
		$this->load->view('templates/footer', $data);
	}

	/** Filter Monitoring PMI dari query string. */
	private function pmiFilter()
	{
		$f = array();
		foreach (array('nama', 'nik', 'provinsi', 'kabupaten', 'bp3mi', 'has_akun', 'has_pen', 'has_epmi') as $key)
		{
			$v = trim((string) $this->input->get($key, TRUE));
			if ($v !== '') { $f[$key] = $v; }
		}
		return $f;
	}

	/** Export Monitoring PMI (seluruh kolom query sumber, sesuai filter) ke .xls. */
	public function monitoring_pmi_export()
	{
		$rows = $this->repo->pmiRows($this->pmiFilter());
		$yn = function ($v) { return $v ? 'Ya' : 'Tidak'; };
		$out = array();
		foreach ($rows as $r)
		{
			$out[] = array(
				$r['event_jenis'], $r['event_nama'], $r['bp3mi_id'], $r['bp3mi'], $r['nama'], $r['nik'],
				$r['provinsi'], $r['kabupaten'], $r['tgl_daftar'],
				$yn($r['has_akun']), $yn($r['has_pen']), $yn($r['has_epmi']),
			);
		}
		$this->sendXls('monitoring_pmi_' . date('Ymd') . '.xls',
			array('Jenis Event', 'Nama Event', 'ID Penyelenggara', 'Penyelenggara (BP3MI)', 'Nama PMI', 'NIK',
				'Provinsi', 'Kabupaten/Kota', 'Tanggal Daftar', 'Akun SiskoP2MI', 'Proses Penempatan', 'E-KPMI'),
			$out);
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
