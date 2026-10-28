<?php
require_once '../includes/auth.php';
requireUser();
require_once '../config/database.php';
require_once '../includes/ui.php';

$pageTitle = 'Absensi - Sistem Absensi Magang';
$userId = (int)$_SESSION['user_id'];
$nama   = $_SESSION['nama'] ?? 'User';

$office = $pdo->query("SELECT * FROM office_location LIMIT 1")->fetch() ?: [];

$now     = time();
$nowSec  = (int)date('G', $now) * 3600 + (int)date('i', $now) * 60 + (int)date('s', $now);
$hari    = dayNameId($now);
$tanggal = date('Y-m-d', $now);

$stmt = $pdo->prepare("SELECT * FROM attendance_settings WHERE day_name = ?");
$stmt->execute([$hari]);
$setting = $stmt->fetch() ?: null;

$stmt = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? AND tanggal = ?");
$stmt->execute([$userId, $tanggal]);
$hariIni = [];
foreach ($stmt->fetchAll() as $row) $hariIni[$row['tipe']] = $row;

$phase = attendancePhase($setting, $nowSec, isset($hariIni['masuk']), isset($hariIni['pulang']));
$t = $setting ? normalizeSetting($setting) : null;

$faceRequired = appSetting($pdo, 'face_required', '1') === '1';
$q = $pdo->prepare("SELECT face_descriptors, face_registered_at FROM users WHERE id = ?");
$q->execute([$userId]);
$u = $q->fetch();
$faceEnrolled = !empty($u['face_descriptors']);

$bannerClass = [
    'masuk' => $phase['status'] === 'terlambat' ? 'phase-late' : 'phase-open', 'pulang' => 'phase-open',
    'sebelum_masuk' => 'phase-wait', 'menunggu_pulang' => 'phase-wait',
    'masuk_ditutup' => 'phase-closed', 'ditutup' => 'phase-closed', 'libur' => 'phase-closed', 'selesai' => 'phase-done',
][$phase['phase']] ?? 'phase-wait';
$bannerIcon = ['phase-open' => 'bi-check-circle-fill', 'phase-late' => 'bi-exclamation-triangle-fill', 'phase-wait' => 'bi-hourglass-split',
               'phase-closed' => 'bi-lock-fill', 'phase-done' => 'bi-patch-check-fill'][$bannerClass];

// kartu per sesi
function slotInfo(string $tipe, ?array $t, int $nowSec, array $hariIni, array $phase): array {
    if (isset($hariIni[$tipe])) {
        [$lbl] = statusLabel($hariIni[$tipe]['status']);
        $late = (int)($hariIni[$tipe]['late_minutes'] ?? 0);
        return ['state' => 'done', 'badge' => 'success', 'text' => 'Sudah absen ' . substr($hariIni[$tipe]['waktu'], 0, 5) . ' • ' . $lbl . ($late ? " ($late mnt)" : '')];
    }
    if ($phase['allowed'] === $tipe) return ['state' => 'open', 'badge' => 'success', 'text' => 'Sedang dibuka'];
    if (!$t) return ['state' => 'locked', 'badge' => 'secondary', 'text' => 'Libur'];
    if ($tipe === 'masuk') {
        return $nowSec < $t['cin_s'] ? ['state' => 'locked', 'badge' => 'info', 'text' => 'Belum dibuka']
                                     : ['state' => 'closed', 'badge' => 'danger', 'text' => 'Ditutup • Alpa'];
    }
    if ($nowSec < $t['cout_s']) return ['state' => 'locked', 'badge' => 'info', 'text' => 'Belum dibuka'];
    return isset($hariIni['masuk']) ? ['state' => 'closed', 'badge' => 'danger', 'text' => 'Ditutup']
                                    : ['state' => 'closed', 'badge' => 'secondary', 'text' => 'Tidak tersedia'];
}
$slotMasuk  = slotInfo('masuk',  $t, $nowSec, $hariIni, $phase);
$slotPulang = slotInfo('pulang', $t, $nowSec, $hariIni, $phase);

