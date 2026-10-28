<?php
/**
 * LOGIKA ABSENSI (murni, tidak butuh database sehingga mudah diuji)
 *
 * Aturan jam (per hari, diatur admin di Pengaturan):
 *   checkin_start .. checkin_ontime_until  -> absen MASUK, status "tepat_waktu"
 *   checkin_ontime_until .. checkin_end    -> absen MASUK, status "terlambat" (menit terlambat dihitung)
 *   setelah checkin_end                    -> absen MASUK DITUTUP. Tidak absen = ALPA
 *   checkout_start .. checkout_end         -> absen PULANG (hanya jika sudah absen masuk)
 *   sebelum checkout_start                 -> pulang belum dibuka (tidak bisa pulang cepat)
 *   setelah checkout_end                   -> pulang ditutup
 * Jam tutup dihitung sampai menit itu berakhir (08:30 berarti sampai 08:30:59).
 * Jendela masuk & pulang tidak boleh tumpang-tindih, jadi tidak mungkin absen keduanya bersamaan.
 */

function timeToSec(?string $t): int {
    if ($t === null || $t === '') return 0;
    $p = array_map('intval', explode(':', $t));
    return ($p[0] ?? 0) * 3600 + ($p[1] ?? 0) * 60 + ($p[2] ?? 0);
}

function secToHm(int $sec): string {
    $sec = max(0, $sec);
    return sprintf('%02d:%02d', intdiv($sec, 3600) % 24, intdiv($sec % 3600, 60));
}

function dayNameId(?int $ts = null): string {
    $list = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    return $list[(int)date('w', $ts ?? time())];
}

/** Rapikan baris attendance_settings menjadi detik (end inklusif sampai :59). */
function normalizeSetting(array $s): array {
    $cin_s  = timeToSec($s['checkin_start']);
    $cin_e  = timeToSec($s['checkin_end']) + 59;
    $ontime = !empty($s['checkin_ontime_until']) ? timeToSec($s['checkin_ontime_until']) : timeToSec($s['checkin_end']);
    $ontime = min(max($ontime, $cin_s), $cin_e);
    return [
        'cin_s' => $cin_s, 'cin_e' => $cin_e, 'ontime' => $ontime,
        'cout_s' => timeToSec($s['checkout_start']),
        'cout_e' => timeToSec($s['checkout_end']) + 59,
    ];
}

/**
 * Status absensi saat ini.
 * @param array|null $setting   baris attendance_settings hari ini (null = bukan hari kerja)
 * @param int        $nowSec    detik sejak 00:00 (jam server)
 * @param bool       $hasMasuk  sudah absen masuk hari ini
 * @param bool       $hasPulang sudah absen pulang hari ini
 * @return array phase, allowed ('masuk'|'pulang'|null), status, late_minutes, message, next_change (detik absolut hari ini atau null)
 */
function attendancePhase(?array $setting, int $nowSec, bool $hasMasuk, bool $hasPulang): array {
    $r = ['phase' => 'libur', 'allowed' => null, 'status' => null, 'late_minutes' => 0,
          'message' => 'Hari ini bukan hari kerja. Absensi ditutup.', 'next_change' => null];
    if (!$setting) return $r;

    $t = normalizeSetting($setting);

    if ($nowSec < $t['cin_s']) {
        $r['phase'] = 'sebelum_masuk';
        $r['message'] = 'Absen masuk dibuka pukul ' . secToHm($t['cin_s']) . '.';
        $r['next_change'] = $t['cin_s'];
    } elseif ($nowSec <= $t['cin_e']) {
        if ($hasMasuk) {
            $r['phase'] = 'menunggu_pulang';
            $r['message'] = 'Anda sudah absen masuk. Absen pulang dibuka pukul ' . secToHm($t['cout_s']) . '.';
            $r['next_change'] = $t['cin_e'] + 1;
        } else {
            $r['phase'] = 'masuk';
            $r['allowed'] = 'masuk';
            if ($nowSec <= $t['ontime'] + 59) {
                $r['status'] = 'tepat_waktu';
                $r['message'] = 'Absen masuk sedang dibuka (tepat waktu sampai ' . secToHm($t['ontime']) . ').';
                $r['next_change'] = min($t['ontime'] + 60, $t['cin_e'] + 1);
            } else {
                $r['status'] = 'terlambat';
                $r['late_minutes'] = (int)ceil(($nowSec - ($t['ontime'] + 59)) / 60);
                $r['message'] = 'Anda terlambat ' . $r['late_minutes'] . ' menit. Absen masuk ditutup pukul ' . secToHm($t['cin_e']) . '.';
                $r['next_change'] = $t['cin_e'] + 1;
            }
        }
    } elseif ($nowSec < $t['cout_s']) {
        $r['phase'] = $hasMasuk ? 'menunggu_pulang' : 'masuk_ditutup';
        $r['message'] = $hasMasuk
            ? 'Anda sudah absen masuk. Absen pulang dibuka pukul ' . secToHm($t['cout_s']) . '.'
            : 'Absen masuk sudah ditutup. Anda tercatat ALPA hari ini.';
        $r['next_change'] = $t['cout_s'];
    } elseif ($nowSec <= $t['cout_e']) {
        if ($hasPulang) {
            $r['phase'] = 'selesai';
            $r['message'] = 'Absensi hari ini sudah lengkap. Terima kasih!';
            $r['next_change'] = $t['cout_e'] + 1;
        } elseif ($hasMasuk) {
            $r['phase'] = 'pulang';
            $r['allowed'] = 'pulang';
            $r['status'] = 'tepat_waktu';
            $r['message'] = 'Absen pulang sedang dibuka sampai ' . secToHm($t['cout_e']) . '.';
            $r['next_change'] = $t['cout_e'] + 1;
        } else {
            $r['phase'] = 'masuk_ditutup';
            $r['message'] = 'Anda tidak absen masuk hari ini (ALPA), sehingga tidak dapat absen pulang.';
            $r['next_change'] = $t['cout_e'] + 1;
        }
    } else {
        $r['phase'] = $hasPulang ? 'selesai' : 'ditutup';
        $r['message'] = $hasPulang ? 'Absensi hari ini sudah lengkap. Terima kasih!'
            : ($hasMasuk ? 'Absen pulang sudah ditutup. Anda tidak absen pulang hari ini.'
                         : 'Absensi hari ini sudah ditutup. Anda tercatat ALPA.');
    }
    return $r;
}

