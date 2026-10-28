<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';
require_once '../includes/ui.php';

$pageTitle = 'Data Absensi - Sistem Absensi Magang';
$nama = $_SESSION['nama'] ?? 'Admin';

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || !strtotime($tanggal)) $tanggal = date('Y-m-d');
$search = trim($_GET['search'] ?? '');

// posisi waktu terhadap tanggal yang dilihat
$today = date('Y-m-d');
if ($tanggal === $today)      $nowSec = (int)date('G') * 3600 + (int)date('i') * 60 + (int)date('s');
elseif ($tanggal < $today)    $nowSec = 86399;
else                          $nowSec = -1;

$st = $pdo->prepare("SELECT * FROM attendance_settings WHERE day_name = ?");
$st->execute([dayNameId(strtotime($tanggal))]);
$setting = $st->fetch() ?: null;

$sql = "SELECT id, nama_lengkap, username FROM users WHERE role = 'user'";
$params = [];
if ($search !== '') { $sql .= " AND (nama_lengkap LIKE ? OR username LIKE ?)"; $params = ["%$search%", "%$search%"]; }
$sql .= " ORDER BY nama_lengkap ASC";
$us = $pdo->prepare($sql); $us->execute($params);
$users = $us->fetchAll();

$at = $pdo->prepare("SELECT * FROM attendances WHERE tanggal = ?");
$at->execute([$tanggal]);
$byUser = [];
foreach ($at->fetchAll() as $r) $byUser[$r['user_id']][$r['tipe']] = $r;

$rows = []; $count = ['Hadir' => 0, 'Terlambat' => 0, 'Alpa' => 0, 'Belum' => 0];
foreach ($users as $u) {
    $m = $byUser[$u['id']]['masuk'] ?? null; $p = $byUser[$u['id']]['pulang'] ?? null;
    $sum = dailySummary($setting, $nowSec, $m, $p);
    $rows[] = ['u' => $u, 'm' => $m, 'p' => $p, 'sum' => $sum];
    if (str_starts_with($sum['label'], 'Terlambat')) $count['Terlambat']++;
    elseif (in_array($sum['label'], ['Hadir', 'Tidak Absen Pulang'], true)) $count['Hadir']++;
    elseif ($sum['label'] === 'Alpa') $count['Alpa']++;
    elseif ($sum['label'] === 'Belum Absen') $count['Belum']++;
}

function cellWaktu(?array $r): string {
    if (!$r) return '<span class="text-muted">—</span>';
    [$lbl, $cls] = statusLabel($r['status']);
    $late = (int)($r['late_minutes'] ?? 0);
    return '<div class="fw-semibold">' . substr($r['waktu'], 0, 5) . '</div><span class="badge bg-' . $cls . '">' . $lbl . ($late ? " $late mnt" : '') . '</span>';
}
function cellBukti(?array $r): string {
    if (!$r) return '';
    $h = '';
    if (!empty($r['photo_path'])) {
        $src = '../' . htmlspecialchars($r['photo_path']);
        $h .= '<img src="' . $src . '" class="face-thumb me-1" alt="foto" data-bs-toggle="modal" data-bs-target="#photoModal" data-src="' . $src . '">';
    }
    $h .= '<div class="d-inline-block align-middle small lh-sm">';
    $h .= !empty($r['face_verified']) ? '<span class="text-success"><i class="bi bi-patch-check-fill"></i> Wajah cocok</span>' : '<span class="text-muted">Tanpa scan wajah</span>';
    if ($r['face_score'] !== null) $h .= '<br><span class="text-muted">skor ' . number_format((float)$r['face_score'], 2) . '</span>';
    if ($r['latitude'] !== null) {
        $h .= '<br><a href="https://www.google.com/maps?q=' . (float)$r['latitude'] . ',' . (float)$r['longitude'] . '" target="_blank" rel="noopener"><i class="bi bi-geo-alt"></i> '
            . ($r['distance_m'] !== null ? round($r['distance_m']) . ' m' : 'peta') . '</a>';
    }
    return $h . '</div>';
}
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
    <?php ui_sidebar('admin', 'attendance'); ?>
    <div class="col-lg-10">
        <?php ui_topbar('admin', $nama, false); ?>
        <main class="p-3 p-md-4">
            <div class="mb-4"><h4 class="mb-1">Data Absensi</h4><p class="text-muted mb-0">Rekap kehadiran anak magang per hari — yang tidak absen otomatis tercatat <b>Alpa</b> setelah jam masuk ditutup</p></div>

            <div class="card shadow-sm border-0 mb-3"><div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4"><label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal) ?>"></div>
                    <div class="col-md-5"><label class="form-label">Cari Nama / Username</label>
                        <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama..."></div>
                    <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filter</button></div>
                </form>
            </div></div>

            <div class="row g-3 mb-3">
                <?php foreach ([['Hadir', 'success', 'bi-check-circle'], ['Terlambat', 'warning', 'bi-alarm'], ['Alpa', 'danger', 'bi-x-circle'], ['Belum', 'secondary', 'bi-hourglass-split']] as [$k, $c, $i]): ?>
                    <div class="col-6 col-md-3"><div class="card stat-card shadow-sm border-0"><div class="card-body d-flex justify-content-between align-items-center">
                        <div><div class="text-muted small"><?= $k === 'Belum' ? 'Belum Absen' : $k ?></div><div class="fs-3 fw-bold"><?= $count[$k] ?></div></div>
                        <i class="bi <?= $i ?> fs-2 text-<?= $c ?>"></i></div></div></div>
                <?php endforeach; ?>
            </div>
            <?php if (!$setting): ?><div class="alert alert-secondary">Tanggal ini bukan hari kerja (Senin–Jumat).</div><?php endif; ?>

            <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-sm-text">
                    <thead class="table-light"><tr>
                        <th class="ps-3">#</th><th>Nama</th><th>Masuk</th><th>Bukti Masuk</th><th>Pulang</th><th>Bukti Pulang</th><th>Status Harian</th>
                    </tr></thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada anak magang</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td class="ps-3"><?= $i + 1 ?></td>
                            <td><div class="fw-medium"><?= htmlspecialchars($r['u']['nama_lengkap']) ?></div><small class="text-muted">@<?= htmlspecialchars($r['u']['username']) ?></small></td>
                            <td><?= cellWaktu($r['m']) ?></td>
                            <td style="min-width:170px"><?= cellBukti($r['m']) ?></td>
                            <td><?= cellWaktu($r['p']) ?></td>
                            <td style="min-width:170px"><?= cellBukti($r['p']) ?></td>
                            <td><span class="badge bg-<?= $r['sum']['class'] ?>"><?= htmlspecialchars($r['sum']['label']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div></div>
        </main>
    </div>
</div></div>

<div class="modal fade" id="photoModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 bg-transparent">
    <img id="photoBig" class="img-fluid rounded-4" alt="Foto absensi">
</div></div></div>

<?php ui_logout_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() { document.getElementById('sidebar').classList.toggle('show'); document.getElementById('sidebarOverlay').classList.toggle('show'); }
document.getElementById('photoModal').addEventListener('show.bs.modal', e => { document.getElementById('photoBig').src = e.relatedTarget.dataset.src; });
</script>
</body>
</html>
