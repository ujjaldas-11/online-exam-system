<?php


$current_page = basename($_SERVER['PHP_SELF']);

$student_nav = [
    'dashboard.php' => ['label' => 'Dashboard',   'icon' => 'space_dashboard'],
    'profile.php'   => ['label' => 'My Profile',  'icon' => 'person'],
];

$student_name = $_SESSION['student_name'] ?? 'Student';
?>

<nav class="student-navbar">
    <div class="student-nav-inner">
        <a href="dashboard.php" class="student-brand">

        <div class="student-greeting-mobile-hidden">
            <div class="avatar-badge"><?= strtoupper(substr($student_name, 0, 1)) ?></div>
            <span class="student-greeting">Hi, <?= htmlspecialchars($student_name) ?></span>
        </div>
        </a>
        <button class="menu-btn" id="menuBtn" aria-label="Toggle navigation">
            <span class="material-symbols-outlined" id="menuIcon">menu</span>
        </button>

        <div class="student-nav-links" id="navLinks">
            <div class="drawer-profile">
                <div class="avatar-badge lg"><?= strtoupper(substr($student_name, 0, 1)) ?></div>
                <span class="student-greeting">Hi, <?= htmlspecialchars($student_name) ?></span>
            </div>

            <?php foreach ($student_nav as $page => $meta): ?>
                <a href="<?= $page ?>" class="<?= $current_page === $page ? 'active' : '' ?>">
                    <span class="material-symbols-outlined"><?= $meta['icon'] ?></span>
                    <span><?= htmlspecialchars($meta['label']) ?></span>
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

<link rel="stylesheet" href="../assets/css/student-navbar.css">
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