$base = mktime(0, 0, 0, (int)date('n', $now), (int)date('j', $now), (int)date('Y', $now));
$cfg = [
    'serverNow'   => $now * 1000,
    'csrf'        => csrfToken(),
    'allowed'     => $phase['allowed'],
    'nextChange'  => $phase['next_change'] !== null ? ($base + $phase['next_change']) * 1000 : null,
    'windowEnd'   => $t ? ($phase['allowed'] === 'masuk' ? ($base + $t['cin_e'] + 1) * 1000 : ($phase['allowed'] === 'pulang' ? ($base + $t['cout_e'] + 1) * 1000 : null)) : null,
    'office'      => ['lat' => (float)($office['latitude'] ?? 0), 'lng' => (float)($office['longitude'] ?? 0), 'radius' => (float)($office['radius_meter'] ?? 150)],
    'faceRequired'=> $faceRequired,
    'faceEnrolled'=> $faceEnrolled,
];
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

<div class="container-fluid p-0">
    <div class="row g-0">
        <?php ui_sidebar('user', 'index'); ?>

        <div class="col-lg-10">
            <?php ui_topbar('user', $nama); ?>

            <main class="p-3 p-md-4">
                <!-- Jam & status -->
                <div class="clock-card mb-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
                        <div>
                            <div class="clock-date mb-1">Halo, <strong><?= htmlspecialchars($nama) ?></strong> 👋</div>
                            <div class="clock-time" id="clock">--:--:--</div>
                        </div>
                        <div class="text-md-end">
                            <div class="fw-semibold"><?= $hari ?>, <?= date('d F Y', $now) ?></div>
                            <small class="clock-date">Waktu server (WIB) — tidak bisa diubah dari perangkat</small>
                        </div>
                    </div>
                </div>

                <div class="phase-banner <?= $bannerClass ?> mb-3" id="phaseBanner">
                    <i class="bi <?= $bannerIcon ?>"></i>
                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($phase['message']) ?></div>
                        <div class="small" id="countdown"></div>
                    </div>
                </div>

                <?php if ($faceRequired && !$faceEnrolled): ?>
                    <div class="card border-0 shadow-sm mb-3" style="border-left:5px solid #f59e0b !important">
                        <div class="card-body d-flex flex-wrap align-items-center gap-3">
                            <div class="fs-1 text-warning"><i class="bi bi-person-bounding-box"></i></div>
                            <div class="flex-grow-1">
                                <h5 class="mb-1">Daftarkan wajah Anda dulu</h5>
                                <p class="text-muted mb-0 small">Absensi memakai verifikasi wajah. Pendaftaran cukup sekali (±15 detik): hadapkan wajah ke kamera dan ikuti petunjuk.</p>
                            </div>
                            <button class="btn btn-warning fw-semibold" id="btnEnroll"><i class="bi bi-camera-fill me-1"></i> Daftarkan Wajah</button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$setting): ?>
                    <div class="alert alert-secondary">Hari ini bukan hari kerja (Senin–Jumat).</div>
                <?php else: ?>
                <div class="row g-3 mb-3">
                    <?php foreach ([['masuk', 'Absen Masuk', 'bi-box-arrow-in-right', $slotMasuk, secToHm($t['cin_s']), secToHm($t['cin_e']), 'btn-success'],
                                    ['pulang', 'Absen Pulang', 'bi-box-arrow-right', $slotPulang, secToHm($t['cout_s']), secToHm($t['cout_e']), 'btn-primary']] as [$tp, $judul, $ic, $sl, $a, $b, $btn]): ?>
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 slot-card <?= $sl['state'] === 'open' ? 'active' : ($sl['state'] === 'done' ? '' : 'locked') ?> h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="text-muted mb-0"><i class="bi <?= $ic ?> me-1"></i><?= $judul ?></h6>
                                    <span class="badge bg-<?= $sl['badge'] ?>"><?= htmlspecialchars($sl['text']) ?></span>
                                </div>
                                <div class="slot-time mb-1"><?= $a ?> – <?= $b ?></div>
                                <?php if ($tp === 'masuk'): ?>
                                    <div class="small text-muted mb-3">Tepat waktu sampai <strong><?= secToHm($t['ontime']) ?></strong>. Setelah itu tercatat terlambat; lewat <?= $b ?> tidak bisa absen (Alpa).</div>
                                <?php else: ?>
                                    <div class="small text-muted mb-3">Hanya bisa setelah absen masuk &amp; setelah jam <?= $a ?>.</div>
                                <?php endif; ?>
                                <button type="button" class="btn <?= $btn ?> btn-absen btn-lg" data-tipe="<?= $tp ?>" disabled>
                                    <i class="bi <?= $sl['state'] === 'open' ? $ic : ($sl['state'] === 'done' ? 'bi-check2-circle' : 'bi-lock-fill') ?> me-1"></i>
                                    <?= $sl['state'] === 'done' ? 'Sudah Absen' : $judul ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Syarat -->
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h5 class="mb-2"><i class="bi bi-shield-check text-primary me-1"></i> Syarat Absensi</h5>
                        <ul class="check-list" id="checkList">
                            <li id="chkTime" class="<?= $phase['allowed'] ? 'ok' : 'bad' ?>"><span class="dot"><i class="bi bi-<?= $phase['allowed'] ? 'check-lg' : 'x-lg' ?>"></i></span>
                                <span>Waktu: <?= $phase['allowed'] ? 'jendela absen ' . $phase['allowed'] . ' sedang terbuka' : 'tidak ada jendela absen yang terbuka' ?></span></li>
                            <li id="chkGps" class="wait"><span class="dot"><i class="bi bi-geo-alt-fill"></i></span><span id="gpsText">Mendeteksi lokasi GPS… (izinkan akses lokasi)</span></li>
                            <?php if ($faceRequired): ?>
                            <li id="chkFace" class="<?= $faceEnrolled ? 'ok' : 'bad' ?>"><span class="dot"><i class="bi bi-<?= $faceEnrolled ? 'check-lg' : 'x-lg' ?>"></i></span>
                                <span>Wajah: <?= $faceEnrolled ? 'sudah terdaftar — akan dipindai saat absen' : 'belum terdaftar' ?></span></li>
                            <?php endif; ?>
                            <li><span class="dot"><i class="bi bi-key-fill"></i></span><span>Kode verifikasi: <span class="code-chip" id="codeChip">------------</span> <small class="text-muted">(berganti tiap menit)</small></span></li>
                        </ul>
                        <div class="small text-muted mt-2" id="btnReason"></div>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<!-- Modal kamera -->
