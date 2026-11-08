<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Contoh konfigurasi akun login. Salin menjadi auth.php lalu isi hash:
|   php -r "echo password_hash('PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
|
| Hanya admin yang login (data lengkap + export). Pengunjung tanpa login =
| guest: data sensitif dimasking, tanpa export.
*/
$config['auth_users'] = array(
	'admin' => array('role' => 'admin', 'hash' => 'GANTI_DENGAN_HASH'),
);

$config['auth_max_attempts'] = 5;
$config['auth_lockout']      = 900;
