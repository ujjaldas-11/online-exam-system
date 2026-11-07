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

// --- AVATAR & PROFILE DATA ---
$admin_gender = strtolower($_SESSION['gender'] ?? 'male');
$nav_avatar_url = ($admin_gender === 'female')
    ? '../assets/avatars/admin-female.avif'
    : '../assets/avatars/admin-male.webp';

$adminName   = $_SESSION['admin_name']  ?? 'Admin';
$adminRole   = $_SESSION['admin_role']  ?? 'Teacher';
$adminEmail  = $_SESSION['admin_email'] ?? 'admin@bist.edu';
$adminDept   = $_SESSION['admin_dept']  ?? 'Computer Science';
$collegeName = 'Bengal Institute of Science & Technology (BIST)';

require_once __DIR__ . '/../utils/auth.php';
$isAdminSuper = is_superadmin();

$admin_nav = [
    'admin-dashboard.php'   => ['label' => 'Dashboard', 'icon' => 'space_dashboard'],
    'manage-subjects.php'   => ['label' => 'Subjects',  'icon' => 'menu_book'],
    'manage-questions.php'  => ['label' => 'Questions', 'icon' => 'quiz'],
    'control-exams.php'     => ['label' => 'Exams',     'icon' => 'fact_check'],
    'results.php'           => ['label' => 'Results',   'icon' => 'bar_chart'],
    'manage-students.php'   => ['label' => 'Students',  'icon' => 'group'],
];

// Child views -> highlight parent nav item
$route_parents = [
    'manage-exam.php'    => 'control-exams.php',
    'proctor-exam.php'   => 'control-exams.php',
    'view-questions.php' => 'manage-questions.php',
    'edit-question.php'  => 'manage-questions.php',
    'view-results.php'   => 'results.php',
];
$effective_active_page = $route_parents[$current_page] ?? $current_page;

if ($isAdminSuper) {
    $admin_nav['manage-teachers.php']       = ['label' => 'Teachers',              'icon' => 'school'];
    $admin_nav['registration-request.php']  = ['label' => 'Registration Requests', 'icon' => 'person_add'];
    $admin_nav['manage-requests.php']       = ['label' => 'Update Requests',       'icon' => 'notifications'];
    $admin_nav['audit-logs.php']            = ['label' => 'Audit Trail',           'icon' => 'receipt_long'];
    $admin_nav['settings.php']              = ['label' => 'Settings & Backup',     'icon' => 'settings'];
} else {
    $admin_nav['audit-logs.php'] = ['label' => 'My Activity', 'icon' => 'history'];
}

// Pending counts shown as badges in the sidebar
$nav_badges = [
    'registration-request.php' => (int) ($pending_registration_requests_count ?? 0),
    'manage-requests.php'      => (int) ($pending_requests_count ?? 0),
];

