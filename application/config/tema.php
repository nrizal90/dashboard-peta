<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| TEMA WARNA DASHBOARD (satu sumber kebenaran)
| -------------------------------------------------------------------------
| Ubah warna tema cukup di file ini. Nilainya dipakai di dua jalur:
|
|   1. CSS  — templates/header.php menuliskannya sebagai custom property
|             :root { --dg-gold, --dg-gold-dark, --dg-gold-deep, --dg-gold-light }
|             sehingga semua view & halaman ikut otomatis.
|   2. JS   — templates/footer.php mengekspor window.APP.tema untuk Chart.js
|             & Leaflet (assets/js/dashboard.js), dan view dashboard/index.php
|             memakainya lewat $this->config->item('tema').
|
| File ini di-autoload lewat application/config/autoload.php.
*/
$config['tema'] = array(
	'gold'      => '#E8C457', // aksen utama
	'gold_dark' => '#C79A2E', // aksen tegas (sidebar, tombol)
	'gold_deep' => '#A87F1E', // aksen paling pekat (teks/ikon di atas emas muda)
	'gold_light'=> '#F2DD8E', // latar lembut (badge, lingkaran ikon)
);
