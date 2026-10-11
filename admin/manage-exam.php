<?php
require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/CurriculumService.php';

date_default_timezone_set('Asia/Kolkata');

$message = '';
$message_type = '';
$departments = CurriculumService::getDepartments($pdo);

// =================================================================================
// EDIT MODE DETECTION & SECURITY LOCK
// =================================================================================
$is_edit = false;
$edit_id = 0;
$form_data = []; // Holds the sticky form values

if (isset($_GET['edit'])) {
    $is_edit = true;
    $edit_id = (int) $_GET['edit'];

    // Fetch the existing exam data
    $stmt = $pdo->prepare("
        SELECT e.*, s.department, s.semester 
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.id = ?
    ");
    $stmt->execute([$edit_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        set_flash('error', "Exam not found.");
        redirect('control-exams.php');
    }

    if (!can_admin_manage_exam($pdo, $edit_id)) {
        set_flash('error', "Access Denied: You do not have permission to edit this exam.");
        redirect('control-exams.php');
    }

    // SECURITY: Cannot edit if retired, or if active AND start time has already passed
    $is_active = ($exam['status'] === 'active');
    $has_started = $exam['start_time'] ? (strtotime($exam['start_time']) <= time()) : false;

    if ($exam['status'] === 'retired' || ($is_active && $has_started)) {
        set_flash('error', "LOCKED: You cannot edit an exam that has already started or is retired.");
        redirect('control-exams.php');
    }

    // Pre-fill the form if we are just loading the page (not submitting it)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $form_data = $exam;
        // Format dates correctly for the HTML5 datetime-local input
        $form_data['start_time'] = $exam['start_time'] ? date('Y-m-d\TH:i', strtotime($exam['start_time'])) : '';
        $form_data['end_time'] = $exam['end_time'] ? date('Y-m-d\TH:i', strtotime($exam['end_time'])) : '';
    }
}

// =================================================================================
// FORM SUBMISSION (CREATE OR UPDATE)
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['create_exam']) || isset($_POST['update_exam']))) {
    verify_csrf();

    $form_data['title'] = clean_input($_POST['title'] ?? '');
    $form_data['department'] = clean_input($_POST['department'] ?? '');
    $form_data['semester'] = int_param($_POST['semester'] ?? 0);
    $form_data['subject_id'] = int_param($_POST['subject_id'] ?? 0);
    $form_data['duration_minutes'] = int_param($_POST['duration_minutes'] ?? 0);
    $form_data['total_marks'] = int_param($_POST['total_marks'] ?? 0);
    $form_data['total_questions_to_ask'] = int_param($_POST['total_questions_to_ask'] ?? 0);
    $form_data['negative_marks_per_question'] = max(0.0, round((float)($_POST['negative_marks_per_question'] ?? 0.0), 2));
    $form_data['access_pin'] = clean_input($_POST['access_pin'] ?? '');
    $form_data['target_units'] = clean_input($_POST['target_units'] ?? '');

    $start_time_raw = clean_input($_POST['start_time'] ?? '');
    $end_time_raw = clean_input($_POST['end_time'] ?? '');
    $start_time_db = !empty($start_time_raw) ? date('Y-m-d H:i:s', strtotime($start_time_raw)) : null;
    $end_time_db = !empty($end_time_raw) ? date('Y-m-d H:i:s', strtotime($end_time_raw)) : null;

    // Keep inputs sticky if there's an error
    $form_data['start_time'] = $start_time_raw;
    $form_data['end_time'] = $end_time_raw;

    // Basic Validation
    if (empty($form_data['title']) || empty($form_data['department']) || $form_data['semester'] <= 0 || $form_data['subject_id'] <= 0 || $form_data['duration_minutes'] <= 0 || $form_data['duration_minutes'] > 1440 || $form_data['total_marks'] <= 0 || $form_data['total_marks'] > 10000 || $form_data['total_questions_to_ask'] <= 0 || $form_data['total_questions_to_ask'] > 1000 || empty($form_data['target_units'])) {
        $message = 'Please fill all required fields with valid values.';
        $message_type = 'error';
    } elseif (strlen($form_data['title']) > 200) {
        $message = 'Exam title cannot exceed 200 characters.';
        $message_type = 'error';
    } elseif (strlen($form_data['access_pin']) > 10) {
        $message = 'Access PIN cannot exceed 10 characters.';
        $message_type = 'error';
    } elseif ($start_time_db && $end_time_db && strtotime($end_time_db) <= strtotime($start_time_db)) {
        $message = 'Scheduled end time must be after the start time.';
        $message_type = 'error';
    } else {
        // Question availability check
        if ($form_data['target_units'] === 'all') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE subject_id = ?');
            $stmt->execute([$form_data['subject_id']]);
        } else {
            $stmt =$pdo->prepare('SELECT COUNT(*) FROM questions WHERE subject_id = ? AND unit_number = ?');
            $stmt->execute([$form_data['subject_id'],$form_data['target_units']]);
        }

        $available = (int)$stmt->fetchColumn();

        if ($available <$form_data['total_questions_to_ask']) {
            $unit_text = ($form_data['target_units'] === 'all') ? "This subject" : "Unit " . $form_data['target_units'];$message = "$unit_text only has$available questions in the bank. You cannot configure an exam for {$form_data['total_questions_to_ask']} questions.";
            $message_type = 'error';
        } else {
            try {
                // Calculate Status Based on Start Time
                $calc_status = 'inactive';
                if ($start_time_db) {
                    $calc_status = (strtotime($start_time_db) > time()) ? 'scheduled' : 'active';
                }

                if ($is_edit) {
                    // RUN SQL UPDATE
                    $stmt =$pdo->prepare("
                        UPDATE exams SET 
                            title=?, subject_id=?, duration_minutes=?, total_marks=?, 
                            negative_marks_per_question=?, total_questions_to_ask=?, 
                            access_pin=?, target_units=?, status=?, start_time=?, end_time=? 
                        WHERE id=?
                    ");
                    $stmt->execute([
                        $form_data['title'],$form_data['subject_id'], $form_data['duration_minutes'],$form_data['total_marks'], $form_data['negative_marks_per_question'],$form_data['total_questions_to_ask'], $form_data['access_pin'] ?: null,$form_data['target_units'], $calc_status,$start_time_db, $end_time_db,$edit_id
                    ]);

                    if(function_exists('log_admin_action')) {
                        log_admin_action($pdo, 'update_exam', 'exam',$edit_id, "Updated exam configurations: {$form_data['title']}");
                    }
                    
                    set_flash('success', "Exam updated successfully!");
                    redirect('control-exams.php');
                } else {
                    // RUN SQL INSERT
                    $creator_id =$_SESSION['admin_id'] ?? null;
                    $stmt =$pdo->prepare("
                        INSERT INTO exams
                        (title, subject_id, duration_minutes, total_marks, negative_marks_per_question, total_questions_to_ask, access_pin, target_units, status, start_time, end_time, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $form_data['title'],$form_data['subject_id'], $form_data['duration_minutes'],$form_data['total_marks'], $form_data['negative_marks_per_question'],$form_data['total_questions_to_ask'], $form_data['access_pin'] ?: null,$form_data['target_units'], $calc_status,$start_time_db, $end_time_db,$creator_id
                    ]);
                    $newExamId = (int)$pdo->lastInsertId();

                    if(function_exists('log_admin_action')) {
                        log_admin_action($pdo, 'create_exam', 'exam',$newExamId, "Created exam: {$form_data['title']} (Status:$calc_status)");
                    }
                    
                    $message = ($calc_status === 'scheduled')
                        ? "Exam scheduled successfully for " . date('M d, Y h:i A', strtotime($start_time_db)) . "! It will activate automatically."
                        : "Exam created successfully! Navigate to 'Control Exams' to manage and monitor it.";
                    $message_type = 'success';$form_data = []; // Clear form on success
                }
            } catch (PDOException $e) {$message = safe_db_error($e, 'Failed to save examination.');$message_type = 'error';
            }
        }
    }
}

