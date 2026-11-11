<?php
$current_page = basename($_SERVER['PHP_SELF']);

$student_nav = [
    'dashboard.php'             => ['label' => 'Home', 'icon' => 'home'],
    // 'exam-history.php'       => ['label' => 'Exam History', 'icon' => 'history_edu'],
    '../docs/user/user-doc.php' => ['label' => 'Docs', 'icon' => 'menu_book'],
];

// --- AVATAR LOGIC ---
$student_name   = $_SESSION['student_name'] ?? 'Student';
$student_gender = strtolower($_SESSION['gender'] ?? 'others');

if ($student_gender === 'female') {
    $nav_avatar_url = '../assets/avatars/female.jpg';
} elseif ($student_gender === 'male') {
    $nav_avatar_url = '../assets/avatars/male.jpg';
} else {
    $nav_avatar_url = '../assets/avatars/all-gender.webp';
}

$student_child_routes = [
    'edit-profile.php' => 'profile.php',
    'result.php'       => 'dashboard.php',
    'review-exam.php'  => 'dashboard.php',
];
$effective_student_page = $student_child_routes[$current_page] ?? $current_page;

$collegeName = 'Bengal Institute of Science & Technology';
$esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>

<nav class="student-navbar" aria-label="Main navigation">
    <div class="student-nav-inner">

        <a href="dashboard.php" class="student-brand" aria-label="Examify home">
            <img src="../assets/images/examify_logo.png" alt="" class="auth-logo" loading="lazy">
            <span class="nav-logo brand-wordmark">xamify</span>
        </a>

        <button class="menu-btn" id="menuBtn" type="button"
                aria-label="Toggle navigation" aria-expanded="false" aria-controls="navLinks">
            <span class="material-symbols-outlined" id="menuIcon">menu</span>
        </button>

        <div class="student-nav-links" id="navLinks">

            <!-- Mobile drawer header -->
            <div class="drawer-head">
                <a href="profile.php" class="drawer-profile">
                    <img src="<?= $esc($nav_avatar_url) ?>" alt="" class="nav-profile-pic lg" loading="lazy">
                    <span class="student-greeting">Hi, <?= $esc($student_name) ?></span>
                </a>
                <button type="button" class="drawer-close" id="drawerClose" aria-label="Close menu">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <?php foreach ($student_nav as $page => $meta):
                $isActive = $effective_student_page === $page; ?>
                <a href="<?= $esc($page) ?>"
                   class="nav-link <?= $isActive ? 'active' : '' ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <span class="material-symbols-outlined" aria-hidden="true"><?= $esc($meta['icon']) ?></span>
                    <span><?= $esc($meta['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <!-- Theme toggle: icon button on desktop, full row (with label) inside the mobile drawer -->
            <button type="button" class="nav-theme-btn no-print" onclick="toggleExamifyTheme()"
                    aria-label="Switch theme" title="Switch theme">
                <span class="material-symbols-outlined" id="theme-icon" aria-hidden="true">dark_mode</span>
                <span class="nav-theme-label" id="theme-label">Dark mode</span>
            </button>

            <span class="nav-sep" aria-hidden="true"></span>

            <!-- Mobile-only logout (the profile widget is hidden on mobile) -->
            <a href="logout.php" class="nav-logout">
                <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                <span>Logout</span>
            </a>

            <!-- Desktop profile widget -->
            <div class="profile-widget">
                <button class="profile-trigger" id="profileTrigger" type="button"
                        aria-haspopup="true" aria-expanded="false" aria-controls="profileDropdown">
                    <img src="<?= $esc($nav_avatar_url) ?>" alt="" class="nav-profile-pic" loading="lazy">
                    <span class="profile-text">
                        <span class="admin-name"><?= $esc($student_name) ?></span>
                        <span class="admin-role">Student</span>
                    </span>
                    <span class="material-symbols-outlined chevron-icon" aria-hidden="true">expand_more</span>
                </button>

                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <img src="<?= $esc($nav_avatar_url) ?>" alt="" class="nav-profile-pic xl" loading="lazy">
                        <div class="dropdown-identity">
                            <p class="dropdown-name"><?= $esc($student_name) ?></p>
                            <span class="role-badge">Student</span>
                            <p class="dropdown-college"><?= $esc($collegeName) ?></p>
                        </div>
                    </div>

                    <div class="dropdown-actions">
                        <a href="profile.php" class="dropdown-item">
                            <span class="material-symbols-outlined" aria-hidden="true">person</span>
                            <span class="dropdown-item-label">Profile</span>
                            <span class="material-symbols-outlined dropdown-item-arrow" aria-hidden="true">chevron_right</span>
                        </a>
                        <a href="logout.php" class="dropdown-item logout-btn">
                            <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                            <span class="dropdown-item-label">Logout</span>
                            <span class="material-symbols-outlined dropdown-item-arrow" aria-hidden="true">chevron_right</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="student-nav-overlay" id="navOverlay"></div>
</nav>

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
        } catch (e) {}

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

document.addEventListener('DOMContentLoaded', () => syncThemeUI(false));

/* ---------- Mobile drawer ---------- */
(function () {
    const menuBtn    = document.getElementById('menuBtn');
    const menuIcon   = document.getElementById('menuIcon');
    const navLinks   = document.getElementById('navLinks');
    const navOverlay = document.getElementById('navOverlay');
    const drawerClose = document.getElementById('drawerClose');
    if (!menuBtn || !navLinks || !navOverlay) return;

    const setOpen = (open) => {
        navLinks.classList.toggle('show', open);
        navOverlay.classList.toggle('show', open);
        menuIcon.textContent = open ? 'close' : 'menu';
        menuBtn.setAttribute('aria-expanded', String(open));
        document.body.style.overflow = open ? 'hidden' : '';
    };

    menuBtn.addEventListener('click', () => setOpen(!navLinks.classList.contains('show')));
    navOverlay.addEventListener('click', () => setOpen(false));
    drawerClose?.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && navLinks.classList.contains('show')) setOpen(false);
    });
})();

/* ---------- Profile dropdown ---------- */
(function () {
    const trigger  = document.getElementById('profileTrigger');
    const dropdown = document.getElementById('profileDropdown');
    if (!trigger || !dropdown) return;

    const setOpen = (open) => {
        dropdown.classList.toggle('open', open);
        trigger.setAttribute('aria-expanded', String(open));
    };

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        setOpen(!dropdown.classList.contains('open'));
    });
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && !trigger.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { setOpen(false); }
    });
})();
</script>
