<?php
session_start();

function respond($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function require_login() {
    if (empty($_SESSION['user'])) {
        respond(['ok' => false, 'message' => 'Please log in first.'], 401);
    }
    return $_SESSION['user'];
}

function require_admin() {
    $user = require_login();
    if ($user['role'] !== 'admin') {
        respond(['ok' => false, 'message' => 'Admins only.'], 403);
    }
    return $user;
}

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=student_hub;charset=utf8mb4',
        'hub_user',
        'hub_pass_123',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    respond(['ok' => false, 'message' => 'Database connection failed. Is MySQL running?'], 500);
}
