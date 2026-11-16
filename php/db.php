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

// Railway MySQL Connection
try {

    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASS');

    if (!$host || !$dbname || !$username) {
        throw new Exception('Database environment variables are missing.');
    }

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

} catch (Throwable $e) {

    error_log('Database error: ' . $e->getMessage());

    respond([
        'ok' => false,
        'message' => 'Database connection failed.'
    ], 500);
}
?>
