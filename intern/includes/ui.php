<?php
/** Komponen UI bersama: sidebar, topbar (menu profil + logout), modal logout */

function ui_initials(string $nama): string {
    $parts = preg_split('/\s+/', trim($nama));
    $a = mb_strtoupper(mb_substr($parts[0] ?? 'U', 0, 1));
    $b = isset($parts[1]) ? mb_strtoupper(mb_substr($parts[1], 0, 1)) : '';
    return $a . $b;
}

function ui_sidebar(string $role, string $active): void {
    $items = $role === 'admin' ? [
        'index'      => ['index.php',      'bi-speedometer2',   'Dashboard'],
        'users'      => ['users.php',      'bi-people',         'Kelola User'],
        'attendance' => ['attendance.php', 'bi-calendar-check', 'Data Absensi'],
        'settings'   => ['settings.php',   'bi-gear',           'Pengaturan'],
    ] : [
        'index'      => ['index.php',      'bi-geo-alt',        'Absensi'],
        'riwayat'    => ['riwayat.php',    'bi-clock-history',  'Riwayat Saya'],
    ];
    ?>
    <nav class="col-lg-2 sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="../assets/img/logodiskominfo.png" alt="Logo Gunung Mas" class="sidebar-logo">
            <div class="sidebar-title">Absensi Magang</div>
            <div class="sidebar-sub">Diskominfosantik Gunung Mas</div>
        </div>
        <ul class="nav flex-column px-3 flex-grow-1">
            <?php foreach ($items as $key => [$href, $icon, $label]): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $key === $active ? 'active' : '' ?>" href="<?= $href ?>"><i class="bi <?= $icon ?> me-2"></i><?= $label ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php
}

/** Topbar: tanggal di kiri, tombol profil (avatar + nama + panah) di kanan, menu berisi Logout */
function ui_topbar(string $role, string $nama, bool $showDate = true): void {
    $roleLabel = $role === 'admin' ? 'Administrator' : 'Anak Magang';
    $username  = $_SESSION['username'] ?? '';
    ?>
    <div class="topbar">
        <button class="btn btn-outline-secondary d-lg-none me-2" onclick="toggleSidebar()" aria-label="Menu">
            <i class="bi bi-list fs-5"></i>
        </button>
        <?php if ($showDate): ?>
            <div class="d-none d-md-block">
                <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= dayNameId() ?>, <?= date('d F Y') ?></span>
            </div>
        <?php endif; ?>

        <div class="dropdown ms-auto">
            <button class="profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="profile-avatar"><i class="bi bi-person-fill"></i></span>
                <span class="profile-label d-none d-sm-inline"><?= htmlspecialchars($role === 'admin' ? 'Administrator' : $nama) ?></span>
                <i class="bi bi-chevron-down profile-caret"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end profile-menu">
                <div class="profile-menu-head">
                    <span class="profile-avatar lg"><?= htmlspecialchars(ui_initials($nama)) ?></span>
                    <div class="min-w-0">
                        <div class="fw-semibold text-truncate"><?= htmlspecialchars($nama) ?></div>
                        <div class="small text-muted text-truncate">@<?= htmlspecialchars($username) ?></div>
                        <span class="badge rounded-pill role-chip"><?= $roleLabel ?></span>
                    </div>
                </div>
                <div class="dropdown-divider my-2"></div>
                <button type="button" class="dropdown-item logout-item" data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>
                        <strong>Logout</strong>
                        <small>Keluar dari sistem</small>
                    </span>
                </button>
            </div>
        </div>
    </div>
    <?php
}

function ui_logout_modal(): void {
    ?>
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg logout-modal">
                <div class="modal-body text-center p-4">
                    <div class="logout-icon"><i class="bi bi-box-arrow-right"></i></div>
                    <h5 class="mb-1">Keluar dari sistem?</h5>
                    <p class="text-muted small mb-4">Sesi Anda akan diakhiri dan Anda perlu login kembali untuk melanjutkan.</p>
                    <div class="d-grid gap-2">
                        <a href="logout.php" class="btn btn-danger"><i class="bi bi-box-arrow-right me-1"></i> Ya, Logout</a>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
