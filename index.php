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
                <a href="#technology" class="nav-item">Technology</a>
            </div>

             <button type="button" onclick="toggleExamifyTheme()" class="icon-btn no-print" aria-label="Switch theme" title="Switch theme">
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

        <!-- Previous 4 Feature Cards (Single Row) -->
        <section class="features-section">
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">quiz</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Question Bank</p>
                    <p class="feature-desc">Organize and reuse subjects effortlessly.</p>
                </div>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">timer</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Timed &amp; Proctored</p>
                    <p class="feature-desc">Set windows and monitor live sessions.</p>
                </div>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">shield</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Cheat-Resistant</p>
                    <p class="feature-desc">Full-screen enforcement &amp; tab tracking.</p>
                </div>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <span class="material-symbols-outlined">analytics</span>
                </div>
                <div class="feature-text">
                    <p class="feature-title">Instant Results</p>
                    <p class="feature-desc">Auto-graded scores available immediately.</p>
                </div>
            </div>
        </section>

        <!-- Role Portals / Features Section -->
        <section class="section features-overview" id="features">
            <div class="section-badge">Role Portals</div>
            <h2 class="section-title">Built for every role in the institution</h2>
            <p class="section-subtitle">
                One platform, three tailored experiences — each designed around how students, teachers, and
                administrators actually work.
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
                            <h4>Department &amp; Semester Feeds</h4>
                            <p>Exams are routed automatically to the right students based on their current academic
                                standing.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Synchronized Timers</h4>
                            <p>Server-side time keeping keeps every terminal fair and prevents local manipulation.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Organized History</h4>
                            <p>Review completed papers, marks, and historical score trend charts anytime.</p>
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
                            <h4>JSON Question Banks</h4>
                            <p>Import massive question sets quickly without manual data entry.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Automated Grading</h4>
                            <p>Instant objective evaluation and result publishing on completion.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Performance Dashboards</h4>
                            <p>Track student completion and item-wise answer accuracy rates.</p>
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
                            <h4>Advanced Audit Logging</h4>
                            <p>Every system action is logged securely to prevent unauthorized changes.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Granular Filtering</h4>
                            <p>Withhold or fine-tune access across semesters, departments, or individual student
                                registers.</p>
                        </div>
                        <div class="feature-item">
                            <h4>Secure Access</h4>
                            <p>Isolated credential authentication with protected administrative endpoints.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Under The Hood / Technology Section -->
        <section class="section tech-overview" id="technology">
            <div class="section-badge">Under the hood</div>
            <h2 class="section-title">Reliable where it matters</h2>
            <p class="section-subtitle">
                Engineered for institutional deployment, from a single lab network to years of accumulated records.
            </p>

            <div class="tech-cards-grid">
                <div class="tech-card">
                    <div class="tech-icon-wrapper">
                        <span class="material-symbols-outlined tech-icon">wifi_off</span>
                    </div>
                    <h3>Offline &amp; LAN Ready</h3>
                    <p>Runs flawlessly on isolated local networks, with zero reliance on external CDNs or internet
                        connectivity.</p>
                </div>

                <div class="tech-card">
                    <div class="tech-icon-wrapper">
                        <span class="material-symbols-outlined tech-icon">bolt</span>
                    </div>
                    <h3>High Performance</h3>
                    <p>Built natively with PHP 8+ and optimized MySQL queries for instant data loading, even under heavy
                        student traffic.</p>
                </div>

                <div class="tech-card">
                    <div class="tech-icon-wrapper">
                        <span class="material-symbols-outlined tech-icon">account_tree</span>
                    </div>
                    <h3>Scalable Architecture</h3>
                    <p>Designed to handle multi-year data accumulation without lag.</p>
                </div>
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
                <a href="developers.php" class="footer-link">Meet the Contributors</a>
                <a href="mailto:itdesk@bistpurulia.org" class="footer-link">Contact IT Desk</a>
                <a href="docs/user/user-doc.php" class="footer-link">System Documentation</a>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Examify. All rights reserved.</p>
            <p>Bengal Institute of Science & Technology (BIST) • TATA ROAD, PURULIA • P.O -Dulmi-Nadiha</p>
            <p class="footer-credits">
                Built with ❤️ by <a href="developers.php" class="footer-credit-link">Contributors</a>
            </p>
        </div>
    </footer>
</body>

<script>
    function syncThemeUI() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const icon = document.getElementById('theme-icon');
    if (icon) icon.textContent = isDark ? 'light_mode' : 'dark_mode';
}

function toggleExamifyTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        try { localStorage.setItem('examify_theme', 'light'); } catch (e) {}
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        try { localStorage.setItem('examify_theme', 'dark'); } catch (e) {}
    }
    syncThemeUI();
}
</script>
</html>