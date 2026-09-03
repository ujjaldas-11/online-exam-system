<?php

/**
 * Meet the Developers & Contributors
 * Highlights the engineering team and honorable testers behind Examify.
 */

declare(strict_types=1);

require_once __DIR__ . '/utils/env.php';
require_once __DIR__ . '/utils/sanitize.php';

$assetVersion = asset_version();

$developers = [
    [
        'name' => 'Bibekananda Mudi',
        'github' => 'https://github.com/FunToHard',
        'username' => 'FunToHard',
        'role' => 'Full-Stack Developer',
        'initials' => 'BM',
        'color' => '#3b82f6'
    ],
    [
        'name' => 'Ujjal Das',
        'github' => 'https://github.com/ujjaldas-11',
        'username' => 'ujjaldas-11',
        'role' => 'Full-Stack Developer',
        'initials' => 'UD',
        'color' => '#10b981'
    ],
    [
        'name' => 'Gopal Mahato',
        'github' => 'https://github.com/gopal-mlfullstack',
        'username' => 'gopal-mlfullstack',
        'role' => 'Full-Stack Developer',
        'initials' => 'GM',
        'color' => '#8b5cf6'
    ],
    [
        'name' => 'Chandan Kuiri',
        'github' => 'https://github.com/chandu885',
        'username' => 'chandu885',
        'role' => 'Full-Stack Developer',
        'initials' => 'CK',
        'color' => '#f59e0b'
    ],
    [
        'name' => 'Manoranjan Mardana',
        'github' => 'https://github.com/manaranjan-fullstack',
        'username' => 'manaranjan-fullstack',
        'role' => 'Full-Stack Developer',
        'initials' => 'MM',
        'color' => '#06b6d4'
    ],
    [
        'name' => 'Gitika Jain',
        'github' => 'https://github.com/gitikajain-06',
        'username' => 'gitikajain-06',
        'role' => 'Full-Stack Developer',
        'initials' => 'GJ',
        'color' => '#ec4899'
    ]
];

