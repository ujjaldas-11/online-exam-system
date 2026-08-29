<?php
// Landing page — public entry point, no session-gated content here
require_once __DIR__ . '/utils/env.php';

$assetVersion = asset_version();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Examify — Online Exam platform</title>
    <link rel="icon" type="image/x-icon" href="assets/images/examify_icon.ico?v=<?= $assetVersion ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="apple-touch-icon" href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?= $assetVersion ?>">
</head>
<body class="landing-body">

    <nav class="landing-nav">
        <a href="https://www.bistpurulia.org/" class="college-brand" target="_blank" rel="noopener noreferrer" aria-label="Visit Bengal Institute of Science and Technology website">
            <img src="assets/images/college_logo.png" alt="Bengal Institute of Science and Technology logo">
            <div class="college-name"><span>B</span>engal <span>I</span>nstitute of <span>S</span>cience and <span>T</span>echnology</div>
            <div class="college-name-mob">B.I.S.T</div>
        </a>
    </nav>

    <main class="landing-main">
        <div class="hero-bg" aria-hidden="true"></div>

        <section class="hero-section">
            <div class="eyebrow">
                <img src="assets/images/examify_logo.png" alt="Examify">
            </div>
            <h1>Examify</h1>
            <p class="hero-sub">Build question banks, run timed and proctored exams, and get graded results instantly.</p>
            
            <div class="hero-actions">
                <a href="admin/admin-login.php" class="btn btn-primary interactive-btn">Admin Portal</a>
                <a href="student/login.php" class="btn btn-outline interactive-btn">Student Portal</a>
                <a href="docs/user/user_doc.html" class="btn btn-text interactive-btn">Documentation &rarr;</a>
            </div>
        </section>

        <section class="features-section">
            <div class="feature-card interactive-card">
                <div class="feature-icon">📚</div>
                <div class="feature-text">
                    <p class="feature-title">Question Bank</p>
                    <p class="feature-desc">Organize and reuse subjects effortlessly.</p>
                </div>
            </div>
            <div class="feature-card interactive-card">
                <div class="feature-icon">⏱️</div>
                <div class="feature-text">
                    <p class="feature-title">Timed & Proctored</p>
                    <p class="feature-desc">Set windows and monitor live sessions.</p>
                </div>
            </div>
            <div class="feature-card interactive-card">
                <div class="feature-icon">🛡️</div>
                <div class="feature-text">
                    <p class="feature-title">Cheat-Resistant</p>
                    <p class="feature-desc">Full-screen enforcement & tab tracking.</p>
                </div>
            </div>
            <div class="feature-card interactive-card">
                <div class="feature-icon">📊</div>
                <div class="feature-text">
                    <p class="feature-title">Instant Results</p>
                    <p class="feature-desc">Auto-graded scores available immediately.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <a href="index.php" class="landing-logo-sm interactive-link">
            <img src="assets/images/examify_logo.png" alt="." class="footer-logo">
            <span>Examify</span>
        </a>
        <p class="footer-copy">&copy; <?= date('Y') ?> Examify. All rights reserved.</p>
    </footer>

</body>
</html>
