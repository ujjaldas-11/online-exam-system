# User Documentation for Examify

This document gives instructions for students, instructors, and administrators who use Examify.
Examify is an online examination system for college computer laboratories and local area networks.

---

## 1. User Roles

Examify supports three distinct user roles:

- **Student**: Registers an account, takes examinations on desktop computers, reviews scores, and downloads scorecards.
- **Teacher**: Manages subjects, uploads questions, configures examinations, proctors live sessions, and views results.
- **Superadmin**: Exercises full system authority, provisions teacher accounts, manages student rosters, and oversees audits.

---

## 2. Student Portal Guide

### 2.1 Student Account Registration

Follow these steps to register a new student account:

1. Open your web browser.
2. Navigate to the application homepage (for example: `http://localhost/online-exam-system/`).
3. Click the **Student Portal** button.
4. Click the **Register here** link.
5. Type your full name in the **Full Name** field.
6. Type your college email in the **College Email** field.
7. Type your roll number in the **Roll Number** field.
   The system converts your roll number to uppercase letters automatically.
8. Select your academic department from the **Department** list.
9. Select your current semester from the **Semester** list.
10. Type your password in the **Password** field.
11. Type your password again in the **Confirm Password** field.
12. Click the **Register** button.

The system registers your account in `pending` status.
A progress bar animates across 30 seconds and redirects your browser to the homepage.
An administrator reviews and approves your account before you take examinations.

### 2.2 Password Visibility Toggle

Every password field includes an interactive eye icon on the right side:

1. Click the **Eye** icon to display your password characters.
2. Click the **Eye** icon again to mask your password characters.

### 2.3 Student Login and Single-Device Rules

Follow these steps to log in:

1. Open the student login page (`student/login.php`).
2. Type your registered college email.
3. Type your password.
4. Click the **Login** button.

Examify enforces a single-device login policy.
If you log in from a second device, the system invalidates your previous session immediately.

### 2.4 Device Requirements & Touchscreen Rules

Observe these computer hardware rules:

- You must use a desktop computer or a laptop computer to take examinations.
- Mobile phones and tablets cannot access the examination room.
- If you open the examination room on a mobile device, a lockout screen appears.
- On touchscreen laptops, the examination room suppresses touch taps.
- You must use a physical mouse or a touchpad to select answers.

### 2.5 Student Dashboard & Examination Access

The student dashboard shows all examinations configured for your department and semester.
The dashboard checks for active tests every 10 seconds.

Each examination card displays:
- Examination title and course subject.
- Duration in minutes.
- Total question count and maximum marks.
- Status badge (**Live**, **Scheduled**, or **Ended**).

Follow these steps to enter an examination:

1. Locate the live examination card on your dashboard.
2. Click the **Start Exam** button.
3. If the test requires a classroom PIN, type the 4-digit code in the PIN field.
4. Click the **Unlock Exam** button.

### 2.6 Taking an Examination

Follow these steps during your test:

1. Click the **Click to Enter Fullscreen & Begin** button.
2. The browser enters full-screen mode.
3. Read the question statement and options.
4. Click an option (A, B, C, or D) to select your answer.
   The system saves your answer automatically.
5. Click the **Next** button to advance to the next question.
6. Click the **Previous** button to revisit the prior question.
7. Click the **Mark for Review** button to flag questions for later check.

#### Question Palette Indicators

The right sidebar displays the question palette:
- **Blue Box**: You answered the question.
- **Yellow Box**: You marked the question for review.
- **Gray Box**: You have not answered the question.
- **Dark Border**: Indicates your current active question.

Click any question number in the palette to navigate directly to that question.

### 2.7 Anti-Cheat Security Rules

Examify enforces strict proctoring rules during every examination:

- Do not exit full-screen mode.
- Do not switch browser tabs or open other applications.
- Do not minimize the browser window.
- Do not press the **F12** key or inspect developer tools.
- Do not use keyboard shortcuts such as **Ctrl+Shift+I** or **Ctrl+U**.

If an infraction occurs:
1. The system pauses your examination and displays a warning banner.
2. Click the **Resume Exam** button to re-enter full-screen mode.
3. If you accumulate 3 violations, the system submits your test attempt immediately.

### 2.8 Submitting an Examination

Follow these steps to submit your test:

1. Review your answers using the question palette.
2. Click the **Submit Exam** button in the sidebar.
3. An in-DOM confirmation modal appears on your screen.
4. Inspect the summary counters:
   - **Answered Questions**
   - **Marked for Review**
   - **Unanswered Questions**
5. Click the **Confirm & Submit** button to finish your examination.

The system halts proctoring listeners before submission to prevent false window blur warnings.

### 2.9 Examination Submission & Result Publication

After you submit an examination:

1. The system records your answers securely and displays the **Exam Submitted Successfully** confirmation.
2. To prevent academic dishonesty in the examination room, scores and question answer keys remain confidential while the examination session continues.
3. Once the examination concludes and the administrator publishes the results, your full performance metrics become visible on your Student Dashboard:
   - Final score and maximum marks.
   - Calculated percentage and clearing status (**PASS** or **FAIL**).
   - Count of correct, incorrect, and skipped questions.
   - Question-by-question answer review with correct options.
4. Click the **Download Scorecard PDF** button on published examinations to download your official grade sheet.
   The scorecard contains institutional headers, detailed metrics, and official signature blocks.

---

## 3. Instructor & Administrator Portal Guide

### 3.1 Administrator Authentication

Follow these steps to log in as an administrator:

1. Open the administrator login page (`admin/admin-login.php`).
2. Type your administrator email address.
3. Type your password.
4. Click the **Login as Admin** button.

