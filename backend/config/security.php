<?php
declare(strict_types=1);
// config/security.php
// Ensure core config is loaded so SESSION_NAME and other constants are available
if (!defined('SESSION_NAME')) {
    $cfgPath = __DIR__ . '/config.php';
    if (file_exists($cfgPath)) {
        require_once $cfgPath;
    }
}

// Load database helper
require_once __DIR__ . '/database.php';



function secure_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Use a local directory for sessions within the project to avoid permission issues
    $sessionPath = __DIR__ . '/../sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }
    session_save_path($sessionPath);

    // Ensure session has the configured name before starting
    if (defined('SESSION_NAME')) {
        session_name(SESSION_NAME);
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Determine cookie domain for hosted environments
    $cookieDomain = '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    // For hosted environments with subdomains, set domain to parent domain
    // Skip if it's an IP address or localhost
    if (!empty($host) && $host !== 'localhost' && !filter_var($host, FILTER_VALIDATE_IP) && strpos($host, '.') !== false) {
        // Extract parent domain (e.g., yourdomain.com from app.yourdomain.com)
        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            // Check for common free hosting TLDs that shouldn't be prefixed with a dot across all subdomains
            $tld = implode('.', array_slice($parts, -2));
            $commonFreeTLDs = ['rf.gd', 'infinityfreeapp.com', 'epizy.com', 'great-site.net', '42web.io'];
            
            if (in_array($tld, $commonFreeTLDs)) {
                $cookieDomain = $host; // Use exact host for free subdomains
            } else {
                $cookieDomain = '.' . $tld;
            }
        }
    }

    session_set_cookie_params([
        'lifetime' => 3600, // 1 hour (matching config.php)
        'path'     => '/',
        'domain'   => $cookieDomain,
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    // Regenerate ID every 10 minutes
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 600) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }

    // Idle timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_unset();
        session_destroy();
        return;
    }
    $_SESSION['last_activity'] = time();

    // Update last_seen in DB (throttle to once every 5 minutes)
    if (isset($_SESSION['user_id'])) {
        if (!isset($_SESSION['last_seen_update']) || (time() - $_SESSION['last_seen_update'] > 300)) {
            update_last_seen((int)$_SESSION['user_id']);
            $_SESSION['last_seen_update'] = time();
        }
    }
}

/**
 * Update the last_seen timestamp in the database for a user
 */
function update_last_seen(int $userId): void
{
    try {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Error updating last_seen: " . $e->getMessage());
    }
}

function allow_cors(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Define allowed origins - UPDATE WITH YOUR HOSTED DOMAIN
    $allowed_origins = [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:8080',
        'http://127.0.0.1',
        // Add your hosted domain(s) here:
        // 'https://yourdomain.com',
        // 'https://www.yourdomain.com',
        // 'https://app.yourdomain.com',
    ];
    
    // Check if origin is allowed or if it's the same as current host
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    $isSameHost = !empty($origin) && strpos($origin, $currentHost) !== false;

    if ($isSameHost || in_array($origin, $allowed_origins, true)) {
        header("Access-Control-Allow-Origin: $origin");
    } elseif (!empty($origin)) {
        // Log unexpected origins for debugging
        error_log("CORS: Rejected origin: $origin");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization");
    header("Access-Control-Allow-Credentials: true");
}

function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }

    if (time() - ($_SESSION['csrf_token_time'] ?? 0) > 1800) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }

    return $_SESSION['csrf_token'];
}

