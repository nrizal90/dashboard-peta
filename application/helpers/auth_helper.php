<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * RBAC: user, role & permission di DB MySQL 'auth' (lihat sql/user_management.sql).
 * Permission user dimuat ulang tiap request → user yang dinonaktifkan/diubah
 * role-nya langsung berlaku tanpa menunggu logout.
 */

/** Koneksi DB auth (sekali per request). */
function auth_db()
{
	static $db = NULL;
	if ($db === NULL)
	{
		$db = get_instance()->load->database('auth', TRUE);
	}
	return $db;
}

/**
 * User login saat ini + permission-nya, atau NULL (guest).
 * @return array|null ['id','username','full_name','role_id','role_name','perms'=>[]]
 */
function auth_user()
{
	static $user = FALSE;
	if ($user !== FALSE) { return $user; }

	$CI  =& get_instance();
	$id  = isset($CI->session) ? (int) $CI->session->userdata('user_id') : 0;
	$user = NULL;
	if ($id <= 0) { return $user; }

	$row = auth_db()->query(
		'SELECT u.id, u.username, u.full_name, u.role_id, r.name AS role_name
		   FROM users u JOIN roles r ON r.id = u.role_id
		  WHERE u.id = ? AND u.is_active = 1', array($id)
	);
	$row = $row ? $row->row_array() : NULL;

	if ( ! $row)
	{
		// User dihapus/nonaktif (atau DB tak bisa dihubungi) → perlakukan sebagai guest.
		$CI->session->unset_userdata(array('user_id', 'username'));
		return $user;
	}

	$perms = auth_db()->query(
		'SELECT permission_code FROM role_permissions WHERE role_id = ?', array($row['role_id'])
	)->result_array();
	$row['perms'] = array_column($perms, 'permission_code');
	return $user = $row;
}

/** TRUE bila user login punya permission $code. Guest selalu FALSE. */
function auth_can($code)
{
	$u = auth_user();
	return $u !== NULL && in_array($code, $u['perms'], TRUE);
}

/**
 * Hentikan request bila tak punya permission: guest → ke login (lalu kembali),
 * user login → 403.
 */
function auth_require($code)
{
	if (auth_can($code)) { return; }

	$CI =& get_instance();
	if (auth_user() === NULL)
	{
		// Simpan tujuan (path internal, bukan URL dari user → aman dari open redirect).
		$qs = $CI->input->server('QUERY_STRING');
		$CI->session->set_userdata('after_login', uri_string() . ($qs ? '?' . $qs : ''));
		redirect('login');
	}
	show_error('Anda tidak punya akses ke halaman ini.', 403, 'Akses Ditolak');
}
