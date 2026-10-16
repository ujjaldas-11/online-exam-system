<?php
$current_page = basename($_SERVER['PHP_SELF']);

$student_nav = [
    'dashboard.php' => ['label' => 'Home', 'icon' => 'space_dashboard'],
    // 'exam-history.php' => ['label' => 'Exam History', 'icon' => 'history_edu'],
    '../docs/user/user-doc.php' => ['label' => 'docs', 'icon' => 'docs']
];

// --- AVATAR LOGIC ---
$student_name = $_SESSION['student_name'] ?? 'Student';
$student_gender = strtolower($_SESSION['gender'] ?? 'others');


if($student_gender === 'female') {
    $nav_avatar_url = '../assets/avatars/female.jpg';
} elseif($student_gender === 'male') {
    $nav_avatar_url = '../assets/avatars/male.jpg';
} else {
    $nav_avatar_url = '../assets/avatars/all-gender.webp';
}

$student_child_routes = [
    'edit-profile.php' => 'profile.php',
    'result.php' => 'dashboard.php',
    'review-exam.php' => 'dashboard.php',
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
                <img src="../assets/images/examify_logo.png" alt="Examify Logo" class="auth-logo">
                <span class="nav-logo" style="margin-left: -20px; font-size: 1.4rem; margin-top: 7.5px;">xamify</span>
        </a>

        <button class="menu-btn" id="menuBtn" type="button"
                aria-label="Toggle navigation" aria-expanded="false" aria-controls="navLinks">
            <span class="material-symbols-outlined" id="menuIcon">menu</span>
        </button>

        <div class="student-nav-links" id="navLinks">
            <a href="profile.php" class="drawer-profile">
                <img src="<?= $esc($nav_avatar_url) ?>" alt="Profile" class="nav-profile-pic lg">
                <span class="student-greeting">Hi,<?= $esc($student_name) ?></span>
            </a>

            <?php foreach ($student_nav as $page => $meta): ?>
                <a href="<?= $esc($page) ?>"
                   class="<?= $effective_student_page === $page ? 'active' : '' ?>"
                   <?= $effective_student_page === $page ? 'aria-current="page"' : '' ?>>
                    <span class="material-symbols-outlined"><?= $esc($meta['icon']) ?></span>
                    <span><?= $esc($meta['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <div class="profile-widget">
                <button class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
                    <!-- Dynamic Topbar Avatar -->
                    <img src="<?= htmlspecialchars($esc($nav_avatar_url), ENT_QUOTES, 'UTF-8') ?>" alt="Admin Profile" class="nav-profile-pic">
                    
                    <div class="profile-text">
                        <span class="admin-name"><?= htmlspecialchars($esc($student_name), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="admin-role" style="color: #edb055;">Student</span>
                    </div>
                    <span class="material-symbols-outlined chevron-icon" aria-hidden="true">expand_more</span>
                </button>

                <!-- Profile dropdown -->

                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <!-- Dynamic Dropdown Avatar -->
                        <img src="<?= htmlspecialchars($nav_avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="Admin Profile" class="nav-profile-pic lg" style="width: 90px; height: 90px;">
                        <div>
                            <p class="dropdown-name"><?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="badge badge-active" style="background-color: #2c3343;">Student</p>
                            <p style="color: var(--color-gold); text-align: center;"> Bengal Institue of Science & Technology
                        </div>
                    </div>
                    
                    <hr class="dropdown-divider" style="margin: 0;">
                    <a href="profile.php" class="dropdown-reset-btn-item"  style="display: flex; justify-content: space-between;">
                        <div>
                            <span class="material-symbols-outlined" aria-hidden="true">person</span>
                            Profile
                        </div>
                        <span class="material-symbols-outlined">Chevron_Right</span>
                    </a>
                    <hr class="dropdown-divider" style="margin: 0;">
                    <a href="logout.php" class="logout-btn" style="display: flex; justify-content: space-between;">
                        <div>
                            <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                            Logout
                        </div>
                        <span class="material-symbols-outlined">Chevron_Right</span>
                    </a>
                </div>
            </div>
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


(function () {
    const trigger = document.getElementById('profileTrigger');
    const dropdown = document.getElementById('profileDropdown');

    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen);
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && !trigger.contains(e.target)) {
            dropdown.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });
})();


</script>
