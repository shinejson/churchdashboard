<?php
/**
 * Document <head> renderer.
 *
 * Emits the identical head every sidebar page used to hand-roll: doctype,
 * charset/viewport, CSRF meta, fonts, stylesheets (page order preserved), the
 * pre-paint theme script and the API/app configuration blocks.
 *
 * Requires $page from layout_page_init().
 *
 * @see frontend/layout/bootstrap.php
 */

if (!function_exists('layout_url')) {
    require_once __DIR__ . '/bootstrap.php';
}

/** @var array $page */
$page = $page ?? layout_current_page();
if (!$page) {
    layout_fail('layout/head.php rendered without a page context - call layout_page_init() first.');
}

$ctx = $page['context'];

/** @var array $htmlAttrs Extra attributes for the <html> tag. */
$htmlAttrs = $page['htmlAttrs'];
$htmlAttrString = '';
foreach ($htmlAttrs as $name => $value) {
    $htmlAttrString .= ' ' . htmlspecialchars((string)$name, ENT_QUOTES) . '="' . htmlspecialchars((string)$value, ENT_QUOTES) . '"';
}

$siteName = defined('SITE_NAME') ? SITE_NAME : 'Church Management System';
$titleSuffix = $page['titleSuffix'] ?? $siteName;
$fullTitle = $page['title'] . ($titleSuffix !== '' ? ' — ' . $titleSuffix : '');

/**
 * Resolve one stylesheet entry.
 *
 * Absolute URLs (CDN sheets) pass straight through, so a page can put a CDN
 * sheet at an exact point in its cascade. members.php needs precisely that: it
 * loads members.css, then Font Awesome, then style.css and screens.css, and
 * members.css relies on coming before those two.
 */
$resolveStylesheet = static function ($entry) use ($ctx): string {
    $href = is_array($entry) ? (string)($entry['href'] ?? '') : (string)$entry;

    if (preg_match('#^(https?:)?//#', $href)) {
        return $href;
    }

    return layout_asset($href, $ctx);
};

// Stylesheets render in the exact order the page declares.
$stylesheets = array_map($resolveStylesheet, $page['css']);

// Body scripts are rendered by layout/body-close.php; the head only carries the
// configuration + environment scripts that must exist before anything else runs.
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($page['lang'] ?? 'en', ENT_QUOTES) ?>"<?= $htmlAttrString ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($page['csrfToken'], ENT_QUOTES) ?>">
<?php if (!empty($page['description'])): ?>
    <meta name="description" content="<?= htmlspecialchars($page['description'], ENT_QUOTES) ?>">
<?php endif; ?>
    <title><?= htmlspecialchars($fullTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@<?= htmlspecialchars($page['fontWeights'] ?? '400;500;600;700;800', ENT_QUOTES) ?>&display=swap" rel="stylesheet">
<?php
// Shorthand for "this page uses Font Awesome". Pages that need the FA sheet at a
// specific point in their cascade (members.php) list its URL in 'css' instead
// and leave this flag off.
?>
<?php if (!empty($page['fontAwesome'])): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<?php endif; ?>
<?php foreach ($stylesheets as $href): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($href, ENT_QUOTES) ?>">
<?php endforeach; ?>
<?php foreach ($page['cdnCss'] as $href): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($href, ENT_QUOTES) ?>">
<?php endforeach; ?>

    <script>
        /* Pre-paint theme: avoids a light flash before the CSS loads. */
        (function () {
            var saved = localStorage.getItem('theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var isDark = saved === 'dark' || (!saved && prefersDark);
            document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        })();
    </script>

<?php
// API configuration (window.BASE_URL / BACKEND_URL / getApiUrl / ...).
// includes/config.php degrades to a self-detecting fallback when paths.php
// constants are unavailable, so this is safe on every deployment shape.
require_once FRONTEND_DIR . '/includes/config.php';
outputApiConfig();
?>
    <script>
        /* App configuration consumed by ui.js, main.js and the page scripts. */
        (function () {
            var origin = window.location.origin;
            var appBase = <?= json_encode($ctx['appBase']) ?>;
            var base = origin + appBase + '/';
            window.APP_CONFIG = {
                baseUrl: base,
                frontendUrl: <?= json_encode($ctx['frontendUrl']) ?>,
                pagesUrl: <?= json_encode($ctx['pagesUrl']) ?>,
                backendUrl: <?= json_encode($ctx['backendUrl']) ?>,
                loginUrl: origin + <?= json_encode($ctx['loginUrl']) ?>,
                rootUrl: origin + <?= json_encode($ctx['rootUrl']) ?>,
                profileUrl: origin + <?= json_encode($ctx['profileUrl']) ?>,
                pageId: <?= json_encode($page['id']) ?>,
                role: <?= json_encode($page['role']) ?>,
                isAdmin: <?= json_encode($page['isAdmin']) ?>,
                csrfToken: <?= json_encode($page['csrfToken']) ?>
            };
        })();
    </script>
<?php
// CDN libraries that must be present before env.js/config.js run.
?>
<?php foreach ($page['headCdnJs'] ?? [] as $src): ?>
    <script src="<?= htmlspecialchars(is_array($src) ? (string)($src['src'] ?? '') : (string)$src, ENT_QUOTES) ?>"></script>
<?php endforeach; ?>
    <script src="<?= htmlspecialchars(layout_url('js/env.js', $ctx), ENT_QUOTES) ?>"></script>
    <script src="<?= htmlspecialchars(layout_url('js/config.js', $ctx), ENT_QUOTES) ?>"></script>
<?php
// CDN libraries a page needs before its own body scripts run. members.php
// loads PapaParse, SheetJS and JSZip here so members.js can use them for the
// bulk Excel import.
?>
<?php foreach ($page['cdnJs'] as $src): ?>
    <script src="<?= htmlspecialchars($src, ENT_QUOTES) ?>"></script>
<?php endforeach; ?>
<?php if (!empty($page['head'])): ?>

    <?= $page['head'] ?>
<?php endif; ?>
</head>
