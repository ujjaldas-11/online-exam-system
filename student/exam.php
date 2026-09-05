<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../utils/device.php';
require_once '../services/ExamEngine.php';
require_once '../utils/rate-limiter.php';

// Enforce desktop PC / laptop environment for active examinations
require_desktop_for_exam();

if (empty($_GET['id'])) {
    die("Error: No exam selected.");
}

$exam_id = int_param($_GET['id']);
$student_id = (int) $_SESSION['student_id'];
$student_semester = (int) $_SESSION['semester'];
$student_department = (string) $_SESSION['department'];

// 1. Fetch Exam Meta to check PIN requirements & access
try {
    ExamEngine::syncExamStatuses($pdo);

    $examMetaStmt = $pdo->prepare("
        SELECT e.id, e.title, e.duration_minutes, e.total_questions_to_ask, e.total_marks,
               e.access_pin, e.target_units, e.status, e.start_time, e.end_time,
               s.department, s.semester, s.name AS subject_name,
               TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE)) AS seconds_left
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.id = ?
        LIMIT 1
    ");
    $examMetaStmt->execute([$exam_id]);
    $exam = $examMetaStmt->fetch();

    if (!$exam) {
        die("<h2 style='text-align:center;margin-top:100px;font-family:sans-serif;'>Exam not found or you do not have permission to access it.</h2>");
    }

    if ($exam['status'] === 'scheduled') {
        if (!empty($exam['start_time']) && strtotime($exam['start_time']) <= time()) {
            $pdo->prepare("UPDATE exams SET status = 'active' WHERE id = ?")->execute([$exam_id]);
            $exam['status'] = 'active';
        } else {
            $startFormatted = !empty($exam['start_time']) ? date('d M Y, h:i A', strtotime($exam['start_time'])) : 'a later date';
            die("<h2 style='text-align:center;margin-top:100px;font-family:sans-serif;'>This examination is scheduled to start at {$startFormatted}. Please check back then.</h2>");
        }
    }

    if ($exam['status'] !== 'active') {
        die("<h2 style='text-align:center;margin-top:100px;font-family:sans-serif;'>This exam is not currently active.</h2>");
    }

    // Authorization: Department and semester match
    if ($exam['department'] !== $student_department || (int)$exam['semester'] !== $student_semester) {
        die("<h2 style='text-align:center;margin-top:100px;font-family:sans-serif;'>You are not authorized to access this exam.</h2>");
    }

    if ((int)$exam['seconds_left'] <= 0) {
        die("<h2 style='text-align:center;margin-top:100px;font-family:sans-serif;'>Time is up! This examination has already concluded.</h2>");
    }

    // 2. Classroom PIN Check with Rate Limiting
    $pinRequired = !empty($exam['access_pin']);
    $isUnlocked = isset($_SESSION['unlocked_exams'][$exam_id]);
    $pinError = '';

    if ($pinRequired && !$isUnlocked) {
        $pinKey = "pin:exam:{$exam_id}:stu:{$student_id}";
        $pinRate = RateLimiter::check($pdo, $pinKey, 5);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_pin'])) {
            verify_csrf();
            if (!$pinRate['allowed']) {
                $cooldownMin = ceil($pinRate['retry_after'] / 60);
                $pinError = "Too many incorrect PIN attempts. Access locked for {$cooldownMin} minute" . ($cooldownMin > 1 ? 's' : '') . " (or {$pinRate['retry_after']}s). Please contact your instructor.";
            } else {
                $enteredPin = clean_input($_POST['exam_pin'] ?? '');
                if ($enteredPin === $exam['access_pin']) {
                    RateLimiter::clear($pdo, $pinKey);
                    $_SESSION['unlocked_exams'][$exam_id] = true;
                    $isUnlocked = true;
                } else {
                    $hit = RateLimiter::hit($pdo, $pinKey, 600, 5);
                    $rem = max(0, 5 - $hit['hits']);
                    if ($rem > 0) {
                        $pinError = "Incorrect Classroom Exam PIN. {$rem} attempt" . ($rem === 1 ? '' : 's') . " remaining before a 10-minute lockout.";
                    } else {
                        $pinError = "Incorrect Classroom Exam PIN. Maximum attempts exceeded. Locked out for 10 minutes.";
                    }
                }
            }
        } elseif (!$pinRate['allowed']) {
            $cooldownMin = ceil($pinRate['retry_after'] / 60);
            $pinError = "Too many incorrect PIN attempts. Access locked for {$cooldownMin} minute" . ($cooldownMin > 1 ? 's' : '') . " (or {$pinRate['retry_after']}s). Please contact your instructor.";
        }
    }

    if ($pinRequired && !$isUnlocked) {
        $page_title = 'Enter Exam PIN • Examify';
        $body_class = 'auth-body';
        include __DIR__ . '/../components/header.php';
        ?>
        <div class="auth-card">
            <h1>Classroom Access PIN</h1>
            <p class="subtitle">Enter the PIN provided by your instructor to unlock <strong><?= e($exam['title']) ?></strong></p>

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

    // 3. Initialize or fetch Attempt via ExamEngine
    $res = ExamEngine::getOrStartAttempt($pdo, $student_id, $exam_id, $student_semester, $student_department);
    if (!empty($res['error'])) {
        die("<h2 style='text-align:center;margin-top:100px;font-family:sans-serif;'>" . e($res['error']) . "</h2>");
    }

    $attempt = $res['attempt'];
    if (($attempt['status'] ?? '') === 'completed') {
        redirect("result.php?exam_id=$exam_id");
    }

    $attempt_id = (int) $attempt['id'];
    $total_questions = (int) $attempt['total_questions'];
    $points_per_question = ($total_questions > 0) ? round((float)$exam['total_marks'] / $total_questions, 2) : 0;

    if (!empty($res['is_new'])) {
        require_once __DIR__ . '/../utils/websocket-pusher.php';
        WebSocketPusher::emit("exam:{$exam_id}", "student_started", [
            'student_id' => $student_id,
            'attempt_id' => $attempt_id,
        ]);
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
        No questions configured for this exam.
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
                    <span class="material-symbols-outlined icon-xs">bookmark_border</span> Mark for Review
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
        <div class="alert alert-warning" style="margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">warning</span>
            <div>Tab switches, window minimization, and developer tools are recorded on the instructor dashboard.</div>
        </div>

        <!-- Dynamic Touchscreen Laptop Notice -->
        <div id="touchscreen-laptop-warning" class="alert alert-warning" style="display: none; margin-bottom: 20px; text-align: left; align-items: center; gap: 8px; background: #fef9c3; border-color: #fef08a; color: #854d0e;">
            <span class="material-symbols-outlined icon-sm" style="color: #ca8a04;">touchpad_mouse</span>
            <div>
                <strong>Touchscreen Laptop Detected:</strong> Institutional regulations strictly require using your physical touchpad or mouse. Screen taps are disabled during the examination.
            </div>
        </div>

        <button id="btn-enter-fullscreen" class="btn btn-primary btn-block" style="padding: 14px; font-size: 1.05rem; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
            <span class="material-symbols-outlined icon-md">fullscreen</span> Click to Enter Fullscreen & Begin
        </button>
        <p style="margin-top: 14px; font-size: 0.85rem; color: var(--color-text-muted);">
            Or press <strong>F11</strong> on your keyboard
        </p>
    </div>
</div>

<!-- In-DOM Examination Submission Confirmation Modal (Prevents Browser Native Dialog Blur Violations) -->
<div id="submit-confirm-modal" class="fullscreen-overlay" style="display: none; z-index: 99999;">
    <div class="overlay-card" style="max-width: 480px; text-align: center; padding: 32px 24px; position: relative;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 68px; height: 68px; border-radius: 50%; background: rgba(16, 185, 129, 0.12); color: #10b981; margin-bottom: 16px;">
            <span class="material-symbols-outlined" style="font-size: 38px;">task_alt</span>
        </div>

        <h2 style="margin: 0 0 8px; color: var(--color-dark); font-size: 1.35rem; font-weight: 800;">
            Submit Examination?
        </h2>

        <p style="color: var(--color-text-secondary); font-size: 0.95rem; line-height: 1.5; margin: 0 0 20px;">
            Are you sure you want to finish and submit your exam? Once submitted, your score will be calculated and you will not be able to modify your answers.
        </p>

        <!-- Summary Statistics -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px; display: flex; justify-content: space-around;">
            <div style="text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 800; color: #2563eb;" id="modal-answered-count">0</div>
                <div style="color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">Answered</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 800; color: #ca8a04;" id="modal-review-count">0</div>
                <div style="color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">Marked</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 1.3rem; font-weight: 800; color: #dc2626;" id="modal-unanswered-count">0</div>
                <div style="color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">Unanswered</div>
            </div>
        </div>

        <div style="display: flex; gap: 12px; justify-content: center;">
            <button type="button" id="btn-cancel-submit" class="btn btn-secondary" style="flex: 1; padding: 12px; font-weight: 600;">
                Return to Exam
            </button>
            <button type="button" id="btn-confirm-submit" class="btn btn-success" style="flex: 1; padding: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                <span class="material-symbols-outlined icon-xs">check_circle</span> Yes, Submit
            </button>
        </div>
    </div>
</div>

<script src="../utils/anti-cheat.js?v=<?= asset_version() ?>"></script>
<script src="../utils/timer.js?v=<?= asset_version() ?>"></script>
<script>
    const examId = <?= $exam_id ?>;
    const attemptId = <?= $attempt_id ?>;
    const totalQuestions = <?= $total_questions ?>;
    const pointsPerQuestion = <?= $points_per_question ?>;
    const csrfToken = '<?= csrf_token() ?>';
    let currentIndex = 0;
    let currentQuestionId = null;

    document.addEventListener('DOMContentLoaded', () => {
        AntiCheat.init({
            attemptId: attemptId,
            csrfToken: csrfToken,
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
            .then(res => {
                if (res.status === 401) {
                    alert("Your account was logged into from another device or browser. This session has been terminated.");
                    window.location.href = 'login.php?error=concurrent_session';
                    return null;
                }
                return res.json();
            })
            .then(data => {
                if (!data) return;
                if (data.concurrent_session) {
                    alert(data.error || "Your account was logged into from another device.");
                    window.location.href = 'login.php?error=concurrent_session';
                    return;
                }
                if (data.error) return alert(data.error);

                currentIndex = data.currentIndex;
                currentQuestionId = data.question.id;

                if (typeof data.seconds_left === 'number' && window.Timer) {
                    window.Timer.syncTimeLeft(data.seconds_left);
                }

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

    function setReviewButtonState(isMarked) {
        const reviewBtn = document.getElementById('btn-review');
        if (!reviewBtn) return;
        reviewBtn.dataset.marked = isMarked ? "1" : "0";
        if (isMarked) {
            reviewBtn.classList.add('is-marked');
            reviewBtn.innerHTML = '<span class="material-symbols-outlined icon-xs">bookmark</span> Unmark Review';
        } else {
            reviewBtn.classList.remove('is-marked');
            reviewBtn.innerHTML = '<span class="material-symbols-outlined icon-xs">bookmark_border</span> Mark for Review';
        }
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

        setReviewButtonState(!!marked);
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
            marked_for_review: isMarked,
            csrf_token: csrfToken
        };
        if (selected) payload.selected_option = selected;

        try {
            const res = await fetch('question.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });

            if (res.status === 401) {
                alert("Your account was logged into from another device or browser. This session has been terminated.");
                window.location.href = 'login.php?error=concurrent_session';
                return;
            }

            const data = await res.json().catch(() => ({}));
            if (data.concurrent_session) {
                alert(data.error || "Your account was logged into from another device.");
                window.location.href = 'login.php?error=concurrent_session';
            }
            if (typeof data.seconds_left === 'number' && window.Timer) {
                window.Timer.syncTimeLeft(data.seconds_left);
            }
        } catch (e) {
            console.error("Auto-sync failed:", e);
        }
    }

    // Auto-save when option is selected
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
        const wasMarked = this.dataset.marked === "1";
        const newMarked = !wasMarked;
        setReviewButtonState(newMarked);

        const btn = document.getElementById(`grid-btn-${currentIndex}`);
        if (btn) {
            if (newMarked) {
                btn.classList.add('review');
            } else {
                btn.classList.remove('review');
            }
        }
        saveCurrentAnswer();
    };

    const submitModal = document.getElementById('submit-confirm-modal');
    const cancelSubmitBtn = document.getElementById('btn-cancel-submit');
    const confirmSubmitBtn = document.getElementById('btn-confirm-submit');

    document.getElementById('btn-submit-exam').onclick = () => {
        // Calculate live answer metrics from palette
        const answeredCount = document.querySelectorAll('#grid-container .grid-btn.answered').length;
        const reviewCount = document.querySelectorAll('#grid-container .grid-btn.review').length;
        const unansweredCount = Math.max(0, totalQuestions - answeredCount);

        const ansEl = document.getElementById('modal-answered-count');
        const revEl = document.getElementById('modal-review-count');
        const unansEl = document.getElementById('modal-unanswered-count');

        if (ansEl) ansEl.innerText = answeredCount;
        if (revEl) revEl.innerText = reviewCount;
        if (unansEl) unansEl.innerText = unansweredCount;

        if (submitModal) {
            submitModal.style.display = 'flex';
        }
    };

    if (cancelSubmitBtn) {
        cancelSubmitBtn.onclick = () => {
            if (submitModal) {
                submitModal.style.display = 'none';
            }
        };
    }

    if (confirmSubmitBtn) {
        confirmSubmitBtn.onclick = async () => {
            confirmSubmitBtn.disabled = true;
            confirmSubmitBtn.innerHTML = '<span class="material-symbols-outlined icon-xs">sync</span> Submitting...';
            if (cancelSubmitBtn) cancelSubmitBtn.disabled = true;

            // Stop AntiCheat monitoring to prevent false violations during form submission & page transition
            if (window.AntiCheat && typeof window.AntiCheat.stop === 'function') {
                window.AntiCheat.stop();
            }

            await saveCurrentAnswer();
            document.getElementById('examForm').submit();
        };
    }
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
