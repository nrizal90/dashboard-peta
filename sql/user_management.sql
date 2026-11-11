-- Manajemen user & role (RBAC) dashboard. MySQL/MariaDB.
--   mysql -uroot < sql/user_management.sql
-- Permission didefinisikan di sini (dicek di kode lewat auth_can()); role & user
-- dikelola dari menu Pengaturan > User / Role.

CREATE DATABASE IF NOT EXISTS dashboard_peta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dashboard_peta;

CREATE TABLE IF NOT EXISTS roles (
	id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	name        VARCHAR(50)  NOT NULL UNIQUE,
	description VARCHAR(255) NOT NULL DEFAULT '',
	is_system   TINYINT(1)   NOT NULL DEFAULT 0, -- role sistem: tak bisa dihapus/diubah hak aksesnya
	created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permissions (
	code        VARCHAR(50)  PRIMARY KEY,
	description VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
	role_id         INT UNSIGNED NOT NULL,
	permission_code VARCHAR(50)  NOT NULL,
	PRIMARY KEY (role_id, permission_code),
	FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
	FOREIGN KEY (permission_code) REFERENCES permissions(code) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
	id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
	username      VARCHAR(50)  NOT NULL UNIQUE,
	full_name     VARCHAR(100) NOT NULL DEFAULT '',
	password_hash VARCHAR(255) NOT NULL,
	role_id       INT UNSIGNED NOT NULL,
	is_active     TINYINT(1)   NOT NULL DEFAULT 1,
	last_login_at DATETIME     NULL,
	created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	FOREIGN KEY (role_id) REFERENCES roles(id) -- RESTRICT: role yang masih dipakai tak bisa dihapus
) ENGINE=InnoDB;

INSERT IGNORE INTO permissions (code, description) VALUES
	('data.full',   'Lihat data pribadi tanpa masking (NIK, nama, telepon, alamat)'),
	('data.export', 'Export data ke Excel'),
	('user.manage', 'Kelola user'),
	('role.manage', 'Kelola role & hak akses');

INSERT IGNORE INTO roles (id, name, description, is_system) VALUES
	(1, 'Administrator', 'Akses penuh termasuk kelola user & role', 1),
	(2, 'Operator',      'Data lengkap + export, tanpa kelola user', 0);

INSERT IGNORE INTO role_permissions (role_id, permission_code)
	SELECT 1, code FROM permissions;
INSERT IGNORE INTO role_permissions (role_id, permission_code) VALUES
	(2, 'data.full'), (2, 'data.export');

-- Akun awal: admin, password sama dengan akun admin sebelumnya di config/auth.php.
INSERT IGNORE INTO users (username, full_name, password_hash, role_id) VALUES
	('admin', 'Administrator', '$2y$12$zVx2OkRAn3Wpb8m0JBdLPufqjewB5Bvt0bq1B5PTRzEXv7xD/ydqy', 1);
