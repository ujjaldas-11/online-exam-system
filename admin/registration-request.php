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
                $reqstmt = $pdo->prepare("SELECT * FROM registration_request WHERE id = ?");
                $reqstmt->execute([$request_id]);
                $req = $reqstmt->fetch();

                if ($req && $req['status'] === 'pending') {
                    $pdo->beginTransaction();

                    $insertStmt = $pdo->prepare("
                        INSERT INTO students (name, email, password, roll_number, department,semester, phone_number, gender)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    $updateSuccess = $insertStmt->execute([
                        $req['name'],
                        $req['email'],
                        $req['password'],
                        $req['roll_number'],
                        $req['department'],
                        $req['semester'],
                        $req['phone_number'],
                        $req['gender']
                    ]);

                    $statusStmt = $pdo->prepare("UPDATE registration_request SET status = 'approved' WHERE id = ?");
                    $statusSuccess = $statusStmt->execute([$request_id]);

                    if ($updateSuccess && $statusSuccess) {
                        $pdo->commit();
                        $message = "Student profile created and approved successfully!";
                    } else {
                        $pdo->rollBack();
                        $error = "Failed to create the student profile.";
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = safe_db_error($e, "Failed to approve request. Roll number or Email might already exist.");
            }
        } elseif ($_POST['action'] === 'reject') {
            try {
                $pdo->prepare("UPDATE registration_request SET status = 'rejected' WHERE id = ?")->execute([$request_id]);
                $message = "Registration request has been rejected.";
            } catch (PDOException $e) {
                $error = safe_db_error($e, "Failed to reject request.");
            }
        }
    }
}

try {
    $requests = $pdo->query("
        SELECT * FROM registration_request WHERE status = 'pending'")->fetchAll();

    //registration requests
    $pending_registration_requests_count = $pdo->query("SELECT COUNT(*) FROM registration_request WHERE status = 'pending'")->fetchColumn();

    //notification
    $pending_requests_count = $pdo->query("SELECT COUNT(*) FROM profile_requests WHERE status = 'pending'")->fetchColumn();



} catch (PDOException $e) {
    log_error("Failed to fetch registration requests", $e);
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
                                        <?= e($req['phone_number']) ?> • <?= ucfirst(e($req['gender'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 500;"><?= e($req['department']) ?></span><br>
                                    <span style="color: var(--color-text-secondary); font-size: 0.9em;">
                                        Semester <?= e((string)$req['semester']) ?>
                                    </span>
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

</div>

<?php include __DIR__ . '/../components/footer.php'; ?>
