/**
 * Render harness: executes a refactored page with the PHP-subset interpreter.
 *
 * DB-backed includes are replaced with stand-ins (MOCKS below) that define the
 * same helper functions and emit the same structural markup, so the real
 * frontend/layout/*.php renderers run and produce HTML we can diff against the
 * hooks the page JavaScript binds to.
 *
 * Page files are read with their own top-level PHP block replaced by a
 * bootstrap call, so page-specific DB queries never execute here.
 */

const fs = require('fs');
const path = require('path');
const { Interpreter } = require('./interpreter.cjs');
const { PhpArray, PhpExit } = require('./runtime.cjs');

const REPO_ROOT = path.resolve(__dirname, '..', '..', '..');

// ---------------------------------------------------------------------------
// Mocks for includes the subset cannot execute
// ---------------------------------------------------------------------------

const SECURITY_MOCK = `<?php
// Test stand-in for backend/config/security.php.
// The real file opens a PDO connection; the preview only needs the function
// signatures and constants the layout calls.

function secure_session_start() { return true; }
function init_csrf() { return get_csrf_token(); }
function get_csrf_token() { return 'preview-csrf-token'; }
function set_csrf_meta_tag() { echo '<meta name="csrf-token" content="preview-csrf-token">'; }
function require_csrf_token() { return true; }
function sanitize_string($input) { return $input === null ? null : trim((string)$input); }
function security_log($level, $message) { return true; }
function update_last_seen($userId) { return true; }
function allow_cors() { return true; }

function get_role_permissions() {
    return [
        'admin'  => ['dashboard' => true,  'users' => true,  'fellowships' => true,  'attendance' => true,  'financial' => true,  'reports' => true,  'settings' => true,  'profile' => true,  'members' => true,  'cells' => true,  'departments' => true],
        'pastor' => ['dashboard' => true,  'users' => false, 'fellowships' => true,  'attendance' => true,  'financial' => true,  'reports' => true,  'settings' => false, 'profile' => true,  'members' => true,  'cells' => true,  'departments' => true],
        'pcf'    => ['dashboard' => true,  'users' => false, 'fellowships' => true,  'attendance' => true,  'financial' => false, 'reports' => false, 'settings' => false, 'profile' => true,  'members' => true,  'cells' => true,  'departments' => false],
        'leader' => ['dashboard' => true,  'users' => false, 'fellowships' => false, 'attendance' => true,  'financial' => false, 'reports' => false, 'settings' => false, 'profile' => true,  'members' => false, 'cells' => false, 'departments' => false],
        'member' => ['dashboard' => false, 'users' => false, 'fellowships' => false, 'attendance' => false, 'financial' => false, 'reports' => false, 'settings' => false, 'profile' => true,  'members' => false, 'cells' => false, 'departments' => false]
    ];
}

function can_access($page) {
    $role = $_SESSION['role'] ?? 'guest';
    $permissions = get_role_permissions();
    return $permissions[$role][$page] ?? false;
}

function get_db_permissions() {
    // The preview has no database: mirror the static role map.
    return get_role_permissions();
}

function can_access_db($module) { return can_access($module); }
function current_fellowship_scope() { return null; }
`;

const PATHS_MOCK = `<?php
// Test stand-in for frontend/config/paths.php.
define('BASE_PATH', '');
define('BACKEND_PATH', '/backend');
define('FRONTEND_PATH', '/frontend');
define('BASE_URL', 'http://preview.local');
define('BACKEND_URL', 'http://preview.local/backend');
define('FRONTEND_URL', 'http://preview.local/frontend');
`;

