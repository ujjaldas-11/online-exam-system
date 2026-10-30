# User Documentation for Examify

This document gives instructions for students, instructors, and administrators who use Examify.
Examify is an online examination system for college computer laboratories and local area networks.

---

## 1. User Roles

Examify supports three distinct user roles:

- **Student**: Registers an account, takes examinations on desktop computers, reviews scores, and downloads scorecards.
- **Teacher**: Manages subjects, uploads questions, configures examinations, proctors live sessions, and views results.
- **Superadmin**: Exercises full system authority, provisions teacher accounts, manages student rosters, and oversees backups and audits.

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
10. Select your gender identity from the **Gender** list.
11. Type your password in the **Password** field.
12. Type your password again in the **Confirm Password** field.
13. Click the **Register** button.

The system registers your account in `pending` status.
A progress bar animates across 30 seconds and redirects your browser to the homepage.
An administrator reviews and approves your account before you take examinations.

### 2.2 Password Visibility Toggle

Every password field includes an interactive eye icon on the right side:

1. Click the **Eye** icon to display your password characters.
2. Click the **Eye** icon again to mask your password characters.

Always mask your password characters if other students sit near your computer terminal.

### 2.3 Student Login and Single-Device Rules

Follow these steps to log in:

1. Open the student login page (`student/login.php`).
2. Type your registered college email.
3. Type your password.
4. Click the **Login** button.

The system enforces two strict security controls:
- **Rate Limiting**: The system locks authentication for 5 minutes after 5 consecutive failed attempts.
- **Single-Device Session**: Examify permits only one active login per student account.
  If you log in from a second device, the system terminates your previous session immediately.

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
2. The browser enters full-screen mode and starts the countdown timer.
3. Read the question statement and options.
4. Note the question type displayed above the question text:
   - **Single Choice**: Click the radio button for your chosen option (A, B, C, or D).
   - **Multiple Choice**: Check the boxes for all correct options. The system saves your combined selection (for example, A and C).
   - **Case Study**: Review the scenario text before you choose the correct option.
   - **Assertion & Reason**: Evaluate statements A and R, then select the relationship option.
   - **Matching**: Match Column I with Column II, then select the corresponding option code.
5. The system saves your answer automatically in the background.
6. Click the **Next** button to advance to the next question.
7. Click the **Previous** button to revisit the prior question.
8. Click the **Mark for Review** button to flag questions for later check.

#### Option Permutation
The system permutes option order for every candidate attempt.
Your option order differs from your neighbors to prevent copying.

#### Question Palette Indicators
The right sidebar displays the question palette:
- **Green Box**: You answered the question.
- **Yellow Box**: You marked the question for review.
- **Gray Box**: You have not answered the question.
- **Dark Border**: Indicates your current active question.

Click any question number in the palette to navigate directly to that question.

#### Negative Marking
Examinations can include negative marking:
- Incorrect answers deduct the penalty configured for the exam (for example, 0.25 or 0.50 marks).
- Unanswered questions receive zero marks without penalty.
- The system floors your total examination score at 0.00 marks.

#### Real-Time Proctor Announcements
Instructors can push live announcements during active examinations.
Announcements appear immediately in a high-visibility notification banner on your screen.

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
2. Scores and question answer keys remain confidential while the examination session continues.
3. Once the administrator publishes the results, your metrics appear on your Student Dashboard:
   - Final score and maximum marks.
   - Calculated percentage and qualification status (**PASS** or **FAIL**).
   - Count of correct, incorrect, and skipped questions.
   - Question-by-question answer review with correct options.
4. Click the **Download Scorecard PDF** button on published examinations to download your official grade sheet.
   The scorecard contains institutional headers, detailed metrics, and official signature blocks.

### 2.10 Student Profile Review and Update Requests

Follow these steps to review or update your student profile:

1. Click your profile name in the top navigation bar.
2. Review your personal details, enrolled department, and current semester.
3. If your details require correction, click the **Request Profile Update** button.
4. Type your updated name, roll number, department, semester, or gender.
5. Click the **Submit Request** button.

An administrator reviews and approves your request before changes take effect.

### 2.11 Examination History

Open the **Exam History** page (`student/exam-history.php`) to view past attempts:
- View all completed examinations with dates, subject names, and scores.
- Check pass or fail status for every examination.
- Download PDF scorecards for published examinations at any time.

---

## 3. Instructor & Administrator Portal Guide

### 3.1 Administrator Authentication & Setup Wizard

Follow these steps to log in as an administrator:

1. Open the administrator login page (`admin/admin-login.php`).
2. Type your administrator email address.
3. Type your password.
4. Click the **Login as Admin** button.

The login form enforces rate limiting (5 failed attempts per 5 minutes) and single-device sessions.
If you deploy a fresh installation, the system redirects you to the setup wizard (`admin/setup.php`).
Complete the setup wizard to provision the initial Superadmin account.

### 3.2 Administrator Dashboard

The dashboard provides a system overview:
- Total active curriculum subjects.
- Total configured examinations and currently active tests.
- Total question bank inventory.
- Enrolled student count and completed test attempts.
- Navigation links with the active tab highlighted in gold (`#ffd700`).

