<?php
// Run this script via PHP CLI: php bin/apply_migration.php
$root = dirname(__DIR__);
$configPath = $root . '/config/app_local.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "config/app_local.php not found at $configPath\n");
    exit(2);
}
// Provide a minimal env() helper if CakePHP's env() is not available in CLI context
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

// We'll apply migration idempotently by checking for existing table/column
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

// Check if quiz_version_id column exists on student_quiz
$dbName = $mysqli->real_escape_string($name ?: $mysqli->query("SELECT DATABASE() AS db")->fetch_object()->db ?? '');
$checkColSql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_quiz' AND COLUMN_NAME = 'quiz_version_id'";
$res = $mysqli->query($checkColSql);
if ($res) {
    $row = $res->fetch_assoc();
    if (isset($row['cnt']) && (int)$row['cnt'] > 0) {
        echo "Column quiz_version_id already exists on student_quiz, skipping ALTER TABLE.\n";
    } else {
        echo "Adding column quiz_version_id to student_quiz...\n";
        $alterSql = "ALTER TABLE `student_quiz` ADD COLUMN `quiz_version_id` INT NULL AFTER `subject_id`";
        if ($mysqli->query($alterSql) === TRUE) {
            echo "Added column quiz_version_id.\n";
        } else {
            fwrite(STDERR, "Failed to add column: ({$mysqli->errno}) {$mysqli->error}\n");
            $mysqli->close();
            exit(9);
        }
    }
    $res->free();
} else {
    fwrite(STDERR, "Failed to query information_schema for column check: ({$mysqli->errno}) {$mysqli->error}\n");
    $mysqli->close();
    exit(10);
}

// Check if quiz_versions table exists
$checkTblSql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_versions'";
$res2 = $mysqli->query($checkTblSql);
if ($res2) {
    $row2 = $res2->fetch_assoc();
    if (isset($row2['cnt']) && (int)$row2['cnt'] > 0) {
        echo "Table quiz_versions already exists, skipping CREATE TABLE.\n";
    } else {
        echo "Creating table quiz_versions...\n";
        $createSql = <<<SQL
CREATE TABLE `quiz_versions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `quiz_id` INT NOT NULL,
  `version_number` INT NOT NULL DEFAULT 1,
  `question_ids` JSON NOT NULL,
  `metadata` JSON DEFAULT NULL,
  `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_quiz_versions_quiz_id` (`quiz_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        if ($mysqli->query($createSql) === TRUE) {
            echo "Created quiz_versions table.\n";
            // Attempt to add FK if referenced quiz table exists
            // Note: adjust the referenced table name if needed
            // Attempt to add FK; try common quiz table names ('quiz', 'quizzes')
            $fkAdded = false;
            $attempts = [
                ['table' => 'quiz', 'fk' => 'fk_quiz_versions_quiz'],
                ['table' => 'quizzes', 'fk' => 'fk_quiz_versions_quizzes']
            ];
            foreach ($attempts as $a) {
                $fkSql = "ALTER TABLE `quiz_versions` ADD CONSTRAINT `{$a['fk']}` FOREIGN KEY (`quiz_id`) REFERENCES `{$a['table']}`(`id`) ON DELETE CASCADE";
                try {
                    if ($mysqli->query($fkSql) === TRUE) {
                        echo "Added foreign key {$a['fk']} referencing {$a['table']}.\n";
                        $fkAdded = true;
                        break;
                    }
                } catch (\mysqli_sql_exception $ex) {
                    // try next candidate
                    echo "Could not add FK referencing {$a['table']}: ({$mysqli->errno}) {$mysqli->error}\n";
                }
            }
            if (!$fkAdded) {
                echo "Skipped adding foreign key for quiz_versions.quiz_id; you may add it manually if desired.\n";
            }
        } else {
            fwrite(STDERR, "Failed to create table quiz_versions: ({$mysqli->errno}) {$mysqli->error}\n");
            $mysqli->close();
            exit(11);
        }
    }
    $res2->free();
} else {
    fwrite(STDERR, "Failed to query information_schema for table check: ({$mysqli->errno}) {$mysqli->error}\n");
    $mysqli->close();
    exit(12);
}

