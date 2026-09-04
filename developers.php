<?php
/**
 * Meet the Developers & Contributors
 * Examify project credits.
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
        'role' => 'Lead Systems Engineer',
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
        'role' => 'Lead Frontend Developer',
        'initials' => 'GM',
        'color' => '#291064'
    ],
    [
        'name' => 'Chandan Kuiri',
        'github' => 'https://github.com/chandu885',
        'username' => 'chandu885',
        'role' => 'Frontend Developer',
        'initials' => 'CK',
        'color' => '#f59e0b'
    ],
    [
        'name' => 'Manoranjan Mardana',
        'github' => 'https://github.com/manaranjan-fullstack',
        'username' => 'manaranjan-fullstack',
        'role' => 'UI/UX Designer',
        'initials' => 'MM',
        'color' => '#06b6d4'
    ],
    [
        'name' => 'Gitika Jain',
        'github' => 'https://github.com/gitikajain-06',
        'username' => 'gitikajain-06',
        'role' => 'UI/UX Designer',
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meet the Developers • Examify</title>

    <link rel="icon" type="image/x-icon"
          href="assets/images/examify_icon.ico?v=<?= $assetVersion ?>">
    <link rel="icon" type="image/png" sizes="32x32"
          href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="apple-touch-icon"
          href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">

    <link rel="stylesheet"
          href="assets/css/material-symbols.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet"
          href="assets/css/components.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet"
          href="assets/css/landing.css?v=<?= $assetVersion ?>">

    <style>
        :root {
            --navy: #071a33;
            --navy-light: #0c2748;
            --navy-hover: #12365f;
            --bg: radial-gradient(circle at top left, #EFF6FF 0%, #F8FAFC 100%);
            --card: #fff;
            --text: #182033;
            --muted: #687386;
            --border: #e5e8ef;
            --accent: #594e69;
            --shadow: 0 8px 28px rgba(25, 30, 45, .07);
            --shadow-hover: 0 15px 35px rgba(25, 30, 45, .12);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family:"Segoe UI", Frutiger, "Frutiger Linotype", "Dejavu Sans", "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            background: var(--bg);
            color: var(--text);
        }

        a {
            text-decoration: none;
        }

        /* ================= NAVBAR ================= */

        .dev-nav {
            position: sticky;
            top: 0;
            z-index: 100;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 34px;
            background: var(--navy);
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .dev-brand,
        .dev-home {
            display: inline-flex;
            align-items: center;
        }

        .dev-brand {
            gap: 10px;
            color: #fff;
            font-size: 1rem;
            font-weight: 750;
        }

        .dev-brand img {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 9px;
        }

        .dev-home {
            gap: 7px;
            padding: 9px 14px;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 9px;
            color: #d7dfeb;
            background: rgba(255,255,255,.06);
            font-size: .82rem;
            font-weight: 650;
            transition: .2s ease;
        }

        .dev-home:hover {
            color: #fff;
            background: var(--navy-hover);
            border-color: rgba(255,255,255,.22);
        }

        /* ================= MAIN ================= */

        .dev-container {
            width: min(1120px, calc(100% - 40px));
            margin: auto;
            padding: 64px 0 75px;
        }

        .dev-hero {
            max-width: 720px;
            margin: 0 auto 56px;
            text-align: center;
        }

        .dev-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-bottom: 18px;
            padding: 7px 13px;
            border: 1px solid rgba(89,78,105,.15);
            border-radius: 999px;
            color: var(--accent);
            background: rgba(89,78,105,.06);
            font-size: .72rem;
            font-weight: 750;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .dev-badge .material-symbols-outlined {
            font-size: 16px;
        }

        .dev-hero h1 {
            margin: 0;
            font-size: clamp(2rem, 5vw, 3rem);
            line-height: 1.12;
            letter-spacing: -.035em;
            font-weight: 800;
        }

        .dev-hero p {
            max-width: 650px;
            margin: 16px auto 0;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.7;
        }

        /* ================= SECTIONS ================= */

        .dev-section {
            margin-bottom: 52px;
        }

        .dev-section-head {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 20px;
        }

        .dev-section-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: var(--accent);
            background: rgba(89,78,105,.08);
        }

        .dev-section-icon .material-symbols-outlined {
            font-size: 21px;
        }

        .dev-section-title {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 750;
            letter-spacing: -.015em;
        }

        .dev-count {
            padding: 4px 9px;
            border-radius: 999px;
            color: var(--muted);
            background: #eef0f4;
            font-size: .68rem;
            font-weight: 700;
        }

        /* ================= CARDS ================= */

        .dev-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .dev-card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 218px;
            padding: 22px;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: var(--card);
            box-shadow: var(--shadow);
            transition: transform .2s ease,
                        box-shadow .2s ease,
                        border-color .2s ease;
        }

        .dev-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(
                90deg,
                var(--card-color),
                transparent
            );
        }

        .dev-card:hover {
            transform: translateY(-4px);
            border-color: #d8dce5;
            box-shadow: var(--shadow-hover);
        }

        .dev-card-header {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .dev-avatar {
            width: 56px;
            height: 56px;
            flex: 0 0 56px;
            overflow: hidden;
            border: 2px solid #f0f1f5;
            border-radius: 50%;
            background: #f1f3f6;
        }

        .dev-avatar img {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .dev-fallback {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1rem;
            font-weight: 800;
        }

        .dev-info {
            min-width: 0;
        }

        .dev-role {
            display: inline-block;
            margin-bottom: 5px;
            padding: 4px 8px;
            border-radius: 6px;
            color: var(--accent);
            background: rgba(89,78,105,.07);
            font-size: .66rem;
            font-weight: 750;
        }

        .dev-name {
            margin: 0;
            overflow: hidden;
            color: var(--text);
            font-size: 1rem;
            font-weight: 750;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .dev-handle {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 5px;
            overflow: hidden;
            color: var(--muted);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .7rem;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .github-icon {
            width: 14px;
            height: 14px;
            flex: 0 0 14px;
            fill: currentColor;
        }

        .dev-card-footer {
            margin-top: auto;
            padding-top: 20px;
        }

        .github-btn {
            width: 100%;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            color: #344054;
            background: #fafbfc;
            font-size: .76rem;
            font-weight: 700;
            transition: .2s ease;
        }

        .github-btn:hover {
            color: #fff;
            background: #20242b;
            border-color: #20242b;
        }

        .open-icon {
            margin-left: auto;
            font-size: 16px;
        }

        /* ================= TESTERS ================= */

        .tester-section .dev-card {
            max-width: 370px;
            border-color: rgba(217,166,0,.25);
        }

        .tester-section .dev-role {
            color: #9a7000;
            background: rgba(245,190,30,.12);
        }

        .tester-section .dev-avatar {
            border-color: rgba(217,166,0,.3);
        }

        /* ================= FOOTER ================= */

        .dev-footer {
            min-height: 72px;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            padding: 0 34px;
            background: var(--navy);
            border-top: 1px solid rgba(255,255,255,.08);
        }

        .dev-footer-brand {
            justify-self: start;
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: #fff;
            font-size: .9rem;
            font-weight: 750;
        }

        .dev-footer-brand img {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 7px;
        }

        .dev-footer-links {
            justify-self: center;
        }

        .dev-footer-links a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #c5cfdd;
            font-size: .78rem;
            font-weight: 600;
            transition: .2s ease;
        }

        .dev-footer-links a:hover {
            color: #fff;
        }

        .dev-footer-links .material-symbols-outlined {
            font-size: 17px;
        }

        .dev-copy {
            justify-self: end;
            margin: 0;
            color: #8997aa;
            font-size: .7rem;
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 900px) {
            .dev-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .dev-nav {
                height: 62px;
                padding: 0 16px;
            }

            .dev-container {
                width: min(100% - 28px, 540px);
                padding: 44px 0 58px;
            }

            .dev-hero {
                margin-bottom: 42px;
            }

            .dev-hero p {
                font-size: .9rem;
            }

            .dev-grid {
                grid-template-columns: 1fr;
            }

            .dev-card {
                min-height: 205px;
            }

            .tester-section .dev-card {
                max-width: none;
            }

            .dev-footer {
                min-height: auto;
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 22px 18px;
                text-align: center;
            }

            .dev-footer-brand,
            .dev-footer-links,
            .dev-copy {
                justify-self: center;
            }
        }

        @media (max-width: 380px) {
            .dev-home span:last-child {
                display: none;
            }

            .dev-section-head {
                gap: 8px;
            }

            .dev-section-title {
                font-size: 1rem;
            }

            .dev-count {
                font-size: .62rem;
            }
        }
    </style>
</head>

<body>

<div class="dev-page">

    <!-- ================= NAVBAR ================= -->

    <header class="dev-nav">
        <a href="index.php" class="dev-brand">
            <img src="assets/images/examify_logo.png" alt="Examify">
            <span>Examify</span>
        </a>

        <a href="index.php" class="dev-home">
            <span class="material-symbols-outlined icon-xs">
                arrow_back
            </span>
            <span>Home</span>
        </a>
    </header>

    <!-- ================= MAIN ================= -->

    <main class="dev-container">

        <!-- Hero -->
        <section class="dev-hero">
            <div class="dev-badge">
                <span class="material-symbols-outlined">code</span>
                Project Credits
            </div>

            <h1>Meet the Developers</h1>

            <p>
                The people behind Examify who designed, developed,
                tested, and helped shape the online examination platform.
            </p>
        </section>

        <!-- Development Team -->
        <section class="dev-section">

            <div class="dev-section-head">
                <div class="dev-section-icon">
                    <span class="material-symbols-outlined">
                        terminal
                    </span>
                </div>

                <h2 class="dev-section-title">
                    Core Development Team
                </h2>

                <span class="dev-count">
                    <?= count($developers) ?> Members
                </span>
            </div>

            <div class="dev-grid">

                <?php foreach ($developers as $dev): ?>

                    <article
                        class="dev-card"
                        style="--card-color: <?= e($dev['color']) ?>;"
                    >

                        <div class="dev-card-header">

                            <div class="dev-avatar">
                                <img
                                    src="https://github.com/<?= e($dev['username']) ?>.png?size=160"
                                    alt="<?= e($dev['name']) ?>"
                                    loading="lazy"
                                    onerror="
                                        this.style.display='none';
                                        this.nextElementSibling.style.display='grid';
                                    "
                                >

                                <div
                                    class="dev-fallback"
                                    style="
                                        display:none;
                                        background:<?= e($dev['color']) ?>;
                                    "
                                >
                                    <?= e($dev['initials']) ?>
                                </div>
                            </div>

                            <div class="dev-info">

                                <span class="dev-role">
                                    <?= e($dev['role']) ?>
                                </span>

                                <h3 class="dev-name">
                                    <?= e($dev['name']) ?>
                                </h3>

                                <div class="dev-handle">
                                    <svg
                                        class="github-icon"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                                    </svg>

                                    @<?= e($dev['username']) ?>
                                </div>

                            </div>
                        </div>

                        <div class="dev-card-footer">

                            <a
                                href="<?= e($dev['github']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="github-btn"
                            >
                                <svg
                                    class="github-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                                </svg>

                                <span>View GitHub Profile</span>

                                <span class="material-symbols-outlined open-icon">
                                    open_in_new
                                </span>
                            </a>

                        </div>
                    </article>

                <?php endforeach; ?>

            </div>
        </section>

        <!-- Testers -->
        <section class="dev-section tester-section">

            <div class="dev-section-head">

                <div class="dev-section-icon">
                    <span class="material-symbols-outlined">
                        verified
                    </span>
                </div>

                <h2 class="dev-section-title">
                    Honorable Testers
                </h2>

                <span class="dev-count">
                    <?= count($testers) ?> Contributor
                </span>

            </div>

            <div class="dev-grid">

                <?php foreach ($testers as $tester): ?>

                    <article
                        class="dev-card"
                        style="--card-color: <?= e($tester['color']) ?>;"
                    >

                        <div class="dev-card-header">

                            <div class="dev-avatar">

                                <img
                                    src="https://github.com/<?= e($tester['username']) ?>.png?size=160"
                                    alt="<?= e($tester['name']) ?>"
                                    loading="lazy"
                                    onerror="
                                        this.style.display='none';
                                        this.nextElementSibling.style.display='grid';
                                    "
                                >

                                <div
                                    class="dev-fallback"
                                    style="
                                        display:none;
                                        background:#d97706;
                                    "
                                >
                                    <?= e($tester['initials']) ?>
                                </div>

                            </div>

                            <div class="dev-info">

                                <span class="dev-role">
                                    <?= e($tester['badge']) ?>
                                </span>

                                <h3 class="dev-name">
                                    <?= e($tester['name']) ?>
                                </h3>

                                <div class="dev-handle">

                                    <svg
                                        class="github-icon"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                                    </svg>

                                    @<?= e($tester['username']) ?>

                                </div>

                            </div>
                        </div>

                        <div class="dev-card-footer">

                            <a
                                href="<?= e($tester['github']) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="github-btn"
                            >

                                <svg
                                    class="github-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
                                </svg>

                                <span>View GitHub Profile</span>

                                <span class="material-symbols-outlined open-icon">
                                    open_in_new
                                </span>

                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>
        </section>

    </main>

    <!-- ================= FOOTER ================= -->

    <footer class="dev-footer">

        <!-- Left -->
        <a href="index.php" class="dev-footer-brand">
            <img src="assets/images/examify_logo.png" alt="Examify">
            <span>Examify</span>
        </a>

        <!-- Center -->
        <div class="dev-footer-links">
            <a href="docs/user/user-doc.php">
                <span class="material-symbols-outlined">
                    menu_book
                </span>
                Documentation
            </a>
        </div>

        <!-- Right -->
        <p class="dev-copy">
            &copy; <?= date('Y') ?> Examify. All rights reserved.
        </p>

    </footer>

</div>

</body>
</html>