<?php
/**
 * Shared Header Layout Partial
 */

$assetsPath = file_exists(__DIR__ . '/../assets/css/app.css') ? '../assets' : 'assets';
if (file_exists('assets/css/app.css')) {
    $assetsPath = 'assets';
}

$pageTitle = $page_title ?? 'Examify • Online Examination System';
$bodyClass = $body_class ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= $assetsPath ?>/css/app.css">
    <?php if (!empty($extra_css)): ?>
        <?php foreach ((array) $extra_css as $cssFile): ?>
            <link rel="stylesheet" href="<?= $assetsPath ?>/css/<?= htmlspecialchars($cssFile) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass) ?>">
