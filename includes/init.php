<?php
// includes/init.php
function includeTrialBanner() {
    global $db;
    
    if (!isset($_SESSION['school_id'])) return '';
    
    if (!isset($GLOBALS['school'])) {
        $stmt = $db->prepare("SELECT * FROM tblschoolinfo WHERE id = :school_id");
        $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
        $stmt->execute();
        $GLOBALS['school'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    ob_start();
    include 'trial_banner.php';
    return ob_get_clean();
}