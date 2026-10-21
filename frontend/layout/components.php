<?php
/**
 * Markup Components
 *
 * Renderers for the building blocks the sidebar pages repeat: page headers,
 * stat cards, search/filter toolbars, data tables, tab bars, empty states,
 * modals and form fields.
 *
 * Design rules
 * ------------
 * 1. Output is markup-identical to what the pages hand-wrote, including class
 *    names and inline styles, because the page CSS and the big page scripts
 *    (attendance.js, financial.js, members.js, users.js ...) bind to them.
 * 2. Every renderer accepts the variants that already exist in the codebase
 *    instead of forcing one shape. Where pages genuinely differ (stat card with
 *    <span> children vs <div> children, sortable <th> with a .th-content div vs
 *    an inline <span>) the variant is an explicit option.
 * 3. Text is escaped; markup is not. Keys ending in `Html`/`html`, plus `icon`
 *    and `actions`, are passed through raw and must only ever contain markup
 *    authored in PHP — never user input. Anything user-supplied goes through the
 *    escaping keys or layout_e().
 *
 * @see frontend/layout/bootstrap.php
 */

declare(strict_types=1);

/** Escape a string for safe HTML output. */
function layout_e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Build an attribute string from a map.
 *
 * Boolean true renders a bare attribute (`defer`), null/false/'' is skipped,
 * everything else is escaped. `class` and `style` values are merged when they
 * are given as arrays.
 */
function layout_attrs(array $attrs): string
{
    $out = '';
    foreach ($attrs as $name => $value) {
        if ($value === null || $value === false || $value === '') {
            continue;
        }
        if (is_array($value)) {
            $value = implode(' ', array_filter($value, static fn($v) => $v !== null && $v !== '' && $v !== false));
            if ($value === '') {
                continue;
            }
        }
        if ($value === true) {
            $out .= ' ' . $name;
            continue;
        }
        $out .= ' ' . $name . '="' . layout_e($value) . '"';
    }
    return $out;
}

/** Merge extra classes onto a base class list. */
function layout_classes(string $base, $extra = null): string
{
    $extra = is_array($extra) ? implode(' ', $extra) : (string)($extra ?? '');
    return trim($base . ' ' . $extra);
}

/** Font Awesome icon tag: layout_icon('fa-plus') -> <i class="fas fa-plus"></i>. */
function layout_icon(string $icon, string $extraClass = ''): string
{
    $classes = layout_classes(trim('fas ' . $icon), $extraClass);
    return '<i class="' . layout_e($classes) . '"></i>';
}

/** A <button> with the app's button classes. */
function layout_button(array $config = []): string
{
    $label = $config['label'] ?? '';
    $icon = $config['icon'] ?? null;
    $variant = $config['variant'] ?? 'secondary';   // primary|secondary|outline
    $classes = $config['classes'] ?? null;

    if ($classes === null) {
        // Pages disagree on `btn btn-primary` vs `btn-primary`; both are
        // supported via `btnStyle`: 'compound' (default) or 'plain'.
        $plain = ($config['btnStyle'] ?? 'compound') === 'plain';
        $classes = $plain ? 'btn-' . $variant : layout_classes('btn', 'btn-' . $variant);
    }

    $attrs = [
        'type' => $config['type'] ?? 'button',
        'id' => $config['id'] ?? null,
        'class' => $classes,
        'title' => $config['title'] ?? null,
        'aria-label' => $config['ariaLabel'] ?? null,
        'data-target' => $config['dataTarget'] ?? null,
        'data-tab' => $config['dataTab'] ?? null,
        'disabled' => !empty($config['disabled']),
        'style' => $config['style'] ?? null,
    ];
    foreach (($config['attrs'] ?? []) as $name => $value) {
        $attrs[$name] = $value;
    }

    $inner = '';
    if ($icon !== null) {
        $inner .= is_array($icon) ? layout_icon($icon['icon'] ?? '', $icon['class'] ?? '') : layout_icon((string)$icon);
    }
    if (!empty($config['iconHtml'])) {
        $inner .= $config['iconHtml'];
    }
    if (!empty($config['labelHtml'])) {
        $inner .= $config['labelHtml'];
    } elseif ($label !== '') {
        // Users/Members wrap the label in <span>; keep that when asked.
        $inner .= !empty($config['wrapLabel']) ? '<span>' . layout_e($label) . '</span>' : layout_e($label);
    }

    return '<button' . layout_attrs($attrs) . '>' . trim($inner) . '</button>';
}