<div class="modal fade" id="camModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius:20px">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="camTitle">Verifikasi Wajah</h5>
                <button type="button" class="btn-close" id="camClose" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="camView">
                    <div class="cam-wrap" id="camWrap">
                        <video id="camVideo" playsinline muted></video>
                        <div class="cam-oval"></div>
                        <div class="cam-hint" id="camHint">Menyiapkan kamera…</div>
                    </div>
                    <div class="cam-steps" id="camSteps"></div>
                </div>
                <div id="camResult" class="text-center py-4" style="display:none"></div>
            </div>
        </div>
    </div>
</div>

<?php ui_logout_modal(); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/face-scan.js?v=2"></script>
<script>
const CFG = <?= json_encode($cfg, JSON_UNESCAPED_SLASHES) ?>;
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}

/* ---------- Jam server & hitung mundur ---------- */
const t0 = Date.now(), serverBase = CFG.serverNow;
const nowMs = () => serverBase + (Date.now() - t0);
const pad = n => String(n).padStart(2, '0');
let reloaded = false;
function tick() {
    const d = new Date(nowMs());
    // tampilkan dalam WIB (UTC+7) sesuai server
    const w = new Date(d.getTime() + 7 * 3600 * 1000);
    document.getElementById('clock').textContent = pad(w.getUTCHours()) + ':' + pad(w.getUTCMinutes()) + ':' + pad(w.getUTCSeconds());
    const cd = document.getElementById('countdown');
    if (CFG.windowEnd && cd) {
        const s = Math.max(0, Math.floor((CFG.windowEnd - nowMs()) / 1000));
        cd.textContent = 'Sisa waktu absen ' + CFG.allowed + ': ' + pad(Math.floor(s / 60)) + ':' + pad(s % 60) + ' menit';
    } else if (CFG.nextChange && cd) {
        const s = Math.max(0, Math.floor((CFG.nextChange - nowMs()) / 1000));
        const h = Math.floor(s / 3600);
        cd.textContent = 'Jendela berikutnya dalam ' + (h ? h + ' jam ' : '') + pad(Math.floor(s % 3600 / 60)) + ' menit ' + pad(s % 60) + ' detik';
    }
    // saat jam berpindah fase, muat ulang agar tombol sesuai jam
    if (CFG.nextChange && nowMs() >= CFG.nextChange && !reloaded) { reloaded = true; setTimeout(() => location.reload(), 800); }
}
tick(); setInterval(tick, 500);

