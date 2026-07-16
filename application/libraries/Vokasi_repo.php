<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Vokasi_repo
 *
 * Repository file-based untuk data lembaga vokasi (Fase 1: JSON, tanpa DB).
 * Semua JSON di-decode sekali lalu di-cache di properti STATIS supaya tidak
 * di-decode ulang tiap pemanggilan (lihat DASHBOARD_SPEC 1.2).
 *
 * ATURAN KRITIS (DASHBOARD_SPEC bagian 2):
 *  - 2.1 Jangan SUM kapasitas dari relasi sektor. Dedup lembaga_id dulu.
 *  - 2.2 Semua agregat default WAJIB filter is_primary = true.
 *  - 2.3 coord_source dibedakan (ditangani di frontend).
 *  - 2.4 Kapasitas skala log untuk marker (ditangani di frontend).
 */
class Vokasi_repo {

	/** Cache hasil json_decode per-file, berlaku lintas instance dalam 1 request. */
	private static $cache = array();

	/** Cache turunan (index by id, subset primary, dll). */
	private static $derived = array();

	/** Direktori data JSON. */
	private $dir;

	public function __construct()
	{
		$this->dir = APPPATH . 'data/vokasi/';
	}

	// ---------------------------------------------------------------------
	// Loader dasar
	// ---------------------------------------------------------------------

	/**
	 * Baca & decode satu file JSON (tanpa ekstensi). Hasil di-cache statis.
	 * @param string $name
	 * @return array
	 */
	private function load($name)
	{
		if (isset(self::$cache[$name]))
		{
			return self::$cache[$name];
		}

		$path = $this->dir . $name . '.json';
		$data = array();

		if (is_file($path))
		{
			$raw = file_get_contents($path);
			$decoded = json_decode($raw, TRUE);
			if (is_array($decoded))
			{
				$data = $decoded;
			}
		}

		self::$cache[$name] = $data;
		return $data;
	}

	// ---------------------------------------------------------------------
	// Akses tabel mentah
	// ---------------------------------------------------------------------

	/** Semua 887 baris lembaga. */
	public function all()          { return $this->load('lembaga'); }

	/** Semua relasi lembaga x sektor x jabatan (3.547 baris). */
	public function relations()    { return $this->load('lembaga_sektor'); }

	/** Payload ringan map_points (776, sudah primary+mappable). */
	public function mapPoints()    { return $this->load('map_points'); }

	/** KPI global. */
	public function summary()      { return $this->load('summary'); }

	/** Agregat provinsi pre-computed (untuk choropleth). */
	public function aggProvinsi()  { return $this->load('agg_provinsi'); }

	/** Matriks provinsi x sektor (gap analysis). */
	public function aggProvinsiSektor() { return $this->load('agg_provinsi_sektor'); }

	// ---------------------------------------------------------------------
	// Subset & index turunan
	// ---------------------------------------------------------------------

	/** Hanya lembaga is_primary = true (776) — dasar semua agregat (2.2). */
	public function primary()
	{
		if (isset(self::$derived['primary']))
		{
			return self::$derived['primary'];
		}

		$out = array();
		foreach ($this->all() as $r)
		{
			if ( ! empty($r['is_primary']))
			{
				$out[] = $r;
			}
		}

		self::$derived['primary'] = $out;
		return $out;
	}

	/** Index lembaga by id (semua 887) untuk find() cepat. */
	private function indexById()
	{
		if (isset(self::$derived['by_id']))
		{
			return self::$derived['by_id'];
		}

		$idx = array();
		foreach ($this->all() as $r)
		{
			$idx[$r['id']] = $r;
		}

		self::$derived['by_id'] = $idx;
		return $idx;
	}

	/** Detail 1 lembaga by id (dari seluruh 887, termasuk duplikat). */
	public function find($id)
	{
		$idx = $this->indexById();
		$id = (int) $id;
		return isset($idx[$id]) ? $idx[$id] : NULL;
	}

