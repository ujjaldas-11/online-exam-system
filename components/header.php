<?php
/**
 * Shared Header Layout Partial
 */

require_once __DIR__ . '/../utils/env.php';
require_once __DIR__ . '/../utils/sanitize.php';

$assetsPath = file_exists(__DIR__ . '/../assets/css/app.css') ? '../assets' : 'assets';
if (file_exists('assets/css/app.css')) {
    $assetsPath = 'assets';
}

$assetVersion = asset_version();
$pageTitle = $page_title ?? 'Examify • Online Examination System';
$bodyClass = $body_class ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/x-icon" href="<?= $assetsPath ?>/images/examify_icon.ico?v=<?= $assetVersion ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= $assetsPath ?>/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="apple-touch-icon" href="<?= $assetsPath ?>/images/examify_logo.png?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/material-symbols.css?v=<?= $assetVersion ?>">
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/app.css?v=<?= $assetVersion ?>">
    <?php if (!empty($extra_css)): ?>
        <?php foreach ((array) $extra_css as $cssFile): ?>
            <?php
            $safeCss = sanitize_asset_name((string) $cssFile, 'css');
            if ($safeCss === null) {
                continue;
            }
            $cssSrc = (strpos($safeCss, '/') !== false) ? $safeCss : "$assetsPath/css/$safeCss";
            ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($cssSrc, ENT_QUOTES, 'UTF-8') ?>?v=<?= $assetVersion ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
