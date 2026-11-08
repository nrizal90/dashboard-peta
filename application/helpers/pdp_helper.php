<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Masking data pribadi untuk akun guest (UU PDP No. 27/2022).
 *
 * Aturan: masking SELALU di server sebelum data dikirim ke browser
 * (view, JSON API). Jangan masking di JS/CSS — data asli tetap terlihat
 * di DevTools.
 */

/** TRUE hanya bila sesi login ber-role admin. Selain itu (termasuk tanpa sesi) = guest. */
function pdp_is_admin()
{
	$CI =& get_instance();
	return isset($CI->session) && $CI->session->userdata('role') === 'admin';
}

/**
 * Peta nama kolom → jenis masking. Dipakai pdp_mask_deep() untuk JSON API.
 * 'nama' sengaja TIDAK di sini: kolom 'nama' juga dipakai untuk nama lembaga,
 * provinsi, dll. (bukan data pribadi). Masking nama orang dilakukan eksplisit
 * per halaman lewat pdp_mask_rows().
 */
function pdp_sensitive_keys()
{
	return array(
		'nik'       => 'nik',
		'no_nik'    => 'nik',
		'telepon'   => 'telp',
		'telp'      => 'telp',
		'no_telp'   => 'telp',
		'no_hp'     => 'telp',
		'phone'     => 'telp',
		'alamat'    => 'alamat',
		'address'   => 'alamat',
		'no_paspor' => 'paspor',
		'paspor'    => 'paspor',
	);
}

/** Pecah string jadi array karakter (aman UTF-8). */
function _pdp_chars($s)
{
	return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
}

/** Sisakan $head karakter depan & $tail belakang; tengah diganti '*' (minimal 4). */
function _pdp_keep($s, $head, $tail)
{
	$c = _pdp_chars($s);
	$n = count($c);
	if ($n <= $head + $tail) { return str_repeat('*', max($n, 4)); }
	$mid = max($n - $head - $tail, 4);
	return implode('', array_slice($c, 0, $head)) . str_repeat('*', $mid)
		. ($tail > 0 ? implode('', array_slice($c, -$tail)) : '');
}

/**
 * Masking satu nilai.
 *   nik    3201234567890001 → ************0001 (wilayah & tgl lahir tersembunyi)
 *   nama   Budi Santoso     → B*** S***         (panjang asli tidak bocor)
 *   telp   081234567890     → 0812******90
 *   alamat Jl. Mawar No. 5  → ********          (teks bebas, tidak bisa dipotong aman)
 *   paspor C1234567         → C*****67
 */
function pdp_mask($value, $type)
{
	if ($value === NULL) { return NULL; }
	$s = trim((string) $value);
	if ($s === '' || $s === '-' || $s === 'Tidak diisi') { return $value; }

	switch ($type)
	{
		case 'nik':
			return _pdp_keep($s, 0, 4);
		case 'nama':
			$words = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($words as &$w) { $w = mb_substr($w, 0, 1) . '***'; }
			unset($w);
			return implode(' ', $words);
		case 'telp':
			return _pdp_keep($s, 4, 2);
		case 'paspor':
			return _pdp_keep($s, 1, 2);
		case 'alamat':
		default:
			return '********';
	}
}

/**
 * Masking kolom tertentu pada list baris.
 * @param array $rows list baris asosiatif
 * @param array $map  kolom => jenis, mis. array('nama' => 'nama', 'nik' => 'nik')
 */
function pdp_mask_rows(array $rows, array $map)
{
	foreach ($rows as &$r)
	{
		foreach ($map as $key => $type)
		{
			if (array_key_exists($key, $r)) { $r[$key] = pdp_mask($r[$key], $type); }
		}
	}
	unset($r);
	return $rows;
}

/** Masking rekursif berdasarkan nama kolom (default: pdp_sensitive_keys()). */
function pdp_mask_deep($data, $map = NULL)
{
	if ($map === NULL) { $map = pdp_sensitive_keys(); }
	if ( ! is_array($data)) { return $data; }
	foreach ($data as $k => $v)
	{
		if (is_array($v))
		{
			$data[$k] = pdp_mask_deep($v, $map);
		}
		elseif (is_string($k) && isset($map[strtolower($k)]))
		{
			$data[$k] = pdp_mask($v, $map[strtolower($k)]);
		}
	}
	return $data;
}
