# Examify — Online Examination System

**Examify** is a web-based online examination platform built with **PHP, MySQL, HTML5, CSS3, and Vanilla JavaScript**. It provides separate interfaces for administrators and students to manage subjects, create examinations, maintain MCQ question banks, conduct timed examinations, automatically evaluate submissions, and manage examination results.

## 🎯 Motive

The goal of Examify is to provide a simple and centralized digital examination system for colleges and educational institutions.

The system covers the complete examination workflow:

**Subject Management → Question Management → Exam Creation → Exam Control → Student Examination → Automatic Evaluation → Results**

### Core Capabilities

- **Admin Panel** — Manage subjects, questions, examinations, students' examination results, and examination settings.
- **Subject Management** — Create and manage subjects according to department and semester.
- **Question Bank** — Add, view, search, and manage multiple-choice questions for individual subjects.
- **Bulk Question Import** — Add multiple questions using structured JSON data.
- **Exam Creation** — Create examinations by selecting a subject, department, semester, duration, number of questions, and marks.
- **Randomized Questions** — Select a configured number of questions randomly from the available subject question bank.
- **Student Panel** — Students can register, log in, access their dashboard, manage their profile, take examinations, and view results.
- **Department & Semester Access** — Students can access examinations according to their registered department and semester.
- **Timed Examination** — Each examination has a configured duration with a live countdown timer.
- **Question Navigation** — Students can move between questions and use the question navigation panel.
- **Auto-Save** — Student answers are saved while the examination is in progress.
- **Mark for Review** — Students can mark questions for later review.
- **Exam Security** — Includes fullscreen mode, tab-switch detection, window-focus monitoring, violation tracking, and browser shortcut restrictions.
- **Automatic Submission** — The examination is automatically submitted when the examination time expires or the maximum violation limit is reached.
- **Automatic Evaluation** — MCQ answers are automatically evaluated after submission.
- **Result Management** — Students can view their examination results while administrators can view examination submissions and performance.

---

## 🚀 Project Status

**Status: Functional Development Version**

The current implementation provides the major components required to conduct an online MCQ examination.

The following functionality is currently implemented:

- Student registration and login
- Student logout
- Student profile management
- Student profile editing
- Admin authentication
- Admin session protection
- Admin dashboard
- Subject management
- Question bank management
- Question search
- Question viewing
- Bulk JSON question preparation/import
- Examination creation
- Examination configuration
- Examination control center
- Department-based examination assignment
- Semester-based examination assignment
- Random question selection
- Timed examinations
- Live examination countdown
- Question navigation
- Answer auto-saving
- Mark-for-review functionality
- Fullscreen examination mode
- Tab-switch detection
- Window-focus detection
- Examination violation counter
- Automatic examination submission
- Automatic MCQ evaluation
- Student result display
- Admin result management
- Examination result filtering
- Examination deletion and management

The project can be further improved with additional analytics, advanced security, UI enhancements, teacher roles, and reporting features.

---

## ✨ Features

### 👨‍🏫 Admin Features

- **Admin Authentication** — Secure login and session-based access control for administrators.
- **Admin Dashboard** — Provides an overview of important system and examination information.
- **Subject Management** — Create and manage subjects with department and semester information.
- **Question Bank Management** — Add and manage MCQ questions associated with subjects.
- **Question Search** — Search questions from the question bank.
- **Question Viewing** — View available questions and their associated information.
- **Bulk Question Import** — Prepare and insert multiple questions using JSON-based question data.
- **Exam Creation** — Create examinations by selecting subject, department, semester, duration, number of questions, and marks.
- **Exam Management** — View and manage created examinations.
- **Exam Control Center** — Monitor examination status and control examination sessions.
- **Exam Status** — Manage examinations according to their current state such as not started, running, or ended.
- **Result Management** — View examination results and student submissions.
- **Result Filtering** — Filter examination results according to available examination information.
- **Search Functionality** — Search examinations and questions using the available search interfaces.

### 🎓 Student Features

