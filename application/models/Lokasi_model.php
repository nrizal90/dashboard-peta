<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Lokasi_model
 *
 * Sumber data marker peta.
 *
 * Perilaku: mencoba membaca dari tabel `lokasi` di database. Jika koneksi
 * gagal atau tabel belum ada, otomatis fallback ke data contoh (dummy)
 * sehingga scaffold tetap jalan sebelum database disiapkan.
 *
 * Untuk mengaktifkan mode database:
 *   1. Import sql/lokasi.sql ke MySQL (buat DB `dashboard_peta`).
 *   2. Sesuaikan kredensial di application/config/database.php.
 *
 * Struktur tiap baris: id, nama, lat, lng, kategori, keterangan.
 */
class Lokasi_model extends CI_Model {

	/** Cache status kesiapan DB dalam satu request: null=belum dicek. */
	private $db_ready = NULL;

	/** Data contoh — dipakai saat DB belum siap. */
	private $dummy = array(
		array('id' => 1, 'nama' => 'Kantor Pusat Jakarta',  'lat' => -6.2088,  'lng' => 106.8456, 'kategori' => 'kantor',  'keterangan' => 'Kantor pusat operasional'),
		array('id' => 2, 'nama' => 'Cabang Bandung',         'lat' => -6.9175,  'lng' => 107.6191, 'kategori' => 'cabang',  'keterangan' => 'Cabang wilayah Jawa Barat'),
		array('id' => 3, 'nama' => 'Cabang Surabaya',        'lat' => -7.2575,  'lng' => 112.7521, 'kategori' => 'cabang',  'keterangan' => 'Cabang wilayah Jawa Timur'),
		array('id' => 4, 'nama' => 'Cabang Medan',           'lat' => 3.5952,   'lng' => 98.6722,  'kategori' => 'cabang',  'keterangan' => 'Cabang wilayah Sumatera Utara'),
		array('id' => 5, 'nama' => 'Cabang Makassar',        'lat' => -5.1477,  'lng' => 119.4327, 'kategori' => 'cabang',  'keterangan' => 'Cabang wilayah Sulawesi Selatan'),
	);

	/**
	 * Cek apakah DB terhubung dan tabel `lokasi` tersedia.
	 * db_debug=FALSE di config membuat koneksi gagal tidak menghentikan app.
	 * @return bool
	 */
	private function db_ready()
	{
		if ($this->db_ready !== NULL)
		{
			return $this->db_ready;
		}

		// Load database lazy (bukan autoload) agar halaman lain aman.
		$db = $this->load->database('default', TRUE);

		$this->db_ready = ($db !== FALSE)
			&& ! empty($db->conn_id)
			&& $db->table_exists('lokasi');

		if ($this->db_ready)
		{
			$this->db = $db; // simpan koneksi aktif untuk dipakai method lain
		}

		return $this->db_ready;
	}

	/**
	 * Ambil semua lokasi.
	 * @return array
	 */
	public function get_all()
	{
		if ($this->db_ready())
		{
			return $this->db->order_by('id', 'ASC')->get('lokasi')->result_array();
		}

		return $this->dummy;
	}

	/**
	 * Jumlah total lokasi.
	 * @return int
	 */
	public function count_all()
	{
		if ($this->db_ready())
		{
			return (int) $this->db->count_all('lokasi');
		}

		return count($this->dummy);
	}

	/**
	 * Bentuk data ke GeoJSON FeatureCollection yang siap dipakai Leaflet.
	 * @return array
	 */
	public function as_geojson()
	{
		$features = array();

		foreach ($this->get_all() as $row)
		{
			$features[] = array(
				'type' => 'Feature',
				'geometry' => array(
					'type' => 'Point',
					// GeoJSON memakai urutan [lng, lat]
					'coordinates' => array((float) $row['lng'], (float) $row['lat']),
				),
				'properties' => array(
					'id'         => $row['id'],
					'nama'       => $row['nama'],
					'kategori'   => $row['kategori'],
					'keterangan' => $row['keterangan'],
				),
			);
		}

		return array(
			'type'     => 'FeatureCollection',
			'features' => $features,
		);
	}
}
