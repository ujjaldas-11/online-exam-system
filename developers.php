<?php
/**
 * Contributors – Examify
 */

declare(strict_types=1);

require_once __DIR__ . '/utils/env.php';
require_once __DIR__ . '/utils/sanitize.php';

$assetVersion = asset_version();

/* ---------- Sections data ---------- */
$codeBy = [
    [
        'name'     => 'Bibekananda Mudi',
        'github'   => 'https://github.com/FunToHard',
        'username' => 'FunToHard',
        'role'     => 'Full-Stack Developer',
        'initials' => 'BM',
        'color'    => '#1E4ED8',
    ],
    [
        'name'     => 'Ujjal Das',
        'github'   => 'https://github.com/ujjaldas-11',
        'username' => 'ujjaldas-11',
        'role'     => 'Full-Stack Developer',
        'initials' => 'UD',
        'color'    => '#0EA5E9',
    ],
];

$designBy = [
    [
        'name'     => 'Gopal Mahato',
        'github'   => 'https://github.com/gopal-mlfullstack',
        'username' => 'gopal-mlfullstack',
        'role'     => 'UI Designer',
        'initials' => 'GM',
        'color'    => '#4338CA',
        
    ],
    [
        'name'     => 'Manaranjan Mardana',
        'github'   => 'https://github.com/manaranjan-fullstack',
        'username' => 'manaranjan-fullstack',
        'role'     => 'Supporting Designer',
        'initials' => 'MM',
        'color'    => '#0891B2',
    ],
    [
        'name'     => 'Chandan Kuiri',
        'github'   => 'https://github.com/chandu885',
        'username' => 'chandu885',
        'role'     => 'Supporting Designer',
        'initials' => 'CK',
        'color'    => '#6366F1',
    ],
];

$documentationBy = [
    [
        'name'     => 'Gitika Jain',
        'github'   => 'https://github.com/gitikajain-06',
        'username' => 'gitikajain-06',
        'role'     => 'Documentation',
        'initials' => 'GJ',
        'color'    => '#DB2777',
    ],
];

