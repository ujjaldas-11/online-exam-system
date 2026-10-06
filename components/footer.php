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
    
    <!-- ===================== VISUAL FOOTER ===================== -->
    <style>
        .app-footer {
            margin-top: auto; /* Helps push footer to the bottom of the page */
            padding: 24px 20px;
            text-align: center;
            border-top: 1px solid var(--color-border, #e2e8f0);
            color: var(--color-text-secondary, #64748b);
            font-size: 0.85rem;
            background: transparent;
        }
        .app-footer-text {
            margin: 0 0 6px 0;
            font-weight: 500;
        }
        .app-footer-links {
            margin: 0;
        }
        .app-footer-links a {
            color: inherit;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .app-footer-links a:hover {
            color: var(--color-primary, #0d6efd);
        }
        .app-footer .divider {
            margin: 0 8px;
            opacity: 0.5;
        }
    </style>

    <footer class="app-footer">
        <p class="app-footer-text">
            &copy; <?= date('Y') ?> Examify. All rights reserved.
        </p>
        <p class="app-footer-links">
            Bengal Institute of Science & Technology (BIST)
            <span class="divider">&bull;</span>
            <a href="#">TATA ROAD, PURULIA</a>
            <span class="divider">&bull;</span>
            <a href="#">P.O -Dulmi-Nadiha</a>
        </p>
    </footer>
    <!-- ========================================================= -->

    <?php if (!empty($extra_js)): ?>
        <?php foreach ((array) $extra_js as $jsFile): ?>
            <?php
            $safeJs = sanitize_asset_name((string) $jsFile, 'js');
            if ($safeJs === null) {
                continue;
            }
            if (str_ends_with($safeJs, 'anti-cheat.js')) {
                $jsSrc = "$assetsPath/js/anti-cheat.js";
            } elseif (str_ends_with($safeJs, 'timer.js')) {
                $jsSrc = "$assetsPath/js/timer.js";
            } else {
                $jsSrc = (str_contains($safeJs, '/')) ? $safeJs : "$assetsPath/js/$safeJs";
            }
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
