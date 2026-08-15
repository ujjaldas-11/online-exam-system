<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examify • Online Examination System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    
</head>
<body>

<nav>
    <div class="nav-inner">
        <a href="index.php" class="logo">Exam<span>ify</span></a>
    </div>
</nav>

<header class="hero">
    <div class="hero-content">
        <h1>Welcome to Examify</h1>
        <p>A modern and secure platform for conducting online semester examinations.</p>

        <div class="cta">
            <a href="student/login.php" class="btn btn-primary">Student Portal</a>
            <a href="admin/admin-login.php" class="btn btn-outline">Admin Portal</a>
        </div>
    </div>
</header>

<section class="features">
    <div class="card">
        <div class="icon">⏱️</div>
        <h3>Synchronized Timers</h3>
        <p>Server-side timers ensure every student starts and ends at the exact same time.</p>
    </div>

    <div class="card">
        <div class="icon">🛡️</div>
        <h3>Secure Submissions</h3>
        <p>Transactions and unique constraints prevent lost answers and duplicate attempts.</p>
    </div>

    <div class="card">
        <div class="icon">📊</div>
        <h3>Instant Auto-Grading</h3>
        <p>Objective questions are graded immediately and results are available right after submission.</p>
    </div>
</section>

<footer>
    &copy; <?= date('Y') ?> Examify. All rights reserved.
</footer>

</body>
</html>