/**
 * Rekap status harian satu orang (dipakai admin). $now = detik jika tanggal = hari ini,
 * atau 86399 untuk tanggal yang sudah lewat, atau -1 untuk tanggal masa depan.
 */
function dailySummary(?array $setting, int $nowSec, ?array $masuk, ?array $pulang): array {
    if (!$setting) return ['label' => 'Libur', 'class' => 'secondary'];
    if ($nowSec < 0) return ['label' => 'Belum Mulai', 'class' => 'secondary'];
    $t = normalizeSetting($setting);
    if ($masuk) {
        $late = ($masuk['status'] ?? '') === 'terlambat';
        if (!$pulang && $nowSec > $t['cout_e']) {
            return ['label' => $late ? 'Terlambat • Tidak Absen Pulang' : 'Tidak Absen Pulang', 'class' => 'warning'];
        }
        return ['label' => $late ? 'Terlambat' : 'Hadir', 'class' => $late ? 'warning' : 'success'];
    }
    if ($nowSec > $t['cin_e']) return ['label' => 'Alpa', 'class' => 'danger'];
    return ['label' => 'Belum Absen', 'class' => 'secondary'];
}

function statusLabel(?string $status): array {
    switch (strtolower((string)$status)) {
        case 'terlambat':    return ['Terlambat', 'warning'];
        case 'tepat_waktu':
        case 'hadir':        return ['Tepat Waktu', 'success'];
        default:             return [ucfirst((string)$status) ?: '-', 'secondary'];
    }
}

/** Validasi jam yang diisi admin untuk satu hari. Mengembalikan pesan error atau null. */
function validateDaySetting(string $day, array $v): ?string {
    foreach (['checkin_start','checkin_ontime_until','checkin_end','checkout_start','checkout_end'] as $k) {
        if (!isset($v[$k]) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v[$k])) return "$day: format jam '$k' tidak valid.";
    }
    $a = timeToSec($v['checkin_start']); $o = timeToSec($v['checkin_ontime_until']);
    $b = timeToSec($v['checkin_end']);   $c = timeToSec($v['checkout_start']); $d = timeToSec($v['checkout_end']);
    if (!($a < $b))  return "$day: jam buka absen masuk harus lebih awal dari jam tutup masuk.";
    if (!($a <= $o && $o <= $b)) return "$day: batas tepat waktu harus berada di antara jam buka dan jam tutup masuk.";
    if (!($b < $c))  return "$day: absen pulang harus dibuka SETELAH absen masuk ditutup (agar tidak bisa bersamaan).";
    if (!($c < $d))  return "$day: jam buka pulang harus lebih awal dari jam tutup pulang.";
    return null;
}

/** Jarak dua titik GPS (meter) - rumus Haversine */
function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $R = 6371000.0;
    $p1 = deg2rad($lat1); $p2 = deg2rad($lat2);
    $dp = deg2rad($lat2 - $lat1); $dl = deg2rad($lon2 - $lon1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** Descriptor wajah valid = array 128 angka terbatas */
function validDescriptor($d): bool {
    if (!is_array($d) || count($d) !== 128) return false;
    foreach ($d as $x) {
        if (!(is_int($x) || is_float($x)) || !is_finite((float)$x) || abs($x) > 5) return false;
    }
    return true;
}

function euclidean(array $a, array $b): float {
    $s = 0.0;
    for ($i = 0; $i < 128; $i++) { $df = $a[$i] - $b[$i]; $s += $df * $df; }
    return sqrt($s);
}

/** Jarak terkecil antara wajah live dengan semua sampel wajah terdaftar. */
function bestFaceDistance(array $stored, array $live): float {
    $best = INF;
    foreach ($stored as $d) {
        if (validDescriptor($d)) $best = min($best, euclidean($d, $live));
    }
    return $best;
}
