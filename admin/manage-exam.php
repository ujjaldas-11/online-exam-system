<?php
require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

date_default_timezone_set('Asia/Kolkata');

$message = '';
$message_type = '';

try {
    $deptStmt = $pdo->query("SELECT DISTINCT department FROM subjects WHERE department IS NOT NULL ORDER BY department ASC");
    $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $departments = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    verify_csrf();

    $title = clean_input($_POST['title'] ?? '');
    $department_selected = clean_input($_POST['department'] ?? '');
    $semester_selected = int_param($_POST['semester'] ?? 0);
    $subject_id = int_param($_POST['subject_id'] ?? 0);
    $duration = int_param($_POST['duration_minutes'] ?? 0);
    $total_marks = int_param($_POST['total_marks'] ?? 0);
    $total_questions = int_param($_POST['total_questions_to_ask'] ?? 0);
    $access_pin = clean_input($_POST['access_pin'] ?? '');
    $target_units = clean_input($_POST['target_units'] ?? '');

    if (empty($title) || empty($department_selected) || $semester_selected <= 0 || $subject_id <= 0 || $duration <= 0 || $duration > 1440 || $total_marks <= 0 || $total_marks > 10000 || $total_questions <= 0 || $total_questions > 1000 || empty($target_units)) {
        $message = 'Please fill all required fields with valid values.';
        $message_type = 'error';
    } elseif (strlen($title) > 200) {
        $message = 'Exam title cannot exceed 200 characters.';
        $message_type = 'error';
    } elseif (strlen($access_pin) > 10) {
        $message = 'Access PIN cannot exceed 10 characters.';
        $message_type = 'error';
    } else {
        if ($target_units === 'all') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE subject_id = ?');
            $stmt->execute([$subject_id]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE subject_id = ? AND unit_number = ?');
            $stmt->execute([$subject_id, $target_units]);
        }

        $available = (int) $stmt->fetchColumn();

        if ($available < $total_questions) {
            $unit_text = ($target_units === 'all') ? "This subject" : "Unit $target_units";
            $message = "$unit_text only has $available questions in the bank. You cannot configure an exam for $total_questions questions.";
            $message_type = 'error';
        } else {
            try {
                $creator_id = $_SESSION['admin_id'] ?? null;
                $stmt = $pdo->prepare("
                    INSERT INTO exams
                    (title, subject_id, duration_minutes, total_marks, total_questions_to_ask, access_pin, target_units, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'inactive', ?)
                ");
                $stmt->execute([$title, $subject_id, $duration, $total_marks, $total_questions, $access_pin ?: null, $target_units, $creator_id]);
                $newExamId = (int) $pdo->lastInsertId();

                log_admin_action($pdo, 'create_exam', 'exam', $newExamId, "Created exam: $title");

                $message = "Exam created successfully! Navigate to 'Control Exams' to start and monitor it.";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = safe_db_error($e, 'Failed to create examination.');
                $message_type = 'error';
            }
        }
    }
}


$sticky_semesters = [];
$sticky_subjects = [];
$sticky_units = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sel_dept = $_POST['department'] ?? '';
    $sel_sem = (int)($_POST['semester'] ?? 0);
    $sel_sub = (int)($_POST['subject_id'] ?? 0);
    
    if ($sel_dept) {
        $stmt = $pdo->prepare("SELECT DISTINCT semester FROM subjects WHERE department = ? ORDER BY semester ASC");
        $stmt->execute([$sel_dept]);
        $sticky_semesters = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if ($sel_dept && $sel_sem) {
        $stmt = $pdo->prepare("SELECT id, name FROM subjects WHERE department = ? AND semester = ? ORDER BY name ASC");
        $stmt->execute([$sel_dept, $sel_sem]);
        $sticky_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($sel_sub) {
        $stmt = $pdo->prepare("SELECT DISTINCT unit_number FROM questions WHERE subject_id = ? AND unit_number IS NOT NULL ORDER BY unit_number ASC");
        $stmt->execute([$sel_sub]);
        $sticky_units = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

$page_title = 'Create Exam • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container exam-form-page">
    <div class="page-header exam-form-page__header">
        <div>
            <h1>Create Examination</h1>
            <p>Configure exam parameters, duration, question pool, and classroom PIN</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?> exam-form-page__alert">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="card exam-card">
        <form method="POST" class="exam-form">
            <?= csrf_field() ?>

            <div class="exam-form__field exam-form__field--full">
                <label>Exam title</label>
                <input type="text" name="title" required placeholder="e.g. Mid-Term Surprise Quiz on DBMS" value="<?= e($_POST['title'] ?? '') ?>">
            </div>

            <div class="exam-form__section-label">Who it's for</div>
            <div class="exam-form__row exam-form__row--4">
                <div class="exam-form__field">
                    <label>Department</label>
                    <select name="department" id="dept_dropdown" required class="form-control">
                        <option value="">Choose</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= e($dept) ?>" <?= (($_POST['department'] ?? '') === $dept) ? 'selected' : '' ?>>
                                <?= e($dept) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="exam-form__field">
                    <label>Semester</label>
                    <select name="semester" id="sem_dropdown" required class="form-control" <?= empty($sticky_semesters) ? 'disabled' : '' ?>>
                        <option value="">Choose</option>
                        <?php foreach ($sticky_semesters as $sem): ?>
                            <option value="<?= e((string) $sem) ?>" <?= (($_POST['semester'] ?? '') == $sem) ? 'selected' : '' ?>>
                                Sem <?= e((string) $sem) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="exam-form__field">
                    <label>Subject</label>
                    <select name="subject_id" id="subject_dropdown" required class="form-control" <?= empty($sticky_subjects) ? 'disabled' : '' ?>>
                        <option value="">Choose</option>
                        <?php foreach ($sticky_subjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>" <?= (($_POST['subject_id'] ?? '') == $sub['id']) ? 'selected' : '' ?>>
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
                            <option value="all" <?= (($_POST['target_units'] ?? '') === 'all') ? 'selected' : '' ?>>All units</option>
                            <?php foreach ($sticky_units as $unit): ?>
                                <option value="<?= e((string) $unit) ?>" <?= (($_POST['target_units'] ?? '') == $unit) ? 'selected' : '' ?>>
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
                    <input type="number" name="duration_minutes" required min="1" max="300" placeholder="30" value="<?= e($_POST['duration_minutes'] ?? '30') ?>">
                </div>

                <div class="exam-form__field">
                    <label>Total marks</label>
                    <input type="number" name="total_marks" required min="1" placeholder="50" value="<?= e($_POST['total_marks'] ?? '50') ?>">
                </div>

                <div class="exam-form__field">
                    <label>Questions / student</label>
                    <input type="number" name="total_questions_to_ask" required min="1" placeholder="20" value="<?= e($_POST['total_questions_to_ask'] ?? '20') ?>">
                </div>

                <div class="exam-form__field">
                    <label>Classroom PIN <span class="exam-form__unit">optional</span></label>
                    <input type="text" name="access_pin" maxlength="10" placeholder="4821" value="<?= e($_POST['access_pin'] ?? '') ?>">
                </div>
            </div>

            <div class="exam-form__actions">
                <button type="submit" name="create_exam" class="btn btn-primary">
                    <span class="material-symbols-outlined icon-sm">add_circle</span> Create examination
                </button>
                <span class="exam-form__hint">Questions are picked at random from the selected scope for each student.</span>
            </div>
        </form>
    </div>
</div>

<script>
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

    // 1. Department Changes -> Fetch Semesters
    deptDropdown.addEventListener('change', function() {
        const dept = this.value;
        resetDropdowns(1);

        if (!dept) return;

        semDropdown.innerHTML = '<option value="">Loading semesters...</option>';
        semDropdown.disabled = false;

        fetch(`api-get-semesters.php?department=${encodeURIComponent(dept)}`)
            .then(res => res.json())
            .then(data => {
                let html = '<option value="">-- Choose Semester --</option>';
                data.forEach(sem => {
                    html += `<option value="${sem}">Semester ${sem}</option>`;
                });
                semDropdown.innerHTML = html;
            });
    });

    // 2. Semester Changes -> Fetch Subjects
    semDropdown.addEventListener('change', function() {
        const dept = deptDropdown.value;
        const sem = this.value;
        resetDropdowns(2);

        if (!dept || !sem) return;

        subjectDropdown.innerHTML = '<option value="">Loading subjects...</option>';
        subjectDropdown.disabled = false;

        fetch(`api-get-subjects.php?department=${encodeURIComponent(dept)}&semester=${encodeURIComponent(sem)}`)
            .then(res => res.json())
            .then(data => {
                let html = '<option value="">-- Choose Subject --</option>';
                data.forEach(sub => {
                    html += `<option value="${sub.id}">${sub.name}</option>`;
                });
                subjectDropdown.innerHTML = html;
            });
    });

    // 3. Subject Changes -> Fetch Units
    subjectDropdown.addEventListener('change', function() {
        const subId = this.value;
        resetDropdowns(3);

        if (!subId) return;

        unitDropdown.innerHTML = '<option value="">Loading units...</option>';
        unitDropdown.disabled = false;

        fetch(`api-get-units.php?subject_id=${subId}`)
            .then(res => res.json())
            .then(data => {
                let html = '<option value="">-- Select Target Unit --</option>';
                if (data.length === 0) {
                    html += '<option value="" disabled>No questions uploaded yet</option>';
                } else {
                    html += '<option value="all">All Units (Combined Exam)</option>';
                    data.forEach(unit => {
                        html += `<option value="${unit}">Unit ${unit}</option>`;
                    });
                }
                unitDropdown.innerHTML = html;
            });
    });
});
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
