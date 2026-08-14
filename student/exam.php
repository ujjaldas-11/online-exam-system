<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: No exam selected. Please return to the dashboard.");
}

$exam_id = (int)$_GET['id'];
$student_semester = $_SESSION['semester'];
$student_department = $_SESSION['department'];

try {
    $examSql = "SELECT e.id, e.title, e.duration_minutes, e.subject_id, e.total_questions_to_ask, e.total_marks
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
        die("Error: Exam not found or you do not have permission to take this exam.");
    }

    // Check if an attempt already exists
    $attemptSql = "SELECT id, total_questions FROM exam_attempts WHERE student_id = :student_id AND exam_id = :exam_id";
    $attemptStmt = $pdo->prepare($attemptSql);
    $attemptStmt->execute([':student_id' => $_SESSION['student_id'], ':exam_id' => $exam_id]);
    $attempt = $attemptStmt->fetch();

    if (!$attempt) {
        // Create new attempt and assign random questions
        $pdo->beginTransaction();
        
        try {
            // Insert attempt
            $insertAttempt = "INSERT INTO exam_attempts (student_id, exam_id, total_questions) VALUES (:student_id, :exam_id, :total_q)";
            $stmt = $pdo->prepare($insertAttempt);
            $stmt->execute([
                ':student_id' => $_SESSION['student_id'],
                ':exam_id' => $exam_id,
                ':total_q' => $exam['total_questions_to_ask']
            ]);
            $attempt_id = $pdo->lastInsertId();
            
            // Get random questions
            $qSql = "SELECT id FROM questions WHERE subject_id = :subject_id ORDER BY RAND() LIMIT " . (int)$exam['total_questions_to_ask'];
            $qStmt = $pdo->prepare($qSql);
            $qStmt->execute([':subject_id' => $exam['subject_id']]);
            $random_questions = $qStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($random_questions) < $exam['total_questions_to_ask']) {
                throw new Exception("Not enough questions in the subject pool.");
            }
            
            // Insert into student_answers
            $insertAnswer = "INSERT INTO student_answers (attempt_id, question_id) VALUES (:attempt_id, :question_id)";
            $ansStmt = $pdo->prepare($insertAnswer);
            foreach ($random_questions as $q_id) {
                $ansStmt->execute([':attempt_id' => $attempt_id, ':question_id' => $q_id]);
            }
            
            $pdo->commit();
            $total_questions = $exam['total_questions_to_ask'];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Error initializing exam: " . $e->getMessage());
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
    <title><?php echo htmlspecialchars($exam['title']); ?> - Examify</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .exam-layout { display: flex; gap: 20px; }
        .question-area { flex: 3; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .grid-area { flex: 1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .grid-container { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .grid-btn { width: 40px; height: 40px; display: flex; justify-content: center; align-items: center; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; background: #f9f9f9; font-weight: bold; }
        .grid-btn:hover { background: #e9ecef; }
        .grid-btn.answered { background: #28a745; color: white; border-color: #218838; }
        .grid-btn.review { border: 3px solid #fd7e14; }
        .grid-btn.active { box-shadow: 0 0 8px rgba(0,123,255,0.8); border-color: #007bff; }
        
        .nav-buttons { margin-top: 30px; display: flex; justify-content: space-between; border-top: 1px solid #eee; padding-top: 20px; }
        .btn-blue { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-orange { background: #fd7e14; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-green { background: #28a745; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; width: 100%; margin-top: 20px; }
        .btn-blue:hover { background: #0056b3; }
        .btn-orange:hover { background: #e86e10; }
        .btn-green:hover { background: #218838; }
        
        .option-label { display: block; margin: 10px 0; padding: 12px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; background: #fafafa; transition: background 0.2s; }
        .option-label:hover { background: #f0f0f0; }
        .option-label input { margin-right: 10px; transform: scale(1.2); }
    </style>
</head>
<body>
    <div class="exam-container" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
        <header class="exam-header" style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <h1 style="margin: 0; font-size: 24px;"><?php echo htmlspecialchars($exam['title']); ?></h1>
            <div class="timer" style="font-size: 18px; font-weight: bold; color: #dc3545;">
                Time Allowed: <?php echo htmlspecialchars($exam['duration_minutes']); ?> minutes
            </div>
        </header>

        <?php if ($total_questions === 0): ?>
            <p>No questions have been added to this exam yet.</p>
        <?php else: ?>
            <div class="exam-layout">
                <!-- Left Side: Question Display -->
                <div class="question-area">
                    <div id="question-container">
                        <h3 style="color: #666;">Loading question...</h3>
                    </div>
                    
                    <div class="nav-buttons">
                        <button type="button" id="btn-prev" class="btn-blue">Previous</button>
                        <button type="button" id="btn-review" class="btn-orange" data-marked="0">Mark for Review</button>
                        <button type="button" id="btn-next" class="btn-blue">Next</button>
                    </div>
                </div>
                
                <!-- Right Side: Grid and Submit -->
                <div class="grid-area">
                    <h3 style="margin-top: 0;">Question Map</h3>
                    <div class="grid-container" id="grid-container">
                        <!-- Rendered by JS -->
                    </div>
                    
                    <form action="result.php" method="POST" id="examForm">
                        <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam['id']); ?>">
                        <button type="button" id="btn-submit-exam" class="btn-green">Final Submit Exam</button>
                    </form>
                    
                    <div style="margin-top: 20px; font-size: 13px; color: #666;">
                        <p><span style="display:inline-block;width:15px;height:15px;background:#28a745;margin-right:5px;"></span> Answered</p>
                        <p><span style="display:inline-block;width:15px;height:15px;border:3px solid #fd7e14;margin-right:5px;box-sizing:border-box;"></span> Marked for Review</p>
                        <p><span style="display:inline-block;width:15px;height:15px;background:#f9f9f9;border:1px solid #ccc;margin-right:5px;"></span> Unanswered</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Exam Overlay for Anti-Cheat -->
    <div id="exam-start-overlay" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.95);z-index:9999;display:flex;justify-content:center;align-items:center;flex-direction:column;font-family:Arial,sans-serif;">
        <h2 style="font-size: 32px; color: #333;">Welcome to the Exam</h2>
        <p style="font-size: 20px; margin-bottom: 30px;">Press <strong>F11</strong> to enter full-screen mode and start the exam.</p>
        <p style="color: #d9534f; font-weight: bold;">Note: Exiting full-screen or switching tabs will result in a violation!</p>
    </div>

    <script src="../utils/anti-cheat.js"></script>
    <script>
        const examId = <?php echo $exam_id; ?>;
        const totalQuestions = <?php echo $total_questions; ?>;
        let currentIndex = 0;
        let currentQuestionId = null;

        document.addEventListener('DOMContentLoaded', () => {
            // Init Anti-Cheat
            AntiCheat.init({
                onViolation: (count, reason) => {
                    console.warn(`Violation ${count}: ${reason}`);
                },
                onTerminate: () => {
                    alert("Exam terminated due to cheating. Submitting your answers as-is.");
                    document.getElementById('examForm').submit();
                }
            });

            // If there are questions, load the first one
            if(totalQuestions > 0) {
                loadQuestion(0);
            }
            
            // Listen to exam start event triggered by anti-cheat.js
            document.addEventListener('examStarted', () => {
                // The overlay is hidden by anti-cheat.js itself, but we can do extra setup here if needed
                console.log('Exam officially started.');
            });
        });

        function loadQuestion(index) {
            fetch(`question.php?exam_id=${examId}&index=${index}`)
            .then(res => res.json())
            .then(data => {
                if(data.error) {
                    alert(data.error);
                    return;
                }
                currentIndex = data.currentIndex;
                currentQuestionId = data.question.id;
                
                renderQuestion(data.question, data.selected_option, data.marked_for_review);
                renderGrid(data.total, data.all_answers, data.all_reviews, data.question_ids);
                updateNavButtons();
            })
            .catch(err => console.error("Failed to fetch question", err));
        }

        function renderQuestion(q, selected, marked) {
            const container = document.getElementById('question-container');
            let html = `
                <h3 style="margin-top: 0; font-size: 20px;">Question ${currentIndex + 1} 
                    <span style="font-size: 0.8em; color: #666; font-weight: normal;">[${q.marks} Marks]</span>
                </h3>
                <p style="font-size: 18px; margin-bottom: 25px;">${q.question_text}</p>
                <div class="options">
            `;
            
            ['A', 'B', 'C', 'D'].forEach(opt => {
                const optText = q['option_' + opt.toLowerCase()];
                if (optText && optText.trim() !== '') {
                    const isChecked = (selected === opt) ? 'checked' : '';
                    html += `
                        <label class="option-label">
                            <input type="radio" name="answer" value="${opt}" ${isChecked}>
                            <strong>${opt})</strong> ${optText}
                        </label>
                    `;
                }
            });
            html += `</div>`;
            container.innerHTML = html;
            
            // Update Review Button State
            const reviewBtn = document.getElementById('btn-review');
            if(marked) {
                reviewBtn.innerText = "Unmark Review";
                reviewBtn.dataset.marked = "1";
            } else {
                reviewBtn.innerText = "Mark for Review";
                reviewBtn.dataset.marked = "0";
            }
        }

        function renderGrid(total, answersObj, reviewsObj, allIds) {
            const grid = document.getElementById('grid-container');
            grid.innerHTML = '';
            
            for (let i = 0; i < total; i++) {
                const qId = allIds[i];
                const btn = document.createElement('div');
                btn.className = 'grid-btn';
                btn.innerText = i + 1;
                btn.id = `grid-btn-${i}`;
                
                if (answersObj[qId]) {
                    btn.classList.add('answered');
                }
                if (reviewsObj[qId]) {
                    btn.classList.add('review');
                }
                if (i === currentIndex) {
                    btn.classList.add('active');
                }
                
                btn.addEventListener('click', () => {
                    saveCurrentAnswer().then(() => loadQuestion(i));
                });
                
                grid.appendChild(btn);
            }
        }
        
        function updateNavButtons() {
            document.getElementById('btn-prev').style.display = (currentIndex > 0) ? 'inline-block' : 'none';
            document.getElementById('btn-next').style.display = (currentIndex < totalQuestions - 1) ? 'inline-block' : 'none';
        }

        async function saveCurrentAnswer() {
            if (currentQuestionId === null) return;
            
            const selectedRadio = document.querySelector('input[name="answer"]:checked');
            const selected = selectedRadio ? selectedRadio.value : null;
            const isMarked = document.getElementById('btn-review').dataset.marked === "1";
            
            const payload = {
                exam_id: examId,
                question_id: currentQuestionId,
                marked_for_review: isMarked
            };
            
            if (selected !== null) {
                payload.selected_option = selected;
            }
            
            await fetch('question.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
        }
        
        // Auto-save on radio change
        document.getElementById('question-container').addEventListener('change', function(e) {
            if(e.target.name === 'answer') {
                saveCurrentAnswer().then(() => {
                    const gridBtn = document.getElementById(`grid-btn-${currentIndex}`);
                    if(gridBtn) gridBtn.classList.add('answered');
                });
            }
        });

        // Navigation listeners
        document.getElementById('btn-prev').addEventListener('click', () => {
            if (currentIndex > 0) {
                saveCurrentAnswer().then(() => loadQuestion(currentIndex - 1));
            }
        });

        document.getElementById('btn-next').addEventListener('click', () => {
            if (currentIndex < totalQuestions - 1) {
                saveCurrentAnswer().then(() => loadQuestion(currentIndex + 1));
            }
        });

        document.getElementById('btn-review').addEventListener('click', function() {
            const isMarked = this.dataset.marked === "1";
            this.dataset.marked = isMarked ? "0" : "1";
            this.innerText = isMarked ? "Mark for Review" : "Unmark Review";
            
            const gridBtn = document.getElementById(`grid-btn-${currentIndex}`);
            if(gridBtn) {
                if(!isMarked) { 
                    gridBtn.classList.add('review');
                } else {
                    gridBtn.classList.remove('review');
                }
            }
            saveCurrentAnswer();
        });
        
        document.getElementById('btn-submit-exam').addEventListener('click', async () => {
            if(confirm("Are you sure you want to final submit your exam?")) {
                await saveCurrentAnswer();
                document.getElementById('examForm').submit();
            }
        });
    </script>
</body>
</html>