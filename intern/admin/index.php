<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';
require_once '../includes/ui.php';

$pageTitle = 'Dashboard Admin - DISKOMINFOSANTIK';

$nama = $_SESSION['nama'] ?? 'Admin';
$parts = explode(' ', trim($nama));
$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

$totalUser          = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalAbsenHariIni  = $pdo->query("SELECT COUNT(*) FROM attendances WHERE tanggal = CURDATE()")->fetchColumn();
$absenMasukHariIni  = $pdo->query("SELECT COUNT(*) FROM attendances WHERE tanggal = CURDATE() AND tipe = 'masuk'")->fetchColumn();
$absenPulangHariIni = $pdo->query("SELECT COUNT(*) FROM attendances WHERE tanggal = CURDATE() AND tipe = 'pulang'")->fetchColumn();

$recent = $pdo->query("
    SELECT a.*, u.nama_lengkap 
    FROM attendances a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 10
")->fetchAll();

$hariList = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$hariIni  = $hariList[date('w')];
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
        <?php ui_sidebar('admin', 'index'); ?>

        <!-- Main -->
        <div class="col-lg-10">
            <?php ui_topbar('admin', $nama, true); ?>

            <main class="p-3 p-md-4">
                <div class="mb-4">
                    <h4 class="mb-1">Dashboard</h4>
                    <p class="text-muted mb-0">Ringkasan aktivitas absensi hari ini</p>
                </div>

                <!-- Statistik -->
                <div class="row g-3 g-md-4 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="card stat-card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-1 small">Total Anak Magang</h6>
                                        <h3 class="mb-0 fw-bold"><?= $totalUser ?></h3>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-2 rounded-3">
                                        <i class="bi bi-people text-primary fs-5"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-1 small">Absen Hari Ini</h6>
                                        <h3 class="mb-0 fw-bold"><?= $totalAbsenHariIni ?></h3>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-2 rounded-3">
                                        <i class="bi bi-calendar-check text-success fs-5"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-1 small">Absen Masuk</h6>
                                        <h3 class="mb-0 fw-bold"><?= $absenMasukHariIni ?></h3>
                                    </div>
                                    <div class="bg-info bg-opacity-10 p-2 rounded-3">
                                        <i class="bi bi-box-arrow-in-right text-info fs-5"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card shadow-sm border-0 h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-muted mb-1 small">Absen Pulang</h6>
                                        <h3 class="mb-0 fw-bold"><?= $absenPulangHariIni ?></h3>
                                    </div>
                                    <div class="bg-warning bg-opacity-10 p-2 rounded-3">
                                        <i class="bi bi-box-arrow-right text-warning fs-5"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabel -->
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Absensi Terbaru</h5>
                        <a href="attendance.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Nama</th>
                                        <th>Tanggal</th>
                                        <th>Tipe</th>
                                        <th>Waktu</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                                Belum ada data absensi
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recent as $r): ?>
                                            <tr>
                                                <td class="ps-3 fw-medium"><?= htmlspecialchars($r['nama_lengkap']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($r['tanggal'])) ?></td>
                                                <td>
                                                    <span class="badge rounded-pill bg-<?= $r['tipe'] === 'masuk' ? 'success' : 'primary' ?>">
                                                        <?= ucfirst($r['tipe']) ?>
                                                    </span>
                                                </td>
                                                <td><?= substr($r['waktu'], 0, 5) ?></td>
                                                <?php [$sl, $sc] = statusLabel($r['status']); ?><td><span class="badge bg-<?= $sc ?>"><?= $sl ?><?= (int)($r['late_minutes'] ?? 0) ? ' ' . (int)$r['late_minutes'] . ' mnt' : '' ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<?php ui_logout_modal(); ?>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>