-- Database Performance Optimization
-- Add indexes for frequently queried columns

-- Exam queries (status filtering, subject joins)
CREATE INDEX IF NOT EXISTS idx_exams_subject_status ON exams(subject_id, status);
CREATE INDEX IF NOT EXISTS idx_exams_start_time ON exams(start_time);

-- Attempt queries (student exam lookup)
CREATE INDEX IF NOT EXISTS idx_exam_attempts_student_exam ON exam_attempts(student_id, exam_id);
CREATE INDEX IF NOT EXISTS idx_exam_attempts_status ON exam_attempts(status);
CREATE INDEX IF NOT EXISTS idx_exam_attempts_submitted ON exam_attempts(submitted_at);

-- Answer queries (attempt lookup)
CREATE INDEX IF NOT EXISTS idx_student_answers_attempt ON student_answers(attempt_id);
CREATE INDEX IF NOT EXISTS idx_student_answers_question ON student_answers(question_id);

-- Question queries (subject filtering)
CREATE INDEX IF NOT EXISTS idx_questions_subject ON questions(subject_id);

-- Student queries (department/semester filtering)
CREATE INDEX IF NOT EXISTS idx_students_department_semester ON students(department, semester);
CREATE INDEX IF NOT EXISTS idx_students_email ON students(email);

-- Subject queries (department/semester lookup)
CREATE INDEX IF NOT EXISTS idx_subjects_dept_sem ON subjects(department, semester);

-- Display index status
SHOW INDEX FROM exams;
SHOW INDEX FROM exam_attempts;
SHOW INDEX FROM student_answers;
SHOW INDEX FROM questions;
SHOW INDEX FROM students;
SHOW INDEX FROM subjects;
