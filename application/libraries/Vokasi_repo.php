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

	/**
	 * Koneksi DB aktif (mode hybrid). Sentinel:
	 *   FALSE = belum dicek, NULL = DB tidak tersedia (fallback JSON), objek = siap.
	 */
	private $ci_db = FALSE;

	public function __construct()
	{
		$this->dir = APPPATH . 'data/vokasi/';
	}

	/**
	 * Ambil koneksi DB CI bila tersedia. db_debug=FALSE membuat kegagalan
	 * koneksi tidak menghentikan app — di sini kita cek conn_id lalu fallback
	 * ke JSON bila kosong. Dicek sekali per instance.
	 * @return object|null
	 */
	private function db()
	{
		if ($this->ci_db !== FALSE)
		{
			return $this->ci_db; // sudah dicek (objek atau NULL)
		}

		$CI =& get_instance();
		$db = @$CI->load->database('default', TRUE);
		$this->ci_db = ($db !== FALSE && is_object($db) && ! empty($db->conn_id))
			? $db
			: NULL;

		return $this->ci_db;
	}

	/** TRUE bila data dashboard sedang bersumber dari database. */
	public function isDb()
	{
		return $this->db() !== NULL;
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

	/**
	 * Semua lembaga.
	 * Mode DB: dari view dashboard_vokasi_detail, di-enrich koordinat & field
	 * geo/dedup dari JSON (join by id). Mode JSON: apa adanya dari file.
	 */
	public function all()
	{
		if (isset(self::$derived['lembaga']))
		{
			return self::$derived['lembaga'];
		}

		$db = $this->db();
		$out = ($db === NULL)
			? $this->load('lembaga')            // fallback penuh ke JSON
			: $this->buildLembagaFromDb($db);   // hybrid: DB + enrichment JSON

		self::$derived['lembaga'] = $out;
		return $out;
	}

	/** Semua relasi lembaga x sektor x jabatan. */
	public function relations()
	{
		if (isset(self::$derived['relations']))
		{
			return self::$derived['relations'];
		}

		$db = $this->db();
		$out = ($db === NULL)
			? $this->load('lembaga_sektor')
			: $this->buildRelationsFromDb($db);

		self::$derived['relations'] = $out;
		return $out;
	}

	/** Semua katalog pelatihan (join via lembaga_id). */
	public function katalog()
	{
		if (isset(self::$derived['katalog']))
		{
			return self::$derived['katalog'];
		}

		$db = $this->db();
		$out = ($db === NULL)
			? $this->load('lembaga_katalog')
			: $this->buildKatalogFromDb($db);

		self::$derived['katalog'] = $out;
		return $out;
	}

	// ---------------------------------------------------------------------
	// Pembentuk data dari DB (mode hybrid) + enrichment JSON
	// ---------------------------------------------------------------------

	/**
	 * Bentuk baris lembaga dari view DB ke SHAPE yang sama dengan lembaga.json,
	 * sehingga seluruh logika filter/stats/peta di bawah tetap dipakai apa adanya.
	 *
	 * Kolom yang tidak ada di DB (lat/lng, provinsi_kode, pulau, slug kota,
	 * is_primary, dup_group, uid, nomor_*) diambil dari JSON via join by id.
	 * Untuk lembaga baru yang belum ada di JSON: koordinat NULL (tak muncul di
	 * peta), kode provinsi dicari dari nama, is_primary default TRUE.
	 */
	private function buildLembagaFromDb($db)
	{
		$rows = $db->get('dashboard_vokasi_detail')->result_array();
		$json = $this->jsonLembagaIndex();
		$prov = $this->provMaps();

		$out = array();
		foreach ($rows as $r)
		{
			$id = (int) $r['vok_id'];
			$j  = isset($json[$id]) ? $json[$id] : NULL;

			// Kode/nama/pulau provinsi: utamakan JSON (bersih), lalu lookup nama DB.
			if ($j !== NULL)
			{
				$prov_kode = $j['provinsi_kode'];
				$prov_nama = $j['provinsi'];
				$pulau     = $j['pulau'];
			}
			else
			{
				$key = strtoupper(trim((string) $r['vok_province']));
				$prov_kode = isset($prov['name2kode'][$key]) ? $prov['name2kode'][$key] : NULL;
				$prov_nama = ($prov_kode !== NULL) ? $prov['kode2nama'][$prov_kode] : $r['vok_province'];
				$pulau     = ($prov_kode !== NULL) ? $prov['kode2pulau'][$prov_kode] : NULL;
			}

			// Kapasitas: utamakan nilai DB (fresh), fallback JSON.
			$kap = $this->parseKapasitas($r['vok_training_capacity']);
			if ($kap === NULL && $j !== NULL)
			{
				$kap = ($j['kapasitas'] === NULL) ? NULL : (int) $j['kapasitas'];
			}

			$out[] = array(
				'id'               => $id,
				'uid'              => $j !== NULL ? $j['uid'] : NULL,
				'nama'             => $r['vok_name'],
				'email'            => $r['vok_email'],
				'provinsi_kode'    => $prov_kode,
				'provinsi'         => $prov_nama,
				'pulau'            => $pulau,
				'kota'             => $r['vok_district'],
				'kota_slug'        => $j !== NULL ? $j['kota_slug'] : $this->slugify($r['vok_district']),
				'ownership'        => $r['own_name'],
				'jenis'            => $r['vok_institution_form'],
				'tipe_lembaga'     => $r['type_name'],      // tambahan dari DB (LPK/SMK/...)
				'kapasitas'        => $kap,
				// Koordinat hanya dari JSON — DB belum punya. Tanpa JSON = NULL.
				'lat'              => $j !== NULL ? $j['lat'] : NULL,
				'lng'              => $j !== NULL ? $j['lng'] : NULL,
				'coord_source'     => $j !== NULL ? $j['coord_source'] : NULL,
				'nomor_registrasi' => $j !== NULL ? $j['nomor_registrasi'] : NULL,
				'nomor_legalitas'  => $j !== NULL ? $j['nomor_legalitas'] : NULL,
				'status_legalitas' => $r['ver_legality_status'],
				'status_fasilitas' => $r['ver_facility_status'],
				'status_program'   => $r['ver_program_status'],
				// Dedup dari JSON; lembaga baru dianggap primary tunggal.
				'is_primary'       => $j !== NULL ? (bool) $j['is_primary'] : TRUE,
				'dup_group'        => $j !== NULL ? $j['dup_group'] : NULL,
			);
		}
		return $out;
	}

	/** Bentuk relasi sektor/jabatan dari view DB (+ slug via ref/slugify). */
	private function buildRelationsFromDb($db)
	{
		$rows = $db->get('dashboard_vokasi_detail_sektor')->result_array();
		$sMap = $this->slugMap('ref_sektor');
		$jMap = $this->slugMap('ref_jabatan');

		$out = array();
		foreach ($rows as $r)
		{
			$sektor  = $r['sector_name'];
			$jabatan = $r['jab_name'];
			$out[] = array(
				'lembaga_id'   => (int) $r['vok_id'],
				'sektor'       => $sektor,
				'sektor_slug'  => isset($sMap[$sektor])  ? $sMap[$sektor]  : $this->slugify($sektor),
				'jabatan'      => $jabatan,
				'jabatan_slug' => isset($jMap[$jabatan]) ? $jMap[$jabatan] : $this->slugify($jabatan),
			);
		}
		return $out;
	}

	/** Bentuk katalog pelatihan dari view DB ke shape lembaga_katalog.json. */
	private function buildKatalogFromDb($db)
	{
		$rows = $db->get('dashboard_vokasi_katalog')->result_array();
		$out = array();
		foreach ($rows as $r)
		{
			$out[] = array(
				'id'               => (int) $r['cat_id'],
				'lembaga_id'       => (int) $r['vok_id'],
				'judul'            => $r['cat_title'],
				'kategori'         => $r['cat_category'],
				'deskripsi'        => $r['cat_description'],
				'tanggal_mulai'    => $r['cat_start_date'],
				'tanggal_selesai'  => $r['cat_end_date'],
				'jam_pelatihan'    => $r['cat_number_of_hours'],
				'kuota'            => $r['cat_quota'],
				'biaya'            => $r['cat_price'],
				'status'           => $r['cat_status'],
			);
		}
		return $out;
	}

	// ---------------------------------------------------------------------
	// Peta bantu untuk enrichment (dimensi diambil dari ref JSON yang stabil)
	// ---------------------------------------------------------------------

	/** Index lembaga JSON by id (untuk join koordinat/geo). */
	private function jsonLembagaIndex()
	{
		if (isset(self::$derived['json_lembaga_idx']))
		{
			return self::$derived['json_lembaga_idx'];
		}
		$idx = array();
		foreach ($this->load('lembaga') as $r)
		{
			$idx[(int) $r['id']] = $r;
		}
		self::$derived['json_lembaga_idx'] = $idx;
		return $idx;
	}

	/**
	 * Peta provinsi dari ref_provinsi.json: nama(UPPER)=>kode, kode=>nama, kode=>pulau.
	 * Ditambah alias nama panjang yang tidak ada di ref (DKI/DIY).
	 */
	private function provMaps()
	{
		if (isset(self::$derived['prov_maps']))
		{
			return self::$derived['prov_maps'];
		}

		$name2kode = array();
		$kode2nama = array();
		$kode2pulau = array();
		foreach ($this->load('ref_provinsi') as $p)
		{
			$kode = (string) $p['kode'];
			$name2kode[strtoupper(trim($p['nama']))] = $kode;
			$kode2nama[$kode]  = $p['nama'];
			$kode2pulau[$kode] = $p['pulau'];
		}

		// Alias nama panjang / ejaan lain yang muncul di DB.
		$alias = array(
			'DAERAH KHUSUS IBUKOTA JAKARTA' => '31',
			'DKI JAKARTA'                   => '31',
			'DAERAH ISTIMEWA YOGYAKARTA'    => '34',
			'DI YOGYAKARTA'                 => '34',
			'YOGYAKARTA'                    => '34',
		);
		foreach ($alias as $k => $v)
		{
			if (isset($kode2nama[$v])) $name2kode[$k] = $v;
		}

		$maps = array('name2kode' => $name2kode, 'kode2nama' => $kode2nama, 'kode2pulau' => $kode2pulau);
		self::$derived['prov_maps'] = $maps;
		return $maps;
	}

	/** Peta nama=>slug dari file ref (ref_sektor / ref_jabatan). */
	private function slugMap($ref)
	{
		$key = 'slugmap_' . $ref;
		if (isset(self::$derived[$key]))
		{
			return self::$derived[$key];
		}
		$map = array();
		foreach ($this->load($ref) as $r)
		{
			if (isset($r['nama'], $r['slug'])) $map[$r['nama']] = $r['slug'];
		}
		self::$derived[$key] = $map;
		return $map;
	}

	/** Slugify sederhana: lowercase, non-alfanumerik jadi tanda hubung. */
	private function slugify($s)
	{
		$s = strtolower(trim((string) $s));
		$s = preg_replace('/[^a-z0-9]+/u', '-', $s);
		return trim($s, '-');
	}

	/**
	 * Parse kapasitas dari string DB yang berantakan ("3.000", ".100", "55").
	 * Titik/koma diperlakukan sebagai pemisah ribuan → dibuang.
	 * @return int|null
	 */
	private function parseKapasitas($v)
	{
		if ($v === NULL) return NULL;
		$digits = preg_replace('/[^0-9]/', '', (string) $v);
		return ($digits === '') ? NULL : (int) $digits;
	}

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

	/** Index katalog by lembaga_id (dibangun sekali per request). */
	private function katalogIndex()
	{
		if (isset(self::$derived['katalog_by_lembaga']))
		{
			return self::$derived['katalog_by_lembaga'];
		}

		$idx = array();
		foreach ($this->katalog() as $k)
		{
			$lid = (int) $k['lembaga_id'];
			if ( ! isset($idx[$lid])) $idx[$lid] = array();
			$idx[$lid][] = $k;
		}

		self::$derived['katalog_by_lembaga'] = $idx;
		return $idx;
	}

	/** Katalog pelatihan milik 1 lembaga, diurut tanggal mulai. */
	public function katalogOf($id)
	{
		$idx = $this->katalogIndex();
		$id = (int) $id;
		$out = isset($idx[$id]) ? $idx[$id] : array();
		usort($out, function ($a, $b) {
			return strcmp((string) $a['tanggal_mulai'], (string) $b['tanggal_mulai']);
		});
		return $out;
	}

	/**
	 * Subset kolom untuk popup "List Lembaga Vokasi" (dipanggil saat cluster diklik).
	 * @param array $ids daftar id (sudah divalidasi di controller)
	 * @return array
	 */
	public function listByIds(array $ids)
	{
		if (empty($ids)) return array();

		$idx = $this->indexById();
		$seen = array();
		$out = array();
		foreach ($ids as $i)
		{
			$id = (int) $i;
			if (isset($seen[$id]) || ! isset($idx[$id])) continue;
			$seen[$id] = TRUE;
			$r = $idx[$id];
			$out[] = array(
				'id'               => $r['id'],
				'nama'             => $r['nama'],
				'provinsi'         => $r['provinsi'],
				'kota'             => $r['kota'],
				'ownership'        => $r['ownership'],
				'nomor_registrasi' => $r['nomor_registrasi'],
				'nomor_legalitas'  => $r['nomor_legalitas'],
			);
		}
		usort($out, function ($a, $b) { return strcasecmp($a['nama'], $b['nama']); });
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
			// Lewati lembaga tanpa koordinat (mis. entri DB baru yang belum
			// dijodohkan ke koordinat) — tak bisa digambar di peta.
			if ($r['lat'] === NULL || $r['lng'] === NULL)
			{
				continue;
			}
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
