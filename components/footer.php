<?php
/**
 * Shared Footer Layout Partial
 */

require_once __DIR__ . '/../utils/env.php';
require_once __DIR__ . '/../utils/sanitize.php';

$assetsPath = file_exists(__DIR__ . '/../assets/css/app.css') ? '../assets' : 'assets';
if (file_exists('assets/css/app.css')) {
    $assetsPath = 'assets';
}

$assetVersion = asset_version();
?>
    <?php if (!empty($extra_js)): ?>
        <?php foreach ((array) $extra_js as $jsFile): ?>
            <?php
            $safeJs = sanitize_asset_name((string) $jsFile, 'js');
            if ($safeJs === null) {
                continue;
            }
            $jsSrc = (strpos($safeJs, '/') !== false) ? $safeJs : "$assetsPath/js/$safeJs";
            ?>
            <script src="<?= htmlspecialchars($jsSrc, ENT_QUOTES, 'UTF-8') ?>?v=<?= $assetVersion ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
