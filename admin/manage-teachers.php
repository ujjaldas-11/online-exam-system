<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

// Superadmin exclusive access
require_superadmin();

$message = '';
$message_type = '';

if (has_flash('success')) {
    $message = get_flash('success');
    $message_type = 'success';
} elseif (has_flash('error')) {
    $message = get_flash('error');
    $message_type = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['create_teacher'])) {
        $name = clean_input($_POST['name'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $department = clean_input($_POST['department'] ?? 'General');
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $message = "Please fill all required fields.";
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $message_type = 'error';
        } elseif (strlen($password) < 8) {
            $message = "Initial password must be at least 8 characters.";
            $message_type = 'error';
        } else {
            try {
                // Check if email is already in use by another admin or student
                $chkAdmin = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
                $chkAdmin->execute([$email]);
                $chkStudent = $pdo->prepare("SELECT id FROM students WHERE email = ?");
                $chkStudent->execute([$email]);

                if ($chkAdmin->fetch() || $chkStudent->fetch()) {
                    $message = "An account with this email address already exists.";
                    $message_type = 'error';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("
                        INSERT INTO admins (name, email, password, role, status, department, created_by)
                        VALUES (?, ?, ?, 'teacher', 'active', ?, ?)
                    ");
                    $ins->execute([$name, $email, $hashed, $department, $_SESSION['admin_id']]);
                    $newTeacherId = (int) $pdo->lastInsertId();

                    log_admin_action(
                        $pdo,
                        'create_teacher',
                        'admin',
                        $newTeacherId,
                        "Created instructor account for $name ($email, Dept: $department)"
                    );

                    $message = "Teacher account created successfully for $name. They can now log in using $email.";
                    $message_type = 'success';
                }
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to create teacher account.");
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['retire_teacher'])) {
        $teacher_id = int_param($_POST['teacher_id'] ?? 0);

        if ($teacher_id === (int) $_SESSION['admin_id']) {
            $message = "You cannot retire your own Superadmin account.";
            $message_type = 'error';
        } else {
            try {
                $chk = $pdo->prepare("SELECT id, name, role FROM admins WHERE id = ?");
                $chk->execute([$teacher_id]);
                $teacher = $chk->fetch();

                if (!$teacher) {
                    $message = "Teacher not found.";
                    $message_type = 'error';
                } elseif ($teacher['role'] === 'superadmin') {
                    $message = "Superadmin accounts cannot be marked as retired.";
                    $message_type = 'error';
                } else {
                    $up = $pdo->prepare("UPDATE admins SET status = 'retired' WHERE id = ?");
                    $up->execute([$teacher_id]);

                    log_admin_action(
                        $pdo,
                        'retire_teacher',
                        'admin',
                        $teacher_id,
                        "Retired instructor {$teacher['name']} (#$teacher_id). All created exams and questions remain preserved."
                    );

                    $message = "Instructor {$teacher['name']} has been marked as retired. Login access is disabled, while all their authored exams, questions, and records are permanently retained.";
                    $message_type = 'success';
                }
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to retire instructor.");
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['reactivate_teacher'])) {
        $teacher_id = int_param($_POST['teacher_id'] ?? 0);

        try {
            $chk = $pdo->prepare("SELECT id, name FROM admins WHERE id = ?");
            $chk->execute([$teacher_id]);
            $teacher = $chk->fetch();

            if ($teacher) {
                $up = $pdo->prepare("UPDATE admins SET status = 'active' WHERE id = ?");
                $up->execute([$teacher_id]);

                log_admin_action(
                    $pdo,
                    'reactivate_teacher',
                    'admin',
                    $teacher_id,
                    "Reactivated instructor {$teacher['name']} (#$teacher_id)."
                );

                $message = "Instructor {$teacher['name']} has been reactivated successfully.";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to reactivate instructor.");
            $message_type = 'error';
        }
    } elseif (isset($_POST['reset_password'])) {
        $teacher_id = int_param($_POST['teacher_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';

        if (strlen($new_password) < 8) {
            $message = "Password must be at least 8 characters.";
            $message_type = 'error';
        } else {
            try {
                $chk = $pdo->prepare("SELECT id, name FROM admins WHERE id = ?");
                $chk->execute([$teacher_id]);
                $teacher = $chk->fetch();

                if ($teacher) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $up = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $up->execute([$hashed, $teacher_id]);

                    log_admin_action(
                        $pdo,
                        'reset_teacher_password',
                        'admin',
                        $teacher_id,
                        "Reset password for instructor {$teacher['name']} (#$teacher_id)."
                    );

                    $message = "Password updated successfully for {$teacher['name']}.";
                    $message_type = 'success';
                }
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to update password.");
                $message_type = 'error';
            }
        }
    }
}

// Fetch Metrics & Instructors
try {
    $totalTeachers = (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'teacher'")->fetchColumn();
    $activeTeachers = (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'teacher' AND status = 'active'")->fetchColumn();
    $retiredTeachers = (int) $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'teacher' AND status = 'retired'")->fetchColumn();

    $query = "
        SELECT
            a.id, a.name, a.email, a.role, a.status, a.department, a.created_at,
            (SELECT COUNT(*) FROM subjects WHERE created_by = a.id) as subjects_count,
            (SELECT COUNT(*) FROM exams WHERE created_by = a.id) as exams_count,
            (SELECT COUNT(*) FROM questions WHERE created_by = a.id) as questions_count
        FROM admins a
        ORDER BY (a.role = 'superadmin') DESC, (a.status = 'active') DESC, a.id ASC
    ";
    $instructors = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    log_error("Failed fetching instructors", $e);
    $instructors = [];
    $totalTeachers = $activeTeachers = $retiredTeachers = 0;
}

$page_title = 'Manage Teachers & Instructors • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Instructor &amp; Teacher Management</h1>
            <p>Provision teacher accounts, assign department roles, audit authorship, and manage retirement records</p>
        </div>
        <a href="audit-logs.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">history</span> View Audit Logs
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-num"><?= $totalTeachers ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">group</span> Total Teachers
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-success);">
            <div class="stat-num" style="color: var(--color-success);"><?= $activeTeachers ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm" style="color: var(--color-success);">verified</span> Active Instructors
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-warning);">
            <div class="stat-num" style="color: var(--color-warning);"><?= $retiredTeachers ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm" style="color: var(--color-warning);">person_off</span> Retired (Records Kept)
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-num">
                <?php
                $totalRecords = 0;
                foreach ($instructors as $inst) {
                    if ($inst['role'] === 'teacher') {
                        $totalRecords += ($inst['exams_count'] + $inst['questions_count'] + $inst['subjects_count']);
                    }
                }
                echo $totalRecords;
                ?>
            </div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">auto_stories</span> Teacher Content Items
            </div>
        </div>
    </div>

    <!-- Create Teacher Form -->
    <div class="card">
        <div class="card-title">Provision New Teacher Account</div>
        <p style="color: var(--color-text-secondary); font-size: 0.9rem; margin-bottom: 16px;">
            Create individual instructor credentials. Each teacher's created exams, questions, and curriculum subjects will be permanently tracked and credited to their account.
        </p>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                <div class="form-group">
                    <label>Teacher Full Name</label>
                    <input type="text" name="name" required placeholder="e.g. Prof. Alan Turing" value="<?= e($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Teacher Email</label>
                    <input type="email" name="email" required placeholder="teacher@college.edu" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <select name="department" required>
                        <option value="BCA">BCA (Computer Applications)</option>
                        <option value="BBA">BBA (Business Administration)</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="General">General / Cross-Department</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Initial Password</label>
                    <input type="password" name="password" required placeholder="Minimum 8 characters" minlength="8">
                </div>
            </div>

            <div style="margin-top: 16px;">
                <button type="submit" name="create_teacher" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">person_add</span> Create Teacher Account
                </button>
            </div>
        </form>
    </div>

    <!-- Instructors Directory Table -->
    <div class="card">
        <div class="card-title">Instructors &amp; Staff Directory (<?= count($instructors) ?>)</div>

        <div style="margin-bottom: 10px;">
            <?php include '../components/searchbar.php'; ?>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Instructor &amp; Email</th>
                        <th>Role &amp; Department</th>
                        <th>Status</th>
                        <th>Records Authored</th>
                        <th>Date Joined</th>
                        <th style="text-align: right;">Administrative Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($instructors)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--color-text-secondary); padding: 32px;">No accounts registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($instructors as $row): ?>
                            <?php
                            $isSelf = ((int)$row['id'] === (int)$_SESSION['admin_id']);
                            $isRetired = ($row['status'] === 'retired');
                            $isSuper = ($row['role'] === 'superadmin');
                            ?>
                            <tr style="<?= $isRetired ? 'opacity: 0.75; background: rgba(241, 245, 249, 0.4);' : '' ?>">
                                <td>#<?= e((string)$row['id']) ?></td>
                                <td>
                                    <strong><?= e($row['name']) ?></strong>
                                    <?php if ($isSelf): ?>
                                        <span class="badge badge-inactive" style="font-size: 0.7rem; margin-left: 4px;">You</span>
                                    <?php endif; ?>
                                    <div style="font-size: 0.82rem; color: var(--color-text-secondary);"><?= e($row['email']) ?></div>
                                </td>
                                <td>
                                    <?php if ($isSuper): ?>
                                        <span class="badge badge-active" style="background: #1e3a8a; color: #93c5fd; border: 1px solid #3b82f6;">Superadmin</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Teacher</span>
                                    <?php endif; ?>
                                    <span style="font-size: 0.82rem; color: var(--color-text-secondary); margin-left: 4px;"><?= e($row['department'] ?: 'General') ?></span>
                                </td>
                                <td>
                                    <?php if ($isRetired): ?>
                                        <span class="badge badge-warning" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-symbols-outlined icon-xs">person_off</span> Retired
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-active" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-symbols-outlined icon-xs">check_circle</span> Active
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; display: flex; gap: 8px; flex-wrap: wrap;">
                                        <span title="Exams created"><strong><?= (int)$row['exams_count'] ?></strong> Exams</span>
                                        <span title="Questions added">• <strong><?= (int)$row['questions_count'] ?></strong> Qs</span>
                                        <span title="Subjects created">• <strong><?= (int)$row['subjects_count'] ?></strong> Subs</span>
                                    </div>
                                </td>
                                <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center; justify-content: flex-end; flex-wrap: wrap;">
                                        <!-- View Activity -->
                                        <a href="audit-logs.php?admin_id=<?= $row['id'] ?>" class="btn btn-secondary btn-sm" title="View Audit Trail" style="display: inline-flex; align-items: center; gap: 2px;">
                                            <span class="material-symbols-outlined icon-xs">history</span> Activity
                                        </a>

                                        <?php if (!$isSuper): ?>
                                            <!-- Reset Password Button / Trigger -->
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="promptResetPassword(<?= $row['id'] ?>, '<?= e(addslashes($row['name'])) ?>')" title="Reset Password">
                                                <span class="material-symbols-outlined icon-xs">key</span>
                                            </button>

                                            <?php if ($isRetired): ?>
                                                <!-- Reactivate Teacher -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Reactivate login access for <?= e(addslashes($row['name'])) ?>?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="teacher_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" name="reactivate_teacher" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                                        <span class="material-symbols-outlined icon-xs">replay</span> Reactivate
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- Retire Teacher -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Retire <?= e(addslashes($row['name'])) ?>? Login access will be disabled, but all their created exams, questions, and records will be PERMANENTLY KEPT in the system.');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="teacher_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" name="retire_teacher" class="btn btn-warning btn-sm" style="display: inline-flex; align-items: center; gap: 4px; background: #d97706; color: white;">
                                                        <span class="material-symbols-outlined icon-xs">person_off</span> Retire
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Password Reset Modal Form -->
<form id="resetPasswordForm" method="POST" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="teacher_id" id="modalTeacherId">
    <input type="hidden" name="new_password" id="modalNewPassword">
    <input type="hidden" name="reset_password" value="1">
</form>

<script>
function promptResetPassword(teacherId, teacherName) {
    const newPass = prompt(`Enter new password for ${teacherName} (minimum 8 characters):`);
    if (newPass === null) return;
    if (newPass.length < 8) {
        alert("Password must be at least 8 characters long.");
        return;
    }
    document.getElementById('modalTeacherId').value = teacherId;
    document.getElementById('modalNewPassword').value = newPass;
    document.getElementById('resetPasswordForm').submit();
}
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
