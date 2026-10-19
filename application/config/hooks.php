<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	https://codeigniter.com/userguide3/general/hooks.html
|
*/

// Header keamanan dikirim dari PHP — production memakai nginx yang mengabaikan
// .htaccess, jadi header di .htaccess saja tidak cukup.
$hook['pre_system'][] = array(
	'class'    => '',
	'function' => 'security_headers',
	'filename' => 'Security_headers.php',
	'filepath' => 'hooks',
);
