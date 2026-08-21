<?php
$current_page = basename($_SERVER['PHP_SELF']);

$admin_pages = ['admin-dashboard.php','manage-subjects.php','manage-questions.php','control-exams.php','results.php','manage-requests.php','proctor-exam.php','import-students.php'];
$student_pages = ['dashboard.php','profile.php','edit-profile.php'];

$is_admin = in_array($current_page, $admin_pages);
$is_student = in_array($current_page, $student_pages);
?>

<nav class="navbar">
    <div class="nav-right">
        <?php if ($is_admin): ?>

            <div class="nav-inner">
                <h1>
                    <span style="font-size: 1.5rem;"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>!</span>
                </h1>
                <button class="menu-btn" id="sidebarToggle" aria-label="Toggle Navigation">
                    <span class="material-symbols-outlined">menu</span>
                </button>
            </div>

            <aside class="admin-sidebar" id="adminSidebar">
                <div class="sidebar-header">
                    <div class="sidebar-brand">
                        <span class="material-symbols-outlined">shield_person</span>
                        <span>Admin panel</span>
                        <button class="sidebar-close" id="sidebarClose" aria-label="Close menu">
                           x
                        </button>
                    </div>
                    
                </div>

                <nav class="sidebar-links">
                    <a href="admin-dashboard.php" class="<?= $current_page === 'admin-dashboard.php' ? 'active' : '' ?>">Dashboard</a>
                    <a href="manage-subjects.php" class="<?= $current_page === 'manage-subjects.php' ? 'active' : '' ?>">Subjects</a>
                    <a href="manage-questions.php" class="<?= $current_page === 'manage-questions.php' ? 'active' : '' ?>">Questions</a>
                    <a href="control-exams.php" class="<?= $current_page === 'control-exams.php' ? 'active' : '' ?>">Exams</a>
                    <a href="results.php" class="<?= $current_page === 'results.php' ? 'active' : '' ?>">Results</a>
                    <a href="manage-requests.php" class="<?= $current_page === 'manage-requests.php' ? 'active' : '' ?>">Requests</a>
                    <a href="proctor-exam.php" class="<?= $current_page === 'proctor-exam.php' ? 'active' : '' ?>">Proctor</a>
                    <a href="import-students.php" class="<?= $current_page === 'import-students.php' ? 'active' : '' ?>">Import</a>
                </nav>

                <a href="admin-logout.php" class="sidebar-logout">Logout</a>
            </aside>

            <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <?php elseif ($is_student): ?>

            <div class="nav-inner">
                <span class="nav-greeting">
                    Hi, <?= htmlspecialchars($_SESSION['student_name'] ?? 'Student') ?>!
                </span>

                <button class="menu-btn" id="menuBtn" aria-label="Toggle Navigation">
                    <span class="material-symbols-outlined" id="menuIcon">menu</span>
                </button>

                <div class="nav-links" id="navLinks">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="profile.php">My Profile</a>
                    <a href="logout.php" class="nav-logout">Logout</a>
                </div>
            </div>

            <div class="nav-overlay" id="navOverlay"></div>

        <?php endif ?>
    </div>
</nav>

<script>
// Admin sidebar open/close
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarClose  = document.getElementById('sidebarClose');
const adminSidebar  = document.getElementById('adminSidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (sidebarToggle && adminSidebar && sidebarOverlay) {
    const openSidebar  = () => { adminSidebar.classList.add('show'); sidebarOverlay.classList.add('show'); };
    const closeSidebar = () => { adminSidebar.classList.remove('show'); sidebarOverlay.classList.remove('show'); };

    sidebarToggle.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay.addEventListener('click', closeSidebar);
}

// Student mobile drawer open/close
const menuBtn   = document.getElementById('menuBtn');
const menuIcon  = document.getElementById('menuIcon');
const navLinks  = document.getElementById('navLinks');
const navOverlay = document.getElementById('navOverlay');

if (menuBtn && navLinks && navOverlay) {
    const toggleMenu = () => {
        const isOpen = navLinks.classList.toggle('show');
        navOverlay.classList.toggle('show');
        menuIcon.textContent = isOpen ? 'close' : 'menu';
        document.body.style.overflow = isOpen ? 'hidden' : '';
    };
    menuBtn.addEventListener('click', toggleMenu);
    navOverlay.addEventListener('click', toggleMenu);
}
</script>