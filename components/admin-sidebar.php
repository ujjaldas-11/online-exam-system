<?php
$current_page = basename($_SERVER['PHP_SELF']);

$admin_nav = [
    'admin-dashboard.php' => ['label' => 'Dashboard', 'icon' => 'space_dashboard'],
    'manage-subjects.php' => ['label' => 'Subjects', 'icon' => 'menu_book'],
    'manage-questions.php' => ['label' => 'Questions', 'icon' => 'quiz'],
    'control-exams.php' => ['label' => 'Exams', 'icon' => 'fact_check'],
    'results.php' => ['label' => 'Results', 'icon' => 'bar_chart'],
    'manage-requests.php' => ['label' => 'Requests', 'icon' => 'notifications'],
    // 'proctor-exam.php' => ['label' => 'Proctor', 'icon' => 'visibility'],
    'import-students.php' => ['label' => 'Import', 'icon' => 'upload_file'],
];
?>

<!-- ===================== ADMIN TOPBAR ===================== -->
<header class="admin-topbar">
    <div class="topbar-inner">
        <div class="topbar-left">
            <button class="icon-btn desktop-collapse-btn" id="desktopCollapseBtn" aria-label="Collapse sidebar">
                <span class="material-symbols-outlined">dock_to_right</span>
            </button>
            <button class="icon-btn mobile-menu-btn" id="sidebarToggle" aria-label="Open navigation">
                <span class="material-symbols-outlined">menu</span>
            </button>
        </div>

        <div class="topbar-right">
           <a href="manage-requests.php"
            class="icon-btn topbar-shortcut <?= $current_page === 'manage-requests.php' ? 'active' : '' ?>" aria-label="Notifications" title="Notifications">
                <span class="material-symbols-outlined">notifications</span>
                <?php if (!empty($pending_requests_count)): ?>
                    <span class="topbar-badge"><?= (int) $pending_requests_count ?></span>
                <?php endif; ?>
            </a>

            <span class="admin-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
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
            class="<?= $current_page === $page ? 'active' : '' ?>"
            data-tooltip="<?= htmlspecialchars($meta['label']) ?>">
                <span class="material-symbols-outlined">
                    <?= $meta['icon'] ?>
                </span>
                <span class="link-label"><?= htmlspecialchars($meta['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <a href="admin-logout.php" class="sidebar-logout" data-tooltip="Logout">
        <span class="material-symbols-outlined">logout</span>
        <span class="link-label">Logout</span>
    </a>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<link rel="stylesheet" href="../assets/css/admin-sidebar.css">


<script>


    // ===================== Admin Sidebar Behaviour =====================
(function () {
    const body = document.body;
    const adminSidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // ---- Desktop minimize / expand (persisted across pages) ----
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

    // ---- Mobile drawer open / close ----
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

    // Close mobile drawer automatically if resized back to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) closeSidebar();
    });
})();
</script>
