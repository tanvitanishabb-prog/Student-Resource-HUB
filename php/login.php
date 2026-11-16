<?php
require __DIR__ . '/db.php';

$action = $_GET['action'] ?? 'login';

if ($action === 'session') {
    respond(['ok' => true, 'user' => $_SESSION['user'] ?? null]);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    respond(['ok' => true]);
}

// ---- login ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'message' => 'Invalid request.'], 405);
}

$email = strtolower(trim($_POST['email'] ?? ''));
$pass  = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) {
    respond(['ok' => false, 'message' => 'Email or password is incorrect.'], 401);
}

session_regenerate_id(true);
$_SESSION['user'] = [
    'id'    => (int)$user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
];

respond(['ok' => true, 'message' => 'Logged in.']);