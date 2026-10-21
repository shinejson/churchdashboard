<?php
/**
 * Document body closer: closes the content wrapper / #app, renders the page's
 * overlay slot (modals), the shared footer and every body script.
 *
 * Script order is intentionally explicit and per page. Today the pages disagree
 * (users.js runs before ui.js, attendance.php loads no ui.js and pulls Chart.js
 * first, report.php stacks jQuery + Bootstrap + DataTables), so the layout
 * renders exactly the ordered list a page declares instead of normalising it
 * and changing execution order.
 *
 * Requires $page from layout_page_init().
 *
 * @see frontend/layout/body-open.php
 */

if (!function_exists('layout_url')) {
    require_once __DIR__ . '/bootstrap.php';
}

/** @var array $page */
$page = $page ?? layout_current_page();
if (!$page) {
    layout_fail('layout/body-close.php rendered without a page context - call layout_page_init() first.');
}

$ctx = $page['context'];

/**
 * Resolve one script entry to a URL.
 * Local paths ('js/users.js', 'frontend/js/users.js') get mtime cache-busting;
 * absolute URLs (CDN) are passed through untouched.
 */
$resolveScript = static function ($entry) use ($ctx): string {
    if (is_array($entry)) {
        $src = (string)($entry['src'] ?? '');
    } else {
        $src = (string)$entry;
    }

    if (preg_match('#^(https?:)?//#', $src)) {
        return $src;
    }

    return layout_asset($src, $ctx);
};
?>
<?php if (($page['contentWrapper'] ?? true) && ($page['contentTag'] ?? 'div') !== 'main'): ?>
            </div><!-- /.page-content -->
<?php endif; ?>
        </main>
    </div><!-- /#app -->

<?php if (!empty($page['overlays'])): ?>
    <?= $page['overlays'] ?>
<?php endif; ?>

<?php foreach ($page['preFooterScripts'] ?? [] as $entry): ?>
<?php
    $preAttrs = '';
    if (is_array($entry) && !empty($entry['type'])) {
        $preAttrs = ' type="' . htmlspecialchars((string)$entry['type'], ENT_QUOTES) . '"';
    }
?>
    <script src="<?= htmlspecialchars($resolveScript($entry), ENT_QUOTES) ?>"<?= $preAttrs ?>></script>
<?php endforeach; ?>

<?php if (!empty($page['bodyInline'])): ?>
<?= $page['bodyInline'] ?>
<?php endif; ?>

<?php if ($page['showFooter']): ?>
<?php include FRONTEND_DIR . '/footer.php'; ?>
<?php endif; ?>

<?php foreach ($page['scripts'] ?? [] as $entry): ?>
<?php
    $attrs = '';
    if (is_array($entry)) {
        foreach (['defer', 'async', 'type', 'id'] as $attr) {
            if (empty($entry[$attr])) {
                continue;
            }
            $attrs .= in_array($attr, ['defer', 'async'], true)
                ? ' ' . $attr
                : ' ' . $attr . '="' . htmlspecialchars((string)$entry[$attr], ENT_QUOTES) . '"';
        }
    }
?>
    <script src="<?= htmlspecialchars($resolveScript($entry), ENT_QUOTES) ?>"<?= $attrs ?>></script>
<?php endforeach; ?>

<?php if (!empty($page['footer'])): ?>
    <?= $page['footer'] ?>
<?php endif; ?>
</body>
</html>
