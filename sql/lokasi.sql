-- ---------------------------------------------------------------------
-- Skema database untuk Dashboard Peta
--
-- Cara pakai (contoh XAMPP):
--   1. Buka phpMyAdmin / mysql client.
--   2. Import file ini (akan membuat database `dashboard_peta` + tabel `lokasi`).
--   3. Pastikan kredensial di application/config/database.php sesuai.
--
-- Setelah tabel ada, Lokasi_model otomatis membaca dari DB
-- (berhenti memakai data contoh/dummy).
-- ---------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `dashboard_peta`
	DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `dashboard_peta`;

CREATE TABLE IF NOT EXISTS `lokasi` (
	`id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`nama`       VARCHAR(150) NOT NULL,
	`lat`        DECIMAL(10, 7) NOT NULL,
	`lng`        DECIMAL(10, 7) NOT NULL,
	`kategori`   VARCHAR(50) NOT NULL DEFAULT 'umum',
	`keterangan` TEXT NULL,
	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `idx_kategori` (`kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data awal (sama dengan data contoh di Lokasi_model)
INSERT INTO `lokasi` (`nama`, `lat`, `lng`, `kategori`, `keterangan`) VALUES
('Kantor Pusat Jakarta', -6.2088000, 106.8456000, 'kantor', 'Kantor pusat operasional'),
('Cabang Bandung',       -6.9175000, 107.6191000, 'cabang', 'Cabang wilayah Jawa Barat'),
('Cabang Surabaya',      -7.2575000, 112.7521000, 'cabang', 'Cabang wilayah Jawa Timur'),
('Cabang Medan',          3.5952000,  98.6722000, 'cabang', 'Cabang wilayah Sumatera Utara'),
('Cabang Makassar',      -5.1477000, 119.4327000, 'cabang', 'Cabang wilayah Sulawesi Selatan');