echo "Migration tasks completed.\n";

// Ensure quiz_versions.subject_id exists and backfill from metadata (idempotent)
$checkQvCol = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'quiz_versions' AND COLUMN_NAME = 'subject_id'";
$res3 = $mysqli->query($checkQvCol);
if ($res3) {
    $r3 = $res3->fetch_assoc();
    if (isset($r3['cnt']) && (int)$r3['cnt'] > 0) {
        echo "Column subject_id already exists on quiz_versions, skipping ALTER TABLE.\n";
    } else {
        echo "Adding column subject_id to quiz_versions...\n";
        $alterQv = "ALTER TABLE `quiz_versions` ADD COLUMN `subject_id` INT NULL AFTER `quiz_id`";
        if ($mysqli->query($alterQv) === TRUE) {
            echo "Added column subject_id.\n";
        } else {
            fwrite(STDERR, "Failed to add column subject_id: ({$mysqli->errno}) {$mysqli->error}\n");
        }
    }
    $res3->free();
} else {
    fwrite(STDERR, "Failed to query information_schema for quiz_versions columns: ({$mysqli->errno}) {$mysqli->error}\n");
}

// Backfill subject_id from metadata where possible
echo "Attempting to backfill quiz_versions.subject_id from metadata...\n";
// First, try an atomic JSON_EXTRACT-based update (MySQL 5.7+)
$jsonUpdateSql = "UPDATE `quiz_versions` SET subject_id = JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.subject_id')) WHERE metadata IS NOT NULL AND (subject_id IS NULL OR subject_id = '')";
try {
    if ($mysqli->query($jsonUpdateSql) === TRUE) {
        echo "Backfilled subject_id via JSON_EXTRACT (if MySQL supports it). Affected rows: {$mysqli->affected_rows}\n";
    } else {
        echo "JSON_EXTRACT update did not run or affected no rows. Error: ({$mysqli->errno}) {$mysqli->error}\n";
    }
} catch (\Throwable $e) {
    echo "JSON_EXTRACT update failed: " . $e->getMessage() . "\n";
}

// Fallback: parse metadata with PHP for rows still missing subject_id
$selectMissing = "SELECT id, metadata FROM quiz_versions WHERE (subject_id IS NULL OR subject_id = '') AND metadata IS NOT NULL";
$res4 = $mysqli->query($selectMissing);
if ($res4) {
    $updated = 0;
    while ($row = $res4->fetch_assoc()) {
        $id = (int)$row['id'];
        $meta = $row['metadata'];
        $subjectId = null;
        if ($meta) {
            // Try JSON decode first
            $decoded = @json_decode($meta, true);
            if (is_array($decoded) && isset($decoded['subject_id'])) {
                $subjectId = (int)$decoded['subject_id'];
            } else {
                // Fallback: regex search for "subject_id"\s*:\s*<number>
                if (preg_match('/"subject_id"\s*:\s*(\d+)/', $meta, $m)) {
                    $subjectId = (int)$m[1];
                }
            }
        }
        if ($subjectId) {
            $u = $mysqli->prepare("UPDATE `quiz_versions` SET subject_id = ? WHERE id = ?");
            if ($u) {
                $u->bind_param('ii', $subjectId, $id);
                $u->execute();
                if ($u->affected_rows >= 0) $updated++;
                $u->close();
            }
        }
    }
    echo "Backfill fallback: updated {$updated} rows from metadata via PHP parse.\n";
    $res4->free();
} else {
    echo "No rows require backfill or select failed: ({$mysqli->errno}) {$mysqli->error}\n";
}

$mysqli->close();
return 0;
