<?php
// Usage: php bin/inspect_quiz_versions.php
$root = dirname(__DIR__);
$configPath = $root . '/config/app_local.php';
// Provide env() helper if not defined (app_local.php expects it)
if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val === false) return $default;
        return $val;
    }
}
if (!file_exists($configPath)) {
    fwrite(STDERR, "config/app_local.php not found at $configPath\n");
    exit(2);
}
$config = require $configPath;
if (!isset($config['Datasources']['default'])) {
    fwrite(STDERR, "Database configuration not found in config/app_local.php\n");
    exit(3);
}
$db = $config['Datasources']['default'];
$host = $db['host'] ?? 'localhost';
$user = $db['username'] ?? '';
$pass = $db['password'] ?? '';
$name = $db['database'] ?? '';
$port = $db['port'] ?? null;

$mysqli = null;
if ($port) {
    $mysqli = mysqli_init();
    $ok = mysqli_real_connect($mysqli, $host, (int)$port, $user, $pass, $name);
} else {
    $mysqli = new mysqli($host, $user, $pass, $name);
}
if ($mysqli->connect_errno) {
    fwrite(STDERR, "MySQL connection failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n");
    exit(6);
}

echo "Fetching up to 50 quiz_versions rows...\n\n";
$sql = "SELECT id, quiz_id, version_number, question_ids, metadata, subject_id, created_by, created FROM quiz_versions ORDER BY id DESC LIMIT 50";
$res = $mysqli->query($sql);
if (!$res) {
    fwrite(STDERR, "Query failed: ({$mysqli->errno}) {$mysqli->error}\n");
    $mysqli->close();
    exit(7);
}

$out = [];
while ($r = $res->fetch_assoc()) {
    $qids = $r['question_ids'];
    if (strlen($qids) > 200) $qids = substr($qids, 0, 197) . '...';
    $meta = $r['metadata'];
    if (strlen($meta) > 200) $meta = substr($meta, 0, 197) . '...';
    $out[] = [
        'id' => (int)$r['id'],
        'quiz_id' => $r['quiz_id'] === null ? null : (int)$r['quiz_id'],
        'version_number' => $r['version_number'] === null ? null : (int)$r['version_number'],
        'subject_id' => $r['subject_id'] === null ? null : (int)$r['subject_id'],
        'created_by' => $r['created_by'] === null ? null : (int)$r['created_by'],
        'created' => $r['created'],
        'question_ids' => $qids,
        'metadata' => $meta
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
$res->free();
$mysqli->close();
exit(0);
