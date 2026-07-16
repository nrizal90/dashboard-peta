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
 * File statis (assets/*) disajikan langsung; request lain diarahkan
 * ke index.php (front controller CodeIgniter) sehingga clean URL jalan.
 */

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Sajikan file statis apa adanya (css, js, gambar, dsb.)
if ($uri !== '/' && is_file($file)) {
	return false;
}

// Selain itu, serahkan ke CodeIgniter.
require __DIR__ . '/index.php';
