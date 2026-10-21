<?php
/**
 * Departments
 *
 * Lists the church's departments with create/edit/delete handled by
 * js/departments.js against backend/ajax/departments_crud.php.
 *
 * Rendered through the shared layout (frontend/layout/*): the document shell,
 * theme bootstrap, CSRF meta tag, API configuration and asset URLs all come
 * from there instead of being re-declared per page. Every DOM hook the
 * department script binds to is unchanged.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

$deptModal = layout_modal([
    'id' => 'dept-modal',
    'title' => 'Create New Department',
    'titleIcon' => 'fa-building',
    'closeTag' => 'button',
    'bodyId' => null,
    'body' => layout_form([
        'id' => 'dept-form',
        'class' => 'modal-form',
        'body' => [
            '<input type="hidden" id="dept-id">',
            layout_form_group([
                'id' => 'dept-name',
                // The required marker is markup, not an option label.
                'labelHtml' => 'Department Name <span class="required">*</span>',
                'control' => layout_input([
                    'type' => 'text',
                    'id' => 'dept-name',
                    'class' => null,
                    'required' => true,
                    'placeholder' => 'e.g. Choir, Ushering, Media Team',
                    'autocomplete' => 'off',
                ]),
            ]),
            layout_form_group([
                'id' => 'dept-description',
                'label' => 'Description',
                'control' => layout_textarea([
                    'id' => 'dept-description',
                    'rows' => 4,
                    'placeholder' => 'Describe the purpose and responsibilities of this department...',
                ]),
                'hint' => 'Optional: Provide details about what this department does',
            ]),
            layout_form_actions([
                ['label' => 'Cancel', 'icon' => 'fa-times', 'variant' => 'outline', 'classes' => 'btn btn-outline close-modal'],
                ['label' => 'Save Department', 'icon' => 'fa-save', 'type' => 'submit', 'classes' => 'btn btn-primary'],
            ]),
        ],
    ]),
]);

$page = layout_page_init([
    'id' => 'departments',
    'title' => 'Departments',
    // Sidebar gates this entry on the fellowships module; keep the same rule so
    // a direct URL cannot bypass what the menu already hides.
    'permission' => 'fellowships',
    'css' => ['css/style.css', 'css/fellowships.css', 'css/icons.css'],
    'scripts' => ['js/ui.js', 'js/main.js', 'js/departments.js'],
    'overlays' => $deptModal,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

                <!-- Page Header -->
                <?= layout_page_header([
                    'title' => 'Departments Management',
                    'icon' => 'fa-building',
                    'actions' => [layout_button([
                        'id' => 'create-dept-btn',
                        'label' => 'Create Department',
                        'icon' => 'fa-plus',
                        'classes' => 'btn btn-primary',
                    ])],
                ]) ?>

                <!-- Stats Overview -->
                <?= layout_stats_overview([
                    layout_stat_card([
                        'icon' => 'fa-building',
                        'iconModifier' => 'total',
                        'valueTag' => 'span',
                        'valueId' => 'total-depts',
                        'label' => 'Total Departments',
                    ]),
                    layout_stat_card([
                        'icon' => 'fa-check-circle',
                        'iconModifier' => 'active',
                        'valueTag' => 'span',
                        'valueId' => 'active-depts',
                        'label' => 'Active Departments',
                    ]),
                ]) ?>

                <!-- Search and Filter Bar -->
                <?= layout_toolbar([
                    'search' => [
                        'id' => 'search-input',
                        'placeholder' => 'Search departments by name or description...',
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
                    'tbodyId' => 'dept-table-body',
                    'tbodyContent' => layout_table_loading(3, 'Loading departments...'),
                    'columns' => [
                        ['label' => 'Name', 'sort' => 'name', 'style' => 'width: 30%;', 'headerStyle' => 'inline-span'],
                        ['label' => 'Description', 'style' => 'width: 50%;'],
                        ['label' => 'Actions', 'style' => 'width: 20%; text-align: center;'],
                    ],
                ]) ?>

                <!-- Empty State -->
                <?= layout_empty_state([
                    'id' => 'empty-state',
                    'icon' => 'fa-building',
                    'title' => 'No Departments Found',
                    'message' => 'Get started by creating your first department.',
                    // departments.js binds #create-dept-btn by id (not by
                    // class), so this second entry point has to forward the
                    // click itself. Kept as-is from the original page: removing
                    // it would need a delegated listener added to the script.
                    'action' => [
                        'label' => 'Create Department',
                        'icon' => 'fa-plus',
                        'classes' => 'btn btn-primary',
                        'attrs' => ['onclick' => "document.getElementById('create-dept-btn').click()"],
                    ],
                ]) ?>

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
