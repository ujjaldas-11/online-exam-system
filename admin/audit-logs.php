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

$where = [];
$params = [];

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
        LIMIT 200
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
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Activity &amp; Record Audit Trail</h1>
            <p><?= $isAdminSuper ? 'Comprehensive institutional tracking of all teacher actions, exam controls, and question banks' : 'Your administrative activity log and record history' ?></p>
        </div>
        <?php if ($isAdminSuper): ?>
            <a href="manage-teachers.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">group</span> Manage Teachers
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Card -->
    <div class="card" style="margin-bottom: 20px;">
        <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
            <?php if ($isAdminSuper): ?>
                <div class="form-group" style="margin-bottom: 0; min-width: 200px; flex: 1;">
                    <label style="font-size: 0.85rem;">Filter by Instructor</label>
                    <select name="admin_id" onchange="this.form.submit()">
                        <option value="0">-- All Instructors &amp; Admins --</option>
                        <?php foreach ($allAdmins as $adm): ?>
                            <option value="<?= $adm['id'] ?>" <?= $filterAdminId === (int)$adm['id'] ? 'selected' : '' ?>>
                                <?= e($adm['name']) ?> (<?= ucfirst(e($adm['role'])) ?><?= $adm['status'] === 'retired' ? ', Retired' : '' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 0; min-width: 180px; flex: 1;">
                <label style="font-size: 0.85rem;">Filter by Action</label>
                <select name="action" onchange="this.form.submit()">
                    <option value="">-- All Actions --</option>
                    <?php foreach ($allActions as $act): ?>
                        <option value="<?= e($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>>
                            <?= e(ucwords(str_replace('_', ' ', $act))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; min-width: 220px; flex: 2;">
                <label style="font-size: 0.85rem;">Search Details or IP</label>
                <input type="text" name="q" placeholder="Keywords, entity, IP..." value="<?= e($searchQuery) ?>">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 4px; padding: 10px 16px;">
                    <span class="material-symbols-outlined icon-sm">search</span> Filter
                </button>
                <a href="audit-logs.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 4px; padding: 10px 16px;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="card">
        <div class="card-title">Activity Logs (<?= count($logs) ?> entries)</div>

        <div class="table-wrap">
            <table>
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
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
