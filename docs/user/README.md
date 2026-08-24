# User Documentation for Examify

This document gives instructions for students, instructors, and administrators who use Examify.
Examify is an online examination system for college computer laboratories and local area networks.

---

## 1. User Roles

Examify has two primary user roles:

- **Student**: Takes online examinations, reviews results, and manages profile data.
- **Instructor / Administrator**: Creates subjects, uploads questions, configures exams, monitors live tests, and evaluates results.

---

## 2. Student Portal Guide

### 2.1 Student Account Registration

Follow these steps to register a new student account:

1. Open a web browser.
2. Go to the application address (for example: `http://localhost/online-exam-system/`).
3. Click the **Student Portal** button.
4. Click the **Register here** link.
5. Type your full name in the **Full Name** box.
6. Type your college email address in the **College Email** box.
7. Type your student roll number in the **Roll Number / Student ID** box.
8. Select your department from the **Department** list.
9. Select your current semester from the **Semester** list.
10. Type a password with at least 6 characters in the **Password** box.
11. Type the password again in the **Confirm Password** box.
12. Click the **Register** button.

The system shows a confirmation message. You can now log in.

### 2.2 Student Login

Follow these steps to log in to the Student Portal:

1. Open the student login page (`student/login.php`).
2. Type your registered email address in the **Email Address** box.
3. Type your password in the **Password** box.
4. Click the **Login** button.

The system opens your student dashboard.

### 2.3 Student Dashboard

The student dashboard shows all examinations for your department and semester.
The dashboard automatically checks for new active examinations every 10 seconds.

Each examination card shows:

- Examination title and description
- Subject name
- Examination duration in minutes
- Total number of questions
- Total examination marks
- Status badge (**Live**, **Scheduled**, or **Ended**)

### 2.4 Unlocking an Examination with a Classroom PIN

Some surprise examinations require an access PIN.
The instructor gives this PIN in the classroom.

Follow these steps to unlock an examination:

1. Find the examination card on your dashboard.
2. Click the **Start Exam** button.
3. If the examination requires a PIN, the system shows the PIN screen.
4. Type the PIN from your instructor in the **Exam PIN / Passcode** box.
5. Click the **Unlock Exam** button.

The system verifies the PIN and opens the secure examination room.

### 2.5 Taking an Examination

Follow these steps to take an examination:

1. Click the **Click to Enter Fullscreen & Begin** button.
2. The browser enters full-screen mode.
3. Read the question text and options on the screen.
4. Click an option button (A, B, C, or D) to select your answer.
5. The system saves your answer automatically.
6. Click the **Next** button to move to the next question.
7. Click the **Previous** button to return to the previous question.
8. Click the **Mark for Review** button if you want to check the question later.

#### Question Palette Colors

The right sidebar shows the question palette grid:

- **Blue Box**: Question answered.
- **Yellow Box**: Question marked for review.
- **Gray Box**: Question not answered.
- **Dark Border**: Current active question.

Click any question number in the grid to jump directly to that question.

#### Examination Timer

The timer at the top right counts down the remaining time.
When the timer reaches zero, the system submits your examination automatically.

### 2.6 Anti-Cheat Security Rules

Examify monitors test integrity during the examination.
The system records all violations in the database.

Observe these rules during the examination:

- Do not exit full-screen mode.
- Do not switch browser tabs.
- Do not minimize the browser window.
- Do not click outside the examination window.
- Do not press the **F12** key or open developer tools.
- Do not press keyboard shortcuts such as **Ctrl+Shift+I** or **Ctrl+U**.

If a violation occurs:

1. The system shows a warning banner with the violation count.
2. The system pauses your examination.
3. Click the **Resume Exam** button to return to full-screen mode.

> [!WARNING]
> If you reach 3 violations, the system submits your examination immediately.

### 2.7 Submitting an Examination

Follow these steps to submit your examination:

1. Check all questions in the question palette.
2. Make sure that you have answered all desired questions.
3. Click the **Submit Exam** button in the sidebar.
4. Click **OK** in the confirmation dialog.

The system evaluates your answers and opens the result page.

### 2.8 Reviewing Examination Results

The result page shows your performance breakdown:

- Celebration icon or study icon based on score percentage
- Total score and maximum possible marks
- Score percentage
- Number of correct answers
- Number of wrong answers
- Number of skipped questions

Click the **Return to Dashboard** button to go back to the dashboard.
Click the **View Profile History** button to see your past examination records.

### 2.9 Student Profile and Change Requests

Follow these steps to view your profile:

1. Click the **Profile** link in the navigation bar.
2. The page shows your name, email, roll number, department, semester, and test history.

Follow these steps to request a profile change:

1. Click the **Edit Profile** button on the profile page.
2. Type the new values for your name, roll number, department, or semester.
3. Click the **Request Update** button.

The request goes to the administrator for review.
You cannot submit another request until the administrator reviews the current request.

---

## 3. Instructor and Administrator Portal Guide

### 3.1 Administrator Login

Follow these steps to log in as an administrator:

1. Open the admin login page (`admin/admin-login.php`).
2. Type your administrator email address.
3. Type your administrator password.
4. Click the **Login as Admin** button.

The system opens the admin dashboard.

### 3.2 Admin Dashboard Overview

The admin dashboard gives an overview of the examination system:

- Total curriculum subjects
- Total configured examinations
- Number of live examinations
- Total questions in the question bank
- Total registered students
- Total completed student attempts

