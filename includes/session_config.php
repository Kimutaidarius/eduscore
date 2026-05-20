<?php
// includes/session_config.php

// Validate teacher session
function validateTeacherSession($dbh = null) {
    // Check if session is started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
        // Redirect to login if session variables are not set
        if (!headers_sent()) {
            header('Location: login.php');
        }
        exit();
    }
    
    // If database connection is provided, validate teacher exists and is active
    if ($dbh) {
        try {
            $stmt = $dbh->prepare("SELECT id FROM tblteachers WHERE id = ? AND school_id = ? AND status = 'Active'");
            $stmt->execute([$_SESSION['teacher_id'], $_SESSION['school_id']]);
            
            if (!$stmt->fetch()) {
                session_destroy();
                if (!headers_sent()) {
                    header('Location: login.php');
                }
                exit();
            }
        } catch (PDOException $e) {
            error_log("Session validation error: " . $e->getMessage());
            // Continue anyway - don't block due to database error
        }
    }
    
    return true;
}

// Set default academic level if not set
function setDefaultAcademicLevel($dbh) {
    if (!isset($_SESSION['academic_level_id']) && isset($_SESSION['school_id'])) {
        try {
            $stmt = $dbh->prepare("SELECT academic_level FROM tblclasses WHERE school_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['school_id']]);
            $result = $stmt->fetch();
            
            if ($result) {
                $_SESSION['academic_level_id'] = $result['academic_level'];
            } else {
                $_SESSION['academic_level_id'] = 'primary'; // Default
            }
        } catch (PDOException $e) {
            error_log("Academic level setting error: " . $e->getMessage());
            $_SESSION['academic_level_id'] = 'primary'; // Default on error
        }
    }
}
?>