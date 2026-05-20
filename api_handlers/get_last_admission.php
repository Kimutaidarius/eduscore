<?php
session_start();
require_once('../includes/config.php');

header('Content-Type: application/json');

// Enhanced security: Check if user is properly authenticated
if (empty($_SESSION['authenticated']) || empty($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$school_id = $_SESSION['school_id'];

// Get school initials from school info
function getSchoolInitials($dbh, $school_id) {
    try {
        $stmt = $dbh->prepare("SELECT school_initials FROM tblschoolinfo WHERE id = :school_id LIMIT 1");
        $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
        $stmt->execute();
        $school = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($school && !empty($school['school_initials'])) {
            return strtoupper($school['school_initials']);
        }
    } catch (PDOException $e) {
        error_log("Error fetching school initials: " . $e->getMessage());
    }
    
    // Default fallback - you can change this
    return 'SCH';
}

// Get current academic year
$current_year = date('Y');

try {
    // Get the school initials
    $school_initials = getSchoolInitials($dbh, $school_id);
    
    // Find the maximum sequential number for this school and year
    // Format: NK/001/2026 - where NK is school initials, 001 is sequential number, 2026 is year
    $pattern = $school_initials . '/%/' . $current_year;
    
    $stmt = $dbh->prepare("SELECT AdmNo FROM tblstudents 
                          WHERE school_id = :school_id 
                          AND AdmNo LIKE :pattern
                          ORDER BY id DESC 
                          LIMIT 1");
    $stmt->bindParam(':school_id', $school_id, PDO::PARAM_INT);
    $stmt->bindParam(':pattern', $pattern);
    $stmt->execute();
    
    $last_admission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_admission && !empty($last_admission['AdmNo'])) {
        // Extract the sequential number from format NK/001/2026
        preg_match('/' . preg_quote($school_initials, '/') . '\/(\d+)\/' . $current_year . '/', $last_admission['AdmNo'], $matches);
        if (isset($matches[1])) {
            $next_number = intval($matches[1]) + 1;
        } else {
            $next_number = 1;
        }
    } else {
        // No existing admission numbers for this year, start from 1
        $next_number = 1;
    }
    
    // Format the admission number: INITIALS/XXX/YYYY
    $next_admission = sprintf("%s/%03d/%d", $school_initials, $next_number, $current_year);
    
    echo json_encode([
        "success" => true,
        "next_admission" => $next_admission,
        "school_id" => $school_id,
        "school_initials" => $school_initials,
        "current_year" => $current_year,
        "format" => "initials/sequential/year"
    ]);

} catch (PDOException $e) {
    error_log("Admission number error - School ID: $school_id, Error: " . $e->getMessage());
    
    // Fallback - generate a basic admission number
    $fallback = "STU/" . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT) . "/" . date('Y');
    echo json_encode([
        "success" => true,
        "next_admission" => $fallback,
        "message" => "Using fallback admission number format"
    ]);
}
?>