- **Student Registration** — Students can create an account by providing their required academic and personal information.
- **Student Login** — Students can securely log in to their account.
- **Student Dashboard** — Provides access to available examinations and student information.
- **Student Profile** — Students can view their profile information.
- **Edit Profile** — Students can update supported profile information.
- **Department-Based Exam Access** — Students receive examinations according to their registered department.
- **Semester-Based Exam Access** — Students receive examinations according to their registered semester.
- **Exam Information** — Students can view examination subject, duration, question count, marks, and status.
- **Randomized Questions** — Questions are selected from the relevant subject question bank for the examination attempt.
- **Live Countdown Timer** — Displays the remaining examination time.
- **Question Navigation** — Students can navigate between available questions.
- **Question Map** — Provides a visual representation of the examination questions.
- **Auto-Save Answers** — Selected answers are saved during the examination.
- **Mark for Review** — Students can mark questions that require additional attention.
- **Fullscreen Mode** — The examination interface supports fullscreen mode.
- **Anti-Cheat Monitoring** — Detects tab switching and loss of examination-window focus.
- **Violation Tracking** — Records examination security violations.
- **Automatic Submission** — Submits the examination when the timer expires or the violation limit is reached.
- **Automatic Evaluation** — Automatically evaluates submitted MCQ answers.
- **Result Display** — Students can view their examination score and answer statistics.
- **Exam History** — Students can access completed examination results where available.

---

## 🛡️ Examination Security

Examify includes several browser-based mechanisms to improve examination security.

### Security Features

- **Fullscreen Mode** — Students are encouraged to take the examination in fullscreen mode.
- **Tab Switching Detection** — Detects when the student leaves the examination tab.
- **Window Focus Detection** — Detects when the examination window loses focus.
- **Violation Counter** — Maintains a count of detected examination violations.
- **Automatic Termination** — The examination can be terminated after reaching the configured violation limit.
- **Keyboard Shortcut Restrictions** — Attempts to restrict common browser and developer-tool shortcuts during the examination.
- **Session Protection** — Student and administrator pages use authentication guards.
- **Academic Access Validation** — Examination access is checked against the student's department and semester.
- **Attempt Protection** — Examination attempts are associated with students to prevent duplicate examination attempts where enforced by the database.

> **Note:** Browser-based security mechanisms are deterrents and cannot completely prevent cheating. For high-stakes examinations, additional institutional security and proctoring mechanisms should be used.

## 🎲 Randomized Questions

Examify allows administrators to maintain a question bank containing more questions than are required for a particular examination.

For example:
```
text
Subject Question Bank
        50 Questions
             ↓
Exam Configuration
     Select 20 Questions
             ↓
      Random Selection
             ↓
       Examination
       20 Questions
```

## ⏱️ Examination Interface

The examination interface provides the student with the tools required to complete a timed MCQ examination.

**Examination Controls**
- Live countdown timer
- Previous question navigation
- Next question navigation
- Question navigation map
- Answer selection
- Automatic answer saving
- Mark for Review
- Unmark Review
- Manual examination submission
- Automatic examination submission

**Question States **

Questions can be represented through different states such as:

| State             | Description                               |
| ----------------- | ----------------------------------------- |
| Current           | Question currently being viewed           |
| Answered          | Question has a selected answer            |
| Not Answered      | Question has not been answered            |
| Marked for Review | Question has been marked for later review |

## 🧮 Automatic Evaluation

After an examination is submitted, Examify evaluates the student's MCQ answers automatically.
The evaluation process follows:

```
Student Answer
      ↓
Compare With Correct Answer
      ↓
Correct / Incorrect / Unanswered
      ↓
Calculate Score
      ↓
Store Examination Result
```
The system can calculate and display information such as:

- Total questions
- Correct answers
- Incorrect answers
- Unanswered questions
- Obtained marks
- Total marks
- Examination performance

## 📊 Results
**Student Results**

After completing an examination, students can view their result.

The result interface can provide information such as:

