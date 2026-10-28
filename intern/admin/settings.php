<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';
require_once '../includes/ui.php';

$pageTitle = 'Pengaturan - Sistem Absensi Magang';
$nama = $_SESSION['nama'] ?? 'Admin';
$days = ['Senin','Selasa','Rabu','Kamis','Jumat'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid($_POST['csrf'] ?? '')) { header('Location: settings.php?error=' . urlencode('Token keamanan tidak valid.')); exit; }

    // ---- Jam absensi (divalidasi: masuk & pulang tidak boleh tumpang tindih) ----
    if (isset($_POST['update_jam'])) {
        $errors = [];
        foreach ($days as $day) {
            $v = $_POST['day'][$day] ?? [];
            foreach (['checkin_start','checkin_ontime_until','checkin_end','checkout_start','checkout_end'] as $k) $v[$k] = substr($v[$k] ?? '', 0, 5);
            if ($e = validateDaySetting($day, $v)) $errors[] = $e;
        }
        if ($errors) { header('Location: settings.php?error=' . urlencode(implode(' | ', $errors))); exit; }

        $up = $pdo->prepare("UPDATE attendance_settings SET checkin_start=?, checkin_ontime_until=?, checkin_end=?, checkout_start=?, checkout_end=? WHERE day_name=?");
        foreach ($days as $day) {
            $v = $_POST['day'][$day];
            $up->execute([$v['checkin_start'], $v['checkin_ontime_until'], $v['checkin_end'], $v['checkout_start'], $v['checkout_end'], $day]);
        }
        header('Location: settings.php?success=' . urlencode('Jam absensi berhasil disimpan. Berlaku langsung untuk semua anak magang.')); exit;
    }

    // ---- Lokasi ----
    if (isset($_POST['update_lokasi'])) {
        $lat = filter_var($_POST['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $lng = filter_var($_POST['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
        $rad = filter_var($_POST['radius'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 5000]]);
        $key = trim($_POST['secret_key'] ?? '');
        if ($lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180 || $rad === false || strlen($key) < 8) {
            header('Location: settings.php?error=' . urlencode('Lokasi tidak valid (koordinat, radius 10–5000 m, secret key min. 8 karakter).')); exit;
        }
        $pdo->prepare("UPDATE office_location SET latitude=?, longitude=?, radius_meter=?, secret_key=? WHERE id=1")->execute([$lat, $lng, $rad, $key]);
        header('Location: settings.php?success=' . urlencode('Lokasi kantor berhasil diupdate')); exit;
    }

    // ---- Wajah ----
    if (isset($_POST['update_wajah'])) {
        $th = filter_var($_POST['face_threshold'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($th === false || $th < 0.30 || $th > 0.60) { header('Location: settings.php?error=' . urlencode('Ambang kecocokan wajah harus 0.30–0.60.')); exit; }
        setAppSetting($pdo, 'face_required', isset($_POST['face_required']) ? '1' : '0');
        setAppSetting($pdo, 'face_threshold', number_format($th, 2, '.', ''));
        header('Location: settings.php?success=' . urlencode('Pengaturan scan wajah disimpan')); exit;
    }
}

$settings = $pdo->query("SELECT * FROM attendance_settings ORDER BY FIELD(day_name,'Senin','Selasa','Rabu','Kamis','Jumat')")->fetchAll();
$office   = $pdo->query("SELECT * FROM office_location LIMIT 1")->fetch() ?: [];
$faceRequired = appSetting($pdo, 'face_required', '1') === '1';
$faceThreshold = appSetting($pdo, 'face_threshold', '0.50');
$csrf = csrfToken();
$hm = fn($t) => substr((string)$t, 0, 5);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=2" rel="stylesheet">
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="container-fluid p-0"><div class="row g-0">
    <?php ui_sidebar('admin', 'settings'); ?>
    <div class="col-lg-10">
        <?php ui_topbar('admin', $nama, false); ?>
        <main class="p-3 p-md-4">
            <div class="mb-4">
                <h4 class="mb-1">Pengaturan Sistem</h4>
                <p class="text-muted mb-0">Atur jam absensi, lokasi kantor, dan scan wajah</p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($_GET['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($_GET['error']) ?></div>
            <?php endif; ?>

            <!-- Jam Absensi -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3"><h5 class="mb-0"><i class="bi bi-clock me-1 text-primary"></i> Jam Absensi (Senin – Jumat)</h5></div>
                <div class="card-body">
                    <div class="alert alert-info small">
                        <strong>Cara kerja:</strong> anak magang hanya bisa absen <u>di dalam jendela waktu</u>.
                        <ul class="mb-0 mt-1">
                            <li><b>Masuk</b>: dibuka pada <i>Buka Masuk</i>. Sampai <i>Batas Tepat Waktu</i> = Tepat Waktu; setelahnya sampai <i>Tutup Masuk</i> = <b>Terlambat</b>. Lewat <i>Tutup Masuk</i> tidak bisa absen = <b>Alpa</b>.</li>
                            <li><b>Pulang</b>: hanya bisa antara <i>Buka Pulang</i> dan <i>Tutup Pulang</i>, dan hanya jika sudah absen masuk. <i>Buka Pulang</i> harus setelah <i>Tutup Masuk</i> sehingga masuk &amp; pulang tidak bisa bersamaan.</li>
                        </ul>
                    </div>
                    <form method="POST" id="formJam">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="update_jam" value="1">
                        <div class="table-responsive">
                            <table class="table align-middle table-sm-text">
                                <thead>
                                    <tr>
                                        <th>Hari</th>
                                        <th class="text-success">Buka Masuk</th>
                                        <th class="text-success">Batas Tepat Waktu</th>
                                        <th class="text-danger">Tutup Masuk</th>
                                        <th class="text-primary">Buka Pulang</th>
                                        <th class="text-primary">Tutup Pulang</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($settings as $s): $d = $s['day_name']; ?>
                                    <tr>
                                        <td><strong><?= $d ?></strong></td>
                                        <?php foreach ([['checkin_start', $hm($s['checkin_start'])], ['checkin_ontime_until', $hm($s['checkin_ontime_until'] ?: $s['checkin_end'])],
                                                        ['checkin_end', $hm($s['checkin_end'])], ['checkout_start', $hm($s['checkout_start'])], ['checkout_end', $hm($s['checkout_end'])]] as [$k, $val]): ?>
                                            <td><input type="time" name="day[<?= $d ?>][<?= $k ?>]" class="form-control" value="<?= $val ?>" required></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Jam Absensi</button>
                            <button type="button" class="btn btn-outline-secondary" id="copyMon"><i class="bi bi-files"></i> Samakan semua hari dengan Senin</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Scan Wajah -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3"><h5 class="mb-0"><i class="bi bi-person-bounding-box me-1 text-primary"></i> Scan Wajah</h5></div>
                <div class="card-body">
                    <form method="POST" class="row g-3 align-items-end">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="update_wajah" value="1">
                        <div class="col-md-4">
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" role="switch" name="face_required" id="fr" <?= $faceRequired ? 'checked' : '' ?>>
                                <label class="form-check-label" for="fr">Wajib scan wajah</label>
                            </div>
                            <div class="form-text">Jika aktif, absen hanya sah bila wajah cocok &amp; berada di lokasi kantor.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Ambang kecocokan: <strong id="thVal"><?= htmlspecialchars($faceThreshold) ?></strong></label>
                            <input type="range" class="form-range" name="face_threshold" min="0.30" max="0.60" step="0.01" value="<?= htmlspecialchars($faceThreshold) ?>" oninput="thVal.textContent=(+this.value).toFixed(2)">
                            <div class="form-text">Lebih kecil = lebih ketat (0.40 sangat ketat, 0.50 disarankan, 0.60 longgar).</div>
                        </div>
                        <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-save"></i> Simpan</button></div>
                    </form>
                </div>
            </div>

            <!-- Lokasi Kantor -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3"><h5 class="mb-0"><i class="bi bi-geo-alt me-1 text-primary"></i> Lokasi Kantor (GPS)</h5></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="update_lokasi" value="1">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Latitude</label>
                                <input type="text" name="latitude" class="form-control" value="<?= htmlspecialchars((string)($office['latitude'] ?? '')) ?>" required></div>
                            <div class="col-md-4"><label class="form-label">Longitude</label>
                                <input type="text" name="longitude" class="form-control" value="<?= htmlspecialchars((string)($office['longitude'] ?? '')) ?>" required></div>
                            <div class="col-md-4"><label class="form-label">Radius (meter)</label>
                                <input type="number" name="radius" class="form-control" min="10" max="5000" value="<?= (int)($office['radius_meter'] ?? 150) ?>" required></div>
                            <div class="col-12"><label class="form-label">Secret Key (untuk kode verifikasi)</label>
                                <input type="text" name="secret_key" class="form-control" value="<?= htmlspecialchars($office['secret_key'] ?? '') ?>" required minlength="8">
                                <div class="form-text">Gunakan string acak yang kuat. Jangan sering diganti.</div></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-geo-alt"></i> Simpan Lokasi</button>
                            <button type="button" class="btn btn-outline-secondary" id="useHere"><i class="bi bi-crosshair"></i> Pakai lokasi saya sekarang</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div></div>

<?php ui_logout_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/script.js"></script>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); document.getElementById('sidebarOverlay').classList.toggle('show'); }
document.getElementById('copyMon').onclick = () => {
    const keys = ['checkin_start','checkin_ontime_until','checkin_end','checkout_start','checkout_end'];
    keys.forEach(k => { const v = document.querySelector('[name="day[Senin][' + k + ']"]').value;
        ['Selasa','Rabu','Kamis','Jumat'].forEach(d => document.querySelector('[name="day[' + d + '][' + k + ']"]').value = v); });
};
document.getElementById('useHere').onclick = () => navigator.geolocation && navigator.geolocation.getCurrentPosition(p => {
    document.querySelector('[name=latitude]').value = p.coords.latitude.toFixed(7);
    document.querySelector('[name=longitude]').value = p.coords.longitude.toFixed(7);
}, () => alert('Gagal membaca lokasi. Izinkan akses lokasi.'), { enableHighAccuracy: true });
</script>
</body>
</html>
