-- Migration 002: Security Hardening & Surprise Classroom Test Enhancements

-- 1. Add Exam Access PIN for Classroom Surprise Tests
ALTER TABLE `exams`
ADD COLUMN IF NOT EXISTS `access_pin` VARCHAR(10) DEFAULT NULL AFTER `status`;

-- 2. Add Option Order Shuffling storage for Exam Attempts
ALTER TABLE `exam_attempts`
ADD COLUMN IF NOT EXISTS `option_order_map` LONGTEXT DEFAULT NULL AFTER `total_questions`;

-- 3. Add Student Status and Active Session ID for Single-Device Enforcement
ALTER TABLE `students`
ADD COLUMN IF NOT EXISTS `status` ENUM('active', 'pending', 'blocked') DEFAULT 'active' AFTER `semester`,
ADD COLUMN IF NOT EXISTS `active_session_id` VARCHAR(128) DEFAULT NULL AFTER `status`;

-- 4. Create Exam Violations Table for Real-Time Proctoring and Audit Trail
CREATE TABLE IF NOT EXISTS `exam_violations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` INT(11) NOT NULL,
  `violation_type` VARCHAR(50) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `occurred_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_violations_attempt` (`attempt_id`),
  CONSTRAINT `fk_violations_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
