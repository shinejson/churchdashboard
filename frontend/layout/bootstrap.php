<?php
/**
 * Page Bootstrap
 *
 * Single entry point every sidebar page includes at the top of the file.
 * It replaces the per-page copy/paste of:
 *   - security.php require + secure_session_start()
 *   - the "is anybody logged in?" redirect
 *   - CSRF initialisation and the <meta name="csrf-token"> tag
 *   - SCRIPT_NAME-based path sniffing for asset URLs
 *   - the pre-paint theme script
 *
 * Usage (from frontend/pages/*.php or the root index.php):
 *
 *   require_once __DIR__ . '/frontend/layout/bootstrap.php';   // root
 *   require_once __DIR__ . '/../layout/bootstrap.php';          // frontend/pages
 *
 *   $page = layout_page_init([
 *       'id'         => 'members',
 *       'title'      => 'Members Management',
 *       'permission' => 'admin_only',
 *       'roles'      => ['admin'],        // optional hard role gate
 *   ]);
 *
 * @package ChurchManagement/Layout
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Paths
// ---------------------------------------------------------------------------

if (!defined('LAYOUT_DIR')) {
    define('LAYOUT_DIR', __DIR__);
}
if (!defined('FRONTEND_DIR')) {
    define('FRONTEND_DIR', dirname(LAYOUT_DIR));
}
if (!defined('APP_ROOT_DIR')) {
    define('APP_ROOT_DIR', dirname(FRONTEND_DIR));
}

// Security helpers (session, CSRF, permissions). security.php pulls in
// config.php + database.php, so SITE_URL and Database are available too.
require_once APP_ROOT_DIR . '/backend/config/security.php';

// README advertises PHP 7.4+; these landed in 8.0. Keep the layout portable.
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

// ---------------------------------------------------------------------------
// URL context
// ---------------------------------------------------------------------------

/**
 * Resolve the URL prefix the application is served under.
 *
 * The app is deployed both at a domain root and inside a subdirectory
 * (e.g. /church). Everything is derived from SCRIPT_NAME so relative links keep
 * working in both cases, and it does not depend on DOCUMENT_ROOT being set
 * correctly (paths.php cannot be trusted under some rewrite setups).
 */
function layout_url_context(): array
{
    $scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strpos($scriptPath, '/frontend');

    if ($pos !== false) {
        $appBase = substr($scriptPath, 0, $pos);          // '' or '/church'
    } else {
        // Root index.php, or anything served outside /frontend.
        $appBase = rtrim(str_replace('\\', '/', dirname($scriptPath)), '/');
        if ($appBase === '/' || $appBase === '.') {
            $appBase = '';
        }
    }

    return [
        'appBase'     => $appBase,
        'frontendUrl' => $appBase . '/frontend',
        'pagesUrl'    => $appBase . '/frontend/pages',
        'assetsUrl'   => $appBase . '/frontend/assets',
        'cssUrl'      => $appBase . '/frontend/css',
        'jsUrl'       => $appBase . '/frontend/js',
        'backendUrl'  => $appBase . '/backend',
        'rootUrl'     => $appBase . '/index.php',
        'loginUrl'    => $appBase . '/frontend/pages/login.php',
        'profileUrl'  => $appBase . '/frontend/pages/profile.php',
    ];
}

/**
 * Build a URL for a repo-relative asset path ('css/style.css', 'js/main.js').
 * Paths are resolved against /frontend so pages never hard-code '../' again.
 */
function layout_url(string $path, ?array $context = null): string
{
    static $shared = null;
    $context = $context ?? ($shared ??= layout_url_context());
    $path = ltrim($path, '/');

    if (preg_match('#^(https?:)?//#', $path)) {
        return $path; // already absolute / CDN
    }
    if (str_starts_with($path, 'frontend/')) {
        return $context['appBase'] . '/' . $path;
    }
    if (str_starts_with($path, 'backend/')) {
        return $context['appBase'] . '/' . $path;
    }

    return $context['frontendUrl'] . '/' . $path;
}

/** Asset URL with a cache-busting version derived from the file's mtime. */
function layout_asset(string $path, ?array $context = null): string
{
    $url = layout_url($path, $context);
    $relative = preg_replace('#^frontend/#', '', $path);
    $file = str_starts_with($path, 'backend/')
        ? APP_ROOT_DIR . '/' . $path
        : FRONTEND_DIR . '/' . $relative;

    if (is_file($file)) {
        return $url . '?v=' . (int)filemtime($file);
    }

    return $url;
}

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

