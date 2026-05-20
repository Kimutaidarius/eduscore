<?php
session_start();
require_once 'config/config.php';

echo "<h2>Trial Banner Debug</h2>";
echo "<pre>";

// Check session
echo "Session Variables:\n";
print_r($_SESSION);

// Check school data
if (isset($_SESSION['school_id'])) {
    $stmt = $db->prepare("SELECT id, school_name, is_activated, created_at FROM tblschoolinfo WHERE id = :school_id");
    $stmt->bindParam(":school_id", $_SESSION['school_id'], PDO::PARAM_INT);
    $stmt->execute();
    $school = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\nSchool Data:\n";
    print_r($school);
    
    if ($school) {
        $trialDays = 14;
        $createdAt = strtotime($school['created_at']);
        $trialEnds = $createdAt + ($trialDays * 86400);
        $now = time();
        $remainingSeconds = $trialEnds - $now;
        $trialExpired = $now > $trialEnds;
        
        echo "\nTrial Calculation:\n";
        echo "Created At: " . date('Y-m-d H:i:s', $createdAt) . "\n";
        echo "Trial Ends: " . date('Y-m-d H:i:s', $trialEnds) . "\n";
        echo "Now: " . date('Y-m-d H:i:s', $now) . "\n";
        echo "Remaining: " . gmdate("d H:i:s", $remainingSeconds) . "\n";
        echo "Is Activated: " . ($school['is_activated'] ? 'Yes' : 'No') . "\n";
        echo "Trial Expired: " . ($trialExpired ? 'Yes' : 'No') . "\n";
        
        echo "\nShould show banner? ";
        if (!$school['is_activated'] && !$trialExpired) {
            echo "YES (Active trial)\n";
        } elseif (!$school['is_activated'] && $trialExpired) {
            echo "YES (Expired trial)\n";
        } else {
            echo "NO (Activated or no trial)\n";
        }
    }
}

echo "</pre>";
?>