<?php
declare(strict_types=1);

namespace App\Utility;

use Cake\Core\Configure;
use RuntimeException;

/**
 * Resolves the application encryption key from environment / config.
 * Never hardcode secrets in source that is committed to git.
 */
final class EncryptionKey
{
    public static function get(): string
    {
        $key = (string)(
            env('APP_ENCRYPTION_KEY')
            ?: Configure::read('App.encryptionKey')
            ?: ''
        );

        if ($key === '') {
            throw new RuntimeException(
                'APP_ENCRYPTION_KEY is not set. Copy .env.example to .env (or set App.encryptionKey in config/app_local.php).'
            );
        }

        return $key;
    }
}
