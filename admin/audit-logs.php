<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$isAdminSuper = is_superadmin();
$currentAdminId = (int) $_SESSION['admin_id'];

// Filter parameters
$filterAdminId = $isAdminSuper ? int_param($_GET['admin_id'] ?? 0) : $currentAdminId;
$filterAction = clean_input($_GET['action'] ?? '');
$searchQuery = clean_input($_GET['q'] ?? '');
$filterQ = $searchQuery;
$filterYear = isset($_GET['year']) && $_GET['year'] === 'all' ? 'all' : (isset($_GET['year']) && is_numeric($_GET['year']) && (int)$_GET['year'] > 0 ? (int)$_GET['year'] : (int)date('Y'));

$where = [];
$params = [];

if ($filterYear !== 'all') {
    $where[] = "YEAR(l.created_at) = ?";
    $params[] = $filterYear;
}

if (!$isAdminSuper) {
    $where[] = "l.admin_id = ?";
    $params[] = $currentAdminId;
} elseif ($filterAdminId > 0) {
    $where[] = "l.admin_id = ?";
    $params[] = $filterAdminId;
}

if (!empty($filterAction)) {
    $where[] = "l.action = ?";
    $params[] = $filterAction;
}

if (!empty($searchQuery)) {
    $where[] = "(l.details LIKE ? OR l.admin_name LIKE ? OR l.action LIKE ? OR l.ip_address LIKE ?)";
    $like = "%$searchQuery%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    // List of admins for the filter dropdown (superadmin only)
    $allAdmins = $isAdminSuper ? $pdo->query("SELECT id, name, role, status FROM admins ORDER BY name ASC")->fetchAll() : [];

    // Distinct actions for filter dropdown
    $allActions = $pdo->query("SELECT DISTINCT action FROM admin_audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

    // Total count for current filter
    $countSql = "
        SELECT COUNT(*)
        FROM admin_audit_logs l
        LEFT JOIN admins a ON l.admin_id = a.id
        $whereSql
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total_logs_count = (int) $countStmt->fetchColumn();

    // Pagination parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    // Main audit query
    $sql = "
        SELECT
            l.*,
            a.status as current_admin_status,
            a.role as current_admin_role
        FROM admin_audit_logs l
        LEFT JOIN admins a ON l.admin_id = a.id
        $whereSql
        ORDER BY l.id DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to query audit logs", $e);
    $logs = [];
    $allAdmins = [];
    $allActions = [];
}

$page_title = 'System Audit Trail • Examify';
$body_class = 'audit-logs-page';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content audit-page-container">
    <div class="page-header audit-page-header">
        <div>
            <h1>Activity &amp; Record Audit Trail</h1>
            <p><?= $isAdminSuper ? 'Comprehensive institutional tracking of all teacher actions, exam controls, and question banks' : 'Your administrative activity log and record history' ?></p>
        </div>
        <?php if ($isAdminSuper): ?>
            <a href="manage-teachers.php" class="btn btn-secondary d-inline-flex items-center gap-1-5 text-sm" style="padding: 6px 14px;">
                <span class="material-symbols-outlined icon-sm">group</span> Manage Teachers
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Bar -->
    <?php
    $form_action = 'audit-logs.php';
    $show_year = true;
    $show_admin = $isAdminSuper;
    $admin_list = $allAdmins;
    $show_action = true;
    $action_list = $allActions;
    $inline_filters = true;
    $search_placeholder = 'Keywords, entity, IP...';
    $live_target = '#live-results';
    include __DIR__ . '/../components/filter-bar.php';
    ?>

    <!-- Audit Logs Table -->
    <div class="card audit-card" id="live-results">
        <div class="card-header-row d-flex justify-between items-center">
            <div class="card-title mb-0 text-lg">Activity Logs (<?= $total_logs_count ?> entries)</div>
            <span class="text-xs text-secondary d-inline-flex items-center gap-1">
                <span class="material-symbols-outlined" style="font-size: 15px;">unfold_more</span> Scrollable log
            </span>
        </div>

        <div class="table-wrap audit-table-wrap">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th style="width: 160px;">Timestamp</th>
                        <th>Instructor / Actor</th>
                        <th>Action</th>
                        <th>Target Entity</th>
                        <th>Activity Details</th>
                        <th style="width: 110px;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-text-secondary); padding: 32px;">No matching activity records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $row): ?>
                            <?php
                            $actionType = $row['action'];
                            $badgeClass = 'badge-inactive';
                            if (str_contains($actionType, 'create') || str_contains($actionType, 'init')) {
                                $badgeClass = 'badge-active';
                            } elseif (str_contains($actionType, 'retire') || str_contains($actionType, 'delete')) {
                                $badgeClass = 'badge-error';
                            } elseif (str_contains($actionType, 'start') || str_contains($actionType, 'approve') || str_contains($actionType, 'reactivate')) {
                                $badgeClass = 'badge-active';
                            }
                            $isRetired = ($row['current_admin_status'] === 'retired');
                            ?>
                            <tr>
                                <td style="font-size: 0.85rem; color: var(--color-text-secondary); white-space: nowrap;">
                                    <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?>
                                </td>
                                <td>
                                    <strong><?= e($row['admin_name']) ?></strong>
                                    <div>
                                        <span class="badge badge-inactive" style="font-size: 0.7rem;">
                                            <?= ucfirst(e($row['admin_role'])) ?>
                                        </span>
                                        <?php if ($isRetired): ?>
                                            <span class="badge badge-warning" style="font-size: 0.7rem; margin-left: 2px;">
                                                Retired
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>" style="text-transform: capitalize; font-size: 0.78rem;">
                                        <?= e(str_replace('_', ' ', $row['action'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($row['entity_type'])): ?>
                                        <code style="font-size: 0.82rem;"><?= e($row['entity_type']) ?><?= !empty($row['entity_id']) ? " #{$row['entity_id']}" : '' ?></code>
                                    <?php else: ?>
                                        <span style="color: var(--color-text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.88rem; max-width: 380px;">
                                    <?= e($row['details'] ?? '') ?>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--color-text-secondary); font-family: var(--font-mono);">
                                    <?= e($row['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        $total_items = $total_logs_count;
        include __DIR__ . '/../components/pagination.php';
        ?>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