### 3.3 Curriculum Subjects Management (`admin/manage-subjects.php`)

Open the **Subjects** tab to manage curriculum courses:

#### Creating a Subject
1. Click the **Subjects** link in the navigation bar.
2. Type the subject name (for example: `Computer Networks`).
3. Select the department and semester.
4. Click the **Create Subject** button.

#### Editing a Subject
1. Locate the subject in the table.
2. Click the **Edit** button in the actions column.
3. Modify the subject name, department, or semester in the `#editSubjectModal` dialog.
4. Click the **Save Changes** button.

#### Deleting a Subject
1. Locate the subject in the table.
2. Click the **Delete** button.
3. The system checks for linked questions or examinations.
4. If questions or exams exist, the system blocks deletion and displays an explanatory error.
5. If no linked records exist, confirm the prompt to delete the subject.

### 3.4 Question Banks Management (`admin/manage-questions.php`)

Examify supports five distinct multiple-choice question archetypes:
- **Single Choice (`single`)**: Questions with exactly one correct option (A, B, C, or D).
- **Multiple Choice (`multiple`)**: Questions with one or more correct options (for example, `A,C`).
- **Case Study (`case_study`)**: Scenario-based evaluations with technical or clinical narratives.
- **Assertion & Reason (`assertion_reason`)**: Evaluates Assertion (A) and Reason (R) statements.
- **Matching (`matching`)**: Matches items between Column I and Column II.

#### Downloading Question Templates
1. Navigate to **Questions** in the navigation bar.
2. Select your target subject from the dropdown menu.
3. Download your preferred template:
   - Click **Download CSV Template** for a pre-formatted CSV template.
   - Click **Download XLSX Template** for a native Microsoft Excel workbook template.

#### Previewing Question Imports
1. Upload a CSV file or paste question text into the text area.
2. Click the **Preview Questions** button.
3. The `#previewQuestionsModal` dialog opens in your browser.
4. Inspect parsed questions, type badges, and highlighted validation errors before upload.
5. Click **Close** when satisfied.

#### AI Question Generation ("Copy LLM Prompt")
1. Click the **Copy LLM Prompt** button above the upload form.
2. The system copies an expert prompt formatted for Large Language Models to your clipboard.
3. Paste the prompt into your AI model along with your syllabus text.
4. Copy the AI output directly into the CSV import text area.

#### Uploading Questions in Bulk
1. Ensure your file or text contains the required columns:
   `Question Text, Unit Number, Option A, Option B, Option C, Option D, Correct Option, Question Type`
2. Click the **Upload Questions** button.
3. The system validates every row and saves valid questions to the database.

#### Editing and Deleting Individual Questions
1. Navigate to the question bank inventory (`admin/view-questions.php`).
2. Filter questions by subject or syllabus unit.
3. Click the **Edit** button on any question to modify text or options in the modal or editor.
4. Click the **Delete** button to remove a single question.
5. Click the **Delete All Questions** button to clear the filtered subject bank after confirmation.

#### Exporting Question Banks
1. Open the question inventory page (`admin/view-questions.php`).
2. Click **Export CSV** to download questions in CSV format.
3. Click **Export XLSX** to download questions in native Microsoft Excel format.

### 3.5 Examination Configuration & Scheduling (`admin/manage-exam.php`)

Follow these steps to create and schedule an examination:

1. Click the **Create Exam** link in the navigation bar.
2. Type the examination title.
3. Select the curriculum subject.
4. Set the test duration in minutes.
5. Set total marks and the question quota per student.
6. (Optional) Set **Negative Marks Per Question** (for example: `0.25` or `0.50`).
7. (Optional) Type a 4-digit classroom PIN for surprise quizzes.
8. (Optional) Select **Start Time** and **End Time** using the date and time pickers.
   Scheduled tests activate automatically when the start time arrives.
9. Select target syllabus units (or choose `All Units`).
10. Click the **Create Examination** button.

### 3.6 Examination Controls Center (`admin/control-exams.php`)

Open the **Exam Controls** page to manage active and past tests:

- **Start Exam**: Click to publish a scheduled test to student dashboards immediately.
- **+5 min / +10 min**: Click to extend the remaining duration for all active candidates.
- **End Exam**: Click to conclude testing and lock submissions immediately.
- **Reopen Exam**: Click to reactivate an ended examination if needed.
- **Publish / Unpublish Results**: Click to release or withhold scores and answer keys.
- **Offline Question Paper**: Click to download a printable question paper PDF with answer keys.

### 3.7 Live Classroom Proctoring Panel (`admin/proctor-exam.php`)

Open the live proctoring dashboard during a test:

- **Real-Time Telemetry**: Real-time updates connect via WebSocket with automatic 8-second HTTP polling fallback.
- **Candidate Roster Table**: Monitor student status, answered question counts, and cheating flags.
- **Broadcast Announcement**:
  1. Click the **Broadcast Announcement** button.
  2. Type your message in the `#announcementModal` dialog.
  3. Click **Send Broadcast** to push the message onto all student screens immediately.
