# Examify - Online Examination System

Examify is a secure, fast, and responsive online examination platform built with PHP and MySQL. It allows administrators to create timed, department-specific multiple-choice exams and instantly grade student submissions.

## Project Status
**Status:** MVP (Minimum Viable Product) - Fully Functional. 
The core features (registration, exam creation, synchronized timers, and auto-grading) are completely built and working. 

##  Features

###  Admin Features
* **Custom Exam Creation:** Create exams targeted to specific Departments (e.g., BCA, BBA) and Semesters.
* **Question Bank Management:** Add unlimited multiple-choice questions with custom point values.
* **Global Synchronized Timer:** Exams only start when the admin clicks "Start". The timer is server-controlled, meaning all students experience the exact same start and end time.
* **Dashboard Analytics:** View system-wide stats like active exams, total students, and total submissions.

### Student Features
* **Smart Exam Feed:** Students only see exams that strictly match their registered Department and Semester.
* **Live Countdown Timer:** A sticky countdown timer that visually alerts students when time is running out.
* **Auto-Submission:** When the server-side timer hits zero, the exam automatically submits to prevent cheating.
* **Instant Results:** Objective questions are auto-graded immediately, allowing students to view their past scores on their dashboard.
* **Secure Sessions:** Robust PHP session management prevents students from spoofing identities.

---

## Tech Stack
* **Frontend:** HTML5, CSS3, Vanilla JavaScript
* **Backend:** PHP 8+ (PDO for secure database interactions)
* **Database:** MySQL (Relational schema with `ON DELETE CASCADE`)


## Folder Structure
    examify/
      ├── .env                        # Environment variables (DB credentials)
      ├── index.php                   # Main landing page
      ├── README.md                   # Project documentation
      ├── assets/                     # Static assets
      │   └── css/
      │       └── style.css           # Global stylesheet
      ├── config/                     # Configuration files
      │   └── database.php            # PDO connection logic
      ├── schema.sql                  # SQL dump to recreate the database
      │                              
      ├── admin/                      # Admin panel files
      │   ├── admin-dashboard.php           # Admin overview and stats
      │   ├── admin-login.php               # Admin login
      │   ├── admin-logout.php              # Admin session destroy
      │   ├── manage-exam.php         # Create and start exams
      │   ├── manage-questions.php    # Insert questions to exams
      │   
      │   
      └── student/                    # Student panel files
          ├── dashboard.php           # Student overview and past results
          ├── login.php               # Student login
          ├── logout.php              # Student session destroy
          ├── register.php            # Student registration (with dept/sem)
          ├── result.php              # Background script to calculate score
          └── exam.php                # Exam interface with countdown timer

---

## How to Run Locally

Follow these steps to get Examify running on your own machine.

### Prerequisites
You need a local server environment that supports PHP and MySQL. We recommend installing [XAMPP](https://www.apachefriends.org/) (Windows/Mac/Linux) or using WAMP/MAMP.

### Step-by-Step Setup
1. **Clone the Repository**
   Open your terminal and run:
   ```bash
     git clone https://github.com/ujjaldas-11/online-exam-system/

### Move to your Server Directory
### Move the cloned examify folder into your local server's public directory:

    XAMPP: C:\xampp\htdocs\examify

    WAMP: C:\wamp\www\examify

    Linux LAMP: /srv/http/examify
    
    Linux/Mac: /var/www/html/examify

🗄️ Database Setup (using schema.sql)

### To get the app running, you need to create the database tables using the provided schema.sql file. You can do this using   either phpMyAdmin (GUI) or the Command Line.
 ## Method 1: Using phpMyAdmin (Recommended for XAMPP/WAMP users)

  * **Start Apache and MySQL in your XAMPP/WAMP control panel.**

  * **Open your web browser and go to http://localhost/phpmyadmin.**

  * **Click on New in the left sidebar to create a new database.**

  * **Name the database examify and click Create.**

  * **Select the newly created examify database from the left sidebar.**

  * **Click on the Import tab in the top menu.**

  * **Click Choose File and select the sql/schema.sql file from your cloned project folder.**

  * **Scroll to the bottom and click Import (or Go). The system will automatically create all tables and relationships!**

 ## Method 2: Using MySQL Command Line

 * **If you prefer the terminal, you can import the schema directly:**

   * **Open your terminal or command prompt.**

   * **Log into MySQL:**
   Bash

          mysql -u root -p

   * **Run the schema file (replace the path with your actual path to the file):**
   Bash

          source /path/to/your/project/sql/schema.sql;

   * **Type exit to leave the MySQL monitor.**
    
## Configure Database Credentials

    Ensure the database name, username (usually root), and password (usually empty "" on XAMPP) match your local setup.



### Environment Configuration

This project uses a configuration file to securely manage database credentials. 

1. Create a new file in the root directory named exactly `.env` (or copy the example file if provided).
2. Add your database connection details to the file. Here is a sample of what it should look like:

### env file setup 

# .env (Sample)
    DB_HOST=localhost
    DB_NAME=examify
    DB_USER=root
    DB_PASS=password

* **Launch the App!**
* **Open your browser and navigate to:**
* **http://localhost/examify/index.php**


🤝 How to Contribute

## Contributions, issues, and feature requests are highly welcome! Whether you are a beginner or a pro, we'd love your help to   * **make Examify better.**
* **Contribution Steps:**

  * Fork the Project: Click the "Fork" button at the top right of this repository.

  * Clone your Fork:
    
          git clone https://github.com/ujjaldas-11/online-exam-system/


   * Create a Feature Branch:
  Bash

          git checkout -b feature/<branch_name>

  * **Make your Changes: Add your code, fix a bug, or improve the UI.**

  * Commit your Changes:
  Bash

          git commit -m 'Add some AmazingFeature'

  * Push to the Branch:
  Bash

          git push origin feature/<branch_name>

 * **Open a Pull Request: Go back to the original repository on GitHub and click "New Pull Request". Explain what you changed and why!**

## Ideas for Future Contributions 💡

  * Feature:  every students get questions in random order.
  * Feature: add real time timer for examination
  * Design the whole UI
  * Make the UI fully mobile-responsive using CSS Flexbox/Grid or a framework like Bootstrap.
  * Design the exam page , only 1 question should appear on screen at a time, add prev , next button to move between questions.
  * Add an option for teachers to export exam results as a CSV/Excel file.
  * Add a "Review Answers" page where students can see which specific questions they got wrong after the exam ends.


### Built by Ujjal Das