Quick-action cards give fast access to all management modules.

### 3.3 Managing Curriculum Subjects

Follow these steps to add a new subject:

1. Click the **Subjects** link in the navigation bar.
2. Type the subject name in the **Subject Name** box (for example: `Operating Systems`).
3. Select the department from the **Department** list.
4. Select the semester from the **Semester** list.
5. Click the **Create Subject** button.

The table below shows all existing subjects.
Click the **View Questions** button next to any subject to inspect its question bank.

### 3.4 Managing Question Banks

You can add multiple-choice questions to subjects in bulk with JSON data.

Follow these steps to upload questions:

1. Click the **Questions** link in the navigation bar.
2. Select the target subject from the **Select Subject** list.
3. (Optional) Click the **Copy LLM Prompt** button to copy a prompt for an AI tool.
4. Generate the JSON question list with your AI tool or text editor.
5. Paste the JSON data into the **JSON Data Array** box.
6. Click the **Upload All Questions** button.

#### Required JSON Structure

Each question in the JSON array must use this format:

```json
[
  {
    "question_text": "What is an operating system?",
    "option_a": "System software",
    "option_b": "Application software",
    "option_c": "Hardware component",
    "option_d": "Malicious program",
    "correct_option": "A"
  }
]
```

- `question_text`: The question statement (required).
- `option_a`: Text for option A (required).
- `option_b`: Text for option B (required).
- `option_c`: Text for option C (optional).
- `option_d`: Text for option D (optional).
- `correct_option`: Must be `A`, `B`, `C`, or `D` (required).

### 3.5 Configuring an Examination

Follow these steps to make a new examination:

1. Click the **Create Exam** link in the navigation bar.
2. Type the exam title in the **Exam Title** box.
3. Select the subject from the **Subject** list.
4. Type the duration in minutes in the **Duration (Minutes)** box.
5. Type the total marks in the **Total Marks** box.
6. Type the number of questions in the **Questions per Student** box.
7. (Optional) Type a 4-digit code in the **Classroom PIN** box for surprise tests.
8. Click the **Create Examination** button.

> [!NOTE]
> The subject question bank must contain at least as many questions as the **Questions per Student** value.

### 3.6 Controlling Examinations

Open the **Exam Controls** page (`admin/control-exams.php`) to manage examinations.

The table shows all examinations, their department, semester, PIN, and status.

#### Actions on Inactive Exams:
- Click the **Start** button to make an examination live.
- Click the **Delete** button to remove an examination.

#### Actions on Live Exams:
- Click the **Live Proctor** button to open real-time classroom monitoring.
- Click the **+5 min** button to give students 5 additional minutes.
- Click the **+10 min** button to give students 10 additional minutes.
- Click the **End Exam** button to stop the examination immediately.

#### Actions on Ended Exams:
- Click the **Results** button to view graded submissions.

### 3.7 Live Classroom Proctoring Panel

Open the Live Proctor panel (`admin/proctor-exam.php?exam_id=<ID>`) during a test.
The panel refreshes data automatically every 5 seconds.

#### Proctoring Statistics:
- **Total Class Roster**: Total students enrolled in the department and semester.
- **Currently Answering**: Number of students with an active test attempt.
- **Submitted / Done**: Number of students who completed the test.
- **Not Started**: Number of students who have not started yet.
- **Total Cheating Flags**: Total anti-cheat violations recorded across all students.

#### Emergency Classroom Actions:
- **Unlock / Resume**: If a student computer crashes, click this button to restore the attempt to **In Progress**.
- **Force Submit**: If a student leaves the lab, click this button to submit the attempt immediately.

### 3.8 Managing Student Requests and Password Resets

Open the **Student Requests** page (`admin/manage-requests.php`) to handle student credentials.

#### Approving Profile Change Requests:
1. Review the table of pending modification requests.
2. Compare the current details with the requested changes.
3. Click the **Approve** button to apply the changes to the student account.
4. Click the **Reject** button to dismiss the request.

#### Resetting Student Passwords:
If a student forgets their password before a lab test:

1. Go to the **Classroom Password Reset** section.
2. Type the student roll number in the **Student Roll Number** box.
3. Type a new temporary password in the **New Temporary Password** box.
4. Click the **Reset Student Password** button.
5. Give the temporary password to the student.

### 3.9 Batch Student Enrollment with CSV

Open the **Import Students** page (`admin/import-students.php`) to enroll a class.

Follow these steps to import students:

1. Prepare a CSV file or CSV text with this column order:
  `Name, Email, Roll Number, Department, Semester, Password`
2. Select the `.csv` file in the **Upload .CSV File** box, or paste the text in the text box.
3. Click the **Import Classroom Roster** button.

The system imports all new students.
The system skips duplicate emails and duplicate roll numbers.
If you omit the password column, the system sets the default password to the student roll number.

### 3.10 Viewing Results and Printing Reports

Follow these steps to inspect exam results:

1. Click the **Results** link in the navigation bar.
2. (Optional) Filter the list by department.
3. Click the **View Results** button for the desired examination.

The results page shows:
- **Top Performers Podium**: Top 3 students with gold, silver, and bronze rank cards.
- **Leaderboard Table**: All student submissions with rank, roll number, score, and submission timestamp.
- **Print / Save PDF**: Click the **Print / Save PDF** button to print an official score sheet.
