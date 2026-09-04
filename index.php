<?php
// Landing page — public entry point, no session-gated content here
require_once __DIR__ . '/utils/env.php';
require_once __DIR__ . '/utils/auth.php';
require_once __DIR__ . '/config/database.php';

$assetVersion = asset_version();
$isInitialized = isset($pdo) ? is_system_initialized($pdo) : true;
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
    <link rel="stylesheet" href="assets/css/material-symbols.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?= $assetVersion ?>">
</head>
<body class="landing-body">
    <?php if (!$isInitialized): ?>
        <div style="background: #1e3a8a; color: #f8fafc; text-align: center; padding: 10px 16px; font-size: 0.88rem; display: flex; align-items: center; justify-content: center; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.2);">
            <span style="background: #eab308; color: #1e293b; font-weight: 700; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">Setup</span>
            System requires first-time initialization.
            <a href="admin/setup.php" style="color: #67e8f9; font-weight: 600; text-decoration: underline;">Configure Superadmin Password &rarr;</a>
        </div>
    <?php endif; ?>

    <nav class="landing-nav">
        <a href="https://www.bistpurulia.org/" class="college-brand" target="_blank" rel="noopener noreferrer" aria-label="Visit Bengal Institute of Science and Technology website">
            <img src="assets/images/college_logo_.png" alt="Bengal Institute of Science and Technology logo">
            <div class="college-name" title="Bengal Institute of Science and Technology" ><span>B</span>engal <span>I</span>nstitute of <span>S</span>cience and <span>T</span>echnology</div>
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
                <a href="admin/admin-login.php" class="btn btn-admin">Admin Portal</a>
                <a href="student/login.php" class="btn btn-student">Student Portal</a>
                <a href="docs/user/user-doc.php" class="btn btn-text-documentation">Documentation &rarr;</a>
            </div>
        </section>

        <section class="features-section">
            <div class="feature-card interactive-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">quiz</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Question Bank</p>
                    <p class="feature-desc">Organize and reuse subjects effortlessly.</p>
                </div>
            </div>
            <div class="feature-card interactive-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">timer</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Timed & Proctored</p>
                    <p class="feature-desc">Set windows and monitor live sessions.</p>
                </div>
            </div>
            <div class="feature-card interactive-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">shield</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Cheat-Resistant</p>
                    <p class="feature-desc">Full-screen enforcement & tab tracking.</p>
                </div>
            </div>
            <div class="feature-card interactive-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">analytics</span>
                </div>
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
        <div class="footer-center">
            <a href="developers.php" class="footer-nav-link">
                <span class="material-symbols-outlined icon-xs">group</span>
                <span>Meet the Developers</span>
            </a>
            
    
        </div>
        <p class="footer-copy">&copy; <?= date('Y') ?> Examify. All rights reserved.</p>
    </footer>

</body>
</html>
