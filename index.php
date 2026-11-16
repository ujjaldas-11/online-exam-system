<?php
// Landing page — public entry point, no session-gated content here
require_once __DIR__ . '/utils/env.php';
require_once __DIR__ . '/utils/auth.php';
require_once __DIR__ . '/config/database.php';

$assetVersion = asset_version();
$isInitialized = isset($pdo) ? is_system_initialized($pdo) : true;

include __DIR__ . '/components/header.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">

<body class="landing-body">
    <?php if (!$isInitialized): ?>
        <div class="setup-banner">
            <span class="setup-badge">Setup</span>
            System requires first-time initialization.
            <a href="admin/setup.php">Configure Superadmin Password &rarr;</a>
        </div>
    <?php endif; ?>

    <!-- Navigation Bar -->
    <header class="landing-header">
        <nav class="landing-nav">
            <a href="index.php" class="topbar-brand student-brand" aria-label="Examify dashboard">
                <img src="assets/images/examify_logo.png" alt="" class="auth-logo">
                <span class="nav-logo brand-wordmark"> xamify</span>
            </a>

            <div class="nav-links">
                <a href="#portals" class="nav-item active">Portals</a>
                <a href="#features" class="nav-item">Features</a>
                <a href="#technology" class="nav-item">Advantages</a>
                <a href="#contributors" class="nav-item">Contributors</a>
            </div>

            <button type="button" onclick="toggleExamifyTheme(event)" class="icon-btn no-print"
                aria-label="Switch theme" title="Switch theme">
                <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
            </button>
        </nav>
    </header>

    <main class="landing-content">
        <!-- Hero Section: Logo Left | Text & Portals Right -->
        <section class="section hero-container" id="portals">
            <div class="hero-left">
                <div class="hero-logo-box">
                    <img src="assets/images/examify_logo.png" alt="Examify Logo" class="hero-brand-img">
                </div>
            </div>

            <div class="hero-right">
                <a href="https://bistpurulia.org/" class="institution-badge" target="_blank" rel="noopener noreferrer"
                    title="Visit Bengal Institute of Science &amp; Technology">
                    <img src="assets/images/college_logo_.png" alt="Bengal Institute of Science &amp; Technology logo"
                        class="college-badge-logo">
                    <span>Bengal Institute of Science &amp; Technology</span>
                </a>

                <h1 class="hero-heading">
                    Examify — <span class="text-accent">Secure</span> Institutional Examination Platform
                </h1>

                <p class="hero-subtext">
                    A streamlined, synchronized, and LAN-ready testing environment for students and faculty.
                </p>

                <div class="hero-cta-group">
                    <div class="cta-primary-row">
                        <a href="student/login.php" class="btn btn-hero-primary">
                            <span class="material-symbols-outlined btn-icon">person</span>
                            <span>Student Portal Login</span>
                        </a>
                        <a href="admin/admin-login.php" class="btn btn-hero-secondary">
                            <span class="material-symbols-outlined btn-icon">co_present</span>
                            <span>Faculty/Teacher Login</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4 Core Value Highlights (Single Row — Zero Word Repetition) -->
        <section class="features-section">
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">quiz</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Question Repository</p>
                    <p class="feature-desc">Curate and organize subject banks effortlessly.</p>
                </div>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">timer</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Live Proctoring</p>
                    <p class="feature-desc">Monitor active candidates within scheduled windows.</p>
                </div>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">shield</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Session Lockdown</p>
                    <p class="feature-desc">Enforce fullscreen focus and detect tab exits.</p>
                </div>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">analytics</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Instant Scoring</p>
                    <p class="feature-desc">Deliver automated marks right upon submission.</p>
                </div>
            </div>
        </section>

        <!-- Role Portals / Expanded Features Section -->
        <section class="section features-overview" id="features">
            <div class="section-badge">Role Capabilities</div>
            <h2 class="section-title">Built for every role in the institution</h2>
            <p class="section-subtitle">
                Tailored workflows designed around how learners, educators, and coordinators operate across each
                academic term.
            </p>

            <div class="role-cards-grid">
                <!-- Students Card -->
                <div class="role-card">
                    <div class="role-card-header">
                        <span class="material-symbols-outlined role-icon">groups</span>
                        <h3>For Students</h3>
                    </div>
                    <div class="role-features-list">
                        <div class="feature-item">
                            <h4>Batch-Specific Feeds</h4>
                            <p>Upcoming tests route automatically by department and semester so candidates only see
                                eligible papers.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Server-Synced Clocks</h4>
                            <p>Centralized countdowns keep every lab terminal fair and immune to local device
                                manipulation.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Crash-Safe Auto-Save</h4>
                            <p>Selected answers persist continuously, safeguarding progress against accidental browser
                                refreshes.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Visual Score Trends</h4>
                            <p>Review completed attempts, itemized solutions, and longitudinal growth charts anytime.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Teachers Card -->
                <div class="role-card">
                    <div class="role-card-header">
                        <span class="material-symbols-outlined role-icon">history_edu</span>
                        <h3>For Teachers</h3>
                    </div>
                    <div class="role-features-list">
                        <div class="feature-item">
                            <h4>Bulk JSON Imports</h4>
                            <p>Upload complete problem sets in seconds without tedious manual form entry.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Randomized Paper Shuffling</h4>
                            <p>Scramble item and option order per candidate to eliminate side-by-side copying.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Zero-Wait Evaluation</h4>
                            <p>Objective responses grade automatically at submission, saving hours of red-pen checking.
                            </p>
                        </div>
                        <div class="feature-item">
                            <h4>Difficulty &amp; Accuracy Insights</h4>
                            <p>Spot high-miss questions and evaluate cohort comprehension at a glance.</p>
                        </div>
                    </div>
                </div>

                <!-- Administrators Card -->
                <div class="role-card">
                    <div class="role-card-header">
                        <span class="material-symbols-outlined role-icon">shield_person</span>
                        <h3>For Administrators</h3>
                    </div>
                    <div class="role-features-list">
                        <div class="feature-item">
                            <h4>Immutable Activity Logs</h4>
                            <p>Every system change is recorded with timestamps to maintain institutional accountability.
                            </p>
                        </div>
                        <div class="feature-item">
                            <h4>Granular Eligibility Controls</h4>
                            <p>Grant, pause, or restrict access across cohorts, streams, or individual roll numbers.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Paperless Archiving</h4>
                            <p>Replace printed booklets and physical storage rooms with searchable digital records.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Role-Isolated Security</h4>
                            <p>Separate authentication boundaries protect sensitive administrative endpoints from
                                unauthorized reach.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Institutional Advantages & Architecture Section -->
        <section class="section tech-overview" id="technology">
            <div class="section-badge">Institutional Advantages</div>
            <h2 class="section-title">Engineered for real campus conditions</h2>
            <p class="section-subtitle">
                Fast, self-contained, and cost-effective—built to outperform legacy paper workflows and sluggish cloud
                tools.
            </p>

            <div class="tech-cards-grid">
                <div class="tech-card">
                    <div class="tech-icon-wrapper">
                        <span class="material-symbols-outlined tech-icon">wifi_off</span>
                    </div>
                    <h3>Offline LAN Operation</h3>
                    <p>Runs reliably across isolated local networks with zero dependence on external CDNs or internet
                        uptime.</p>
                </div>

                <div class="tech-card">
                    <div class="tech-icon-wrapper">
                        <span class="material-symbols-outlined tech-icon">bolt</span>
                    </div>
                    <h3>Native PHP 8+ Speed</h3>
                    <p>Tuned MySQL queries deliver sub-second page loads even when an entire computer lab logs in
                        simultaneously.</p>
                </div>

                <div class="tech-card">
                    <div class="tech-icon-wrapper">
                        <span class="material-symbols-outlined tech-icon">devices</span>
                    </div>
                    <h3>Zero-Install Lab Ready</h3>
                    <p>Works out of the box on existing desktop browsers without extra plugins or heavy client software.
                    </p>
                </div>

                <div class="tech-card">
                    <div class="tech-icon-wrapper">
                        <span class="material-symbols-outlined tech-icon">download</span>
                    </div>
                    <h3>One-Click Result Exports</h3>
                    <p>Generate clean, audit-ready mark sheets for department heads and university reporting in seconds.
                    </p>
                </div>

                <div class="tech-card">
                    <div class="tech-icon-wrapper">
                        <span class="material-symbols-outlined tech-icon">savings</span>
                    </div>
                    <h3>80% Less Admin Overhead</h3>
                    <p>Cut printing expenses, seating logistics, and manual grade tabulation across every examination
                        cycle.</p>
                </div>

                <div class="tech-card">
                    <div class="tech-icon-wrapper">
                        <span class="material-symbols-outlined tech-icon">account_tree</span>
                    </div>
                    <h3>Multi-Year Data Retention</h3>
                    <p>Structured relational schemas preserve historical archives smoothly without sluggishness over
                        time.</p>
                </div>
            </div>
        </section>

        <!-- Contributors Spotlight Section -->
        <section class="section tech-overview" id="contributors">
            <div class="section-badge">Development Team</div>
            <h2 class="section-title">Crafted by BIST Innovators</h2>
            <p class="section-subtitle">
                Designed, architected, and maintained by the student &amp; faculty engineering group at Bengal Institute
                of Science &amp; Technology.
            </p>
            <div style="display: flex; justify-content: center; margin-top: 1.5rem;">
                <a href="developers.php" class="btn btn-hero-secondary">
                    <span class="material-symbols-outlined btn-icon">code</span>
                    <span>Meet the Contributors</span>
                </a>
            </div>
        </section>
    </main>

    <!-- Footer -->
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
                <a href="developers.php" class="footer-link" id="contributor"><b>Meet the Contributors</b></a>
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
</body>

