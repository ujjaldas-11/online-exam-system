<?php
/**
 * Shared Flash Message Component
 * Checks for session flash messages across standard channels ('success', 'error', 'warning', 'info')
 * and renders dismissible alert banners.
 */
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/sanitize.php';

$flash_types = [
    'success' => ['class' => 'alert-success', 'icon' => 'check_circle'],
    'error'   => ['class' => 'alert-error',   'icon' => 'error'],
    'warning' => ['class' => 'alert-warning', 'icon' => 'warning'],
    'info'    => ['class' => 'alert-info',    'icon' => 'info'],
];

foreach ($flash_types as $type => $meta):
    $msg = get_flash($type);
    if ($msg):
?>
    <div class="alert <?= $meta['class'] ?>" style="display: flex; align-items: center; justify-content: space-between; gap: 10px; text-align: left;" role="alert">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span class="material-symbols-outlined icon-sm" aria-hidden="true"><?= $meta['icon'] ?></span>
            <span><?= e($msg) ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: inherit; display: flex; align-items: center; padding: 2px; opacity: 0.7;" aria-label="Dismiss alert">
            <span class="material-symbols-outlined icon-xs">close</span>
        </button>
    </div>
<?php
    endif;
endforeach;
?>