const TOPNAV_MOCK = `<header class="topnav" id="topnav" role="banner">
    <div class="topnav-left">
        <button id="hamburger" class="icon-btn" type="button" aria-label="Toggle sidebar menu" aria-controls="app-sidebar" aria-expanded="false">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
    </div>
    <div class="topnav-center">
        <form id="topnav-search" class="topnav-search" role="search" aria-label="Site search" autocomplete="off">
            <label for="search-input" class="sr-only">Search members and fellowships</label>
            <input type="search" id="search-input" name="q" placeholder="Search members, fellowships..." aria-label="Search" autocomplete="off">
            <button type="submit" id="search-btn" class="icon-btn" aria-label="Search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
            <div id="search-results-list" class="search-results-list" role="listbox" aria-label="Search results" hidden></div>
            <div id="search-suggestions" class="search-suggestions" hidden></div>
        </form>
        <button id="mobile-search-toggle" class="icon-btn mobile-only" type="button" aria-label="Open search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/></svg>
        </button>
        <button id="mobile-search-close" class="icon-btn mobile-only" type="button" aria-label="Close search">&times;</button>
    </div>
    <div class="topnav-right">
        <button id="notif-btn" class="icon-btn" type="button" aria-label="Notifications" aria-expanded="false">
            <span id="notif-count" class="notif-count" hidden>0</span>
        </button>
        <div id="notifications-menu" class="notification-panel" role="region" aria-label="Notifications" hidden>
            <div class="notification-panel-header">
                <h3>Notifications</h3>
                <button id="mark-all-read" class="close-panel" type="button" aria-label="Mark all read">Mark all read</button>
            </div>
            <div class="notification-panel-body"><ul id="notifications-list" class="notifications-list"></ul></div>
        </div>
        <div class="profile-area">
            <button id="profile-btn" class="profile-btn" type="button" aria-label="User menu" aria-expanded="false">
                <img src="/frontend/assets/avatar-placeholder.png" alt="" class="profile-avatar" loading="lazy">
                <span class="profile-info"><span class="profile-name">Preview User</span><span class="profile-role">Admin</span></span>
            </button>
            <div id="profile-menu" class="profile-menu" role="menu" hidden>
                <a id="admin-permissions-link" href="/frontend/pages/permissions.php" role="menuitem">Permissions</a>
                <a id="logout-link" href="/backend/auth/logout.php" role="menuitem">Logout</a>
            </div>
        </div>
    </div>
</header>`;

const SIDEBAR_MOCK = `<nav class="sidebar" id="app-sidebar" role="navigation" aria-label="Main navigation">
    <button id="sidebar-close" class="icon-btn mobile-only" type="button" aria-label="Close sidebar">&times;</button>
    <div class="sidebar-scroll">
        <div class="sidebar-top">
            <img src="/frontend/assets/images/logo.png" alt="Preview Church Logo" class="sidebar-logo" loading="lazy">
            <h3 class="sidebar-title">Preview Church</h3>
        </div>
        <ul class="sidebar-nav" role="menu">
            <li role="none"><a href="/index.php" data-page="dashboard" class="nav-link" role="menuitem"><span>Dashboard</span></a></li>
            <li role="none"><a href="/frontend/pages/users.php" data-page="users" class="nav-link" role="menuitem"><span>Users</span></a></li>
            <li role="none"><a href="/frontend/pages/fellowships.php" data-page="fellowships" class="nav-link" role="menuitem"><span>Fellowships</span></a></li>
            <li role="none"><a href="/frontend/pages/departments.php" data-page="departments" class="nav-link" role="menuitem"><span>Departments</span></a></li>
            <li role="none"><a href="/frontend/pages/cells.php" data-page="cells" class="nav-link" role="menuitem"><span>Home Cells</span></a></li>
            <li role="none"><a href="/frontend/pages/attendance.php" data-page="attendance" class="nav-link" role="menuitem"><span>Attendance</span></a></li>
            <li role="none"><a href="/frontend/pages/financial.php" data-page="financial" class="nav-link" role="menuitem"><span>Financial</span></a></li>
            <li role="none"><a href="/frontend/pages/report.php" data-page="reports" class="nav-link" role="menuitem"><span>Reports</span></a></li>
            <li role="none"><a href="/frontend/pages/members.php" data-page="members" class="nav-link" role="menuitem"><span>Members</span></a></li>
            <li role="none"><a href="/frontend/pages/birthdays.php" data-page="birthdays" class="nav-link" role="menuitem"><span>Birthdays</span></a></li>
            <li role="none"><a href="/frontend/pages/settings.php" data-page="settings" class="nav-link" role="menuitem"><span>Settings</span></a></li>
            <li role="none"><a href="/frontend/pages/profile.php" data-page="profile" class="nav-link" role="menuitem"><span>My Profile</span></a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <div class="sidebar-footer-content">
            <div class="footer-user-profile">
                <div class="avatar-wrapper">
                    <img src="/frontend/assets/avatar-placeholder.png" alt="Preview User avatar" class="sidebar-avatar" loading="lazy">
                    <span class="status-dot online" aria-label="Online"></span>
                </div>
                <div class="footer-user-info">
                    <div class="footer-user-name">Preview User</div>
                    <div class="footer-user-role">Admin <span class="activity" id="sidebar-activity-time">Active just now</span></div>
                </div>
            </div>
            <div class="footer-actions">
                <button id="theme-toggle-sidebar" class="icon-btn" type="button" aria-label="Toggle dark mode">
                    <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="5" stroke="currentColor" stroke-width="2"/></svg>
                    <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" stroke="currentColor" stroke-width="2"/></svg>
                </button>
                <button id="logout-sidebar" class="icon-btn" type="button" aria-label="Logout">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke="currentColor" stroke-width="2"/></svg>
                </button>
            </div>
            <button id="sidebar-expand-toggle" class="icon-btn footer-toggle-btn" type="button" aria-label="Show sidebar actions" aria-expanded="false">&rsaquo;</button>
        </div>
    </div>
</nav>
<meta name="csrf-token" content="preview-csrf-token">`;

