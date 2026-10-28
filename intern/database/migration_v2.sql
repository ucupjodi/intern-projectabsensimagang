-- Migrasi manual (opsional). Aplikasi sudah menjalankan ini otomatis lewat includes/schema.php.
-- Jalankan di phpMyAdmin hanya jika ingin menyiapkan database sebelum aplikasi dibuka.
-- Catatan: "ADD COLUMN IF NOT EXISTS" butuh MariaDB (XAMPP) / MySQL 8.0.29+.

ALTER TABLE attendances MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'tepat_waktu';
ALTER TABLE attendances
  ADD COLUMN IF NOT EXISTS late_minutes  INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS face_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS face_score    FLOAT NULL,
  ADD COLUMN IF NOT EXISTS distance_m    FLOAT NULL,
  ADD COLUMN IF NOT EXISTS gps_accuracy  FLOAT NULL,
  ADD COLUMN IF NOT EXISTS photo_path    VARCHAR(255) NULL;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS face_descriptors   MEDIUMTEXT NULL,
  ADD COLUMN IF NOT EXISTS face_registered_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS face_photo         VARCHAR(255) NULL;

ALTER TABLE attendance_settings ADD COLUMN IF NOT EXISTS checkin_ontime_until TIME NULL;
UPDATE attendance_settings SET checkin_ontime_until = checkin_end WHERE checkin_ontime_until IS NULL;

CREATE TABLE IF NOT EXISTS app_settings (
  k VARCHAR(50) NOT NULL PRIMARY KEY,
  v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO app_settings (k, v) VALUES ('face_required','1'), ('face_threshold','0.50');
