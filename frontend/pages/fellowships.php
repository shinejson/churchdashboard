<?php
/**
 * Fellowships
 *
 * Fellowship records (leaders, meeting schedules, membership) managed by
 * js/fellowships.js. Rendered through the shared layout; every DOM hook the
 * script binds to is preserved.
 *
 * Note: this page ships Font Awesome class names (fa-plus, fa-users, ...) but
 * never loaded the Font Awesome stylesheet, so those <i> elements render empty.
 * The markup is kept exactly as-is here rather than silently changing how the
 * page looks - see REFACTORING.md for the follow-up.
 */

require_once __DIR__ . '/../layout/bootstrap.php';

$fellowshipModal = layout_modal([
    'id' => 'fellowship-modal',
    'modalContent' => '
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
            <h3 id="modal-title">
                <i class="fas fa-users"></i>
                <span>Create New Fellowship</span>
            </h3>
            <button class="close" type="button" aria-label="Close modal">&times;</button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="modal-body">
            <form id="fellowship-form" class="modal-form">
                <input type="hidden" id="fellowship-id">

                <!-- Basic Information Section -->
                <div class="form-row">
                    ' . layout_form_group([
                        'id' => 'fellowship-name',
                        'labelHtml' => 'Fellowship Name <span class="required">*</span>',
                        'control' => layout_input([
                            'type' => 'text',
                            'id' => 'fellowship-name',
                            'class' => null,
                            'required' => true,
                            'placeholder' => 'e.g. Youth Fellowship',
                            'autocomplete' => 'off',
                        ]),
                        'hint' => 'Choose a clear, descriptive name',
                    ]) . '

                    ' . layout_form_group([
                        'id' => 'fellowship-status',
                        'labelHtml' => 'Status <span class="required">*</span>',
                        'control' => layout_select([
                            'id' => 'fellowship-status',
                            'class' => null,
                            'required' => true,
                            'options' => [
                                ['value' => '', 'label' => 'Select Status'],
                                ['value' => 'active', 'label' => 'Active', 'selected' => true],
                                ['value' => 'inactive', 'label' => 'Inactive'],
                                ['value' => 'pending', 'label' => 'Pending'],
                            ],
                        ]),
                    ]) . '
                </div>

                <!-- Leadership Section -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user-tie"></i>
                        Leadership
                    </div>
                </div>

                <div class="form-row">
                    ' . layout_form_group([
                        'id' => 'fellowship-leader',
                        'labelHtml' => 'Leader <span class="required">*</span>',
                        'control' => layout_select([
                            'id' => 'fellowship-leader',
                            'class' => null,
                            'required' => true,
                            // Remaining options are populated by fellowships.js.
                            'options' => [['value' => '', 'label' => 'Select Leader']],
                        ]),
                        'hint' => 'Primary fellowship leader',
                    ]) . '

                    ' . layout_form_group([
                        'id' => 'fellowship-assistant',
                        'label' => 'Assistant Leader',
                        'control' => layout_select([
                            'id' => 'fellowship-assistant',
                            'class' => null,
                            'options' => [['value' => '', 'label' => 'Select Assistant (Optional)']],
                        ]),
                        'hint' => 'Secondary leader (optional)',
                    ]) . '
                </div>

                <!-- Schedule Section -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Meeting Schedules
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Meeting Schedules <span class="required">*</span></label>
                        <div class="meeting-schedules" id="meeting-schedules-container">
                            <!-- Meeting schedules are populated by fellowships.js -->
                        </div>
                        <button type="button" class="add-meeting-btn" onclick="fellowships.addMeetingSchedule()">
                            <i class="fas fa-plus"></i>
                            Add Another Meeting Schedule
                        </button>
                        <span class="form-hint">Add all meeting days and times. The first one will be considered primary.</span>
                    </div>
                </div>

                <!-- Location Section -->
                <div class="form-row full">
                    ' . layout_form_group([
                        'id' => 'meeting-location',
                        'labelHtml' => 'Location <span class="required">*</span>',
                        'control' => layout_input([
                            'type' => 'text',
                            'id' => 'meeting-location',
                            'class' => null,
                            'required' => true,
                            'placeholder' => 'e.g. Main Hall, Room 201',
                            'autocomplete' => 'off',
                        ]),
                        'hint' => 'Where the fellowship meets',
                    ]) . '
                </div>

                <!-- Description Section -->
                <div class="form-row full">
                    ' . layout_form_group([
                        'id' => 'fellowship-description',
                        'label' => 'Description',
                        'control' => layout_textarea([
                            'id' => 'fellowship-description',
                            'rows' => 4,
                            'placeholder' => 'Enter fellowship purpose, vision, and activities...',
                        ]),
                        'hint' => "Describe the fellowship's purpose and activities",
                    ]) . '
                </div>
            </form>
        </div>

        <!-- Modal Footer: a SIBLING of .modal-body. The submit button targets the
             form through its form="" attribute, so the layout_modal() footer slot
             (which renders inside the body flow) is not used here. -->
        <div class="form-actions">
            <button type="button" id="cancel-fellowship" class="btn btn-outline">
                <i class="fas fa-times"></i>
                Cancel
            </button>
            <button type="submit" form="fellowship-form" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Save Fellowship
            </button>
        </div>
    </div>',
]);

