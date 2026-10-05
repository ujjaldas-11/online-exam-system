<?php
$current_page = basename($_SERVER['PHP_SELF']);

$student_nav = [
    'dashboard.php'    => ['label' => 'Dashboard',    'icon' => 'space_dashboard'],
    'profile.php'      => ['label' => 'My Profile',   'icon' => 'person'],
    'exam-history.php' => ['label' => 'Exam History', 'icon' => 'history_edu'],
];

// --- AVATAR LOGIC ---
$student_name   = $_SESSION['student_name'] ?? 'Student';
$student_gender = strtolower($_SESSION['gender'] ?? 'male');

$nav_avatar_url = ($student_gender === 'female')
    ? '../assets/avatars/female.jpg'
    : '../assets/avatars/male.jpg';

$student_child_routes = [
    'edit-profile.php' => 'profile.php',
    'result.php'       => 'dashboard.php',
    'review-exam.php'  => 'dashboard.php',
];
$effective_student_page = $student_child_routes[$current_page] ?? $current_page;

$esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>

<style>
    /* Layout-only fixes. No colors changed: your existing navbar styles still apply. */
    .nav-profile-pic {
        width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
        border: 2px solid var(--color-border, #e5e7eb);
        background-color: #fff;
    }
    .nav-profile-pic.lg { width: 64px; height: 64px; margin-bottom: 8px; }

    .student-navbar .student-greeting-mobile-hidden { display: flex; align-items: center; gap: 10px; }
    .student-navbar .student-greeting { white-space: nowrap; }

    /* Drawer avatar block: desktop hidden (this was the duplicate avatar), mobile shown */
    .student-navbar .drawer-profile { display: none; }

    @media (max-width: 768px) {
        .student-navbar .student-greeting-mobile-hidden { display: none; }
        .student-navbar .drawer-profile {
            display: flex; flex-direction: column; align-items: center; padding: 20px 0;
        }
        .student-navbar .drawer-profile .student-greeting { margin-left: 0; }
    }
</style>

<nav class="student-navbar" aria-label="Main navigation">
    <div class="student-nav-inner">
        <a href="dashboard.php" class="student-brand">
            <div class="student-greeting-mobile-hidden">
                <img src="<?= $esc($nav_avatar_url) ?>" alt="Profile" class="nav-profile-pic">
                <span class="student-greeting">Hi, <?= $esc($student_name) ?></span>
            </div>
        </a>

        <button class="menu-btn" id="menuBtn" type="button"
                aria-label="Toggle navigation" aria-expanded="false" aria-controls="navLinks">
            <span class="material-symbols-outlined" id="menuIcon">menu</span>
        </button>

        <div class="student-nav-links" id="navLinks">
            <div class="drawer-profile">
                <img src="<?= $esc($nav_avatar_url) ?>" alt="Profile" class="nav-profile-pic lg">
                <span class="student-greeting">Hi, <?= $esc($student_name) ?></span>

            </div>
            <p><?= htmlspecialchars($student_gender)?></p>

            <?php foreach ($student_nav as $page => $meta): ?>
                <a href="<?= $esc($page) ?>"
                   class="<?= $effective_student_page === $page ? 'active' : '' ?>"
                   <?= $effective_student_page === $page ? 'aria-current="page"' : '' ?>>
                    <span class="material-symbols-outlined"><?= $esc($meta['icon']) ?></span>
                    <span><?= $esc($meta['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <a href="logout.php" class="nav-logout">
                <span class="material-symbols-outlined">logout</span>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="student-nav-overlay" id="navOverlay"></div>
</nav>

<script defer>
(function () {
    const menuBtn    = document.getElementById('menuBtn');
    const menuIcon   = document.getElementById('menuIcon');
    const navLinks   = document.getElementById('navLinks');
    const navOverlay = document.getElementById('navOverlay');

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
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && navLinks.classList.contains('show')) setOpen(false);
    });
})();
</script>
