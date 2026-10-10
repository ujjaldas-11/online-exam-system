<?php
$current_page = basename($_SERVER['PHP_SELF']);

if ((!isset($pending_registration_requests_count) || !isset($pending_requests_count)) && isset($pdo)) {
    try {
        $sidebar_counts = $pdo->query("SELECT 
            (SELECT COUNT(*) FROM students WHERE status = 'pending') AS pending_students,
            (SELECT COUNT(*) FROM profile_requests WHERE status = 'pending') AS pending_requests")->fetch(PDO::FETCH_ASSOC);
        if (!isset($pending_registration_requests_count)) {
            $pending_registration_requests_count = (int) ($sidebar_counts['pending_students'] ?? 0);
        }
        if (!isset($pending_requests_count)) {
            $pending_requests_count = (int) ($sidebar_counts['pending_requests'] ?? 0);
        }
    } catch (PDOException) {
        $pending_registration_requests_count = $pending_registration_requests_count ?? 0;
        $pending_requests_count = $pending_requests_count ?? 0;
    }
}

// --- AVATAR & PROFILE DATA LOGIC ---

$admin_gender = strtolower($_SESSION['gender'] ?? 'male');
$nav_avatar_url = ($admin_gender === 'female')
    ? '../assets/avatars/admin-female.avif'
    : '../assets/avatars/admin-male.webp';

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = $_SESSION['admin_role'] ?? 'Teacher';
// Ensure these are set in your admin login script!
$adminEmail = $_SESSION['admin_email'] ?? 'admin@bist.edu'; 
$adminDept = $_SESSION['admin_dept'] ?? 'Computer Science';
$collegeName = 'Bengal Institute of Science & Technology(BIST)';
// -----------------------------------

require_once __DIR__ . '/../utils/auth.php';
$isAdminSuper = is_superadmin();

$admin_nav = [
    'admin-dashboard.php' => ['label' => 'Dashboard', 'icon' => 'space_dashboard', 'title' => 'dashboard'],
    'manage-subjects.php' => ['label' => 'Subjects', 'icon' => 'menu_book', 'title' => 'manage subjects'],
    'manage-questions.php' => ['label' => 'Questions', 'icon' => 'quiz', 'title' => 'questions'],
    'control-exams.php' => ['label' => 'Exams', 'icon' => 'fact_check', 'title' => 'exams'],
    'results.php' => ['label' => 'Results', 'icon' => 'bar_chart', 'title' => 'results'],
    'manage-students.php' => ['label' => 'Students', 'icon' => 'group', 'title' => 'Manage Students']
];

// Map secondary/child views to parent navigation item
$route_parents = [
    'manage-exam.php' => 'control-exams.php',
    'proctor-exam.php' => 'control-exams.php',
    'view-questions.php' => 'manage-questions.php',
    'edit-question.php' => 'manage-questions.php',
    'view-results.php' => 'results.php',
];
$effective_active_page = $route_parents[$current_page] ?? $current_page;

if ($isAdminSuper) {
    $admin_nav['registration-request.php'] = ['label' => 'Registration Requests', 'icon' => 'person_add', 'title' => 'registration requests'];
    $admin_nav['manage-requests.php'] = ['label' => 'update Requests', 'icon' => 'notifications', 'title' => 'profile update request'];
    $admin_nav['manage-teachers.php'] = ['label' => 'Teachers', 'icon' => 'school', 'title' => 'teachers'];
    $admin_nav['audit-logs.php'] = ['label' => 'Audit Trail', 'icon' => 'receipt_long', 'title' => 'logs'];
    $admin_nav['settings.php'] = ['label' => 'Settings & Backup', 'icon' => 'settings', 'title' => 'system settings and backup'];
} else {
    $admin_nav['audit-logs.php'] = ['label' => 'My Activity', 'icon' => 'history', 'title' => 'my history'];
}
?>


<!-- ===================== ADMIN TOPBAR ===================== -->
<header class="admin-topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <button class="icon-btn desktop-collapse-btn" id="desktopCollapseBtn" aria-label="Collapse sidebar">
                <span class="material-symbols-outlined">dock_to_right</span>
            </button>
            <button class="icon-btn mobile-menu-btn" id="sidebarToggle" aria-label="Open navigation" title="menu bar">
                <span class="material-symbols-outlined">menu</span>
            </button>
        </div>

        <div class="topbar-right">
            <?php if($isAdminSuper): ?>
                <a href="registration-request.php" class="icon-btn topbar-shortcut <?= $current_page === 'registration-request.php' ? 'active' : '' ?>" aria-label="Notifications" title="registration requests">
                    <span class="material-symbols-outlined">person_add</span>
                    <?php if (!empty($pending_registration_requests_count)): ?>
                        <span class="topbar-badge"><?= (int) $pending_registration_requests_count ?></span>
                    <?php endif; ?>
                </a>

                <a href="manage-requests.php" class="icon-btn topbar-shortcut <?= $current_page === 'manage-requests.php' ? 'active' : '' ?>" aria-label="Notifications" title="profile update requests">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if (!empty($pending_requests_count)): ?>
                        <span class="topbar-badge"><?= (int) $pending_requests_count ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <a href="../docs/user/admin-doc.php" class="icon-btn topbar-shortcut" aria-label="help file" title="Read Documentation">
                <span class="material-symbols-outlined">docs</span>
            </a>

            <div class="profile-widget">
                <button class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
                    <!-- Dynamic Topbar Avatar -->
                    <img src="<?= htmlspecialchars($nav_avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="Admin Profile" class="nav-profile-pic">
                    
                    <div class="profile-text">
                        <span class="admin-name"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="admin-role" style="color: #edb055;"><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <span class="material-symbols-outlined chevron-icon" aria-hidden="true">expand_more</span>
                </button>

                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <!-- Dynamic Dropdown Avatar -->
                        <img src="<?= htmlspecialchars($nav_avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="Admin Profile" class="nav-profile-pic lg" style="width: 90px; height: 90px;">
                        <div>
                            <p class="dropdown-name"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="badge badge-active" style="background-color: #2c3343;"><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></p>
                            <p style="color: var(--color-gold); text-align: center;"> <?= $collegeName ?></p>
                        </div>
                    </div>
                    
                    <!-- Extended Admin Details -->
                    <div class="dropdown-admin-details">
                        <p><span class="material-symbols-outlined">email</span> <?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?></p>
                        <p> <span class="material-symbols-outlined">school</span> <?= htmlspecialchars($adminDept, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    
                    <hr class="dropdown-divider" style="margin: 0;">
                    <a href="admin-reset-password.php" class="dropdown-reset-btn-item">
                        <span class="material-symbols-outlined" aria-hidden="true">lock_reset</span>
                        Reset Password
                    </a>
                    <hr class="dropdown-divider" style="margin: 0;">
                    <a href="admin-logout.php" class="logout-btn">
                        <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- ===================== ADMIN SIDEBAR ===================== -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <button class="sidebar-close" id="sidebarClose" aria-label="Close menu">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>

    <nav class="sidebar-links">
        <?php foreach ($admin_nav as $page => $meta): ?>
            <a href="<?= $page ?>"
            class="<?= $effective_active_page === $page ? 'active' : '' ?>"
            title="<?= $meta['title'] ?>"
            data-tooltip="<?= htmlspecialchars($meta['label']) ?>"
            >
                <span class="material-symbols-outlined">
                    <?= $meta['icon'] ?>
                </span>
                <span class="link-label"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
// Sidebar Toggle Logic
(function () {
    const body = document.body;
    const adminSidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const desktopCollapseBtn = document.getElementById('desktopCollapseBtn');
    const STORAGE_KEY = 'adminSidebarCollapsed';

    if (localStorage.getItem(STORAGE_KEY) === 'true') {
        body.classList.add('sidebar-collapsed');
    }

    if (desktopCollapseBtn) {
        desktopCollapseBtn.addEventListener('click', () => {
            const collapsed = body.classList.toggle('sidebar-collapsed');
            localStorage.setItem(STORAGE_KEY, collapsed);
        });
    }

    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');

    const openSidebar = () => {
        adminSidebar.classList.add('show');
        sidebarOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    };

    const closeSidebar = () => {
        adminSidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';
    };

    sidebarToggle?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) closeSidebar();
    });
})();

// Profile Dropdown Logic
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
