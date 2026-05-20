<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include('../includes/config.php');

$response = ["success" => false, "message" => ""];

// Optional session check (useful when triggered from frontend)
if (
    isset($_SESSION['school_id']) &&
    !empty($_SESSION['school_id'])
) {
    $school_id = (int) $_SESSION['school_id'];
} else {
    // Allow cron to call it without session
    $school_id = null;
}

try {
    // === AUTO CLOSE EXAMS PAST DEADLINE ===
    $sql = "UPDATE tblexam 
            SET status = 'Closed', last_updated = NOW() 
            WHERE deadline_date IS NOT NULL 
            AND deadline_date < CURDATE() 
            AND status != 'Closed'";
    if ($school_id) {
        $sql .= " AND school_id = :school_id";
        $stmt = $dbh->prepare($sql);
        $stmt->execute([':school_id' => $school_id]);
    } else {
        $stmt = $dbh->prepare($sql);
        $stmt->execute();
    }

    $closedCount = $stmt->rowCount();

    // === AUTO ACTIVATE EXAMS (OPTIONAL) ===
    // If you also want to mark upcoming exams as "Active"
    $sqlActive = "UPDATE tblexam 
                  SET status = 'Active', last_updated = NOW() 
                  WHERE (deadline_date IS NULL OR deadline_date >= CURDATE()) 
                  AND status != 'Active'";
    if ($school_id) {
        $sqlActive .= " AND school_id = :school_id";
        $stmtActive = $dbh->prepare($sqlActive);
        $stmtActive->execute([':school_id' => $school_id]);
    } else {
        $stmtActive = $dbh->prepare($sqlActive);
        $stmtActive->execute();
    }

    $activatedCount = $stmtActive->rowCount();

    $response['success'] = true;
    $response['message'] = "Auto-update complete: {$closedCount} closed, {$activatedCount} activated.";
    echo json_encode($response);
} catch (PDOException $e) {
    error_log("Auto-update exams error: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Database error during auto-update.';
    echo json_encode($response);
}
?>
