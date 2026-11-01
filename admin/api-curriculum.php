<?php

require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../services/CurriculumService.php';

release_session_lock();

$type = $_GET['type'] ?? '';
$department = (string)($_GET['department'] ?? '');
$semester = (int)($_GET['semester'] ?? 0);
$subject_id = (int)($_GET['subject_id'] ?? 0);

if ($type === 'semesters' && !empty($department)) {
    json_response(CurriculumService::getSemestersByDepartment($pdo, $department));
} elseif ($type === 'subjects' && !empty($department) && $semester > 0) {
    $subjects = CurriculumService::getSubjects($pdo, $department, $semester);
    json_response(array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name']], $subjects));
} elseif ($type === 'units' && $subject_id > 0) {
    json_response(CurriculumService::getUnitsBySubject($pdo, $subject_id));
}

json_response([]);
