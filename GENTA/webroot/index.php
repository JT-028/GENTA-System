<?php
/**
 * The Front Controller for handling every request
 *
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.2.9
 * @license       MIT License (https://opensource.org/licenses/mit-license.php)
 */

// Check platform requirements
require dirname(__DIR__) . '/config/requirements.php';

// For built-in server
if (PHP_SAPI === 'cli-server') {
    $_SERVER['PHP_SELF'] = '/' . basename(__FILE__);

    $url = parse_url(urldecode($_SERVER['REQUEST_URI']));
    $file = __DIR__ . $url['path'];
    if (strpos($url['path'], '..') === false && strpos($url['path'], '.') !== false && is_file($file)) {
        return false;
    }
}
require dirname(__DIR__) . '/vendor/autoload.php';

// Optional: when running the built-in server or in dev, redirect localhost access
// to the machine's LAN IP so other devices on the WLAN can access the site.
// Controlled by env var AUTO_REDIRECT_LOCALHOST (default: enabled). Set to "false"
// to disable.
try {
    $autoRedirect = getenv('AUTO_REDIRECT_LOCALHOST');
    if ($autoRedirect === false) $autoRedirect = getenv('AUTO_REDIRECT_LOCALHOST');
    $autoRedirect = (is_string($autoRedirect) && in_array(strtolower($autoRedirect), ['0','false','no'])) ? false : true;
} catch (
    Exception $e
) {
    $autoRedirect = true;
}

if ($autoRedirect && PHP_SAPI === 'cli-server') {
    // Only act for requests that used 'localhost' or '127.0.0.1' as Host header
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $hostOnly = preg_replace('/:.*/', '', $host);
    if (in_array($hostOnly, ['localhost', '127.0.0.1'], true)) {
        // Attempt to determine the primary LAN IP by opening a UDP socket to a public IP.
        // This does not send packets to the remote host, just reveals the chosen outbound IP.
        $lanIp = null;
        try {
            $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock !== false) {
                // connect to a public DNS IP (no packets are actually sent for UDP connect)
                socket_connect($sock, '8.8.8.8', 53);
                socket_getsockname($sock, $lanIp);
                socket_close($sock);
            }
        } catch (\Throwable $e) {
            // fallback techniques
            try { $lanIp = gethostbyname(gethostname()); } catch (\Throwable $_) { $lanIp = null; }
        }

        // If we found a sensible LAN IP, redirect the browser to it (keep port and URI)
        if ($lanIp && !in_array($lanIp, ['127.0.0.1', '::1', false, null], true)) {
            $port = $_SERVER['SERVER_PORT'] ?? '80';
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            // Only redirect when Host header is localhost to avoid surprising behavior
            $target = $scheme . '://' . $lanIp;
            // By default do NOT preserve a non-standard port (so redirect to e.g. http://192.168.0.107/GENTA/...)
            // Set environment variable PRESERVE_PORT=1 to opt-in to keeping the original port (useful for builtin servers).
            $preservePort = (getenv('PRESERVE_PORT') === '1' || getenv('PRESERVE_PORT') === 'true');
            if ($preservePort) {
                if ($port && !in_array($port, ['80','443'], true)) $target .= ':' . $port;
            }
            $target .= $uri;
            // Send a 302 redirect and exit
            header('Location: ' . $target, true, 302);
            exit;
        }
    }
}

use App\Application;
use Cake\Http\Server;

// Bind your application to the server.
$server = new Server(new Application(dirname(__DIR__) . '/config'));

// Run the request/response through the application and emit the response.
$server->emit($server->run());
