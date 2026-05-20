<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_POST['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = intval($_POST['school_id']);
$phone = $_POST['phone'] ?? '';
$amount = floatval($_POST['amount'] ?? 0);
$invoice_id = intval($_POST['invoice_id'] ?? 0);

if (empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number is required']);
    exit();
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount']);
    exit();
}

// Format phone number
$phone = preg_replace('/^0/', '254', $phone);
$phone = preg_replace('/^\+/', '', $phone);

// Here you would integrate with Safaricom M-PESA API
// For now, we'll simulate a successful STK push initiation

// Create a transaction record
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$transaction_id = 'STK' . time() . rand(1000, 9999);
$stmt = $conn->prepare("
    INSERT INTO tbltransactions (school_id, invoice_id, amount, phone, transaction_id, status, created_at) 
    VALUES (?, ?, ?, ?, ?, 'pending', NOW())
");
$stmt->bind_param("iidss", $school_id, $invoice_id, $amount, $phone, $transaction_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true, 
        'message' => 'STK push initiated successfully',
        'transaction_id' => $transaction_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to initiate STK push']);
}

$stmt->close();
$conn->close();
?>