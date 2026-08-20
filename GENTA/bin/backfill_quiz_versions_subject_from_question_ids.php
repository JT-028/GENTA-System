<?php
// Usage: php bin/backfill_quiz_versions_subject_from_question_ids.php
$root = dirname(__DIR__);
$configPath = $root . '/config/app_local.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "config/app_local.php not found at $configPath\n");
    exit(2);
}
if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        if ($val === false) return $default;
        return $val;
    }
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

echo "Selecting quiz_versions rows missing subject_id...\n";
$sql = "SELECT id, question_ids, metadata FROM quiz_versions WHERE subject_id IS NULL OR subject_id = ''";
$res = $mysqli->query($sql);
if (!$res) {
    fwrite(STDERR, "Query failed: ({$mysqli->errno}) {$mysqli->error}\n");
    $mysqli->close();
    exit(7);
}

$updated = 0;
$checked = 0;
while ($row = $res->fetch_assoc()) {
    $checked++;
    $id = (int)$row['id'];
    $subjectId = null;
    // 1) try metadata
    if (!empty($row['metadata'])) {
        $meta = @json_decode($row['metadata'], true);
        if (is_array($meta) && !empty($meta['subject_id'])) {
            $subjectId = (int)$meta['subject_id'];
        }
    }
    // 2) if still null, try question_ids JSON -> first question -> lookup Questions.subject_id
    if (empty($subjectId) && !empty($row['question_ids'])) {
        $qids = @json_decode($row['question_ids'], true);
        if (is_array($qids) && count($qids)) {
            // take first numeric id
            $first = null;
            foreach ($qids as $v) { if (is_numeric($v)) { $first = (int)$v; break; } }
            if ($first) {
                $qRow = $mysqli->query('SELECT subject_id FROM questions WHERE id = ' . (int)$first . ' LIMIT 1');
                if ($qRow) {
                    $qR = $qRow->fetch_assoc();
                    if ($qR && !empty($qR['subject_id'])) {
                        $subjectId = (int)$qR['subject_id'];
                    }
                    $qRow->free();
                }
            }
        }
    }

    if ($subjectId) {
        $stmt = $mysqli->prepare('UPDATE quiz_versions SET subject_id = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $subjectId, $id);
            $stmt->execute();
            if ($stmt->affected_rows >= 0) $updated++;
            $stmt->close();
        }
    }
}

echo "Checked {$checked} rows, updated {$updated} rows.\n";
$res->free();
$mysqli->close();
exit(0);
