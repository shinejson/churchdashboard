<?php
/**
 * Document body opener: <body>, topnav, #app, sidebar and <main class="content">.
 *
 * This is the exact skeleton every page repeated by hand (topnav outside #app,
 * sidebar and main inside it). The CSS sibling selectors in style.css
 * (.sidebar.collapsed ~ .content / ~ .topnav) and main.js's showPage() both
 * depend on that structure, so it is centralised here rather than re-derived
 * per page.
 *
 * The content wrapper id is deliberately configurable: pages are inconsistent
 * today (users-content, financial-content, dashboard-content, report.php's
 * main-content) and several have no id at all. Each page passes exactly the id
 * it already used so nothing that binds to it changes.
 *
 * Requires $page from layout_page_init().
 *
 * @see frontend/layout/body-close.php
 */

if (!function_exists('layout_url')) {
    require_once __DIR__ . '/bootstrap.php';
}

/** @var array $page */
$page = $page ?? layout_current_page();
if (!$page) {
    layout_fail('layout/body-open.php rendered without a page context - call layout_page_init() first.');
}

$bodyClasses = array_filter([
    'page-' . $page['id'],
    $page['bodyClass'] !== '' ? $page['bodyClass'] : null,
]);

$contentId = $page['contentId'] ?? null;
$hasWrapper = (bool)($page['contentWrapper'] ?? true);
$topnavInContent = ($page['topnavPlacement'] ?? 'before-app') === 'in-content';
$contentTag = ($page['contentTag'] ?? 'div') === 'main' ? 'main' : 'div';
$contentClasses = array_filter([
    'page-content',
    ($page['contentActive'] ?? true) ? 'active' : null,
    $page['contentClass'] ?? null,
]);
?>
<body<?= $bodyClasses ? ' class="' . htmlspecialchars(implode(' ', $bodyClasses), ENT_QUOTES) . '"' : '' ?> data-page="<?= htmlspecialchars($page['id'], ENT_QUOTES) ?>">
<?php if (!$topnavInContent): ?>
<?php include FRONTEND_DIR . '/topnav.php'; ?>
<?php endif; ?>

    <div id="app">
<?php include FRONTEND_DIR . '/sidebar.php'; ?>

<?php if ($contentTag === 'main'): ?>
        <main<?= $contentId ? ' id="' . htmlspecialchars($contentId, ENT_QUOTES) . '"' : '' ?> class="<?= htmlspecialchars(layout_classes('content', $page['contentClass'] ?? null), ENT_QUOTES) ?>">
<?php else: ?>
        <main class="content">
<?php endif; ?>
<?php if ($topnavInContent): ?>
<?php include FRONTEND_DIR . '/topnav.php'; ?>
<?php endif; ?>
<?php if ($hasWrapper && $contentTag !== 'main'): ?>
            <div<?= $contentId ? ' id="' . htmlspecialchars($contentId, ENT_QUOTES) . '"' : '' ?> class="<?= htmlspecialchars(implode(' ', $contentClasses), ENT_QUOTES) ?>">
<?php endif; ?>