const FOOTER_MOCK = `<footer class="app-footer">
    <div class="footer-left">Preview Church</div>
    <div class="footer-center"><span id="footer-datetime">--</span></div>
    <div class="footer-right">&copy; <span id="footer-year"></span></div>
</footer>`;

const CONFIG_INCLUDE_MOCK = `<?php
// Test stand-in for frontend/includes/config.php (outputApiConfig only).
function outputApiConfig() {
    echo '<script>window.BASE_URL = "http://preview.local"; window.BACKEND_URL = "http://preview.local/backend"; window.FRONTEND_URL = "http://preview.local/frontend"; window.API_BASE = window.BACKEND_URL;</script>';
}
function getScriptUrl($path, $cacheBust = false) { return '/frontend/' . $path; }
`;

const DATABASE_MOCK = `<?php
// Test stand-in for backend/config/database.php (no PDO in the preview).
`;

const CONFIG_BACKEND_MOCK = `<?php
// Test stand-in for backend/config/config.php.
define('SITE_URL', 'http://preview.local');
define('SITE_NAME', 'Church Management System');
define('SESSION_NAME', 'PREVIEWSESSID');
define('APP_ENV', 'preview');
define('DB_HOST', 'localhost');
define('DB_NAME', 'preview');
define('DB_USER', 'preview');
define('DB_PASS', '');
`;

function buildMockIncludes() {
  const abs = (rel) => path.resolve(REPO_ROOT, rel);
  return new Map([
    [abs('backend/config/security.php'), SECURITY_MOCK],
    [abs('backend/config/database.php'), DATABASE_MOCK],
    [abs('backend/config/config.php'), CONFIG_BACKEND_MOCK],
    [abs('frontend/config/paths.php'), PATHS_MOCK],
    [abs('frontend/includes/config.php'), CONFIG_INCLUDE_MOCK],
    [abs('frontend/topnav.php'), TOPNAV_MOCK],
    [abs('frontend/sidebar.php'), SIDEBAR_MOCK],
    [abs('frontend/footer.php'), FOOTER_MOCK],
  ]);
}

// ---------------------------------------------------------------------------
// Page source preparation
// ---------------------------------------------------------------------------

const BOOTSTRAP_MARKER = "require_once __DIR__ . '/../layout/bootstrap.php';";
const ROOT_BOOTSTRAP_MARKER = "require_once __DIR__ . '/frontend/layout/bootstrap.php';";

/** True when the page has been migrated to the layout system. */
function isMigrated(source) {
  return source.includes('layout_page_init');
}

/**
 * Pages that run server-side DB queries in their view layer.
 *
 * Every other sidebar page builds its document in PHP and fetches data from JS,
 * so it renders as-written. `report.php` queries the database while rendering,
 * which the preview cannot do; for those pages the leading PHP block is
 * replaced with a stub `$page` context so the layout still renders and the
 * markup hooks can be checked. Server-side values are obviously absent.
 */