- **Unlock / Resume**: Click to restore an attempt if a student workstation crashes.
- **Force Submit**: Click to submit an attempt immediately if a candidate leaves the room.
- **Disqualify Student**: Click to disqualify a candidate caught violating academic integrity.

### 3.8 Student Management Panel (`admin/manage-students.php`)

Open the **Students** tab to manage student enrollments:

#### Filtering and Searching
- Type a name, email, or roll number in the search bar.
- Filter records by department, semester, or enrollment status (`active`, `pending`, `blocked`).

#### Enrolling an Individual Student
1. Click the **Add Student** button.
2. Type the student name, email, roll number, department, semester, gender, and password.
3. Click the **Create Student** button.

#### Editing Student Information
1. Locate the student in the table.
2. Click the **Edit** button in the actions menu.
3. Modify academic details and click **Save Changes**.

#### Resetting Student Passwords
1. Click the **Reset Password** button for the student.
2. Type a new temporary password and click **Update Password**.

#### Account Suspension and Deletion
- Click the **Block** button to suspend a student account.
- Click the **Unblock** button to restore account access.
- Click the **Delete** button to remove a student and purge historical attempts.

#### Cohort Bulk Promotion
1. Locate the **Bulk Promote by Cohort** card.
2. Select the target **Department** and **Current Semester**.
3. Click the **Promote Cohort (+1 Sem)** button.
4. Confirm the prompt to advance all matching students by one semester (capped at Semester 8).

#### Selection Bulk Promotion
1. Select checkboxes next to individual student records in the roster table.
2. A floating batch action bar appears at the bottom of the screen.
3. Click the **Promote Selected (+1 Sem)** button.
4. Confirm the prompt to advance selected students.

#### Batch Student Import & Export
- Click **Import Students** to upload a CSV file of student accounts (`admin/import-students.php`).
- Click **Export CSV** to download the filtered student roster as a CSV spreadsheet.

### 3.9 Student Requests Governance (`admin/manage-requests.php`)

Open the **Requests** tab to process student submissions:

#### Registration Requests (`admin/registration-request.php`)
- Review new student registrations in `pending` status.
- Inspect student name, roll number, department, and semester.
- Click **Approve** to activate the student account.
- Click **Reject** to deny enrollment.

#### Profile Change Requests (`admin/manage-requests.php`)
- Review academic detail changes requested by students.
- Compare existing records with requested modifications.
- Click **Approve** to apply updates to the student profile.
- Click **Reject** to dismiss the request.

### 3.10 Faculty Management (`admin/manage-teachers.php`)

Superadmins manage faculty accounts in the **Faculty** portal:

#### Provisioning Faculty Accounts
1. Click the **Faculty** link in the navigation sidebar.
2. Type the faculty member full name, college email, department, and initial temporary password.
3. Click the **Create Teacher Account** button.

#### Resetting Faculty Passwords
1. Locate the faculty member in the directory.
2. Click the **Reset Password** button.
3. Type the new temporary password into the dialog.
4. Click the **Update Password** button.

#### Superadmin Password Reset
1. Locate your account entry in the Superadmin table.
2. Click the **Reset My Password** button.
3. Type your new password into the confirmation modal and confirm.

#### Permanent Record Retention
When a faculty member departs or retires:
- Click the **Retire Account** button to revoke portal access.
- The system permanently retains 100% of their authored questions, examinations, and student scores.
- Historical author attribution displays permanently across curriculum records as `Prof. Name [Retired]`.

### 3.11 Examination Results & Institutional Reports

Follow these steps to inspect and export examination results:

1. Click the **Results** link in the navigation bar (`admin/results.php`).
2. Click the **View Results** button for the target examination.
3. Inspect the top 3 podium cards and comprehensive leaderboard table.
4. Click the **Publish Results to Students** button to release scores to candidates.
5. Click the **Download Results PDF** button to download the institutional grade report.
   The report contains KPI metric boxes, student rankings, scores, and official signature lines.
6. Click any student row to view itemized answers and download individual student answer sheets.

### 3.12 System Settings & Database Backups (`admin/settings.php`)

Superadmins govern database backups and restoration in the **Settings** panel:

#### Creating a Database Backup
1. Click the **Settings** link in the navigation sidebar.
2. Locate the **Database Backup** section.
3. Inspect the detected backup engine (`mariadb-dump`, `mysqldump`, or PHP streaming fallback).
4. Click the **Download Database Backup** button.
5. The system streams a complete `.sql` (or `.sql.gz`) backup file directly to your browser.

#### Restoring a Database Backup
1. Locate the **Database Restore** section in the settings panel.
2. Select your `.sql` or `.sql.gz` backup file using the file picker.
3. Click the **Restore Database** button.
4. Confirm the prompt to apply the backup to your database.

### 3.13 Institutional Audit Trail (`admin/audit-logs.php`)

Superadmins can inspect immutable security audit logs:
- Filter logs by administrator, action type, or date range.
- Inspect action descriptions, entity types, entity IDs, and target details.
- View client IP addresses and exact timestamps for every administrative action.