$testedBy = [
    [
        'name'     => 'Bimalendu Ganguly',
        'github'   => 'https://github.com/Arya2005-star',
        'username' => 'Arya2005-star',
        'role'     => 'Quality Assurance',
        'initials' => 'BG',
        'color'    => '#D97706',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contributors • Examify</title>

    <link rel="icon" type="image/x-icon" href="assets/images/examify_icon.ico?v=<?= $assetVersion ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="apple-touch-icon" href="assets/images/examify_logo.png?v=<?= $assetVersion ?>">

    <link rel="stylesheet" href="assets/css/material-symbols.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="assets/css/landing.css?v=<?= $assetVersion ?>">

    <style>
        :root {
            --bg: radial-gradient(circle at top left, #EFF6FF 0%, #F8FAFC 100%);
            --nav: #0F172A;
            --text: #0F172A;
            --muted: #334155;
            --border: #E2E8F0;
            --accent: #1E4ED8;
            --accent-light: #38BDF8;
            --surface: #FFFFFF;
            --radius: 14px;
            --shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
            --shadow-hover: 0 8px 20px rgba(15, 23, 42, 0.1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; color: inherit; }

        /* Nav */
        .dev-nav {
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 28px;
            background: var(--nav);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .dev-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-weight: 800;
            font-size: 1.1rem;
        }
        .dev-brand img {
            width: 38px;
            height: 38px;
            object-fit: contain;
            border-radius: 50%;
        }
        .dev-brand:hover { color: var(--accent-light); }

        .dev-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            color: #E2E8F0;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.06);
            transition: all 0.2s ease;
        }
        .dev-home:hover {
            color: #fff;
            background: #1E293B;
        }

        /* Main */
        .dev-main {
            flex: 1;
            width: min(960px, calc(100% - 40px));
            margin: 0 auto;
            padding: 40px 0 56px;
        }

        /* Hero – simple */
        .dev-hero {
            text-align: center;
            margin-bottom: 40px;
        }
        .dev-hero h1 {
            font-size: clamp(1.9rem, 4.5vw, 2.6rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #0F172A 40%, #1E4ED8 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: #0F172A;
            margin-bottom: 8px;
        }
        .dev-hero p {
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Section */
        .dev-section {
            margin-bottom: 36px;
        }
        .dev-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
            font-weight: 750;
            margin-bottom: 14px;
            color: var(--text);
        }
        .dev-section-title .material-symbols-outlined {
            font-size: 22px;
            color: var(--accent);
        }

        /* Grid */
        .dev-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }

        /* Card – simple */
        .dev-card {
            display: flex;
            flex-direction: column;
            padding: 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .dev-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        /* Importance highlight */
        .dev-card.is-important {
            border-color: rgba(30, 78, 216, 0.35);
            background: linear-gradient(180deg, #F8FAFF 0%, #FFFFFF 100%);
        }
        .dev-card.is-lead {
            border-color: rgba(67, 56, 202, 0.4);
            background: linear-gradient(180deg, #F5F3FF 0%, #FFFFFF 100%);
        }

        .dev-card-top {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .dev-avatar {
            position: relative;
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border-radius: 50%;
            overflow: hidden;
            background: #F1F5F9;
        }
        .dev-avatar img {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .dev-fallback {
            position: absolute;
            inset: 0;
            z-index: 1;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 0.9rem;
            font-weight: 800;
        }

        .dev-info { min-width: 0; }
        .dev-name {
            font-size: 0.95rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .dev-role {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            margin-top: 2px;
        }
        .dev-card.is-lead .dev-role {
            color: #4338CA;
            font-weight: 700;
        }
        .dev-card.is-important .dev-role {
            color: var(--accent);
        }

        .github-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: auto;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #F8FAFC;
            color: #334155;
            font-size: 0.78rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .github-btn:hover {
            color: #fff;
            background: #0F172A;
            border-color: #0F172A;
        }
        .github-icon {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }

        /* Footer */
        .dev-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 28px;
            background: var(--nav);
            border-top: 1px solid rgba(255,255,255,0.08);
            color: #E2E8F0;
        }
        .dev-footer-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
        }
        .dev-footer-brand img {
            width: 24px;
            height: 24px;
            object-fit: contain;
            border-radius: 6px;
        }
        .dev-footer-brand:hover { color: var(--accent-light); }

        .dev-footer-links a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #E2E8F0;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .dev-footer-links a:hover { color: #FFD700; }

        .dev-copy {
            font-size: 0.75rem;
            color: #94A3B8;
            margin: 0;
        }

        @media (max-width: 640px) {
            .dev-nav { padding: 10px 16px; }
            .dev-main {
                width: min(100% - 28px, 100%);
                padding: 32px 0 48px;
            }
            .dev-grid { grid-template-columns: 1fr; }
            .dev-footer {
                flex-direction: column;
                gap: 10px;
                padding: 16px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<header class="dev-nav">
    <a href="index.php" class="dev-brand">
        <img src="assets/images/examify_logo.png" alt="Examify">
        <span>Examify</span>
    </a>
    <a href="index.php" class="dev-home">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>
        <span>Home</span>
    </a>
</header>

<main class="dev-main">

    <section class="dev-hero">
        <h1>Contributors</h1>
        <p>The team behind Examify</p>
    </section>

    <!-- 1. Code By -->
    <section class="dev-section">
        <h2 class="dev-section-title">
            <span class="material-symbols-outlined">terminal</span>
            Code By
        </h2>
        <div class="dev-grid">
            <?php foreach ($codeBy as $dev): ?>
                <?php
                $local = 'assets/images/devs/' . $dev['username'] . '.png';
                $has   = file_exists(__DIR__ . '/' . $local);
                ?>
                <article class="dev-card is-important">
                    <div class="dev-card-top">
                        <div class="dev-avatar">
                            <div class="dev-fallback" style="background:<?= e($dev['color']) ?>;">
                                <?= e($dev['initials']) ?>
                            </div>
                            <?php if ($has): ?>
                                <img src="<?= e($local) ?>" alt="<?= e($dev['name']) ?>" loading="lazy" onerror="this.style.display='none'">
                            <?php endif; ?>
                        </div>
                        <div class="dev-info">
                            <div class="dev-name"><?= e($dev['name']) ?></div>
                            <div class="dev-role"><?= e($dev['role']) ?></div>
                        </div>
                    </div>
                    <a href="<?= e($dev['github']) ?>" target="_blank" rel="noopener noreferrer" class="github-btn">
                        <svg class="github-icon" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        GitHub
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 2. Design By -->
    <section class="dev-section">
        <h2 class="dev-section-title">
            <span class="material-symbols-outlined">palette</span>
            Design By
        </h2>
        <div class="dev-grid">
            <?php foreach ($designBy as $dev): ?>
                <?php
                $local = 'assets/images/devs/' . $dev['username'] . '.png';
                $has   = file_exists(__DIR__ . '/' . $local);
                $lead  = !empty($dev['lead']);
                ?>
                <article class="dev-card<?= $lead ? ' is-lead' : '' ?>">
                    <div class="dev-card-top">
                        <div class="dev-avatar">
                            <div class="dev-fallback" style="background:<?= e($dev['color']) ?>;">
                                <?= e($dev['initials']) ?>
                            </div>
                            <?php if ($has): ?>
                                <img src="<?= e($local) ?>" alt="<?= e($dev['name']) ?>" loading="lazy" onerror="this.style.display='none'">
                            <?php endif; ?>
                        </div>
                        <div class="dev-info">
                            <div class="dev-name"><?= e($dev['name']) ?></div>
                            <div class="dev-role"><?= e($dev['role']) ?></div>
                        </div>
                    </div>
                    <a href="<?= e($dev['github']) ?>" target="_blank" rel="noopener noreferrer" class="github-btn">
                        <svg class="github-icon" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        GitHub
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 3. Documentation By -->
    <section class="dev-section">
        <h2 class="dev-section-title">
            <span class="material-symbols-outlined">menu_book</span>
            Documentation By
        </h2>
        <div class="dev-grid">
            <?php foreach ($documentationBy as $dev): ?>
                <?php
                $local = 'assets/images/devs/' . $dev['username'] . '.png';
                $has   = file_exists(__DIR__ . '/' . $local);
                ?>
                <article class="dev-card">
                    <div class="dev-card-top">
                        <div class="dev-avatar">
                            <div class="dev-fallback" style="background:<?= e($dev['color']) ?>;">
                                <?= e($dev['initials']) ?>
                            </div>
                            <?php if ($has): ?>
                                <img src="<?= e($local) ?>" alt="<?= e($dev['name']) ?>" loading="lazy" onerror="this.style.display='none'">
                            <?php endif; ?>
                        </div>
                        <div class="dev-info">
                            <div class="dev-name"><?= e($dev['name']) ?></div>
                            <div class="dev-role"><?= e($dev['role']) ?></div>
                        </div>
                    </div>
                    <a href="<?= e($dev['github']) ?>" target="_blank" rel="noopener noreferrer" class="github-btn">
                        <svg class="github-icon" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        GitHub
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 4. Tested By -->
    <section class="dev-section">
        <h2 class="dev-section-title">
            <span class="material-symbols-outlined">verified</span>
            Tested By
        </h2>
        <div class="dev-grid">
            <?php foreach ($testedBy as $dev): ?>
                <?php
                $local = 'assets/images/devs/' . $dev['username'] . '.png';
                $has   = file_exists(__DIR__ . '/' . $local);
                ?>
                <article class="dev-card">
                    <div class="dev-card-top">
                        <div class="dev-avatar">
                            <div class="dev-fallback" style="background:<?= e($dev['color']) ?>;">
                                <?= e($dev['initials']) ?>
                            </div>
                            <?php if ($has): ?>
                                <img src="<?= e($local) ?>" alt="<?= e($dev['name']) ?>" loading="lazy" onerror="this.style.display='none'">
                            <?php endif; ?>
                        </div>
                        <div class="dev-info">
                            <div class="dev-name"><?= e($dev['name']) ?></div>
                            <div class="dev-role"><?= e($dev['role']) ?></div>
                        </div>
                    </div>
                    <a href="<?= e($dev['github']) ?>" target="_blank" rel="noopener noreferrer" class="github-btn">
                        <svg class="github-icon" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                        GitHub
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

</main>

<footer class="dev-footer">
    <a href="index.php" class="dev-footer-brand">
        <img src="assets/images/examify_logo.png" alt="Examify">
        <span>Examify</span>
    </a>
    <div class="dev-footer-links">
        <a href="docs/user/user-doc.php">
            <span class="material-symbols-outlined" style="font-size:18px">menu_book</span>
            Documentation
        </a>
    </div>
    <p class="dev-copy">&copy; <?= date('Y') ?> Examify. All rights reserved.</p>
</footer>

</body>
</html>