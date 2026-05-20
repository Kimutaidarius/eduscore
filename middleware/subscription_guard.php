<?php
/**
 * Subscription Guard Middleware
 * Include this at the top of every protected page
 */

function checkSubscription($conn, $school_id) {
    // Get school info
    $query = "SELECT s.*, sub.expires_at, sub.status as sub_status, sub.auto_renew,
                     inv.id as unpaid_invoice_id, inv.total_amount, inv.due_date
              FROM tblschoolinfo s
              LEFT JOIN subscriptions sub ON s.id = sub.school_id AND sub.status = 'active'
              LEFT JOIN invoices inv ON s.id = inv.school_id AND inv.status = 'UNPAID'
              WHERE s.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    if (!$data) {
        return ['access' => false, 'reason' => 'School not found'];
    }
    
    $now = time();
    
    // Check if subscription exists
    if (!$data['expires_at']) {
        return [
            'access' => false,
            'reason' => 'no_subscription',
            'message' => 'No active subscription found. Please contact support.'
        ];
    }
    
    $expires = strtotime($data['expires_at']);
    
    // Check if expired
    if ($expires < $now) {
        // Check grace period
        if ($data['grace_expires_at']) {
            $grace_expires = strtotime($data['grace_expires_at']);
            if ($grace_expires < $now) {
                return [
                    'access' => false,
                    'reason' => 'grace_expired',
                    'message' => 'Your grace period has expired. System is locked until payment is received.',
                    'invoice' => $data['unpaid_invoice_id'] ? [
                        'id' => $data['unpaid_invoice_id'],
                        'amount' => $data['total_amount'],
                        'due_date' => $data['due_date']
                    ] : null
                ];
            }
        }
        
        return [
            'access' => false,
            'reason' => 'expired',
            'message' => 'Your subscription has expired. Please renew to continue using the system.',
            'grace_days' => $data['grace_expires_at'] ? 
                floor((strtotime($data['grace_expires_at']) - $now) / (60 * 60 * 24)) : 0,
            'invoice' => $data['unpaid_invoice_id'] ? [
                'id' => $data['unpaid_invoice_id'],
                'amount' => $data['total_amount'],
                'due_date' => $data['due_date']
            ] : null
        ];
    }
    
    // Check for unpaid invoices
    if ($data['unpaid_invoice_id']) {
        $due = strtotime($data['due_date']);
        if ($due < $now) {
            // Overdue invoice
            return [
                'access' => false,
                'reason' => 'overdue_invoice',
                'message' => 'You have an overdue invoice. System access is restricted until payment.',
                'invoice' => [
                    'id' => $data['unpaid_invoice_id'],
                    'amount' => $data['total_amount'],
                    'due_date' => $data['due_date']
                ]
            ];
        }
    }
    
    // All good
    return [
        'access' => true,
        'days_remaining' => floor(($expires - $now) / (60 * 60 * 24)),
        'expires_at' => $data['expires_at']
    ];
}

// Usage in protected pages:
/*
require_once 'includes/config.php';
require_once 'middleware/subscription_guard.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$check = checkSubscription($conn, $_SESSION['school_id']);

if (!$check['access']) {
    $_SESSION['lock_reason'] = $check;
    header('Location: system_locked.php');
    exit();
}
*/