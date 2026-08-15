<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input && isset($input['exam_id'], $input['question_id'])) {
        $exam_id = (int)$input['exam_id'];
        $question_id = (int)$input['question_id'];
        
        if (!isset($_SESSION['exam_answers'])) $_SESSION['exam_answers'] = [];
        if (!isset($_SESSION['exam_answers'][$exam_id])) $_SESSION['exam_answers'][$exam_id] = [];
        
        if (!isset($_SESSION['exam_reviews'])) $_SESSION['exam_reviews'] = [];
        if (!isset($_SESSION['exam_reviews'][$exam_id])) $_SESSION['exam_reviews'][$exam_id] = [];
        
        // Save selected option
        if (isset($input['selected_option'])) {
            $_SESSION['exam_answers'][$exam_id][$question_id] = trim(strip_tags($input['selected_option']));
        }
        
        // Save review status
        if (isset($input['marked_for_review'])) {
            $_SESSION['exam_reviews'][$exam_id][$question_id] = (bool)$input['marked_for_review'];
        }
        
        echo json_encode(['success' => true]);
        exit();
    }
    
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

// Handle GET: Fetch Question
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['exam_id']) || !isset($_GET['index'])) {
        echo json_encode(['error' => 'Missing parameters']);
        exit();
    }

    $exam_id = (int)$_GET['exam_id'];
    $index = (int)$_GET['index']; // 0-based index

    try {
        // Find the attempt
        $attemptStmt = $pdo->prepare("SELECT id, total_questions FROM exam_attempts WHERE student_id = :student_id AND exam_id = :exam_id");
        $attemptStmt->execute([':student_id' => $_SESSION['student_id'], ':exam_id' => $exam_id]);
        $attempt = $attemptStmt->fetch();

        if (!$attempt) {
            echo json_encode(['error' => 'Exam attempt not initialized']);
            exit();
        }

        $attempt_id = $attempt['id'];
        $total_questions = (int)$attempt['total_questions'];

        if ($index < 0 || $index >= $total_questions) {
            echo json_encode(['error' => 'Question index out of bounds']);
            exit();
        }

        // Get the specific question by index from the student's assigned questions (REMOVED q.marks)
        $qSql = "SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d
                 FROM student_answers sa
                 JOIN questions q ON sa.question_id = q.id
                 WHERE sa.attempt_id = :attempt_id 
                 ORDER BY sa.id ASC 
                 LIMIT 1 OFFSET :index";
        $qStmt = $pdo->prepare($qSql);
        $qStmt->bindValue(':attempt_id', $attempt_id, PDO::PARAM_INT);
        $qStmt->bindValue(':index', $index, PDO::PARAM_INT);
        $qStmt->execute();
        
        $question = $qStmt->fetch(PDO::FETCH_ASSOC);

        // Fetch ordered list of all assigned question IDs for grid mapping
        $idsSql = "SELECT question_id FROM student_answers WHERE attempt_id = :attempt_id ORDER BY id ASC";
        $idsStmt = $pdo->prepare($idsSql);
        $idsStmt->execute([':attempt_id' => $attempt_id]);
        $all_ids = $idsStmt->fetchAll(PDO::FETCH_COLUMN);

        if ($question) {
            $q_id = $question['id'];
            $selected = $_SESSION['exam_answers'][$exam_id][$q_id] ?? null;
            $marked = $_SESSION['exam_reviews'][$exam_id][$q_id] ?? false;

            echo json_encode([
                'success' => true,
                'question' => $question,
                'total' => (int)$total_questions,
                'currentIndex' => $index,
                'question_ids' => $all_ids,
                'selected_option' => $selected,
                'marked_for_review' => $marked,
                'all_answers' => $_SESSION['exam_answers'][$exam_id] ?? [],
                'all_reviews' => $_SESSION['exam_reviews'][$exam_id] ?? []
            ]);
        } else {
            echo json_encode(['error' => 'Question not found']);
        }

    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}