/**
 * Page header.
 *
 * `layout` => 'simple'  : .page-header > h2 + button           (cells, departments, fellowships)
 * `layout` => 'content' : .page-header > .header-content > h2 + .header-actions (users, members)
 */
function layout_page_header(array $config = []): string
{
    $title = $config['title'] ?? '';
    $icon = $config['icon'] ?? null;
    $iconClass = $config['iconClass'] ?? '';
    $subtitle = $config['subtitle'] ?? null;
    $layout = $config['layout'] ?? 'simple';
    $actions = $config['actions'] ?? [];
    $actionsHtml = is_array($actions) ? implode("\n", $actions) : (string)$actions;

    $heading = '<h2>'
        . ($icon !== null ? layout_icon((string)$icon, $iconClass) . ' ' : '')
        . ($config['titleHtml'] ?? layout_e($title))
        . '</h2>';

    if ($subtitle !== null) {
        $heading .= '<p class="page-subtitle">' . layout_e($subtitle) . '</p>';
    }

    if ($layout === 'content') {
        $search = '';
        if (!empty($config['search'])) {
            $search = layout_search_box($config['search']);
        }
        return '<div class="page-header">' . "\n"
            . '    <div class="header-content">' . "\n"
            . '        ' . $heading . "\n"
            . '        <div class="header-actions">' . "\n"
            . ($search !== '' ? '            ' . $search . "\n" : '')
            . ($actionsHtml !== '' ? '            ' . $actionsHtml . "\n" : '')
            . '        </div>' . "\n"
            . '    </div>' . "\n"
            . '</div>';
    }

    return '<div class="page-header">' . "\n"
        . '    ' . $heading . "\n"
        . ($actionsHtml !== '' ? '    ' . $actionsHtml . "\n" : '')
        . '</div>';
}

/** Search input with the magnifier icon (.search-container or .search-box). */
function layout_search_box(array $config = []): string
{
    $variant = $config['variant'] ?? 'box'; // 'box' -> .search-box, 'container' -> .search-container
    $wrapper = $variant === 'container' ? 'search-container' : 'search-box';
    $inputClass = $variant === 'container' ? 'search-input' : null;
    $iconClass = $variant === 'container' ? 'search-icon' : '';

    $attrs = [
        'type' => $config['type'] ?? 'text',
        'id' => $config['id'] ?? null,
        'name' => $config['name'] ?? null,
        'class' => $inputClass,
        'placeholder' => $config['placeholder'] ?? 'Search...',
        'aria-label' => $config['ariaLabel'] ?? ($config['placeholder'] ?? null),
        'autocomplete' => $config['autocomplete'] ?? null,
        'value' => $config['value'] ?? null,
    ];

    return '<div class="' . layout_e($wrapper) . '">'
        . '<input' . layout_attrs($attrs) . '>'
        . layout_icon($config['icon'] ?? 'fa-search', $iconClass)
        . '</div>';
}

/** A <select> filter, optionally with the inline style cells.php uses. */
function layout_select(array $config = []): string
{
    $options = '';
    foreach (($config['options'] ?? []) as $option) {
        if (is_array($option)) {
            $options .= '<option value="' . layout_e($option['value'] ?? '') . '"'
                . (!empty($option['selected']) ? ' selected' : '')
                . '>' . layout_e($option['label'] ?? '') . '</option>';
        } else {
            $options .= '<option>' . layout_e($option) . '</option>';
        }
    }

    $attrs = [
        'id' => $config['id'] ?? null,
        'name' => $config['name'] ?? null,
        'class' => $config['class'] ?? 'form-input',
        'style' => $config['style'] ?? null,
        'aria-label' => $config['ariaLabel'] ?? null,
        'required' => !empty($config['required']),
        'disabled' => !empty($config['disabled']),
    ];
    foreach (($config['attrs'] ?? []) as $name => $value) {
        $attrs[$name] = $value;
    }

    return '<select' . layout_attrs($attrs) . '>' . $options . '</select>';
}

