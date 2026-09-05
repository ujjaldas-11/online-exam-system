<?php

/**
 * Authentication & RBAC Guard Helpers
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

function is_admin_logged_in(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    return !empty($_SESSION['admin_id']);
}

function is_superadmin(): bool
{
    if (!is_admin_logged_in()) {
        return false;
    }
    $role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? '';
    return $role === 'superadmin';
}


function get_admin_role(): string
{
    if (!is_admin_logged_in()) {
        return '';
    }
    return $_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'teacher';
}

function is_student_logged_in(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    return !empty($_SESSION['student_id']);
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        redirect('admin-login.php');
    }
}

function require_superadmin(): void
{
    require_admin();
    if (!is_superadmin()) {
        http_response_code(403);
        set_flash('error', 'Access denied: Superadmin privileges required.');
        redirect('admin-dashboard.php');
    }
}

function require_student(): void
{
    if (!is_student_logged_in()) {
        redirect('login.php');
    }
}

function is_system_initialized(PDO $pdo): bool
{
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'admins'")->fetchColumn();
        if (!$check) {
            return false;
        }
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin'");
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Singleton Session Management Helpers
 * Enforces strictly ONE active login per user account across devices.
 */
function bind_active_session(PDO $pdo, string $userType, int $userId): void
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    $table = ($userType === 'admin') ? 'admins' : 'students';
    $sessionId = session_id();
    $stmt = $pdo->prepare("UPDATE {$table} SET active_session_id = ? WHERE id = ?");
    $stmt->execute([$sessionId, $userId]);
}

function verify_active_session(PDO $pdo, string $userType, int $userId): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    $table = ($userType === 'admin') ? 'admins' : 'students';
    $currentSessionId = session_id();

    $stmt = $pdo->prepare("SELECT active_session_id FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $activeId = $stmt->fetchColumn();

    // If active_session_id is recorded and does not match current session, another device has logged in
    if (!empty($activeId) && $activeId !== $currentSessionId) {
        return false;
    }
    return true;
}

function clear_active_session(PDO $pdo, string $userType, int $userId, ?string $sessionId = null, bool $force = false): void
{
    $table = ($userType === 'admin') ? 'admins' : 'students';
    $targetSession = $sessionId ?? (session_status() === PHP_SESSION_ACTIVE ? session_id() : '');

    if ($force) {
        $stmt = $pdo->prepare("UPDATE {$table} SET active_session_id = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        return;
    }

    if (!empty($targetSession)) {
        $stmt = $pdo->prepare("UPDATE {$table} SET active_session_id = NULL WHERE id = ? AND active_session_id = ?");
        $stmt->execute([$userId, $targetSession]);
    }
}

/**
 * Check if the currently logged-in admin has permission to manage an exam.
 * Superadmins can manage all exams. Other instructors must be the creator.
 */
function can_admin_manage_exam(PDO $pdo, int $examId, ?int $adminId = null): bool
{
    if (is_superadmin()) {
        return true;
    }

    $adminId = $adminId ?? (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0 || $examId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT created_by FROM exams WHERE id = ? LIMIT 1");
        $stmt->execute([$examId]);
        $creator = $stmt->fetchColumn();
        return $creator !== false && (int)$creator === $adminId;
    } catch (PDOException) {
        return false;
    }
}

/**
 * Check if the currently logged-in admin has permission to manage a subject.
 * Superadmins can manage all subjects. Other instructors must be the creator.
 */
function can_admin_manage_subject(PDO $pdo, int $subjectId, ?int $adminId = null): bool
{
    if (is_superadmin()) {
        return true;
    }

    $adminId = $adminId ?? (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0 || $subjectId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT created_by FROM subjects WHERE id = ? LIMIT 1");
        $stmt->execute([$subjectId]);
        $creator = $stmt->fetchColumn();
        return $creator !== false && (int)$creator === $adminId;
    } catch (PDOException) {
        return false;
    }
}

/**
 * Check if the currently logged-in admin has permission to manage a question.
 * Superadmins can manage all questions. Other instructors must be question creator or subject creator.
 */
function can_admin_manage_question(PDO $pdo, int $questionId, ?int $adminId = null): bool
{
    if (is_superadmin()) {
        return true;
    }

    $adminId = $adminId ?? (int)($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0 || $questionId <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT q.created_by AS question_creator, s.created_by AS subject_creator
            FROM questions q
            LEFT JOIN subjects s ON q.subject_id = s.id
            WHERE q.id = ?
            LIMIT 1
        ");
        $stmt->execute([$questionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        return ((int)$row['question_creator'] === $adminId) ||
               (!empty($row['subject_creator']) && (int)$row['subject_creator'] === $adminId);
    } catch (PDOException) {
        return false;
    }
}
