<?php

/**
 * @deprecated Use tools/setup-db.php or tools/reset-and-seed.php instead.
 */
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');
echo "This script is deprecated. Please run tools/setup-db.php or tools/reset-and-seed.php instead.\n";
exit(0);
