<?php
/**
 * Endpoint absensi (JSON). Semua validasi dilakukan DI SERVER:
 *  1. jam   : harus berada di jendela waktu yang ditetapkan admin (masuk / pulang, tidak bisa bersamaan)
 *  2. lokasi: jarak GPS ke kantor <= radius (dihitung ulang di server)
 *  3. kode  : barcode/kode verifikasi per menit
 *  4. wajah : descriptor wajah dibandingkan dengan wajah yang didaftarkan (di server)
 * Semuanya harus lolos dalam SATU permintaan, baru absensi dicatat.
 */
require_once 'includes/auth.php';
require_once 'config/database.php';
require_once 'includes/face_photo.php';

jsonRequireUser();

$userId = (int)$_SESSION['user_id'];
$tipe   = $_POST['tipe'] ?? '';
$lat    = filter_var($_POST['latitude']  ?? null, FILTER_VALIDATE_FLOAT);
$lng    = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$acc    = filter_var($_POST['accuracy']  ?? null, FILTER_VALIDATE_FLOAT);
$code   = strtoupper(trim($_POST['barcode'] ?? ''));

if (!in_array($tipe, ['masuk', 'pulang'], true)) jsonOut(['ok' => false, 'message' => 'Jenis absensi tidak valid.'], 422);

// ---------- 1. JAM ----------
$now    = time();
$nowSec = (int)date('G', $now) * 3600 + (int)date('i', $now) * 60 + (int)date('s', $now);
$tanggal = date('Y-m-d', $now);

$st = $pdo->prepare("SELECT * FROM attendance_settings WHERE day_name = ?");
$st->execute([dayNameId($now)]);
$setting = $st->fetch() ?: null;

$pdo->beginTransaction();
try {
    // kunci baris user agar klik ganda / dua perangkat tidak menghasilkan absen ganda
    $pdo->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE")->execute([$userId]);

    $rows = $pdo->prepare("SELECT tipe FROM attendances WHERE user_id = ? AND tanggal = ?");
    $rows->execute([$userId, $tanggal]);
    $done = array_column($rows->fetchAll(), 'tipe');
    $hasMasuk = in_array('masuk', $done, true);
    $hasPulang = in_array('pulang', $done, true);

    if (in_array($tipe, $done, true)) { $pdo->rollBack(); jsonOut(['ok' => false, 'message' => "Anda sudah absen $tipe hari ini."], 409); }

    $phase = attendancePhase($setting, $nowSec, $hasMasuk, $hasPulang);
    if ($phase['allowed'] !== $tipe) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'message' => "Absen $tipe tidak dapat dilakukan sekarang. " . $phase['message']], 403);
    }

    // ---------- 2. LOKASI ----------
    $office = $pdo->query("SELECT * FROM office_location LIMIT 1")->fetch();
    if (!$office) { $pdo->rollBack(); jsonOut(['ok' => false, 'message' => 'Lokasi kantor belum diatur admin.'], 500); }
    if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180) {
        $pdo->rollBack(); jsonOut(['ok' => false, 'message' => 'Lokasi GPS tidak terbaca. Aktifkan GPS lalu coba lagi.'], 422);
    }
    $dist = haversineMeters((float)$lat, (float)$lng, (float)$office['latitude'], (float)$office['longitude']);
    $radius = (float)($office['radius_meter'] ?: 150);
    if ($dist > $radius) {
        $pdo->rollBack();
        jsonOut(['ok' => false, 'message' => 'Anda berada di luar area kantor (' . round($dist) . ' m dari kantor, batas ' . round($radius) . ' m).'], 403);
    }

    // ---------- 3. KODE VERIFIKASI ----------
    $ok = false;
    foreach ([0, -60] as $off) {
        $exp = strtoupper(substr(hash_hmac('sha256', date('Y-m-d H:i', $now + $off) . '|' . $userId, $office['secret_key']), 0, 12));
        if (hash_equals($exp, $code)) { $ok = true; break; }
    }
    if (!$ok) { $pdo->rollBack(); jsonOut(['ok' => false, 'message' => 'Kode verifikasi kadaluarsa. Coba lagi.'], 403); }

    // ---------- 4. WAJAH ----------
    $faceRequired = appSetting($pdo, 'face_required', '1') === '1';
    $threshold = (float)appSetting($pdo, 'face_threshold', '0.50');
    $faceScore = null; $faceVerified = 0; $photoPath = null;

    if ($faceRequired) {
        $u = $pdo->prepare("SELECT nama_lengkap, face_descriptors FROM users WHERE id = ?");
        $u->execute([$userId]);
        $urow = $u->fetch();
        $stored = json_decode((string)($urow['face_descriptors'] ?? ''), true);
        if (!is_array($stored) || !$stored) {
            $pdo->rollBack();
            jsonOut(['ok' => false, 'message' => 'Wajah Anda belum didaftarkan. Daftarkan wajah terlebih dahulu.', 'need_enroll' => true], 403);
        }
        $live = json_decode($_POST['face_descriptor'] ?? '', true);
        if (!validDescriptor($live)) { $pdo->rollBack(); jsonOut(['ok' => false, 'message' => 'Data wajah tidak terbaca. Ulangi scan wajah.'], 422); }

        $faceScore = bestFaceDistance($stored, $live);
        if ($faceScore > $threshold) {
            $pdo->rollBack();
            jsonOut(['ok' => false, 'message' => 'Wajah tidak cocok dengan data yang terdaftar. Pastikan wajah terlihat jelas dan pencahayaan cukup.'], 403);
        }
        $faceVerified = 1;
        $stamp = $urow['nama_lengkap'] . ' | ' . strtoupper($tipe) . ' ' . date('d/m/Y H:i:s', $now) . ' | ' . round($lat, 5) . ',' . round($lng, 5) . ' | ' . round($dist) . 'm';
        $photoPath = saveFacePhoto($_POST['photo'] ?? '', 'absensi', 'u' . $userId . '_' . date('Ymd', $now) . '_' . $tipe, $stamp);
        if ($photoPath === null) { $pdo->rollBack(); jsonOut(['ok' => false, 'message' => 'Foto verifikasi gagal disimpan: ' . (facePhotoError() ?: 'tidak diketahui') . '.'], 422); }
    }

    // ---------- SIMPAN ----------
    $status = $tipe === 'masuk' ? $phase['status'] : 'tepat_waktu';
    $ins = $pdo->prepare("INSERT INTO attendances
        (user_id, tanggal, tipe, waktu, latitude, longitude, barcode_used, status, late_minutes, face_verified, face_score, distance_m, gps_accuracy, photo_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$userId, $tanggal, $tipe, date('H:i:s', $now), $lat, $lng, $code, $status,
        $tipe === 'masuk' ? $phase['late_minutes'] : 0, $faceVerified, $faceScore, round($dist, 1), $acc === false ? null : $acc, $photoPath]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('absensi error: ' . $e->getMessage());
    jsonOut(['ok' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.'], 500);
}

$msg = $tipe === 'masuk'
    ? ($status === 'terlambat' ? "Absen masuk berhasil, tercatat TERLAMBAT {$phase['late_minutes']} menit." : 'Absen masuk berhasil. Tepat waktu!')
    : 'Absen pulang berhasil. Hati-hati di jalan!';
jsonOut(['ok' => true, 'message' => $msg, 'status' => $status, 'waktu' => date('H:i:s', $now), 'jarak' => round($dist)]);
