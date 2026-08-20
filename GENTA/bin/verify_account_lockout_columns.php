<?php
/**
 * Verify that failed_login_attempts and account_locked_until columns exist in users table
 * If not, this script will guide you to add them.
 */

require dirname(__DIR__) . '/config/bootstrap.php';

use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;

try {
    echo "Checking for account lockout columns in users table...\n\n";
    
    $connection = ConnectionManager::get('default');
    
    // Check if columns exist
    $checkSql = "SELECT 
        COUNT(CASE WHEN COLUMN_NAME = 'failed_login_attempts' THEN 1 END) AS has_failed_attempts,
        COUNT(CASE WHEN COLUMN_NAME = 'account_locked_until' THEN 1 END) AS has_locked_until
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'users'";
    
    $result = $connection->execute($checkSql)->fetch('assoc');
    
    $hasFailedAttempts = $result['has_failed_attempts'] > 0;
    $hasLockedUntil = $result['has_locked_until'] > 0;
    
    if ($hasFailedAttempts && $hasLockedUntil) {
        echo "✓ SUCCESS: Both columns exist!\n\n";
        echo "Column Details:\n";
        echo "---------------\n";
        
        $detailsSql = "SELECT 
            COLUMN_NAME, 
            COLUMN_TYPE, 
            IS_NULLABLE, 
            COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'users'
            AND COLUMN_NAME IN ('failed_login_attempts', 'account_locked_until')
        ORDER BY ORDINAL_POSITION";
        
        $details = $connection->execute($detailsSql)->fetchAll('assoc');
        
        foreach ($details as $column) {
            echo sprintf(
                "- %s: %s, Nullable: %s, Default: %s\n",
                $column['COLUMN_NAME'],
                $column['COLUMN_TYPE'],
                $column['IS_NULLABLE'],
                $column['COLUMN_DEFAULT'] ?? 'NULL'
            );
        }
        
        echo "\n✓ Your database is ready for per-account login timeout tracking!\n";
        exit(0);
    } else {
        echo "✗ MISSING COLUMNS:\n\n";
        
        if (!$hasFailedAttempts) {
            echo "- failed_login_attempts column is missing\n";
        }
        if (!$hasLockedUntil) {
            echo "- account_locked_until column is missing\n";
        }
        
        echo "\n";
        echo "To add the missing columns, run the following SQL:\n";
        echo "======================================================\n\n";
        
        if (!$hasFailedAttempts) {
            echo "ALTER TABLE `users` ADD COLUMN `failed_login_attempts` INT DEFAULT 0 AFTER `password_reset_expires`;\n";
        }
        if (!$hasLockedUntil) {
            echo "ALTER TABLE `users` ADD COLUMN `account_locked_until` DATETIME NULL AFTER `failed_login_attempts`;\n";
        }
        
        echo "\n";
        echo "Or run the complete migration file:\n";
        echo "php bin/cake migrations migrate\n";
        echo "\n";
        echo "Or manually execute:\n";
        echo "mysql -u [username] -p [database_name] < config/schema/20251206_add_security_fields.sql\n";
        
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
