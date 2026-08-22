<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['request_id']) && isset($_POST['action'])) {
        $request_id = int_param($_POST['request_id']);

        if ($_POST['action'] === 'approve') {
            try {
                $reqstmt = $pdo->prepare("SELECT * FROM profile_requests WHERE id = ?");
                $reqstmt->execute([$request_id]);
                $req = $reqstmt->fetch();

                if ($req && $req['status'] === 'pending') {
                    $pdo->beginTransaction();

                    // 1. Update the student
                    $updatestmt = $pdo->prepare("UPDATE students SET name = ?, roll_number = ?, department = ?, semester = ? WHERE id = ?");
                    $updateSuccess = $updatestmt->execute([$req['new_name'], $req['new_roll_no'], $req['new_department'], $req['new_semester'], $req['student_id']]);

                    // 2. Update the request status
                    $statusStmt = $pdo->prepare("UPDATE profile_requests SET status = 'approved' WHERE id = ?");
                    $statusSuccess = $statusStmt->execute([$request_id]);

                    if ($updateSuccess && $statusSuccess) {
                        $pdo->commit();
                        $message = "Student profile updated successfully!";
                    } else {
                        $pdo->rollBack();
                        $error = "Failed to update profile constraints.";
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = safe_db_error($e, "Failed to approve request.");
            }
        } elseif ($_POST['action'] === 'reject') {
            try {
                $pdo->prepare("UPDATE profile_requests SET status ='rejected' WHERE id = ?")->execute([$request_id]);
                $message = "Request has been rejected.";
            } catch (PDOException $e) {
                $error = safe_db_error($e, "Failed to reject request.");
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // Teacher One-Click Student Password Reset
        $roll_number = clean_input($_POST['student_roll'] ?? '');
        $new_password = $_POST['new_password'] ?? '';

        if (empty($roll_number) || strlen($new_password) < 6) {
            $error = "Please provide a valid Roll Number and a password with at least 6 characters.";
        } else {
            try {
                $checkStmt = $pdo->prepare("SELECT id, name FROM students WHERE roll_number = ?");
                $checkStmt->execute([$roll_number]);
                $student = $checkStmt->fetch();

                if (!$student) {
                    $error = "No student found with Roll Number: $roll_number";
                } else {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $upStmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
                    $upStmt->execute([$hashed, $student['id']]);
                    $message = "Password successfully reset for " . e($student['name']) . " ($roll_number)!";
                }
            } catch (PDOException $e) {
                $error = safe_db_error($e, "Failed to reset student password.");
            }
        }
    }
}

try {
    $requests = $pdo->query("
        SELECT r.*, s.name as old_name, s.roll_number as old_roll, s.department as old_dept, s.semester as old_sem
        FROM profile_requests r
        JOIN students s ON r.student_id = s.id
        WHERE r.status = 'pending'
        ORDER BY r.request_date ASC
    ")->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to fetch profile requests", $e);
    $requests = [];
}

$page_title = 'Manage Requests & Student Credentials • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Manage Student Requests & Credentials</h1>
            <p>Review profile updates and emergency password resets for lab surprise tests</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Pending Profile Requests -->
    <div class="card">
        <div class="card-title">Pending Profile Modification Requests (<?= count($requests) ?>)</div>

        <?php if (empty($requests)): ?>
            <p style="color: var(--color-text-secondary); padding: 16px 0;">No pending profile requests at this time.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Current Academic Details</th>
                            <th>Requested Changes</th>
                            <th>Request Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><strong><?= e($req['old_roll']) ?></strong></td>
                                <td>
                                    <strong><?= e($req['old_name']) ?></strong><br>
                                    <small style="color: var(--color-text-secondary);">
                                        <?= e($req['old_dept']) ?> • Sem <?= e((string)$req['old_sem']) ?>
                                    </small>
                                </td>
                                <td>
                                    <strong style="color: var(--color-primary);"><?= e($req['new_name']) ?></strong> (Roll: <?= e($req['new_roll_no']) ?>)<br>
                                    <small style="color: var(--color-primary);">
                                        <?= e($req['new_department']) ?> • Sem <?= e((string)$req['new_semester']) ?>
                                    </small>
                                </td>
                                <td><?= date('d M Y, h:i A', strtotime($req['request_date'])) ?></td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <form method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                                <span class="material-symbols-outlined icon-xs">check</span> Approve
                                            </button>
                                        </form>

                                        <form method="POST" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                                <span class="material-symbols-outlined icon-xs">close</span> Reject
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Teacher Emergency Password Reset Tool -->
    <div class="card">
        <div class="card-title" style="display: flex; align-items: center; gap: 8px;">
            <span class="material-symbols-outlined icon-md">lock_reset</span> Classroom Password Reset (Offline LAN Mode)
        </div>
        <p style="color: var(--color-text-secondary); font-size: 0.9rem; margin-bottom: 20px;">
            If a student forgets their password before a surprise test in the lab, reset their password instantly below.
        </p>

        <form method="POST" style="max-width: 500px;">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Student Roll Number</label>
                <input type="text" name="student_roll" required placeholder="e.g. BCA2401">
            </div>

            <div class="form-group">
                <label>New Temporary Password</label>
                <input type="text" name="new_password" required minlength="6" placeholder="e.g. password123">
            </div>

            <button type="submit" name="reset_password" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">lock_reset</span> Reset Student Password
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
