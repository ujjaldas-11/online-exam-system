<?php
$current_page = basename($_SERVER['PHP_SELF']);

if ((!isset($pending_registration_requests_count) || !isset($pending_requests_count)) && isset($pdo)) {
    try {
        $sidebar_counts = $pdo->query("SELECT 
            (SELECT COUNT(*) FROM students WHERE status = 'pending') AS pending_students,
            (SELECT COUNT(*) FROM profile_requests WHERE status = 'pending') AS pending_requests"
        )->fetch(PDO::FETCH_ASSOC);
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

require_once __DIR__ . '/../utils/auth.php';
$isAdminSuper = is_superadmin();

$admin_nav = [
    'admin-dashboard.php' => ['label' => 'Dashboard', 'icon' => 'space_dashboard', 'title' => 'dashboard'],
    'manage-subjects.php' => ['label' => 'Subjects', 'icon' => 'menu_book', 'title' => 'manage subjects'],
    'manage-questions.php' => ['label' => 'Questions', 'icon' => 'quiz', 'title' => 'questions'],
    'control-exams.php' => ['label' => 'Exams', 'icon' => 'fact_check', 'title' => 'exams'],
    'results.php' => ['label' => 'Results', 'icon' => 'bar_chart', 'title' => 'results'],
    'manage-requests.php' => ['label' => 'Requests', 'icon' => 'notifications', 'title' => 'profile update request'],
    'registration-request.php' => ['label' => 'Registration Requests', 'icon' => 'person_add', 'title' => 'registration requests'],
    'manage-students.php' => ['label' => 'Students', 'icon' => 'group', 'title' => 'students'],

];

// Map secondary/child views to parent navigation item
$route_parents = [
    'manage-exam.php'    => 'control-exams.php',
    'proctor-exam.php'   => 'control-exams.php',
    'view-questions.php' => 'manage-questions.php',
    'edit-question.php'  => 'manage-questions.php',
    'view-results.php'   => 'results.php',
];
$effective_active_page = $route_parents[$current_page] ?? $current_page;

if ($isAdminSuper) {
    $admin_nav['manage-teachers.php'] = ['label' => 'Teachers', 'icon' => 'school', 'title' => 'teachers'];
    $admin_nav['audit-logs.php'] = ['label' => 'Audit Trail', 'icon' => 'receipt_long', 'title' => 'logs'];
    $admin_nav['import-students.php'] = ['label' => 'Import', 'icon' => 'upload_file', 'title' => 'import students'];
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
            <a href="registration-request.php"
            class="icon-btn topbar-shortcut <?= $current_page === 'registration-request.php' ? 'active' : '' ?>" aria-label="Notifications" title="registration requests">
                <span class="material-symbols-outlined">person_add</span>
                <?php if (!empty($pending_registration_requests_count)): ?>
                    <span class="topbar-badge"><?= (int) $pending_registration_requests_count ?></span>
                <?php endif; ?>
            </a>

            <a href="manage-requests.php"
            class="icon-btn topbar-shortcut <?= $current_page === 'manage-requests.php' ? 'active' : '' ?>" aria-label="Notifications" title="profile update requests">
                <span class="material-symbols-outlined">notifications</span>
                <?php if (!empty($pending_requests_count)): ?>
                    <span class="topbar-badge"><?= (int) $pending_requests_count ?></span>
                <?php endif; ?>
            </a>

            <div style="display: flex; flex-direction: column; align-items: flex-end; line-height: 1.2;">
                <span class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
                <span style="font-size: 0.72rem; color: #e2e8f0; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">
                    <?= htmlspecialchars($_SESSION['admin_role'] ?? 'Teacher', ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
            <span class="material-symbols-outlined profile-icon" aria-hidden="true">account_circle</span>
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

    <a href="admin-logout.php" class="sidebar-logout" data-tooltip="Logout">
        <span class="material-symbols-outlined">logout</span>
        <span class="link-label">Logout</span>
    </a>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
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
</script>
