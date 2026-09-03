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
        $student_id = int_param($_POST['request_id']);
        $reviewer_id = (int) ($_SESSION['admin_id'] ?? 0);

        if ($_POST['action'] === 'approve') {
            try {
                $stmt = $pdo->prepare("SELECT name, roll_number, department FROM students WHERE id = ? AND status = 'pending'");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch();

                if ($student) {
                    $up = $pdo->prepare("UPDATE students SET status = 'active', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $up->execute([$reviewer_id, $student_id]);

                    log_admin_action(
                        $pdo,
                        'approve_student_reg',
                        'student',
                        $student_id,
                        "Approved registration for {$student['name']} (Roll: {$student['roll_number']}, Dept: {$student['department']})"
                    );
                    $message = "Student {$student['name']} ({$student['roll_number']}) approved and enrolled successfully!";
                } else {
                    $error = "Student request not found or already reviewed.";
                }
            } catch (PDOException $e) {
                $error = safe_db_error($e, "Failed to approve student registration.");
            }
        } elseif ($_POST['action'] === 'reject') {
            try {
                $stmt = $pdo->prepare("SELECT name, roll_number FROM students WHERE id = ? AND status = 'pending'");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch();

                if ($student) {
                    $up = $pdo->prepare("UPDATE students SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $up->execute([$reviewer_id, $student_id]);

                    log_admin_action(
                        $pdo,
                        'reject_student_reg',
                        'student',
                        $student_id,
                        "Rejected registration request for {$student['name']} (Roll: {$student['roll_number']})"
                    );
                    $message = "Registration request for {$student['name']} has been rejected.";
                } else {
                    $error = "Student request not found or already reviewed.";
                }
            } catch (PDOException $e) {
                $error = safe_db_error($e, "Failed to reject registration request.");
            }
        }
    }
}

try {
    $requests = $pdo->query("
        SELECT id, name, email, roll_number, department, semester, phone_number, gender, created_at
        FROM students
        WHERE status = 'pending'
        ORDER BY created_at ASC
    ")->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to fetch pending student registrations", $e);
    $requests = [];
}

$page_title = 'Manage Registrations • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Manage Student Registrations</h1>
            <p>Review and approve new student account requests for the examination portal.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Pending Registration Requests -->
    <div class="card">
        <div class="card-title">Pending Account Approvals (<?= count($requests) ?>)</div>

        <?php if (empty($requests)): ?>
            <p style="color: var(--color-text-secondary); padding: 16px 0;">No pending registration requests at this time.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Personal Details</th>
                            <th>Academic Info</th>
                            <th>Request Date</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><strong><?= e($req['roll_number']) ?></strong></td>
                                <td>
                                    <strong><?= e($req['name']) ?></strong><br>
                                    <span style="color: var(--color-text-secondary); font-size: 0.9em;">
                                        <?= e($req['email']) ?><br>
                                        <?= e($req['phone_number'] ?? '—') ?> • <?= ucfirst(e($req['gender'] ?? 'unknown')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 500;"><?= e($req['department']) ?></span><br>
                                    <span style="color: var(--color-text-secondary); font-size: 0.9em;">
                                        Semester <?= e((string)$req['semester']) ?>
                                    </span>
                                </td>
                                <td><?= date('d M Y, h:i A', strtotime($req['created_at'])) ?></td>
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
                                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 4px; background-color: var(--color-error, #dc2626); color: #ffffff;">
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
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
