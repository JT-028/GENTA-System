<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;
use Cake\View\View;
use Cake\Utility\Security;

/**
 * Encrypt helper
 */
class EncryptHelper extends Helper
{
    protected const ENCRYPTION_KEY = 'REDACTED_SET_APP_ENCRYPTION_KEY';

    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    protected $_defaultConfig = [];

    public function hex($hex)
    {
        // Security::encrypt returns: 48 ASCII hex chars (HMAC) + 48 bytes of binary (ciphertext)
        // The HMAC is already hex-encoded, only the ciphertext needs encoding
        $encrypted = Security::encrypt((string) $hex, self::ENCRYPTION_KEY);
        
        // Split: first 48 chars are hex (HMAC), remaining 48 bytes are binary (ciphertext)
        $hmac = substr($encrypted, 0, 48);  // Already hex
        $ciphertext = substr($encrypted, 48);  // Binary
        
        // Encode only the binary part to hex
        $result = $hmac . bin2hex($ciphertext);  // Total: 48 + 96 = 144 chars
        
        return $result;
    }
}
