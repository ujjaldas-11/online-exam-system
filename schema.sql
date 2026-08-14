SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

CREATE DATABASE IF NOT EXISTS `examify` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `examify`;

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `department` varchar(50) NOT NULL,
  `semester` tinyint(4) NOT NULL,
  `duration_minutes` int(11) NOT NULL,
  `total_marks` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `start_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `exam_attempts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `submitted_at` timestamp NULL DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `total_questions` int(11) DEFAULT 0,
  `status` enum('in_progress','completed') DEFAULT 'in_progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_option` enum('A','B','C','D') NOT NULL,
  `marks` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `roll_number` varchar(50) NOT NULL,
  `department` varchar(50) NOT NULL,
  `semester` tinyint(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_option` enum('A','B','C','D') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `exam_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attempt` (`student_id`,`exam_id`),
  ADD KEY `exam_id` (`exam_id`);

ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`);

ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `roll_number` (`roll_number`);

ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `question_id` (`question_id`);

ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

ALTER TABLE `exam_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `exam_attempts`
  ADD CONSTRAINT `fk_exam_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_exam_attempts_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

ALTER TABLE `questions`
  ADD CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

ALTER TABLE `student_answers`
  ADD CONSTRAINT `fk_student_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_student_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

COMMIT;
