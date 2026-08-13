# Examify — Online Examination System

**Examify** is a secure, fast, and responsive online examination platform built with **PHP and MySQL**. It enables administrators and teachers to create and manage timed, department-specific multiple-choice examinations while providing students with a simple and secure interface for taking exams and viewing results.

## 🎯 Motive

The goal of Examify is to provide a complete digital examination system that simplifies exam creation, management, evaluation, and result tracking.

### Core Capabilities

* **Admin Panel** — Create and manage exams, control timers, build a large question bank, select departments/classes and semesters, configure marks, duration, question count, and exam schedules.
* **Randomized Questions** — Teachers can add, for example, 50 questions to an exam while selecting only 20 questions for the actual examination. Each student receives a randomized set of questions.
* **Student Panel** — Students log in using their roll number and password to access their profile, upcoming exams, previous exams, results, and rankings.
* **Exam Interface** — Provides question navigation, countdown timer, next/previous controls, mark-for-review functionality, auto-saving, and automatic submission when the time expires.
* **Results & Rankings** — Students can view their score, percentage, correct/wrong/unanswered questions, rank, and optionally the top 5 performers.
* **Teacher Analytics** — Teachers can view student scores, rankings, average marks, difficult questions, and overall exam performance.
* **Exam Management** — Exams can be targeted to specific departments, classes, or semesters and can potentially be conducted for multiple groups simultaneously.
* **Exam Security** — Includes one-attempt restrictions, server-side timing, preservation of each student's randomized question set, and restrictions against accessing an exam before or after its scheduled time.

---

## 🚀 Project Status

**Status: MVP (Minimum Viable Product) — Fully Functional**

The core examination workflow is currently implemented, including:

* Student registration and authentication
* Admin authentication
* Exam creation and management
* Question management
* Department/semester-based exam access
* Server-controlled examination timers
* Automatic submission
* Automatic grading
* Student result viewing
* Basic dashboard statistics

The project is actively open to improvements, UI enhancements, and additional examination features.

---

## ✨ Features

### 👨‍🏫 Admin Features

* **Custom Exam Creation** — Create exams for specific departments such as BCA, BBA, etc., and target particular semesters.
* **Question Bank Management** — Add and manage multiple-choice questions with customizable marks.
* **Exam Control** — Start and manage examinations from the admin panel.
* **Server-Controlled Timer** — Examination timing is controlled by the server to maintain consistent start and end times for students.
* **Dashboard Analytics** — View important statistics such as total students, active exams, and total submissions.
* **Result Management** — View student performance, scores, rankings, and overall examination statistics.

### 🎓 Student Features

* **Smart Exam Feed** — Students only see examinations assigned to their registered department and semester.
* **Live Countdown Timer** — A visible countdown keeps students informed about the remaining examination time.
* **Auto-Submission** — The examination is automatically submitted when the server-side timer expires.
* **Instant Results** — Objective questions are automatically evaluated after submission.
* **Exam History** — Students can view their previous examination results.
* **Secure Sessions** — PHP session management helps prevent unauthorized access and identity spoofing.

---

## 🛠️ Tech Stack

| Layer           | Technology                                 |
| --------------- | ------------------------------------------ |
| Frontend        | HTML5, CSS3, Vanilla JavaScript            |
| Backend         | PHP 8+                                     |
| Database        | MySQL                                      |
| Database Access | PDO                                        |
| Server          | Apache                                     |
| Authentication  | PHP Sessions                               |
| Database Design | Relational schema with `ON DELETE CASCADE` |

---

## 📁 Folder Structure

```text
examify/
├── .env                              # Environment variables
├── index.php                         # Main landing page
├── README.md                         # Project documentation
├── schema.sql                        # Database schema
│
├── assets/
│   └── css/
│       └── style.css                 # Global stylesheet
│
├── config/
│   └── database.php                  # PDO database connection
│
├── admin/
│   ├── admin-dashboard.php           # Admin dashboard and statistics
│   ├── admin-login.php               # Admin authentication
│   ├── admin-logout.php              # Admin session logout
│   ├── manage-exam.php               # Create and manage exams
│   └── manage-questions.php          # Manage exam questions
│
└── student/
    ├── dashboard.php                 # Student dashboard and results
    ├── login.php                     # Student login
    ├── logout.php                    # Student logout
    ├── register.php                  # Student registration
    ├── result.php                    # Result calculation/display
    └── exam.php                      # Examination interface
```

---

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
C:\xampp\htdocs\examify
```

### WAMP

```text
C:\wamp\www\examify
```

### Linux

```text
/var/www/html/examify
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
DB_NAME=examify
DB_USER=root
DB_PASS=password
```

Update the values according to your local MySQL configuration.

> **Important:** Never commit your real `.env` file or database passwords to GitHub. Add `.env` to `.gitignore`.

---

# ▶️ Launch the Application

Once Apache, PHP, and MySQL are configured, open:

```text
http://localhost/examify/index.php
```

You should now be able to access the Examify application.

---

# 🤝 Contributing

Contributions, issues, bug reports, and feature requests are welcome!

Whether you're a beginner or an experienced developer, you can help make Examify better.

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

Add a feature, fix a bug, improve the UI, or enhance the existing functionality.

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

The following features are planned or can be contributed to the project:

* [ ] Randomize the order of questions for every student.
* [ ] Randomize the order of answer options.
* [ ] Improve real-time examination timer synchronization.
* [ ] Redesign the complete application UI.
* [ ] Make the entire application fully mobile responsive.
* [ ] Display one question at a time on the examination page.
* [ ] Add improved **Previous / Next / Mark for Review** navigation.
* [ ] Add an exam review page before final submission.
* [ ] Allow teachers to export examination results as **CSV/Excel** files.
* [ ] Add a detailed **Review Answers** page after examination.
* [ ] Show question-wise performance and difficulty statistics.
* [ ] Add improved student ranking and leaderboard functionality.
* [ ] Add examination scheduling with start/end dates and times.
* [ ] Improve exam security and anti-cheating mechanisms.

---

## 📌 About the Project

Examify is designed as a practical **college-level online examination system** and can be extended into a larger examination management platform.

The project focuses on building a reliable foundation for:

**Exam Creation → Question Management → Secure Examination → Automatic Evaluation → Results & Analytics**
