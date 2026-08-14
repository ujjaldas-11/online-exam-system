    SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
    START TRANSACTION;
    SET time_zone = "+05:30";

    CREATE TABLE `admins` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `email` varchar(100) NOT NULL,
      `password` varchar(255) NOT NULL,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `students` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `email` varchar(100) NOT NULL,
      `password` varchar(255) NOT NULL,
      `roll_number` varchar(50) NOT NULL,
      `department` varchar(50) NOT NULL,
      `semester` tinyint(4) NOT NULL,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`),
      UNIQUE KEY `roll_number` (`roll_number`)
    ) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `subjects` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(200) NOT NULL,
      `department` varchar(50) NOT NULL,
      `semester` tinyint(4) NOT NULL,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `exams` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `subject_id` int(11) NOT NULL,
      `title` varchar(200) NOT NULL,
      `description` text DEFAULT NULL,
      `duration_minutes` int(11) NOT NULL,
      `total_questions_to_ask` int(11) NOT NULL DEFAULT 10,
      `total_marks` int(11) DEFAULT 0,
      `status` enum('active','inactive') DEFAULT 'active',
      `start_time` datetime DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `subject_id` (`subject_id`),
      CONSTRAINT `fk_exams_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `questions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `subject_id` int(11) NOT NULL,
      `question_text` text NOT NULL,
      `option_a` varchar(255) NOT NULL,
      `option_b` varchar(255) NOT NULL,
      `option_c` varchar(255) NOT NULL,
      `option_d` varchar(255) NOT NULL,
      `correct_option` enum('A','B','C','D') NOT NULL,
      `marks` int(11) DEFAULT 1,
      PRIMARY KEY (`id`),
      KEY `subject_id` (`subject_id`),
      CONSTRAINT `fk_questions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `exam_attempts` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `student_id` int(11) NOT NULL,
      `exam_id` int(11) NOT NULL,
      `started_at` timestamp NULL DEFAULT current_timestamp(),
      `submitted_at` timestamp NULL DEFAULT NULL,
      `score` int(11) DEFAULT 0,
      `total_questions` int(11) DEFAULT 0,
      `status` enum('in_progress','completed') DEFAULT 'in_progress',
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_attempt` (`student_id`,`exam_id`),
      KEY `exam_id` (`exam_id`),
      CONSTRAINT `fk_exam_attempts_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_exam_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE `student_answers` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `attempt_id` int(11) NOT NULL,
      `question_id` int(11) NOT NULL,
      `selected_option` enum('A','B','C','D') DEFAULT NULL,
      `is_correct` tinyint(1) DEFAULT 0,
      PRIMARY KEY (`id`),
      KEY `attempt_id` (`attempt_id`),
      KEY `question_id` (`question_id`),
      CONSTRAINT `fk_student_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_student_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    COMMIT;