<script>
    /* ---------- Theme ---------- */
    function syncThemeUI(animate = false) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const icon = document.getElementById('theme-icon');
        if (!icon) return;

        if (animate) {
            icon.classList.remove('theme-icon-spin');
            void icon.offsetWidth; // Force reflow to restart animation
            icon.classList.add('theme-icon-spin');
        }

        icon.textContent = isDark ? 'light_mode' : 'dark_mode';
    }

    function toggleExamifyTheme(event) {
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

        // Circular Ripple Animation (View Transitions API)
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (document.startViewTransition && !prefersReducedMotion) {
            const iconEl = document.getElementById('theme-icon');
            const rect = iconEl ? iconEl.getBoundingClientRect() : null;

            // Automatically finds the button coordinates even if event wasn't passed
            const x = event?.clientX ?? (rect ? rect.left + rect.width / 2 : window.innerWidth - 40);
            const y = event?.clientY ?? (rect ? rect.top + rect.height / 2 : 30);

            const endRadius = Math.hypot(
                Math.max(x, window.innerWidth - x),
                Math.max(y, window.innerHeight - y)
            );

            const transition = document.startViewTransition(() => {
                applyTheme();
            });

            transition.ready.then(() => {
                document.documentElement.animate(
                    {
                        clipPath: [
                            `circle(0px at ${x}px ${y}px)`,
                            `circle(${endRadius}px at ${x}px ${y}px)`
                        ]
                    },
                    {
                        duration: 500,
                        easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                        pseudoElement: '::view-transition-new(root)'
                    }
                );
            });
            return;
        }

        // Fallback smooth color transition for older browsers
        document.documentElement.classList.add('theme-transitioning');
        applyTheme();
        setTimeout(() => {
            document.documentElement.classList.remove('theme-transitioning');
        }, 350);
    }

    /* ---------- Lightweight Scroll-Spy ---------- */
    function initScrollSpy() {
        const navLinks = Array.from(document.querySelectorAll('.nav-links a.nav-item[href^="#"]'));
        const sections = navLinks
            .map(link => document.querySelector(link.getAttribute('href')))
            .filter(Boolean);

        if (!navLinks.length || !sections.length) return;

        const updateActiveNav = () => {
            const scrollPosition = window.scrollY + 140; // Offset for sticky header
            let currentId = sections[0].id;

            for (const section of sections) {
                if (scrollPosition >= section.offsetTop) {
                    currentId = section.id;
                }
            }

            navLinks.forEach(link => {
                const isMatch = link.getAttribute('href') === `#${currentId}`;
                link.classList.toggle('active', isMatch);
            });
        };

        window.addEventListener('scroll', updateActiveNav, { passive: true });
        updateActiveNav();
    }

    document.addEventListener('DOMContentLoaded', () => {
        syncThemeUI(false);
        initScrollSpy();
    });
</script>

</html>