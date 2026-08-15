<?php
require_once 'student-guard.php';
require_once '../config/database.php';


if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: No exam selected.");
}

$exam_id = (int)$_GET['id'];
$student_semester = $_SESSION['semester'];
$student_department = $_SESSION['department'];

try {
    $examSql = "SELECT e.id, e.title, e.duration_minutes, e.subject_id, e.total_questions_to_ask, e.total_marks,
                       TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE)) AS seconds_left
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
        ':department' => $student_department
    ]);
    
    $exam = $examStmt->fetch();

    if (!$exam) {
        die("Exam not found or you don't have permission.");
    }

    if ($exam['seconds_left'] <= 0) {
        die("<h2 style='text-align:center;margin-top:100px;'>Time is up! This exam has ended.</h2>");
    }

    // Check existing attempt
    $attemptStmt = $pdo->prepare("SELECT id, total_questions FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
    $attemptStmt->execute([$_SESSION['student_id'], $exam_id]);
    $attempt = $attemptStmt->fetch();

    if (!$attempt) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO exam_attempts (student_id, exam_id, total_questions) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['student_id'], $exam_id, $exam['total_questions_to_ask']]);
            $attempt_id = $pdo->lastInsertId();

            $qStmt = $pdo->prepare("SELECT id FROM questions WHERE subject_id = ? ORDER BY RAND() LIMIT " . (int)$exam['total_questions_to_ask']);
            $qStmt->execute([$exam['subject_id']]);
            $random_questions = $qStmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($random_questions) < $exam['total_questions_to_ask']) {
                throw new Exception("Not enough questions available.");
            }

            $ansStmt = $pdo->prepare("INSERT INTO student_answers (attempt_id, question_id) VALUES (?, ?)");
            foreach ($random_questions as $q_id) {
                $ansStmt->execute([$attempt_id, $q_id]);
            }

            $pdo->commit();
            $total_questions = $exam['total_questions_to_ask'];
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error starting exam: " . $e->getMessage());
        }
    } else {
        $total_questions = $attempt['total_questions'];
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($exam['title']) ?> • Examify</title>
    <!-- <link rel="stylesheet" href="../assets/css/student.css"> -->
    <style>

        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 40;
        }
        .header h1 {
            font-size: 1.25rem;
            font-weight: 600;
        }
        .timer {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--danger);
            background: #fef2f2;
            padding: 6px 14px;
            border-radius: 8px;
        }

        /* Layout */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 20px;
            display: grid;
            grid-template-columns: 1fr 260px;
            gap: 24px;
        }

        /* Question Card */
        .question-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px;
        }
        .question-meta {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 12px;
        }
        .question-text {
            font-size: 1.15rem;
            font-weight: 500;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        /* Options - Fixed Alignment */
        .options {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .option {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            transition: 0.15s;
            background: white;
        }
        .option:hover {
            border-color: #93c5fd;
            background: #f0f7ff;
        }
        .option input {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
            flex-shrink: 0;
        }
        .option span {
            flex: 1;
            font-size: 0.98rem;
        }

        /* Navigation */
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            gap: 12px;
        }
        .btn {
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-warning { background: #fef3c7; color: #92400e; }
        .btn-warning:hover { background: #fde68a; }

        /* Sidebar */
        .sidebar {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 80px;
        }
        .sidebar h3 {
            font-size: 1rem;
            margin-bottom: 14px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        .grid-btn {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            background: white;
            transition: 0.15s;
        }
        .grid-btn:hover { background: #f1f5f9; }
        .grid-btn.answered {
            background: #dcfce7;
            border-color: #86efac;
            color: var(--success);
        }
        .grid-btn.review {
            border: 2px solid var(--warning);
        }
        .grid-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .legend {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 20px;
        }
        .legend div {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .dot {
            width: 14px;
            height: 14px;
            border-radius: 4px;
            border: 1px solid var(--border);
        }
        .dot.answered { background: #dcfce7; border-color: #86efac; }
        .dot.review { border: 2px solid var(--warning); }
        .dot.unanswered { background: white; }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
        }
        .btn-submit:hover { background: #15803d; }

        /* Overlay */
        #exam-start-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.92);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 20px;
        }
        #exam-start-overlay h2 { font-size: 1.8rem; margin-bottom: 12px; }
        #exam-start-overlay p { color: #94a3b8; margin-bottom: 8px; }
        #exam-start-overlay .warning { color: #fca5a5; font-weight: 600; margin-top: 16px; }

        /* Mobile */
        @media (max-width: 800px) {
            .container {
                grid-template-columns: 1fr;
            }
            .sidebar {
                position: static;
                order: -1;
            }
            .grid {
                grid-template-columns: repeat(8, 1fr);
            }
        }





    </style>
   
</head>
<body>

<div class="header">
    <h1><?= htmlspecialchars($exam['title']) ?></h1>
    <div class="timer" id="timerDisplay" data-time-left="<?= max(0, $exam['seconds_left']) ?>">
        Time Left: --:--
    </div>
</div>

<?php if ($total_questions === 0): ?>
    <div style="text-align:center;padding:80px 20px;color:var(--gray);">
        No questions available for this exam.
    </div>
<?php else: ?>

<div class="container">
    <!-- Question Area -->
    <div class="question-card">
        <div id="question-container">
            <p style="color:var(--gray)">Loading question...</p>
        </div>

        <div class="nav-buttons">
            <button type="button" id="btn-prev" class="btn btn-secondary">← Previous</button>
            <button type="button" id="btn-review" class="btn btn-warning" data-marked="0">Mark for Review</button>
            <button type="button" id="btn-next" class="btn btn-primary">Next →</button>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <h3>Question Map</h3>
        <div class="grid" id="grid-container"></div>

        <div class="legend">
            <div><span class="dot answered"></span> Answered</div>
            <div><span class="dot review"></span> Marked for Review</div>
            <div><span class="dot unanswered"></span> Not Answered</div>
        </div>

        <form action="result.php" method="POST" id="examForm">
            <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
            <button type="button" id="btn-submit-exam" class="btn-submit">Submit Exam</button>
        </form>
    </div>
</div>

<?php endif; ?>

<!-- Start Overlay -->
<div id="exam-start-overlay">
    <h2>Ready to begin?</h2>
    <p>Press <strong>F11</strong> to enter full-screen mode</p>
    <p class="warning">Switching tabs or exiting full-screen will be recorded as a violation</p>
</div>

<script src="../utils/anti-cheat.js"></script>
<script src="../utils/timer.js"></script>
<script>
    const examId = <?= $exam_id ?>;
    const totalQuestions = <?= $total_questions ?>;
    let currentIndex = 0;
    let currentQuestionId = null;

    document.addEventListener('DOMContentLoaded', () => {
        AntiCheat.init({
            onViolation: (count, reason) => console.warn(`Violation ${count}: ${reason}`),
            onTerminate: () => {
                alert("Exam terminated due to violations. Submitting answers.");
                document.getElementById('examForm').submit();
            }
        });

        if (totalQuestions > 0) loadQuestion(0);
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
            .catch(err => console.error(err));
    }

    function renderQuestion(q, selected, marked) {
        const container = document.getElementById('question-container');
        let html = `
            <div class="question-meta">Question ${currentIndex + 1} of ${totalQuestions} • ${q.marks} Mark${q.marks > 1 ? 's' : ''}</div>
            <div class="question-text">${q.question_text}</div>
            <div class="options">
        `;

        ['A', 'B', 'C', 'D'].forEach(opt => {
            const text = q['option_' + opt.toLowerCase()];
            if (text && text.trim() !== '') {
                const checked = selected === opt ? 'checked' : '';
                html += `
                    <label class="option">
                        <input type="radio" name="answer" value="${opt}" ${checked}>
                        <span><strong>${opt}.</strong> ${text}</span>
                    </label>
                `;
            }
        });

        html += `</div>`;
        container.innerHTML = html;

        const reviewBtn = document.getElementById('btn-review');
        reviewBtn.dataset.marked = marked ? "1" : "0";
        reviewBtn.innerText = marked ? "Unmark Review" : "Mark for Review";
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
        document.getElementById('btn-prev').style.visibility = currentIndex > 0 ? 'visible' : 'hidden';
        document.getElementById('btn-next').style.visibility = currentIndex < totalQuestions - 1 ? 'visible' : 'hidden';
    }

    async function saveCurrentAnswer() {
        if (!currentQuestionId) return;

        const selected = document.querySelector('input[name="answer"]:checked')?.value || null;
        const isMarked = document.getElementById('btn-review').dataset.marked === "1";

        const payload = {
            exam_id: examId,
            question_id: currentQuestionId,
            marked_for_review: isMarked
        };
        if (selected) payload.selected_option = selected;

        await fetch('question.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
    }

    // Auto-save when option selected
    document.getElementById('question-container').addEventListener('change', e => {
        if (e.target.name === 'answer') {
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
        if (btn) isMarked ? btn.classList.remove('review') : btn.classList.add('review');
        saveCurrentAnswer();
    };

    document.getElementById('btn-submit-exam').onclick = async () => {
        if (confirm("Are you sure you want to submit the exam?")) {
            await saveCurrentAnswer();
            document.getElementById('examForm').submit();
        }
    };
</script>
</body>
</html>