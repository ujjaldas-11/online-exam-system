<?php
/**
 * Global Filter & Search Bar Component
 * 
 * Expected configuration variables passed before inclusion:
 * @var string $form_action      (The page URL the form submits to)
 * @var array  $departments      (Optional array of departments for dropdown)
 * @var bool   $show_dept        (Show/Hide department dropdown, default false)
 * @var bool   $show_sem         (Show/Hide semester dropdown, default false)
 * @var bool   $show_status      (Show/Hide status dropdown, default false)
 * @var string $search_placeholder (Placeholder text for the search box)
 */

// --- Build a Smart Reset URL ---
// This keeps important IDs (like subject_id=5) but clears all search filters!
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
$authors_list = $authors_list ?? []; // Array of authors for the dropdown
$filterAuthor = $_GET['author'] ?? '';

?>

<div class="card" style="margin-bottom: 10px;">
    <form method="GET" action="<?= htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8') ?>" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">

        <?php if ($show_author && !empty($authors_list)): ?>
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 140px;">
                <label>Author</label>
                <select name="author" class="form-control">
                    <option value="">All Authors</option>
                    <?php foreach ($authors_list as $auth): ?>
                        <option value="<?= (int)$auth['id'] ?>" <?= (string)$filterAuthor === (string)$auth['id'] ? 'selected' : '' ?>>
                            <?= e($auth['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- Optional Department Filter -->
        <?php if ($show_dept && !empty($departments)): ?>
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 140px;">
                <label>Department</label>
                <select name="department" class="form-control">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= e($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- Optional Semester Filter -->
        <?php if ($show_sem): ?>
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 130px;">
                <label>Semester</label>
                <select name="semester" class="form-control">
                    <option value="">All Semesters</option>
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= $filterSem === $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- Optional Status Filter -->
        <?php if ($show_status && !empty($status_list)): ?>
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 130px;">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <?php foreach ($status_list as $value => $label): ?>
                        <?php 
                        // This handles both flat arrays ['active'] and associative arrays ['active' => 'Active']
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

        <?php foreach ($_GET as $key => $val): ?>
            <?php 
            // Only keep it if it's a simple string/number AND not one of our filter inputs
            if (is_scalar($val) && !in_array($key, ['q', 'department', 'semester', 'status', 'author', 'page'])): 
            ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e($val) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <!-- Search Input (Always Visible) -->
        <div class="form-group" style="margin-bottom: 0; flex: 2; min-width: 220px;">
            <label>Search</label>
            <input type="text" name="q" value="<?= e($filterQ) ?>" placeholder="<?= e($search_placeholder) ?>" class="form-control">
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 8px; align-items: center;">
            <button type="submit" class="btn btn-primary" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">filter_alt</span> Filter
            </button>

            <a href="<?= htmlspecialchars($reset_url, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary" style="height: 38px; display: inline-flex; align-items: center; gap: 4px;" title="Reset filters">
                <span class="material-symbols-outlined icon-sm">restart_alt</span>
            </a>
        </div>
    </form>
</div>