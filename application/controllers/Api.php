<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Api
 *
 * Endpoint JSON untuk dashboard peta vokasi. Semua response Content-Type
 * application/json. Semua input filter di-whitelist di sini (DASHBOARD_SPEC 1.5)
 * — input user TIDAK PERNAH dipakai untuk membentuk path file.
 */
class Api extends CI_Controller {

	/** Field yang boleh dipakai sebagai filter (whitelist). */
	private $allowed = array(
		'provinsi', 'kota_slug', 'pulau', 'ownership', 'jenis',
		'sektor_slug', 'jabatan_slug', 'status_legalitas',
		'kapasitas_min', 'kapasitas_max', 'coord_source', 'q', 'only_original',
	);

	public function __construct()
	{
		parent::__construct();
		$this->load->library('Vokasi_repo', NULL, 'repo');
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/** Kirim array/objek sebagai JSON. */
	private function json($data, $status = 200)
	{
		$this->output
			->set_status_header($status)
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode($data));
	}

	/**
	 * Ambil hanya parameter GET yang ada di whitelist, dibersihkan.
	 * @return array
	 */
	private function filters()
	{
		$p = array();
		foreach ($this->allowed as $key)
		{
			$val = $this->input->get($key, TRUE); // TRUE = XSS clean
			if ($val === NULL || $val === '' || $val === FALSE)
			{
				continue;
			}

			switch ($key)
			{
				case 'kapasitas_min':
				case 'kapasitas_max':
					$p[$key] = (int) $val;
					break;

				case 'only_original':
					$p[$key] = in_array($val, array('1', 'true', 'on', 'yes'), TRUE);
					break;

				case 'provinsi':
				case 'sektor_slug':
				case 'jabatan_slug':
					// multi-value: array atau string koma. Batasi panjang wajar.
					$p[$key] = is_array($val)
						? array_slice($val, 0, 50)
						: $val;
					break;

				case 'q':
					$p[$key] = substr((string) $val, 0, 100);
					break;

				default:
					$p[$key] = (string) $val;
					break;
			}
		}
		return $p;
	}

	// ---------------------------------------------------------------------
	// Endpoints
	// ---------------------------------------------------------------------

	/** GET /api/summary */
	public function summary()
	{
		$this->json($this->repo->summary());
	}

	/** GET /api/refs — untuk isi dropdown filter. */
	public function refs()
	{
		$this->json($this->repo->refs());
	}

	/** GET /api/points?<filters> — array map_points terfilter. */
	public function points()
	{
		$this->json($this->repo->points($this->filters()));
	}

	/** GET /api/lembaga/{id} — detail + sektor/jabatan + info duplikat. */
	public function lembaga($id = NULL)
	{
		if ($id === NULL || ! ctype_digit((string) $id))
		{
			return $this->json(array('error' => 'ID tidak valid'), 400);
		}

		$row = $this->repo->find($id);
		if ($row === NULL)
		{
			return $this->json(array('error' => 'Lembaga tidak ditemukan'), 404);
		}

		$this->json(array(
			'lembaga'   => $row,
			'sektor'    => $this->repo->sektorOf($id),
			'katalog'   => $this->repo->katalogOf($id),
			'duplikat'  => $this->repo->dupGroupOf($row),
		));
	}

	/**
	 * GET /api/lembaga_list?ids=1,2,3
	 * Subset kolom untuk popup "List Lembaga Vokasi" saat cluster peta diklik.
	 */
	public function lembaga_list()
	{
		$raw = $this->input->get('ids', TRUE);
		if ($raw === NULL || $raw === '' || $raw === FALSE)
		{
			return $this->json(array());
		}

		$parts = is_array($raw) ? $raw : explode(',', (string) $raw);
		$ids = array();
		foreach ($parts as $p)
		{
			$p = trim((string) $p);
			if ($p !== '' && ctype_digit($p))
			{
				$ids[] = (int) $p;
			}
			if (count($ids) >= 2000) break; // batas wajar 1 cluster
		}

		$this->json($this->repo->listByIds($ids));
	}

	/** GET /api/choropleth?metric=jumlah|kapasitas|kepadatan_sektor */
	public function choropleth()
	{
		$metric = $this->input->get('metric', TRUE);
		$allowed = array('jumlah', 'kapasitas', 'kepadatan_sektor');
		if ( ! in_array($metric, $allowed, TRUE))
		{
			$metric = 'jumlah';
		}
		$this->json($this->repo->choropleth($metric));
	}

	/** GET /api/stats?<filters> — breakdown chart, ikut filter aktif. */
	public function stats()
	{
		$this->json($this->repo->stats($this->filters()));
	}

	/** GET /api/gap — matriks provinsi x sektor. */
	public function gap()
	{
		$this->json($this->repo->aggProvinsiSektor());
	}

	/** GET /api/pendataan_list — daftar lembaga + sektor untuk modal drill-down. */
	public function pendataan_list()
	{
		$this->json($this->repo->pendataanListFull());
	}
}
