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
// Railway MySQL Connection
try {

    $databaseUrl = getenv('DATABASE_URL');

    if (!$databaseUrl) {
        throw new Exception('DATABASE_URL is missing.');
    }

    $db = parse_url($databaseUrl);

    $host = $db['host'];
    $port = $db['port'] ?? 3306;
    $username = urldecode($db['user']);
    $password = urldecode($db['pass']);
    $dbname = ltrim($db['path'], '/');

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