	/** Relasi sektor/jabatan milik 1 lembaga. */
	public function sektorOf($id)
	{
		$id = (int) $id;
		$out = array();
		foreach ($this->relations() as $rel)
		{
			if ((int) $rel['lembaga_id'] === $id)
			{
				$out[] = $rel;
			}
		}
		return $out;
	}

	/** Lembaga (primary) di satu provinsi (kode BPS). */
	public function byProvinsi($kode)
	{
		$kode = (string) $kode;
		$out = array();
		foreach ($this->primary() as $r)
		{
			if ((string) $r['provinsi_kode'] === $kode)
			{
				$out[] = $r;
			}
		}
		return $out;
	}

	/** Lembaga lain dalam grup duplikat yang sama (untuk badge detail). */
	public function dupGroupOf($row)
	{
		if (empty($row['dup_group']))
		{
			return array();
		}
		$out = array();
		foreach ($this->all() as $r)
		{
			if ($r['dup_group'] === $row['dup_group'] && $r['id'] !== $row['id'])
			{
				$out[] = array(
					'id'         => $r['id'],
					'uid'        => $r['uid'],
					'email'      => $r['email'],
					'is_primary' => $r['is_primary'],
				);
			}
		}
		return $out;
	}

	// ---------------------------------------------------------------------
	// Referensi (untuk dropdown filter)
	// ---------------------------------------------------------------------

	public function refs()
	{
		return array(
			'provinsi' => $this->load('ref_provinsi'),
			'kota'     => $this->load('ref_kota'),
			'sektor'   => $this->load('ref_sektor'),
			'jabatan'  => $this->load('ref_jabatan'),
			'pulau'    => $this->pulauList(),
		);
	}

	/** Daftar pulau unik + jumlah lembaga primary. */
	private function pulauList()
	{
		$acc = array();
		foreach ($this->primary() as $r)
		{
			$p = $r['pulau'];
			if ($p === NULL || $p === '') continue;
			if ( ! isset($acc[$p])) $acc[$p] = 0;
			$acc[$p]++;
		}
		$out = array();
		foreach ($acc as $nama => $jml)
		{
			$out[] = array('nama' => $nama, 'jumlah_lembaga' => $jml);
		}
		usort($out, function ($a, $b) { return $b['jumlah_lembaga'] - $a['jumlah_lembaga']; });
		return $out;
	}

	// ---------------------------------------------------------------------
	// Filter inti
	// ---------------------------------------------------------------------

	/**
	 * Kembalikan set lembaga_id (dedup) yang cocok filter sektor/jabatan.
	 * Ini implementasi aturan 2.1: resolve relasi -> id unik dulu.
	 * @return array|null  null = tidak ada filter sektor/jabatan
	 */
	private function idsBySektorJabatan($sektorSlugs, $jabatanSlugs)
	{
		if (empty($sektorSlugs) && empty($jabatanSlugs))
		{
			return NULL;
		}

		$sektorSet  = $sektorSlugs  ? array_flip($sektorSlugs)  : NULL;
		$jabatanSet = $jabatanSlugs ? array_flip($jabatanSlugs) : NULL;

		$ids = array();
		foreach ($this->relations() as $rel)
		{
			if ($sektorSet !== NULL && ! isset($sektorSet[$rel['sektor_slug']]))
			{
				continue;
			}
			if ($jabatanSet !== NULL && ! isset($jabatanSet[$rel['jabatan_slug']]))
			{
				continue;
			}
			$ids[(int) $rel['lembaga_id']] = TRUE; // dedup by key
		}
		return $ids; // map id => true
	}