/* ---------- GPS ---------- */
let gps = { ok: false, lat: null, lng: null, acc: null, dist: null };
function distM(a, b, c, d) {
    const R = 6371000, p1 = a * Math.PI / 180, p2 = c * Math.PI / 180, dp = (c - a) * Math.PI / 180, dl = (d - b) * Math.PI / 180;
    const x = Math.sin(dp / 2) ** 2 + Math.cos(p1) * Math.cos(p2) * Math.sin(dl / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
}
function setCheck(id, cls, icon, text) {
    const li = document.getElementById(id); if (!li) return;
    li.className = cls; li.querySelector('.dot').innerHTML = '<i class="bi bi-' + icon + '"></i>';
    if (text) li.querySelector('span:not(.dot)').innerHTML = text;
}
function refreshButtons() {
    const reasons = [];
    if (!CFG.allowed) reasons.push('belum ada jendela absen yang terbuka');
    if (!gps.ok) reasons.push(gps.dist === null ? 'lokasi GPS belum terdeteksi' : 'Anda di luar radius kantor');
    if (CFG.faceRequired && !CFG.faceEnrolled) reasons.push('wajah belum didaftarkan');
    document.querySelectorAll('.btn-absen').forEach(b => {
        b.disabled = !(CFG.allowed === b.dataset.tipe && gps.ok && (!CFG.faceRequired || CFG.faceEnrolled));
    });
    const r = document.getElementById('btnReason');
    if (r) r.textContent = (CFG.allowed && reasons.length) ? 'Tombol aktif setelah: ' + reasons.join(', ') + '.' : '';
}
if (!navigator.geolocation) {
    setCheck('chkGps', 'bad', 'x-lg', 'Browser tidak mendukung GPS.');
} else {
    navigator.geolocation.watchPosition(pos => {
        gps.lat = pos.coords.latitude; gps.lng = pos.coords.longitude; gps.acc = pos.coords.accuracy;
        gps.dist = distM(gps.lat, gps.lng, CFG.office.lat, CFG.office.lng);
        gps.ok = gps.dist <= CFG.office.radius;
        setCheck('chkGps', gps.ok ? 'ok' : 'bad', gps.ok ? 'check-lg' : 'x-lg',
            'Lokasi: ' + (gps.ok ? 'di dalam' : 'DI LUAR') + ' radius kantor (' + Math.round(gps.dist) + ' m dari kantor, batas ' + Math.round(CFG.office.radius) + ' m, akurasi ±' + Math.round(gps.acc) + ' m)');
        refreshButtons();
    }, err => {
        gps.ok = false;
        setCheck('chkGps', 'bad', 'x-lg', err.code === 1 ? 'Izin lokasi ditolak. Aktifkan izin lokasi pada browser.' : 'Gagal mendapatkan lokasi GPS.');
        refreshButtons();
    }, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });
}
async function fetchCode() {
    try {
        const r = await (await fetch('../generate_barcode.php', { cache: 'no-store' })).json();
        if (r.barcode) { document.getElementById('codeChip').textContent = r.barcode; return r.barcode; }
    } catch (e) {}
    return null;
}
fetchCode(); setInterval(fetchCode, 30000);
refreshButtons();

