<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['school_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$schoolId = $_SESSION['school_id'];
$level    = $_GET['level'] ?? '';

// Map dropdown values to actual DB academic_level values
$levelMap = [
    'pre-primary' => ['pre-primary'],
    'primary'     => ['lower-primary', 'upper-primary'], // ✅ combine both
    'junior'      => ['junior'],
    'senior'      => ['senior'],
];

try {
    // ---- Students ----
    $sqlStudents = "
        SELECT 
            COUNT(s.id) AS total_students,
            COALESCE(SUM(CASE WHEN s.Gender = 'Male' THEN 1 ELSE 0 END), 0) AS male_students,
            COALESCE(SUM(CASE WHEN s.Gender = 'Female' THEN 1 ELSE 0 END), 0) AS female_students
        FROM tblstudents s
        INNER JOIN tblclasses c ON c.id = s.class_id
        WHERE s.school_id = :school_id
    ";

    $params = [':school_id' => $schoolId];

    if (!empty($level) && isset($levelMap[$level])) {
        $placeholders = [];
        foreach ($levelMap[$level] as $i => $val) {
            $ph = ":level{$i}";
            $placeholders[] = $ph;
            $params[$ph] = strtolower($val);
        }
        $sqlStudents .= " AND LOWER(TRIM(c.academic_level)) IN (" . implode(',', $placeholders) . ")";
    }

    $query = $dbh->prepare($sqlStudents);
    $query->execute($params);
    $studentCounts = $query->fetch(PDO::FETCH_ASSOC);

    // ---- Teachers ----
    $sqlTeachers = "
        SELECT 
            COUNT(t.id) AS total_teachers,
            COALESCE(SUM(CASE WHEN t.gender = 'Male' THEN 1 ELSE 0 END), 0) AS male_teachers,
            COALESCE(SUM(CASE WHEN t.gender = 'Female' THEN 1 ELSE 0 END), 0) AS female_teachers
        FROM tblteachers t
        WHERE t.school_id = :school_id
    ";

    $params = [':school_id' => $schoolId];

    if (!empty($level) && isset($levelMap[$level])) {
        $placeholders = [];
        foreach ($levelMap[$level] as $i => $val) {
            $ph = ":level{$i}";
            $placeholders[] = $ph;
            $params[$ph] = strtolower($val);
        }
        $sqlTeachers .= " 
            AND EXISTS (
                SELECT 1 
                FROM tblclasses c 
                WHERE c.school_id = t.school_id 
                AND LOWER(TRIM(c.academic_level)) IN (" . implode(',', $placeholders) . ")
            )
        ";
    }

    $query = $dbh->prepare($sqlTeachers);
    $query->execute($params);
    $teacherCounts = $query->fetch(PDO::FETCH_ASSOC);

    // Ensure numeric values
    $studentCounts = array_map('intval', $studentCounts ?: []);
    $teacherCounts = array_map('intval', $teacherCounts ?: []);

    echo json_encode([
        'status'   => 'success',
        'students' => $studentCounts,
        'teachers' => $teacherCounts
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
