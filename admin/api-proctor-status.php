<?php

declare(strict_types=1);

require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/sanitize.php';

header('Content-Type: application/json; charset=utf-8');

$exam_id = int_param($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) {
    json_response(['success' => false, 'error' => 'Invalid examination ID'], 400);
}

try {
    $examStmt = $pdo->prepare("
        SELECT e.*, s.name AS subject_name, s.department, s.semester,
            TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE)) AS seconds_left
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.id = ?
    ");
    $examStmt->execute([$exam_id]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        json_response(['success' => false, 'error' => 'Exam not found'], 404);
    }

    $rosterStmt = $pdo->prepare("
        SELECT st.id AS student_id, st.name, st.roll_number, st.email,
            ea.id AS attempt_id, ea.status AS attempt_status, ea.score, ea.started_at, ea.submitted_at,
            (SELECT COUNT(*) FROM student_answers WHERE attempt_id = ea.id AND selected_option IS NOT NULL AND selected_option != '') AS answered_count,
            (SELECT COUNT(*) FROM exam_violations WHERE attempt_id = ea.id) AS violation_count
        FROM students st
        LEFT JOIN exam_attempts ea ON st.id = ea.student_id AND ea.exam_id = ?
        WHERE st.department = ? AND st.semester = ? AND st.status = 'active'
        ORDER BY st.roll_number ASC
    ");
    $rosterStmt->execute([$exam_id, $exam['department'], $exam['semester']]);
    $students = $rosterStmt->fetchAll();

    $in_progress_count = 0;
    $completed_count = 0;
    $not_started_count = 0;
    $total_violations = 0;

    $studentList = [];
    foreach ($students as $st) {
        $status = empty($st['attempt_id']) ? 'not_started' : ($st['attempt_status'] ?? 'in_progress');
        if ($status === 'not_started') {
            $not_started_count++;
        } elseif ($status === 'completed') {
            $completed_count++;
        } else {
            $in_progress_count++;
        }

        $viol = (int) ($st['violation_count'] ?? 0);
        $total_violations += $viol;

        $studentList[] = [
            'student_id' => (int) $st['student_id'],
            'name' => $st['name'],
            'roll_number' => $st['roll_number'],
            'attempt_id' => $st['attempt_id'] ? (int) $st['attempt_id'] : null,
            'attempt_status' => $status,
            'answered_count' => (int) ($st['answered_count'] ?? 0),
            'total_questions' => (int) ($exam['total_questions_to_ask'] ?? 0),
            'score' => $st['score'] !== null ? (float) $st['score'] : null,
            'violation_count' => $viol,
        ];
    }

    json_response([
        'success' => true,
        'exam_id' => $exam_id,
        'seconds_left' => max(0, (int) ($exam['seconds_left'] ?? 0)),
        'total_marks' => (int) ($exam['total_marks'] ?? 0),
        'summary' => [
            'total_enrolled' => count($students),
            'in_progress' => $in_progress_count,
            'completed' => $completed_count,
            'not_started' => $not_started_count,
            'total_violations' => $total_violations,
        ],
        'students' => $studentList,
    ]);
} catch (PDOException $e) {
    json_response(['success' => false, 'error' => 'Database error'], 500);
}
