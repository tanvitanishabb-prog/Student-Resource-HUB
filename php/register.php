<?php
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'message' => 'Invalid request.'], 405);
}

$name   = trim($_POST['name'] ?? '');
$email  = strtolower(trim($_POST['email'] ?? ''));
$course = trim($_POST['course'] ?? '');
$pass   = $_POST['password'] ?? '';

if ($name === '' || $email === '' || $pass === '') {
    respond(['ok' => false, 'message' => 'Name, email and password are required.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['ok' => false, 'message' => 'Enter a valid email address.'], 400);
}
if (strlen($pass) < 8) {
    respond(['ok' => false, 'message' => 'Password must be at least 8 characters.'], 400);
}

$check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    respond(['ok' => false, 'message' => 'An account with this email already exists.'], 409);
}

$insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, course) VALUES (?, ?, ?, ?)');
$insert->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), $course]);

respond(['ok' => true, 'message' => 'Account created. You can log in now.']);