/**
 * Guard a page: session, then optional permission module / explicit role list.
 *
 * `$permission` values match the ones already used by sidebar.php so the menu
 * and the page agree:
 *   'admin_only'  - any administrator
 *   'none'        - any authenticated user
 *   '<module>'    - admins, or roles granted that module in the permissions table
 *
 * Redirects (never renders) so a page cannot leak markup before the check,
 * which is what financial.php used to do.
 */
function layout_require_access(string $permission = 'none', ?array $roles = null): void
{
    secure_session_start();

    if (empty($_SESSION['user_id'])) {
        layout_redirect(layout_url_context()['loginUrl']);
    }

    $role = (string)($_SESSION['role'] ?? 'guest');

    // Members have no dashboard access at all - they live on their profile.
    if ($role === 'member' && $permission !== 'profile' && $roles === null) {
        layout_redirect(layout_url_context()['profileUrl']);
    }

    if ($roles !== null && !in_array($role, $roles, true)) {
        layout_redirect(layout_url_context()['rootUrl']);
    }

    // 'none' with an explicit role list means "gate on these roles, but do not
    // look up a named permission" - the check above already did the gating.
    if ($permission === 'none' || $permission === 'profile' || $role === 'admin') {
        return;
    }

    if ($permission === 'admin_only') {
        layout_redirect(layout_url_context()['rootUrl']);
    }

    $allowed = false;
    try {
        $allowed = can_access_db($permission) || can_access($permission);
    } catch (Throwable $e) {
        // Database unavailable: fall back to the static role map so a DB
        // hiccup does not lock administrators out of the whole app.
        error_log('layout_require_access permission lookup failed: ' . $e->getMessage());
        $allowed = can_access($permission);
    }

    if (!$allowed) {
        layout_redirect(layout_url_context()['rootUrl']);
    }
}

/** Redirect helper that always terminates. */
function layout_redirect(string $url): never
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    exit();
}

/**
 * Fail loudly on a programming error in the layout itself.
 *
 * Used for the "you forgot to call layout_page_init()" class of mistake. This
 * deliberately does not throw an exception object: it logs, emits an HTML
 * comment (so a broken page is diagnosable from view-source) and stops. Keeping
 * the layout free of exception objects also means it stays inside the PHP subset
 * the refactor harness can execute — see scripts/refactor/README.
 */
function layout_fail(string $message): void
{
    error_log('[layout] ' . $message);
    echo "\n<!-- layout error: " . htmlspecialchars($message, ENT_QUOTES) . " -->\n";
    exit();
}

// ---------------------------------------------------------------------------
// Page initialisation
// ---------------------------------------------------------------------------

/**
 * Initialise a page and return its render context.
 *
 * @param array{id:string,title:string,permission?:string,roles?:array,
 *              description?:string,css?:array,scripts?:array,overlays?:string,
 *              bodyClass?:string,htmlAttrs?:array,fontAwesome?:bool,head?:string,
 *              footer?:string,contentId?:?string,contentClass?:?string,
 *              titleSuffix?:?string,showFooter?:bool,lang?:string} $config
 */
