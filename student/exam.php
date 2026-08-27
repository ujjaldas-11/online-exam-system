<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

if (empty($_GET['id'])) {
    die("Error: No exam selected.");
}

$exam_id = int_param($_GET['id']);
$student_id = (int) $_SESSION['student_id'];
$student_semester = (int) $_SESSION['semester'];
$student_department = (string) $_SESSION['department'];

try {
    $examSql = "SELECT e.id, e.title, e.duration_minutes, e.subject_id, e.total_questions_to_ask, e.total_marks,
        e.access_pin, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE)) AS seconds_left
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.id = :id
            AND s.semester = :semester
            AND s.department = :department
            AND e.status = 'active'
        LIMIT 1";

    $examStmt = $pdo->prepare($examSql);
    $examStmt->execute([
        ':id' => $exam_id,
        ':semester' => $student_semester,
        ':department' => $student_department,
    ]);

    $exam = $examStmt->fetch();

    if (!$exam) {
        die("Exam not found or you do not have permission to access it.");
    }

    if ($exam['seconds_left'] <= 0) {
        die("<h2 style='text-align:center;margin-top:100px;font-family:sans-serif;'>Time is up! This exam has ended.</h2>");
    }

    // Classroom PIN Verification (For Surprise Tests)
    $pinRequired = !empty($exam['access_pin']);
    $isUnlocked = isset($_SESSION['unlocked_exams'][$exam_id]);
    $pinError = '';

    if ($pinRequired && !$isUnlocked) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_pin'])) {
            verify_csrf();
            $enteredPin = clean_input($_POST['exam_pin'] ?? '');
            if ($enteredPin === $exam['access_pin']) {
                $_SESSION['unlocked_exams'][$exam_id] = true;
                $isUnlocked = true;
            } else {
                $pinError = "Incorrect Classroom Exam PIN. Please ask your instructor.";
            }
        }
    }

    if ($pinRequired && !$isUnlocked) {
        $page_title = 'Enter Exam PIN • Examify';
        $body_class = 'auth-body';
        include __DIR__ . '/../components/header.php';
        ?>
        <div class="auth-card">
            <h1>Classroom Access PIN</h1>
            <p class="subtitle">Enter the PIN provided by your instructor on the board to unlock <strong><?= e($exam['title']) ?></strong></p>

            <?php if ($pinError): ?>
                <div class="alert alert-error"><?= e($pinError) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Exam PIN / Passcode</label>
                    <input type="text" name="exam_pin" required autofocus placeholder="e.g. 1234" style="text-align: center; font-size: 1.5rem; letter-spacing: 4px;">
                </div>
                <button type="submit" name="verify_pin" class="btn btn-primary btn-block">Unlock Exam</button>
                <div style="text-align: center; margin-top: 16px;">
                    <a href="dashboard.php" class="btn btn-secondary btn-sm">Return to Dashboard</a>
                </div>
            </form>
        </div>
        <?php
        include __DIR__ . '/../components/footer.php';
        exit;
    }

    $points_per_question = ($exam['total_questions_to_ask'] > 0) ? ($exam['total_marks'] / $exam['total_questions_to_ask']) : 0;
    $points_per_question = round((float) $points_per_question, 2);

    // Check or initialize attempt
    $attemptStmt = $pdo->prepare("SELECT id, total_questions, status FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
    $attemptStmt->execute([$student_id, $exam_id]);
    $attempt = $attemptStmt->fetch();

    if ($attempt && $attempt['status'] === 'completed') {
        redirect("result.php?exam_id=$exam_id");
    }

    if (!$attempt) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO exam_attempts (student_id, exam_id, total_questions) VALUES (?, ?, ?)");
            $stmt->execute([$student_id, $exam_id, $exam['total_questions_to_ask']]);
            $attempt_id = (int) $pdo->lastInsertId();

            $qStmt = $pdo->prepare("SELECT id FROM questions WHERE subject_id = ? ORDER BY RAND() LIMIT " . (int) $exam['total_questions_to_ask']);
            $qStmt->execute([$exam['subject_id']]);
            $random_questions = $qStmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($random_questions) < $exam['total_questions_to_ask']) {
                throw new Exception("Not enough questions in question bank.");
            }

            $ansStmt = $pdo->prepare("INSERT INTO student_answers (attempt_id, question_id) VALUES (?, ?)");
            foreach ($random_questions as $q_id) {
                $ansStmt->execute([$attempt_id, $q_id]);
            }

            $pdo->commit();
            $total_questions = (int) $exam['total_questions_to_ask'];
        } catch (Exception $e) {
            $pdo->rollBack();
            log_error("Error initializing exam attempt for student $student_id", $e);
            die("Error starting exam: " . $e->getMessage());
        }
    } else {
        $attempt_id = (int) $attempt['id'];
        $total_questions = (int) $attempt['total_questions'];
    }

} catch (PDOException $e) {
    log_error("Exam loading error", $e);
    die("Database Error. Please contact your instructor.");
}

