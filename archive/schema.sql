-- Examify Canonical Relational Database Schema (Refined)
-- Elimination of redundant registration table, high-concurrency indexing, decimal scoring, and text MCQ options.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- --------------------------------------------------------
-- Table: admins
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','teacher') NOT NULL DEFAULT 'teacher',
  `status` enum('active','retired') NOT NULL DEFAULT 'active',
  `department` varchar(50) DEFAULT NULL,
  `active_session_id` varchar(128) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_admins_role_status` (`role`, `status`),
  KEY `fk_admins_created_by` (`created_by`),
  CONSTRAINT `fk_admins_created_by` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: students (Unified student lifecycle: self-registration, approvals & enrollment)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `roll_number` varchar(50) NOT NULL,
  `department` varchar(50) NOT NULL,
  `semester` tinyint(3) unsigned NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','others') DEFAULT NULL,
  `status` enum('pending','active','rejected','blocked') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `active_session_id` varchar(128) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `roll_number` (`roll_number`),
  KEY `idx_students_department_semester` (`department`, `semester`),
  KEY `idx_students_status` (`status`),
  KEY `fk_students_reviewed_by` (`reviewed_by`),
  CONSTRAINT `fk_students_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: subjects
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `department` varchar(50) NOT NULL,
  `semester` tinyint(3) unsigned NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subjects_dept_sem` (`department`, `semester`),
  KEY `fk_subjects_admin` (`created_by`),
  CONSTRAINT `fk_subjects_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: exams
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL,
  `total_questions_to_ask` int(11) NOT NULL DEFAULT 10,
  `total_marks` int(11) NOT NULL DEFAULT 0,
  `status` enum('inactive','scheduled','active','ended') NOT NULL DEFAULT 'inactive',
  `results_published` tinyint(1) NOT NULL DEFAULT 0,
  `access_pin` varchar(10) DEFAULT NULL,
  `target_units` varchar(50) NOT NULL DEFAULT 'all',
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`),
  KEY `idx_exams_subject_status` (`subject_id`, `status`),
  KEY `idx_exams_status_time` (`status`, `start_time`),
  KEY `fk_exams_admin` (`created_by`),
  CONSTRAINT `fk_exams_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exams_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: questions (TEXT fields for questions & options to support code, math and long statements)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `unit_number` int(11) NOT NULL DEFAULT 1,
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text DEFAULT NULL,
  `option_d` text DEFAULT NULL,
  `correct_option` enum('A','B','C','D') NOT NULL,
  `marks` int(11) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subject_id` (`subject_id`),
  KEY `idx_questions_subject_unit` (`subject_id`, `unit_number`),
  KEY `fk_questions_admin` (`created_by`),
  CONSTRAINT `fk_questions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_questions_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: exam_attempts (DECIMAL score for fractional points precision)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL,
  `score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_questions` int(11) NOT NULL DEFAULT 0,
  `status` enum('in_progress','completed','disqualified') NOT NULL DEFAULT 'in_progress',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_exam` (`student_id`, `exam_id`),
  KEY `exam_id` (`exam_id`),
  KEY `idx_exam_attempts_status` (`status`),
  KEY `idx_exam_attempts_exam_status` (`exam_id`, `status`),
  KEY `idx_exam_attempts_submitted` (`submitted_at`),
  CONSTRAINT `fk_exam_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_attempts_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: student_answers (Persistent marked_for_review and unique attempt_question constraint)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` enum('A','B','C','D') DEFAULT NULL,
  `marked_for_review` tinyint(1) NOT NULL DEFAULT 0,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `answered_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attempt_question` (`attempt_id`, `question_id`),
  KEY `idx_answers_attempt` (`attempt_id`),
  KEY `idx_answers_question` (`question_id`),
  CONSTRAINT `fk_student_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_student_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: exam_violations (Real-time Proctoring Audit Trail)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_violations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `violation_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `occurred_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_violations_attempt` (`attempt_id`),
  CONSTRAINT `fk_violations_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: profile_requests (Student Academic Detail Change Requests)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `profile_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `new_name` varchar(100) NOT NULL,
  `new_roll_no` varchar(50) NOT NULL,
  `new_department` varchar(50) NOT NULL,
  `new_semester` tinyint(3) unsigned NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `request_date` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `idx_profile_requests_status` (`status`),
  KEY `fk_profile_requests_admin` (`reviewed_by`),
  CONSTRAINT `fk_profile_requests_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_profile_requests_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: admin_audit_logs (Immutable activity tracking)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `admin_name` varchar(100) NOT NULL,
  `admin_role` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_admin` (`admin_id`, `created_at`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: rate_limits (High-throughput security throttle)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `rate_key` varchar(128) NOT NULL,
  `hits` int(11) NOT NULL DEFAULT 1,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`rate_key`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
