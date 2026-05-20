<?php
session_start();
require_once '../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_id']) || !isset($_GET['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$school_id = intval($_GET['school_id']);

// Use PDO connection from config
global $dbh;

try {
    // Get school's information from tblschoolinfo including created_at, is_activated, and product_type
    $stmt = $dbh->prepare("
        SELECT product_type, is_activated, created_at, school_name 
        FROM tblschoolinfo 
        WHERE id = ?
    ");
    $stmt->execute([$school_id]);
    $school = $stmt->fetch();
    
    if (!$school) {
        echo json_encode(['success' => false, 'message' => 'School not found']);
        exit();
    }
    
    $product_type = $school['product_type'] ?? 'Exam Analysis';
    $is_activated = intval($school['is_activated'] ?? 0);
    $created_at = $school['created_at'] ?? null;
    $school_name = $school['school_name'] ?? '';
    
    // Calculate how long the school has been registered (in days)
    $days_registered = 0;
    if ($created_at) {
        $created_date = new DateTime($created_at);
        $now = new DateTime();
        $interval = $now->diff($created_date);
        $days_registered = $interval->days;
    }
    
    // Define the two main modules
    $modules = [];
    
    // MODULE 1: Exam Analysis System
    // Always active regardless of any conditions
    $modules[] = [
        'id' => 1,
        'name' => 'Exam Analysis System',
        'key' => 'exam_analysis',
        'icon' => 'fas fa-chart-line',
        'description' => 'Comprehensive exam analysis and performance tracking',
        'status' => 'active', // Always active for all schools
        'is_selected' => true,
        'enabled_at' => $created_at, // Show when the school was created
        'activation_source' => 'core_module'
    ];
    
    // MODULE 2: Fee Management System
    // Active only if:
    // 1. School is activated (is_activated = 1) AND
    // 2. Product type includes "Exam Analysis" OR school has been registered for at least 30 days
    $fee_management_active = false;
    $fee_activation_reason = '';
    
    // Check conditions for Fee Management activation
    if ($is_activated == 1) {
        // School is activated - this is the primary condition
        $fee_management_active = true;
        $fee_activation_reason = 'school_activated';
    } elseif (stripos($product_type, 'exam analysis') !== false) {
        // Product type includes Exam Analysis
        $fee_management_active = true;
        $fee_activation_reason = 'product_type';
    } elseif ($days_registered >= 30) {
        // School has been registered for at least 30 days (trial period ended)
        $fee_management_active = true;
        $fee_activation_reason = 'trial_ended';
    }
    
    // You can also add logic for specific product types
    if (stripos($product_type, 'premium') !== false || stripos($product_type, 'standard') !== false) {
        $fee_management_active = true;
        $fee_activation_reason = 'premium_product';
    }
    
    $modules[] = [
        'id' => 2,
        'name' => 'Fee Management System',
        'key' => 'fee_management',
        'icon' => 'fas fa-coins',
        'description' => 'Manage student fees, payments, and invoices',
        'status' => $fee_management_active ? 'active' : 'inactive',
        'is_selected' => $fee_management_active,
        'enabled_at' => $fee_management_active ? $created_at : null,
        'activation_reason' => $fee_activation_reason,
        'conditions' => [
            'is_activated' => $is_activated,
            'product_type' => $product_type,
            'days_registered' => $days_registered,
            'trial_ended' => ($days_registered >= 30)
        ]
    ];
    
    // Optional: You can add more modules based on product_type
    if (stripos($product_type, 'premium') !== false) {
        // Add premium-only modules here if needed
        $modules[] = [
            'id' => 3,
            'name' => 'Advanced Analytics',
            'key' => 'advanced_analytics',
            'icon' => 'fas fa-chart-pie',
            'description' => 'Advanced data analytics and reporting',
            'status' => 'active',
            'is_selected' => true,
            'enabled_at' => $created_at,
            'activation_reason' => 'premium_feature'
        ];
    }
    
    // Count active modules
    $active_modules = array_filter($modules, function($m) { 
        return $m['status'] === 'active'; 
    });
    
    $response = [
        'success' => true,
        'data' => [
            'modules' => $modules,
            'school_info' => [
                'name' => $school_name,
                'product_type' => $product_type,
                'is_activated' => $is_activated,
                'created_at' => $created_at,
                'days_registered' => $days_registered,
                'trial_ended' => ($days_registered >= 30)
            ],
            'summary' => [
                'total_modules' => count($modules),
                'active_modules' => count($active_modules),
                'inactive_modules' => count($modules) - count($active_modules)
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Error in get_modules_data: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred'
    ]);
}