<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../services/CurriculumService.php';

release_session_lock();

$department = $_GET['department'] ?? '';

if (empty($department)) {
    json_response([]);
}

$semesters = CurriculumService::getSemestersByDepartment($pdo, (string)$department);
json_response($semesters);