$page = layout_page_init([
    'id' => 'fellowships',
    'title' => 'Fellowships',
    'permission' => 'fellowships',
    'contentId' => 'fellowships-content',
    'css' => ['css/style.css', 'css/fellowships.css', 'css/icons.css'],
    'scripts' => ['js/ui.js', 'js/main.js', 'js/fellowships.js'],
    'overlays' => $fellowshipModal,
]);

require_once LAYOUT_DIR . '/head.php';
?>

<?php require_once LAYOUT_DIR . '/body-open.php'; ?>

                <?= layout_page_header([
                    'title' => 'Fellowships Management',
                    'actions' => [layout_button([
                        'id' => 'create-fellowship-btn',
                        'label' => 'Create New Fellowship',
                        'icon' => 'fa-plus',
                        'classes' => 'btn btn-primary',
                    ])],
                ]) ?>

                <!-- Stats Overview -->
                <?= layout_stats_overview([
                    layout_stat_card([
                        'icon' => 'fa-users',
                        'iconModifier' => 'total',
                        'valueId' => 'total-fellowships',
                        'label' => 'Total Fellowships',
                    ]),
                    layout_stat_card([
                        'icon' => 'fa-check-circle',
                        'iconModifier' => 'active',
                        'valueId' => 'active-fellowships',
                        'label' => 'Active Fellowships',
                    ]),
                    layout_stat_card([
                        'icon' => 'fa-user-friends',
                        'iconModifier' => 'members',
                        'valueId' => 'total-members',
                        'label' => 'Total Members',
                    ]),
                ]) ?>

                <?= layout_section_title('Fellowships Overview', 'fa-th-large') ?>

                <?= layout_toolbar([
                    'search' => [
                        'id' => 'fellowship-search',
                        'placeholder' => 'Search fellowships by name, leader or location...',
                    ],
                    'buttons' => [layout_button([
                        'id' => 'export-fellowships',
                        'label' => 'Export List',
                        'icon' => 'fa-download',
                        'classes' => 'btn btn-outline',
                    ])],
                ]) ?>

                <!-- Grid View: cards populated by fellowships.js -->
                <div class="fellowships-grid" id="fellowships-grid"></div>

                <!-- Table View -->
                <?= layout_section_title('Detailed Records', 'fa-table') ?>

                <?= layout_data_table([
                    'tbodyId' => 'fellowships-table-body',
                    'columns' => [
                        ['label' => 'Fellowship'],
                        ['label' => 'Leader'],
                        ['label' => 'Members'],
                        ['label' => 'Schedule'],
                        ['label' => 'Location'],
                        ['label' => 'Status'],
                        ['label' => 'Actions'],
                    ],
                ]) ?>

<?php require_once LAYOUT_DIR . '/body-close.php'; ?>
