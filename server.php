<?php
/**
 * Router untuk PHP built-in server.
 *
 * Menjalankan dashboard tanpa Apache:
 *
 *     php -S localhost:8080 server.php
 *
 * Lalu buka http://localhost:8080/
 *
 * Hanya file statis di assets/ yang disajikan langsung; request lain
 * (termasuk .git/, dokumen/, sql/) diarahkan ke index.php (front controller
 * CodeIgniter) — sama dengan aturan .htaccess.
 */

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Sajikan file statis apa adanya (css, js, gambar, dsb.) — hanya di assets/.
if (strpos($uri, '/assets/') === 0 && strpos($uri, '..') === FALSE && is_file($file)) {
	return false;
}

// Selain itu, serahkan ke CodeIgniter.
require __DIR__ . '/index.php';