/* ---------- Kamera / scan wajah ---------- */
const camEl = {
    modal: new bootstrap.Modal(document.getElementById('camModal')),
    title: document.getElementById('camTitle'), view: document.getElementById('camView'), result: document.getElementById('camResult'),
};
const scanner = new FaceScanner({
    video: document.getElementById('camVideo'), wrap: document.getElementById('camWrap'),
    hint: document.getElementById('camHint'), steps: document.getElementById('camSteps'),
});
let busy = false;
document.getElementById('camClose').onclick = () => { scanner.stop(); camEl.modal.hide(); busy = false; };

function showResult(ok, title, msg, reload) {
    scanner.stop();
    camEl.view.style.display = 'none'; camEl.result.style.display = 'block';
    camEl.result.innerHTML =
        '<div class="logout-icon" style="background:' + (ok ? '#dcfce7;color:#16a34a' : '#fee2e2;color:#dc2626') + '"><i class="bi bi-' + (ok ? 'check-lg' : 'x-lg') + '"></i></div>' +
        '<h5>' + title + '</h5><p class="text-muted">' + msg + '</p>' +
        '<button class="btn btn-' + (ok ? 'success' : 'secondary') + ' px-4" id="resOk">' + (ok ? 'Selesai' : 'Tutup') + '</button>' +
        (!ok && !reload ? ' <button class="btn btn-primary px-4" id="resRetry">Coba Lagi</button>' : '');
    document.getElementById('resOk').onclick = () => { camEl.modal.hide(); busy = false; if (reload || ok) location.reload(); };
    const rt = document.getElementById('resRetry');
    if (rt) rt.onclick = () => openCam(currentMode, currentTipe);
}

let currentMode = null, currentTipe = null;
async function openCam(mode, tipe) {
    if (busy && camEl.result.style.display !== 'block') return;
    busy = true; currentMode = mode; currentTipe = tipe;
    camEl.result.style.display = 'none'; camEl.view.style.display = 'block';
    camEl.title.textContent = mode === 'enroll' ? 'Daftarkan Wajah Anda' : 'Verifikasi Wajah — Absen ' + tipe.charAt(0).toUpperCase() + tipe.slice(1);
    camEl.modal.show();
    try {
        const steps = mode === 'enroll' ? ['Kamera', 'Depan', 'Kiri', 'Kanan', 'Simpan'] : ['Kamera', 'Putar kepala', 'Cocokkan wajah', 'Catat absen'];
        scanner.setSteps(steps, 0);
        await scanner.init();
        await scanner.startCamera();
        if (mode === 'enroll') await doEnroll(steps); else await doAbsen(tipe, steps);
    } catch (e) {
        showResult(false, 'Verifikasi gagal', e.message || 'Terjadi kesalahan.', false);
    }
}