$testers = [
    [
        'name' => 'Bimalendu Ganguly',
        'github' => 'https://github.com/Arya2005-star',
        'username' => 'Arya2005-star',
        'role' => 'Quality Assurance & Security Testing',
        'badge' => 'Honorable Tester',
        'initials' => 'BG',
        'color' => '#ffd700'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Meet the Developers • Examify</title>
    <link rel="icon" type="image/x-icon" href="assets/images/examify_icon.ico?v=<?= $assetVersion ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="apple-touch-icon" href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/material-symbols.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/components.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?= $assetVersion ?>">
    <style>
        .dev-page-container {
            max-width: 1180px;
            margin: 0 auto;
            padding: 40px 24px 80px;
            width: 100%;
        }

        .dev-hero {
            text-align: center;
            margin-bottom: 56px;
        }

        .dev-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 215, 0, 0.1);
            color: #ffd700;
            border: 1px solid rgba(255, 215, 0, 0.25);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .dev-hero h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #f0f6fc;
            margin-bottom: 14px;
            letter-spacing: -0.02em;
        }

        .dev-hero p {
            color: #8b949e;
            font-size: 1.05rem;
            max-width: 640px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .dev-section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.25rem;
            font-weight: 700;
            color: #f0f6fc;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(240, 246, 252, 0.1);
        }

        .dev-section-title .material-symbols-outlined {
            color: #ffd700;
            font-size: 1.5rem;
        }

        .dev-section-title .count-badge {
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.08);
            color: #8b949e;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .dev-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 56px;
        }

        .dev-card {
            background: rgba(22, 27, 34, 0.85);
            border: 1px solid rgba(240, 246, 252, 0.1);
            border-radius: 14px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
            backdrop-filter: blur(12px);
            position: relative;
            overflow: hidden;
        }

        .dev-card:hover {
            transform: translateY(-3px);
            border-color: rgba(255, 215, 0, 0.35);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.45);
        }

        .dev-card-header {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .dev-avatar-wrap {
            position: relative;
            width: 58px;
            height: 58px;
            flex-shrink: 0;
            border-radius: 50%;
            overflow: hidden;
            background: #161b22;
            border: 2px solid rgba(240, 246, 252, 0.15);
        }

        .dev-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .dev-avatar-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
            background: #1e293b;
        }

        .dev-info {
            flex: 1;
            min-width: 0;
        }

        .dev-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #f0f6fc;
            margin: 0 0 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dev-role-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.12);
            padding: 2px 8px;
            border-radius: 6px;
            margin-bottom: 2px;
        }

        .dev-role-badge.tester {
            color: #ffd700;
            background: rgba(255, 215, 0, 0.12);
            border: 1px solid rgba(255, 215, 0, 0.25);
        }

        .dev-handle {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #8b949e;
            font-size: 0.82rem;
            font-family: monospace;
            margin-top: 4px;
        }

        .dev-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: #f0f6fc;
            border: 1px solid rgba(240, 246, 252, 0.12);
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            margin-top: auto;
        }

        .dev-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            text-decoration: none;
        }

        .github-svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
            flex-shrink: 0;
        }

        .topbar-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 28px;
            background: rgba(18, 18, 18, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(240, 246, 252, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #8b949e;
            font-size: 0.86rem;
            font-weight: 600;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .topbar-back-btn:hover {
            color: #f0f6fc;
            background: rgba(255, 255, 255, 0.06);
            text-decoration: none;
        }

        @media (max-width: 640px) {
            .dev-page-container {
                padding: 24px 16px 60px;
            }
            .dev-hero h1 {
                font-size: 1.85rem;
            }
            .dev-grid {
                grid-template-columns: 1fr;
            }
            .topbar-nav {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body class="landing-body">

    <!-- Top Sticky Navigation Bar -->
    <header class="topbar-nav">
        <a href="index.php" class="landing-logo-sm interactive-link">
            <img src="assets/images/examify_logo.png" alt="." class="footer-logo">
            <span>Examify</span>
        </a>
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="docs/user/user-doc.php" class="topbar-back-btn">
                <span class="material-symbols-outlined icon-xs">menu_book</span>
                <span>Documentation</span>
            </a>
            <a href="index.php" class="topbar-back-btn">
                <span class="material-symbols-outlined icon-xs">arrow_back</span>
                <span>Home</span>
            </a>
        </div>
    </header>

    <div class="dev-page-container">

        <!-- Hero Heading -->
        <div class="dev-hero">
            <div class="dev-badge-pill">
                <span class="material-symbols-outlined icon-xs">code</span>
                <span>Project Credits</span>
            </div>
            <h1>Meet the Developers</h1>
            <p>The software engineering team and testers who designed, built, and verified the Examify online examination platform.</p>
        </div>

        <!-- Section 1: Core Developers -->
        <div class="dev-section-title">
            <span class="material-symbols-outlined">terminal</span>
            <span>Core Engineering Team</span>
            <span class="count-badge"><?= count($developers) ?> Engineers</span>
        </div>

        <div class="dev-grid">
            <?php foreach ($developers as $dev): ?>
                <div class="dev-card">
                    <div class="dev-card-header">
                        <div class="dev-avatar-wrap">
                            <img src="https://github.com/<?= e($dev['username']) ?>.png?size=160"
                                 alt="<?= e($dev['name']) ?>"
                                 class="dev-avatar-img"
                                 loading="lazy"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="dev-avatar-fallback" style="display: none; background: <?= e($dev['color']) ?>;">
                                <?= e($dev['initials']) ?>
                            </div>
                        </div>
                        <div class="dev-info">
                            <div class="dev-role-badge"><?= e($dev['role']) ?></div>
                            <h2 class="dev-name"><?= e($dev['name']) ?></h2>
                            <div class="dev-handle">
                                <svg class="github-svg" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                                </svg>
                                <span>@<?= e($dev['username']) ?></span>
                            </div>
                        </div>
                    </div>

                    <a href="<?= e($dev['github']) ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="dev-btn">
                        <svg class="github-svg" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                        </svg>
                        <span>View GitHub Profile</span>
                        <span class="material-symbols-outlined icon-xs" style="margin-left: auto;">open_in_new</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Section 2: Honorable Testers -->
        <div class="dev-section-title">
            <span class="material-symbols-outlined">verified</span>
            <span>Honorable Testers</span>
            <span class="count-badge"><?= count($testers) ?> Contributor</span>
        </div>

        <div class="dev-grid">
            <?php foreach ($testers as $tester): ?>
                <div class="dev-card" style="border-color: rgba(255, 215, 0, 0.2);">
                    <div class="dev-card-header">
                        <div class="dev-avatar-wrap" style="border-color: rgba(255, 215, 0, 0.35);">
                            <img src="https://github.com/<?= e($tester['username']) ?>.png?size=160"
                                 alt="<?= e($tester['name']) ?>"
                                 class="dev-avatar-img"
                                 loading="lazy"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="dev-avatar-fallback" style="display: none; background: #d97706;">
                                <?= e($tester['initials']) ?>
                            </div>
                        </div>
                        <div class="dev-info">
                            <div class="dev-role-badge tester"><?= e($tester['badge']) ?></div>
                            <h2 class="dev-name"><?= e($tester['name']) ?></h2>
                            <div class="dev-handle">
                                <svg class="github-svg" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                                </svg>
                                <span>@<?= e($tester['username']) ?></span>
                            </div>
                        </div>
                    </div>

                    <a href="<?= e($tester['github']) ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="dev-btn"
                       style="border-color: rgba(255, 215, 0, 0.25);">
                        <svg class="github-svg" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                        </svg>
                        <span>View GitHub Profile</span>
                        <span class="material-symbols-outlined icon-xs" style="margin-left: auto;">open_in_new</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Footer -->
    <footer class="landing-footer">
        <a href="index.php" class="landing-logo-sm interactive-link">
            <img src="assets/images/examify_logo.png" alt="." class="footer-logo">
            <span>Examify</span>
        </a>
        <div class="footer-center">
            <a href="developers.php" class="footer-nav-link" style="color: #ffd700;">
                <span class="material-symbols-outlined icon-xs">group</span>
                <span>Meet the Developers</span>
            </a>
            <span class="footer-sep">&bull;</span>
            <a href="docs/user/user-doc.php" class="footer-nav-link">
                <span class="material-symbols-outlined icon-xs">menu_book</span>
                <span>Documentation</span>
            </a>
        </div>
        <p class="footer-copy">&copy; <?= date('Y') ?> Examify. All rights reserved.</p>
    </footer>

</body>
</html>