// =================================================================================
// 3. DROPDOWN DATA (AJAX Fallback / Sticky States)
// =================================================================================
$sticky_semesters = [];
$sticky_subjects = [];$sticky_units = [];

$sel_dept =$form_data['department'] ?? '';
$sel_sem = (int)($form_data['semester'] ?? 0);
$sel_sub = (int)($form_data['subject_id'] ?? 0);

if ($sel_dept) {
    $stmt =$pdo->prepare("SELECT DISTINCT semester FROM subjects WHERE department = ? ORDER BY semester ASC");
    $stmt->execute([$sel_dept]);
    $sticky_semesters =$stmt->fetchAll(PDO::FETCH_COLUMN);
}
if ($sel_dept &&$sel_sem) {
    $stmt =$pdo->prepare("SELECT id, name FROM subjects WHERE department = ? AND semester = ? ORDER BY name ASC");
    $stmt->execute([$sel_dept,$sel_sem]);
    $sticky_subjects =$stmt->fetchAll(PDO::FETCH_ASSOC);
}
if ($sel_sub) {
    $stmt =$pdo->prepare("SELECT DISTINCT unit_number FROM questions WHERE subject_id = ? AND unit_number IS NOT NULL ORDER BY unit_number ASC");
    $stmt->execute([$sel_sub]);
    $sticky_units =$stmt->fetchAll(PDO::FETCH_COLUMN);
}