/**
 * Stat card.
 *
 * `valueTag`  'span'|'div'  — cells/departments use <span>, users/fellowships use <div>
 * `contentWrapper` bool     — users.php wraps value/label/trend in .stat-content
 * `trend` array|null        — ['html' => ..., 'class' => 'trend-positive']
 */
function layout_stat_card(array $config = []): string
{
    $valueTag = $config['valueTag'] ?? 'div';
    $iconModifier = $config['iconModifier'] ?? null;   // 'total' | 'active' | ...
    $iconClasses = layout_classes('stat-icon', $iconModifier);

    $icon = '';
    if (!empty($config['icon'])) {
        $icon = '<div class="' . layout_e($iconClasses) . '">' . layout_icon((string)$config['icon']) . '</div>';
    } elseif (!empty($config['iconHtml'])) {
        $icon = '<div class="' . layout_e($iconClasses) . '">' . $config['iconHtml'] . '</div>';
    }

    // financial.php colours its income/expense figures with extra classes on the
    // value element itself (`.stat-value.amount-income`), so the value and label
    // class lists are both overridable rather than hardcoded.
    $valueClasses = layout_classes('stat-value', $config['valueClass'] ?? null);
    $labelClasses = layout_classes('stat-label', $config['labelClass'] ?? null);

    $value = '<' . $valueTag . ' class="' . layout_e($valueClasses) . '"'
        . (!empty($config['valueId']) ? ' id="' . layout_e($config['valueId']) . '"' : '')
        . '>' . layout_e($config['value'] ?? '0') . '</' . $valueTag . '>';

    $label = '<' . $valueTag . ' class="' . layout_e($labelClasses) . '">' . layout_e($config['label'] ?? '') . '</' . $valueTag . '>';

    $trend = '';
    if (!empty($config['trend'])) {
        $trendClass = layout_classes('stat-trend', $config['trend']['class'] ?? null);
        $trend = '<div class="' . layout_e($trendClass) . '">' . ($config['trend']['html'] ?? '') . '</div>';
    }

    $body = $value . $label . $trend;
    if (!empty($config['contentWrapper'])) {
        $body = '<div class="stat-content">' . $body . '</div>';
    }

    $cardClass = layout_classes('stat-card', $config['class'] ?? null);
    $cardAttrs = [
        'class' => $cardClass,
        'id' => $config['id'] ?? null,
        'style' => $config['style'] ?? null,
        'aria-label' => $config['ariaLabel'] ?? null,
    ];

    return '<div' . layout_attrs($cardAttrs) . '>' . $icon . $body . '</div>';
}

/** .stats-overview wrapper around a list of layout_stat_card() results. */
function layout_stats_overview(array $cards, ?string $extraClass = null): string
{
    $classes = layout_classes('stats-overview', $extraClass);
    return '<div class="' . layout_e($classes) . '">' . "\n" . implode("\n", $cards) . "\n" . '</div>';
}

/** Search / filter / export toolbar (.search-export-bar). */
function layout_toolbar(array $config = []): string
{
    $parts = [];
    if (!empty($config['search'])) {
        $parts[] = layout_search_box($config['search']);
    }
    foreach (($config['selects'] ?? []) as $select) {
        $parts[] = layout_select($select);
    }
    foreach (($config['buttons'] ?? []) as $button) {
        $parts[] = is_string($button) ? $button : layout_button($button);
    }
    foreach (($config['extra'] ?? []) as $extra) {
        $parts[] = is_string($extra) ? $extra : '';
    }

    $classes = layout_classes('search-export-bar', $config['class'] ?? null);
    $style = $config['style'] ?? null;

    return '<div' . layout_attrs(['class' => $classes, 'id' => $config['id'] ?? null, 'style' => $style]) . '>' . "\n"
        . '    ' . implode("\n    ", $parts) . "\n"
        . '</div>';
}

/**
 * Data table scaffold.
 *
 * Columns: ['label' => 'Name', 'sort' => 'name', 'style' => 'width: 30%;',
 *            'headerStyle' => 'inline-span'|'th-content'|'plain', 'align' => 'center']
 *
 * `headerStyle` reproduces the three <th> shapes in use:
 *   'plain'       <th>Name</th>                              (fellowships)
 *   'inline-span' <th><span style="cursor:pointer" data-sort> (cells, departments)
 *   'th-content'  <th data-sort><div class="th-content">...   (users)
 */
