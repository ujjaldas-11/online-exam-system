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
$body_class = 'audit-logs-page';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<style>
/* Audit Logs Scrollable Container & Viewport Fit */
@media (min-width: 769px) and (min-height: 550px) {
    html, body.audit-logs-page {
        height: 100%;
        overflow: hidden;
    }

    .audit-page-container {
        height: 100vh;
        height: 100dvh;
        display: flex;
        flex-direction: column;
        padding-top: 64px !important;
        padding-bottom: 16px !important;
        box-sizing: border-box;
        overflow: hidden;
    }

    .audit-page-container .audit-page-header {
        margin-bottom: 10px;
        flex-shrink: 0;
    }

    .audit-page-container .audit-page-header h1 {
        font-size: 1.4rem;
        line-height: 1.2;
    }

    .audit-page-container .audit-page-header p {
        font-size: 0.85rem;
        margin-top: 2px;
    }

    .audit-page-container .filter-card {
        margin-bottom: 10px !important;
        padding: 10px 16px !important;
        flex-shrink: 0;
    }

    .audit-page-container .audit-card {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: column;
        margin-bottom: 0 !important;
        padding: 14px 18px !important;
        overflow: hidden;
    }

    .audit-page-container .audit-card .card-header-row {
        margin-bottom: 10px;
        flex-shrink: 0;
    }

    .audit-page-container .audit-table-wrap {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: auto;
    }
}

@media (max-width: 768px), (max-height: 549px) {
    .audit-page-container {
        padding-top: 64px !important;
        padding-bottom: 24px !important;
    }

    .audit-card {
        margin-bottom: 24px;
        padding: 16px;
    }

    .audit-table-wrap {
        max-height: 60vh;
        min-height: 280px;
        overflow-y: auto;
        overflow-x: auto;
    }
}

.audit-table-wrap {
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    position: relative;
    overscroll-behavior: contain;
}

.audit-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
}

.audit-table thead th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: #f8fafc;
    border-bottom: 1px solid var(--color-border);
    padding: 10px 14px;
    font-size: 0.82rem;
    white-space: nowrap;
}

.audit-table tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--color-border);
}

.audit-table tbody tr:last-child td {
    border-bottom: none;
}

/* Subtle modern scrollbars */
.audit-table-wrap::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.audit-table-wrap::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.audit-table-wrap::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.audit-table-wrap::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
.audit-table-wrap {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 #f1f5f9;
}
</style>

<div class="container main-content audit-page-container">
    <div class="page-header audit-page-header">
        <div>
            <h1>Activity &amp; Record Audit Trail</h1>
            <p><?= $isAdminSuper ? 'Comprehensive institutional tracking of all teacher actions, exam controls, and question banks' : 'Your administrative activity log and record history' ?></p>
        </div>
        <?php if ($isAdminSuper): ?>
            <a href="manage-teachers.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 0.85rem;">
                <span class="material-symbols-outlined icon-sm">group</span> Manage Teachers
            </a>
        <?php endif; ?>
    </div>

    <!-- Filter Card -->
    <div class="card filter-card">
        <form method="GET" action="" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
            <?php if ($isAdminSuper): ?>
                <div class="form-group" style="margin-bottom: 0; min-width: 140px; flex: 1.2;">
                    <label style="font-size: 0.8rem; margin-bottom: 3px; display: block; font-weight: 500;">Filter by Instructor</label>
                    <select name="admin_id" onchange="this.form.submit()" style="padding: 6px 10px; font-size: 0.85rem; height: 34px;">
                        <option value="0">-- All Instructors &amp; Admins --</option>
                        <?php foreach ($allAdmins as $adm): ?>
                            <option value="<?= $adm['id'] ?>" <?= $filterAdminId === (int)$adm['id'] ? 'selected' : '' ?>>
                                <?= e($adm['name']) ?> (<?= ucfirst(e($adm['role'])) ?><?= $adm['status'] === 'retired' ? ', Retired' : '' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 0; min-width: 130px; flex: 1;">
                <label style="font-size: 0.8rem; margin-bottom: 3px; display: block; font-weight: 500;">Filter by Action</label>
                <select name="action" onchange="this.form.submit()" style="padding: 6px 10px; font-size: 0.85rem; height: 34px;">
                    <option value="">-- All Actions --</option>
                    <?php foreach ($allActions as $act): ?>
                        <option value="<?= e($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>>
                            <?= e(ucwords(str_replace('_', ' ', $act))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; min-width: 160px; flex: 1.5;">
                <label style="font-size: 0.8rem; margin-bottom: 3px; display: block; font-weight: 500;">Search Details or IP</label>
                <input type="text" name="q" placeholder="Keywords, entity, IP..." value="<?= e($searchQuery) ?>" style="padding: 6px 10px; font-size: 0.85rem; height: 34px;">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 4px; padding: 0 14px; height: 34px; font-size: 0.85rem;">
                    <span class="material-symbols-outlined icon-sm">search</span> Filter
                </button>
                <a href="audit-logs.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 4px; padding: 0 14px; height: 34px; font-size: 0.85rem;">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Audit Logs Table -->
    <div class="card audit-card">
        <div class="card-header-row" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="card-title" style="margin-bottom: 0; font-size: 1.1rem;">Activity Logs (<?= count($logs) ?> entries)</div>
            <span style="font-size: 0.78rem; color: var(--color-text-secondary); display: inline-flex; align-items: center; gap: 4px;">
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
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
