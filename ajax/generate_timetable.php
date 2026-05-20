<?php
// ajax/generate_timetable.php - Conflict-Free Timetable Generator
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 120);

header('Content-Type: application/json');

require_once dirname(__DIR__) . '/includes/config.php';

if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}
$conn->set_charset("utf8mb4");

$input = json_decode(file_get_contents('php://input'), true);
$school_id = (int)($_SESSION['school_id']);
$class_id = (int)($input['class_id'] ?? 0);
$stream_id = (int)($input['stream_id'] ?? 0);

// Fetch settings
$settings = getSettings($conn, $school_id);
$days = getSchoolDays($settings);
$periods = getPeriods($conn, $school_id);
$subjects = getSubjectLessons($conn, $school_id, $class_id);

if (empty($subjects)) {
    echo json_encode(['success' => false, 'message' => 'No subjects configured for this class']);
    exit();
}

// Generate timetable
try {
    $timetable = generateTimetable($days, $periods, $subjects, $settings);
    
    // Save to database
    saveTimetable($conn, $school_id, $class_id, $stream_id, $timetable);
    
    echo json_encode([
        'success' => true,
        'message' => 'Timetable generated successfully',
        'timetable' => $timetable,
        'stats' => [
            'total_lessons' => count($timetable),
            'days' => count($days),
            'periods' => count($periods)
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();

// ==================== FUNCTIONS ====================

function getSettings($conn, $school_id) {
    $stmt = $conn->prepare("SELECT * FROM tbl_timetable_settings WHERE school_id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result ?: [
        'periods_per_day' => 8,
        'first_period_start' => '07:30:00',
        'period_duration' => 60,
        'school_days' => 5,
        'include_saturday' => 0,
        'monday_assembly' => 1,
        'friday_games' => 1
    ];
}

function getSchoolDays($settings) {
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    if ($settings['include_saturday']) {
        $days[] = 'Saturday';
    }
    return $days;
}

function getPeriods($conn, $school_id) {
    $stmt = $conn->prepare("SELECT * FROM tbl_timetable_periods WHERE school_id = ? ORDER BY period_number");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $periods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($periods)) {
        // Generate default periods
        $periods = [];
        $start = strtotime('07:30');
        for ($i = 1; $i <= 8; $i++) {
            $period_start = date('H:i', $start);
            $period_end = date('H:i', $start + 3600);
            $periods[] = [
                'period_number' => $i,
                'period_name' => 'P' . $i,
                'start_time' => $period_start,
                'end_time' => $period_end,
                'is_break' => ($i == 4) ? 1 : 0,
                'break_type' => ($i == 4) ? 'short_break' : 'none'
            ];
            $start += 3600;
            if ($i == 4) $start += 1800; // Add break time
        }
    }
    return $periods;
}

function getSubjectLessons($conn, $school_id, $class_id) {
    $stmt = $conn->prepare("
        SELECT sl.*, s.subject_name, CONCAT(t.firstname, ' ', t.lastname) as teacher_name
        FROM tbl_subject_lessons sl
        JOIN tblsubjects s ON sl.subject_id = s.id
        JOIN tblteachers t ON sl.teacher_id = t.id
        WHERE sl.school_id = ? AND sl.class_id = ?
    ");
    $stmt->bind_param("ii", $school_id, $class_id);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $subjects;
}

function generateTimetable($days, $periods, $subjects, $settings) {
    $timetable = [];
    $teacher_schedule = []; // Track teacher availability
    $max_attempts = 100;
    
    // Step 1: Expand lessons into slots
    $lesson_slots = [];
    foreach ($subjects as $subject) {
        $lessons = $subject['lessons_per_week'];
        $type = $subject['lesson_type'];
        
        if ($type === 'double') {
            // Create pairs of consecutive slots
            for ($i = 0; $i < $lessons; $i++) {
                $lesson_slots[] = [
                    'subject_id' => $subject['subject_id'],
                    'teacher_id' => $subject['teacher_id'],
                    'teacher_name' => $subject['teacher_name'],
                    'subject_name' => $subject['subject_name'],
                    'type' => 'double',
                    'assigned' => false
                ];
            }
        } else {
            for ($i = 0; $i < $lessons; $i++) {
                $lesson_slots[] = [
                    'subject_id' => $subject['subject_id'],
                    'teacher_id' => $subject['teacher_id'],
                    'teacher_name' => $subject['teacher_name'],
                    'subject_name' => $subject['subject_name'],
                    'type' => 'single',
                    'assigned' => false
                ];
            }
        }
    }
    
    // Step 2: Sort by priority (doubles first, then constrained subjects)
    usort($lesson_slots, function($a, $b) {
        if ($a['type'] === 'double' && $b['type'] !== 'double') return -1;
        if ($a['type'] !== 'double' && $b['type'] === 'double') return 1;
        return 0;
    });
    
    // Step 3: Assign lessons using greedy algorithm
    $attempt = 0;
    
    foreach ($lesson_slots as &$slot) {
        $placed = false;
        
        foreach ($days as $day) {
            if ($placed) break;
            
            // Skip Assembly on Monday Period 1
            $skip_periods = getFixedSlots($day, $periods, $settings);
            
            foreach ($periods as $p_idx => $period) {
                if ($placed) break;
                if ($period['is_break']) continue;
                if (in_array($period['period_number'], $skip_periods)) continue;
                
                $time_key = $period['start_time'] . '-' . $period['end_time'];
                
                // Check teacher availability
                if (isset($teacher_schedule[$slot['teacher_id']][$day][$time_key])) {
                    continue;
                }
                
                // For double lessons, check next period is also free
                if ($slot['type'] === 'double') {
                    $next_idx = $p_idx + 1;
                    if (!isset($periods[$next_idx])) continue;
                    if ($periods[$next_idx]['is_break']) continue;
                    
                    $next_time_key = $periods[$next_idx]['start_time'] . '-' . $periods[$next_idx]['end_time'];
                    if (isset($teacher_schedule[$slot['teacher_id']][$day][$next_time_key])) {
                        continue;
                    }
                    
                    // Assign double lesson
                    $timetable[] = [
                        'day' => $day,
                        'time_slot' => $time_key,
                        'period_number' => $period['period_number'],
                        'subject_id' => $slot['subject_id'],
                        'teacher_id' => $slot['teacher_id'],
                        'subject_name' => $slot['subject_name'],
                        'teacher_name' => $slot['teacher_name'],
                        'type' => 'double'
                    ];
                    $timetable[] = [
                        'day' => $day,
                        'time_slot' => $next_time_key,
                        'period_number' => $periods[$next_idx]['period_number'],
                        'subject_id' => $slot['subject_id'],
                        'teacher_id' => $slot['teacher_id'],
                        'subject_name' => $slot['subject_name'],
                        'teacher_name' => $slot['teacher_name'],
                        'type' => 'double'
                    ];
                    
                    $teacher_schedule[$slot['teacher_id']][$day][$time_key] = true;
                    $teacher_schedule[$slot['teacher_id']][$day][$next_time_key] = true;
                    $slot['assigned'] = true;
                    $placed = true;
                } else {
                    // Assign single lesson
                    $timetable[] = [
                        'day' => $day,
                        'time_slot' => $time_key,
                        'period_number' => $period['period_number'],
                        'subject_id' => $slot['subject_id'],
                        'teacher_id' => $slot['teacher_id'],
                        'subject_name' => $slot['subject_name'],
                        'teacher_name' => $slot['teacher_name'],
                        'type' => 'single'
                    ];
                    
                    $teacher_schedule[$slot['teacher_id']][$day][$time_key] = true;
                    $slot['assigned'] = true;
                    $placed = true;
                }
            }
        }
        
        $attempt++;
        if ($attempt > $max_attempts) {
            throw new Exception("Could not place all lessons after {$max_attempts} attempts");
        }
    }
    
    return $timetable;
}

function getFixedSlots($day, $periods, $settings) {
    $fixed = [];
    
    // Monday Assembly - blocks Period 1
    if ($day === 'Monday' && $settings['monday_assembly']) {
        $fixed[] = 1;
    }
    
    // Friday Games - blocks last period
    if ($day === 'Friday' && $settings['friday_games']) {
        $last_period = end($periods);
        $fixed[] = $last_period['period_number'];
    }
    
    return $fixed;
}

function saveTimetable($conn, $school_id, $class_id, $stream_id, $timetable) {
    // Clear existing timetable for this class
    $stmt = $conn->prepare("DELETE FROM tbl_timetable WHERE school_id = ? AND class_id = ? AND stream_id = ?");
    $stmt->bind_param("iii", $school_id, $class_id, $stream_id);
    $stmt->execute();
    $stmt->close();
    
    // Insert new timetable
    $stmt = $conn->prepare("
        INSERT INTO tbl_timetable (school_id, class_id, stream_id, subject_id, teacher_id, day, time_slot, period_number, lesson_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($timetable as $lesson) {
        $stmt->bind_param(
            "iiiisssis",
            $school_id, $class_id, $stream_id,
            $lesson['subject_id'], $lesson['teacher_id'],
            $lesson['day'], $lesson['time_slot'],
            $lesson['period_number'], $lesson['type']
        );
        $stmt->execute();
    }
    $stmt->close();
}