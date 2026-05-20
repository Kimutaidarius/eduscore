<?php
// /feesystem/api/billing/get_plans.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('../../includes/config.php');
require_once('../../includes/billing_functions.php');

session_start();

if (!isset($db)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['school_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$school_id = $_SESSION['school_id'];

try {
    // Get school details - handle missing column gracefully
    $stmt = $db->prepare("SELECT institution_level FROM tblschoolinfo WHERE id = ?");
    $stmt->execute([$school_id]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$school) {
        echo json_encode(['success' => false, 'message' => 'School not found']);
        exit;
    }

    $school_level = ($school['institution_level'] == 'secondary') ? 'secondary' : 'primary';
    
    // Check if school_type column exists, if not, default to 'public'
    $school_type = 'public';
    try {
        $checkColumn = $db->query("SHOW COLUMNS FROM tblschoolinfo LIKE 'school_type'");
        if ($checkColumn->rowCount() > 0) {
            $stmt = $db->prepare("SELECT school_type FROM tblschoolinfo WHERE id = ?");
            $stmt->execute([$school_id]);
            $typeResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $school_type = $typeResult['school_type'] ?? 'public';
        }
    } catch (PDOException $e) {
        // Column doesn't exist, use default
        $school_type = 'public';
    }

    // Check if saas_plans table exists
    $checkTable = $db->query("SHOW TABLES LIKE 'saas_plans'");
    if ($checkTable->rowCount() == 0) {
        // Create tables if they don't exist
        createBillingTables($db);
    }

    // Get plans for this school type
    $stmt = $db->prepare("
        SELECT * FROM saas_plans 
        WHERE school_level = ? AND school_type = ? AND status = 'active'
        ORDER BY module_type, price_per_student
    ");
    $stmt->execute([$school_level, $school_type]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no plans found, return default plans
    if (empty($plans)) {
        $plans = getDefaultPlans($school_level, $school_type);
    }

    // Add features to each plan
    foreach ($plans as &$plan) {
        $plan['features'] = getPlanFeatures($plan);
        $plan['plan_name'] = $plan['name'] ?? ($plan['module_type'] == 'single' ? 'Starter' : 'Professional');
        $plan['price'] = $plan['price_per_student'];
        $plan['max_students'] = 999999;
        $plan['billing_cycle'] = $plan['billing_cycle'] ?? 'monthly';
    }

    echo json_encode(['success' => true, 'plans' => $plans]);
    
} catch (PDOException $e) {
    error_log("PDO Error in get_plans.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in get_plans.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Function to create billing tables if they don't exist
function createBillingTables($db) {
    $sql = "
    CREATE TABLE IF NOT EXISTS `saas_plans` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `school_level` enum('primary','secondary') NOT NULL,
        `school_type` enum('public','private') NOT NULL,
        `module_type` enum('single','both') NOT NULL,
        `price_per_student` decimal(10,2) NOT NULL,
        `billing_cycle` enum('monthly','term','yearly') NOT NULL DEFAULT 'monthly',
        `onboarding_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
        `status` enum('active','inactive') DEFAULT 'active',
        `description` text,
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    
    CREATE TABLE IF NOT EXISTS `saas_subscriptions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `school_id` int(11) NOT NULL,
        `plan_id` int(11) DEFAULT NULL,
        `module_type` enum('single','both') NOT NULL DEFAULT 'single',
        `student_count` int(11) NOT NULL DEFAULT 0,
        `status` enum('trial','active','expired','pending_payment','cancelled') NOT NULL DEFAULT 'trial',
        `trial_ends_at` datetime NOT NULL,
        `current_period_start` datetime DEFAULT NULL,
        `current_period_end` datetime DEFAULT NULL,
        `next_billing_date` datetime DEFAULT NULL,
        `onboarding_paid` tinyint(1) DEFAULT 0,
        `auto_renew` tinyint(1) DEFAULT 1,
        `cancelled_at` datetime DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_school_id` (`school_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    
    CREATE TABLE IF NOT EXISTS `saas_invoices` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `school_id` int(11) NOT NULL,
        `subscription_id` int(11) DEFAULT NULL,
        `invoice_number` varchar(50) NOT NULL,
        `invoice_date` datetime NOT NULL,
        `due_date` datetime NOT NULL,
        `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
        `onboarding_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
        `total_amount` decimal(12,2) NOT NULL,
        `student_count` int(11) NOT NULL,
        `price_per_student` decimal(10,2) NOT NULL,
        `billing_period_start` date DEFAULT NULL,
        `billing_period_end` date DEFAULT NULL,
        `status` enum('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
        `paid_at` datetime DEFAULT NULL,
        `payment_method` varchar(50) DEFAULT NULL,
        `mpesa_code` varchar(100) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `invoice_number` (`invoice_number`),
        KEY `idx_school_id` (`school_id`),
        KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    
    CREATE TABLE IF NOT EXISTS `saas_payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `school_id` int(11) NOT NULL,
        `invoice_id` int(11) DEFAULT NULL,
        `amount` decimal(12,2) NOT NULL,
        `payment_method` enum('mpesa','bank_transfer','card','cash') NOT NULL,
        `mpesa_code` varchar(100) DEFAULT NULL,
        `transaction_id` varchar(100) DEFAULT NULL,
        `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
        `payment_date` datetime DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_invoice_id` (`invoice_id`),
        KEY `idx_school_id` (`school_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    
    -- Insert default plans
    INSERT IGNORE INTO `saas_plans` (`name`, `school_level`, `school_type`, `module_type`, `price_per_student`, `billing_cycle`, `onboarding_fee`, `description`) VALUES
    ('Primary Starter', 'primary', 'public', 'single', 15.00, 'monthly', 2000.00, 'Perfect for small primary schools'),
    ('Primary Professional', 'primary', 'public', 'both', 25.00, 'monthly', 3500.00, 'Complete solution for primary schools'),
    ('Primary Starter', 'primary', 'private', 'single', 20.00, 'monthly', 3000.00, 'Perfect for private primary schools'),
    ('Primary Professional', 'primary', 'private', 'both', 50.00, 'monthly', 8000.00, 'Complete solution for private primary schools'),
    ('Secondary Starter', 'secondary', 'public', 'single', 20.00, 'term', 2500.00, 'Perfect for secondary schools'),
    ('Secondary Professional', 'secondary', 'public', 'both', 35.00, 'term', 4500.00, 'Complete solution for public secondary schools'),
    ('Secondary Starter', 'secondary', 'private', 'single', 40.00, 'term', 6000.00, 'Perfect for private secondary schools'),
    ('Secondary Professional', 'secondary', 'private', 'both', 70.00, 'term', 10000.00, 'Complete solution for private secondary schools');
    ";
    
    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        error_log("Error creating billing tables: " . $e->getMessage());
    }
}
?>