$page_title = e($exam['title']) . ' • Examify';
$extra_css = ['exam.css'];
include __DIR__ . '/../components/header.php';
?>

<div class="exam-header">
    <h1><?= e($exam['title']) ?></h1>
    <div class="timer-box">
        <div class="timer" id="timerDisplay" data-time-left="<?= max(0, (int)$exam['seconds_left']) ?>" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">timer</span> <span id="timerText">--:--</span>
        </div>
    </div>
</div>

<?php if ($total_questions === 0): ?>
    <div style="text-align: center; padding: 80px 20px; color: var(--color-text-secondary);">
        No questions available for this exam.
    </div>
<?php else: ?>
    <div class="exam-container">
        <!-- Question Card -->
        <div class="question-card">
            <div id="question-container">
                <p style="color: var(--color-text-secondary);">Loading question...</p>
            </div>

            <div class="exam-nav">
                <button type="button" id="btn-prev" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 4px;">
                    <span class="material-symbols-outlined icon-xs">arrow_back</span> Previous
                </button>
                <button type="button" id="btn-review" class="btn btn-warning" data-marked="0" style="display: inline-flex; align-items: center; gap: 4px;">
                    <span class="material-symbols-outlined icon-xs">bookmark</span> Mark for Review
                </button>
                <button type="button" id="btn-next" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 4px;">
                    Next <span class="material-symbols-outlined icon-xs">arrow_forward</span>
                </button>
            </div>
        </div>

        <!-- Sidebar Question Map -->
        <div class="exam-sidebar">
            <h3>Question Palette</h3>
            <div class="question-grid" id="grid-container"></div>

            <div class="exam-legend">
                <div><span class="dot dot-answered"></span> Answered</div>
                <div><span class="dot dot-review"></span> Marked for Review</div>
                <div><span class="dot dot-unanswered"></span> Unanswered</div>
            </div>

            <form action="result.php" method="POST" id="examForm">
                <?= csrf_field() ?>
                <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                <button type="button" id="btn-submit-exam" class="btn btn-success btn-block" style="padding: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">check_circle</span> Submit Exam
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Fullscreen Start Modal -->
<div id="exam-start-overlay" class="fullscreen-overlay">
    <div class="overlay-card">
        <h2 style="display: flex; align-items: center; justify-content: center; gap: 8px;">
            <span class="material-symbols-outlined icon-lg">shield</span> Start Secure Examination
        </h2>
        <p>This exam is protected by anti-cheat monitoring. Fullscreen mode will be activated.</p>
        <div class="alert alert-warning" style="margin-bottom: 20px; display: flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">warning</span>
            <div>Tab switches, window minimization, and developer tools are recorded on the instructor dashboard.</div>
        </div>
        <button id="btn-enter-fullscreen" class="btn btn-primary btn-block" style="padding: 14px; font-size: 1.05rem; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
            <span class="material-symbols-outlined icon-md">fullscreen</span> Click to Enter Fullscreen & Begin
        </button>
        <p style="margin-top: 14px; font-size: 0.85rem; color: var(--color-text-muted);">
            Or press <strong>F11</strong> on your keyboard
        </p>
    </div>
</div>

