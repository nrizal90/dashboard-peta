<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Akses dashboard:
 *   - Tanpa login  = guest → semua halaman & API terbuka, data pribadi dimasking
 *                    (lihat pdp_helper), tanpa export.
 *   - Login admin  = data lengkap + export.
 *
 * Aturan: method berakhiran "_export" wajib admin; selain itu diarahkan ke
 * halaman login lalu kembali ke URL export setelah berhasil.
 *
 * Dipasang di config/hooks.php (post_controller_constructor, setelah library
 * session di-autoload).
 */
class Auth_guard {

	public function check()
	{
		$CI     =& get_instance();
		$method = strtolower($CI->router->fetch_method());

		if (substr($method, -7) !== '_export' || pdp_is_admin()) { return; }

		// Simpan tujuan (path internal, bukan URL dari user → aman dari open redirect).
		$qs = $CI->input->server('QUERY_STRING');
		$CI->session->set_userdata('after_login', uri_string() . ($qs ? '?' . $qs : ''));
		redirect('login');
	}
}
