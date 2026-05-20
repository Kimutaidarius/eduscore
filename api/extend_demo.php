<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['demo_mode']) && $_SESSION['demo_mode'] === true) {
    if (isset($_SESSION['demo_expiry_time'])) {
        // Extend by 5 minutes
        $_SESSION['demo_expiry_time'] = time() + (5 * 60);
        echo json_encode(['success' => true, 'message' => 'Demo extended by 5 minutes']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No active demo session']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Not in demo mode']);
}
?>