function layout_data_table(array $config = []): string
{
    $columns = $config['columns'] ?? [];

    // `headHtml` renders a header row verbatim. Needed where a table sorts on a
    // different attribute than the shared one - cells.php's members table uses
    // data-sort-members while its cells table uses data-sort, and cells.js binds
    // to each by name.
    if (!empty($config['headHtml'])) {
        $tableClass = layout_classes('data-table', $config['tableClass'] ?? null);
        $tbodyAttrs = [
            'id' => $config['tbodyId'] ?? null,
            'class' => $config['tbodyClass'] ?? null,
        ];
        $rawTable = '<table' . layout_attrs([
                'class' => $tableClass,
                'id' => $config['tableId'] ?? null,
                'aria-label' => $config['ariaLabel'] ?? null,
            ]) . '>'
            . '<thead><tr>' . $config['headHtml'] . '</tr></thead>'
            . '<tbody' . layout_attrs($tbodyAttrs) . '>' . ($config['tbodyContent'] ?? '') . '</tbody>'
            . '</table>';

        return '<div' . layout_attrs(['class' => layout_classes('table-container', $config['class'] ?? null), 'id' => $config['id'] ?? null]) . '>'
            . $rawTable
            . '</div>';
    }

    $head = '';
    foreach ($columns as $column) {
        $thAttrs = [
            'style' => $column['style'] ?? null,
            'data-sort' => $column['sort'] ?? null,
        ];
        $label = $column['labelHtml'] ?? layout_e($column['label'] ?? '');
        $headerStyle = $column['headerStyle'] ?? 'plain';
        $sortable = !empty($column['sort']);

        if ($headerStyle === 'th-content' && $sortable) {
            $inner = '<div class="th-content"><span>' . $label . '</span>' . layout_icon('fa-sort', 'sort-icon') . '</div>';
        } elseif ($headerStyle === 'inline-span' && $sortable) {
            $inner = '<span style="cursor: pointer;" data-sort="' . layout_e($column['sort']) . '">'
                . $label . ' ' . layout_icon('fa-sort') . '</span>';
            unset($thAttrs['data-sort']);
        } else {
            $inner = $label;
        }

        $head .= '<th' . layout_attrs($thAttrs) . '>' . $inner . '</th>';
    }

    $tableClass = layout_classes('data-table', $config['tableClass'] ?? null);
    $tbodyAttrs = [
        'id' => $config['tbodyId'] ?? null,
        'class' => $config['tbodyClass'] ?? null,
    ];
    $wrapper = $config['wrapper'] ?? null; // e.g. 'table-wrapper'

    $table = '<table' . layout_attrs([
            'class' => $tableClass,
            'id' => $config['tableId'] ?? null,
            'aria-label' => $config['ariaLabel'] ?? null,
        ]) . '>'
        . ($head !== '' ? '<thead><tr>' . $head . '</tr></thead>' : '')
        . '<tbody' . layout_attrs($tbodyAttrs) . '>' . ($config['tbodyContent'] ?? '') . '</tbody>'
        . '</table>';

    if ($wrapper !== null) {
        $table = '<div class="' . layout_e($wrapper) . '">' . $table . '</div>';
    }

    return '<div' . layout_attrs(['class' => layout_classes('table-container', $config['class'] ?? null), 'id' => $config['id'] ?? null]) . '>'
        . $table
        . '</div>';
}

/** Full-width "Loading..." row for a table body. */
function layout_table_loading(int $colspan, string $message = 'Loading...'): string
{
    return '<tr><td colspan="' . $colspan . '" style="text-align: center; padding: 2rem;">'
        . '<i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i>'
        . '<p style="margin-top: 1rem; color: var(--text-muted);">' . layout_e($message) . '</p>'
        . '</td></tr>';
}

