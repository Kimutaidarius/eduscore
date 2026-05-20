<?php
// This script is the M-Pesa callback endpoint.
// It receives the payment confirmation from Safaricom.

require_once '../includes/config.php';

// Get the raw POST data from M-Pesa
$callbackData = json_decode(file_get_contents('php://input'), true);

if (!isset($callbackData['Body']['stkCallback']['ResultCode'])) {
    // This is not a valid M-Pesa callback, log and exit
    http_response_code(400);
    exit;
}

$resultCode = $callbackData['Body']['stkCallback']['ResultCode'];
$checkoutRequestId = $callbackData['Body']['stkCallback']['CheckoutRequestID'];

// Check if the transaction was successful (ResultCode 0)
if ($resultCode === 0) {
    $amountPaid = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
    $mpesaReceiptNumber = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
    $phoneNumber = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
    
    // --- Determine the school ID based on the transaction ---
    // You might need to add a CheckoutRequestID to your sms_transactions table
    // when you initiate the push, to be able to map this callback back to a school.
    // For now, let's assume we can retrieve it by some means.
    // Example: fetch school_id from a table based on CheckoutRequestID
    // Let's assume you have a function to get the school ID
    $schoolId = 1; // Replace this with dynamic school ID lookup

    // --- Update the SMS balance ---
    $smsCredits = floor($amountPaid / 10) * 100;
    
    try {
        $dbh->beginTransaction();

        // Update the balance in tblschoolinfo
        $stmtUpdate = $dbh->prepare("UPDATE tblschoolinfo SET sms_balance = sms_balance + :sms_credits WHERE id = :school_id");
        $stmtUpdate->bindParam(':sms_credits', $smsCredits, PDO::PARAM_INT);
        $stmtUpdate->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmtUpdate->execute();

        // Log the successful transaction in sms_transactions table
        $stmtLog = $dbh->prepare("INSERT INTO sms_transactions (school_id, amount, m_pesa_transaction_id) VALUES (:school_id, :amount, :m_pesa_id)");
        $stmtLog->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmtLog->bindParam(':amount', $amountPaid, PDO::PARAM_INT);
        $stmtLog->bindParam(':m_pesa_id', $mpesaReceiptNumber, PDO::PARAM_STR);
        $stmtLog->execute();
        
        $dbh->commit();
    } catch (PDOException $e) {
        $dbh->rollBack();
        // Log the error to a file for debugging
        error_log("Failed to update SMS balance from M-Pesa callback: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    // Transaction failed. Log the error for review.
    error_log("M-Pesa transaction failed for CheckoutRequestID: $checkoutRequestId. Result: " . $callbackData['Body']['stkCallback']['ResultDesc']);
}

// M-Pesa requires a specific response format
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
?><?php
// This script is the M-Pesa callback endpoint.
// It receives the payment confirmation from Safaricom.

require_once '../includes/config.php';

// Get the raw POST data from M-Pesa
$callbackData = json_decode(file_get_contents('php://input'), true);

if (!isset($callbackData['Body']['stkCallback']['ResultCode'])) {
    // This is not a valid M-Pesa callback, log and exit
    http_response_code(400);
    exit;
}

$resultCode = $callbackData['Body']['stkCallback']['ResultCode'];
$checkoutRequestId = $callbackData['Body']['stkCallback']['CheckoutRequestID'];

// Check if the transaction was successful (ResultCode 0)
if ($resultCode === 0) {
    $amountPaid = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
    $mpesaReceiptNumber = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
    $phoneNumber = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];
    
    // --- Determine the school ID based on the transaction ---
    // You might need to add a CheckoutRequestID to your sms_transactions table
    // when you initiate the push, to be able to map this callback back to a school.
    // For now, let's assume we can retrieve it by some means.
    // Example: fetch school_id from a table based on CheckoutRequestID
    // Let's assume you have a function to get the school ID
    $schoolId = 1; // Replace this with dynamic school ID lookup

    // --- Update the SMS balance ---
    $smsCredits = floor($amountPaid / 10) * 100;
    
    try {
        $dbh->beginTransaction();

        // Update the balance in tblschoolinfo
        $stmtUpdate = $dbh->prepare("UPDATE tblschoolinfo SET sms_balance = sms_balance + :sms_credits WHERE id = :school_id");
        $stmtUpdate->bindParam(':sms_credits', $smsCredits, PDO::PARAM_INT);
        $stmtUpdate->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmtUpdate->execute();

        // Log the successful transaction in sms_transactions table
        $stmtLog = $dbh->prepare("INSERT INTO sms_transactions (school_id, amount, m_pesa_transaction_id) VALUES (:school_id, :amount, :m_pesa_id)");
        $stmtLog->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmtLog->bindParam(':amount', $amountPaid, PDO::PARAM_INT);
        $stmtLog->bindParam(':m_pesa_id', $mpesaReceiptNumber, PDO::PARAM_STR);
        $stmtLog->execute();
        
        $dbh->commit();
    } catch (PDOException $e) {
        $dbh->rollBack();
        // Log the error to a file for debugging
        error_log("Failed to update SMS balance from M-Pesa callback: " . $e->getMessage());
        http_response_code(500);
    }
} else {
    // Transaction failed. Log the error for review.
    error_log("M-Pesa transaction failed for CheckoutRequestID: $checkoutRequestId. Result: " . $callbackData['Body']['stkCallback']['ResultDesc']);
}

// M-Pesa requires a specific response format
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
?>