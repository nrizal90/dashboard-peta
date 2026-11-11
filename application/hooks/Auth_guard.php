<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Akses dashboard:
 *   - Tanpa login / tanpa permission 'data.full' → data pribadi dimasking (pdp_helper).
 *   - Method berakhiran "_export" wajib permission 'data.export'; guest diarahkan
 *     ke login lalu kembali ke URL export.
 *   - Halaman kelola user/role dijaga di controller masing-masing (auth_require).
 *
 * Dipasang di config/hooks.php (post_controller_constructor, setelah library
 * session di-autoload).
 */
class Auth_guard {

	public function check()
	{
		$CI     =& get_instance();
		$method = strtolower($CI->router->fetch_method());

		if (substr($method, -7) === '_export') { auth_require('data.export'); }
	}
}