/** Empty-state block. Pages toggle its visibility, so id + inline style are kept. */
function layout_empty_state(array $config = []): string
{
    $attrs = [
        'id' => $config['id'] ?? 'empty-state',
        'class' => layout_classes('empty-state', $config['class'] ?? null),
        // Pages ship `display: none` inline and let JS flip it.
        'style' => $config['style'] ?? 'display: none; text-align: center; padding: 3rem;',
    ];

    $icon = !empty($config['icon'])
        ? '<i class="fas ' . layout_e($config['icon']) . '" style="font-size: 4rem; color: var(--text-muted); opacity: 0.3;"></i>'
        : ($config['iconHtml'] ?? '');

    $action = '';
    if (!empty($config['action'])) {
        $action = layout_button(array_merge(
            ['style' => 'margin-top: 1.5rem;'],
            $config['action']
        ));
    }

    return '<div' . layout_attrs($attrs) . '>'
        . $icon
        . '<h3 style="margin-top: 1rem; color: var(--text-color);">' . layout_e($config['title'] ?? 'Nothing here yet') . '</h3>'
        . '<p style="color: var(--text-muted); margin-top: 0.5rem;">' . layout_e($config['message'] ?? '') . '</p>'
        . $action
        . '</div>';
}

/**
 * Tab bar (.tab-navigation) + tab panels (.tab-content).
 *
 * tabs: [['id' => 'cells-tab', 'label' => 'Cells', 'icon' => 'fa-home', 'active' => true]]
 * The inline style on .tab-navigation is what cells.php ships today.
 */
function layout_tabs(array $tabs, array $config = []): string
{
    $buttons = '';
    foreach ($tabs as $tab) {
        $buttons .= '<button class="' . layout_e(layout_classes('tab-btn', !empty($tab['active']) ? 'active' : null)) . '"'
            . ' data-tab="' . layout_e($tab['id']) . '"'
            . ' type="button"'
            . ' role="tab"'
            . ' aria-controls="' . layout_e($tab['id']) . '"'
            . ' aria-selected="' . (!empty($tab['active']) ? 'true' : 'false') . '">'
            . (!empty($tab['icon']) ? layout_icon((string)$tab['icon']) . ' ' : '')
            . layout_e($tab['label'] ?? '')
            . '</button>';
    }

    $style = $config['style'] ?? 'margin-bottom: 2rem; border-bottom: 2px solid var(--border-color);';

    return '<div class="' . layout_e(layout_classes('tab-navigation', $config['class'] ?? null)) . '"'
        . ($config['id'] ?? null ? ' id="' . layout_e($config['id']) . '"' : '')
        . ' role="tablist" style="' . layout_e($style) . '">'
        . $buttons
        . '</div>';
}

/** Tab panel wrapper. Content is raw markup authored in the page. */
function layout_tab_panel(string $id, string $content, bool $active = false, ?string $extraClass = null): string
{
    return '<div id="' . layout_e($id) . '" class="' . layout_e(layout_classes('tab-content', [$active ? 'active' : null, $extraClass])) . '"'
        . ' role="tabpanel"' . ($active ? '' : ' hidden') . '>' . "\n"
        . $content . "\n"
        . '</div>';
}

/**
 * Modal shell.
 *
 * `overlay` true reproduces the .modal-overlay pages that use one
 * (financial.php, users.php) instead of a bare .modal.
 */