$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<!-- ===================== ADMIN TOPBAR ===================== -->
<header class="admin-topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <button class="icon-btn mobile-menu-btn" id="sidebarToggle" type="button" aria-label="Open navigation" aria-controls="adminSidebar">
                <span class="material-symbols-outlined">menu</span>
            </button>

            <a href="admin-dashboard.php" class="topbar-brand student-brand" aria-label="Examify dashboard">
                <img src="../assets/images/examify_logo.png" alt="" class="auth-logo">
                <span class="nav-logo brand-wordmark">xamify</span>
            </a>
        </div>

        <div class="topbar-right">
            <?php if ($isAdminSuper): ?>
                <a href="registration-request.php"
                   class="icon-btn topbar-shortcut <?= $current_page === 'registration-request.php' ? 'active' : '' ?>"
                   aria-label="Registration requests" title="Registration requests">
                    <span class="material-symbols-outlined">person_add</span>
                    <?php if (!empty($pending_registration_requests_count)): ?>
                        <span class="topbar-badge"><?= (int) $pending_registration_requests_count ?></span>
                    <?php endif; ?>
                </a>

                <a href="manage-requests.php"
                   class="icon-btn topbar-shortcut <?= $current_page === 'manage-requests.php' ? 'active' : '' ?>"
                   aria-label="Profile update requests" title="Profile update requests">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if (!empty($pending_requests_count)): ?>
                        <span class="topbar-badge"><?= (int) $pending_requests_count ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <a href="../docs/user/admin-doc.php" class="icon-btn topbar-shortcut" aria-label="Documentation" title="Read documentation">
                <span class="material-symbols-outlined">help</span>
            </a>

            <button type="button" onclick="toggleExamifyTheme()" class="icon-btn no-print" aria-label="Switch theme" title="Switch theme">
                <span class="material-symbols-outlined" id="theme-icon">dark_mode</span>
            </button>

            <span class="topbar-sep" aria-hidden="true"></span>

            <div class="profile-widget">
                <button class="profile-trigger" id="profileTrigger" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="profileDropdown">
                    <img src="<?= $e($nav_avatar_url) ?>" alt="" class="nav-profile-pic">
                    <span class="profile-text">
                        <span class="admin-name"><?= $e($adminName) ?></span>
                        <span class="admin-role"><?= $e($adminRole) ?></span>
                    </span>
                    <span class="material-symbols-outlined chevron-icon" aria-hidden="true">expand_more</span>
                </button>

                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <img src="<?= $e($nav_avatar_url) ?>" alt="" class="nav-profile-pic lg">
                        <div class="dropdown-identity">
                            <p class="dropdown-name"><?= $e($adminName) ?></p>
                            <span class="role-badge"><?= $e($adminRole) ?></span>
                            <p class="dropdown-college"><?= $e($collegeName) ?></p>
                        </div>
                    </div>

                    <div class="dropdown-admin-details">
                        <p><span class="material-symbols-outlined" aria-hidden="true">email</span><?= $e($adminEmail) ?></p>
                        <p><span class="material-symbols-outlined" aria-hidden="true">school</span><?= $e($adminDept) ?></p>
                    </div>

                    <div class="dropdown-actions">
                        <a href="admin-reset-password.php" class="dropdown-item">
                            <span class="material-symbols-outlined" aria-hidden="true">lock_reset</span>
                            <span class="dropdown-item-label">Reset password</span>
                            <span class="material-symbols-outlined dropdown-item-arrow" aria-hidden="true">chevron_right</span>
                        </a>
                        <a href="admin-logout.php" class="dropdown-item logout-btn">
                            <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                            <span class="dropdown-item-label">Logout</span>
                            <span class="material-symbols-outlined dropdown-item-arrow" aria-hidden="true">chevron_right</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- ===================== ADMIN SIDEBAR ===================== -->
<aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
    <div class="sidebar-header">
        <span class="sidebar-title">Menu</span>
        <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close menu">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>

    <nav class="sidebar-links">
        <?php foreach ($admin_nav as $page => $meta):
            $isActive = $effective_active_page === $page;
            $badge    = $nav_badges[$page] ?? 0;
        ?>
            <a href="<?= $e($page) ?>"
               class="<?= $isActive ? 'active' : '' ?>"
               data-tooltip="<?= $e($meta['label']) ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <span class="material-symbols-outlined" aria-hidden="true"><?= $e($meta['icon']) ?></span>
                <span class="link-label"><?= $e($meta['label']) ?></span>
                <?php if ($badge > 0): ?>
                    <span class="link-badge"><?= $badge ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <button type="button" class="sidebar-collapse-btn desktop-collapse-btn" id="desktopCollapseBtn"
                aria-label="Collapse sidebar" aria-expanded="true" data-tooltip="Expand sidebar">
            <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
            <span class="link-label">Collapse</span>
        </button>
    </nav>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
/* ---------- Theme ---------- */
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

document.addEventListener('DOMContentLoaded', syncThemeUI);

/* ---------- Sidebar ---------- */
(function () {
    const body = document.body;
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const collapseBtn = document.getElementById('desktopCollapseBtn');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const KEY = 'adminSidebarCollapsed';

    const syncCollapse = (collapsed) => {
        if (!collapseBtn) return;
        collapseBtn.setAttribute('aria-expanded', String(!collapsed));
        collapseBtn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        collapseBtn.setAttribute('data-tooltip', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    };

    let saved = false;
    try { saved = localStorage.getItem(KEY) === 'true'; } catch (e) {}
    if (saved) body.classList.add('sidebar-collapsed');
    syncCollapse(saved);

    collapseBtn?.addEventListener('click', () => {
        const collapsed = body.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem(KEY, collapsed); } catch (e) {}
        syncCollapse(collapsed);
    });

    const openSidebar = () => {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        body.style.overflow = 'hidden';
    };
    const closeSidebar = () => {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        body.style.overflow = '';
    };

    toggleBtn?.addEventListener('click', openSidebar);
    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSidebar(); });
    window.addEventListener('resize', () => { if (window.innerWidth > 992) closeSidebar(); });
})();

/* ---------- Profile dropdown ---------- */
(function () {
    const trigger = document.getElementById('profileTrigger');
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
        if (e.key === 'Escape') { setOpen(false); trigger.focus(); }
    });
})();
</script>

<div id="adminAppViewport" class="admin-app-viewport">