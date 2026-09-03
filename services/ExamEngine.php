<?php

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
                   e.access_pin, e.target_units, e.status, e.start_time,
                   TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE)) AS seconds_left
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            WHERE e.id = :id
              AND s.semester = :semester
              AND s.department = :department
              AND e.status = 'active'
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

        if ((int)$exam['seconds_left'] <= 0) {
            return ['error' => 'Time is up! This examination has already concluded.'];
        }

        // 2. Check for Existing Attempt
        $attemptStmt = $pdo->prepare("SELECT id, total_questions, score, status FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
        $attemptStmt->execute([$studentId, $examId]);
        $attempt = $attemptStmt->fetch();

        if ($attempt) {
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

            if (count($availableQuestionIds) < $qCount) {
                throw new Exception("The question bank does not have enough questions for this exam ($qCount required, " . count($availableQuestionIds) . " available).");
            }

            // Shuffle in memory (O(1) in MySQL, 0 temp tables, zero lock contention)
            shuffle($availableQuestionIds);
            $selectedQuestionIds = array_slice($availableQuestionIds, 0, $qCount);

            // Insert Attempt record
            $insAttempt = $pdo->prepare("
                INSERT INTO exam_attempts (student_id, exam_id, total_questions, status, started_at)
                VALUES (?, ?, ?, 'in_progress', NOW())
            ");
            $insAttempt->execute([$studentId, $examId, $qCount]);
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
                    'status' => 'in_progress'
                ],
                'is_new' => true
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Handle race condition: Duplicate attempt inserted simultaneously by double-click
            if ($e->getCode() === '23000') {
                $chk = $pdo->prepare("SELECT id, total_questions, score, status FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
                $chk->execute([$studentId, $examId]);
                $existing = $chk->fetch();
                if ($existing) {
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
                SELECT ea.id, ea.status,
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

            if ($attempt['status'] === 'completed') {
                return ['error' => 'Exam already submitted', 'code' => 400];
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
                $params[] = $attemptId;
                $params[] = $questionId;
                $sql = "UPDATE student_answers SET " . implode(', ', $updates) . " WHERE attempt_id = ? AND question_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            return ['success' => true];
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
            $checkStmt = $pdo->prepare("
                SELECT ea.id, ea.score, ea.total_questions, ea.status,
                       e.total_marks, e.total_questions_to_ask
                FROM exam_attempts ea
                JOIN exams e ON ea.exam_id = e.id
                WHERE ea.student_id = ? AND ea.exam_id = ?
            ");
            $checkStmt->execute([$studentId, $examId]);
            $attempt = $checkStmt->fetch();

            if (!$attempt) {
                return ['error' => 'Attempt not found'];
            }

            $attemptId = (int) $attempt['id'];

            if ($attempt['status'] === 'completed') {
                return ['success' => true, 'already_submitted' => true, 'score' => (float)$attempt['score']];
            }

            $totalMarks = (float) $attempt['total_marks'];
            $totalQs = (int) $attempt['total_questions_to_ask'];
            $pointsPerQuestion = ($totalQs > 0) ? ($totalMarks / $totalQs) : 1.0;

            // Fetch assigned answers with questions
            $ansSql = "
                SELECT sa.id AS ans_id, q.id AS question_id, q.correct_option, sa.selected_option
                FROM student_answers sa
                JOIN questions q ON sa.question_id = q.id
                WHERE sa.attempt_id = ?
            ";
            $ansStmt = $pdo->prepare($ansSql);
            $ansStmt->execute([$attemptId]);
            $answers = $ansStmt->fetchAll();

            $pdo->beginTransaction();

            $totalScore = 0.00;
            $upAns = $pdo->prepare("UPDATE student_answers SET is_correct = ? WHERE id = ?");

            foreach ($answers as $row) {
                $isCorrect = (!empty($row['selected_option']) && $row['selected_option'] === $row['correct_option']) ? 1 : 0;
                if ($isCorrect === 1) {
                    $totalScore += $pointsPerQuestion;
                }
                $upAns->execute([$isCorrect, $row['ans_id']]);
            }

            $totalScore = round($totalScore, 2);

            $upAttempt = $pdo->prepare("
                UPDATE exam_attempts
                SET score = ?, status = 'completed', submitted_at = NOW()
                WHERE id = ?
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
}
