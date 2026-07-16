# Dashboard Peta — CodeIgniter 3 + Bootstrap (SB Admin 2) + Leaflet

Scaffold dashboard peta interaktif memakai CodeIgniter 3.1.13, template
Bootstrap SB Admin 2, dan Leaflet + OpenStreetMap (tanpa API key).

## Cara menjalankan

### Opsi A — PHP built-in server (paling cepat untuk dev)

```bash
php -S localhost:8080 server.php
```

Buka http://localhost:8080/

### Opsi B — Apache (XAMPP / Laragon)

Taruh folder ini di `htdocs`, aktifkan `mod_rewrite`, buka
http://localhost/dashboard-peta/ — clean URL ditangani oleh `.htaccess`.

## Struktur penting

| Path | Fungsi |
|------|--------|
| `application/controllers/Dashboard.php` | Controller utama: render halaman + endpoint JSON `/api/lokasi` |
| `application/models/Lokasi_model.php`   | Sumber data marker (kini **data dummy**, ganti ke query DB) |
| `application/views/templates/`          | header, sidebar, topbar, footer (layout SB Admin 2) |
| `application/views/dashboard/index.php` | Isi halaman: kartu ringkasan + kontainer `#map` |
| `assets/`                               | CSS/JS SB Admin 2 + Leaflet + FontAwesome |
| `server.php`                            | Router untuk `php -S` |

## Mode wall / command center

Halaman peta fullscreen untuk video wall: **`/wall`**. Tanpa sidebar/topbar,
ada jam live, dan **auto-refresh ringan** (default 30 dtk, atur di
`Dashboard::wall()` → `refresh_ms`). Refresh hanya menarik JSON—objek peta
tidak dibuat ulang, berhenti otomatis saat tab tidak terlihat, dan memakai
**ETag/304** sehingga bila data tidak berubah server hanya membalas 304
(tanpa body) — nyaris nol beban.

## Database

Model sudah siap DB dengan **fallback otomatis ke data contoh** bila DB
belum ada. Untuk mengaktifkan mode database:

1. Import `sql/lokasi.sql` (membuat DB `dashboard_peta` + tabel `lokasi` + data awal).
2. Sesuaikan kredensial di `application/config/database.php` (default XAMPP: `root` / kosong).

Begitu tabel `lokasi` tersedia, `Lokasi_model` otomatis membaca dari DB.
Tidak perlu mengubah kode.

## Langkah lanjutan

- **Auth**: belum disertakan — template login SB Admin ada di
  `startbootstrap-sb-admin-2-gh-pages/login.html`.
- Titik tengah & zoom peta diatur di `Dashboard::index()` / `Dashboard::wall()`
  (`map_center`, `map_zoom`).

## Catatan PHP 8.x

CI3 masih memunculkan notice *deprecated* di PHP 8.x. Notice tersebut
disembunyikan di `index.php` (`error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT)`)
agar tidak mengotori output/JSON. Error lain tetap tampil.
