<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| Akun login ada di DB MySQL (tabel users, lihat sql/user_management.sql),
| dikelola lewat menu Pengaturan > User / Role.
*/

$config['auth_max_attempts'] = 5;
$config['auth_lockout']      = 900;