function layout_page_init(array $config): array
{
    foreach (['id', 'title'] as $required) {
        if (empty($config[$required])) {
            layout_fail("layout_page_init(): the '$required' key is required");
        }
    }

    $context = layout_url_context();
    $permission = (string)($config['permission'] ?? 'none');
    $roles = $config['roles'] ?? null;

    // members.php and financial.php shipped with no access check at all - any
    // visitor, logged in or not, could load them by URL. 'requireLogin' closes
    // that hole without applying the usual role/permission rules, so a page that
    // is merely *hidden* from the sidebar for some roles still behaves the same
    // for the roles that can reach it.
    if (!empty($config['requireLogin'])) {
        secure_session_start();
        if (empty($_SESSION['user_id'])) {
            layout_redirect(layout_url_context()['loginUrl']);
        }
    } else {
        layout_require_access($permission, $roles);
    }

    // CSRF: every authenticated page gets a token + meta tag. Pages that used
    // to forget it (financial.php) now behave like the rest.
    if (function_exists('init_csrf')) {
        init_csrf();
    }

    $page = [
        'id'          => $config['id'],
        'title'       => $config['title'],
        'description' => $config['description'] ?? '',
        'permission'  => $permission,
        // True when the page only needs an authenticated visitor and must not
        // apply the 'member' role rule (see layout_page_init's requireLogin).
        'requireLogin' => (bool)($config['requireLogin'] ?? false),
        'roles'       => $roles,
        'role'        => (string)($_SESSION['role'] ?? 'guest'),
        'userId'      => (int)($_SESSION['user_id'] ?? 0),
        'username'    => (string)($_SESSION['username'] ?? 'User'),
        'isAdmin'     => (string)($_SESSION['role'] ?? 'guest') === 'admin',
        'css'         => array_values($config['css'] ?? []),
        'js'          => array_values($config['js'] ?? []),
        'cdnCss'      => array_values($config['cdnCss'] ?? []),
        'cdnJs'       => array_values($config['cdnJs'] ?? []),
        // Ordered body scripts: ['js/users.php', 'https://cdn.../chart.js', ...]
        'scripts'     => array_values($config['scripts'] ?? []),
        // Scripts that must run BEFORE the footer include. attendance.php loads
        // js/attendance-report.js there, ahead of attendance.js and main.js, and
        // report.php loads jQuery, Bootstrap, moment, daterangepicker and
        // DataTables there ahead of its own inline script.
        'preFooterScripts' => array_values($config['preFooterScripts'] ?? []),
        // CDN libraries that must exist in <head> BEFORE js/env.js and
        // js/config.js. report.php loads Chart.js there.
        'headCdnJs' => array_values($config['headCdnJs'] ?? []),
        // Raw markup (usually an inline <script>) emitted after preFooterScripts
        // and before the overlays. report.php keeps ~1000 lines of inline JS in
        // this slot because it interpolates the CSRF token and role from PHP.
        'bodyInline' => (string)($config['bodyInline'] ?? ''),
        // Markup rendered after #app closes (modals, overlays, drawers).
        'overlays'    => (string)($config['overlays'] ?? ''),
        'bodyClass'   => (string)($config['bodyClass'] ?? ''),
        'htmlAttrs'   => (array)($config['htmlAttrs'] ?? []),
        'fontAwesome' => (bool)($config['fontAwesome'] ?? false),
        'head'        => (string)($config['head'] ?? ''),
        'footer'      => (string)($config['footer'] ?? ''),
        'context'     => $context,
        'csrfToken'   => (string)($_SESSION['csrf_token'] ?? ''),
        // Content wrapper: id/classes are page-specific today, so they are
        // passed through verbatim (null id = the page never had one).
        'contentId'     => $config['contentId'] ?? null,
        'contentClass'  => $config['contentClass'] ?? null,
        'contentActive' => (bool)($config['contentActive'] ?? true),
        // permissions.php puts .page-header and its editor directly inside
        // <main class="content"> with no .page-content wrapper at all.
        'contentWrapper' => (bool)($config['contentWrapper'] ?? true),
        // Which element carries contentId/contentClass. 'div' (default) renders a
        // .page-content wrapper inside <main>; 'main' puts them on <main> itself
        // and renders no wrapper, which is what report.php does
        // (<main class="content" id="main-content">).
        'contentTag' => (string)($config['contentTag'] ?? 'div'),
        // 'before-app' (default, every sidebar page) or 'in-content', which is
        // what link_members.php and create_member_users.php ship today: their
        // topnav is nested inside <main class="content">. Kept as an option so
        // migrating them does not move the bar and change how they look.
        'topnavPlacement' => (string)($config['topnavPlacement'] ?? 'before-app'),
        'lang'          => (string)($config['lang'] ?? 'en'),
        // birthdays.php loads Inter 300-700; everything else 400-800.
        'fontWeights'   => (string)($config['fontWeights'] ?? '400;500;600;700;800'),
        'titleSuffix'   => array_key_exists('titleSuffix', $config)
            ? (string)$config['titleSuffix']
            : 'Church Management System',
        'showFooter'    => (bool)($config['showFooter'] ?? true),
    ];

    // One page per request: the context is published for the layout includes
    // (head/body-open/body-close) which are rendered after this call.
    $GLOBALS['layout_current_page'] = $page;

    return $page;
}

/** The current page context (set by layout_page_init). */
function layout_current_page(): ?array
{
    $page = $GLOBALS['layout_current_page'] ?? null;
    return is_array($page) ? $page : null;
}

// Markup renderers (page header, stat cards, toolbars, tables, modals, ...).
// Loaded here so a page only has to require bootstrap.php.
require_once LAYOUT_DIR . '/components.php';

/** Convenience: is the signed-in user allowed to see a module? */
function layout_can(string $permission): bool
{
    $page = layout_current_page();
    if (($page['role'] ?? '') === 'admin') {
        return true;
    }
    if ($permission === 'admin_only') {
        return false;
    }
    try {
        return (bool)(can_access_db($permission) || can_access($permission));
    } catch (Throwable $e) {
        return can_access($permission);
    }
}
