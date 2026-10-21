<?php
/**
 * Home Cells
 *
 * Two tabs - cells and cell members - managed by js/cells.js against
 * backend/ajax/cells_crud.php and backend/ajax/cell_members.php.
 *
 * Rendered through the shared layout. Notes on the details that had to be
 * preserved exactly:
 *   - the tab navigation CSS moved from an inline <style> block to css/cells.css
 *     (same rules, same order);
 *   - the cells table sorts on data-sort, the members table on
 *     data-sort-members, so the members header is passed through verbatim;
 *   - the members tab keeps its inline `display: none` rather than a `hidden`
 *     attribute, because cells.js toggles inline display to switch tabs and a
 *     `hidden` attribute would keep it hidden after the switch.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

/** The seven weekday options the day filter and the cell form share. */
$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

/** Build `[['value' => 'Monday', 'label' => 'Monday'], ...]` for layout_select(). */
$weekdayOptions = static fn(string $placeholder): array => array_merge(
    [['value' => '', 'label' => $placeholder]],
    array_map(static fn(string $day): array => ['value' => $day, 'label' => $day], $weekdays)
);

/** Inline style cells.php puts on its filter selects. */
$filterStyle = 'min-width: 200px; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color);';

$cellModal = layout_modal([
    'id' => 'cell-modal',
    'title' => 'Create New Home Cell',
    'titleIcon' => 'fa-home',
    'closeTag' => 'button',
    'bodyId' => null,
    'body' => layout_form([
        'id' => 'cell-form',
        'class' => 'modal-form',
        'body' => [
            '<input type="hidden" id="cell-id">',
            layout_form_group([
                'id' => 'cell-name',
                'labelHtml' => 'Cell Name <span class="required">*</span>',
                'control' => layout_input([
                    'type' => 'text',
                    'id' => 'cell-name',
                    'class' => null,
                    'required' => true,
                    'placeholder' => 'e.g. Life Cell, Victory Cell',
                    'autocomplete' => 'off',
                ]),
            ]),
            '<div class="form-row">'
                . layout_form_group([
                    'id' => 'cell-leader',
                    'label' => 'Cell Leader',
                    'control' => layout_select([
                        'id' => 'cell-leader',
                        'class' => null,
                        // Remaining options populated by cells.js.
                        'options' => [['value' => '', 'label' => 'Select Leader']],
                    ]),
                    'hint' => 'Optional: Assign a leader to this cell',
                ])
                . layout_form_group([
                    'id' => 'cell-fellowship',
                    'label' => 'Parent Fellowship',
                    'control' => layout_select([
                        'id' => 'cell-fellowship',
                        'class' => null,
                        'options' => [['value' => '', 'label' => 'Select Fellowship']],
                    ]),
                    'hint' => 'Optional: Link to a fellowship',
                ])
                . '</div>',
            '<div class="form-row">'
                . layout_form_group([
                    'id' => 'cell-day',
                    'label' => 'Meeting Day',
                    'control' => layout_select([
                        'id' => 'cell-day',
                        'class' => null,
                        'options' => $weekdayOptions('Select Day'),
                    ]),
                ])
                . layout_form_group([
                    'id' => 'cell-location',
                    'label' => 'Location',
                    'control' => layout_input([
                        'type' => 'text',
                        'id' => 'cell-location',
                        'class' => null,
                        'placeholder' => 'e.g. 123 Church St, Community Center',
                    ]),
                ])
                . '</div>',
            layout_form_actions([
                ['label' => 'Cancel', 'icon' => 'fa-times', 'classes' => 'btn btn-outline close-modal'],
                ['label' => 'Save Cell', 'icon' => 'fa-save', 'type' => 'submit', 'classes' => 'btn btn-primary'],
            ]),
        ],
    ]),
]);