If you deploy a fresh installation, the system redirects you to the setup wizard (`admin/setup.php`).
Complete the setup wizard to provision the initial Superadmin account.

### 3.2 Administrator Dashboard

The dashboard provides a system overview:
- Total active curriculum subjects.
- Total configured examinations and currently active tests.
- Total question bank inventory.
- Enrolled student count and completed test attempts.
- Navigation links with the active tab highlighted in gold (`#ffd700`).

### 3.3 Student Management Panel (`admin/manage-students.php`)

Open the **Students** tab to manage student enrollments.

#### Filtering and Searching
- Type a name, email, or roll number in the search bar.
- Filter records by department, semester, or enrollment status (`active`, `pending`, `blocked`).

#### Enrolling a Student
1. Click the **Add Student** button.
2. Type the student name, email, roll number, department, semester, and password.
3. Click the **Create Student** button.

#### Editing Student Information
1. Locate the student in the table.
2. Click the **Edit** button in the action menu.
3. Modify the academic details and click **Save Changes**.

#### Resetting Student Passwords
1. Click the **Reset Password** button for the student.
2. Type a new temporary password and click **Update Password**.

#### Account Suspension and Deletion
- Click the **Block** button to suspend a student account.
- Click the **Unblock** button to restore account access.
- Click the **Delete** button to remove a student and purge historical attempts.

#### Exporting Student Rosters
Click the **Export CSV** button to download the filtered student roster as a CSV spreadsheet.

### 3.4 Bulk Student Promotion

Administrators can promote student cohorts to the next academic semester:

#### Cohort Bulk Promotion
1. Locate the **Bulk Promote by Cohort** card.
2. Select the target **Department** and **Current Semester**.
3. Click the **Promote Cohort (+1 Sem)** button.
4. Confirm the prompt to advance all matching students by one semester.

#### Selection Bulk Promotion
1. Select the checkboxes next to individual student records in the roster table.
2. A floating batch action bar appears at the bottom of the screen.
3. Click the **Promote Selected (+1 Sem)** button.
4. Confirm the prompt to advance selected students.
The system caps student advancement at Semester 8.

### 3.5 Managing Subjects

Follow these steps to create a subject:

1. Click the **Subjects** link in the navigation bar.
2. Type the subject name (for example: `Computer Networks`).
3. Select the department and semester.
4. Click the **Create Subject** button.

### 3.6 Managing Question Banks

Follow these steps to upload multiple-choice questions in bulk via CSV:

1. Click the **Questions** link in the navigation bar (`admin/manage-questions.php`).
2. Select the target subject from the dropdown menu.
3. (Optional) Click the **Download Template** button to obtain a pre-formatted CSV template.
4. Upload your `.csv` or `.txt` file, or paste CSV records directly into the text area.
   The CSV must contain the following columns:
   `Question Text, Unit Number, Option A, Option B, Option C, Option D, Correct Option`
   ```csv
   Question Text,Unit Number,Option A,Option B,Option C,Option D,Correct Option
   "What does HTTP stand for?",1,"HyperText Transfer Protocol","High Text Transfer Program","Hyperlink Text Transmission Protocol","Hosting Text Transfer Provider",A
   "Which OSI layer handles routing?",2,"Physical Layer","Network Layer","Transport Layer","Data Link Layer",B
   ```
5. Click the **Upload Questions** button to validate and store the question bank.

### 3.7 Configuring Examinations

Follow these steps to configure a test:

1. Click the **Create Exam** link in the navigation bar.
2. Type the examination title.
3. Select the curriculum subject.
4. Set the test duration in minutes.
5. Set total marks and the question quota per student.
6. (Optional) Type a 4-digit classroom PIN for surprise quizzes.
7. Click the **Create Examination** button.

### 3.8 Examination Controls & Emergency Time

Open the **Exam Controls** page (`admin/control-exams.php`) to govern tests:

- **Start Exam**: Click to publish a scheduled test to student dashboards.
- **+5 min / +10 min**: Click to extend the remaining duration for all active candidates.
- **End Exam**: Click to conclude testing and lock submissions.

### 3.9 Live Classroom Proctoring Panel

Open the live proctoring dashboard (`admin/proctor-exam.php?exam_id=<ID>`) during a test:

- The screen updates candidate statistics every 5 seconds.
- Monitor active attempts, completed tests, and cumulative cheating flags.
- **Unlock / Resume**: Click to restore an attempt if a student computer crashes.
- **Force Submit**: Click to submit an attempt immediately if a candidate departs.

### 3.10 Viewing Results & Downloading Institutional Reports

Follow these steps to inspect and export examination results:

1. Click the **Results** link in the navigation bar.
2. Click the **View Results** button for the examination.
3. Inspect the top 3 podium cards and comprehensive leaderboard table.
4. Click the **Download Results PDF** button.

### 3.11 Publishing Examination Results to Students

By default, student scores and question answer keys are locked to prevent early finishers from sharing answers with peers who are still writing.

Follow these steps to release results to candidates:

1. Conclude the examination session by clicking **End Exam** in the **Exam Controls** center (`admin/control-exams.php`).
2. Verify that all students in the classroom have finished testing.
3. Click the **Publish** button in the **Exam Controls** center, or click **Publish Results to Students** at the top of the examination's results page (`admin/view-results.php`).
4. The system updates the publication status to `Published`. All candidates can now view their final scores, review question answer keys, and download official PDF scorecards.
5. If you must audit or modify records, click the **Unpublish Results** button to temporarily lock student visibility.

Examify compiles an official institutional PDF report.
The report contains KPI metric boxes, student rankings, scores, and official endorsement signature lines.

