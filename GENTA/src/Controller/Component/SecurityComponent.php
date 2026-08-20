<?php
declare(strict_types=1);

namespace App\Controller\Component;

use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Cache\Cache;
use Cake\Log\Log;

/**
 * Security Component
 * 
 * Provides security features:
 * - Rate limiting for login attempts
 * - Account lockout after failed attempts
 * - IP-based rate limiting
 * - Session security
 */
class SecurityComponent extends Component
{
    /**
     * Default configuration
     */
    protected $_defaultConfig = [
        'rateLimit' => [
            'enabled' => true,
            'maxAttempts' => 5,           // Max failed login attempts
            'windowSeconds' => 300,        // Time window (5 minutes)
            'lockoutMinutes' => 15,        // Lockout duration after max attempts
            'byIp' => true,                // Enable IP-based rate limiting
            'byEmail' => true,             // Enable email-based rate limiting
        ],
        'session' => [
            'regenerate' => true,          // Regenerate session ID on login
            'timeout' => 1800,             // Session timeout (30 minutes)
            'absoluteTimeout' => 43200,    // Absolute session timeout (12 hours)
        ],
    ];

    /**
     * Check if IP or email is rate limited
     *
     * @param string|null $email Email address
     * @param string|null $ip IP address
     * @return array ['allowed' => bool, 'remainingAttempts' => int, 'lockoutTime' => int|null]
     */
    public function checkRateLimit(?string $email = null, ?string $ip = null): array
    {
        $config = $this->getConfig('rateLimit');
        
        if (!$config['enabled']) {
            return ['allowed' => true, 'remainingAttempts' => $config['maxAttempts'], 'lockoutTime' => null];
        }

        $blocked = false;
        $minRemaining = $config['maxAttempts'];
        $maxLockoutTime = null;

        // Check IP-based rate limit
        if ($config['byIp'] && $ip) {
            $ipResult = $this->_checkLimit('ip_' . $ip, $config);
            if (!$ipResult['allowed']) {
                $blocked = true;
            }
            $minRemaining = min($minRemaining, $ipResult['remainingAttempts']);
            if ($ipResult['lockoutTime'] && (!$maxLockoutTime || $ipResult['lockoutTime'] > $maxLockoutTime)) {
                $maxLockoutTime = $ipResult['lockoutTime'];
            }
        }

        // Check email-based rate limit
        if ($config['byEmail'] && $email) {
            $emailResult = $this->_checkLimit('email_' . strtolower($email), $config);
            if (!$emailResult['allowed']) {
                $blocked = true;
            }
            $minRemaining = min($minRemaining, $emailResult['remainingAttempts']);
            if ($emailResult['lockoutTime'] && (!$maxLockoutTime || $emailResult['lockoutTime'] > $maxLockoutTime)) {
                $maxLockoutTime = $emailResult['lockoutTime'];
            }
        }

        return [
            'allowed' => !$blocked,
            'remainingAttempts' => $minRemaining,
            'lockoutTime' => $maxLockoutTime
        ];
    }

