<?php
/**
 * Migrasi otomatis & aman (idempotent): menambah kolom/tabel baru bila belum ada.
 * Data lama tidak dihapus. Salinan manual ada di database/migration_v2.sql
 */
function schemaHasColumn(PDO $pdo, string $table, string $col): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $s->execute([$table, $col]);
    return (int)$s->fetchColumn() > 0;
}

function schemaAddColumn(PDO $pdo, string $table, string $col, string $ddl): void {
    if (!schemaHasColumn($pdo, $table, $col)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $ddl");
    }
}

function ensureSchema(PDO $pdo): void {
    // Cek cepat: kalau kolom terakhir sudah ada, anggap semua sudah termigrasi.
    if (schemaHasColumn($pdo, 'attendances', 'photo_path') && schemaHasColumn($pdo, 'users', 'face_descriptors')
        && schemaHasColumn($pdo, 'attendance_settings', 'checkin_ontime_until')) {
        return;
    }

    // --- attendances ---
    if (!schemaHasColumn($pdo, 'attendances', 'status')) {
        $pdo->exec("ALTER TABLE attendances ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'tepat_waktu'");
    } else {
        // Jadikan VARCHAR agar bisa menampung 'tepat_waktu' / 'terlambat' (jika sebelumnya ENUM)
        $t = $pdo->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='attendances' AND COLUMN_NAME='status'")->fetchColumn();
        if (strtolower((string)$t) !== 'varchar') {
            $pdo->exec("ALTER TABLE attendances MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'tepat_waktu'");
        }
    }
    schemaAddColumn($pdo, 'attendances', 'late_minutes',  'INT NOT NULL DEFAULT 0');
    schemaAddColumn($pdo, 'attendances', 'face_verified', 'TINYINT(1) NOT NULL DEFAULT 0');
    schemaAddColumn($pdo, 'attendances', 'face_score',    'FLOAT NULL');
    schemaAddColumn($pdo, 'attendances', 'distance_m',    'FLOAT NULL');
    schemaAddColumn($pdo, 'attendances', 'gps_accuracy',  'FLOAT NULL');
    schemaAddColumn($pdo, 'attendances', 'photo_path',    'VARCHAR(255) NULL');

    // --- users ---
    schemaAddColumn($pdo, 'users', 'face_descriptors',    'MEDIUMTEXT NULL');
    schemaAddColumn($pdo, 'users', 'face_registered_at',  'DATETIME NULL');
    schemaAddColumn($pdo, 'users', 'face_photo',          'VARCHAR(255) NULL');

    // --- attendance_settings ---
    if (!schemaHasColumn($pdo, 'attendance_settings', 'checkin_ontime_until')) {
        $pdo->exec("ALTER TABLE attendance_settings ADD COLUMN `checkin_ontime_until` TIME NULL");
        // Awalnya batas tepat waktu = jam tutup masuk (perilaku lama tidak berubah)
        $pdo->exec("UPDATE attendance_settings SET checkin_ontime_until = checkin_end WHERE checkin_ontime_until IS NULL");
    }

    // --- pengaturan umum ---
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        `k` VARCHAR(50) NOT NULL PRIMARY KEY,
        `v` VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("INSERT IGNORE INTO app_settings (k, v) VALUES ('face_required','1'), ('face_threshold','0.50')");
}

function appSetting(PDO $pdo, string $key, string $default = ''): string {
    $s = $pdo->prepare("SELECT v FROM app_settings WHERE k = ?");
    $s->execute([$key]);
    $v = $s->fetchColumn();
    return $v === false ? $default : (string)$v;
}

function setAppSetting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("INSERT INTO app_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")
        ->execute([$key, $value]);
}
