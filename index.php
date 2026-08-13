<?php
// index.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examify - Online Examination System</title>
    <style>
        /* CSS Variables for consistent theming */
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --dark-color: #343a40;
            --light-bg: #f4f7f6;
            --white: #ffffff;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-bg);
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Navigation Bar */
        nav {
            background-color: var(--white);
            padding: 15px 50px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        nav .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: var(--white);
            text-align: center;
            padding: 100px 20px;
        }

        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            margin-top: 0;
        }

        .hero p {
            font-size: 20px;
            max-width: 600px;
            margin: 0 auto 40px auto;
            opacity: 0.9;
        }

        /* Call to Action Buttons */
        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            text-decoration: none;
            border-radius: 5px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .btn-student {
            background-color: var(--white);
            color: var(--primary-color);
        }

        .btn-admin {
            background-color: transparent;
            color: var(--white);
            border: 2px solid var(--white);
        }

        .btn-admin:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Features Section */
        .features {
            display: flex;
            justify-content: center;
            gap: 30px;
            padding: 60px 20px;
            max-width: 1200px;
            margin: 0 auto;
            flex-wrap: wrap;
        }

        .feature-card {
            background: var(--white);
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            flex: 1;
            min-width: 250px;
            text-align: center;
            border-top: 4px solid var(--primary-color);
        }

        .feature-card h3 {
            color: var(--dark-color);
            margin-bottom: 15px;
        }

        .feature-card p {
            color: var(--secondary-color);
            font-size: 15px;
        }

        .feature-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 20px;
            background-color: var(--dark-color);
            color: var(--white);
            margin-top: 40px;
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav>
        <a href="index.php" class="logo">Examify</a>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <h1>Welcome to Examify</h1>
        <p>A modern, secure, and streamlined platform for conducting and managing online semester examinations.</p>
        
        <div class="cta-buttons">
            <!-- Link to Student Portal -->
            <a href="student/login.php" class="btn btn-student">👨‍🎓 Student Portal</a>
            
            <!-- Link to Admin Portal -->
            <a href="admin/admin-login.php" class="btn btn-admin">⚙️ Admin Portal</a>
        </div>
    </header>

    <!-- Decorative Features Section -->
    <section class="features">
        <div class="feature-card">
            <div class="feature-icon">⏱️</div>
            <h3>Synchronized Timers</h3>
            <p>Global server-side timers ensure exams start and end at the exact same moment for all connected students.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">🛡️</div>
            <h3>Secure Submissions</h3>
            <p>Database transactions ensure that no answers are lost, and duplicate submissions are automatically blocked.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon">📊</div>
            <h3>Instant Auto-Grading</h3>
            <p>Objective questions are evaluated instantly against the database key, providing immediate results upon submission.</p>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Examify System. All rights reserved.</p>
    </footer>

</body>
</html>