| Result Information | Description                              |
| ------------------ | ---------------------------------------- |
| Examination        | Name/title of the examination            |
| Subject            | Subject associated with the examination  |
| Score              | Marks obtained by the student            |
| Total Marks        | Maximum marks of the examination         |
| Correct            | Number of correctly answered questions   |
| Wrong              | Number of incorrectly answered questions |
| Unanswered         | Number of questions left unanswered      |

**Admin Results**

Administrators can access examination results and review student submissions.

The result management interface supports:

- Examination-based result viewing
- Student performance viewing
- Result filtering
- Examination submission information
- Individual result details

## 🗃️ Database

Examify uses MySQL with InnoDB as its relational database system.

The main database tables include:

| Table             | Purpose                                           |
| ----------------- | ------------------------------------------------- |
| `admins`          | Stores administrator accounts                     |
| `students`        | Stores student accounts and academic information  |
| `subjects`        | Stores subject information                        |
| `exams`           | Stores examination configuration                  |
| `questions`       | Stores MCQ question bank data                     |
| `exam_attempts`   | Stores student examination attempts               |
| `student_answers` | Stores student answers and evaluation information |

The application uses PDO for database communication.

Database relationships and foreign keys are used to maintain data consistency between students, examinations, subjects, questions, attempts, and answers.
## 🛠️ Tech Stack

| **Layer**         | **Technology**     |
| ----------------- | ------------------ |
| Frontend          | HTML5, CSS3        |
| Client-side Logic | Vanilla JavaScript |
| Backend           | PHP 8+             |
| Database          | MySQL              |
| Database Access   | PDO                |
| Server            | Apache             |
| Authentication    | PHP Sessions       |
| Data Format       | JSON               |
| Database Engine   | MySQL InnoDB       |
| Version Control   | Git / GitHub       |

---

## 📁 Folder Structure
```
online-exam-system/
├── .github/
│   ├── ISSUE_TEMPLATE/
│   └── workflows/
│       └── archive.yml
│
├── admin/
│   ├── admin-dashboard.php
│   ├── admin-guard.php
│   ├── admin-login.php
│   ├── admin-logout.php
│   ├── admin-navbar.php
│   ├── control-exams.php
│   ├── manage-exam.php
│   ├── manage-questions.php
│   ├── manage-subjects.php
│   ├── results.php
│   ├── view-questions.php
│   └── view-results.php
│
├── archive/
│   └── schema.sql
│
├── assets/
│   └── css/
│       ├── login.css
│       ├── register.css
│       ├── student.css
│       └── style.css
│
├── components/
│   ├── navbar.php
│   └── searchbar.php
│
├── config/
│   └── database.php
│
├── student/
│   ├── dashboard.php
│   ├── edit-profile.php
│   ├── exam.php
│   ├── login.php
│   ├── logout.php
│   ├── profile.php
│   ├── question.php
│   ├── register.php
│   ├── result.php
│   └── student-guard.php
│
├── tests/
│   ├── create-credentials.php
│   ├── daa-questions.json
│   ├── networking-questions.json
│   ├── os-questions.json
│   └── prepare-question.php
│
├── utils/
│   ├── anti-cheat.js
│   └── timer.js
│
├── index.php
├── LICENSE
├── production.md
└── README.md
```

---
# 🚀 Production Deployment
Examify includes a production.md file containing information related to production deployment.

The project also contains GitHub Actions configuration for project automation.

Before deploying Examify to a production environment:

- Configure a production MySQL database.
- Use secure database credentials.
- Enable HTTPS.
- Protect administrator accounts.
- Configure secure PHP sessions.
- Disable unnecessary debugging.
- Configure appropriate server permissions.
- Back up the database regularly.
- Test the complete examination workflow before conducting real examinations.

# ⚙️ How to Run Locally

Follow these steps to set up Examify on your local machine.

## Prerequisites

You need a local environment that supports **PHP, Apache, and MySQL**.

Recommended options:

* XAMPP — Windows / macOS / Linux
* WAMP — Windows
* MAMP — macOS
* LAMP — Linux

---

## 1. Clone the Repository

Open your terminal and run:

```bash
git clone https://github.com/ujjaldas-11/online-exam-system.git
```

Then enter the project directory:

