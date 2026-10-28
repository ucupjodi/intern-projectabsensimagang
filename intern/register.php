<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php include 'includes/auth_head.php'; ?>
    <title>Daftar - Sistem Absensi Magang</title>
</head>
<body class="auth-body">
<div class="auth-bg" aria-hidden="true"></div>
<main class="auth-page">
    <header class="auth-brand">
        <img class="brand-logo" src="assets/img/logodiskominfo.png" alt="Logo Diskominfosantik Kabupaten Gunung Mas">
        <div class="org-name">Dinas Komunikasi, Informatika,<br>Persandian dan Statistik</div>
        <div class="org-kab">Kabupaten Gunung Mas</div>
    </header>
    <section class="auth-panel">
        <div class="glass-card">
            <h2>Daftar</h2>
            <p class="glass-sub">Isi data diri Anda untuk membuat akun<br>sistem absensi magang</p>
            <?php if (isset($_GET['error'])): ?>
                <div class="auth-alert error" role="alert"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($_GET['error']) ?></div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="auth-alert ok" role="alert"><i class="bi bi-check-circle"></i><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
            <form action="process_register.php" method="POST">
                <label class="field"><i class="bi bi-person-vcard-fill"></i>
                    <input type="text" name="nama_lengkap" placeholder="Nama lengkap" required autocomplete="name"></label>
                <label class="field"><i class="bi bi-person-fill"></i>
                    <input type="text" name="username" placeholder="Username" required autocomplete="username"></label>
                <label class="field"><i class="bi bi-lock-fill"></i>
                    <input type="password" name="password" id="pw" placeholder="Password (min. 6 karakter)" required minlength="6" autocomplete="new-password">
                    <button type="button" class="eye" id="togglePw" aria-label="Tampilkan password"><i class="bi bi-eye-fill"></i></button></label>
                <button type="submit" class="btn-login"><i class="bi bi-person-plus-fill"></i> Daftar</button>
            </form>
            <div class="glass-foot">Sudah punya akun? <a href="index.php">Login</a></div>
        </div>
        <p class="copyright">© <?= date('Y') ?> Diskominfosantik Kabupaten Gunung Mas</p>
    </section>
</main>
<script>
document.getElementById('togglePw').addEventListener('click', function () {
    var i = document.getElementById('pw'), show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    this.innerHTML = '<i class="bi bi-eye' + (show ? '-slash' : '') + '-fill"></i>';
});
</script>
</body>
</html>
