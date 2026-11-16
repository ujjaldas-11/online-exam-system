<?php
/**
 * Contributors – Examify
 */
require_once __DIR__ . '/utils/env.php';
require_once __DIR__ . '/utils/sanitize.php';

$assetVersion = asset_version();

$codeBy = [
    [
        'name' => 'Bibekananda Mudi',
        'github' => 'https://github.com/FunToHard',
        'username' => 'FunToHard',
        'role' => 'Full-Stack Developer',
        'initials' => 'BM',
        'color' => '#1E4ED8',
    ],
    [
        'name' => 'Ujjal Das',
        'github' => 'https://github.com/ujjaldas-11',
        'username' => 'ujjaldas-11',
        'role' => 'Full-Stack Developer',
        'initials' => 'UD',
        'color' => '#0EA5E9',
    ],
];

$designBy = [
    [
        'name' => 'Gopal Mahato',
        'github' => 'https://github.com/gopal-mlfullstack',
        'username' => 'gopal-mlfullstack',
        'role' => 'UI Designer',
        'initials' => 'GM',
        'color' => '#4338CA',
    ],
    [
        'name' => 'Manaranjan Mardana',
        'github' => 'https://github.com/manaranjan-fullstack',
        'username' => 'manaranjan-fullstack',
        'role' => 'Supporting Designer',
        'initials' => 'MM',
        'color' => '#0891B2',
    ],
    [
        'name' => 'Chandan Kuiri',
        'github' => 'https://github.com/chandu885',
        'username' => 'chandu885',
        'role' => 'Supporting Designer',
        'initials' => 'CK',
        'color' => '#6366F1',
    ],
];

$documentationBy = [
    [
        'name' => 'Gitika Jain',
        'github' => 'https://github.com/gitikajain-06',
        'username' => 'gitikajain-06',
        'role' => 'Documentation',
        'initials' => 'GJ',
        'color' => '#DB2777',
    ],
];

$testedBy = [
    [
        'name' => 'Bimalendu Ganguly',
        'github' => 'https://github.com/Arya2005-star',
        'username' => 'Arya2005-star',
        'role' => 'Quality Assurance',
        'initials' => 'BG',
        'color' => '#D97706',
    ],
];

$sections = [
    ['title' => 'Code By', 'icon' => 'terminal', 'members' => $codeBy, 'highlight' => true],
    ['title' => 'Design By', 'icon' => 'palette', 'members' => $designBy, 'highlight' => false],
    ['title' => 'Documentation By', 'icon' => 'menu_book', 'members' => $documentationBy, 'highlight' => false],
    ['title' => 'Tested By', 'icon' => 'verified', 'members' => $testedBy, 'highlight' => false],
];

