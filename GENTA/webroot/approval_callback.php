<?php
// Lightweight server-to-server approval callback handler.
// This bypasses the normal CakePHP authentication middleware so the Flask
// admin can POST signed approval/rejection messages.

// Load local config to obtain DB credentials and callback secret.
$cfgPath = __DIR__ . '/../config/app_local.php';
$cfg = [];
if (file_exists($cfgPath)) {
    $cfg = include $cfgPath;
}

$secret = getenv('CALLBACK_SECRET') ?: ($cfg['App']['callbackSecret'] ?? '');

// Read raw body
$raw = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

// Minimal validation: require JSON body
if (stripos($contentType, 'application/json') === false) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'expected application/json']);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'invalid json']);
    exit;
}

// Verify HMAC signature if configured
$provided = '';
if (!empty($_SERVER['HTTP_X_CALLBACK_SIGNATURE'])) {
    $provided = trim($_SERVER['HTTP_X_CALLBACK_SIGNATURE']);
    if (strpos($provided, 'sha256=') === 0) {
        $provided = substr($provided, 7);
    }
}

if (!empty($secret)) {
    $expected = hash_hmac('sha256', $raw, $secret);
    if (empty($provided) || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: application/json');
        error_log('approval_callback.php: signature mismatch provided=' . ($provided ?: '<none>') . ' expected=' . $expected);
        echo json_encode(['success' => false, 'message' => 'invalid signature']);
        exit;
    }
} else {
    // In dev mode, allow unsigned callbacks but log
    error_log('approval_callback.php: no CALLBACK_SECRET configured; accepting unsigned callback');
}

$teacherId = $data['teacher_id'] ?? $data['id'] ?? null;
$status = $data['status'] ?? null;

if (!$teacherId || $status === null) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'missing teacher_id or status']);
    exit;
}

// Read DB credentials from config (fallback to env)
$dbHost = getenv('DB_HOST') ?: ($cfg['Datasources']['default']['host'] ?? 'localhost');
$dbUser = getenv('DB_USER') ?: ($cfg['Datasources']['default']['username'] ?? 'root');
$dbPass = getenv('DB_PASS') ?: ($cfg['Datasources']['default']['password'] ?? '');
$dbName = getenv('DB_NAME') ?: ($cfg['Datasources']['default']['database'] ?? null);

if (!$dbName) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'database not configured']);
    exit;
}

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'db connection failed']);
    error_log('approval_callback.php: DB connect failed: ' . $mysqli->connect_error);
    exit;
}

// Normalize id to integer when possible
if (is_numeric($teacherId)) {
    $tid = (int)$teacherId;
} else {
    $tid = null;
}

$lower = strtolower((string)$status);
$approved = in_array($lower, ['approved', 'approve', '1', 'true'], true);

if ($tid) {
    if ($approved) {
        $stmt = $mysqli->prepare('UPDATE users SET status = 1 WHERE id = ?');
        $stmt->bind_param('i', $tid);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'updated' => 1]);
            exit;
        }
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'failed to update']);
        exit;
    } else {
        $stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $tid);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'deleted' => 1]);
            exit;
        }
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'failed to delete']);
        exit;
    }
} else {
    // If teacher_id isn't numeric, try to search by string id (rare)
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('s', $teacherId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if ($row && isset($row['id'])) {
        $tid2 = (int)$row['id'];
        // repeat same approve/delete logic
        if ($approved) {
            $stmt = $mysqli->prepare('UPDATE users SET status = 1 WHERE id = ?');
            $stmt->bind_param('i', $tid2);
            $ok = $stmt->execute(); $stmt->close();
            header('Content-Type: application/json'); echo json_encode(['success' => (bool)$ok]); exit;
        } else {
            $stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bind_param('i', $tid2);
            $ok = $stmt->execute(); $stmt->close();
            header('Content-Type: application/json'); echo json_encode(['success' => (bool)$ok]); exit;
        }
    }
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'user not found']);
exit;