<script src="../utils/anti-cheat.js?v=<?= asset_version() ?>"></script>
<script src="../utils/timer.js?v=<?= asset_version() ?>"></script>
<script>
    const examId = <?= $exam_id ?>;
    const attemptId = <?= $attempt_id ?>;
    const totalQuestions = <?= $total_questions ?>;
    const pointsPerQuestion = <?= $points_per_question ?>;
    let currentIndex = 0;
    let currentQuestionId = null;

    document.addEventListener('DOMContentLoaded', () => {
        AntiCheat.init({
            attemptId: attemptId,
            onViolation: (count, reason) => console.warn(`Violation ${count}: ${reason}`),
            onTerminate: () => {
                alert("Maximum violations reached. Your exam is being automatically submitted.");
                document.getElementById('examForm').submit();
            }
        });

        const startBtn = document.getElementById('btn-enter-fullscreen');
        if (startBtn) {
            startBtn.addEventListener('click', () => {
                AntiCheat.start();
            });
        }

        if (totalQuestions > 0) {
            loadQuestion(0);
        }
    });

    function loadQuestion(index) {
        fetch(`question.php?exam_id=${examId}&index=${index}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) return alert(data.error);

                currentIndex = data.currentIndex;
                currentQuestionId = data.question.id;

                renderQuestion(data.question, data.selected_option, data.marked_for_review);
                renderGrid(data.total, data.all_answers, data.all_reviews, data.question_ids);
                updateNavButtons();
            })
            .catch(err => console.error("Error loading question:", err));
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderQuestion(q, selected, marked) {
        const container = document.getElementById('question-container');
        const safeQuestionText = escapeHtml(q.question_text);
        let html = `
            <div class="question-meta">Question ${currentIndex + 1} of ${totalQuestions} • ${pointsPerQuestion} Mark${pointsPerQuestion > 1 ? 's' : ''}</div>
            <div class="question-text">${safeQuestionText}</div>
            <div class="options-list">
        `;

        ['A', 'B', 'C', 'D'].forEach(opt => {
            const text = q['option_' + opt.toLowerCase()];
            if (text !== null && text !== undefined && String(text).trim() !== '') {
                const isSelected = selected === opt;
                const safeText = escapeHtml(text);
                html += `
                    <label class="option-item ${isSelected ? 'selected' : ''}">
                        <input type="radio" name="answer" value="${opt}" ${isSelected ? 'checked' : ''}>
                        <span><strong>${opt}.</strong> ${safeText}</span>
                    </label>
                `;
            }
        });

        html += `</div>`;
        container.innerHTML = html;

        const reviewBtn = document.getElementById('btn-review');
        if (reviewBtn) {
            reviewBtn.dataset.marked = marked ? "1" : "0";
            reviewBtn.innerText = marked ? "Unmark Review" : "Mark for Review";
        }
    }

    function renderGrid(total, answers, reviews, allIds) {
        const grid = document.getElementById('grid-container');
        grid.innerHTML = '';

        for (let i = 0; i < total; i++) {
            const qId = allIds[i];
            const btn = document.createElement('div');
            btn.className = 'grid-btn';
            btn.innerText = i + 1;
            btn.id = `grid-btn-${i}`;

            if (answers[qId]) btn.classList.add('answered');
            if (reviews[qId]) btn.classList.add('review');
            if (i === currentIndex) btn.classList.add('active');

            btn.onclick = () => saveCurrentAnswer().then(() => loadQuestion(i));
            grid.appendChild(btn);
        }
    }

    function updateNavButtons() {
        const prevBtn = document.getElementById('btn-prev');
        const nextBtn = document.getElementById('btn-next');
        if (prevBtn) prevBtn.style.visibility = currentIndex > 0 ? 'visible' : 'hidden';
        if (nextBtn) nextBtn.style.visibility = currentIndex < totalQuestions - 1 ? 'visible' : 'hidden';
    }

    async function saveCurrentAnswer() {
        if (!currentQuestionId) return;

        const selected = document.querySelector('input[name="answer"]:checked')?.value || null;
        const reviewBtn = document.getElementById('btn-review');
        const isMarked = reviewBtn ? reviewBtn.dataset.marked === "1" : false;

        const payload = {
            exam_id: examId,
            question_id: currentQuestionId,
            marked_for_review: isMarked
        };
        if (selected) payload.selected_option = selected;

        try {
            await fetch('question.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
        } catch (e) {
            console.error("Auto-sync failed:", e);
        }
    }

    // Auto-save when option is clicked
    document.getElementById('question-container').addEventListener('change', e => {
        if (e.target.name === 'answer') {
            document.querySelectorAll('.option-item').forEach(el => el.classList.remove('selected'));
            e.target.closest('.option-item')?.classList.add('selected');

            saveCurrentAnswer().then(() => {
                document.getElementById(`grid-btn-${currentIndex}`)?.classList.add('answered');
            });
        }
    });

    document.getElementById('btn-prev').onclick = () => {
        if (currentIndex > 0) saveCurrentAnswer().then(() => loadQuestion(currentIndex - 1));
    };

    document.getElementById('btn-next').onclick = () => {
        if (currentIndex < totalQuestions - 1) saveCurrentAnswer().then(() => loadQuestion(currentIndex + 1));
    };

    document.getElementById('btn-review').onclick = function () {
        const isMarked = this.dataset.marked === "1";
        this.dataset.marked = isMarked ? "0" : "1";
        this.innerText = isMarked ? "Mark for Review" : "Unmark Review";

        const btn = document.getElementById(`grid-btn-${currentIndex}`);
        if (btn) {
            if (isMarked) {
                btn.classList.remove('review');
            } else {
                btn.classList.add('review');
            }
        }
        saveCurrentAnswer();
    };

    document.getElementById('btn-submit-exam').onclick = async () => {
        if (confirm("Are you sure you want to submit your examination? Once submitted, you cannot change your answers.")) {
            await saveCurrentAnswer();
            document.getElementById('examForm').submit();
        }
    };
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