async function doEnroll(steps) {
    // depan -> kiri -> kanan -> depan. Data wajah diambil pada frame yang sama saat pose terpenuhi.
    scanner.setSteps(steps, 1);
    const front = await scanner.holdPose('depan', 2);
    const frontMean = FaceScanner.mean(front);
    const photo = scanner.snapshot();

    const samples = [...front];
    for (const [pose, idx] of [['kiri', 2], ['kanan', 3]]) {
        scanner.setSteps(steps, idx); scanner.setState(null);
        const d = await scanner.holdPose(pose, 2);
        const m = FaceScanner.mean(d);
        // Wajah menoleh sering kurang akurat: hanya disimpan bila masih mirip wajah depan
        if (FaceScanner.dist(m, frontMean) <= 0.55) samples.push(m);
    }
    scanner.setSteps(steps, 3); scanner.setState(null);
    scanner.setHint('Kembali lihat lurus ke kamera');
    samples.push(...await scanner.holdPose('depan', 2));

    scanner.setSteps(steps, 4); scanner.setHint('Menyimpan…');
    const fd = new FormData();
    fd.append('descriptors', JSON.stringify(samples)); fd.append('photo', photo);
    let res;
    try {
        const resp = await fetch('../process_face_enroll.php', { method: 'POST', headers: { 'X-CSRF-Token': CFG.csrf }, body: fd });
        const txt = await resp.text();
        try { res = JSON.parse(txt); } catch (e) { res = { ok: false, message: 'Respon server tidak valid (HTTP ' + resp.status + '): ' + txt.replace(/<[^>]*>/g, ' ').slice(0, 160) }; }
    } catch (e) { res = { ok: false, message: 'Tidak bisa menghubungi server: ' + e.message }; }
    if (res.ok) showResult(true, 'Wajah terdaftar', 'Sekarang Anda bisa melakukan absensi dengan scan wajah.', true);
    else showResult(false, 'Gagal mendaftar', res.message, false);
}

async function doAbsen(tipe, steps) {
    // Liveness: menoleh ke arah acak (kiri/kanan), lalu kembali depan -> descriptor diambil pada frame depan terakhir.
    const side = Math.random() < 0.5 ? 'kiri' : 'kanan';
    scanner.setSteps(steps, 1);
    await scanner.holdPose('depan', 0);
    await scanner.holdPose(side, 0);
    scanner.setSteps(steps, 2); scanner.setState(null); scanner.setHint('Kembali lihat lurus ke kamera');
    const desc = FaceScanner.mean(await scanner.holdPose('depan', 3));
    const photo = scanner.snapshot();
    scanner.setSteps(steps, 3); scanner.setHint('Mencocokkan wajah & lokasi di server…');
    const code = await fetchCode();
    const fd = new FormData();
    fd.append('tipe', tipe); fd.append('latitude', gps.lat); fd.append('longitude', gps.lng); fd.append('accuracy', gps.acc);
    fd.append('barcode', code || ''); fd.append('face_descriptor', JSON.stringify(desc)); fd.append('photo', photo);
    const res = await (await fetch('../process_attendance.php', { method: 'POST', headers: { 'X-CSRF-Token': CFG.csrf }, body: fd })).json();
    if (res.ok) showResult(true, 'Absensi berhasil', res.message + '<br><small>Pukul ' + res.waktu + ' • ' + res.jarak + ' m dari kantor</small>', true);
    else showResult(false, 'Absensi ditolak', res.message, !!res.need_enroll);
}

async function absenTanpaWajah(tipe) {
    if (!confirm('Konfirmasi absen ' + tipe.toUpperCase() + '?')) return;
    const code = await fetchCode();
    const fd = new FormData();
    fd.append('tipe', tipe); fd.append('latitude', gps.lat); fd.append('longitude', gps.lng); fd.append('accuracy', gps.acc); fd.append('barcode', code || '');
    const res = await (await fetch('../process_attendance.php', { method: 'POST', headers: { 'X-CSRF-Token': CFG.csrf }, body: fd })).json();
    alert(res.message); if (res.ok) location.reload();
}

document.querySelectorAll('.btn-absen').forEach(b => b.addEventListener('click', () => {
    if (b.disabled) return;
    if (CFG.faceRequired) openCam('absen', b.dataset.tipe); else absenTanpaWajah(b.dataset.tipe);
}));
const be = document.getElementById('btnEnroll');
if (be) be.addEventListener('click', () => openCam('enroll', null));
</script>
</body>
</html>
