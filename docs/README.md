# Dashboard Peta Vokasi — versi statis (GitHub Pages)

Folder ini adalah **hasil konversi** aplikasi CodeIgniter (PHP) menjadi situs 100%
statis, supaya bisa di-host gratis di **GitHub Pages** tanpa server PHP.

## Cara kerja

Aplikasi asli mengambil data lewat endpoint PHP `/api/*`
(`application/controllers/Api.php` + `application/libraries/Vokasi_repo.php`).
Di versi statis, seluruh logika itu diport ke JavaScript di
[`assets/js/vokasi-api.js`](assets/js/vokasi-api.js):

- Memuat file JSON di [`data/vokasi/`](data/vokasi/) langsung di browser.
- Meniru endpoint `points`, `stats`, `refs`, `lembaga/{id}`, `choropleth`, `gap`.
- Meng-override `window.fetch` **hanya** untuk URL `api/...` sehingga file
  `assets/js/dashboard.js` dan `assets/js/wall.js` tetap dipakai apa adanya.

Halaman:

| File | Isi |
|------|-----|
| `index.html`   | Peta interaktif + filter + chart |
| `gap.html`     | Gap analysis (matriks provinsi × sektor) |
| `tentang.html` | Tentang / keterbatasan data |
| `wall.html`    | Command Center (kiosk auto-rotasi) |

## Cara update data

Regenerate JSON dengan pipeline `clean_vokasi.py`, lalu salin hasilnya ke
`docs/data/vokasi/` (nama file harus sama). Tidak perlu mengubah kode.

## Cara mengaktifkan GitHub Pages

1. Push repo ini ke GitHub.
2. **Settings → Pages**.
3. **Source: Deploy from a branch**, pilih branch (mis. `main`) dan folder **`/docs`**.
4. Simpan. Situs terbit di `https://<user>.github.io/<repo>/`.

> Aplikasi PHP asli tetap utuh di root repo untuk pengembangan lokal
> (`php -S localhost:8080 server.php`). Folder `docs/` hanya untuk publikasi statis.
