<?php
// /feesystem/api/billing/upgrade_plan.php
header('Content-Type: application/json');
require_once('../../../includes/config.php');
require_once('../../../includes/billing_functions.php');

session_start();
if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $_SESSION['school_id'];
$plan_id = $data['plan_id'] ?? null;
$payment_method = $data['payment_method'] ?? 'mpesa';
$phone_number = $data['phone_number'] ?? null;

if (!$plan_id) {
    echo json_encode(['success' => false, 'message' => 'Plan ID required']);
    exit;
}

// Get the selected plan
$stmt = $conn->prepare("SELECT * FROM saas_plans WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    echo json_encode(['success' => false, 'message' => 'Invalid plan selected']);
    exit;
}

// Get current subscription
$subscription = getOrCreateSubscription($school_id, $conn);
$student_count = getSchoolStudentCount($school_id, $conn);

$conn->begin_transaction();

try {
    // Update subscription with new plan
    $amount = $student_count * $plan['price_per_student'];
    $onboarding_needed = !$subscription['onboarding_paid'];
    
    if ($onboarding_needed) {
        $amount += $plan['onboarding_fee'];
    }
    
    $stmt = $conn->prepare("
        UPDATE saas_subscriptions 
        SET plan_id = ?, module_type = ?, student_count = ?, 
            price_per_student = ?, onboarding_fee = ?
        WHERE school_id = ?
    ");
    $stmt->bind_param("isiddi", $plan_id, $plan['module_type'], $student_count, 
                      $plan['price_per_student'], $plan['onboarding_fee'], $school_id);
    $stmt->execute();
    
    // Generate invoice for the upgrade
    $subscription = getOrCreateSubscription($school_id, $conn);
    $invoice_id = generateInvoice($subscription['id'], $conn, $onboarding_needed);
    
    if (!$invoice_id) {
        throw new Exception("Failed to generate invoice");
    }
    
    // If payment method is provided, process payment
    if ($payment_method == 'mpesa' && $phone_number) {
        // Initiate M-PESA payment
        $payment_result = initiateMpesaPayment($phone_number, $amount, $invoice_id, $conn);
        
        if (!$payment_result['success']) {
            throw new Exception($payment_result['message']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment initiated. Check your phone to complete payment.',
            'invoice_id' => $invoice_id,
            'checkout_request_id' => $payment_result['checkout_request_id']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Plan upgraded. Invoice #' . $invoice_id . ' generated.',
            'invoice_id' => $invoice_id
        ]);
    }
    
    $conn->commit();
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function initiateMpesaPayment($phone, $amount, $invoice_id, $conn) {
    // Implement your M-PESA integration here
    // Return array with 'success' and 'checkout_request_id'
    return ['success' => true, 'checkout_request_id' => 'REQ123456'];
}
?>