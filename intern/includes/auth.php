<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../user/index.php');
        exit;
    }
}

function requireUser() {
    requireLogin();
    if (isAdmin()) {
        header('Location: ../admin/index.php');
        exit;
    }
}

/** Token CSRF untuk request POST/AJAX */
function csrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function csrfValid(?string $t): bool {
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

/** Untuk endpoint JSON: balas error JSON, bukan redirect */
function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonRequireUser(): void {
    if (!isLoggedIn() || isAdmin()) jsonOut(['ok' => false, 'message' => 'Sesi berakhir. Silakan login ulang.'], 401);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['ok' => false, 'message' => 'Metode tidak valid.'], 405);
    $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!csrfValid($tok)) jsonOut(['ok' => false, 'message' => 'Token keamanan tidak valid. Muat ulang halaman.'], 403);
}