	/**
	 * Filter lembaga berdasarkan parameter (sudah divalidasi/whitelist di controller).
	 * Default hanya primary (2.2). Set $primaryOnly=false untuk pencarian/detail.
	 *
	 * @param array $p
	 * @param bool  $primaryOnly
	 * @return array daftar lembaga penuh
	 */
	public function filter(array $p, $primaryOnly = TRUE)
	{
		$rows = $primaryOnly ? $this->primary() : $this->all();

		// Normalisasi multi-value
		$provinsi = $this->asList($p, 'provinsi');
		$sektor   = $this->asList($p, 'sektor_slug');
		$jabatan  = $this->asList($p, 'jabatan_slug');

		// Resolusi sektor/jabatan -> set id unik (2.1)
		$idSet = $this->idsBySektorJabatan($sektor, $jabatan);

		$kota    = isset($p['kota_slug'])       ? $p['kota_slug']       : NULL;
		$pulau   = isset($p['pulau'])           ? $p['pulau']           : NULL;
		$owner   = isset($p['ownership'])       ? $p['ownership']       : NULL;
		$jenis   = isset($p['jenis'])           ? $p['jenis']           : NULL;
		$legal   = isset($p['status_legalitas'])? $p['status_legalitas']: NULL;
		$coord   = isset($p['coord_source'])    ? $p['coord_source']    : NULL;
		$kmin    = isset($p['kapasitas_min'])   ? (int) $p['kapasitas_min'] : NULL;
		$kmax    = isset($p['kapasitas_max'])   ? (int) $p['kapasitas_max'] : NULL;
		$q       = isset($p['q']) && $p['q'] !== '' ? $this->norm($p['q']) : NULL;
		$onlyReal = ! empty($p['only_original']); // toggle "hanya koordinat asli"

		$out = array();
		foreach ($rows as $r)
		{
			if ($idSet !== NULL && ! isset($idSet[(int) $r['id']]))          continue;
			if ($provinsi && ! in_array((string) $r['provinsi_kode'], $provinsi, TRUE)) continue;
			if ($kota  !== NULL && $r['kota_slug'] !== $kota)                continue;
			if ($pulau !== NULL && $r['pulau'] !== $pulau)                   continue;
			if ($owner !== NULL && $r['ownership'] !== $owner)               continue;
			if ($jenis !== NULL && $r['jenis'] !== $jenis)                   continue;
			if ($legal !== NULL && $r['status_legalitas'] !== $legal)        continue;
			if ($coord !== NULL && $r['coord_source'] !== $coord)            continue;
			if ($onlyReal && $r['coord_source'] !== 'original')              continue;

			$kap = $r['kapasitas'] === NULL ? 0 : (int) $r['kapasitas'];
			if ($kmin !== NULL && $kap < $kmin)                              continue;
			if ($kmax !== NULL && $kap > $kmax)                              continue;

			if ($q !== NULL && strpos($this->norm($r['nama']), $q) === FALSE) continue;

			$out[] = $r;
		}
		return $out;
	}

	/**
	 * Versi ringkas untuk peta (kunci disingkat spt map_points.json).
	 * @return array
	 */
	public function points(array $p)
	{
		$rows = $this->filter($p, TRUE);
		$out = array();
		foreach ($rows as $r)
		{
			$out[] = array(
				'id'  => $r['id'],
				'n'   => $r['nama'],
				'lat' => $r['lat'],
				'lng' => $r['lng'],
				'p'   => $r['provinsi_kode'],
				'o'   => ($r['ownership'] === 'Pemerintah') ? 'P' : 'N',
				'k'   => $r['kapasitas'] === NULL ? 0 : (int) $r['kapasitas'],
				'cs'  => ($r['coord_source'] === 'original') ? 'o' : 'c',
			);
		}
		return $out;
	}

