<?php
/**
 * Password History Migration Runner
 * Run this script once to create the password_history table
 * 
 * Usage: php bin/migrate_password_history.php
 */

// Load database configuration
$configPath = dirname(__DIR__) . '/config/app_local.php';
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config/app.php';
}

$config = require $configPath;
$dbConfig = $config['Datasources']['default'];

// Connect to database
try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=utf8mb4',
        $dbConfig['host'],
        $dbConfig['database']
    );
    
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database: {$dbConfig['database']}\n";
    
    // Read migration file
    $migrationFile = dirname(__DIR__) . '/config/Migrations/20251206_create_password_history.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: {$migrationFile}");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Execute migration
    echo "Running migration...\n";
    $pdo->exec($sql);
    
    echo "✓ Successfully created password_history table\n";
    echo "✓ Migration completed!\n\n";
    
    // Show table info
    $stmt = $pdo->query("DESCRIBE password_history");
    echo "Table structure:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-20s %-20s %-10s %-10s\n", "Field", "Type", "Null", "Key");
    echo str_repeat("-", 80) . "\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf(
            "%-20s %-20s %-10s %-10s\n",
            $row['Field'],
            $row['Type'],
            $row['Null'],
            $row['Key']
        );
    }
    echo str_repeat("-", 80) . "\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
