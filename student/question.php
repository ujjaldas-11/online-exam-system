<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/sanitize.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/device.php';
require_once __DIR__ . '/../services/ExamEngine.php';

init_secure_session();

if (is_mobile_or_tablet()) {
    release_session_lock();
    json_response(['error' => 'Desktop workstation required to take examinations.', 'desktop_required' => true], 403);
}

if (empty($_SESSION['student_id'])) {
    release_session_lock();
    json_response(['error' => 'Not authenticated'], 401);
}

if (!verify_active_session($pdo, 'student', (int) $_SESSION['student_id'])) {
    release_session_lock();
    json_response(['error' => 'Your session was terminated because your account was logged in from another device.', 'concurrent_session' => true], 401);
}

$student_id = (int) $_SESSION['student_id'];

// Handle POST: Autosave Answer & Review status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['exam_id']) || empty($input['question_id'])) {
        release_session_lock();
        json_response(['error' => 'Invalid parameters'], 400);
    }

    // Verify CSRF token from header or body
    $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!is_csrf_valid($token)) {
        release_session_lock();
        json_response(['error' => 'CSRF verification failed'], 403);
    }

    $exam_id = int_param($input['exam_id']);
    $question_id = int_param($input['question_id']);
    $selectedOption = isset($input['selected_option']) ? (string) $input['selected_option'] : null;
    $markedForReview = isset($input['marked_for_review']) ? (bool) $input['marked_for_review'] : null;

    // Release session lock immediately so subsequent requests are not blocked
    release_session_lock();

    $res = ExamEngine::saveAnswer($pdo, $student_id, $exam_id, $question_id, $selectedOption, $markedForReview);

    if (!empty($res['error'])) {
        json_response(['error' => $res['error']], $res['code'] ?? 400);
    }

    json_response(['success' => true]);
}

// Handle GET: Fetch Question and Map Data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['exam_id']) || !isset($_GET['index'])) {
        release_session_lock();
        json_response(['error' => 'Missing parameters'], 400);
    }

    $exam_id = int_param($_GET['exam_id']);
    $index = int_param($_GET['index']);

    // Release session lock before DB query
    release_session_lock();

    try {
        // 1. Fetch Attempt
        $attemptStmt = $pdo->prepare("SELECT id, total_questions, status FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
        $attemptStmt->execute([$student_id, $exam_id]);
        $attempt = $attemptStmt->fetch();

        if (!$attempt) {
            json_response(['error' => 'Exam attempt not initialized'], 404);
        }

        if ($attempt['status'] === 'completed') {
            json_response(['error' => 'Exam already submitted', 'completed' => true], 400);
        }

        $attempt_id = (int) $attempt['id'];
        $total_questions = (int) $attempt['total_questions'];

        if ($index < 0 || $index >= $total_questions) {
            json_response(['error' => 'Question index out of bounds'], 400);
        }

        // 2. Fetch specific question by offset (Zero-leakage: correct_option is NEVER selected)
        $qSql = "
            SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                   sa.selected_option, sa.marked_for_review
            FROM student_answers sa
            JOIN questions q ON sa.question_id = q.id
            WHERE sa.attempt_id = :attempt_id
            ORDER BY sa.id ASC
            LIMIT 1 OFFSET :index
        ";
        $qStmt = $pdo->prepare($qSql);
        $qStmt->bindValue(':attempt_id', $attempt_id, PDO::PARAM_INT);
        $qStmt->bindValue(':index', $index, PDO::PARAM_INT);
        $qStmt->execute();

        $questionRow = $qStmt->fetch();

        if (!$questionRow) {
            json_response(['error' => 'Question not found'], 404);
        }

        // 3. Fetch grid mapping for entire exam
        $allSql = "SELECT question_id, selected_option, marked_for_review FROM student_answers WHERE attempt_id = ? ORDER BY id ASC";
        $allStmt = $pdo->prepare($allSql);
        $allStmt->execute([$attempt_id]);
        $allRows = $allStmt->fetchAll();

        $all_ids = [];
        $all_answers = [];
        $all_reviews = [];

        foreach ($allRows as $r) {
            $qid = (int) $r['question_id'];
            $all_ids[] = $qid;
            if (!empty($r['selected_option'])) {
                $all_answers[$qid] = $r['selected_option'];
            }
            if (!empty($r['marked_for_review'])) {
                $all_reviews[$qid] = true;
            }
        }

        json_response([
            'success' => true,
            'question' => [
                'id' => (int) $questionRow['id'],
                'question_text' => clean_input($questionRow['question_text']),
                'option_a' => clean_input($questionRow['option_a']),
                'option_b' => clean_input($questionRow['option_b']),
                'option_c' => clean_input($questionRow['option_c']),
                'option_d' => clean_input($questionRow['option_d']),
            ],
            'total' => $total_questions,
            'currentIndex' => $index,
            'question_ids' => $all_ids,
            'selected_option' => $questionRow['selected_option'],
            'marked_for_review' => (bool) $questionRow['marked_for_review'],
            'all_answers' => $all_answers,
            'all_reviews' => $all_reviews,
        ]);
    } catch (PDOException $e) {
        log_error("Failed fetching question index $index for attempt", $e);
        json_response(['error' => 'Database query failed'], 500);
    }
}
