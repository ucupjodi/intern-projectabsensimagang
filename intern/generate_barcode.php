<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
if (!isLoggedIn()) jsonOut(['ok' => false, 'message' => 'Sesi berakhir.'], 401);

$office = $pdo->query("SELECT secret_key FROM office_location LIMIT 1")->fetch();
$minute = date('Y-m-d H:i'); // berubah setiap menit
$barcode = strtoupper(substr(hash_hmac('sha256', $minute . '|' . $_SESSION['user_id'], $office['secret_key']), 0, 12));

jsonOut(['barcode' => $barcode, 'server_time' => time()]);
