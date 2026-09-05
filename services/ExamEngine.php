<?php

declare(strict_types=1);

/**
 * High-Concurrency Exam Engine
 *
 * Optimized for simultaneous exam launches by 40+ students:
 * - Memory-based randomized sampling (eliminates MySQL ORDER BY RAND() disk thrashing)
 * - Single multi-row bulk insert for student answers
 * - Race-condition safe attempt generation
 * - Direct database answer & review flag persistence (eliminates session sync lag)
 * - Zero-leakage question delivery (correct_option never exposed to active test takers)
 * - Accurate decimal scoring
 */

class ExamEngine
{
    /**
     * Fetch or initialize an examination attempt safely under high concurrency.
     */
    public static function getOrStartAttempt(
        PDO $pdo,
        int $studentId,
        int $examId,
        int $studentSemester,
        string $studentDepartment
    ): array {
        // 1. Verify Exam Permissions & Timing
        $examSql = "
            SELECT e.id, e.title, e.duration_minutes, e.subject_id, e.total_questions_to_ask, e.total_marks,
                   e.access_pin, e.target_units, e.status, e.start_time, e.end_time,
                   TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE)) AS seconds_left
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            WHERE e.id = :id
              AND s.semester = :semester
              AND s.department = :department
              AND e.status IN ('active', 'scheduled')
            LIMIT 1
        ";

        $examStmt = $pdo->prepare($examSql);
        $examStmt->execute([
            ':id' => $examId,
            ':semester' => $studentSemester,
            ':department' => $studentDepartment,
        ]);

        $exam = $examStmt->fetch();

        if (!$exam) {
            return ['error' => 'Exam not found, inactive, or not authorized for your department/semester.'];
        }

        // Automated schedule check: Auto-transition scheduled exam if start_time has arrived
        if ($exam['status'] === 'scheduled') {
            if (!empty($exam['start_time']) && strtotime($exam['start_time']) <= time()) {
                $upd = $pdo->prepare("UPDATE exams SET status = 'active' WHERE id = ?");
                $upd->execute([$examId]);
                $exam['status'] = 'active';
            } else {
                $startFormatted = !empty($exam['start_time']) ? date('M d, Y h:i A', strtotime($exam['start_time'])) : 'a later date';
                return ['error' => "This examination is scheduled to start at {$startFormatted}."];
            }
        }

        // Check if scheduled end_time has passed
        if (!empty($exam['end_time']) && strtotime($exam['end_time']) <= time()) {
            $pdo->prepare("UPDATE exams SET status = 'ended' WHERE id = ?")->execute([$examId]);
            return ['error' => 'The scheduled examination window for this exam has ended.'];
        }

        if (!empty($exam['start_time']) && (int)$exam['seconds_left'] <= 0) {
            return ['error' => 'Time is up! This examination has already concluded.'];
        }

        // 2. Check for Existing Attempt
        $attemptStmt = $pdo->prepare("SELECT id, total_questions, score, status, options_order FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
        $attemptStmt->execute([$studentId, $examId]);
        $attempt = $attemptStmt->fetch();

        if ($attempt) {
            $optionsOrder = !empty($attempt['options_order']) ? json_decode((string)$attempt['options_order'], true) : null;
            if (empty($optionsOrder)) {
                $qStmt = $pdo->prepare("SELECT question_id FROM student_answers WHERE attempt_id = ? ORDER BY id ASC");
                $qStmt->execute([$attempt['id']]);
                $qIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($qIds)) {
                    $optionsOrder = self::generateOptionsOrder($qIds, $studentId, $examId);
                    $pdo->prepare("UPDATE exam_attempts SET options_order = ? WHERE id = ?")
                        ->execute([json_encode($optionsOrder), $attempt['id']]);
                }
            }
            $attempt['options_order'] = $optionsOrder;
            return [
                'success' => true,
                'exam' => $exam,
                'attempt' => $attempt,
                'is_new' => false
            ];
        }

        // 3. Concurrently Initialize Attempt & Allocate Questions
        $pdo->beginTransaction();
        try {
            $qCount = (int) $exam['total_questions_to_ask'];

            // Query only question IDs indexed by subject & unit (very fast index scan)
            if ($exam['target_units'] === 'all') {
                $qStmt = $pdo->prepare("SELECT id FROM questions WHERE subject_id = ?");
                $qStmt->execute([$exam['subject_id']]);
            } else {
                $qStmt = $pdo->prepare("SELECT id FROM questions WHERE subject_id = ? AND unit_number = ?");
                $qStmt->execute([$exam['subject_id'], $exam['target_units']]);
            }

            $availableQuestionIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);

            if ($qCount <= 0) {
                throw new Exception("This examination has no questions configured.");
            }

            if (count($availableQuestionIds) < $qCount) {
                throw new Exception("The question bank does not have enough questions for this exam ($qCount required, " . count($availableQuestionIds) . " available).");
            }

            // Shuffle in memory (O(1) in MySQL, 0 temp tables, zero lock contention)
            shuffle($availableQuestionIds);
            $selectedQuestionIds = array_slice($availableQuestionIds, 0, $qCount);

            // Generate deterministic options permutation per question for candidate attempt to eliminate shoulder surfing
            $optionsOrder = self::generateOptionsOrder($selectedQuestionIds, $studentId, $examId);
            $optionsOrderJson = json_encode($optionsOrder, JSON_UNESCAPED_UNICODE);

            // Insert Attempt record with options_order
            $insAttempt = $pdo->prepare("
                INSERT INTO exam_attempts (student_id, exam_id, total_questions, status, started_at, options_order)
                VALUES (?, ?, ?, 'in_progress', NOW(), ?)
            ");
            $insAttempt->execute([$studentId, $examId, $qCount, $optionsOrderJson]);
            $attemptId = (int) $pdo->lastInsertId();

            // Bulk multi-row insert for all assigned questions in a single query
            $placeholders = [];
            $insertValues = [];
            foreach ($selectedQuestionIds as $qId) {
                $placeholders[] = "(?, ?)";
                $insertValues[] = $attemptId;
                $insertValues[] = (int) $qId;
            }

            $bulkSql = "INSERT INTO student_answers (attempt_id, question_id) VALUES " . implode(', ', $placeholders);
            $bulkStmt = $pdo->prepare($bulkSql);
            $bulkStmt->execute($insertValues);

            $pdo->commit();

            return [
                'success' => true,
                'exam' => $exam,
                'attempt' => [
                    'id' => $attemptId,
                    'total_questions' => $qCount,
                    'score' => 0.00,
                    'status' => 'in_progress',
                    'options_order' => $optionsOrder
                ],
                'is_new' => true
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Handle race condition: Duplicate attempt inserted simultaneously by double-click
            if ($e->getCode() === '23000') {
                $chk = $pdo->prepare("SELECT id, total_questions, score, status, options_order FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
                $chk->execute([$studentId, $examId]);
                $existing = $chk->fetch();
                if ($existing) {
                    if (!empty($existing['options_order']) && is_string($existing['options_order'])) {
                        $existing['options_order'] = json_decode($existing['options_order'], true);
                    }
                    return [
                        'success' => true,
                        'exam' => $exam,
                        'attempt' => $existing,
                        'is_new' => false
                    ];
                }
            }

            log_error("Error initializing exam attempt for student $studentId", $e);
            return ['error' => 'Database error starting exam. Please try again.'];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Save student answer & review status directly to database.
     */
    public static function saveAnswer(
        PDO $pdo,
        int $studentId,
        int $examId,
        int $questionId,
        ?string $selectedOption = null,
        ?bool $markedForReview = null
    ): array {
        try {
            // Verify attempt ownership & active status
            $checkStmt = $pdo->prepare("
                SELECT ea.id, ea.status, e.end_time,
                       TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE)) AS seconds_left
                FROM exam_attempts ea
                JOIN exams e ON ea.exam_id = e.id
                WHERE ea.student_id = ? AND ea.exam_id = ?
            ");
            $checkStmt->execute([$studentId, $examId]);
            $attempt = $checkStmt->fetch();

            if (!$attempt) {
                return ['error' => 'Attempt not found', 'code' => 404];
            }

            if ($attempt['status'] === 'disqualified') {
                return ['error' => 'Attempt has been disqualified due to exam integrity violations', 'code' => 403];
            }

            if ($attempt['status'] === 'completed') {
                return ['error' => 'Exam already submitted', 'code' => 400];
            }

            if ($attempt['status'] !== 'in_progress') {
                return ['error' => 'Exam is not in progress', 'code' => 400];
            }

            // Check if scheduled end_time has passed (with 30-sec latency grace period)
            if (!empty($attempt['end_time']) && strtotime($attempt['end_time']) < (time() - 30)) {
                return ['error' => 'Scheduled examination window has ended', 'code' => 400];
            }

            // Allow 30 seconds network latency grace period
            if ((int)$attempt['seconds_left'] < -30) {
                return ['error' => 'Examination time has expired', 'code' => 400];
            }

            $attemptId = (int) $attempt['id'];

            // Prepare update fields dynamically
            $updates = [];
            $params = [];

            if ($selectedOption !== null) {
                $validOptions = ['A', 'B', 'C', 'D', ''];
                $val = strtoupper(trim($selectedOption));
                if (!in_array($val, $validOptions, true)) {
                    return ['error' => 'Invalid option choice', 'code' => 400];
                }
                $updates[] = "selected_option = ?";
                $params[] = ($val === '') ? null : $val;
            }

            if ($markedForReview !== null) {
                $updates[] = "marked_for_review = ?";
                $params[] = $markedForReview ? 1 : 0;
            }

            if (!empty($updates)) {
                $setClauses = [];
                foreach ($updates as $up) {
                    $setClauses[] = "sa." . $up;
                }
                $params[] = $attemptId;
                $params[] = $questionId;
                $sql = "UPDATE student_answers sa
                        JOIN exam_attempts ea ON sa.attempt_id = ea.id
                        SET " . implode(', ', $setClauses) . "
                        WHERE sa.attempt_id = ? AND sa.question_id = ? AND ea.status = 'in_progress'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                if ($stmt->rowCount() === 0) {
                    $statusCheck = $pdo->prepare("SELECT status FROM exam_attempts WHERE id = ?");
                    $statusCheck->execute([$attemptId]);
                    $currentStatus = $statusCheck->fetchColumn();

                    if ($currentStatus === 'completed') {
                        return ['error' => 'Exam already submitted', 'code' => 400];
                    }
                    if ($currentStatus === 'disqualified') {
                        return ['error' => 'Attempt has been disqualified due to exam integrity violations', 'code' => 403];
                    }
                    if ($currentStatus !== 'in_progress') {
                        return ['error' => 'Exam is not in progress', 'code' => 400];
                    }
                }
            }

            $ansCountStmt = $pdo->prepare("SELECT COUNT(*) FROM student_answers WHERE attempt_id = ? AND selected_option IS NOT NULL AND selected_option != ''");
            $ansCountStmt->execute([$attemptId]);
            $answeredCount = (int) $ansCountStmt->fetchColumn();

            return [
                'success' => true,
                'attempt_id' => $attemptId,
                'answered_count' => $answeredCount,
                'seconds_left' => max(0, (int)$attempt['seconds_left']),
            ];
        } catch (PDOException $e) {
            log_error("Failed saving answer: student $studentId, question $questionId", $e);
            return ['error' => 'Database error saving answer', 'code' => 500];
        }
    }

    /**
     * Submit and auto-grade the examination with accurate decimal score calculation.
     */
    public static function submitExam(PDO $pdo, int $studentId, int $examId): array
    {
        try {
            $pdo->beginTransaction();

            $checkStmt = $pdo->prepare("
                SELECT ea.id, ea.score, ea.total_questions, ea.status,
                       e.total_marks, e.total_questions_to_ask, e.negative_marks_per_question
                FROM exam_attempts ea
                JOIN exams e ON ea.exam_id = e.id
                WHERE ea.student_id = ? AND ea.exam_id = ?
                FOR UPDATE
            ");
            $checkStmt->execute([$studentId, $examId]);
            $attempt = $checkStmt->fetch();

            if (!$attempt) {
                $pdo->rollBack();
                return ['error' => 'Attempt not found'];
            }

            $attemptId = (int) $attempt['id'];

            if ($attempt['status'] === 'disqualified') {
                $pdo->rollBack();
                return ['error' => 'Attempt has been disqualified due to exam integrity violations', 'disqualified' => true];
            }

            if ($attempt['status'] === 'completed') {
                $pdo->rollBack();
                return ['success' => true, 'already_submitted' => true, 'score' => (float)$attempt['score']];
            }

            if ($attempt['status'] !== 'in_progress') {
                $pdo->rollBack();
                return ['error' => 'Exam is not in progress'];
            }

            $totalMarks = (float) $attempt['total_marks'];
            $totalQs = (int) $attempt['total_questions_to_ask'];
            $pointsPerQuestion = ($totalQs > 0) ? ($totalMarks / $totalQs) : 1.0;
            $negativeMarksPerQuestion = isset($attempt['negative_marks_per_question']) ? (float)$attempt['negative_marks_per_question'] : 0.00;

            // Fetch assigned answers with questions to calculate score
            // The attempt row is already exclusively locked with FOR UPDATE above, serializing this submission
            $ansSql = "
                SELECT sa.id AS ans_id, q.id AS question_id, q.correct_option, sa.selected_option
                FROM student_answers sa
                JOIN questions q ON sa.question_id = q.id
                WHERE sa.attempt_id = ?
            ";
            $ansStmt = $pdo->prepare($ansSql);
            $ansStmt->execute([$attemptId]);
            $answers = $ansStmt->fetchAll();

            $totalScore = 0.00;
            $upAns = $pdo->prepare("UPDATE student_answers SET is_correct = ? WHERE id = ?");

            foreach ($answers as $row) {
                $hasAnswered = (!empty($row['selected_option']) && trim($row['selected_option']) !== '');
                $isCorrect = ($hasAnswered && $row['selected_option'] === $row['correct_option']) ? 1 : 0;
                if ($isCorrect === 1) {
                    $totalScore += $pointsPerQuestion;
                } elseif ($hasAnswered && $negativeMarksPerQuestion > 0.0) {
                    $totalScore -= $negativeMarksPerQuestion;
                }
                $upAns->execute([$isCorrect, $row['ans_id']]);
            }

            // Floor score at 0.00 to avoid negative total scores
            $totalScore = max(0.00, round($totalScore, 2));

            $upAttempt = $pdo->prepare("
                UPDATE exam_attempts
                SET score = ?, status = 'completed', submitted_at = NOW()
                WHERE id = ? AND status = 'in_progress'
            ");
            $upAttempt->execute([$totalScore, $attemptId]);

            $pdo->commit();

            return [
                'success' => true,
                'score' => $totalScore,
                'total_marks' => $totalMarks,
                'attempt_id' => $attemptId
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_error("Submission error for student $studentId, exam $examId", $e);
            return ['error' => 'Database error grading exam.'];
        }
    }

    /**
     * Synchronize exam lifecycle statuses across the system:
     * 1. Auto-activates scheduled exams once start_time has arrived.
     * 2. Automatically marks exams as ended once their window has elapsed.
     */
    public static function syncExamStatuses(PDO $pdo): void
    {
        try {
            // 1. Scheduled exams whose start_time has arrived and are within window -> active
            $pdo->exec("
                UPDATE exams
                SET status = 'active'
                WHERE status = 'scheduled'
                  AND start_time IS NOT NULL
                  AND start_time <= NOW()
                  AND (
                      (end_time IS NOT NULL AND end_time > NOW())
                      OR
                      (end_time IS NULL AND NOW() < DATE_ADD(start_time, INTERVAL duration_minutes MINUTE))
                  )
            ");

            // 2. Active or scheduled exams whose window has fully expired -> ended
            $pdo->exec("
                UPDATE exams
                SET status = 'ended'
                WHERE status IN ('active', 'scheduled')
                  AND (
                      (end_time IS NOT NULL AND end_time <= NOW())
                      OR
                      (end_time IS NULL AND start_time IS NOT NULL AND NOW() >= DATE_ADD(start_time, INTERVAL duration_minutes MINUTE))
                  )
            ");
        } catch (PDOException $e) {
            log_error("Failed to sync exam statuses", $e);
        }
    }

    /**
     * Determine if an examination is concluded based on status, end time, and duration.
     *
     * @param array<string, mixed> $exam Associative array containing 'status' (or 'exam_status'), and optionally 'start_time', 'end_time', 'duration_minutes'
     */
    public static function isExamEnded(array $exam): bool
    {
        $status = (string)($exam['status'] ?? $exam['exam_status'] ?? '');
        if ($status === 'ended' || $status === 'cancelled') {
            return true;
        }

        // Check explicit end_time
        if (!empty($exam['end_time'])) {
            $endTime = strtotime((string)$exam['end_time']);
            if ($endTime !== false && time() >= $endTime) {
                return true;
            }
        }

        // Check start_time + duration_minutes
        if (!empty($exam['start_time']) && !empty($exam['duration_minutes'])) {
            $startTime = strtotime((string)$exam['start_time']);
            $durationSec = (int)$exam['duration_minutes'] * 60;
            if ($startTime !== false && time() >= ($startTime + $durationSec)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate aggregated answer statistics for a student attempt.
     *
     * @return array{total_questions: int, correct_count: int, wrong_count: int, skipped_count: int, score: float, percentage: float}
     */
    public static function getAttemptStats(PDO $pdo, int $attemptId): array
    {
        if ($attemptId <= 0) {
            return [
                'total_questions' => 0,
                'correct_count' => 0,
                'wrong_count' => 0,
                'skipped_count' => 0,
                'score' => 0.0,
                'percentage' => 0.0,
            ];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_questions,
                    SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct_count,
                    SUM(CASE WHEN is_correct = 0 AND selected_option IS NOT NULL AND selected_option != '' THEN 1 ELSE 0 END) AS wrong_count,
                    SUM(CASE WHEN selected_option IS NULL OR selected_option = '' THEN 1 ELSE 0 END) AS skipped_count
                FROM student_answers
                WHERE attempt_id = ?
            ");
            $stmt->execute([$attemptId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $attStmt = $pdo->prepare("
                SELECT ea.score, e.total_marks
                FROM exam_attempts ea
                JOIN exams e ON ea.exam_id = e.id
                WHERE ea.id = ?
            ");
            $attStmt->execute([$attemptId]);
            $att = $attStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $score = (float)($att['score'] ?? 0.0);
            $totalMarks = (float)($att['total_marks'] ?? 0.0);
            $percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0.0;

            return [
                'total_questions' => (int)($stats['total_questions'] ?? 0),
                'correct_count' => (int)($stats['correct_count'] ?? 0),
                'wrong_count' => (int)($stats['wrong_count'] ?? 0),
                'skipped_count' => (int)($stats['skipped_count'] ?? 0),
                'score' => $score,
                'percentage' => $percentage,
            ];
        } catch (PDOException $e) {
            log_error("Failed to fetch attempt stats for attempt $attemptId", $e);
            return [
                'total_questions' => 0,
                'correct_count' => 0,
                'wrong_count' => 0,
                'skipped_count' => 0,
                'score' => 0.0,
                'percentage' => 0.0,
            ];
        }
    }

    /**
     * Fetch joined questions and student responses for exam review or answer sheet export.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAttemptReviewQuestions(PDO $pdo, int $attemptId): array
    {
        if ($attemptId <= 0) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT 
                    q.id AS question_id,
                    q.question_text,
                    q.option_a,
                    q.option_b,
                    q.option_c,
                    q.option_d,
                    q.correct_option,
                    sa.selected_option,
                    sa.is_correct,
                    sa.marked_for_review
                FROM student_answers sa
                JOIN questions q ON sa.question_id = q.id
                WHERE sa.attempt_id = ?
                ORDER BY sa.id ASC
            ");
            $stmt->execute([$attemptId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            log_error("Failed to load review questions for attempt $attemptId", $e);
            return [];
        }
    }

    /**
     * Generate a deterministic permutation of options (A, B, C, D) per question
     * for a candidate attempt to eliminate shoulder surfing.
     *
     * @param array<int> $questionIds List of question IDs
     * @param int $studentId Candidate ID
     * @param int $examId Exam ID
     * @return array<string, array<string>> Map of questionId => ['B', 'A', 'D', 'C']
     */
    public static function generateOptionsOrder(array $questionIds, int $studentId, int $examId): array
    {
        $orderMap = [];
        $base = ['A', 'B', 'C', 'D'];

        foreach ($questionIds as $qId) {
            $opts = $base;
            // Seed PRNG deterministically based on student, exam, and question
            $seed = crc32("opt_seed_{$studentId}_{$examId}_{$qId}");
            mt_srand($seed);

            for ($i = count($opts) - 1; $i > 0; $i--) {
                $j = mt_rand(0, $i);
                $tmp = $opts[$i];
                $opts[$i] = $opts[$j];
                $opts[$j] = $tmp;
            }

            $orderMap[(string)$qId] = $opts;
        }

        mt_srand(); // Restore standard PRNG state
        return $orderMap;
    }

    /**
     * Retrieve the options permutation map for an attempt.
     *
     * @param PDO $pdo
     * @param int $attemptId
     * @return array<string, array<string>>
     */
    public static function getAttemptOptionsOrder(PDO $pdo, int $attemptId): array
    {
        if ($attemptId <= 0) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT options_order FROM exam_attempts WHERE id = ?");
            $stmt->execute([$attemptId]);
            $raw = $stmt->fetchColumn();
            if ($raw) {
                $decoded = json_decode((string)$raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            return [];
        } catch (PDOException $e) {
            log_error("Failed fetching options_order for attempt $attemptId", $e);
            return [];
        }
    }

    /**
     * Initialize or fetch an examination attempt with deterministic option permutation.
     * Alias for getOrStartAttempt, automatically resolving student profile if omitted.
     *
     * @param PDO $pdo
     * @param int $studentId
     * @param int $examId
     * @param int $studentSemester Optional semester override
     * @param string $studentDepartment Optional department override
     * @return array
     */
    public static function startExam(
        PDO $pdo,
        int $studentId,
        int $examId,
        int $studentSemester = 0,
        string $studentDepartment = ''
    ): array {
        if ($studentSemester <= 0 || $studentDepartment === '') {
            try {
                $stmt = $pdo->prepare("SELECT semester, department FROM students WHERE id = ?");
                $stmt->execute([$studentId]);
                $stu = $stmt->fetch();
                if ($stu) {
                    $studentSemester = (int)$stu['semester'];
                    $studentDepartment = (string)$stu['department'];
                }
            } catch (PDOException $e) {
                log_error("Failed looking up student profile for startExam", $e);
            }
        }

        return self::getOrStartAttempt($pdo, $studentId, $examId, $studentSemester, $studentDepartment);
    }
}