$page = layout_page_init([
    'id' => 'cells',
    'title' => 'Home Cells',
    'permission' => 'fellowships',
    'css' => ['css/style.css', 'css/fellowships.css', 'css/icons.css', 'css/cells.css'],
    'scripts' => ['js/ui.js', 'js/main.js', 'js/cells.js'],
    'overlays' => $cellModal,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

                <!-- Page Header -->
                <?= layout_page_header([
                    'title' => 'Home Cells Management',
                    'icon' => 'fa-home',
                    'actions' => [layout_button([
                        'id' => 'create-cell-btn',
                        'label' => 'Create Cell',
                        'icon' => 'fa-plus',
                        'classes' => 'btn btn-primary',
                    ])],
                ]) ?>

                <!-- Tab Navigation -->
                <?= layout_tabs([
                    ['id' => 'cells-tab', 'label' => 'Cells', 'icon' => 'fa-home', 'active' => true],
                    ['id' => 'members-tab', 'label' => 'Cell Members', 'icon' => 'fa-users'],
                ]) ?>

                <!-- Cells Tab -->
                <div id="cells-tab" class="tab-content active">
                    <?= layout_stats_overview([
                        layout_stat_card([
                            'icon' => 'fa-home',
                            'iconModifier' => 'total',
                            'valueTag' => 'span',
                            'valueId' => 'total-cells',
                            'label' => 'Total Home Cells',
                        ]),
                        layout_stat_card([
                            'icon' => 'fa-users',
                            'iconModifier' => 'active',
                            'valueTag' => 'span',
                            'valueId' => 'cells-with-leaders',
                            'label' => 'Cells with Leaders',
                        ]),
                        layout_stat_card([
                            'icon' => 'fa-calendar-alt',
                            'iconModifier' => 'members',
                            'valueTag' => 'span',
                            'valueId' => 'active-meetings',
                            'label' => 'Active Meetings',
                        ]),
                    ]) ?>

                    <!-- Search and Filter Bar -->
                    <?= layout_toolbar([
                        'search' => [
                            'id' => 'search-input',
                            'placeholder' => 'Search cells by name, leader, or location...',
                        ],
                        'selects' => [
                            [
                                'id' => 'fellowship-filter',
                                'style' => $filterStyle,
                                // Remaining options populated by cells.js.
                                'options' => [['value' => '', 'label' => 'All Fellowships']],
                            ],
                            [
                                'id' => 'day-filter',
                                'style' => 'min-width: 180px; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color);',
                                'options' => $weekdayOptions('All Days'),
                            ],
                        ],
                        'buttons' => [layout_button([
                            'id' => 'export-btn',
                            'label' => 'Export',
                            'icon' => 'fa-download',
                            'classes' => 'btn btn-outline',
                        ])],
                    ]) ?>

                    <!-- Table Container -->
                    <?= layout_data_table([
                        'tbodyId' => 'cell-table-body',
                        'tbodyContent' => layout_table_loading(6, 'Loading home cells...'),
                        'columns' => [
                            ['label' => 'Cell Name', 'sort' => 'name', 'style' => 'width: 20%;', 'headerStyle' => 'inline-span'],
                            ['label' => 'Leader', 'sort' => 'leader_name', 'style' => 'width: 18%;', 'headerStyle' => 'inline-span'],
                            ['label' => 'Fellowship', 'sort' => 'fellowship_name', 'style' => 'width: 18%;', 'headerStyle' => 'inline-span'],
                            ['label' => 'Meeting Day', 'sort' => 'meeting_day', 'style' => 'width: 14%;', 'headerStyle' => 'inline-span'],
                            ['label' => 'Location', 'style' => 'width: 20%;'],
                            ['label' => 'Actions', 'style' => 'width: 10%; text-align: center;'],
                        ],
                    ]) ?>

                    <!-- Empty State -->
                    <?= layout_empty_state([
                        'id' => 'empty-state',
                        'icon' => 'fa-home',
                        'title' => 'No Home Cells Found',
                        'message' => 'Get started by creating your first home cell.',
                        // cells.js binds #create-cell-btn by id, so this second
                        // entry point forwards the click (as the original did).
                        'action' => [
                            'label' => 'Create Home Cell',
                            'icon' => 'fa-plus',
                            'classes' => 'btn btn-primary',
                            'attrs' => ['onclick' => "document.getElementById('create-cell-btn').click()"],
                        ],
                    ]) ?>
                </div>

                <!-- Members Tab -->
                <div id="members-tab" class="tab-content" style="display: none;">
                    <?= layout_stats_overview([
                        layout_stat_card([
                            'icon' => 'fa-users',
                            'iconModifier' => 'total',
                            'valueTag' => 'span',
                            'valueId' => 'total-cell-members',
                            'label' => 'Total Cell Members',
                        ]),
                        layout_stat_card([
                            'icon' => 'fa-user-check',
                            'iconModifier' => 'active',
                            'valueTag' => 'span',
                            'valueId' => 'active-cell-members',
                            'label' => 'Active Members',
                        ]),
                        layout_stat_card([
                            'icon' => 'fa-home',
                            'iconModifier' => 'members',
                            'valueTag' => 'span',
                            'valueId' => 'cells-with-members',
                            'label' => 'Cells with Members',
                        ]),
                    ]) ?>

                    <!-- Members Search and Filter -->
                    <?= layout_toolbar([
                        'search' => [
                            'id' => 'members-search-input',
                            'placeholder' => 'Search members by name, phone, or email...',
                        ],
                        'selects' => [
                            [
                                'id' => 'members-cell-filter',
                                'style' => $filterStyle,
                                'options' => [['value' => '', 'label' => 'All Cells']],
                            ],
                            [
                                'id' => 'members-status-filter',
                                'style' => 'min-width: 150px; padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color);',
                                'options' => [
                                    ['value' => '', 'label' => 'All Status'],
                                    ['value' => 'active', 'label' => 'Active'],
                                    ['value' => 'inactive', 'label' => 'Inactive'],
                                ],
                            ],
                        ],
                        'buttons' => [layout_button([
                            'id' => 'export-members-btn',
                            'label' => 'Export',
                            'icon' => 'fa-download',
                            'classes' => 'btn btn-outline',
                        ])],
                    ]) ?>

                    <!-- Members Table: sorts on data-sort-members, so the header
                         row is passed through verbatim rather than generated. -->
                    <?= layout_data_table([
                        'tbodyId' => 'members-table-body',
                        'tbodyContent' => layout_table_loading(6, 'Loading cell members...'),
                        'headHtml' => '<th style="width: 25%;">'
                            . '<span style="cursor: pointer;" data-sort-members="name">Member Name <i class="fas fa-sort"></i></span>'
                            . '</th>'
                            . '<th style="width: 20%;">'
                            . '<span style="cursor: pointer;" data-sort-members="cell_name">Cell <i class="fas fa-sort"></i></span>'
                            . '</th>'
                            . '<th style="width: 15%;">Phone</th>'
                            . '<th style="width: 20%;">Email</th>'
                            . '<th style="width: 10%;">'
                            . '<span style="cursor: pointer;" data-sort-members="status">Status <i class="fas fa-sort"></i></span>'
                            . '</th>'
                            . '<th style="width: 10%;">Role</th>',
                    ]) ?>

                    <!-- Members Empty State -->
                    <?= layout_empty_state([
                        'id' => 'members-empty-state',
                        'icon' => 'fa-users',
                        'title' => 'No Cell Members Found',
                        'message' => 'Members need to be assigned to cells from the Members page.',
                    ]) ?>
                </div>

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
