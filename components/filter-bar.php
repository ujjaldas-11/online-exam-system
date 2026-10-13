<?php
/**
- Global Filter & Search Bar Component (Modernized)
- 
- Expected configuration variables passed before inclusion:
- 
@var string $form_action      (The page URL the form submits to)
- 
@var array  $departments      (Optional array of departments for dropdown)
- 
@var bool   $show_dept        (Show/Hide department dropdown, default false)
- 
@var bool   $show_sem         (Show/Hide semester dropdown, default false)
- 
@var bool   $show_status      (Show/Hide status dropdown, default false)
- 
@var string $search_placeholder (Placeholder text for the search box)
 */

// --- Build a Smart Reset URL ---
$reset_params = [];
foreach ($_GET as $key => $val) {
    if (is_scalar($val) && !in_array($key, ['q', 'department', 'semester', 'status', 'author', 'page'])) {
        $reset_params[$key] = $val;
    }
}
$reset_query_string = http_build_query($reset_params);
$reset_url = $form_action . ($reset_query_string ? '?' . $reset_query_string : '');

$form_action = $form_action ?? '#';
$show_dept = $show_dept ?? false;
$show_sem = $show_sem ?? false;
$show_status = $show_status ?? false;
$search_placeholder = $search_placeholder ?? "Search records...";
$show_author = $show_author ?? false;

// Retain existing filters from GET request safely
$filterQ = $_GET['q'] ?? '';
$filterDept = $_GET['department'] ?? '';
$filterSem = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$status_list = $status_list ?? [];
$filterStatus = $_GET['status'] ?? '';
$authors_list = $authors_list ?? []; 
$filterAuthor = $_GET['author'] ?? '';

// Calculate Active Filters Count for the Badge
$active_filters_count = 0;
if (!empty($filterDept)) $active_filters_count++;
if (!empty($filterSem)) $active_filters_count++;
if (!empty($filterStatus)) $active_filters_count++;
if (!empty($filterAuthor)) $active_filters_count++;

// Determine if we need the filter button at all
$has_advanced_filters = ($show_dept || $show_sem || $show_status || $show_author);
?>

<style>
    /* Modern Filter Bar Minimal CSS */
    .modern-search-wrapper {
        position: relative;
        flex: 1;
        min-width: 200px;
    }
    .modern-search-wrapper .icon-search {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-secondary);
        font-size: 20px;
        pointer-events: none;
    }
    .modern-search-input {
        padding-left: 42px !important;
        border-radius: 8px; /* Slightly more rounded for modern feel */
        width: 100%;
    }
    
    /* Modal Overlay */
    .filter-modal-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.4);
        display: none; /* Hidden by default */
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(2px);
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .filter-modal-overlay.active {
        display: flex;
        opacity: 1;
    }
    
    /* Modal Content */
    .filter-modal-content {
        background: var(--color-bg);
        width: 100%;
        max-width: 400px;
        border-radius: var(--radius-md);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        padding: 24px;
        transform: translateY(20px);
        transition: transform 0.2s ease;
        margin: 0 16px;
    }
    .filter-modal-overlay.active .filter-modal-content {
        transform: translateY(0);
    }
    
    .filter-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background: var(--color-primary);
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 0.65rem;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--color-bg);
    }
</style>

<div style="margin-bottom: 16px;">
    <form method="GET" action="<?= htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8') ?>">
        
        <!-- Retain Hidden Params -->
        <?php foreach ($_GET as $key => $val): ?>
            <?php if (is_scalar($val) && !in_array($key, ['q', 'department', 'semester', 'status', 'author', 'page'])): ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e($val) ?>">
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Primary Top Bar -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            
            <!-- Sleek Search Input -->
            <div class="modern-search-wrapper">
                <span class="material-symbols-outlined icon-search">search</span>
                <input type="text" name="q" value="<?= e($filterQ) ?>" placeholder="<?= e($search_placeholder) ?>" class="form-control modern-search-input">
            </div>

            <!-- Open Filters Modal Button -->
            <?php if ($has_advanced_filters): ?>
                <button type="button" class="btn btn-secondary" onclick="toggleFilterModal()" style="position: relative; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                    <span class="material-symbols-outlined icon-sm">tune</span> Filters
                    <?php if ($active_filters_count > 0): ?>
                        <span class="filter-badge"><?= $active_filters_count ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>

            <!-- Direct Submit Button -->
            <a href="<?= htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="height: 38px; display: inline-flex; align-items: center; gap: 4px;" title="Reset filters" global-data-tooltip="reset filter">
                <span class="material-symbols-outlined icon-sm">restart_alt</span>
            </a> 

        </div>

        <!-- Advanced Filters Modal -->
        <?php if ($has_advanced_filters): ?>
        <div id="filterModal" class="filter-modal-overlay" onclick="handleModalClick(event)">
            <div class="filter-modal-content">
                
                <!-- Modal Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--color-border); padding-bottom: 12px;">
                    <h3 style="margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; color: var(--color-dark);">
                        <span class="material-symbols-outlined">filter_alt</span> Advanced Filters
                    </h3>
                    <button type="button" onclick="toggleFilterModal()" style="background: transparent; border: none; cursor: pointer; color: var(--color-text-secondary); display: flex; align-items: center;">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <!-- Modal Body (Dropdowns) -->
                <div style="display: grid; gap: 16px; margin-bottom: 24px;">
                    
                    <?php if ($show_author && !empty($authors_list)): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Author</label>
                            <select name="author" class="form-control" style="width: 100%;">
                                <option value="">All Authors</option>
                                <?php foreach ($authors_list as $auth): ?>
                                    <option value="<?= (int)$auth['id'] ?>" <?= (string)$filterAuthor === (string)$auth['id'] ? 'selected' : '' ?>>
                                        <?= e($auth['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_dept && !empty($departments)): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Department</label>
                            <select name="department" class="form-control" style="width: 100%;">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= e($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_sem): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Semester</label>
                            <select name="semester" class="form-control" style="width: 100%;">
                                <option value="">All Semesters</option>
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>" <?= $filterSem === $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_status && !empty($status_list)): ?>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 4px; display: block;">Status</label>
                            <select name="status" class="form-control" style="width: 100%;">
                                <option value="">All Statuses</option>
                                <?php foreach ($status_list as $value => $label): ?>
                                    <?php 
                                    if (is_numeric($value)) {
                                        $value = $label;
                                        $label = ucfirst($label);
                                    }
                                    ?>
                                    <option value="<?= e($value) ?>" <?= (string)$filterStatus === (string)$value ? 'selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- Modal Footer -->
                <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--color-border); padding-top: 16px;">
                    <a href="<?= htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="font-size: 0.9rem;">Clear All</a>
                    <button type="submit" class="btn btn-primary" style="font-size: 0.9rem;">Apply Filters</button>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </form>
</div>

<script>
    function toggleFilterModal() {
        const modal = document.getElementById('filterModal');
        if (modal) {
            modal.classList.toggle('active');
        }
    }

    // Close the modal if the user clicks the dark background overlay
    function handleModalClick(event) {
        if (event.target.classList.contains('filter-modal-overlay')) {
            toggleFilterModal();
        }
    }
</script>
