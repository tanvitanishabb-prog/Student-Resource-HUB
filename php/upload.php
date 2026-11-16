<?php
require __DIR__ . '/db.php';

$action = $_GET['action'] ?? 'upload';

// ---------- Upload a PDF ----------
if ($action === 'upload') {
    $user = require_login();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['ok' => false, 'message' => 'Invalid request.'], 405);
    }

    $title   = trim($_POST['title'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $desc    = trim($_POST['description'] ?? '');

    if ($title === '' || $subject === '') {
        respond(['ok' => false, 'message' => 'Title and subject are required.'], 400);
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        respond(['ok' => false, 'message' => 'Choose a PDF to upload (max 10 MB).'], 400);
    }

    $file = $_FILES['file'];

    if ($file['size'] > 10 * 1024 * 1024) {
        respond(['ok' => false, 'message' => 'The file is larger than 10 MB.'], 400);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if ($mime !== 'application/pdf') {
        respond(['ok' => false, 'message' => 'Only PDF files are allowed.'], 400);
    }

    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Random stored name so files cannot be guessed or overwritten
    $stored = bin2hex(random_bytes(16)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $stored)) {
        respond(['ok' => false, 'message' => 'Could not save the file.'], 500);
    }

    $original = substr(basename($file['name']), 0, 255);
    $status   = $user['role'] === 'admin' ? 'approved' : 'pending';

    $stmt = $pdo->prepare(
        'INSERT INTO resources (user_id, title, subject, description, file_name, original_name, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$user['id'], $title, $subject, $desc, $stored, $original, $status]);

    respond([
        'ok' => true,
        'message' => $status === 'approved'
            ? 'Uploaded and published.'
            : 'Uploaded. An admin will review it before it appears in the library.',
    ]);
}

// ---------- Library: approved resources, with search ----------
// ---------- Mine: the logged-in user's own uploads ----------
if ($action === 'list' || $action === 'mine') {
    $q       = trim($_GET['q'] ?? '');
    $subject = trim($_GET['subject'] ?? '');

    $sql = 'SELECT r.id, r.title, r.subject, r.description, r.status, r.downloads, r.created_at, u.name AS uploader
            FROM resources r JOIN users u ON u.id = r.user_id WHERE ';
    $params = [];

    if ($action === 'mine') {
        $user = require_login();
        $sql .= 'r.user_id = ?';
        $params[] = $user['id'];
    } else {
        $sql .= "r.status = 'approved'";
    }

    if ($q !== '') {
        $sql .= ' AND (r.title LIKE ? OR r.subject LIKE ? OR r.description LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
    if ($subject !== '') {
        $sql .= ' AND r.subject = ?';
        $params[] = $subject;
    }

    $sql .= ' ORDER BY r.id DESC LIMIT 100';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $subjects = $pdo->query("SELECT DISTINCT subject FROM resources WHERE status = 'approved' ORDER BY subject")
                    ->fetchAll(PDO::FETCH_COLUMN);

    respond(['ok' => true, 'resources' => $stmt->fetchAll(), 'subjects' => $subjects]);
}

// ---------- Admin: list pending ----------
if ($action === 'pending') {
    require_admin();
    $stmt = $pdo->query(
        "SELECT r.id, r.title, r.subject, r.description, r.status, r.downloads, r.created_at, u.name AS uploader
         FROM resources r JOIN users u ON u.id = r.user_id
         WHERE r.status = 'pending' ORDER BY r.id ASC"
    );
    respond(['ok' => true, 'resources' => $stmt->fetchAll()]);
}

// ---------- Admin: approve or reject ----------
if ($action === 'review') {
    require_admin();
    $id       = (int)($_POST['id'] ?? 0);
    $decision = $_POST['decision'] ?? '';
    $status   = ['approve' => 'approved', 'reject' => 'rejected'][$decision] ?? null;

    if (!$id || !$status) {
        respond(['ok' => false, 'message' => 'Invalid request.'], 400);
    }
    $pdo->prepare('UPDATE resources SET status = ? WHERE id = ?')->execute([$status, $id]);
    respond(['ok' => true, 'message' => 'Resource ' . $status . '.']);
}

// ---------- Download a PDF ----------
if ($action === 'download') {
    $user = require_login();
    $id = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM resources WHERE id = ?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();

    if (!$r) {
        respond(['ok' => false, 'message' => 'Resource not found.'], 404);
    }
    if ($r['status'] !== 'approved' && (int)$r['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
        respond(['ok' => false, 'message' => 'This resource is not available.'], 403);
    }

    $path = __DIR__ . '/../uploads/' . $r['file_name'];
    if (!is_file($path)) {
        respond(['ok' => false, 'message' => 'File is missing on the server.'], 404);
    }

    $pdo->prepare('UPDATE resources SET downloads = downloads + 1 WHERE id = ?')->execute([$id]);

    $safeName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $r['original_name']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

respond(['ok' => false, 'message' => 'Unknown action.'], 400);
