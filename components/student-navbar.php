<?php

$current_page = basename($_SERVER['PHP_SELF']);

$student_nav = [
    'dashboard.php' => ['label' => 'Dashboard',   'icon' => 'space_dashboard'],
    'profile.php'   => ['label' => 'My Profile',  'icon' => 'person'],
];

$student_name = $_SESSION['student_name'] ?? 'Student';
$avatar_char = mb_strtoupper(mb_substr(trim($student_name), 0, 1, 'UTF-8')) ?: 'S';

$student_child_routes = [
    'edit-profile.php' => 'profile.php',
    'result.php'       => 'dashboard.php',
    'review-exam.php'  => 'dashboard.php',
];
$effective_student_page = $student_child_routes[$current_page] ?? $current_page;
?>

<nav class="student-navbar">
    <div class="student-nav-inner">
        <a href="dashboard.php" class="student-brand">
            <div class="student-greeting-mobile-hidden">
                <div class="avatar-badge"><?= $avatar_char ?></div>
                <span class="student-greeting">Hi, <?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </a>
        <button class="menu-btn" id="menuBtn" aria-label="Toggle navigation">
            <span class="material-symbols-outlined" id="menuIcon">menu</span>
        </button>

        <div class="student-nav-links" id="navLinks">
            <div class="drawer-profile">
                <div class="avatar-badge lg"><?= $avatar_char ?></div>
                <span class="student-greeting">Hi, <?= htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <?php foreach ($student_nav as $page => $meta): ?>
                <a href="<?= $page ?>" class="<?= $effective_student_page === $page ? 'active' : '' ?>">
                    <span class="material-symbols-outlined"><?= $meta['icon'] ?></span>
                    <span><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
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
    const menuBtn = document.getElementById('menuBtn');
    const menuIcon = document.getElementById('menuIcon');
    const navLinks = document.getElementById('navLinks');
    const navOverlay = document.getElementById('navOverlay');

    if (!menuBtn || !navLinks || !navOverlay) return;

    const toggleMenu = () => {
        const isOpen = navLinks.classList.toggle('show');
        navOverlay.classList.toggle('show');
        menuIcon.textContent = isOpen ? 'close' : 'menu';
        document.body.style.overflow = isOpen ? 'hidden' : '';
    };

    menuBtn.addEventListener('click', toggleMenu);
    navOverlay.addEventListener('click', toggleMenu);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && navLinks.classList.contains('show')) {
            toggleMenu();
        }
    });
})();
</script>
