<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Vokasi_repo
 *
 * Repository data lembaga vokasi. Sumber utama = database (view
 * dashboard_vokasi_*); JSON hanya dipakai untuk enrichment field yang belum ada
 * di DB (geo/dedup) dan agregat pre-computed (Fase 2). Fallback dataset ke JSON
 * saat DB down DIMATIKAN — koneksi DB wajib (lihat requireDb()).
 * JSON di-decode sekali lalu di-cache di properti STATIS (lihat DASHBOARD_SPEC 1.2).
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
	 * Koneksi DB aktif. Sentinel:
	 *   FALSE = belum dicek, NULL = DB tidak tersedia, objek = siap.
	 * Catatan: NULL kini memicu error di requireDb() (bukan lagi fallback JSON).
	 */
	private $ci_db = FALSE;

	public function __construct()
	{
		$this->dir = APPPATH . 'data/vokasi/';
	}

	/**
	 * Ambil koneksi DB CI bila tersedia. db_debug=FALSE membuat kegagalan
	 * koneksi tidak menghentikan app di level driver — di sini kita cek conn_id
	 * dan kembalikan NULL bila kosong (penanganan wajib/tidak ada di requireDb()).
	 * Dicek sekali per instance.
	 * @return object|null
	 */
	private function db()
	{
		if ($this->ci_db !== FALSE)
		{
			return $this->ci_db; // sudah dicek (objek atau NULL)
		}

		// Driver 'postgre' butuh ekstensi pgsql (pg_connect). Bila belum terpasang
		// di server, JANGAN panggil load->database (akan fatal) — fallback ke JSON.
		if ( ! function_exists('pg_connect'))
		{
			log_message('error', 'Ekstensi PHP pgsql belum aktif — koneksi DB tidak bisa dibuat.');
			$this->ci_db = NULL;
			return NULL;
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
	 * Ambil koneksi DB — WAJIB. Fallback dataset ke JSON DIMATIKAN (permintaan:
	 * data dari DB saja) supaya app tidak diam-diam menyajikan data lama saat DB
	 * down. Bila koneksi gagal → hentikan dengan pesan jelas, bukan fallback senyap.
	 * @return object
	 */
	private function requireDb()
	{
		$db = $this->db();
		if ($db === NULL)
		{
			log_message('error', 'Vokasi_repo: DB wajib tapi koneksi gagal — fallback JSON dinonaktifkan.');
			show_error(
				'Koneksi ke database gagal. Dashboard hanya menyajikan data dari database (fallback JSON dinonaktifkan). Coba lagi setelah koneksi pulih.',
				503,
				'Database Tidak Tersedia'
			);
		}
		return $db;
	}

	/**
	 * Semua lembaga — SELALU dari view dashboard_vokasi_detail, di-enrich field
	 * geo/dedup yang belum ada di DB (provinsi_kode, is_primary, dll) dari JSON.
	 */
	public function all()
	{
		if (isset(self::$derived['lembaga']))
		{
			return self::$derived['lembaga'];
		}

		$out = $this->buildLembagaFromDb($this->requireDb());
		self::$derived['lembaga'] = $out;
		return $out;
	}

	/** Semua relasi lembaga x sektor x jabatan (DB-only). */
	public function relations()
	{
		if (isset(self::$derived['relations']))
		{
			return self::$derived['relations'];
		}

		$out = $this->buildRelationsFromDb($this->requireDb());
		self::$derived['relations'] = $out;
		return $out;
	}

	/** Semua katalog pelatihan (join via lembaga_id) — DB-only. */
	public function katalog()
	{
		if (isset(self::$derived['katalog']))
		{
			return self::$derived['katalog'];
		}

		$out = $this->buildKatalogFromDb($this->requireDb());
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
	 * Koordinat kini tersedia di view (vok_lat/vok_long) → dipakai lebih dulu;
	 * JSON hanya fallback bila koordinat DB kosong. Kolom lain yang belum ada di
	 * DB (provinsi_kode, pulau, slug kota, is_primary, dup_group, uid, nomor_*)
	 * diambil dari JSON via join by id. Untuk lembaga baru tanpa koordinat DB
	 * maupun JSON: koordinat NULL (tak muncul di peta), kode provinsi dicari dari
	 * nama, is_primary default TRUE.
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

			// Koordinat: DB-ONLY (tanpa fallback JSON). Data DB yang kotor
			// (lng > 180 / titik di luar Indonesia) dikosongkan → tak digambar di peta.
			$lat = $this->parseCoord(isset($r['vok_lat'])  ? $r['vok_lat']  : NULL);
			$lng = $this->parseCoord(isset($r['vok_long']) ? $r['vok_long'] : NULL);
			if ($lat !== NULL && $lng !== NULL && $this->inIndonesia($lat, $lng))
			{
				$coord_source = 'original';        // koordinat tersimpan di DB = titik asli
			}
			else
			{
				$lat = $lng = $coord_source = NULL; // invalid/kosong; JANGAN fallback JSON
			}

			$out[] = array(
				'id'               => $id,
				'uid'              => $j !== NULL ? $j['uid'] : NULL,
				'nama'             => trim((string) $r['vok_name']),
				'email'            => trim((string) $r['vok_email']),
				'provinsi_kode'    => $prov_kode,
				'provinsi'         => $prov_nama,
				'pulau'            => $pulau,
				'kota'             => $r['vok_district'],
				'kota_slug'        => $this->slugify($r['vok_district']),
				'telepon'          => isset($r['vok_phone']) ? trim((string) $r['vok_phone']) : '',
				'ownership'        => $r['own_name'],
				'jenis'            => $r['vok_institution_form'],
				'tipe_lembaga'     => $r['type_name'],      // tambahan dari DB (LPK/SMK/...)
				'kapasitas'        => $kap,
				'lat'              => $lat,
				'lng'              => $lng,
				'coord_source'     => $coord_source,
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

	/**
	 * Parse koordinat lat/long dari view DB (bisa string desimal atau numeric).
	 * Nilai kosong, non-numerik, atau 0 dianggap tidak valid (0,0 = null island)
	 * → NULL, supaya tidak digambar di tengah laut.
	 * @return float|null
	 */
	private function parseCoord($v)
	{
		if ($v === NULL || $v === '' || ! is_numeric($v)) return NULL;
		$f = (float) $v;
		return ($f == 0.0) ? NULL : $f;
	}

	/**
	 * TRUE bila (lat,lng) berada dalam bounding box wilayah Indonesia (longgar).
	 * Dipakai menyaring koordinat DB yang jelas kotor (lng > 180, lat kutub, dll).
	 */
	private function inIndonesia($lat, $lng)
	{
		return $lat >= -11.5 && $lat <= 7.0 && $lng >= 94.0 && $lng <= 142.0;
	}

	/** Payload ringan map_points (776, sudah primary+mappable). */
	public function mapPoints()    { return $this->load('map_points'); }

	/**
	 * KPI global — dihitung LIVE dari DB (bukan lagi summary.json).
	 * Struktur dijaga sama dengan summary.json lama agar view index/wall/gap/tentang
	 * tetap jalan. Agregat kapasitas/koordinat/verifikasi atas primary (spec 2.2);
	 * cakupan wilayah & gap sektor atas seluruh baris.
	 *
	 * Catatan koordinat: sejak DB-only, tak ada lagi tingkatan centroid — koordinat
	 * yang ada = 'original' (asli DB). centroid_* = 0. "persen_asli" kini berarti
	 * % lembaga primary yang punya koordinat valid.
	 */
	public function summary()
	{
		if (isset(self::$derived['summary']))
		{
			return self::$derived['summary'];
		}

		$all     = $this->all();        // WAJIB DB (requireDb di dalam)
		$primary = $this->primary();
		$rel     = $this->relations();
		$kat     = $this->katalog();

		$totalBaris  = count($all);
		$unikPrimary = count($primary);

		// --- Cakupan wilayah + peta lembaga->provinsi (untuk gap) atas semua baris ---
		$provAll = array();
		$kotaAll = array();
		$lembagaProv = array();
		foreach ($all as $r)
		{
			if ( ! empty($r['provinsi'])) $provAll[$r['provinsi']] = TRUE;
			if ( ! empty($r['kota']))     $kotaAll[$r['kota']]     = TRUE;
			$lembagaProv[(int) $r['id']] = isset($r['provinsi']) ? $r['provinsi'] : '';
		}

		// --- Kapasitas + koordinat + verifikasi atas primary (spec 2.2) ---
		$kapTotal = 0; $kapList = array();
		$coordAsli = 0; $coordTidakAda = 0;
		$fun = array(
			'legalitas' => array('accepted'=>0,'rejected'=>0,'pending'=>0,'not_submitted'=>0,'revised'=>0),
			'fasilitas' => array('accepted'=>0,'rejected'=>0,'pending'=>0,'not_submitted'=>0,'revised'=>0),
			'program'   => array('accepted'=>0,'rejected'=>0,'pending'=>0,'not_submitted'=>0,'revised'=>0),
		);
		// Hanya lembaga terverifikasi legalitas (accepted) yang dihitung kapasitas &
		// sektor/jabatan-nya; ditolak/pending/belum submit tidak ikut.
		$verified = array();
		foreach ($primary as $r)
		{
			$isVerified = (isset($r['status_legalitas']) && $r['status_legalitas'] === 'accepted');
			if ($isVerified) $verified[(int) $r['id']] = TRUE;

			$k = ($r['kapasitas'] === NULL || ! $isVerified) ? 0 : (int) $r['kapasitas'];
			$kapTotal += $k;
			if ($k > 0) $kapList[] = $k;

			if ($r['lat'] !== NULL && $r['lng'] !== NULL) $coordAsli++;
			else $coordTidakAda++;

			foreach (array('legalitas'=>'status_legalitas','fasilitas'=>'status_fasilitas','program'=>'status_program') as $tahap => $field)
			{
				$v = ( ! empty($r[$field])) ? $r[$field] : 'not_submitted';
				if ( ! isset($fun[$tahap][$v])) $fun[$tahap][$v] = 0;
				$fun[$tahap][$v]++;
			}
		}
		sort($kapList);
		$nK = count($kapList);
		$median = $nK ? ($nK % 2 ? $kapList[intdiv($nK, 2)] : (int) (($kapList[$nK/2 - 1] + $kapList[$nK/2]) / 2)) : 0;
		$maxKap = $nK ? $kapList[$nK - 1] : 0;
		$persenAsli = $unikPrimary > 0 ? round($coordAsli / $unikPrimary * 100, 1) : 0;

		// --- Sektor/jabatan + gap provinsi x sektor (atas seluruh relasi) ---
		$sektorSet = array(); $jabatanSet = array(); $provSektor = array();
		foreach ($rel as $x)
		{
			$s = isset($x['sektor'])  ? $x['sektor']  : '';
			$j = isset($x['jabatan']) ? $x['jabatan'] : '';
			if (isset($verified[(int) $x['lembaga_id']]))
			{
				if ($s !== '') $sektorSet[$s]  = TRUE;
				if ($j !== '') $jabatanSet[$j] = TRUE;
			}
			$pv = isset($lembagaProv[(int) $x['lembaga_id']]) ? $lembagaProv[(int) $x['lembaga_id']] : '';
			if ($s !== '' && $pv !== '') $provSektor[$pv . '|' . $s] = TRUE;
		}
		$totalSektor  = count($sektorSet);
		$totalSel     = count($provAll) * $totalSektor;
		$selTerisi    = count($provSektor);

		// --- Katalog ---
		$lembagaPunyaKat = array();
		foreach ($kat as $c) $lembagaPunyaKat[(int) $c['lembaga_id']] = TRUE;
		$punyaKat = count($lembagaPunyaKat);

		$out = array(
			'generated_at' => date('c'),
			'sumber'       => array('Database main_db (view dashboard_vokasi_detail / _sektor / dashboard_vokasi_katalog)'),
			'lembaga' => array(
				'total_baris'       => $totalBaris,
				'unik_primary'      => $unikPrimary,
				'duplikat_ditandai' => $totalBaris - $unikPrimary,
			),
			'katalog' => array(
				'total_katalog'              => count($kat),
				'lembaga_punya_katalog'      => $punyaKat,
				'persen_lembaga_ada_katalog' => $unikPrimary > 0 ? round($punyaKat / $unikPrimary * 100, 1) : 0,
			),
			'kapasitas' => array(
				'total'  => $kapTotal,
				'median' => $median,
				'max'    => $maxKap,
			),
			'koordinat' => array(
				'asli'               => $coordAsli,
				'centroid_kota'      => 0,   // tak berlaku lagi (koordinat DB-only)
				'centroid_provinsi'  => 0,
				'tidak_ada'          => $coordTidakAda,
				'persen_asli'        => $persenAsli,
			),
			'wilayah' => array(
				'provinsi' => count($provAll),
				'kota'     => count($kotaAll),
			),
			'sektor' => array(
				'total_sektor'  => $totalSektor,
				'total_jabatan' => count($jabatanSet),
				'total_relasi'  => count($rel),
			),
			'verifikasi' => $fun,
			'gap_sektor' => array(
				'total_sel'   => $totalSel,
				'sel_terisi'  => $selTerisi,
				'sel_kosong'  => $totalSel - $selTerisi,
			),
			'is_db' => $this->isDb(),
		);

		self::$derived['summary'] = $out;
		return $out;
	}

	/**
	 * Angka ringkas kartu sambutan dashboard utama (redesign).
	 *
	 * Sumbernya SAMA dengan halaman Peta Sebaran (views/dashboard/peta.php), yaitu
	 * summary() — supaya kedua halaman tidak pernah menampilkan angka berbeda:
	 *   - lembaga_terdaftar       = lembaga.unik_primary
	 *                               (lembaga unik setelah duplikat ditandai, sama
	 *                               dengan KPI "Lembaga" di halaman Peta)
	 *   - terverifikasi_legalitas = verifikasi.legalitas.accepted
	 *                               (sama dengan batang Accepted pada chart
	 *                               "Tahapan Verifikasi" layer 1 di halaman Peta)
	 *
	 * Sebelumnya kedua angka ini dihitung COUNT langsung atas seluruh BARIS view
	 * dashboard_vokasi_detail, sehingga baris duplikat ikut terhitung.
	 * summary() sendiri sudah di-cache per request (self::$derived).
	 * @return array
	 */
	public function headerStats()
	{
		$s = $this->summary();

		$legal = isset($s['verifikasi']['legalitas']['accepted'])
			? (int) $s['verifikasi']['legalitas']['accepted']
			: 0;

		return array(
			'lembaga_terdaftar'       => (int) $s['lembaga']['unik_primary'],
			'terverifikasi_legalitas' => $legal,
		);
	}

	/**
	 * 4 KPI verifikasi kartu atas dashboard utama (redesign).
	 * Hanya butuh view dashboard_vokasi_detail (tanpa join) — dihitung sekali jalan
	 * via COUNT + FILTER (PostgreSQL) agar cuma 1 round-trip ke DB.
	 *   - fasilitas   : lulus verifikasi fasilitas  (ver_facility_status = 'accepted')
	 *   - program     : lulus verifikasi program    (ver_program_status  = 'accepted')
	 *   - keseluruhan : lulus SELURUH tahap         (legalitas+fasilitas+program 'accepted')
	 *   - ditolak     : belum memenuhi persyaratan  (ver_legality_status = 'rejected')
	 * 'persen' = porsi terhadap total lembaga terdaftar (dibulatkan).
	 * @return array
	 */
	public function verifikasiKpi()
	{
		$db = $this->requireDb();

		$sql = "SELECT
				count(*)                                                       AS total,
				count(*) FILTER (WHERE ver_facility_status = 'accepted')        AS fasilitas,
				count(*) FILTER (WHERE ver_program_status  = 'accepted')        AS program,
				count(*) FILTER (WHERE ver_legality_status = 'accepted'
				                   AND ver_facility_status = 'accepted'
				                   AND ver_program_status  = 'accepted')        AS keseluruhan,
				count(*) FILTER (WHERE ver_legality_status = 'rejected')        AS ditolak
			FROM dashboard_vokasi_detail";

		$row   = $db->query($sql)->row_array();
		$total = max(1, (int) $row['total']); // hindari bagi 0
		$pack  = function ($n) use ($total) {
			return array('nilai' => (int) $n, 'persen' => (int) round($n / $total * 100));
		};

		return array(
			'fasilitas'   => $pack($row['fasilitas']),
			'program'     => $pack($row['program']),
			'keseluruhan' => $pack($row['keseluruhan']),
			'ditolak'     => $pack($row['ditolak']),
		);
	}

	/**
	 * Komposisi untuk kartu "Status Lembaga Vokasi" (donut) & "Bentuk Lembaga" (bar).
	 * Keduanya cukup dari view dashboard_vokasi_detail (tanpa join).
	 *   status  : distribusi legalitas — terverifikasi(accepted)/proses(pending)/ditolak(rejected)
	 *   bentuk  : distribusi vok_institution_form (Pendidikan dan Pelatihan / Pelatihan / Pendidikan)
	 * Catatan: "Akreditasi Lembaga" TIDAK ada kolomnya di view → tidak disediakan di sini.
	 * @return array
	 */
	public function komposisiStatusBentuk()
	{
		$db = $this->requireDb();

		// Status legalitas — 3 bucket tetap, 1 query.
		$s = $db->query(
			"SELECT
				count(*)                                                 AS total,
				count(*) FILTER (WHERE ver_legality_status = 'accepted') AS terverifikasi,
				count(*) FILTER (WHERE ver_legality_status = 'pending')  AS proses,
				count(*) FILTER (WHERE ver_legality_status = 'rejected') AS ditolak
			FROM dashboard_vokasi_detail"
		)->row_array();

		// Bentuk penyelenggaraan — GROUP BY.
		$b = $db->query(
			"SELECT vok_institution_form AS label, count(*) AS value
			FROM dashboard_vokasi_detail
			WHERE vok_institution_form IS NOT NULL AND vok_institution_form <> ''
			GROUP BY vok_institution_form
			ORDER BY value DESC"
		)->result_array();

		$bentuk = array();
		foreach ($b as $r)
		{
			$bentuk[] = array('label' => $r['label'], 'value' => (int) $r['value']);
		}

		return array(
			'status' => array(
				'terverifikasi' => (int) $s['terverifikasi'],
				'proses'        => (int) $s['proses'],
				'ditolak'       => (int) $s['ditolak'],
				'total'         => (int) $s['total'],
			),
			'bentuk' => $bentuk,
		);
	}

	/**
	 * Kunci provinsi kanonik agar cocok dgn GeoJSON (assets/vendor/geojson/
	 * indonesia-provinsi.json, prop `state`). Normalisasi = lowercase + buang
	 * non-alfanumerik; lalu alias untuk nama DB yang beda dari GeoJSON
	 * (DKI/DIY, Bangka Belitung, Papua Barat Daya→Papua Barat). Kunci hasil sama
	 * dgn normalisasi `state` GeoJSON di sisi klien → merge otomatis (mis. DKI
	 * Jakarta + Daerah Khusus Ibukota Jakarta → 'jakartaraya').
	 */
	private function provKey($name)
	{
		$k = preg_replace('/[^a-z0-9]/', '', strtolower((string) $name));
		$alias = array(
			'daerahistimewayogyakarta'   => 'yogyakarta',
			'diyogyakarta'               => 'yogyakarta',
			'daerahkhususibukotajakarta' => 'jakartaraya',
			'dkijakarta'                 => 'jakartaraya',
			'jakarta'                    => 'jakartaraya',
			'kepulauanbangkabelitung'    => 'bangkabelitung',
			'papuabaratdaya'             => 'papuabarat',
		);
		return isset($alias[$k]) ? $alias[$k] : $k;
	}

	/**
	 * Sebaran lembaga TERVERIFIKASI LEGALITAS (accepted) untuk:
	 *   - kartu "Sebaran Lembaga per Provinsi (Top 5)" (bar)
	 *   - kartu "Peta Persebaran Verifikasi Lembaga" (choropleth provinsi)
	 * Cukup view dashboard_vokasi_detail (tanpa join). Provinsi dinormalisasi via
	 * provKey() → casing beda & DKI/DIY digabung.
	 *   provinsi_top : Top-N provinsi by jumlah lembaga accepted (label = varian terbanyak).
	 *   choropleth   : map { key_provinsi => jumlah } untuk pewarnaan polygon peta.
	 * @return array
	 */
	public function sebaranLegalitas($topN = 5)
	{
		$db = $this->requireDb();

		$rows = $db->query(
			"SELECT vok_province AS provinsi, count(*) AS jumlah
			FROM dashboard_vokasi_detail
			WHERE ver_legality_status = 'accepted'
			  AND vok_province IS NOT NULL AND vok_province <> ''
			GROUP BY vok_province"
		)->result_array();

		// Agregasi per provinsi kanonik (gabung casing + alias DKI/DIY dll).
		$agg = array(); // key => ['label','jumlah','lblN']
		foreach ($rows as $r)
		{
			$key = $this->provKey($r['provinsi']);
			$n   = (int) $r['jumlah'];
			if ( ! isset($agg[$key]))
			{
				$agg[$key] = array('label' => $r['provinsi'], 'jumlah' => 0, 'lblN' => -1);
			}
			if ($n > $agg[$key]['lblN'])   // label tampilan = varian ber-jumlah terbanyak
			{
				$agg[$key]['label'] = $r['provinsi'];
				$agg[$key]['lblN']  = $n;
			}
			$agg[$key]['jumlah'] += $n;
		}

		// Choropleth: key => jumlah.
		$choropleth = array();
		foreach ($agg as $key => $v)
		{
			$choropleth[$key] = $v['jumlah'];
		}

		// Top-N provinsi (bar).
		$list = array();
		foreach ($agg as $v)
		{
			$list[] = array('label' => $v['label'], 'value' => $v['jumlah']);
		}
		usort($list, function ($a, $b) { return $b['value'] - $a['value']; });
		$provinsi = array_slice($list, 0, (int) $topN);

		return array('provinsi_top' => $provinsi, 'choropleth' => $choropleth);
	}

	/**
	 * Kartu "Jenis Lembaga Vokasi" (bar) + "Sektor Spesialisasi (Top N)" (bar).
	 *   jenis  : distribusi type_name — view dashboard_vokasi_detail (tanpa join).
	 *   sektor : Top-N sektor by JUMLAH LEMBAGA UNIK (count distinct vok_id) —
	 *            view dashboard_vokasi_detail_sektor (tanpa join; sektor & vok_id
	 *            keduanya ada di view itu). Pakai distinct krn 1 lembaga bisa punya
	 *            banyak baris jabatan pada 1 sektor (spec 2.1: dedup dulu).
	 * @return array
	 */
	public function jenisDanSektor($topSektor = 5)
	{
		$db = $this->requireDb();

		$j = $db->query(
			"SELECT trim(type_name) AS label, count(*) AS value
			FROM dashboard_vokasi_detail
			WHERE type_name IS NOT NULL AND trim(type_name) <> ''
			GROUP BY trim(type_name)
			ORDER BY value DESC"
		)->result_array();

		$jenis = array();
		foreach ($j as $r)
		{
			$jenis[] = array('label' => $r['label'], 'value' => (int) $r['value']);
		}

		$s = $db->query(
			"SELECT trim(sector_name) AS label, count(DISTINCT vok_id) AS value
			FROM dashboard_vokasi_detail_sektor
			WHERE sector_name IS NOT NULL AND trim(sector_name) <> ''
			GROUP BY trim(sector_name)
			ORDER BY value DESC
			LIMIT " . (int) $topSektor
		)->result_array();

		$sektor = array();
		foreach ($s as $r)
		{
			$sektor[] = array('label' => $r['label'], 'value' => (int) $r['value']);
		}

		return array('jenis' => $jenis, 'sektor' => $sektor);
	}

	/**
	 * Agregat menu "Monitoring Pelatihan PMI" — SELURUHNYA dari view
	 * dashboard_pelatihan_detail (1 baris = 1 pendaftaran pelatihan PMI).
	 *
	 *   kpi     : total peserta, terverifikasi (train_directorate_verification
	 *             = 'accepted'), tersertifikasi (train_certified = 'finish').
	 *   negara  : jumlah peserta per negara tujuan (train_requested_country).
	 *   status  : jumlah peserta per status pendaftaran (train_status:
	 *             pending/accepted/rejected/revised).
	 *   sektor  : jumlah peserta per sektor — train_sectoral_id di-resolve ke
	 *             nama lewat view dashboard_vokasi_detail_sektor (sector_id →
	 *             sector_name); id tanpa padanan ditampilkan "Sektor #id".
	 *   gender  : jumlah peserta per jenis kelamin. Data mentah tidak konsisten
	 *             kapitalisasinya ("Laki-Laki" vs "Laki-laki") → dinormalisasi
	 *             via initcap agar tidak terpecah 2 bucket.
	 *   tren    : jumlah peserta per bulan (train_created_at), urut naik,
	 *             label 'YYYY-MM' + label Indonesia ('Jul 2026').
	 * @return array
	 */
	public function pelatihanStats()
	{
		$db = $this->requireDb();

		$pack = function ($rows) {
			$out = array();
			foreach ($rows as $r)
			{
				$out[] = array('label' => (string) $r['label'], 'value' => (int) $r['value']);
			}
			return $out;
		};

		// KPI — 1 round-trip via COUNT + FILTER.
		$k = $db->query(
			"SELECT
				count(*)                                                          AS total,
				count(*) FILTER (WHERE train_directorate_verification = 'accepted') AS terverifikasi,
				count(*) FILTER (WHERE train_certified = 'finish')                 AS tersertifikasi
			FROM dashboard_pelatihan_detail"
		)->row_array();
		$total = (int) $k['total'];
		$pct   = function ($n) use ($total) { return $total > 0 ? (int) round($n / $total * 100) : 0; };

		$negara = $db->query(
			"SELECT coalesce(nullif(trim(train_requested_country), ''), 'Tidak diisi') AS label, count(*) AS value
			FROM dashboard_pelatihan_detail
			GROUP BY 1
			ORDER BY value DESC, label ASC"
		)->result_array();

		$status = $db->query(
			"SELECT coalesce(nullif(trim(train_status), ''), 'unknown') AS label, count(*) AS value
			FROM dashboard_pelatihan_detail
			GROUP BY 1
			ORDER BY value DESC"
		)->result_array();

		$sektor = $db->query(
			"SELECT coalesce(s.sector_name, 'Sektor #' || p.train_sectoral_id::text, 'Tidak diisi') AS label,
				count(*) AS value
			FROM dashboard_pelatihan_detail p
			LEFT JOIN (SELECT DISTINCT sector_id, trim(sector_name) AS sector_name
			           FROM dashboard_vokasi_detail_sektor) s
			  ON s.sector_id = p.train_sectoral_id
			GROUP BY 1
			ORDER BY value DESC, label ASC"
		)->result_array();

		$gender = $db->query(
			"SELECT coalesce(nullif(initcap(trim(train_pmi_gender)), ''), 'Tidak diisi') AS label, count(*) AS value
			FROM dashboard_pelatihan_detail
			GROUP BY 1
			ORDER BY value DESC"
		)->result_array();

		$tren = $db->query(
			"SELECT to_char(train_created_at, 'YYYY-MM') AS label, count(*) AS value
			FROM dashboard_pelatihan_detail
			WHERE train_created_at IS NOT NULL
			GROUP BY 1
			ORDER BY 1 ASC"
		)->result_array();

		// Label bulan Indonesia untuk sumbu chart tren.
		$bulanID = array(1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des');
		$trenOut = array();
		foreach ($tren as $r)
		{
			list($y, $m) = explode('-', $r['label']);
			$trenOut[] = array(
				'label' => $r['label'],
				'nama'  => $bulanID[(int) $m] . ' ' . $y,
				'value' => (int) $r['value'],
			);
		}

		return array(
			'kpi' => array(
				'total'          => $total,
				'terverifikasi'  => array('nilai' => (int) $k['terverifikasi'],  'persen' => $pct($k['terverifikasi'])),
				'tersertifikasi' => array('nilai' => (int) $k['tersertifikasi'], 'persen' => $pct($k['tersertifikasi'])),
			),
			'negara' => $pack($negara),
			'status' => $pack($status),
			'sektor' => $pack($sektor),
			'gender' => $pack($gender),
			'tren'   => $trenOut,
		);
	}

	/** Agregat provinsi pre-computed (untuk choropleth). */
	public function aggProvinsi()  { return $this->load('agg_provinsi'); }

	/** Matriks provinsi x sektor (gap analysis). */
	public function aggProvinsiSektor() { return $this->load('agg_provinsi_sektor'); }

	/**
	 * Agregat untuk menu "Dashboard Vokasi" (replika ringkasan pendataan).
	 * Semua dihitung dari data DB (via all()). Memakai SELURUH baris (bukan hanya
	 * primary) agar cocok dengan konsep "total lembaga" di dashboard sumber.
	 *
	 * Lapisan e-Vokasi (asumsi, lihat dokumen ANALISIS_REPLIKASI_DASHBOARD_VOKASI):
	 *   Layer 3 = punya status program · Layer 2 = punya status fasilitas (belum program)
	 *   Layer 1 = legalitas 'accepted' (belum fasilitas) · Belum = selain itu.
	 * @return array
	 */
	public function pendataanStats()
	{
		$rows = $this->all();

		$prov  = array();  // nama provinsi => jumlah
		$jenis = array();  // jenis lembaga (type_name) => jumlah
		$own   = array('Pemerintah' => 0, 'Non Pemerintah' => 0);
		$ev    = array('belum' => 0, 'layer1' => 0, 'layer2' => 0, 'layer3' => 0);

		foreach ($rows as $r)
		{
			$p = isset($r['provinsi']) ? $r['provinsi'] : NULL;
			if ($p !== NULL && $p !== '')
			{
				if ( ! isset($prov[$p])) $prov[$p] = 0;
				$prov[$p]++;
			}

			// Jenis lembaga: pakai type_name dari DB (tipe_lembaga); fallback ke 'jenis'.
			$j = ( ! empty($r['tipe_lembaga'])) ? $r['tipe_lembaga']
				: ( ! empty($r['jenis']) ? $r['jenis'] : '(Tidak diketahui)');
			if ( ! isset($jenis[$j])) $jenis[$j] = 0;
			$jenis[$j]++;

			$o = isset($r['ownership']) ? $r['ownership'] : NULL;
			if (isset($own[$o])) $own[$o]++;

			$hasProgram  = ! empty($r['status_program']);
			$hasFacility = ! empty($r['status_fasilitas']);
			$legalOk     = (isset($r['status_legalitas']) && $r['status_legalitas'] === 'accepted');
			if ($hasProgram)       $ev['layer3']++;
			elseif ($hasFacility)  $ev['layer2']++;
			elseif ($legalOk)      $ev['layer1']++;
			else                   $ev['belum']++;
		}

		arsort($prov);
		arsort($jenis);

		$provList = array();
		foreach ($prov as $nama => $v) $provList[] = array('label' => $nama, 'value' => $v);
		$jenisList = array();
		foreach ($jenis as $nama => $v) $jenisList[] = array('label' => $nama, 'value' => $v);

		// Rincian sektor & jabatan (dari relasi) — jumlah lembaga UNIK per sektor (2.1).
		$sektorLembaga = array();  // sektor => set lembaga_id
		$jabatanSet    = array();  // jabatan unik
		foreach ($this->relations() as $rel)
		{
			$s   = isset($rel['sektor']) ? $rel['sektor'] : NULL;
			$lid = (int) $rel['lembaga_id'];
			if ($s !== NULL && $s !== '')
			{
				if ( ! isset($sektorLembaga[$s])) $sektorLembaga[$s] = array();
				$sektorLembaga[$s][$lid] = TRUE;
			}
			if ( ! empty($rel['jabatan'])) $jabatanSet[$rel['jabatan']] = TRUE;
		}
		$sektorCounts = array();
		foreach ($sektorLembaga as $s => $set) $sektorCounts[$s] = count($set);
		arsort($sektorCounts);
		$sektorList = array();
		foreach ($sektorCounts as $nama => $v) $sektorList[] = array('label' => $nama, 'value' => $v);

		return array(
			'total'             => count($rows),
			'provinsi_tercakup' => count($prov),
			'sektor_count'      => count($sektorLembaga),
			'jabatan_count'     => count($jabatanSet),
			'per_provinsi'      => $provList,
			'per_jenis'         => $jenisList,
			'per_sektor'        => $sektorList,
			'ownership'         => $own,
			'evokasi'           => $ev,
			'evokasi_masuk'     => $ev['layer1'] + $ev['layer2'] + $ev['layer3'],
			'evokasi_belum'     => $ev['belum'],
			'is_db'             => $this->isDb(),
		);
	}

	/** Label lapisan e-Vokasi untuk 1 baris lembaga (dari status verifikasi). */
	public function evokasiLayer($r)
	{
		if ( ! empty($r['status_program']))  return 'Layer 3 (program)';
		if ( ! empty($r['status_fasilitas'])) return 'Layer 2 (fasilitas)';
		if (isset($r['status_legalitas']) && $r['status_legalitas'] === 'accepted') return 'Layer 1 (legalitas)';
		return 'Belum e-Vokasi';
	}

	/** Kunci lapisan e-Vokasi (layer3/layer2/layer1/belum) untuk filter tahapan. */
	public function evokasiLayerKey($r)
	{
		if ( ! empty($r['status_program']))   return 'layer3';
		if ( ! empty($r['status_fasilitas'])) return 'layer2';
		if (isset($r['status_legalitas']) && $r['status_legalitas'] === 'accepted') return 'layer1';
		return 'belum';
	}

	/**
	 * Baris ternormalisasi untuk menu "Daftar Pendataan" (tabel + export Excel/CSV).
	 * Semua kolom di sini tersedia di DB. Diurut nama.
	 * @return array
	 */
	public function pendataanRows()
	{
		$out = array();
		foreach ($this->all() as $r)
		{
			$out[] = array(
				'id'        => (int) $r['id'],
				'nama'      => $r['nama'],
				'email'     => isset($r['email']) ? $r['email'] : '',
				'provinsi'  => isset($r['provinsi']) ? $r['provinsi'] : '',
				'kota'      => isset($r['kota']) ? $r['kota'] : '',
				'jenis'     => ( ! empty($r['tipe_lembaga'])) ? $r['tipe_lembaga'] : (isset($r['jenis']) ? $r['jenis'] : ''),
				'ownership' => isset($r['ownership']) ? $r['ownership'] : '',
				'kapasitas' => ($r['kapasitas'] === NULL) ? NULL : (int) $r['kapasitas'],
				'evokasi'   => $this->evokasiLayer($r),
			);
		}
		usort($out, function ($a, $b) { return strcasecmp($a['nama'], $b['nama']); });
		return $out;
	}

	/**
	 * Daftar lembaga ringkas + sektornya untuk list di dalam modal drill-down
	 * (search + paginasi di sisi klien). Diurut nama.
	 * @return array
	 */
	public function pendataanListFull()
	{
		// Sektor unik per lembaga.
		$sekByLembaga = array();
		foreach ($this->relations() as $rel)
		{
			$lid = (int) $rel['lembaga_id'];
			$s   = isset($rel['sektor']) ? $rel['sektor'] : '';
			if ($s === '') continue;
			if ( ! isset($sekByLembaga[$lid])) $sekByLembaga[$lid] = array();
			$sekByLembaga[$lid][$s] = TRUE;
		}

		$out = array();
		foreach ($this->all() as $r)
		{
			$id = (int) $r['id'];
			$out[] = array(
				'id'     => $id,
				'nama'   => $r['nama'],
				'prov'   => isset($r['provinsi']) ? $r['provinsi'] : '',
				'own'    => isset($r['ownership']) ? $r['ownership'] : '',
				'ev'     => $this->evokasiLayer($r),
				'sektor' => isset($sekByLembaga[$id]) ? array_keys($sekByLembaga[$id]) : array(),
				'email'  => isset($r['email']) ? $r['email'] : '',
			);
		}
		usort($out, function ($a, $b) { return strcasecmp($a['nama'], $b['nama']); });
		return $out;
	}

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
		$tahapan = isset($p['tahapan'])         ? $p['tahapan']         : NULL;
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
			if ($tahapan !== NULL && $this->evokasiLayerKey($r) !== $tahapan) continue;
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