$page_title = ($is_edit ? 'Edit Exam' : 'Create Exam') . ' • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content exam-form-page">
    <div class="page-header exam-form-page__header">
        <div>
            <h1><?= $is_edit ? 'Edit Examination' : 'Create Examination' ?></h1>
            <p>Configure exam parameters, duration, question pool, and classroom PIN</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="control-exams.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to Exams
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?> exam-form-page__alert">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="card exam-card">
        <!-- 🚨 CRITICAL FIX: The form action MUST include the ?edit= ID if in edit mode! 🚨 -->
        <form method="POST" class="exam-form" action="<?= $is_edit ? 'manage-exam.php?edit='.$edit_id : 'manage-exam.php' ?>">
            <?= csrf_field() ?>

            <div class="exam-form__field exam-form__field--full">
                <label>Exam title</label>
                <input type="text" name="title" required placeholder="e.g. Mid-Term Surprise Quiz on DBMS" value="<?= e($form_data['title'] ?? '') ?>">
            </div>

            <div class="exam-form__section-label">Who it's for</div>
            <div class="exam-form__row exam-form__row--4">
                <div class="exam-form__field">
                    <label>Department</label>
                    <select name="department" id="dept_dropdown" required class="form-control">
                        <option value="">Choose</option>
                        <?php foreach ($departments as$dept): ?>
                            <option value="<?= e($dept) ?>" <?= (($form_data['department'] ?? '') ===$dept) ? 'selected' : '' ?>>
                                <?= e($dept) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="exam-form__field">
                    <label>Semester</label>
                    <select name="semester" id="sem_dropdown" required class="form-control" <?= empty($sticky_semesters) ? 'disabled' : '' ?>>
                        <option value="">Choose</option>
                        <?php foreach ($sticky_semesters as$sem): ?>
                            <option value="<?= e((string) $sem) ?>" <?= (($form_data['semester'] ?? 0) ==$sem) ? 'selected' : '' ?>>
                                Sem <?= e((string) $sem) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="exam-form__field">
                    <label>Subject</label>
                    <select name="subject_id" id="subject_dropdown" required class="form-control" <?= empty($sticky_subjects) ? 'disabled' : '' ?>>
                        <option value="">Choose</option>
                        <?php foreach ($sticky_subjects as$sub): ?>
                            <option value="<?= $sub['id'] ?>" <?= (($form_data['subject_id'] ?? 0) ==$sub['id']) ? 'selected' : '' ?>>
                                <?= e($sub['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="exam-form__field">
                    <label>Target unit</label>
                    <select name="target_units" id="unit_dropdown" required class="form-control" <?= empty($sticky_units) ? 'disabled' : '' ?>>
                        <option value="">Choose</option>
                        <?php if (!empty($sticky_units)): ?>
                            <option value="all" <?= (($form_data['target_units'] ?? '') === 'all') ? 'selected' : '' ?>>All units</option>
                            <?php foreach ($sticky_units as$unit): ?>
                                <option value="<?= e((string) $unit) ?>" <?= (($form_data['target_units'] ?? '') ==$unit) ? 'selected' : '' ?>>
                                    Unit <?= e((string) $unit) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="exam-form__section-label">How it runs</div>
            <div class="exam-form__row exam-form__row--4">
                <div class="exam-form__field">
                    <label>Duration <span class="exam-form__unit">min</span></label>
                    <input type="number" name="duration_minutes" required min="1" max="300" placeholder="30" value="<?= e($form_data['duration_minutes'] ?? '30') ?>">
                </div>

                <div class="exam-form__field">
                    <label>Total marks</label>
                    <input type="number" name="total_marks" required min="1" placeholder="50" value="<?= e($form_data['total_marks'] ?? '50') ?>">
                </div>

                <div class="exam-form__field">
                    <label>Questions / student</label>
                    <input type="number" name="total_questions_to_ask" required min="1" placeholder="20" value="<?= e($form_data['total_questions_to_ask'] ?? '20') ?>">
                </div>

                <div class="exam-form__field">
                    <label>Negative mark / wrong <span class="exam-form__unit">deduction</span></label>
                    <input type="number" step="0.05" min="0" max="50" name="negative_marks_per_question" placeholder="0.00" value="<?= e($form_data['negative_marks_per_question'] ?? '0.00') ?>">
                </div>

                <div class="exam-form__field">
                    <label>Classroom PIN <span class="exam-form__unit">optional</span></label>
                    <input type="text" name="access_pin" maxlength="10" placeholder="4821" value="<?= e($form_data['access_pin'] ?? '') ?>">
                </div>
            </div>

            <div class="exam-form__section-label">Scheduling & Auto-Activation <span class="exam-form__unit">optional</span></div>
            <div class="exam-form__row exam-form__row--2">
                <div class="exam-form__field">
                    <label>Scheduled Start Time</label>
                    <input type="datetime-local" name="start_time" value="<?= e($form_data['start_time'] ?? '') ?>" class="form-control">
                    <small style="color: var(--color-text-secondary); font-size: 0.78rem; margin-top: 4px; display: block;">Leave blank to activate manually from the Control Center</small>
                </div>
                <div class="exam-form__field">
                    <label>Scheduled End Time</label>
                    <input type="datetime-local" name="end_time" value="<?= e($form_data['end_time'] ?? '') ?>" class="form-control">
                    <small style="color: var(--color-text-secondary); font-size: 0.78rem; margin-top: 4px; display: block;">Auto-closes exam access when this time arrives</small>
                </div>
            </div>

            <div class="exam-form__actions" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <?php if ($is_edit): ?>
                    <!-- 🚨 CRITICAL FIX: The button must have name="update_exam" 🚨 -->
                    <button type="submit" name="update_exam" class="btn btn-primary">
                        <span class="material-symbols-outlined icon-sm">save</span> Update Configuration
                    </button>
                <?php else: ?>
                    <!-- Create Button -->
                    <button type="submit" name="create_exam" class="btn btn-primary">
                        <span class="material-symbols-outlined icon-sm">add_circle</span> Create Examination
                    </button>
                <?php endif; ?>
                
                <span class="exam-form__hint">Questions are picked at random from the selected scope for each student.</span>
            </div>
        </form>
    </div>
</div>

<script>
// (Keep your existing dropdown AJAX javascript here)
document.addEventListener("DOMContentLoaded", function() {
    const deptDropdown = document.getElementById('dept_dropdown');
    const semDropdown = document.getElementById('sem_dropdown');
    const subjectDropdown = document.getElementById('subject_dropdown');
    const unitDropdown = document.getElementById('unit_dropdown');

    function resetDropdowns(level) {
        if (level <= 1) {
            semDropdown.innerHTML = '<option value="">-- Choose Semester --</option>';
            semDropdown.disabled = true;
        }
        if (level <= 2) {
            subjectDropdown.innerHTML = '<option value="">-- Choose Subject --</option>';
            subjectDropdown.disabled = true;
        }
        if (level <= 3) {
            unitDropdown.innerHTML = '<option value="">-- Select Target Unit --</option>';
            unitDropdown.disabled = true;
        }
    }

    deptDropdown.addEventListener('change', function() {
        const dept = this.value;
        resetDropdowns(1);
        if (!dept) return;
        semDropdown.innerHTML = '<option value="">Loading semesters...</option>';
        semDropdown.disabled = false;
        fetch(`api-get-semesters.php?department=${encodeURIComponent(dept)}`).then(res => res.json()).then(data => {
            let html = '<option value="">-- Choose Semester --</option>';
            data.forEach(sem => { html += `<option value="${sem}">Semester ${sem}</option>`; });
            semDropdown.innerHTML = html;
        });
    });

    semDropdown.addEventListener('change', function() {
        const dept = deptDropdown.value;
        const sem = this.value;
        resetDropdowns(2);
        if (!dept || !sem) return;
        subjectDropdown.innerHTML = '<option value="">Loading subjects...</option>';
        subjectDropdown.disabled = false;
        fetch(`api-get-subjects.php?department=${encodeURIComponent(dept)}&semester=${encodeURIComponent(sem)}`).then(res => res.json()).then(data => {
            let html = '<option value="">-- Choose Subject --</option>';
            data.forEach(sub => { html += `<option value="${sub.id}">${sub.name}</option>`; });
            subjectDropdown.innerHTML = html;
        });
    });

    subjectDropdown.addEventListener('change', function() {
        const subId = this.value;
        resetDropdowns(3);
        if (!subId) return;
        unitDropdown.innerHTML = '<option value="">Loading units...</option>';
        unitDropdown.disabled = false;
        fetch(`api-get-units.php?subject_id=${subId}`).then(res => res.json()).then(data => {
            let html = '<option value="">-- Select Target Unit --</option>';
            if (data.length === 0) { html += '<option value="" disabled>No questions uploaded yet</option>'; } else {
                html += '<option value="all">All Units (Combined Exam)</option>';
                data.forEach(unit => { html += `<option value="${unit}">Unit ${unit}</option>`; });
            }
            unitDropdown.innerHTML = html;
        });
    });
});
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