```bash
cd online-exam-system
```

---

## 2. Move the Project to Your Server Directory

Move the cloned project into your local server's public directory.

### XAMPP

```text
C:\xampp\htdocs\online-exam-system
```

### WAMP

```text
C:\wamp\www\online-exam-system
```

### Linux

```text
/var/www/html/online-exam-system
```

> The exact web-root directory may vary depending on your Linux distribution and Apache configuration.

---
# 🗄️ Database Setup

Examify includes a `schema.sql` file that can be used to create the required database tables and relationships.

You can import it using **phpMyAdmin** or the **MySQL command line**.

## Method 1 — phpMyAdmin

Recommended for XAMPP/WAMP users.

1. Start **Apache** and **MySQL** from your XAMPP/WAMP control panel.
2. Open:

```text
http://localhost/phpmyadmin
```

3. Click **New** in the sidebar.
4. Create a database named:

```text
examify
```

5. Select the newly created `examify` database.
6. Open the **Import** tab.
7. Select the project's `schema.sql` file.
8. Click **Import** or **Go**.

The required tables and relationships will be created automatically.

---

## Method 2 — MySQL Command Line

Open a terminal and log in to MySQL:

```bash
mysql -u root -p
```

Select the database:

```sql
CREATE DATABASE examify;
USE examify;
```

Then import the schema:

```bash
source /path/to/examify/schema.sql;
```

Finally, exit MySQL:

```sql
exit;
```
---

# 🔐 Configure Database Credentials

Examify uses environment variables for database configuration.

Create a file named exactly:

```text
.env
```

in the project root.

Example:

```env
DB_HOST=localhost
DB_DATABASE=examify
DB_USERNAME=root
DB_PASSWORD=passowrd
DB_CHARSET=utf8mb4
```

Update the values according to your local MySQL configuration.

> **Important:** Never commit your real `.env` file or database passwords to GitHub. Add `.env` to `.gitignore`.

---

# ▶️ Launch the Application

Once Apache, PHP, and MySQL are configured, open:

```text
http://localhost/online-exam-system/index.php
```

You should now be able to access the Examify application.
The application provides separate access areas for:
- **Student**
-**Administrator**
---

# 🤝 Contributing

Contributions, issues, bug reports, and feature requests are welcome!

Whether you're a beginner or an experienced developer, you can help improve Examify.

## Contribution Steps

### 1. Fork the Repository

Click the **Fork** button on the GitHub repository.

### 2. Clone Your Fork

```bash
git clone https://github.com/ujjaldas-11/online-exam-system.git
cd online-exam-system
```

### 3. Create a Feature Branch

```bash
git checkout -b feature/<branch-name>
```

### 4. Make Your Changes

Add a feature, fix a bug, improve the UI, or enhance existing functionality.

### 5. Commit Your Changes

```bash
git add .
git commit -m "Add some AmazingFeature"
```

### 6. Push Your Branch

```bash
git push origin feature/<branch-name>
```

### 7. Open a Pull Request

Go back to the original GitHub repository and create a **Pull Request**.

Explain what you changed and why.

---

# 💡 Future Improvements

The following features can be added in future versions:

* [ ] Randomize the order of answer options.
* [ ] Advanced question-wise performance analytics.
* [ ] Detailed examination reports.
* [ ] CSV/Excel/PDF result export.
* [ ] Student ranking and leaderboard.
* [ ] Advanced examination scheduling.
* [ ] Question difficulty and category management.
* [ ] Teacher accounts and role-based permissions.
* [ ] Multiple administrator roles.
* [ ] Email/OTP verification.
* [ ] Improved mobile responsiveness.
* [ ] Dark mode.
* [ ] Accessibility improvements.
* [ ] Advanced anti-cheating and proctoring mechanisms.
* [ ] Examination notifications.
* [ ] Academic session management.

---

## 📌 About the Project

Examify is designed as a practical **college-level online examination system** that provides a foundation for managing digital examinations.

The project focuses on:

**Subject Management → Question Management → Exam Creation → Secure Examination → Automatic Evaluation → Results**
