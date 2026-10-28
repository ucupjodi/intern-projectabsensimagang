<?php
require_once '../includes/auth.php';
requireUser();
require_once '../config/database.php';
require_once '../includes/ui.php';

$nama = $_SESSION['nama'] ?? 'User';
$stmt = $pdo->prepare("SELECT * FROM attendances WHERE user_id = ? ORDER BY tanggal DESC, waktu DESC LIMIT 120");
$stmt->execute([(int)$_SESSION['user_id']]);
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Absensi - Sistem Absensi Magang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=2" rel="stylesheet">
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<div class="container-fluid p-0"><div class="row g-0">
    <?php ui_sidebar('user', 'riwayat'); ?>
    <div class="col-lg-10">
        <?php ui_topbar('user', $nama); ?>
        <main class="p-3 p-md-4">
            <div class="mb-4"><h4 class="mb-1">Riwayat Absensi</h4><p class="text-muted mb-0">120 catatan terakhir Anda</p></div>
            <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-sm-text">
                    <thead class="table-light"><tr><th class="ps-3">Tanggal</th><th>Jenis</th><th>Jam</th><th>Status</th><th>Jarak</th><th>Wajah</th></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada riwayat absensi</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): [$lbl, $cls] = statusLabel($r['status']); ?>
                        <tr>
                            <td class="ps-3"><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                            <td><span class="badge rounded-pill bg-<?= $r['tipe'] === 'masuk' ? 'success' : 'primary' ?>"><?= ucfirst($r['tipe']) ?></span></td>
                            <td><?= substr($r['waktu'], 0, 5) ?></td>
                            <td><span class="badge bg-<?= $cls ?>"><?= $lbl ?><?= (int)$r['late_minutes'] ? ' ' . (int)$r['late_minutes'] . ' mnt' : '' ?></span></td>
                            <td><?= $r['distance_m'] !== null ? round($r['distance_m']) . ' m' : '-' ?></td>
                            <td><?= !empty($r['face_verified']) ? '<i class="bi bi-patch-check-fill text-success"></i> Terverifikasi' : '<span class="text-muted">-</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div></div>
        </main>
    </div>
</div></div>
<?php ui_logout_modal(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('show');document.getElementById('sidebarOverlay').classList.toggle('show');}</script>
</body>
</html>
