<?php

namespace PHPMaker2024\FIMS; // Change this to your name space
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

// Bootstrap PagePermission for API routes
require_once dirname(__DIR__, 1) . '/_shared/PagePermission.php';
\PagePermission::boot('PHPMaker2024\\FIMS');

// ---------------------------------------------------------------------------
// Brute-force / rate-limit helpers
// Tracks failed auth attempts per IP using APCu (preferred) or temp-file fallback.
// Config: 10 failures within a 15-minute window → 15-minute lockout.
// ---------------------------------------------------------------------------
const RATELIMIT_MAX_ATTEMPTS = 10;
const RATELIMIT_WINDOW       = 900; // 15 minutes
const RATELIMIT_LOCKOUT      = 900; // 15 minutes

function apiGetClientIp(): string
{
    // REMOTE_ADDR is the reliable source; nginx in this project does not forward
    // a proxy IP header, so we do NOT trust X-Forwarded-For to prevent IP spoofing.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function apiRateLimitKey(string $ip): string
{
    return 'rl_api_' . md5($ip);
}

function apiRateLimitRead(string $key): array
{
    if (function_exists('apcu_fetch')) {
        $data = apcu_fetch($key, $ok);
        return ($ok && is_array($data)) ? $data : [];
    }
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $key . '.json';
    if (is_file($file)) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
    return [];
}

function apiRateLimitWrite(string $key, array $data, int $ttl): void
{
    if (function_exists('apcu_store')) {
        apcu_store($key, $data, $ttl);
        return;
    }
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $key . '.json';
    file_put_contents($file, json_encode($data), LOCK_EX);
}

/**
 * Check if IP is currently rate-limited.
 * Returns ['blocked' => bool, 'retry_after' => int].
 */
function apiCheckRateLimit(string $ip): array
{
    $key  = apiRateLimitKey($ip);
    $now  = time();
    $data = apiRateLimitRead($key);

    if (empty($data)) {
        return ['blocked' => false];
    }

    // Active lockout?
    if (!empty($data['blocked_until']) && $data['blocked_until'] > $now) {
        return ['blocked' => true, 'retry_after' => $data['blocked_until'] - $now];
    }

    // Expired window — clean slate
    if ($now - ($data['window_start'] ?? 0) >= RATELIMIT_WINDOW) {
        apiRateLimitWrite($key, ['attempts' => 0, 'window_start' => $now, 'blocked_until' => 0], RATELIMIT_WINDOW);
    }

    return ['blocked' => false];
}

/** Increment failure counter for an IP; impose lockout when threshold is reached. */
function apiRecordFailedAttempt(string $ip): void
{
    $key  = apiRateLimitKey($ip);
    $now  = time();
    $data = apiRateLimitRead($key);

    if (empty($data) || $now - ($data['window_start'] ?? 0) >= RATELIMIT_WINDOW) {
        $data = ['attempts' => 0, 'window_start' => $now, 'blocked_until' => 0];
    }

    $data['attempts']++;
    if ($data['attempts'] >= RATELIMIT_MAX_ATTEMPTS) {
        $data['blocked_until'] = $now + RATELIMIT_LOCKOUT;
    }

    apiRateLimitWrite($key, $data, RATELIMIT_WINDOW + RATELIMIT_LOCKOUT);
}

/** Clear failure counter for an IP on successful authentication. */
function apiResetRateLimit(string $ip): void
{
    $key = apiRateLimitKey($ip);
    if (function_exists('apcu_delete')) {
        apcu_delete($key);
        return;
    }
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $key . '.json';
    if (is_file($file)) {
        @unlink($file);
    }
}
// ---------------------------------------------------------------------------

$jwtMiddleware = function (Request $request, RequestHandler $handler): Response {
    // --- Brute-force protection ---
    $clientIp  = apiGetClientIp();
    $rateLimit = apiCheckRateLimit($clientIp);
    if ($rateLimit['blocked']) {
        $response = new \Nyholm\Psr7\Response();
        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withHeader('Retry-After', (string) ($rateLimit['retry_after'] ?? RATELIMIT_LOCKOUT));
        $response->getBody()->write(json_encode(['error' => 'Too Many Requests', 'message' => 'Too many failed attempts. Please try again later.']));
        return $response->withStatus(429);
    }

    // Ensure session data is available for session-based API calls.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    // SESSION_USER_ID/SESSION_USER_LEVEL_ID/SESSION_USER_NAME (src/constants.php)
    // resolve to "FIMS_Status_UserID" etc. in this project (PROJECT_NAME =
    // "FIMS") — the literal "UAC_Status_*"/"ACCOUNTING_Status_*" keys below are
    // kept only as a fallback for a shared SSO session with those sibling apps,
    // if one is ever set up; they don't exist in this project's own session.
    $sessionUserId = (int) ($_SESSION[SESSION_USER_ID] ?? $_SESSION['UAC_Status_UserID'] ?? $_SESSION['ACCOUNTING_Status_UserID'] ?? 0);
    // NOTE: user_level_id can be a comma-separated multi-role string (e.g.
    // "4001,9006"). Do NOT (int) cast this — PHP's int cast truncates at the
    // first comma, silently dropping every role after the first, and that
    // truncated value gets written back into this system's own session via
    // Security->loginUser() below, breaking access for the rest of the session.
    $sessionUserLevel = $_SESSION[SESSION_USER_LEVEL_ID] ?? $_SESSION['UAC_Status_UserLevel'] ?? $_SESSION['ACCOUNTING_Status_UserLevel'] ?? (string) AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID;
    $sessionUsername = (string) ($_SESSION[SESSION_USER_NAME] ?? $_SESSION['UAC_Status_UserName'] ?? $_SESSION['ACCOUNTING_Status_UserName'] ?? '');

    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader) {
        $authHeader = $request->getHeaderLine('X-Authorization');
    }

    $payload = null;
    $jwt = '';

    // If request has no bearer token but a logged-in session exists, trust session.
    // NOTE: the superadmin's session user id is -1 (ADMIN_USER_LEVEL_ID), so test
    // "logged in" as != 0, not > 0, or the superadmin is wrongly rejected.
    if (!$authHeader && $sessionUserId !== 0) {
        $payload = [
            'username' => $sessionUsername,
            'userid' => $sessionUserId,
            'parentuserid' => null,
            'userlevel' => $sessionUserLevel,
            'userprimarykey' => null,
            'permission' => 0
        ];
    } else {
        if (!$authHeader) {
            $authHeader = GetJwtToken();
        }
        $jwt = str_replace('Bearer ', '', $authHeader);

        // Decode the JWT
        $payload = DecodeJwt($jwt);
    }

    // If the JWT/session payload is invalid, return a 401 Unauthorized response
    if (!is_array($payload) || isset($payload['failureMessage']) || (empty($jwt) && $sessionUserId === 0)) {
        apiRecordFailedAttempt($clientIp);
        $response = new \Nyholm\Psr7\Response();
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401);
    }

    // Reject unauthenticated (anonymous) users.
    // GetJwtToken() always returns a valid JWT, including anonymous users.
    // A logged-in app/UAC session is allowed even when bearer token is missing.
    $jwtUserLevel = (int) ($payload['userlevel'] ?? AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID);
    $jwtUserId = (int) ($payload['userid'] ?? 0);
    if ($jwtUserLevel == AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID && $sessionUserId === 0 && $jwtUserId <= 0) {
        apiRecordFailedAttempt($clientIp);
        $response = new \Nyholm\Psr7\Response();
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(['error' => 'Unauthorized', 'message' => 'Login required']));
        return $response->withStatus(401);
    }

    // Login the user via Security so that CurrentUserID() works in services.
    // The custom $jwtMiddleware doesn't call Security->loginUser() unlike the
    // PHPMaker JwtMiddleware class, so user_id from JWT is always -1 (guest).
    global $Security;
    $Security = Container("app.security");
    $Security->loginUser(
        $payload['username'] ?? '',
        $payload['userid'] ?? null,
        $payload['parentuserid'] ?? null,
        $payload['userlevel'] ?? AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID,
        $payload['userprimarykey'] ?? null,
        $payload['permission'] ?? 0
    );

    // Successful authentication — reset brute-force counter for this IP.
    apiResetRateLimit($clientIp);

    // userid in the JWT may be null/-1 if auth was done through UAC (a separate app).
    // Fall back to common session keys that UAC sets so write operations get the real ID.
    $user_id = (int) ($payload['userid'] ?? 0);
    if ($user_id <= 0) {
        $user_id = (int) ($_SESSION['UAC_Status_UserID']
            ?? $_SESSION['ACCOUNTING_Status_UserID']
            ?? 0);
    }
    $request = $request->withAttribute('user_id', $user_id);

    $user_level = (int) ($payload['userlevel'] ?? AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID);
    if ($user_level === AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID) {
        $user_level = (int) ($_SESSION['UAC_Status_UserLevel'] ?? $_SESSION['ACCOUNTING_Status_UserLevel'] ?? AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID);
    }
    $request = $request->withAttribute('user_level', $user_level);

    // If the JWT/session is valid, pass the request to the next middleware
    $response = $handler->handle($request);
    return $response;
};

// Access middleware for permission checking
$accessMiddleware = function (Request $request, RequestHandler $handler): Response {
    // Add your access control logic here
    // Example: Check user permissions based on endpoint and method

    $response = $handler->handle($request);
    return $response;
};

// Service classes and route files are auto-discovered — drop a new .php file
// into app/services/ or app/routes/ (not starting with "_") and it's picked
// up automatically, no manual include() needed here.
$appBase = dirname(__DIR__, 1) . '/app/';

// Shared helpers (app/lib is not auto-discovered): GlobalService::generateProgramCode(), …
require_once $appBase . 'lib/GlobalService.php';

foreach (glob($appBase . 'services/[!_]*.php') ?: [] as $f) {
    require_once $f;
}

foreach (glob($appBase . 'routes/[!_]*.php') ?: [] as $f) {
    require_once $f;
}
