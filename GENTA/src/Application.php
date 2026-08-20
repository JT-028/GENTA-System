<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     3.3.0
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App;

use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Datasource\FactoryLocator;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\ORM\Locator\TableLocator;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceInterface;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Cake\Routing\Router;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication implements AuthenticationServiceProviderInterface
{
    /**
     * Load all the application configuration and bootstrap logic.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        if (PHP_SAPI === 'cli') {
            $this->bootstrapCli();
        } else {
            FactoryLocator::add(
                'Table',
                (new TableLocator())->allowFallbackClass(false)
            );
        }

        // Log configured app base values so we can verify the application
        // is loading environment overrides (e.g. app_local.php) when running
        // under a subdirectory like /GENTA.
        try {
            // Keep a lighter-weight informational entry rather than verbose debug noise.
            // Emit this at debug level to avoid noisy info-level logs in normal runs.
            \Cake\Log\Log::write('debug', 'Configured App.base=' . (string)Configure::read('App.base') . ' fullBaseUrl=' . (string)Configure::read('App.fullBaseUrl'));
        } catch (\Throwable $e) {
            // Avoid throwing during bootstrap if logging isn't available yet.
        }
        // DebugKit plugin loading removed to fully disable the toolbar

        

        // Load more plugins here
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // Log incoming request details early to diagnose base/path issues
            ->add(function (ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler) {
                try {
                    $reqUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
                    $pathInfo = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
                    $uriPath = (string)$request->getUri()->getPath();
                    // Attempt to read Cake-specific base attribute if present
                    $baseAttr = $request->getAttribute('base') ?? (string)\Cake\Core\Configure::read('App.base');

                    // If the incoming REQUEST_URI contains the application base twice (e.g. /GENTA/GENTA),
                    // normalize it by removing the duplicated segment. This addresses cases where a
                    // relative Location header or a client-side relative link produced /GENTA/GENTA.
                    if (!empty($baseAttr) && strpos($reqUri, $baseAttr . $baseAttr) === 0) {
                        $fixedReqUri = preg_replace('#^' . preg_quote($baseAttr . $baseAttr, '#') . '#', $baseAttr, $reqUri, 1);
                        // Log a concise informational message when we detect and correct a duplicated base path.
                        // Lower to debug: helpful when diagnosing base duplication but too noisy for info.
                        \Cake\Log\Log::write('debug', 'Request diagnostics: normalized duplicated base from ' . $reqUri . ' to ' . $fixedReqUri);
                        // Update PHP server superglobal so downstream code reading it sees the normalized URI
                        $_SERVER['REQUEST_URI'] = $fixedReqUri;
                        // Normalize the PSR-7 URI path too by removing the leading baseAttr from the path
                        $newPath = $uriPath;
                        if (strpos($newPath, $baseAttr) === 0) {
                            // remove the leading baseAttr from the path so Router sees the intended sub-path
                            $newPath = substr($newPath, strlen($baseAttr));
                            if ($newPath === '') {
                                $newPath = '/';
                            }
                        }
                        $request = $request->withUri($request->getUri()->withPath($newPath));
                        $uriPath = $newPath;
                        // update local copy of reqUri for logging below
                        $reqUri = $_SERVER['REQUEST_URI'];
                    }

                    // If the request path equals the configured app base without a trailing slash,
                    // redirect to the canonical trailing-slash form (e.g. /GENTA -> /GENTA/).
                    // This prevents the router from interpreting the base segment as a controller name.
                    $baseNoSlash = rtrim($baseAttr, '/');
                    $currentPath = $request->getUri()->getPath();
                    if (!empty($baseNoSlash) && ($currentPath === $baseNoSlash || $currentPath === $baseAttr)) {
                        $target = $baseNoSlash . '/';
                        // Redirecting to the canonical base path is an important event - record at info level.
                        // Keep this at debug level during normal operation to avoid logfile noise.
                        \Cake\Log\Log::write('debug', 'Request diagnostics: redirecting base path ' . $currentPath . ' to ' . $target);
                        $response = new \Cake\Http\Response();
                        // Use 307 to preserve the original HTTP method (POST) when redirecting
                        // the canonical base path. This prevents POST -> GET conversion that
                        // breaks form submissions targeting the application root.
                        return $response->withStatus(307)->withHeader('Location', $target);
                    }

                    // Reduce volume by emitting a single informational snapshot rather than debug spam.
                    \Cake\Log\Log::write('debug', 'Request diagnostics: REQUEST_URI=' . $reqUri . ' UriPath=' . $uriPath . ' BaseAttr=' . $baseAttr);
                } catch (\Throwable $e) {
                    \Cake\Log\Log::write('error', 'Request diagnostics error: ' . $e->getMessage());
                }
                return $handler->handle($request);
            })

            // Add routing middleware.
            // If you have a large number of routes connected, turning on routes
            // caching in production could improve performance.
            // See https://github.com/CakeDC/cakephp-cached-routing
            ->add(new RoutingMiddleware($this))

            // Parse various types of encoded request bodies so that they are
            // available as array through $request->getData()
            // For our server-to-server approval callbacks we skip the BodyParser
            // because some clients post raw JSON in ways that trigger a 400
            // from the parser. We want the controller to handle the raw body
            // and perform HMAC verification itself.
            ->add(function (ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler) {
                try {
                    $path = (string)$request->getUri()->getPath();
                    // If the client included the HMAC signature header we should
                    // also treat this as an API callback even when the PSR-7 path
                    // doesn't contain the action name (hosting under a subpath
                    // or normalization can change the visible path). This makes
                    // the raw-capture robust when requests arrive with the
                    // X-Callback-Signature header set.
                    $hasSignatureHeader = (bool)$request->getHeaderLine('X-Callback-Signature');
                    // Match broadly: the app may be hosted under a base path (e.g. /GENTA),
                    // so check for the callback action name rather than an exact prefix.
                    if (strpos($path, 'approvalCallback') !== false || $hasSignatureHeader) {
                        // Capture raw request bytes and some header diagnostics to help
                        // debug why the BodyParser is rejecting incoming callbacks.
                        // Temporary debug: record whether a signature header was present
                        // and log its (trimmed) value. This helps detect header
                        // normalization or proxy stripping when callbacks arrive.
                        // Intentionally do not log the signature header value in production.
                        // We'll write the raw bytes to a timestamped file in logs and
                        // attach the raw content as a request attribute so controllers
                        // can still access it even if php://input was consumed here.
                        try {
                            $raw = @file_get_contents('php://input');
                            $headers = [];
                            foreach ($request->getHeaders() as $k => $vals) {
                                $headers[$k] = implode(', ', $vals);
                            }
                            $meta = [
                                'path' => $path,
                                'method' => $request->getMethod(),
                                'content_type' => $request->getHeaderLine('Content-Type'),
                                'content_length' => $request->getHeaderLine('Content-Length'),
                                'timestamp' => date('Y-m-d_His'),
                            ];
                            // Use dirname(__DIR__) to compute project root reliably instead
                            // of relying on the ROOT constant which may not be available.
                            $dumpDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
                            if (!is_dir($dumpDir)) { @mkdir($dumpDir, 0777, true); }
                            $fname = $dumpDir . DIRECTORY_SEPARATOR . 'approval_cb_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $meta['timestamp']) . '.txt';
                            $out = "--- META ---\n" . json_encode($meta) . "\n--- HEADERS ---\n" . json_encode($headers) . "\n--- RAW ---\n" . $raw;
                            @file_put_contents($fname, $out);
                            // Attach raw payload as an attribute for downstream controllers
                            if ($raw !== false && $raw !== null) {
                                $request = $request->withAttribute('raw_body', $raw);
                            }
                        } catch (\Throwable $_) {
                            // Continue even if logging fails
                            \Cake\Log\Log::write('error', 'Failed to capture raw approvalCallback input: ' . $_->getMessage());
                        }
                        return $handler->handle($request);
                    }
                } catch (\Throwable $_) {
                    // If anything goes wrong, fall back to normal parsing
                }
                $bodyParser = new BodyParserMiddleware();
                return $bodyParser->process($request, $handler);
            })

            // AUTHENTICATION MIDDLEWARE
            ->add(new AuthenticationMiddleware($this))

            // Cross Site Request Forgery (CSRF) Protection Middleware
            // https://book.cakephp.org/4/en/security/csrf.html#cross-site-request-forgery-csrf-middleware
            // Allow skipping CSRF validation for specific server-to-server callback endpoints
            // (e.g. Flask posting approval callbacks). We use skipCheckCallback to exempt
            // requests whose path includes '/users/approvalCallback'. Adjust as needed
            // if your callback path differs or the app is hosted under a subdirectory.
            ->add((function () {
                $csrf = new CsrfProtectionMiddleware([
                    'httponly' => true,
                    'secure' => false,
                    'samesite' => null // Set to null to avoid mobile IP cross-site rejection issues
                ]);
                $csrf->skipCheckCallback(function ($request) {
                    try {
                        $path = (string)$request->getUri()->getPath();
                        $hasSignatureHeader = (bool)$request->getHeaderLine('X-Callback-Signature');
                        // If the request targets the approvalCallback endpoint or
                        // includes the callback signature header, skip CSRF. This
                        // mirrors the authentication behaviour for API callbacks.
                        if (strpos($path, '/users/approvalCallback') !== false || $hasSignatureHeader) {
                            return true;
                        }
                    } catch (\Throwable $_) {
                        // fall through to default behavior (do not skip)
                    }
                    return false;
                });
                return $csrf;
            })());

        return $middlewareQueue;
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     * @return void
     * @link https://book.cakephp.org/4/en/development/dependency-injection.html#dependency-injection
     */
    public function services(ContainerInterface $container): void
    {
    }

    /**
     * Bootstrapping for CLI application.
     *
     * That is when running commands.
     *
     * @return void
     */
    protected function bootstrapCli(): void
    {
        $this->addOptionalPlugin('Bake');

        $this->addPlugin('Migrations');

        // Load more plugins here
    }

    public function getAuthenticationService(ServerRequestInterface $request): AuthenticationServiceInterface
    {
        // Determine whether this request should use the normal unauthenticated
        // redirect (browser flows) or behave like an API/server-to-server call
        // which should *not* be redirected to an HTML login page.
        // If the request targets our approvalCallback endpoint, disable the
        // redirect so the controller can return a JSON response (or 403).
    $path = (string)$request->getUri()->getPath();
    // Treat requests as API-style callbacks if they target the approvalCallback
    // path OR if they include the X-Callback-Signature header (server-to-server).
    $hasSignatureHeader = (bool)$request->getHeaderLine('X-Callback-Signature');
    $isCallbackPath = (strpos($path, '/users/approvalCallback') !== false) || $hasSignatureHeader;

    if ($isCallbackPath) {
            // For API-style callbacks, do not redirect unauthenticated requests.
            $unauthenticatedRedirect = false;
        } else {
            // Default browser flow: redirect unauthenticated users to Users::login
            $unauthenticatedRedirect = Router::url([
                'prefix' => false,
                'controller' => 'Users',
                'action' => 'login',
            ]);
        }

        $authenticationService = new AuthenticationService([
            'unauthenticatedRedirect' => $unauthenticatedRedirect,
            'queryParam' => 'redirect',
        ]);

        // Load identifiers, ensure we check email and password fields
        // Configure the Password identifier to only resolve users that are active (status = 1)
        // by using the UsersTable::findActive finder. This ensures pending or suspended
        // accounts cannot be identified and therefore cannot log in even if the
        // credentials are correct.
        $authenticationService->loadIdentifier('Authentication.Password', [
            'fields' => [
                'username' => 'email',
                'password' => 'password',
            ],
            'resolver' => [
                'className' => 'Authentication.Orm',
                'finder' => 'active'
            ]
        ]);

        // Load the authenticators, you want session first
        $authenticationService->loadAuthenticator('Authentication.Session');
        // Configure form data check to pick email and password
        // Compute the canonical login URL via the Router so it honors any custom
        // route mapping (for example the app root '/' can be routed to Users::login).
        // This ensures the authenticator's `loginUrl` matches the form action the
        // templates generate.
        $formLoginUrl = Router::url([
            'prefix' => false,
            'controller' => 'Users',
            'action' => 'login',
        ]);
        try {
            // Auth config details are helpful when debugging but should be debug-level.
            \Cake\Log\Log::write('debug', 'Auth config: form loginUrl=' . $formLoginUrl . ' requestPath=' . (string)$request->getUri()->getPath());
        } catch (\Throwable $_) { /* ignore logging errors during bootstrap */ }

        $authenticationService->loadAuthenticator('Authentication.Form', [
            'fields' => [
                'username' => 'email',
                'password' => 'password',
            ],
            'loginUrl' => $formLoginUrl,
        ]);

        return $authenticationService;
    }
}
