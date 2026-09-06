<?php

declare(strict_types=1);

/**
 * Curriculum Service
 * Centralizes department, semester, subject, and syllabus unit access logic.
 */
class CurriculumService
{
    private const DEFAULT_DEPARTMENTS = ['BCA', 'BBA'];

    /**
     * Retrieve all distinct active departments.
     *
     * @return string[]
     */
    public static function getDepartments(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT DISTINCT department FROM subjects WHERE department IS NOT NULL AND TRIM(department) != '' ORDER BY department ASC");
            $depts = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($depts)) {
                return array_values(array_unique(array_map('trim', $depts)));
            }
        } catch (PDOException) {
            // Fallback to default configured departments
        }

        return self::DEFAULT_DEPARTMENTS;
    }

    /**
     * Check if a department code is valid.
     */
    public static function isValidDepartment(PDO $pdo, string $department): bool
    {
        $department = trim($department);
        if ($department === '') {
            return false;
        }

        $validDepts = self::getDepartments($pdo);
        foreach ($validDepts as $valid) {
            if (strcasecmp($valid, $department) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate semester number (standard undergraduate semester 1-8).
     */
    public static function isValidSemester(int $semester): bool
    {
        return $semester >= 1 && $semester <= 8;
    }

    /**
     * Fetch all available semesters for a specific department.
     *
     * @return int[]
     */
    public static function getSemestersByDepartment(PDO $pdo, string $department): array
    {
        $department = trim($department);
        if ($department === '') {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT DISTINCT semester FROM subjects WHERE department = ? ORDER BY semester ASC");
            $stmt->execute([$department]);
            $semesters = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return array_map('intval', $semesters);
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * Fetch subjects with optional department and semester filters.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getSubjects(PDO $pdo, ?string $department = null, ?int $semester = null): array
    {
        $sql = "SELECT id, name, department, semester FROM subjects WHERE 1=1";
        $params = [];

        if ($department !== null && trim($department) !== '') {
            $sql .= " AND department = ?";
            $params[] = trim($department);
        }

        if ($semester !== null && $semester > 0) {
            $sql .= " AND semester = ?";
            $params[] = $semester;
        }

        $sql .= " ORDER BY name ASC";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * Fetch distinct unit numbers available in questions for a subject.
     *
     * @return int[]
     */
    public static function getUnitsBySubject(PDO $pdo, int $subjectId): array
    {
        if ($subjectId <= 0) {
            return [];
        }

        try {
            $stmt = $pdo->prepare("SELECT DISTINCT unit_number FROM questions WHERE subject_id = ? AND unit_number IS NOT NULL ORDER BY unit_number ASC");
            $stmt->execute([$subjectId]);
            $units = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return array_map('intval', $units);
        } catch (PDOException) {
            return [];
        }
    }
}
