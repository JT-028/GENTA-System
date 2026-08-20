<?php
declare(strict_types=1);

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Utility\Security;
use Cake\Log\Log;

/**
 * Decrypt component
 */
class DecryptComponent extends Component
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
        Log::write('debug', "[DecryptComponent] Input: len=" . strlen($hex) . ", first40=" . substr($hex, 0, 40));
        
        try {
            $len = strlen($hex);
            
            if ($len === 144) {
                // New correct format: 48 hex HMAC + 96 hex ciphertext
                $hmac = substr($hex, 0, 48);
                $ciphertextHex = substr($hex, 48);
                $ciphertext = hex2bin($ciphertextHex);
                if ($ciphertext === false) {
                    Log::write('debug', "[DecryptComponent] hex2bin FAILED");
                    return null;
                }
                $encryptedData = $hmac . $ciphertext;
                
            } else if ($len === 142) {
                // Format with 47 char HMAC + 95 hex ciphertext
                Log::write('debug', "[DecryptComponent] Using 142-char format");
                $hmac = substr($hex, 0, 47);
                $ciphertextHex = substr($hex, 47);
                $ciphertext = hex2bin($ciphertextHex);
                if ($ciphertext === false) return null;
                $encryptedData = $hmac . $ciphertext;
                
            } else if ($len === 140) {
                // Old buggy format: full hex2bin
                Log::write('debug', "[DecryptComponent] Using 140-char format");
                $encryptedData = hex2bin($hex);
                if ($encryptedData === false) return null;
                
            } else {
                Log::write('debug', "[DecryptComponent] Unexpected length: $len, trying flexible decode");
                // Try flexible decoding
                if ($len >= 96) {
                    $hmac = substr($hex, 0, 48);
                    $ciphertextHex = substr($hex, 48);
                    $ciphertext = hex2bin($ciphertextHex);
                    if ($ciphertext === false) return null;
                    $encryptedData = $hmac . $ciphertext;
                } else {
                    return null;
                }
            }
            
            // Decrypt
            $decrypted = Security::decrypt($encryptedData, self::ENCRYPTION_KEY);
            
            if ($decrypted === false) {
                Log::write('debug', "[DecryptComponent] Security::decrypt FAILED");
                return null;
            }
            
            Log::write('debug', "[DecryptComponent] SUCCESS: decrypted to '$decrypted'");
            return $decrypted;
            
        } catch (\Exception $e) {
            Log::write('debug', "[DecryptComponent] Exception: " . $e->getMessage());
            return null;
        }
    }
}
