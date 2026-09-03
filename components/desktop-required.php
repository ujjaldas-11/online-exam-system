<?php

/**
 * Desktop Required - Lockout Screen for Mobile & Tablet Devices
 * Rendered when mobile devices attempt to enter active exam routes.
 */

require_once __DIR__ . '/../utils/env.php';
require_once __DIR__ . '/../utils/sanitize.php';

$page_title = 'Desktop Required • Examify';
$body_class = 'auth-body';
include __DIR__ . '/header.php';
?>

<div class="auth-card" style="max-width: 520px; text-align: center; padding: 40px 28px;">
    <div style="display: inline-flex; align-items: center; justify-content: center; width: 80px; height: 80px; border-radius: 50%; background: #fee2e2; color: #dc2626; margin-bottom: 20px;">
        <span class="material-symbols-outlined" style="font-size: 44px;">desktop_windows</span>
    </div>

    <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--color-dark); margin-bottom: 8px;">
        Desktop Workstation Required
    </h1>

    <p style="color: var(--color-text-secondary); font-size: 0.95rem; line-height: 1.6; margin-bottom: 24px;">
        College examination regulations require students to take active tests on a <strong>desktop PC</strong> or <strong>laptop computer</strong> equipped with a physical keyboard and touchpad/mouse.
    </p>

    <div class="alert alert-error" style="text-align: left; margin-bottom: 24px; font-size: 0.88rem;">
        <div style="font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-xs">block</span> Mobile Phones & Tablets Prohibited
        </div>
        Active examinations cannot be launched from mobile phones, tablets, or touchpads to prevent tab switching, floating overlays, and proctoring violations.
    </div>

    <div class="alert alert-warning" style="text-align: left; margin-bottom: 28px; font-size: 0.84rem; color: #854d0e; background: #fef9c3; border-color: #fef08a;">
        <div style="font-weight: 700; margin-bottom: 2px; display: flex; align-items: center; gap: 4px;">
            <span class="material-symbols-outlined icon-xs">info</span> Using a Touchscreen Laptop?
        </div>
        Touchscreen laptops are permitted only when using the physical touchpad or mouse. Screen touch interactions are strictly disabled during the exam.
    </div>

    <div style="display: flex; flex-direction: column; gap: 10px;">
        <a href="dashboard.php" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 12px;">
            <span class="material-symbols-outlined icon-sm">arrow_back</span> Return to Student Dashboard
        </a>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
