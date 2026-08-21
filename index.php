<?php
// Landing page — public entry point, no session-gated content here
require_once __DIR__ . '/utils/env.php';

$assetVersion = asset_version();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examify — Online Exam platform</title>
    <link rel="icon" type="image/x-icon" href="assets/images/examify_icon.ico?v=<?= $assetVersion ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="apple-touch-icon" href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?= $assetVersion ?>">
</head>
<body class="landing-body">

    <nav class="landing-nav">

        <a href="https://www.bistpurulia.org/"
            class="college-brand"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Visit Bengal Institute of Science and Technology website"
        >

            <img
                src="assets/images/college_logo.png"
                alt="Bengal Institute of Science and Technology logo"
            >

            <div class="college-name">
                <span>B</span>engal
                <span>I</span>nstitute of
                <span>S</span>cience and
                <span>T</span>echnology
            </div>

            <div class="college-name-mob">B.I.S.T</div>
        </a>
    </nav>

    <header class="landing-hero">
        <div class="hero-bg" aria-hidden="true"></div>

        <div class="hero-content">
            <p class="eyebrow">
                <img src="assets/images/examify_logo.png" alt="Examify">
            </p>
            <h1>Online exams that stay fair, end to end.</h1>
            <p class="hero-sub">Build question banks, run timed and proctored exams, and get graded results the moment students submit.</p>
            <div class="hero-actions">
                <a href="admin/admin-login.php" class="btn btn-primary">Admin portal</a>
                <a href="student/login.php" class="btn btn-outline">Student portal</a>
            </div>
        </div>
    </header>

    <section class="landing-features">
        <div class="feature-card">
            <p class="feature-title">Question bank</p>
            <p class="feature-desc">Organize questions by subject, ready to reuse across exams.</p>
        </div>
        <div class="feature-card">
            <p class="feature-title">Timed and proctored</p>
            <p class="feature-desc">Set exam windows and monitor sessions as students work through them.</p>
        </div>
        <div class="feature-card">
            <p class="feature-title">Cheat-resistant exams</p>
            <p class="feature-desc">Full-screen enforcement and tab-switch detection flag suspicious activity as it happens.</p>
        </div>
        <div class="feature-card">
            <p class="feature-title">Instant results</p>
            <p class="feature-desc">Auto-graded scores and exportable reports as soon as exams close.</p>
        </div>
    </section>

    <footer class="landing-footer">
        <a href="index.php" class="landing-logo landing-logo-sm">
            <img src="assets/images/examify_logo.png" alt="." class="footer-logo">
            <span>Examify</span>
        </a>
        <p class="footer-copy">&copy; <?php echo date('Y'); ?> examify. All rights reserved.</p>
    </footer>

</body>
</html>
