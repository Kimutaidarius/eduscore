<?php
header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/MpesaApi.php';

session_start();

$data = json_decode(file_get_contents('php://input'), true);
$phoneNumber = $data['phoneNumber'] ?? null;
$amount = $data['amount'] ?? null;

if (empty($phoneNumber) || empty($amount)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing phone number or amount.']);
    exit;
}

// Instantiate the M-Pesa API class
$mpesaApi = new MpesaApi();
$response = $mpesaApi->stkPush($phoneNumber, $amount);

echo json_encode($response);
?>