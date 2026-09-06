<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../services/CurriculumService.php';

release_session_lock();

$subject_id = (int)($_GET['subject_id'] ?? 0);

if ($subject_id <= 0) {
    json_response([]);
}

$units = CurriculumService::getUnitsBySubject($pdo, $subject_id);
json_response($units);
