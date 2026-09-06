<?php

declare(strict_types=1);

/**
 * Examify — Online Examination System
 *
 * Real-Time WebSocket & IPC Daemon CLI Entrypoint (Root Alias)
 *
 * @package   Examify
 * @copyright (c) Bibekananda Mudi
 * @license   MIT License (see LICENSE file in root)
 *
 * Usage:
 *   php server.php
 */

require_once __DIR__ . '/bin/websocket-server.php';
