<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../services/CurriculumService.php';

release_session_lock();

$department = $_GET['department'] ?? '';
$semester = (int)($_GET['semester'] ?? 0);

if (empty($department) || $semester <= 0) {
    json_response([]);
}

$subjects = CurriculumService::getSubjects($pdo, (string)$department, $semester);
$result = array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name']], $subjects);
json_response($result);
