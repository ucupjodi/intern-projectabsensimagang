<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../config/database.php';
require_once '../includes/ui.php';

$pageTitle = 'Kelola User - DISKOMINFOSANTIK';

$nama = $_SESSION['nama'] ?? 'Admin';
$parts = explode(' ', trim($nama));
$initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

// Reset wajah (anak magang harus daftar ulang)
if (isset($_POST['reset_face'])) {
    if (csrfValid($_POST['csrf'] ?? '')) {
        $pdo->prepare("UPDATE users SET face_descriptors = NULL, face_registered_at = NULL, face_photo = NULL WHERE id = ? AND role = 'user'")->execute([(int)$_POST['reset_face']]);
        header('Location: users.php?success=Data wajah direset. User harus mendaftarkan wajah lagi.');
    } else {
        header('Location: users.php?success=Token tidak valid');
    }
    exit;
}

// Hapus user
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'")->execute([$id]);
        header('Location: users.php?success=User berhasil dihapus');
        exit;
    }
}

// Tambah / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['reset_face'])) {
    $id       = $_POST['id'] ?? null;
    $namaUser = trim($_POST['nama_lengkap']);
    $username = trim($_POST['username']);
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'user';

    if ($id) {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET nama_lengkap=?, username=?, password=?, role=? WHERE id=?");
            $stmt->execute([$namaUser, $username, $hash, $role, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET nama_lengkap=?, username=?, role=? WHERE id=?");
            $stmt->execute([$namaUser, $username, $role, $id]);
        }
        header('Location: users.php?success=User berhasil diupdate');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (nama_lengkap, username, password, role) VALUES (?,?,?,?)");
        $stmt->execute([$namaUser, $username, $hash, $role]);
        header('Location: users.php?success=User berhasil ditambahkan');
    }
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY role DESC, nama_lengkap ASC")->fetchAll();
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
        <?php ui_sidebar('admin', 'users'); ?>

        <div class="col-lg-10">
            <?php ui_topbar('admin', $nama, false); ?>

            <main class="p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                    <div>
                        <h4 class="mb-1">Kelola User</h4>
                        <p class="text-muted mb-0">Manajemen akun anak magang & admin</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                        <i class="bi bi-plus-lg"></i> Tambah User
                    </button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($_GET['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Nama Lengkap</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Wajah</th>
                                        <th>Tanggal Daftar</th>
                                        <th width="170">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $i => $u): ?>
                                        <tr>
                                            <td class="ps-3"><?= $i + 1 ?></td>
                                            <td class="fw-medium"><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                                            <td><?= htmlspecialchars($u['username']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                                    <?= ucfirst($u['role']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($u['role'] === 'admin'): ?><span class="text-muted">—</span>
                                                <?php elseif (!empty($u['face_descriptors'])): ?>
                                                    <?php if (!empty($u['face_photo'])): ?><img src="../<?= htmlspecialchars($u['face_photo']) ?>" class="face-thumb me-1" alt="wajah"><?php endif; ?>
                                                    <span class="badge bg-success">Terdaftar</span>
                                                <?php else: ?><span class="badge bg-secondary">Belum</span><?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" 
                                                    onclick='editUser(<?= json_encode(array_diff_key($u, ['password' => 1, 'face_descriptors' => 1])) ?>)'
                                                    data-bs-toggle="modal" data-bs-target="#userModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <?php if ($u['role'] !== 'admin' && !empty($u['face_descriptors'])): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Reset data wajah user ini? User harus daftar wajah ulang.')">
                                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
                                                        <input type="hidden" name="reset_face" value="<?= (int)$u['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-secondary" title="Reset wajah"><i class="bi bi-person-x"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ($u['role'] !== 'admin'): ?>
                                                    <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger"
                                                       onclick="return confirm('Yakin hapus user ini?')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="userId">
                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="nama" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password <small class="text-muted">(kosongkan jika tidak diubah)</small></label>
                    <input type="password" name="password" id="password" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select name="role" id="role" class="form-select">
                        <option value="user">User (Anak Magang)</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php ui_logout_modal(); ?>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
function resetForm() {
    document.getElementById('modalTitle').textContent = 'Tambah User';
    document.getElementById('userId').value = '';
    document.getElementById('nama').value = '';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('password').required = true;
    document.getElementById('role').value = 'user';
}
function editUser(user) {
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('userId').value = user.id;
    document.getElementById('nama').value = user.nama_lengkap;
    document.getElementById('username').value = user.username;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('role').value = user.role;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>