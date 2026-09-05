<?php
/**
 * Shared Status Badge Component
 * Standardizes visual badges across exam controls, student dashboards, proctoring, and tables.
 */
declare(strict_types=1);

if (!function_exists('render_status_badge')) {
    function render_status_badge(string $status, string $context = 'exam', ?string $customLabel = null): string
    {
        $normalized = strtolower(trim($status));
        $badgeClass = 'badge-inactive';
        $icon = '';
        $label = $customLabel ?? ucfirst($normalized);

        if ($context === 'exam') {
            switch ($normalized) {
                case 'active':
                case 'running':
                case 'live':
                    $badgeClass = 'badge-running';
                    $icon = 'sensors';
                    $label = $customLabel ?? 'Running';
                    break;
                case 'scheduled':
                    $badgeClass = 'badge-scheduled';
                    $icon = 'schedule';
                    $label = $customLabel ?? 'Scheduled';
                    break;
                case 'ended':
                    $badgeClass = 'badge-ended';
                    $icon = 'lock';
                    $label = $customLabel ?? 'Ended';
                    break;
                case 'not_started':
                case 'notstarted':
                    $badgeClass = 'badge-notstarted';
                    $icon = 'hourglass_empty';
                    $label = $customLabel ?? 'Not Started';
                    break;
                default:
                    $badgeClass = 'badge-inactive';
                    $icon = 'info';
                    break;
            }
        } elseif ($context === 'proctor') {
            switch ($normalized) {
                case 'completed':
                case 'submitted':
                    $badgeClass = 'badge-active';
                    $icon = 'check_circle';
                    $label = $customLabel ?? 'Submitted';
                    break;
                case 'in_progress':
                case 'running':
                case 'active':
                    $badgeClass = 'badge-running';
                    $icon = 'timelapse';
                    $label = $customLabel ?? 'In Progress';
                    break;
                case 'not_started':
                case '':
                case null:
                    $badgeClass = 'badge-inactive';
                    $icon = 'radio_button_unchecked';
                    $label = $customLabel ?? 'Not Started';
                    break;
                case 'violation':
                    $badgeClass = 'badge-rejected';
                    $icon = 'warning';
                    $label = $customLabel ?? 'Violation';
                    break;
            }
        } elseif ($context === 'student') {
            switch ($normalized) {
                case 'active':
                case 'live':
                    $badgeClass = 'badge-active';
                    $icon = 'sensors';
                    $label = $customLabel ?? 'Live';
                    break;
                case 'scheduled':
                    $badgeClass = 'badge-scheduled';
                    $icon = 'schedule';
                    $label = $customLabel ?? 'Scheduled';
                    break;
                case 'ended':
                    $badgeClass = 'badge-ended';
                    $icon = 'lock';
                    $label = $customLabel ?? 'Ended';
                    break;
                case 'pending':
                    $badgeClass = 'badge-pending';
                    $icon = 'hourglass_top';
                    $label = $customLabel ?? 'Pending';
                    break;
                default:
                    $badgeClass = 'badge-inactive';
                    break;
            }
        }

        $iconHtml = $icon !== '' ? '<span class="material-symbols-outlined icon-xs" aria-hidden="true">' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '</span> ' : '';
        $labelEscaped = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $badgeClassEscaped = htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8');

        return '<span class="badge ' . $badgeClassEscaped . '">' . $iconHtml . $labelEscaped . '</span>';
    }
}