include __DIR__ . '/components/header.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <style>
        /* Minimal component helpers for contributor avatars & GitHub link using existing theme variables */
        .contrib-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 1rem;
        }

        .contrib-card.is-core {
            border-color: var(--primary, #38BDF8);
        }

        .contrib-header {
            display: flex;
            align-items: center;
            gap: 14px;
            text-align: left;
        }

        .contrib-avatar {
            position: relative;
            width: 54px;
            height: 54px;
            flex-shrink: 0;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid var(--border, rgba(148, 163, 184, 0.25));
        }

        .contrib-avatar img {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .contrib-fallback,
        .dev-fallback {
            position: absolute;
            inset: 0;
            z-index: 1;
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 800;
        }

        .contrib-meta h3 {
            margin: 0;
            font-size: 1.05rem;
        }

        .contrib-meta p {
            margin: 2px 0 0;
            font-size: 0.82rem;
            opacity: 0.8;
        }

        .contrib-github-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 9px 14px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            color: inherit;
            border: 1px solid var(--border, rgba(148, 163, 184, 0.25));
            background: rgba(148, 163, 184, 0.08);
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .contrib-github-btn:hover {
            background: rgba(148, 163, 184, 0.18);
            transform: translateY(-1px);
        }

        .contrib-group-heading {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.15rem;
            font-weight: 700;
            margin: 2.25rem 0 1rem;
        }

        .contrib-group-heading .material-symbols-outlined {
            color: var(--primary, #38BDF8);
        }
    </style>
</head>

<body class="landing-body">
    <!-- Navigation Bar (Reusing existing landing-header & landing-nav) -->
    <header class="landing-header">
        <nav class="landing-nav">
            <a href="index.php" class="topbar-brand student-brand" aria-label="Examify Home">
                <img src="assets/images/examify_logo.png" alt="" class="auth-logo">
                <span class="nav-logo brand-wordmark"> xamify</span>
            </a>

            <div class="nav-links">
                <span class="nav-item active">CONTRIBUTORS</span>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="index.php" class="btn btn-hero-secondary" style="padding: 8px 14px; font-size: 0.85rem;">
                    <span class="material-symbols-outlined btn-icon">home</span>
                    <span>Home</span>
                </a>
                <button type="button" onclick="toggleExamifyTheme(event)" class="icon-btn no-print"
                    aria-label="Switch theme" title="Switch theme">
                    <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
                </button>
            </div>
        </nav>
    </header>

    <main class="landing-content">
        <section class="section tech-overview" style="padding-top: 2.5rem;">
            <div class="section-badge">Development Team</div>
            <h1 class="section-title">Contributors</h1>
            <p class="section-subtitle">
                The developers, designers, documentation writers, and QA engineers behind Examify at Bengal Institute of
                Science &amp; Technology.
            </p>

            <?php foreach ($sections as $section): ?>
                <h2 class="contrib-group-heading">
                    <span class="material-symbols-outlined"><?= e($section['icon']) ?></span>
                    <span><?= e($section['title']) ?></span>
                </h2>

                <div class="tech-cards-grid">
                    <?php foreach ($section['members'] as $dev): ?>
                        <?php
                        $localDevAvatar = 'assets/images/devs/' . $dev['username'] . '.png';
                        $devAvatarSrc = file_exists(__DIR__ . '/' . $localDevAvatar)
                            ? $localDevAvatar
                            : 'https://github.com/' . urlencode((string) $dev['username']) . '.png?size=96';
                        ?>
                        <article class="tech-card contrib-card <?= $section['highlight'] ? 'is-core' : '' ?>">
                            <div class="contrib-header">
                                <div class="contrib-avatar">
                                    <div class="contrib-fallback dev-fallback" style="background: <?= e($dev['color']) ?>;">
                                        <?= e($dev['initials']) ?>
                                    </div>
                                    <img src="<?= e($devAvatarSrc) ?>" alt="<?= e($dev['name']) ?>" loading="lazy"
                                        onerror="this.style.display='none';">
                                </div>
                                <div class="contrib-meta">
                                    <h3><?= e($dev['name']) ?></h3>
                                    <p><?= e($dev['role']) ?></p>
                                </div>
                            </div>

                            <a href="<?= e($dev['github']) ?>" target="_blank" rel="noopener noreferrer"
                                class="contrib-github-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path
                                        d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z" />
                                </svg>
                                <span>@<?= e($dev['username']) ?></span>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </section>
    </main>

    <!-- Footer (Reusing existing landing-footer) -->
    <footer class="landing-footer">
        <div class="footer-top">
            <div class="footer-brand">
                <div class="footer-brand-title">
                    <img src="assets/images/examify_logo.png" alt="Examify" class="footer-logo">
                    <span>Examify</span>
                </div>
                <p class="footer-affiliation">Bengal Institute of Science &amp; Technology (BIST)</p>
            </div>

            <div class="footer-links">
                <a href="mailto:itdesk@bistpurulia.org" class="footer-link">Contact IT Desk</a>
                <a href="docs/user/user-doc.php" class="footer-link">System Documentation</a>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Examify. All rights reserved.</p>
            <p>Bengal Institute of Science &amp; Technology (BIST) • TATA ROAD, PURULIA • P.O -Dulmi-Nadiha</p>
            <p class="footer-credits">
                Built with ❤️ by <a href="developers.php" class="footer-credit-link">Contributors</a>
            </p>
        </div>
    </footer>

    <script>
        let isThemeTransitioning = false;

        function syncThemeUI(animate = false) {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const icon = document.getElementById('theme-icon');
            if (!icon) return;

            if (animate) {
                icon.classList.remove('theme-icon-spin');
                void icon.offsetWidth;
                icon.classList.add('theme-icon-spin');
            }
            icon.textContent = isDark ? 'light_mode' : 'dark_mode';
        }

        function toggleExamifyTheme(event) {
            if (isThemeTransitioning) return;

            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            const nextTheme = isDark ? 'light' : 'dark';

            const applyTheme = () => {
                if (nextTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                }
                try {
                    localStorage.setItem('examify_theme', nextTheme);
                } catch (e) { }
                syncThemeUI(true);
            };

            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (document.startViewTransition && !prefersReducedMotion) {
                isThemeTransitioning = true;
                const btn = event?.currentTarget || document.getElementById('theme-icon');
                const rect = btn ? btn.getBoundingClientRect() : null;
                const x = rect ? rect.left + rect.width / 2 : window.innerWidth - 40;
                const y = rect ? rect.top + rect.height / 2 : 30;
                const endRadius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));

                const transition = document.startViewTransition(() => applyTheme());
                transition.ready.then(() => {
                    document.documentElement.animate(
                        { clipPath: [`circle(0px at ${x}px ${y}px)`, `circle(${endRadius}px at ${x}px ${y}px)`] },
                        { duration: 550, easing: 'cubic-bezier(0.22, 1, 0.36, 1)', pseudoElement: '::view-transition-new(root)' }
                    );
                });
                transition.finished.finally(() => { isThemeTransitioning = false; });
                return;
            }

            document.documentElement.classList.add('theme-transitioning');
            applyTheme();
            setTimeout(() => document.documentElement.classList.remove('theme-transitioning'), 350);
        }

        document.addEventListener('DOMContentLoaded', () => syncThemeUI(false));
    </script>
</body>

</html>