// No page needs shell mode now that report.php has been migrated onto the
// layout: it runs its own layout_page_init(), and its database block is handled
// by the PhpObject stand-in (see runtime.cjs). Kept as a switch so a future
// page that genuinely cannot be interpreted can be added back.
const SHELL_MODE_PAGES = new Set([]);

/**
 * Prepare a page's source for the interpreter.
 *
 * Full mode (default): the page runs exactly as written - its own
 * `layout_page_init()` call and component setup included. This is what makes
 * the render a faithful check of the migrated page.
 *
 * Shell mode: the leading PHP block (up to the first `?>`) is swapped for a
 * bootstrap require plus a minimal `$page`, so a DB-backed page still renders
 * its document shell.
 */
function prepareSource(source, pageFile) {
  if (!SHELL_MODE_PAGES.has(pageFile)) {
    return source;
  }

  const firstClose = source.indexOf('?>');
  if (firstClose === -1) return source;

  const relative = pageFile.startsWith('frontend/pages/')
    ? BOOTSTRAP_MARKER
    : ROOT_BOOTSTRAP_MARKER;

  const pageId = pageFile.split('/').pop().replace(/\.php$/, '');
  // The replacement keeps a closing `?>`: without it the tokenizer would stay
  // in PHP mode and lex the document body's next `<?php` as operators.
  return `<?php\n${relative}\n`
    + `$page = layout_page_init(['id' => '${pageId}', 'title' => '${pageId}', 'permission' => 'none']);\n`
    + `?>` + source.slice(firstClose + 2);
}

/** Which mode a page renders in. */
function renderMode(pageFile) {
  return SHELL_MODE_PAGES.has(pageFile) ? 'shell' : 'full';
}

/** Session state used for the render. */
function mockSession(role = 'admin') {
  const session = new PhpArray();
  session.set('user_id', 1);
  session.set('username', 'Preview User');
  session.set('role', role);
  session.set('csrf_token', 'preview-csrf-token');
  session.set('fellowship_id', null);
  return session;
}

function mockServer(pageFile) {
  const scriptName = '/' + pageFile.replace(/^\/+/, '');
  const server = new PhpArray();
  server.set('SCRIPT_NAME', scriptName);
  server.set('REQUEST_METHOD', 'GET');
  server.set('HTTP_HOST', 'preview.local');
  server.set('DOCUMENT_ROOT', '/');
  server.set('HTTPS', '');
  return server;
}

/**
 * Render one page and return its HTML.
 *
 * @param {string} pageFile repo-relative path
 * @param {{role?: string, verbose?: boolean}} options
 */
function renderPage(pageFile, options = {}) {
  const role = options.role || 'admin';
  const abs = path.resolve(REPO_ROOT, pageFile);
  const source = fs.readFileSync(abs, 'utf8');

  if (!isMigrated(source)) {
    return {
      pageFile,
      migrated: false,
      html: null,
      note: 'page still uses hand-rolled markup; nothing to render',
    };
  }

  const prepared = prepareSource(source, pageFile);

  const interp = new Interpreter({
    rootDir: REPO_ROOT,
    mockIncludes: buildMockIncludes(),
    verbose: options.verbose !== false,
    predefined: {
      $_SESSION: mockSession(role),
      $_SERVER: mockServer(pageFile),
      $_GET: new PhpArray(),
      $_POST: new PhpArray(),
      $_COOKIE: new PhpArray(),
      $_FILES: new PhpArray(),
    },
    constants: {
      __DIR__: path.dirname(abs),
      __FILE__: abs,
    },
  });

  try {
    interp.runSource(prepared, abs);
  } catch (error) {
    // exit()/die() is a normal way for a page to stop (auth guard, layout
    // misuse). Treat it as "render finished here" and keep whatever was output,
    // so the caller can still inspect the partial document.
    if (error instanceof PhpExit) {
      return {
        pageFile,
        migrated: true,
        html: interp.getOutput(),
        role,
        exited: true,
      };
    }
    return {
      pageFile,
      migrated: true,
      html: null,
      error: error.message,
      partial: interp.getOutput(),
    };
  }

  return { pageFile, migrated: true, html: interp.getOutput(), role, mode: renderMode(pageFile) };
}

module.exports = { renderPage, isMigrated, prepareSource, renderMode, buildMockIncludes, REPO_ROOT, SHELL_MODE_PAGES };