function require_csrf_token(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') return;

    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!$header || !isset($_SESSION['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['message' => 'CSRF token missing']);
        exit();
    }

    if (!hash_equals($_SESSION['csrf_token'], $header)) {
        error_log("CSRF failed: {$header} vs {$_SESSION['csrf_token']}");
        http_response_code(403);
        echo json_encode(['message' => 'Invalid CSRF token']);
        exit();
    }
}

function set_csrf_meta_tag(): void
{
    $token = get_csrf_token();
    echo "<meta name=\"csrf-token\" content=\"{$token}\">";
}

function init_csrf(): void
{
    get_csrf_token();
}

// === SECURE sanitize_string() ===
function sanitize_string($input): ?string
{
    if ($input === null || $input === '') {
        return null;
    }

    $str = trim($input);
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');

    // Optional: strip tags if not needed
    $str = strip_tags($str);

    return $str === '' ? null : $str;
}
// === SECURITY LOGGING ===
function security_log(string $level, string $message): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/security.log';
    $time = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $sessionId = session_id() ? substr(session_id(), 0, 8) . '...' : 'none';
    $userId = $_SESSION['user_id'] ?? 'guest';

    $line = "[$time] [$level] [IP:$ip] [User:$userId] [Sess:$sessionId] $message -- UA: $ua" . PHP_EOL;

    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

// config/security.php – ADD THIS AT THE END

// === ROLE-BASED PERMISSIONS ===
function get_role_permissions(): array
{
    return [
        'admin' => [
            'dashboard'     => true,
            'users'         => true,
            'fellowships'   => true,
            'attendance'    => true,
            'financial'     => true,
            'reports'       => true,
            'settings'      => true,
            'profile'       => true,
            'logout'        => true,
            'view_members' => true,
            'members'       => true,
            'cells'         => true,
            'departments'   => true,
        ],
        'pastor' => [
            'dashboard'     => true,
            'users'         => false,
            'fellowships'   => true,
            'attendance'    => true,
            'financial'     => true,
            'reports'       => true,
            'settings'      => false,
            'profile'       => true,
            'logout'        => true,
            'view_members' => true,
            'members'       => true,
            'cells'         => true,
            'departments'   => true,
        ],
        'pcf' => [
            'dashboard'     => true,
            'users'         => false,
            'fellowships'   => true,
            'attendance'    => true,
            'financial'     => false,
            'reports'       => false,
            'settings'      => false,
            'profile'       => true,
            'logout'        => true,
            'view_members' => true,
            'members'       => true,
            'cells'         => true,
            'departments'   => false,
        ],
        'leader' => [
            'dashboard'     => true,
            'users'         => false,
            'fellowships'   => false,
            'attendance'    => true,
            'financial'     => false,
            'reports'       => false,
            'settings'      => false,
            'profile'       => true,
            'logout'        => true,
            'view_members' => true,
            'members'       => false,
            'cells'         => false,
            'departments'   => false,
        ],
        'member' => [
            'dashboard'     => false,
            'users'         => false,
            'fellowships'   => false,
            'attendance'    => false,
            'financial'     => false,
            'reports'       => false,
            'settings'      => false,
            'profile'       => true,
            'logout'        => true,
            'view_members' => false,
            'members'       => false,
            'cells'         => false,
            'departments'   => false,
        ],
    ];
}

function can_access(string $page): bool
{
    $role = $_SESSION['role'] ?? 'guest';
    $permissions = get_role_permissions();

    return $permissions[$role][$page] ?? false;
}

// config/security.php – ADD AT THE END

function get_db_permissions(): array
{
    $db = (new Database())->getConnection();
    $stmt = $db->query("SELECT role, module, allowed FROM permissions");
    $perms = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $perms[$row['role']][$row['module']] = (bool)$row['allowed'];
    }
    return $perms;
}

function can_access_db(string $module): bool
{
    $role = $_SESSION['role'] ?? 'guest';
    $perms = get_db_permissions();
    return $perms[$role][$module] ?? false;
}

/**
 * Fellowship data isolation helper
 *
 * Returns the fellowship_id that should be used to scope queries for the
 * current user, or null if the user is allowed to see all fellowships.
 *
 * Convention:
 * - admin  : can see all fellowships (no isolation)  -> returns null
 * - others : restricted to their own fellowship_id   -> returns int or null
 */
function current_fellowship_scope(): ?int
{
    // If no fellowship assigned, nothing to restrict by
    if (empty($_SESSION['fellowship_id'])) {
        return null;
    }

    $role = $_SESSION['role'] ?? 'guest';

    // Admins can see everything
    if ($role === 'admin') {
        return null;
    }

    return (int) $_SESSION['fellowship_id'];
}