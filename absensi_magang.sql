-- ============================================================
-- Database lengkap: Sistem Absensi Magang (Diskominfosantik Kab. Gunung Mas)
-- Cara pakai: phpMyAdmin -> tab "Import" -> pilih file ini -> Go.
--   (atau terminal:  mysql -u root -p < absensi_magang.sql )
-- Kompatibel MySQL 5.7+/8.x dan MariaDB 10.3+ (XAMPP).
-- Nama database harus sama dengan $dbname di config/database.php (absensi_magang).
-- Login admin awal:  username = admin  |  password = Admin12345!   -> SEGERA GANTI.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `absensi_magang` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `absensi_magang`;

SET NAMES utf8mb4;

-- ---------- users ----------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_lengkap`        VARCHAR(100) NOT NULL,
  `username`            VARCHAR(50)  NOT NULL,
  `password`            VARCHAR(255) NOT NULL,                 -- hash password_hash() PHP
  `role`                ENUM('admin','user') NOT NULL DEFAULT 'user',
  `face_descriptors`    MEDIUMTEXT NULL,                       -- JSON sampel wajah (128 angka per sampel)
  `face_registered_at`  DATETIME NULL,
  `face_photo`          VARCHAR(255) NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- office_location (1 baris: id = 1) ----------
CREATE TABLE IF NOT EXISTS `office_location` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `latitude`     DOUBLE NOT NULL,
  `longitude`    DOUBLE NOT NULL,
  `radius_meter` INT NOT NULL DEFAULT 150,
  `secret_key`   VARCHAR(100) NOT NULL,                      -- kunci HMAC kode verifikasi absensi
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- attendance_settings (jam absensi per hari kerja) ----------
CREATE TABLE IF NOT EXISTS `attendance_settings` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `day_name`              VARCHAR(10) NOT NULL,              -- Senin..Jumat (hari tanpa baris = libur)
  `checkin_start`         TIME NOT NULL,                     -- buka absen masuk
  `checkin_ontime_until`  TIME NULL,                         -- batas tepat waktu
  `checkin_end`           TIME NOT NULL,                     -- tutup absen masuk
  `checkout_start`        TIME NOT NULL,                     -- buka absen pulang
  `checkout_end`          TIME NOT NULL,                     -- tutup absen pulang
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_day_name` (`day_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- attendances ----------
CREATE TABLE IF NOT EXISTS `attendances` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `tanggal`       DATE NOT NULL,
  `tipe`          ENUM('masuk','pulang') NOT NULL,
  `waktu`         TIME NOT NULL,
  `latitude`      DOUBLE NULL,
  `longitude`     DOUBLE NULL,
  `barcode_used`  VARCHAR(50) NULL,
  `status`        VARCHAR(20) NOT NULL DEFAULT 'tepat_waktu',  -- tepat_waktu | terlambat
  `late_minutes`  INT NOT NULL DEFAULT 0,
  `face_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `face_score`    FLOAT NULL,
  `distance_m`    FLOAT NULL,
  `gps_accuracy`  FLOAT NULL,
  `photo_path`    VARCHAR(255) NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_hari_tipe` (`user_id`, `tanggal`, `tipe`),
  KEY `idx_tanggal` (`tanggal`),
  CONSTRAINT `fk_att_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- app_settings ----------
CREATE TABLE IF NOT EXISTS `app_settings` (
  `k` VARCHAR(50)  NOT NULL PRIMARY KEY,
  `v` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DATA AWAL
-- ============================================================

-- Admin awal (password: Admin12345!  -> ganti setelah login)
INSERT INTO `users` (`nama_lengkap`, `username`, `password`, `role`)
SELECT 'Administrator', 'admin', '$2y$10$61d9bl0OaHeV5v/3YIvKAuej4xzBi9QAUjnBK/yloadImPeUKh5g2', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin');

-- Lokasi kantor (CONTOH — atur koordinat asli & secret key di Admin > Pengaturan)
INSERT INTO `office_location` (`id`, `latitude`, `longitude`, `radius_meter`, `secret_key`)
SELECT 1, -1.1167, 113.8667, 150, 'GANTI-SECRET-KEY-INI-12345'
WHERE NOT EXISTS (SELECT 1 FROM `office_location` WHERE `id` = 1);

-- Jam absensi contoh Senin-Jumat (atur di Admin > Pengaturan)
INSERT IGNORE INTO `attendance_settings`
  (`day_name`, `checkin_start`, `checkin_ontime_until`, `checkin_end`, `checkout_start`, `checkout_end`) VALUES
  ('Senin',  '07:00:00', '08:00:00', '09:00:00', '15:30:00', '17:00:00'),
  ('Selasa', '07:00:00', '08:00:00', '09:00:00', '15:30:00', '17:00:00'),
  ('Rabu',   '07:00:00', '08:00:00', '09:00:00', '15:30:00', '17:00:00'),
  ('Kamis',  '07:00:00', '08:00:00', '09:00:00', '15:30:00', '17:00:00'),
  ('Jumat',  '07:00:00', '08:00:00', '09:00:00', '14:30:00', '17:00:00');

-- Pengaturan umum: wajib scan wajah = 1, ambang kecocokan wajah = 0.50
INSERT IGNORE INTO `app_settings` (`k`, `v`) VALUES ('face_required', '1'), ('face_threshold', '0.50');