function layout_modal(array $config = []): string
{
    $id = $config['id'] ?? 'modal';
    $title = $config['title'] ?? '';
    $overlay = !empty($config['overlay']);

    $modalClass = layout_classes('modal', $config['class'] ?? null);
    $overlayHtml = '';
    if ($overlay) {
        $overlayId = $config['overlayId'] ?? ($id . '-overlay');
        $overlayHtml = '<div id="' . layout_e($overlayId) . '" class="modal-overlay"></div>';
    }

    $footer = '';
    if (!empty($config['footer'])) {
        $footer = '<div class="modal-footer">' . (is_array($config['footer']) ? implode("\n", $config['footer']) : $config['footer']) . '</div>';
    }

    // Close control: pages use either <span class="close"> or <button class="close">.
    if (array_key_exists('closeHtml', $config)) {
        $closeButton = (string)$config['closeHtml'];
    } elseif ($config['closeButton'] ?? true) {
        $closeButton = ($config['closeTag'] ?? 'span') === 'button'
            ? '<button class="close" type="button" aria-label="Close">&times;</button>'
            : '<span class="close" role="button" tabindex="0" aria-label="Close">&times;</span>';
    } else {
        $closeButton = '';
    }

    // Title: optional leading icon (departments, cells) or raw title markup.
    $titleIcon = !empty($config['titleIcon']) ? layout_icon((string)$config['titleIcon']) . ' ' : '';
    $titleHtml = $config['titleHtml'] ?? ($titleIcon . layout_e($title));

    // The body id is optional: pages that never reference it ship a bare div.
    $bodyId = $config['bodyId'] ?? 'modal-body';
    $bodyAttrs = $bodyId === null ? ' class="modal-body"' : ' class="modal-body" id="' . layout_e($bodyId) . '"';

    // Some modals do not fit the header/body/footer shape - fellowships.php puts
    // .form-actions as a SIBLING of .modal-body and submits via the button's
    // form="" attribute. `modalContent` renders the inner markup verbatim so
    // that structure (and its hooks) survive untouched.
    if (array_key_exists('modalContent', $config)) {
        return $overlayHtml
            . '<div id="' . layout_e($id) . '" class="' . layout_e($modalClass) . '" aria-hidden="true" role="dialog" aria-modal="true"'
            . (!empty($config['ariaLabel']) ? ' aria-label="' . layout_e($config['ariaLabel']) . '"' : '') . '>'
            . $config['modalContent']
            . '</div>';
    }

    return $overlayHtml
        . '<div id="' . layout_e($id) . '" class="' . layout_e($modalClass) . '" aria-hidden="true" role="dialog" aria-modal="true"'
        . (!empty($config['ariaLabel']) ? ' aria-label="' . layout_e($config['ariaLabel']) . '"' : '') . '>'
        . '<div class="modal-content">'
        . '<div class="modal-header">'
        . '<h3 id="' . layout_e($config['titleId'] ?? 'modal-title') . '">' . $titleHtml . '</h3>'
        . $closeButton
        . '</div>'
        . '<div' . $bodyAttrs . '>' . ($config['body'] ?? '') . '</div>'
        . $footer
        . '</div>'
        . '</div>';
}

/**
 * Form field: label + control + optional hint/error slot.
 *
 * The control markup is passed in (`control`) because the pages mix inputs,
 * selects, textareas and custom widgets; this keeps the surrounding
 * .form-group structure consistent without touching the control's own hooks.
 */
function layout_form_group(array $config = []): string
{
    $for = $config['for'] ?? ($config['id'] ?? null);
    $label = $config['label'] ?? '';
    $icon = !empty($config['icon']) ? layout_icon((string)$config['icon']) . ' ' : '';
    $required = !empty($config['required']) ? ' <span class="required" aria-hidden="true">*</span>' : '';

    // `labelHtml` is for labels that carry their own markup, e.g. the required
    // marker several pages render by hand: 'Name <span class="required">*</span>'.
    $labelHtml = $config['labelHtml'] ?? null;
    $hasLabel = $labelHtml !== null || $label !== '';
    $labelInner = $labelHtml !== null ? $labelHtml : ($icon . layout_e($label) . $required);

    return '<div class="' . layout_e(layout_classes('form-group', $config['class'] ?? null)) . '"'
        . (!empty($config['id']) && !empty($config['groupId']) ? ' id="' . layout_e($config['groupId']) . '"' : '') . '>'
        . ($hasLabel ? '<label for="' . layout_e($for) . '">' . $labelInner . '</label>' : '')
        . ($config['control'] ?? '')
        . (!empty($config['hint']) ? '<small class="form-hint">' . layout_e($config['hint']) . '</small>' : '')
        . (!empty($config['errorId']) ? '<span class="field-error" id="' . layout_e($config['errorId']) . '"></span>' : '')
        . '</div>';
}

