<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/sanitize.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/rate-limiter.php';

init_secure_session();

if (empty($_SESSION['student_id'])) {
    release_session_lock();
    json_response(['error' => 'Unauthorized'], 401);
}

if (!verify_active_session($pdo, 'student', (int) $_SESSION['student_id'])) {
    release_session_lock();
    json_response(['error' => 'Concurrent session detected.'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['attempt_id']) || empty($input['violation_type'])) {
    release_session_lock();
    json_response(['error' => 'Invalid parameters'], 400);
}

$token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!is_csrf_valid($token)) {
    release_session_lock();
    json_response(['error' => 'CSRF verification failed'], 403);
}

$attempt_id = int_param($input['attempt_id']);
$violation_type = clean_input($input['violation_type']);
$details = clean_input($input['details'] ?? '');
$student_id = (int) $_SESSION['student_id'];

// Release session lock to unblock concurrent client requests
release_session_lock();

// Rate limit violation reporting (max 30 events per minute per attempt)
$violRate = RateLimiter::hit($pdo, "api:viol:attempt:{$attempt_id}", 60, 30);
if (!$violRate['allowed']) {
    json_response(['error' => 'Rate limit exceeded for violation events.'], 429);
}

try {
    // Verify attempt belongs to current student
    $checkStmt = $pdo->prepare("SELECT id, exam_id FROM exam_attempts WHERE id = ? AND student_id = ?");
    $checkStmt->execute([$attempt_id, $student_id]);
    $attemptRow = $checkStmt->fetch();
    if (!$attemptRow) {
        json_response(['error' => 'Attempt not found or unauthorized'], 403);
    }

    $insertStmt = $pdo->prepare("INSERT INTO exam_violations (attempt_id, violation_type, details) VALUES (?, ?, ?)");
    $insertStmt->execute([$attempt_id, $violation_type, $details]);

    // Count total violations for this attempt
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM exam_violations WHERE attempt_id = ?");
    $countStmt->execute([$attempt_id]);
    $totalViolations = (int) $countStmt->fetchColumn();

    // Broadcast real-time violation event to proctors
    require_once __DIR__ . '/../utils/websocket-pusher.php';
    WebSocketPusher::emit("exam:{$attemptRow['exam_id']}", "violation", [
        'student_id' => $student_id,
        'attempt_id' => $attempt_id,
        'violation_type' => $violation_type,
        'total_violations' => $totalViolations,
    ]);

    $maxViolations = 3;
    $isDisqualified = false;

    if ($totalViolations >= $maxViolations) {
        $disqStmt = $pdo->prepare("UPDATE exam_attempts SET status = 'disqualified' WHERE id = ? AND status = 'in_progress'");
        $disqStmt->execute([$attempt_id]);
        $isDisqualified = true;

        WebSocketPusher::emit("exam:{$attemptRow['exam_id']}", "student_disqualified", [
            'student_id' => $student_id,
            'attempt_id' => $attempt_id,
            'total_violations' => $totalViolations,
            'reason' => 'Maximum violation threshold exceeded'
        ]);
    }

    json_response([
        'success' => true,
        'violations_count' => $totalViolations,
        'disqualified' => $isDisqualified,
    ]);
} catch (PDOException $e) {
    json_response(['success' => false, 'message' => 'Logged locally'], 200);
}
