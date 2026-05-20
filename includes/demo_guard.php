if (!empty($_SESSION['demo_expiry_time']) && time() >= $_SESSION['demo_expiry_time']) {
    http_response_code(403);
    echo json_encode([
        'error' => 'DEMO_EXPIRED',
        'message' => 'Your demo session has expired'
    ]);
    exit;
}
