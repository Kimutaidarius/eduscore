<?php
// === TEMPORARY DEBUGGING: ENABLE PHP ERROR REPORTING ===
// Remove these lines once you have resolved the issue.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// =======================================================

// Include your database connection file.
// The path may need to be adjusted depending on your directory structure.
require_once 'includes/config.php';

// Check for the maintenance mode status using the existing database connection ($dbh)
try {
    // Fetch the maintenance mode setting
    $stmt = $dbh->prepare("SELECT `setting_value` FROM `system_settings` WHERE `setting_name` = 'maintenance_mode'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $maintenanceMode = $result ? $result['setting_value'] : 'off';

    // Check if maintenance mode is enabled and the user is not a superadmin
    // Assuming 'user_role' is stored in the session for authenticated users.
    if ($maintenanceMode === 'on' && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'superadmin')) {
        header("Location: /maintenance.html");
        exit();
    }
} catch (PDOException $e) {
    // This catch block is for errors specific to the SQL query, not the connection.
    error_log("Maintenance check query error: " . $e->getMessage());
    die("An internal server error occurred during maintenance check.");
}
?>
