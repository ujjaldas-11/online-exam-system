<?php
/**
 * Shared Footer Layout Partial
 */

require_once __DIR__ . '/../utils/env.php';

$assetsPath = file_exists(__DIR__ . '/../assets/css/app.css') ? '../assets' : 'assets';
if (file_exists('assets/css/app.css')) {
    $assetsPath = 'assets';
}

$assetVersion = asset_version();
?>
    <?php if (!empty($extra_js)): ?>
        <?php foreach ((array) $extra_js as $jsFile): ?>
            <?php
            $jsSrc = (strpos($jsFile, '/') !== false) ? $jsFile : "$assetsPath/js/$jsFile";
            ?>
            <script src="<?= htmlspecialchars($jsSrc) ?>?v=<?= $assetVersion ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
