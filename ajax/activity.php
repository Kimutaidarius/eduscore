<?php
// ajax/activity.php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

// Set JSON response header properly
ob_clean(); // Clear any previous output

$school_id = $_SESSION['school_id'] ?? 0;

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School not found', 'activities' => []]);
    exit;
}

try {
    // Get school info
    $stmt = $dbh->prepare("
        SELECT school_name, created_at 
        FROM tblschoolinfo 
        WHERE `id` = :school_id
        LIMIT 1
    ");
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->execute();
    $school = $stmt->fetch(PDO::FETCH_ASSOC);

    $activities = [];

    if ($school) {
        // 1. School account creation
        $activities[] = [
            'activity' => "School account was created",
            'details' => $school['school_name'] . " registered on the system",
            'school_name' => $school['school_name'],
            'created_at' => $school['created_at'],
            'icon' => 'fas fa-school',
            'badge' => 'Account',
            'type' => 'account'
        ];

        // 2. Classes created (limited to 5)
        $stmt = $dbh->prepare("
            SELECT c.*, COUNT(s.id) as student_count
            FROM tblclasses c
            LEFT JOIN tblstudents s ON c.id = s.class_id
            WHERE c.school_id = :school_id
            GROUP BY c.id
            ORDER BY c.id DESC
            LIMIT 5
        ");
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($classes as $class) {
            $activities[] = [
                'activity' => "Class created: " . $class['class_level'] . " (" . $class['academic_level'] . ")",
                'details' => $class['stream'] ? "Stream: " . $class['stream'] . " • Students: " . $class['student_count'] : "Students: " . $class['student_count'],
                'school_name' => $school['school_name'],
                'created_at' => $class['academic_year'] ?: date('Y-m-d H:i:s'),
                'icon' => 'fas fa-chalkboard-teacher',
                'badge' => 'Class',
                'type' => 'class',
                'class_id' => $class['id']
            ];
        }

        // 3. Students added (limited to 5)
        $stmt = $dbh->prepare("
            SELECT s.*, c.class_level, c.academic_level, c.stream
            FROM tblstudents s
            LEFT JOIN tblclasses c ON s.class_id = c.id
            WHERE s.school_id = :school_id
            ORDER BY s.id DESC
            LIMIT 5
        ");
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($students as $student) {
            $activities[] = [
                'activity' => "Student enrolled: " . $student['FirstName'] . " " . $student['LastName'],
                'details' => "Adm No: " . $student['AdmNo'] . 
                           " • Class: " . $student['class_level'] . 
                           ($student['stream'] ? " (" . $student['stream'] . ")" : "") .
                           " • Gender: " . $student['Gender'],
                'school_name' => $school['school_name'],
                'created_at' => $student['admission_date'] ?: date('Y-m-d H:i:s'),
                'icon' => 'fas fa-user-graduate',
                'badge' => 'Student',
                'type' => 'student',
                'student_id' => $student['id']
            ];
        }

        // 4. Reports generated (limited to 5)
        $stmt = $dbh->prepare("
            SELECT rc.*, c.class_level, c.academic_level,
                   t.term_name, t.academic_year as term_year,
                   e.examname
            FROM tblreportconfigurations rc
            LEFT JOIN tblclasses c ON rc.class_id = c.id
            LEFT JOIN tblterms t ON rc.term_id = t.id
            LEFT JOIN tblexam e ON rc.exam_id = e.id
            WHERE rc.school_id = :school_id
            AND rc.batch_status = 'completed'
            ORDER BY rc.created_at DESC
            LIMIT 5
        ");
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reports as $report) {
            $activities[] = [
                'activity' => "Report generated: " . $report['report_title'],
                'details' => "Exam: " . $report['examname'] . 
                           " • Term: " . $report['term_name'] . " " . $report['term_year'] .
                           " • Class: " . $report['class_level'],
                'school_name' => $school['school_name'],
                'created_at' => $report['created_at'],
                'icon' => 'fas fa-file-alt',
                'badge' => 'Report',
                'type' => 'report',
                'report_id' => $report['id']
            ];
        }

        // 5. Exams created (limited to 5)
        $stmt = $dbh->prepare("
            SELECT e.*, c.class_level, c.academic_level
            FROM tblexam e
            LEFT JOIN tblclasses c ON e.class_id = c.id
            WHERE e.school_id = :school_id
            ORDER BY e.DateAdded DESC
            LIMIT 5
        ");
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($exams as $exam) {
            $activities[] = [
                'activity' => "Exam created: " . $exam['examname'],
                'details' => "Class: " . $exam['class_level'] . 
                           " • Deadline: " . ($exam['deadline_date'] ?: 'N/A') .
                           " • Status: " . $exam['status'],
                'school_name' => $school['school_name'],
                'created_at' => $exam['DateAdded'],
                'icon' => 'fas fa-edit',
                'badge' => 'Exam',
                'type' => 'exam',
                'exam_id' => $exam['id']
            ];
        }

        // Sort all activities by date (newest first)
        usort($activities, function($a, $b) {
            $timeA = strtotime($a['created_at']);
            $timeB = strtotime($b['created_at']);
            return $timeB - $timeA; // Descending order
        });

        // Limit to most recent 15 activities
        $activities = array_slice($activities, 0, 15);

    } else {
        echo json_encode(['success' => false, 'message' => 'School not found', 'activities' => []]);
        exit;
    }

    echo json_encode([
        'success' => true, 
        'activities' => $activities,
        'total' => count($activities)
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'activities' => []]);
}
exit;