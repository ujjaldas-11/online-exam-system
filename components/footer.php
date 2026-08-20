<?php
/**
 * Shared Footer Layout Partial
 */

$assetsPath = file_exists(__DIR__ . '/../assets/css/app.css') ? '../assets' : 'assets';
if (file_exists('assets/css/app.css')) {
    $assetsPath = 'assets';
}
?>
    <?php if (!empty($extra_js)): ?>
        <?php foreach ((array) $extra_js as $jsFile): ?>
            <script src="<?= $assetsPath ?>/js/<?= htmlspecialchars($jsFile) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
