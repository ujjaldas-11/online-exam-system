<?php
// Find out which page the user is currently on (e.g., "dashboard.php")
$current_page = basename($_SERVER['PHP_SELF']);
?>

<link rel="stylesheet" href="../assets/css/components.css">

<nav class="navbar">
    <div class="nav-right">
        <?php if (
            $current_page === 'admin-dashboard.php' ||
            $current_page === 'manage-subjects.php' ||
            $current_page === 'manage-questions.php' ||
            $current_page === 'control-exams.php' ||
            $current_page === 'results.php' ||
            $current_page === 'manage-requests.php'
        ): ?>

            <div class="nav-inner">
                <h1>
                    Hi, <span style="font-size:2rem;"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>!</span>
                </h1>
                <button class="menu-btn" onclick="document.querySelector('.nav-links').classList.toggle('show')">☰</button>

                <div class="nav-links">
                    <a href="admin-dashboard.php">Dashboard</a>
                    <a href="manage-subjects.php">Subjects</a>
                    <a href="manage-questions.php">Questions</a>
                    <a href="control-exams.php">Exams</a>
                    <a href="results.php">Results</a>
                    <a href="manage-requests.php">Manage requests</a>
                    <a href="admin-logout.php" class="logout">Logout</a>
                </div>
            </div>

        <?php elseif ($current_page === 'dashboard.php' || $current_page === 'profile.php' || $current_page === 'edit-profile.php'): ?>
            <div class="nav-inner">
                <span class="nav-greeting">
                    Hi, <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'Student'); ?>!
                </span>

                <button class="menu-btn" onclick="document.querySelector('.nav-links').classList.toggle('show')">☰</button>

                <div class="nav-links">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="profile.php">My Profile</a>
                    <!-- <a href="#exam-history">Exam History</a> -->

                    <a href="logout.php" class="nav-logout">Logout</a>
                </div>

            </div>

        <?php endif ?>
    </div>
</nav>
