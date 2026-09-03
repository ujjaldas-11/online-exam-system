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
            $jsSrc = (str_contains($safeJs, '/')) ? $safeJs : "$assetsPath/js/$safeJs";
            ?>
            <script src="<?= htmlspecialchars($jsSrc, ENT_QUOTES, 'UTF-8') ?>?v=<?= $assetVersion ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Universal Password Visibility Toggle Handler -->
    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.password-toggle-btn');
            if (!btn) return;
            e.preventDefault();

            const wrapper = btn.closest('.password-wrapper');
            if (!wrapper) return;
            const input = wrapper.querySelector('input');
            const icon = btn.querySelector('.material-symbols-outlined');
            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.innerText = 'visibility_off';
                btn.setAttribute('aria-label', 'Hide password');
                btn.setAttribute('title', 'Hide password');
            } else {
                input.type = 'password';
                if (icon) icon.innerText = 'visibility';
                btn.setAttribute('aria-label', 'Show password');
                btn.setAttribute('title', 'Show password');
            }
        });
    </script>
</body>
</html>
