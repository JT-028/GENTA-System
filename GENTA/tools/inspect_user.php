<?php
// Quick CLI helper to inspect recent rows in the users table.
// Place this in tools/inspect_user.php and run: php tools/inspect_user.php

 // Minimal implementation of Cake's env() helper so we can safely require the local config
 if (!function_exists('env')) {
    function env($key, $default = null) {
        $val = getenv($key);
        return ($val !== false) ? $val : $default;
    }
 }

 $config = require __DIR__ . '/../config/app_local.php';
$ds = $config['Datasources']['default'] ?? [];
$host = $ds['host'] ?? '127.0.0.1';
$user = $ds['username'] ?? 'root';
$pass = $ds['password'] ?? '';
$db   = $ds['database'] ?? '';
$charset = 'utf8mb4';
$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connect failed: " . $e->getMessage() . "\n");
    exit(2);
}

$sql = "SELECT id, email, profile_image, status, created FROM users ORDER BY id DESC LIMIT 10";
try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        echo "No users found.\n";
        exit(0);
    }
    // Print a header
    printf("%-6s %-30s %-22s %-6s %-20s\n", 'id', 'email', 'profile_image', 'status', 'created');
    echo str_repeat('-', 95) . "\n";
    foreach ($rows as $r) {
        printf("%-6s %-30s %-22s %-6s %-20s\n",
            $r['id'], $r['email'] ?? '<null>', $r['profile_image'] ?? '<null>', $r['status'] ?? '<null>', $r['created'] ?? '<null>');
    }
} catch (PDOException $e) {
    fwrite(STDERR, "Query failed: " . $e->getMessage() . "\n");
    exit(3);
}

echo "\nTip: if recent new users have profile_image = 'default_profile.png' and status = 1, they should immediately show the assets fallback.\n";