/** A text/email/password/number/date input using the app's .form-input class. */
function layout_input(array $config = []): string
{
    $attrs = [
        'type' => $config['type'] ?? 'text',
        'id' => $config['id'] ?? null,
        'name' => $config['name'] ?? null,
        'class' => $config['class'] ?? 'form-input',
        'placeholder' => $config['placeholder'] ?? null,
        'value' => $config['value'] ?? null,
        'required' => !empty($config['required']),
        'disabled' => !empty($config['disabled']),
        'readonly' => !empty($config['readonly']),
        'min' => $config['min'] ?? null,
        'max' => $config['max'] ?? null,
        'step' => $config['step'] ?? null,
        'maxlength' => $config['maxlength'] ?? null,
        'autocomplete' => $config['autocomplete'] ?? null,
        'aria-label' => $config['ariaLabel'] ?? null,
        'style' => $config['style'] ?? null,
    ];
    foreach (($config['attrs'] ?? []) as $name => $value) {
        $attrs[$name] = $value;
    }

    return '<input' . layout_attrs($attrs) . '>';
}

/** A <textarea>. Class defaults to none: modal forms style bare textareas. */
function layout_textarea(array $config = []): string
{
    $attrs = [
        'id' => $config['id'] ?? null,
        'name' => $config['name'] ?? null,
        'class' => $config['class'] ?? null,
        'rows' => $config['rows'] ?? null,
        'cols' => $config['cols'] ?? null,
        'placeholder' => $config['placeholder'] ?? null,
        'required' => !empty($config['required']),
        'disabled' => !empty($config['disabled']),
        'readonly' => !empty($config['readonly']),
        'maxlength' => $config['maxlength'] ?? null,
        'aria-label' => $config['ariaLabel'] ?? null,
        'style' => $config['style'] ?? null,
    ];
    foreach (($config['attrs'] ?? []) as $name => $value) {
        $attrs[$name] = $value;
    }

    return '<textarea' . layout_attrs($attrs) . '>' . layout_e($config['value'] ?? '') . '</textarea>';
}

/** A <form> wrapper. Pages bind submit handlers by form id. */
function layout_form(array $config = []): string
{
    $attrs = [
        'id' => $config['id'] ?? null,
        'class' => $config['class'] ?? 'modal-form',
        'action' => $config['action'] ?? null,
        'method' => $config['method'] ?? null,
        'novalidate' => !empty($config['novalidate']),
        'autocomplete' => $config['autocomplete'] ?? null,
        'enctype' => $config['enctype'] ?? null,
    ];
    foreach (($config['attrs'] ?? []) as $name => $value) {
        $attrs[$name] = $value;
    }

    $body = is_array($config['body'] ?? null) ? implode("\n", $config['body']) : (string)($config['body'] ?? '');

    return '<form' . layout_attrs($attrs) . '>' . "\n" . $body . "\n" . '</form>';
}

/** The button row at the bottom of a modal form (.form-actions). */
function layout_form_actions(array $buttons, ?string $extraClass = null): string
{
    $rendered = array_map(
        static fn($button) => is_string($button) ? $button : layout_button($button),
        $buttons
    );

    return '<div class="' . layout_e(layout_classes('form-actions', $extraClass)) . '">' . "\n"
        . '    ' . implode("\n    ", $rendered) . "\n"
        . '</div>';
}

/** Pagination controls. Ids match the existing prev/next/first/last buttons. */
function layout_pagination(array $config = []): string
{
    $prefix = $config['prefix'] ?? '';
    $button = static function (string $id, string $label, bool $disabled = false): string {
        return '<button type="button" class="pagination-btn" id="' . layout_e($id) . '"' . ($disabled ? ' disabled' : '') . '>' . $label . '</button>';
    };

    return '<div class="' . layout_e(layout_classes('pagination', $config['class'] ?? null)) . '">'
        . $button($prefix . 'first-page', '&laquo;', true)
        . $button($prefix . 'prev-page', '&lsaquo;', true)
        . '<span class="page-numbers" id="' . layout_e($prefix . 'page-numbers') . '"></span>'
        . $button($prefix . 'next-page', '&rsaquo;', true)
        . $button($prefix . 'last-page', '&raquo;', true)
        . '</div>';
}

/** Section title used above grids/tables (fellowships.php, financial.php). */
function layout_section_title(string $title, ?string $icon = null, ?string $extraHtml = null): string
{
    return '<div class="section-title">'
        . ($icon !== null ? layout_icon($icon) . ' ' : '')
        . layout_e($title)
        . ($extraHtml ?? '')
        . '</div>';
}