	/**
	 * Breakdown untuk chart samping — ikut filter aktif.
	 * @return array
	 */
	public function stats(array $p)
	{
		$rows = $this->filter($p, TRUE);

		// Set id lembaga hasil filter -> untuk agregasi relasi (2.1: dedup)
		$idSet = array();
		$ownership = array('Pemerintah' => 0, 'Non Pemerintah' => 0);
		$prov = array();
		$totalKapasitas = 0;

		// Funnel verifikasi
		$fun = array(
			'legalitas' => array('accepted'=>0,'rejected'=>0,'pending'=>0,'not_submitted'=>0,'revised'=>0),
			'fasilitas' => array('accepted'=>0,'rejected'=>0,'pending'=>0,'not_submitted'=>0,'revised'=>0),
			'program'   => array('accepted'=>0,'rejected'=>0,'pending'=>0,'not_submitted'=>0,'revised'=>0),
		);

		foreach ($rows as $r)
		{
			$idSet[(int) $r['id']] = TRUE;
			$totalKapasitas += ($r['kapasitas'] === NULL ? 0 : (int) $r['kapasitas']);

			$o = $r['ownership'];
			if (isset($ownership[$o])) $ownership[$o]++;

			$pk = $r['provinsi'];
			if ( ! isset($prov[$pk])) $prov[$pk] = 0;
			$prov[$pk]++;

			foreach (array('legalitas'=>'status_legalitas','fasilitas'=>'status_fasilitas','program'=>'status_program') as $tahap => $field)
			{
				$v = isset($r[$field]) ? $r[$field] : 'not_submitted';
				if ( ! isset($fun[$tahap][$v])) $fun[$tahap][$v] = 0;
				$fun[$tahap][$v]++;
			}
		}

		// Top sektor & jabatan dari relasi yang lembaga_id-nya lolos filter
		$sektor = array();
		$jabatan = array();
		foreach ($this->relations() as $rel)
		{
			if ( ! isset($idSet[(int) $rel['lembaga_id']])) continue;
			$s = $rel['sektor'];
			$j = $rel['jabatan'];
			if ( ! isset($sektor[$s]))  $sektor[$s]  = 0;
			if ( ! isset($jabatan[$j])) $jabatan[$j] = 0;
			$sektor[$s]++;
			$jabatan[$j]++;
		}

		return array(
			'jumlah_lembaga'  => count($rows),
			'total_kapasitas' => $totalKapasitas,
			'ownership'       => $ownership,
			'top_sektor'      => $this->topN($sektor, 10),
			'top_jabatan'     => $this->topN($jabatan, 10),
			'top_provinsi'    => $this->topN($prov, 10),
			'verifikasi'      => $fun,
		);
	}

	/**
	 * Nilai choropleth per provinsi sesuai metric.
	 * @param string $metric jumlah|kapasitas|kepadatan_sektor
	 * @return array [{provinsi_kode, provinsi, value}]
	 */
	public function choropleth($metric)
	{
		$out = array();
		foreach ($this->aggProvinsi() as $a)
		{
			switch ($metric)
			{
				case 'kapasitas':
					$val = isset($a['total_kapasitas']) ? $a['total_kapasitas'] : 0;
					break;
				case 'kepadatan_sektor':
					$val = isset($a['jumlah_sektor']) ? $a['jumlah_sektor'] : 0;
					break;
				case 'jumlah':
				default:
					$val = isset($a['jumlah_lembaga']) ? $a['jumlah_lembaga'] : 0;
					break;
			}
			$out[] = array(
				'provinsi_kode' => $a['provinsi_kode'],
				'provinsi'      => $a['provinsi'],
				'value'         => $val,
			);
		}
		return $out;
	}

	// ---------------------------------------------------------------------
	// Util privat
	// ---------------------------------------------------------------------

	/** Normalisasi parameter jadi list (terima array atau string koma). */
	private function asList(array $p, $key)
	{
		if ( ! isset($p[$key]) || $p[$key] === '' || $p[$key] === array())
		{
			return array();
		}
		$v = $p[$key];
		if ( ! is_array($v))
		{
			$v = explode(',', $v);
		}
		$v = array_map('trim', $v);
		return array_values(array_filter($v, function ($x) { return $x !== ''; }));
	}

	/** Lowercase + trim untuk pencarian case-insensitive. */
	private function norm($s)
	{
		return trim(strtolower((string) $s));
	}

	/** Ambil N teratas dari map [label => count], sorted desc. */
	private function topN(array $map, $n)
	{
		arsort($map);
		$out = array();
		$i = 0;
		foreach ($map as $label => $count)
		{
			if ($i++ >= $n) break;
			$out[] = array('label' => $label, 'value' => $count);
		}
		return $out;
	}
}
