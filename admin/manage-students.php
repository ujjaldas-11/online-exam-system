<?php

/**
 * Examify - Comprehensive Student Management Panel
 */

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../utils/auth.php';
require_once '../services/CurriculumService.php';
require_once '../services/CsvService.php';

require_admin();

$message = '';
$message_type = '';

if (has_flash('success')) {
    $message = get_flash('success');
    $message_type = 'success';
} elseif (has_flash('error')) {
    $message = get_flash('error');
    $message_type = 'error';
}

$isAdminSuper = is_superadmin();
$adminId = (int) ($_SESSION['admin_id'] ?? 0);


if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!is_superadmin()) {
        http_response_code(403);
        die("Access Denied: Superadmin privileges required to export student data.");
    }
    $q = clean_input($_GET['q'] ?? '');
    $dept = clean_input($_GET['department'] ?? '');
    $sem = int_param($_GET['semester'] ?? 0);
    $status = clean_input($_GET['status'] ?? '');

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = "(name LIKE ? OR roll_number LIKE ? OR email LIKE ? OR phone_number LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
    if ($dept !== '') {
        $where[] = "department = ?";
        $params[] = $dept;
    }
    if ($sem > 0 && $sem <= 8) {
        $where[] = "semester = ?";
        $params[] = $sem;
    }
    if ($status !== '' && in_array($status, ['active', 'blocked', 'pending', 'rejected'], true)) {
        $where[] = "status = ?";
        $params[] = $status;
    }

    $sql = "SELECT roll_number, name, email, phone_number, department, semester, gender, status, created_at FROM students";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY department ASC, semester ASC, roll_number ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exportRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = "students_roster_" . date('Y-m-d_His') . ".csv";
    $headers = ['Roll Number', 'Full Name', 'Email Address', 'Phone Number', 'Department', 'Semester', 'Gender', 'Status', 'Registration Date'];
    CsvService::export($filename, $headers, $exportRows, function (array $r): array {
        return [
            $r['roll_number'],
            $r['name'],
            $r['email'],
            $r['phone_number'] ?? '',
            $r['department'],
            $r['semester'],
            ucfirst($r['gender'] ?? 'N/A'),
            ucfirst($r['status']),
            $r['created_at']
        ];
    });
}


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();


    if (isset($_POST['add_student'])) {
        $name = clean_input($_POST['name'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $roll = strtoupper(clean_input($_POST['roll_number'] ?? ''));
        $dept = clean_input($_POST['department'] ?? '');
        $sem = int_param($_POST['semester'] ?? 0);
        $phone = clean_input($_POST['phone_number'] ?? '');
        $gender = clean_input($_POST['gender'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($email) || empty($roll) || empty($dept) || $sem < 1 || $sem > 8 || empty($password)) {
            $message = "Please fill in all required fields.";
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid student email address.";
            $message_type = 'error';
        } elseif (strlen($password) < 6) {
            $message = "Password must be at least 6 characters long.";
            $message_type = 'error';
        } else {
            try {
                $chk = $pdo->prepare("SELECT id FROM students WHERE email = ? OR roll_number = ? LIMIT 1");
                $chk->execute([$email, $roll]);
                if ($chk->fetch()) {
                    $message = "A student with this email address or roll number already exists.";
                    $message_type = 'error';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("
                        INSERT INTO students (name, email, password, roll_number, department, semester, phone_number, gender, status, reviewed_by, reviewed_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                    ");
                    $ins->execute([$name, $email, $hashed, $roll, $dept, $sem, $phone ?: null, $gender ?: null, $adminId]);
                    $newId = (int) $pdo->lastInsertId();

                    log_admin_action($pdo, 'create_student', 'student', $newId, "Enrolled student $name (Roll: $roll, Dept: $dept, Sem $sem)");

                    set_flash('success', "Student $name ($roll) enrolled successfully!");
                    redirect('manage-students.php');
                }
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to create student account.");
                $message_type = 'error';
            }
        }
    }


    elseif (isset($_POST['edit_student'])) {
        $studentId = int_param($_POST['student_id'] ?? 0);
        $name = clean_input($_POST['name'] ?? '');
        $email = clean_input($_POST['email'] ?? '');
        $roll = strtoupper(clean_input($_POST['roll_number'] ?? ''));
        $dept = clean_input($_POST['department'] ?? '');
        $sem = int_param($_POST['semester'] ?? 0);
        $phone = clean_input($_POST['phone_number'] ?? '');
        $gender = clean_input($_POST['gender'] ?? '');
        $status = clean_input($_POST['status'] ?? 'active');

        if ($studentId <= 0 || empty($name) || empty($email) || empty($roll) || empty($dept) || $sem < 1 || $sem > 8) {
            $message = "Please verify all required student details.";
            $message_type = 'error';
        } elseif (!in_array($status, ['active', 'blocked', 'pending', 'rejected'], true)) {
            $message = "Invalid student status specified.";
            $message_type = 'error';
        } else {
            try {

            $chk = $pdo->prepare("SELECT id FROM students WHERE (email = ? OR roll_number = ?) AND id != ? LIMIT 1");
                $chk->execute([$email, $roll, $studentId]);
                if ($chk->fetch()) {
                    $message = "Another student is already using this email or roll number.";
                    $message_type = 'error';
                } else {
                    $up = $pdo->prepare("
                        UPDATE students
                        SET name = ?, email = ?, roll_number = ?, department = ?, semester = ?, phone_number = ?, gender = ?, status = ?
                        WHERE id = ?
                    ");
                    $up->execute([$name, $email, $roll, $dept, $sem, $phone ?: null, $gender ?: null, $status, $studentId]);

                    // If student was blocked or rejected, immediately terminate active session
                    if (in_array($status, ['blocked', 'rejected'], true)) {
                        clear_active_session($pdo, 'student', $studentId, null, true);
                    }

                    log_admin_action($pdo, 'edit_student', 'student', $studentId, "Updated student $name (Roll: $roll, Status: $status)");

                    set_flash('success', "Student $name ($roll) updated successfully!");
                    redirect('manage-students.php');
                }
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to update student.");
                $message_type = 'error';
            }
        }
    }

    // 3. TOGGLE STATUS (BLOCK / UNBLOCK)
    elseif (isset($_POST['toggle_status'])) {
        $studentId = int_param($_POST['student_id'] ?? 0);
        $newStatus = clean_input($_POST['target_status'] ?? '');

        if ($studentId > 0 && in_array($newStatus, ['active', 'blocked'], true)) {
            try {
                $stmt = $pdo->prepare("SELECT name, roll_number FROM students WHERE id = ?");
                $stmt->execute([$studentId]);
                $st = $stmt->fetch();

                if ($st) {
                    $up = $pdo->prepare("UPDATE students SET status = ? WHERE id = ?");
                    $up->execute([$newStatus, $studentId]);

                    if ($newStatus === 'blocked') {
                        clear_active_session($pdo, 'student', $studentId, null, true);
                    }

                    $actionVerb = ($newStatus === 'blocked') ? 'Blocked' : 'Unblocked/Activated';
                    log_admin_action($pdo, ($newStatus === 'blocked' ? 'block_student' : 'unblock_student'), 'student', $studentId, "$actionVerb student {$st['name']} ({$st['roll_number']})");

                    set_flash('success', "Student {$st['name']} has been $actionVerb.");
                    redirect('manage-students.php');
                }
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to update student status.");
                $message_type = 'error';
            }
        }
    }

    // 4. RESET PASSWORD DIRECTLY
    elseif (isset($_POST['reset_password'])) {
        $studentId = int_param($_POST['student_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? '';

        if ($studentId <= 0 || strlen($newPassword) < 6) {
            $message = "New password must be at least 6 characters long.";
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT name, roll_number FROM students WHERE id = ?");
                $stmt->execute([$studentId]);
                $st = $stmt->fetch();

                if ($st) {
                    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                    $up = $pdo->prepare("UPDATE students SET password = ?, active_session_id = NULL WHERE id = ?");
                    $up->execute([$hashed, $studentId]);

                    log_admin_action($pdo, 'reset_student_password', 'student', $studentId, "Reset password for student {$st['name']} ({$st['roll_number']})");

                    set_flash('success', "Password for {$st['name']} ({$st['roll_number']}) was successfully reset!");
                    redirect('manage-students.php');
                }
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to reset password.");
                $message_type = 'error';
            }
        }
    }

    // 5. DELETE STUDENT RECORD
    elseif (isset($_POST['delete_student'])) {
        if (!is_superadmin()) {
            http_response_code(403);
            die("Access Denied: Superadmin privileges required to delete student records.");
        }
        $studentId = int_param($_POST['student_id'] ?? 0);

        if ($studentId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT name, roll_number FROM students WHERE id = ?");
                $stmt->execute([$studentId]);
                $st = $stmt->fetch();

                if ($st) {
                    // Delete student; cascade handles answers and attempts
                    $del = $pdo->prepare("DELETE FROM students WHERE id = ?");
                    $del->execute([$studentId]);

                    log_admin_action($pdo, 'delete_student', 'student', $studentId, "Deleted student record {$st['name']} ({$st['roll_number']})");

                    set_flash('success', "Student record for {$st['name']} ({$st['roll_number']}) was permanently deleted.");
                    redirect('manage-students.php');
                }
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Cannot delete student with active exam records.");
                $message_type = 'error';
            }
        }
    }

    // 6. BULK PROMOTE COHORT (BATCH ADVANCEMENT)
    elseif (isset($_POST['bulk_promote_cohort'])) {
        if (!is_superadmin()) {
            http_response_code(403);
            die("Access Denied: Superadmin privileges required for cohort bulk promotion.");
        }
        $sourceDept = clean_input($_POST['source_dept'] ?? '');
        $fromSem = int_param($_POST['from_semester'] ?? 0);
        $toSem = int_param($_POST['to_semester'] ?? 0);
        $onlyActive = isset($_POST['only_active']);

        if (empty($sourceDept) || $fromSem < 1 || $fromSem > 8 || $toSem < 1 || $toSem > 8) {
            $message = "Please select valid department and semesters for promotion.";
            $message_type = 'error';
        } elseif ($toSem <= $fromSem) {
            $message = "Target semester must be higher than current semester.";
            $message_type = 'error';
        } else {
            try {
                $statusSql = $onlyActive ? " AND status = 'active'" : "";
                
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE department = ? AND semester = ? $statusSql");
                $countStmt->execute([$sourceDept, $fromSem]);
                $matchCount = (int) $countStmt->fetchColumn();

                if ($matchCount === 0) {
                    $message = "No matching students found in $sourceDept Semester $fromSem to promote.";
                    $message_type = 'error';
                } else {
                    $upStmt = $pdo->prepare("UPDATE students SET semester = ? WHERE department = ? AND semester = ? $statusSql");
                    $upStmt->execute([$toSem, $sourceDept, $fromSem]);

                    log_admin_action(
                        $pdo,
                        'bulk_promote_cohort',
                        'student',
                        0,
                        "Bulk promoted $matchCount students in $sourceDept from Semester $fromSem to Semester $toSem"
                    );

                    set_flash('success', "Successfully promoted $matchCount student(s) in $sourceDept from Semester $fromSem to Semester $toSem!");
                    redirect('manage-students.php?department=' . urlencode($sourceDept) . '&semester=' . $toSem);
                }
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to bulk promote students.");
                $message_type = 'error';
            }
        }
    }

    // 7. BULK PROMOTE SELECTED STUDENTS (+1 SEMESTER)
    elseif (isset($_POST['bulk_promote_selected'])) {
        if (!is_superadmin()) {
            http_response_code(403);
            die("Access Denied: Superadmin privileges required for student bulk promotion.");
        }
        $rawIds = $_POST['selected_student_ids'] ?? '';
        $idList = is_array($rawIds) ? $rawIds : explode(',', (string) $rawIds);
        $studentIds = array_filter(array_map('intval', $idList), fn($id) => $id > 0);

        if (empty($studentIds)) {
            $message = "No students selected for promotion.";
            $message_type = 'error';
        } else {
            try {
                $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
                $stmt = $pdo->prepare("SELECT id, name, roll_number, semester FROM students WHERE id IN ($placeholders)");
                $stmt->execute(array_values($studentIds));
                $selectedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $promotedCount = 0;
                $cappedCount = 0;

                $upStmt = $pdo->prepare("UPDATE students SET semester = semester + 1 WHERE id = ?");

                $pdo->beginTransaction();
                foreach ($selectedStudents as $st) {
                    if ((int)$st['semester'] < 8) {
                        $upStmt->execute([(int)$st['id']]);
                        $promotedCount++;
                    } else {
                        $cappedCount++;
                    }
                }
                $pdo->commit();

                log_admin_action(
                    $pdo,
                    'bulk_promote_selected',
                    'student',
                    0,
                    "Bulk promoted $promotedCount selected students (+1 semester). $cappedCount student(s) already in Semester 8."
                );

                $msg = "Successfully promoted $promotedCount selected student(s) to their next semester!";
                if ($cappedCount > 0) {
                    $msg .= " ($cappedCount student(s) already in Semester 8 were retained at maximum semester).";
                }
                set_flash('success', $msg);
                redirect('manage-students.php');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = safe_db_error($e, "Failed to promote selected students.");
                $message_type = 'error';
            }
        }
    }
}

// --- Data Fetching & Filter Query ---
$filterQ = clean_input($_GET['q'] ?? '');
$filterDept = clean_input($_GET['department'] ?? '');
$filterSem = int_param($_GET['semester'] ?? 0);
$filterStatus = clean_input($_GET['status'] ?? '');

$queryWhere = [];
$queryParams = [];

if ($filterQ !== '') {
    $queryWhere[] = "(s.name LIKE ? OR s.roll_number LIKE ? OR s.email LIKE ? OR s.phone_number LIKE ?)";
    $queryParams[] = "%$filterQ%";
    $queryParams[] = "%$filterQ%";
    $queryParams[] = "%$filterQ%";
    $queryParams[] = "%$filterQ%";
}
if ($filterDept !== '') {
    $queryWhere[] = "s.department = ?";
    $queryParams[] = $filterDept;
}
if ($filterSem > 0 && $filterSem <= 8) {
    $queryWhere[] = "s.semester = ?";
    $queryParams[] = $filterSem;
}
if ($filterStatus !== '' && in_array($filterStatus, ['active', 'blocked', 'pending', 'rejected'], true)) {
    $queryWhere[] = "s.status = ?";
    $queryParams[] = $filterStatus;
}

$whereClause = !empty($queryWhere) ? "WHERE " . implode(" AND ", $queryWhere) : "";

try {
    // Stats
    $totalCount = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $activeCount = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
    $blockedCount = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'blocked'")->fetchColumn();
    $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'pending'")->fetchColumn();

    // Total count for current filter
    $countSql = "SELECT COUNT(*) FROM students s $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($queryParams);
    $total_students_count = (int) $countStmt->fetchColumn();

    // Pagination parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 50;
    $offset = ($page - 1) * $per_page;

    // Roster query
    $sql = "
        SELECT
            s.*,
            (SELECT COUNT(*) FROM exam_attempts WHERE student_id = s.id) as attempts_count,
            (SELECT MAX(started_at) FROM exam_attempts WHERE student_id = s.id) as last_exam_at
        FROM students s
        $whereClause
        ORDER BY s.id DESC
        LIMIT $per_page OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    log_error("Failed to load students roster", $e);
    $students = [];
    $totalCount = 0;
    $activeCount = 0;
    $blockedCount = 0;
    $pendingCount = 0;
}

$page_title = 'Student Management • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>Student Management</h1>
            <p>Maintain enrolled student roster, account credentials, and examination access</p>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php
            $exportParams = $_GET;
            $exportParams['export'] = 'csv';
            $exportUrl = 'manage-students.php?' . http_build_query($exportParams);
            ?>

            <?php if ($isAdminSuper): ?>
                <a href="import-students.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">group</span>Bulk Student Insert
                </a>
                <button type="button" class="btn btn-primary" onclick="openBulkPromoteModal()" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">upgrade</span> Bulk Semester Promote
                </button>
                <button type="button" class="btn btn-primary" onclick="openAddStudentModal()" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">person_add</span> Add New Student
                </button>
            <?php endif; ?>
            <a href="<?= e($exportUrl) ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">download</span> Export CSV
            </a>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="stats" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <div class="stat-card">
            <div class="stat-num"><?= $totalCount ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">group</span> Total Students
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-success, #10b981);">
            <div class="stat-num" style="color: var(--color-success, #10b981);"><?= $activeCount ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm" style="color: var(--color-success, #10b981);">check_circle</span> Active &amp; Enrolled
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #ef4444;">
            <div class="stat-num" style="color: #ef4444;"><?= $blockedCount ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm" style="color: #ef4444;">block</span> Blocked / Suspended
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid #f59e0b;">
            <div class="stat-num" style="color: #f59e0b;"><?= $pendingCount ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm" style="color: #f59e0b;">pending</span> Pending Verification
            </div>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card" style="margin-bottom: 24px;">
        <form method="GET" action="manage-students.php" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0; flex: 2; min-width: 220px;">
                <label>Search Roster</label>
                <input type="text" name="q" value="<?= e($filterQ) ?>" placeholder="Search by name, roll no, email, or phone...">
            </div>

            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 140px;">
                <label>Department</label>
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach (CurriculumService::getDepartments($pdo) as $d): ?>
                        <option value="<?= e($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 130px;">
                <label>Semester</label>
                <select name="semester">
                    <option value="">All Semesters</option>
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= $filterSem === $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 130px;">
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="blocked" <?= $filterStatus === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px; align-items: center;">
                <button type="submit" class="btn btn-primary" style="height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">filter_alt</span> Filter
                </button>

                <a href="manage-students.php" class="btn btn-secondary" style="height: 38px; display: inline-flex; align-items: center; gap: 4px;" title="Reset filters">
                    <span class="material-symbols-outlined icon-sm">restart_alt</span>
                </a>
            </div>
        </form>
    </div>

    <!-- Student Roster Table -->
    <div class="card" style="overflow: hidden; max-width: 100%;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
            <div class="card-title" style="margin-bottom: 0;">
                Enrolled Students (<?= count($students) ?> <?= count($students) === 1 ? 'record' : 'records' ?>)
            </div>
            <?php if ($pendingCount > 0): ?>
                <a href="registration-request.php" class="badge badge-warning" style="text-decoration: none; padding: 6px 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <span class="material-symbols-outlined icon-xs">notifications_active</span> <?= $pendingCount ?> pending approval
                </a>
            <?php endif; ?>
        </div>

        <div class="table-responsive" style="width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="table" style="width: 100%; min-width: 820px; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="width: 38px; text-align: center;">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" title="Select all visible students">
                        </th>
                        <th style="width: 110px; white-space: nowrap;">Roll No</th>
                        <th>Student Name</th>
                        <th style="white-space: nowrap;">Dept</th>
                        <th style="white-space: nowrap;">Sem</th>
                        <th style="min-width: 160px;">Contact</th>
                        <th style="white-space: nowrap; text-align: center;">Exams</th>
                        <th style="white-space: nowrap; text-align: center;">Status</th>
                        <th style="text-align: right; width: 140px; white-space: nowrap;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px; color: var(--color-text-secondary);">
                                <span class="material-symbols-outlined" style="font-size: 48px; opacity: 0.35; display: block; margin-bottom: 8px;">search_off</span>
                                No student records found matching your filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $st): ?>
                            <tr>
                                <td style="text-align: center; width: 38px;">
                                    <input type="checkbox" name="student_ids[]" value="<?= (int) $st['id'] ?>" class="student-select-cb" onchange="updateBatchBar()" data-name="<?= e($st['name']) ?>" data-sem="<?= (int) $st['semester'] ?>">
                                </td>
                                <td style="white-space: nowrap;">
                                    <span style="font-family: monospace; font-weight: 700; background: #e2e8f0; color: #0f172a; padding: 3px 8px; border-radius: 4px; font-size: 0.82rem;">
                                        <?= e($st['roll_number']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= e($st['name']) ?></strong>
                                    <?php if ($st['gender']): ?>
                                        <small style="color: var(--color-text-muted); margin-left: 4px; text-transform: capitalize;">(<?= e($st['gender']) ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <span class="badge" style="background: #e0f2fe; color: #0369a1; font-weight: 600;">
                                        <?= e($st['department']) ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">Sem <?= (int) $st['semester'] ?></td>
                                <td style="max-width: 220px;">
                                    <div style="font-size: 0.85rem; line-height: 1.3;">
                                        <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px;">
                                            <a href="mailto:<?= e($st['email']) ?>" style="color: inherit;" title="<?= e($st['email']) ?>"><?= e($st['email']) ?></a>
                                        </div>
                                        <?php if (!empty($st['phone_number'])): ?>
                                            <div style="color: var(--color-text-muted); font-size: 0.78rem; white-space: nowrap;">📞 <?= e($st['phone_number']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="white-space: nowrap; text-align: center;">
                                    <?php if ((int) $st['attempts_count'] > 0): ?>
                                        <a href="results.php?search=<?= urlencode($st['roll_number']) ?>" class="badge badge-active" style="text-decoration: none;" title="View Exam Results">
                                            <?= (int) $st['attempts_count'] ?> taken
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--color-text-muted); font-size: 0.82rem;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space: nowrap; text-align: center;">
                                    <?php if ($st['status'] === 'active'): ?>
                                        <span class="badge badge-active" style="display: inline-flex; align-items: center; gap: 3px;">
                                            <span class="material-symbols-outlined icon-xs">check_circle</span> Active
                                        </span>
                                    <?php elseif ($st['status'] === 'blocked'): ?>
                                        <span class="badge badge-inactive" style="display: inline-flex; align-items: center; gap: 3px; background: #fee2e2; color: #b91c1c;">
                                            <span class="material-symbols-outlined icon-xs">block</span> Blocked
                                        </span>
                                    <?php elseif ($st['status'] === 'pending'): ?>
                                        <span class="badge badge-warning" style="display: inline-flex; align-items: center; gap: 3px;">
                                            <span class="material-symbols-outlined icon-xs">schedule</span> Pending
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #f1f5f9; color: #64748b;">
                                            <?= e(ucfirst($st['status'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 4px; align-items: center; justify-content: flex-end;">
                                        <!-- Edit Modal Trigger -->
                                        <button type="button" class="btn btn-secondary btn-sm" onclick='openEditStudentModal(<?= json_encode($st, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' title="Edit Details">
                                            <span class="material-symbols-outlined icon-xs">edit</span>
                                        </button>

                                        <!-- Reset Password Trigger -->
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="openResetPasswordModal(<?= (int)$st['id'] ?>, '<?= e(addslashes($st['name'])) ?>', '<?= e(addslashes($st['roll_number'])) ?>')" title="Reset Password">
                                            <span class="material-symbols-outlined icon-xs">key</span>
                                        </button>

                                        <!-- Quick Block / Unblock Toggle -->
                                        <?php if ($st['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;" data-confirm="Block student <?= e($st['name']) ?> (<?= e($st['roll_number']) ?>)? Login and exam access will be suspended immediately." data-confirm-title="Block Student" data-confirm-btn="Block Student">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="student_id" value="<?= (int) $st['id'] ?>">
                                                <input type="hidden" name="target_status" value="blocked">
                                                <button type="submit" name="toggle_status" class="btn btn-secondary btn-sm" style="color: #dc2626;" title="Block Account">
                                                    <span class="material-symbols-outlined icon-xs">block</span>
                                                </button>
                                            </form>
                                        <?php elseif ($st['status'] === 'blocked'): ?>
                                            <form method="POST" style="display: inline;" data-confirm="Unblock student <?= e($st['name']) ?>?" data-confirm-title="Unblock Student" data-confirm-btn="Unblock" data-confirm-danger="false">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="student_id" value="<?= (int) $st['id'] ?>">
                                                <input type="hidden" name="target_status" value="active">
                                                <button type="submit" name="toggle_status" class="btn btn-secondary btn-sm" style="color: #10b981;" title="Activate / Unblock Account">
                                                    <span class="material-symbols-outlined icon-xs">replay</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($isAdminSuper): ?>
                                            <!-- Delete Student Record (Superadmin Only) -->
                                            <form method="POST" style="display: inline;" data-confirm="Permanently delete <?= e($st['name']) ?> (<?= e($st['roll_number']) ?>)? This will purge all their answers and scores." data-confirm-title="Delete Student Record" data-confirm-btn="Delete Permanently">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="student_id" value="<?= (int) $st['id'] ?>">
                                                <button type="submit" name="delete_student" class="btn btn-secondary btn-sm" style="color: #991b1b;" title="Permanently Delete">
                                                    <span class="material-symbols-outlined icon-xs">delete</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        $total_items = $total_students_count;
        include __DIR__ . '/../components/pagination.php';
        ?>
    </div>
</div>

<!-- Floating Batch Action Bar -->
<div id="batchActionBar" class="batch-action-bar" style="display: none;">
    <div class="batch-bar-count">
        <span id="batchCountBadge">0</span> student(s) selected
    </div>
    <div class="batch-bar-actions">
        <form method="POST" id="batchPromoteForm" style="display: inline;" onsubmit="return confirm('Promote all selected students to their next semester (+1)?');">
            <?= csrf_field() ?>
            <input type="hidden" name="selected_student_ids" id="batchSelectedIds">
            <button type="submit" name="bulk_promote_selected" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                <span class="material-symbols-outlined icon-xs">upgrade</span> Promote Selected (+1 Sem)
            </button>
        </form>
        <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()" style="color: #cbd5e1; border-color: #475569;">
            Deselect
        </button>
    </div>
</div>

<!-- ================= BULK PROMOTE MODAL ================= -->
<div id="bulkPromoteModal" class="admin-modal-overlay">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><span class="material-symbols-outlined">upgrade</span> Bulk Promote Cohort</h3>
            <button type="button" class="admin-modal-close" onclick="closeBulkPromoteModal()">&times;</button>
        </div>
        <form method="POST" onsubmit="return confirmBulkPromote();">
            <?= csrf_field() ?>
            <div class="admin-modal-body">
                <p style="font-size: 0.9rem; color: var(--color-text-secondary); margin-top: 0; line-height: 1.5;">
                    Advance an entire student cohort from one semester to the next at the beginning of a new academic term.
                </p>

                <div class="form-group">
                    <label>Academic Department *</label>
                    <select name="source_dept" id="promote_dept" required onchange="updatePromotePreview()">
                        <option value="">Select Department</option>
                        <?php foreach (CurriculumService::getDepartments($pdo) as $d): ?>
                            <option value="<?= e($d) ?>"><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="auth-row-2">
                    <div class="form-group">
                        <label>From Current Semester *</label>
                        <select name="from_semester" id="promote_from_sem" required onchange="handleFromSemChange(this.value)">
                            <option value="">Select Current Sem</option>
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <option value="<?= $i ?>">Semester <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Promote To Semester *</label>
                        <select name="to_semester" id="promote_to_sem" required onchange="updatePromotePreview()">
                            <option value="">Select Target Sem</option>
                            <?php for ($i = 2; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>">Semester <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer;">
                        <input type="checkbox" name="only_active" value="1" checked style="width: auto; height: auto;">
                        <span>Only promote <strong>Active</strong> students (skips suspended/blocked accounts)</span>
                    </label>
                </div>

                <div id="promote_preview_box" style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 14px; font-size: 0.85rem; color: #334155; margin-top: 14px; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined icon-sm" style="color: #0284c7;">info</span>
                    <span id="promote_preview_text">Select a department and semester to configure promotion.</span>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBulkPromoteModal()">Cancel</button>
                <button type="submit" name="bulk_promote_cohort" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-xs">upgrade</span> Execute Promotion
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= ADD STUDENT MODAL ================= -->
<div id="addStudentModal" class="admin-modal-overlay">
    <div class="admin-modal-card admin-modal-card-wide">
        <div class="admin-modal-header">
            <h3><span class="material-symbols-outlined">person_add</span> Enroll New Student</h3>
            <button type="button" class="admin-modal-close" onclick="closeAddStudentModal()">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="admin-modal-body">
                <div class="auth-row-2">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Rahul Sharma">
                    </div>
                    <div class="form-group">
                        <label>Student Email *</label>
                        <input type="email" name="email" required placeholder="student@college.edu">
                    </div>
                </div>

                <div class="auth-row-2">
                    <div class="form-group">
                        <label>Roll Number / ID *</label>
                        <input type="text" name="roll_number" required style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()" placeholder="e.g. B26BCA01">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)" placeholder="10 digit number">
                    </div>
                </div>

                <div class="auth-row-3">
                    <div class="form-group">
                        <label>Department *</label>
                        <select name="department" required>
                            <option value="">Select Department</option>
                            <?php foreach (CurriculumService::getDepartments($pdo) as $d): ?>
                                <option value="<?= e($d) ?>"><?= e($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Semester *</label>
                        <select name="semester" required>
                            <option value="">Select Semester</option>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>">Semester <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="others">Others</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Initial Login Password * (Min 6 characters)</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" required minlength="6" placeholder="Initial password for student">
                        <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddStudentModal()">Cancel</button>
                <button type="submit" name="add_student" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-xs">check_circle</span> Enroll Student
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= EDIT STUDENT MODAL ================= -->
<div id="editStudentModal" class="admin-modal-overlay">
    <div class="admin-modal-card admin-modal-card-wide">
        <div class="admin-modal-header">
            <h3><span class="material-symbols-outlined">edit</span> Edit Student Profile</h3>
            <button type="button" class="admin-modal-close" onclick="closeEditStudentModal()">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="student_id" id="edit_student_id">

            <div class="admin-modal-body">
                <div class="auth-row-2">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label>Student Email *</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                </div>

                <div class="auth-row-2">
                    <div class="form-group">
                        <label>Roll Number *</label>
                        <input type="text" name="roll_number" id="edit_roll" required style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase()">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" id="edit_phone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10">
                    </div>
                </div>

                <div class="auth-row-3">
                    <div class="form-group">
                        <label>Department *</label>
                        <select name="department" id="edit_department" required>
                            <?php foreach (CurriculumService::getDepartments($pdo) as $d): ?>
                                <option value="<?= e($d) ?>"><?= e($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Semester *</label>
                        <select name="semester" id="edit_semester" required>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>">Semester <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" id="edit_gender">
                            <option value="">N/A</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="others">Others</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Account Status *</label>
                    <select name="status" id="edit_status" required>
                        <option value="active">Active (Permitted to log in and take tests)</option>
                        <option value="blocked">Blocked (Login and exam access suspended)</option>
                        <option value="pending">Pending Verification</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditStudentModal()">Cancel</button>
                <button type="submit" name="edit_student" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-xs">save</span> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= RESET PASSWORD MODAL ================= -->
<div id="resetPasswordModal" class="admin-modal-overlay">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><span class="material-symbols-outlined">key</span> Reset Student Password</h3>
            <button type="button" class="admin-modal-close" onclick="closeResetPasswordModal()">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="student_id" id="reset_student_id">

            <div class="admin-modal-body">
                <p style="font-size: 0.92rem; color: var(--color-text-secondary); margin-top: 0;">
                    Resetting password for: <strong id="reset_student_label" style="color: var(--color-dark);"></strong>
                </p>

                <div class="form-group">
                    <label>New Password * (Min 6 characters)</label>
                    <div class="password-wrapper">
                        <input type="password" name="new_password" id="reset_new_password" required minlength="6" placeholder="Enter new password">
                        <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                </div>
                <small style="color: var(--color-text-muted);">
                    Note: This will immediately clear any active sessions and require the student to log in with this new password.
                </small>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
                <button type="submit" name="reset_password" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-xs">lock_reset</span> Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddStudentModal() {
    document.getElementById('addStudentModal').classList.add('active');
}
function closeAddStudentModal() {
    document.getElementById('addStudentModal').classList.remove('active');
}

function openEditStudentModal(student) {
    document.getElementById('edit_student_id').value = student.id;
    document.getElementById('edit_name').value = student.name;
    document.getElementById('edit_email').value = student.email;
    document.getElementById('edit_roll').value = student.roll_number;
    document.getElementById('edit_department').value = student.department;
    document.getElementById('edit_semester').value = student.semester;
    document.getElementById('edit_phone').value = student.phone_number || '';
    document.getElementById('edit_gender').value = student.gender || '';
    document.getElementById('edit_status').value = student.status || 'active';

    document.getElementById('editStudentModal').classList.add('active');
}
function closeEditStudentModal() {
    document.getElementById('editStudentModal').classList.remove('active');
}

function openResetPasswordModal(studentId, studentName, rollNumber) {
    document.getElementById('reset_student_id').value = studentId;
    document.getElementById('reset_student_label').innerText = `${studentName} (${rollNumber})`;
    document.getElementById('reset_new_password').value = '';
    document.getElementById('resetPasswordModal').classList.add('active');
}
function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').classList.remove('active');
}

// Bulk Promote Modal Controls
function openBulkPromoteModal() {
    document.getElementById('bulkPromoteModal').classList.add('active');
    updatePromotePreview();
}
function closeBulkPromoteModal() {
    document.getElementById('bulkPromoteModal').classList.remove('active');
}

function handleFromSemChange(val) {
    const fromSem = parseInt(val, 10);
    const toSemSelect = document.getElementById('promote_to_sem');
    if (!isNaN(fromSem) && fromSem >= 1 && fromSem < 8) {
        toSemSelect.value = fromSem + 1;
    }
    updatePromotePreview();
}

function updatePromotePreview() {
    const dept = document.getElementById('promote_dept').value;
    const fromSem = document.getElementById('promote_from_sem').value;
    const toSem = document.getElementById('promote_to_sem').value;
    const preview = document.getElementById('promote_preview_text');

    if (dept && fromSem && toSem) {
        preview.innerHTML = `All active <strong>${dept}</strong> students in <strong>Semester ${fromSem}</strong> will be promoted to <strong>Semester ${toSem}</strong>.`;
    } else {
        preview.innerText = 'Select a department and semester to configure promotion.';
    }
}

function confirmBulkPromote() {
    const dept = document.getElementById('promote_dept').value;
    const fromSem = document.getElementById('promote_from_sem').value;
    const toSem = document.getElementById('promote_to_sem').value;
    return confirm(`Are you sure you want to promote all eligible ${dept} students from Semester ${fromSem} to Semester ${toSem}? This action updates all matching student records.`);
}

// Table Checkbox & Batch Selection Bar Controls
function toggleSelectAll(masterCb) {
    const checkboxes = document.querySelectorAll('.student-select-cb');
    checkboxes.forEach(cb => {
        cb.checked = masterCb.checked;
    });
    updateBatchBar();
}

function updateBatchBar() {
    const checked = document.querySelectorAll('.student-select-cb:checked');
    const bar = document.getElementById('batchActionBar');
    const badge = document.getElementById('batchCountBadge');
    const hiddenIds = document.getElementById('batchSelectedIds');
    const masterCb = document.getElementById('selectAllCheckbox');
    const allCbs = document.querySelectorAll('.student-select-cb');

    if (checked.length > 0) {
        badge.innerText = checked.length;
        const ids = Array.from(checked).map(cb => cb.value);
        hiddenIds.value = ids.join(',');
        bar.style.display = 'flex';
    } else {
        bar.style.display = 'none';
        hiddenIds.value = '';
    }

    if (masterCb && allCbs.length > 0) {
        masterCb.checked = (checked.length === allCbs.length);
    }
}

function clearSelection() {
    document.querySelectorAll('.student-select-cb').forEach(cb => cb.checked = false);
    const masterCb = document.getElementById('selectAllCheckbox');
    if (masterCb) masterCb.checked = false;
    updateBatchBar();
}

// Close modals when clicking backdrop
document.querySelectorAll('.admin-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});
</script>

<?php
include __DIR__ . '/../components/confirm-modal.php';
include __DIR__ . '/../components/footer.php';
?>