    /**
     * Record a failed login attempt
     *
     * @param string|null $email Email address
     * @param string|null $ip IP address
     * @return void
     */
    public function recordFailedAttempt(?string $email = null, ?string $ip = null): void
    {
        $config = $this->getConfig('rateLimit');
        
        if (!$config['enabled']) {
            return;
        }

        // Record IP-based attempt
        if ($config['byIp'] && $ip) {
            $this->_recordAttempt('ip_' . $ip, $config);
        }

        // Record email-based attempt
        if ($config['byEmail'] && $email) {
            $this->_recordAttempt('email_' . strtolower($email), $config);
        }

        Log::warning('Failed login attempt', [
            'email' => $email,
            'ip' => $ip,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Clear failed attempts (call after successful login)
     *
     * @param string|null $email Email address
     * @param string|null $ip IP address
     * @return void
     */
    public function clearFailedAttempts(?string $email = null, ?string $ip = null): void
    {
        $config = $this->getConfig('rateLimit');

        if ($config['byIp'] && $ip) {
            Cache::delete('rate_limit_ip_' . $ip, 'security');
            Cache::delete('lockout_ip_' . $ip, 'security');
        }

        if ($config['byEmail'] && $email) {
            Cache::delete('rate_limit_email_' . strtolower($email), 'security');
            Cache::delete('lockout_email_' . strtolower($email), 'security');
        }
    }

    /**
     * Regenerate session ID (call after successful login)
     *
     * @return void
     */
    public function regenerateSession(): void
    {
        $config = $this->getConfig('session');
        
        if ($config['regenerate']) {
            $request = $this->getController()->getRequest();
            $session = $request->getSession();
            
            if ($session) {
                $session->renew();
                
                // Store session metadata
                $session->write('Security.loginTime', time());
                $session->write('Security.lastActivity', time());
                $session->write('Security.ip', $this->getClientIp());
                $session->write('Security.userAgent', $request->getHeaderLine('User-Agent'));
            }
        }
    }

    /**
     * Check session validity and timeouts
     *
     * @return array ['valid' => bool, 'reason' => string|null]
     */
    public function checkSession(): array
    {
        $config = $this->getConfig('session');
        $request = $this->getController()->getRequest();
        $session = $request->getSession();

        if (!$session) {
            return ['valid' => true, 'reason' => null];
        }

        $loginTime = $session->read('Security.loginTime');
        $lastActivity = $session->read('Security.lastActivity');
        $sessionIp = $session->read('Security.ip');
        $sessionUserAgent = $session->read('Security.userAgent');
        $currentTime = time();

        // Check absolute timeout (max session lifetime)
        if ($loginTime && ($currentTime - $loginTime) > $config['absoluteTimeout']) {
            return ['valid' => false, 'reason' => 'absolute_timeout'];
        }

        // Check idle timeout (inactivity)
        if ($lastActivity && ($currentTime - $lastActivity) > $config['timeout']) {
            return ['valid' => false, 'reason' => 'idle_timeout'];
        }

        // Check IP address consistency (optional security check)
        $currentIp = $this->getClientIp();
        if ($sessionIp && $currentIp && $sessionIp !== $currentIp) {
            Log::warning('Session IP mismatch', [
                'session_ip' => $sessionIp,
                'current_ip' => $currentIp
            ]);
            // Don't invalidate session on IP change (mobile networks change IPs)
            // but log it for security monitoring
        }

        // Update last activity time
        $session->write('Security.lastActivity', $currentTime);

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Check rate limit for a specific key
     *
     * @param string $key Cache key
     * @param array $config Rate limit configuration
     * @return array
     */
    protected function _checkLimit(string $key, array $config): array
    {
        $cacheKey = 'rate_limit_' . $key;
        $lockoutKey = 'lockout_' . $key;

        // Check if currently locked out
        $lockoutUntil = Cache::read($lockoutKey, 'security');
        if ($lockoutUntil && time() < $lockoutUntil) {
            return [
                'allowed' => false,
                'remainingAttempts' => 0,
                'lockoutTime' => $lockoutUntil
            ];
        }

        // Get attempt history
        $attempts = Cache::read($cacheKey, 'security') ?: [];
        $windowStart = time() - $config['windowSeconds'];
        
        // Filter attempts within the window
        $recentAttempts = array_filter($attempts, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        $attemptCount = count($recentAttempts);
        $remainingAttempts = max(0, $config['maxAttempts'] - $attemptCount);

        // Check if max attempts reached
        if ($attemptCount >= $config['maxAttempts']) {
            // Set lockout
            $lockoutUntil = time() + ($config['lockoutMinutes'] * 60);
            Cache::write($lockoutKey, $lockoutUntil, 'security');
            
            return [
                'allowed' => false,
                'remainingAttempts' => 0,
                'lockoutTime' => $lockoutUntil
            ];
        }

        return [
            'allowed' => true,
            'remainingAttempts' => $remainingAttempts,
            'lockoutTime' => null
        ];
    }

    /**
     * Record an attempt
     *
     * @param string $key Cache key
     * @param array $config Rate limit configuration
     * @return void
     */
    protected function _recordAttempt(string $key, array $config): void
    {
        $cacheKey = 'rate_limit_' . $key;
        $attempts = Cache::read($cacheKey, 'security') ?: [];
        $attempts[] = time();
        
        // Store attempts for window duration
        Cache::write($cacheKey, $attempts, 'security');
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public function getClientIp(): string
    {
        $request = $this->getController()->getRequest();
        
        // Check for proxy headers
        $ip = $request->getHeaderLine('X-Forwarded-For');
        if ($ip) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }
        
        if (!$ip) {
            $ip = $request->getHeaderLine('X-Real-IP');
        }
        
        if (!$ip) {
            $ip = $request->clientIp();
        }
        
        return $ip ?: '0.0.0.0';
    }
}
