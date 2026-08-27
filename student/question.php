<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/sanitize.php';

init_secure_session();

if (empty($_SESSION['student_id'])) {
    json_response(['error' => 'Not authenticated'], 401);
}

$student_id = (int) $_SESSION['student_id'];

// Handle POST: Save Answer / Review status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input && isset($input['exam_id'], $input['question_id'])) {
        $exam_id = int_param($input['exam_id']);
        $question_id = int_param($input['question_id']);

        if (!isset($_SESSION['exam_answers'])) {
            $_SESSION['exam_answers'] = [];
        }
        if (!isset($_SESSION['exam_answers'][$exam_id])) {
            $_SESSION['exam_answers'][$exam_id] = [];
        }

        if (!isset($_SESSION['exam_reviews'])) {
            $_SESSION['exam_reviews'] = [];
        }
        if (!isset($_SESSION['exam_reviews'][$exam_id])) {
            $_SESSION['exam_reviews'][$exam_id] = [];
        }

        // Save selected option in session and database
        if (isset($input['selected_option'])) {
            $selected = strtoupper(clean_input((string) $input['selected_option']));
            if (!in_array($selected, ['A', 'B', 'C', 'D', ''], true)) {
                session_write_close();
                json_response(['error' => 'Invalid option choice'], 400);
            }

            if ($selected === '') {
                $selected = null;
            }

            $_SESSION['exam_answers'][$exam_id][$question_id] = $selected;

            // Direct database backup to prevent data loss on browser crash
            try {
                $syncStmt = $pdo->prepare("
                    UPDATE student_answers sa
                    JOIN exam_attempts ea ON sa.attempt_id = ea.id
                    SET sa.selected_option = ?
                    WHERE ea.student_id = ? AND ea.exam_id = ? AND sa.question_id = ?
                ");
                $syncStmt->execute([$selected, $student_id, $exam_id, $question_id]);
            } catch (PDOException) {
                // Failover gracefully to session storage
            }
        }

        // Save review status
        if (isset($input['marked_for_review'])) {
            $_SESSION['exam_reviews'][$exam_id][$question_id] = (bool) $input['marked_for_review'];
        }

        // Release session lock to unblock concurrent requests
        session_write_close();

        json_response(['success' => true]);
    }

    session_write_close();
    json_response(['error' => 'Invalid input'], 400);
}

// Handle GET: Fetch Question
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['exam_id']) || !isset($_GET['index'])) {
        session_write_close();
        json_response(['error' => 'Missing parameters'], 400);
    }

    $exam_id = int_param($_GET['exam_id']);
    $index = int_param($_GET['index']);

    // Read session data before closing lock
    $sessionAnswers = $_SESSION['exam_answers'][$exam_id] ?? [];
    $sessionReviews = $_SESSION['exam_reviews'][$exam_id] ?? [];

    session_write_close();

    try {
        // Find the attempt
        $attemptStmt = $pdo->prepare("SELECT id, total_questions FROM exam_attempts WHERE student_id = :student_id AND exam_id = :exam_id");
        $attemptStmt->execute([':student_id' => $student_id, ':exam_id' => $exam_id]);
        $attempt = $attemptStmt->fetch();

        if (!$attempt) {
            json_response(['error' => 'Exam attempt not initialized'], 404);
        }

        $attempt_id = (int) $attempt['id'];
        $total_questions = (int) $attempt['total_questions'];

        if ($index < 0 || $index >= $total_questions) {
            json_response(['error' => 'Question index out of bounds'], 400);
        }

        // Get the specific question by index from the student's assigned questions
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
        $idsSql = "SELECT question_id, selected_option FROM student_answers WHERE attempt_id = :attempt_id ORDER BY id ASC";
        $idsStmt = $pdo->prepare($idsSql);
        $idsStmt->execute([':attempt_id' => $attempt_id]);
        $dbRows = $idsStmt->fetchAll(PDO::FETCH_ASSOC);

        $all_ids = array_column($dbRows, 'question_id');

        // Merge DB saved answers with session answers
        foreach ($dbRows as $row) {
            if (!empty($row['selected_option']) && empty($sessionAnswers[$row['question_id']])) {
                $sessionAnswers[$row['question_id']] = $row['selected_option'];
            }
        }

        if ($question) {
            $q_id = (int) $question['id'];
            $selected = $sessionAnswers[$q_id] ?? null;
            $marked = $sessionReviews[$q_id] ?? false;

            json_response([
                'success' => true,
                'question' => [
                    'id' => $q_id,
                    'question_text' => clean_input($question['question_text'] ?? ''),
                    'option_a' => clean_input($question['option_a'] ?? ''),
                    'option_b' => clean_input($question['option_b'] ?? ''),
                    'option_c' => clean_input($question['option_c'] ?? ''),
                    'option_d' => clean_input($question['option_d'] ?? ''),
                ],
                'total' => $total_questions,
                'currentIndex' => $index,
                'question_ids' => $all_ids,
                'selected_option' => $selected,
                'marked_for_review' => $marked,
                'all_answers' => $sessionAnswers,
                'all_reviews' => $sessionReviews,
            ]);
        } else {
            json_response(['error' => 'Question not found'], 404);
        }

    } catch (PDOException $e) {
        json_response(['error' => 'Database query failed'], 500);
    }
}
