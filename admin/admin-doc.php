<?php
/**
 * Admin Documentation Shortcut
 * Redirects authenticated instructors and superadmins to the official Admin Documentation.
 */

require_once __DIR__ . '/admin-guard.php';

header('Location: ../docs/user/admin-doc.php');
exit;
