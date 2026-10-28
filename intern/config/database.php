<?php
// ============================================================
// Konfigurasi database & aplikasi
// ============================================================
$host     = 'localhost';
$dbname   = 'absensi_magang';
$username = 'root';          // sesuaikan
$password = '';              // sesuaikan

// Zona waktu aplikasi (Gunung Mas, Kalimantan Tengah = WIB)
define('APP_TZ', 'Asia/Jakarta');
define('APP_TZ_OFFSET', '+07:00');
date_default_timezone_set(APP_TZ);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Samakan jam MySQL dengan jam PHP agar tidak ada selisih tanggal/jam
    $pdo->exec("SET time_zone = '" . APP_TZ_OFFSET . "'");
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

require_once __DIR__ . '/../includes/schema.php';
require_once __DIR__ . '/../includes/attendance_logic.php';
ensureSchema($pdo);
