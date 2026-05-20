<?php
header('Content-Type: application/json');
require_once('../../includes/config.php');

session_start();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$school_id = $_SESSION['school_id'];
$action = $data['action'] ?? '';

if (!$school_id) {
    echo json_encode(['success' => false, 'message' => 'School ID required']);
    exit;
}

try {
    switch($action) {
        case 'add_account':
            $account_name = trim($data['account_name']);
            $abbreviation = trim($data['abbreviation']);
            
            $stmt = $db->prepare("INSERT INTO school_accounts (school_id, account_name, abbreviation) VALUES (?, ?, ?)");
            $stmt->execute([$school_id, $account_name, $abbreviation]);
            
            echo json_encode(['success' => true, 'message' => 'Account added successfully', 'id' => $db->lastInsertId()]);
            break;
            
        case 'delete_account':
            $account_id = $data['account_id'];
            $stmt = $db->prepare("DELETE FROM school_accounts WHERE id = ? AND school_id = ?");
            $stmt->execute([$account_id, $school_id]);
            echo json_encode(['success' => true, 'message' => 'Account deleted successfully']);
            break;
            
        case 'get_accounts':
            $stmt = $db->prepare("SELECT id, account_name, abbreviation FROM school_accounts WHERE school_id = ? AND status = 'active' ORDER BY is_default DESC, account_name");
            $stmt->execute([$school_id]);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'accounts' => $accounts]);
            break;
            
        case 'add_payment_mode':
            $account_id = $data['account_id'] ?: null;
            $mode_name = $data['mode_name'];
            
            $stmt = $db->prepare("INSERT INTO payment_modes (school_id, account_id, mode_name) VALUES (?, ?, ?)");
            $stmt->execute([$school_id, $account_id, $mode_name]);
            echo json_encode(['success' => true, 'message' => 'Payment mode added successfully']);
            break;
            
        case 'delete_payment_mode':
            $mode_id = $data['mode_id'];
            $stmt = $db->prepare("DELETE FROM payment_modes WHERE id = ? AND school_id = ?");
            $stmt->execute([$mode_id, $school_id]);
            echo json_encode(['success' => true, 'message' => 'Payment mode deleted successfully']);
            break;
            
        case 'get_payment_modes':
            $stmt = $db->prepare("
                SELECT pm.id, pm.mode_name, sa.account_name as linked_account 
                FROM payment_modes pm
                LEFT JOIN school_accounts sa ON pm.account_id = sa.id
                WHERE pm.school_id = ? AND pm.is_active = 1
                ORDER BY pm.mode_name
            ");
            $stmt->execute([$school_id]);
            $modes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'payment_modes' => $modes]);
            break;
            
        case 'add_bank_account':
            $account_id = $data['account_id'] ?: null;
            $bank_name = $data['bank_name'];
            $account_number = $data['account_number'];
            $branch = $data['branch'] ?? '';
            
            $stmt = $db->prepare("INSERT INTO school_bank_accounts (school_id, account_id, bank_name, account_number, branch) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$school_id, $account_id, $bank_name, $account_number, $branch]);
            echo json_encode(['success' => true, 'message' => 'Bank account added successfully']);
            break;
            
        case 'delete_bank_account':
            $bank_id = $data['bank_id'];
            $stmt = $db->prepare("DELETE FROM school_bank_accounts WHERE id = ? AND school_id = ?");
            $stmt->execute([$bank_id, $school_id]);
            echo json_encode(['success' => true, 'message' => 'Bank account deleted successfully']);
            break;
            
        case 'get_bank_accounts':
            $stmt = $db->prepare("
                SELECT sba.id, sba.bank_name, sba.account_number, sba.branch, sa.account_name as linked_account
                FROM school_bank_accounts sba
                LEFT JOIN school_accounts sa ON sba.account_id = sa.id
                WHERE sba.school_id = ? AND sba.is_active = 1
                ORDER BY sba.bank_name
            ");
            $stmt->execute([$school_id]);
            $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'bank_accounts' => $banks]);
            break;
            
        case 'save_ipsas':
            $financial_year_start = $data['financial_year_start'];
            $financial_year_end = $data['financial_year_end'];
            $arrears_rolled_up = $data['arrears_rolled_up'] ? 1 : 0;
            $prepayments_distributed = $data['prepayments_distributed'] ? 1 : 0;
            
            $stmt = $db->prepare("
                INSERT INTO ipsas_settings (school_id, financial_year_start, financial_year_end, arrears_rolled_up, prepayments_distributed) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                financial_year_start = VALUES(financial_year_start),
                financial_year_end = VALUES(financial_year_end),
                arrears_rolled_up = VALUES(arrears_rolled_up),
                prepayments_distributed = VALUES(prepayments_distributed)
            ");
            $stmt->execute([$school_id, $financial_year_start, $financial_year_end, $arrears_rolled_up, $prepayments_distributed]);
            echo json_encode(['success' => true, 'message' => 'IPSAS settings saved successfully']);
            break;
            
        case 'get_ipsas':
            $stmt = $db->prepare("SELECT * FROM ipsas_settings WHERE school_id = ?");
            $stmt->execute([$school_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
            
        case 'save_templates':
            $receipt_template = $data['receipt_template'];
            $voucher_template = $data['voucher_template'];
            $receipt_copies = $data['receipt_copies'];
            
            $stmt = $db->prepare("
                INSERT INTO template_settings (school_id, receipt_template, voucher_template, receipt_copies) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                receipt_template = VALUES(receipt_template),
                voucher_template = VALUES(voucher_template),
                receipt_copies = VALUES(receipt_copies)
            ");
            $stmt->execute([$school_id, $receipt_template, $voucher_template, $receipt_copies]);
            echo json_encode(['success' => true, 'message' => 'Template settings saved successfully']);
            break;
            
        case 'get_templates':
            $stmt = $db->prepare("SELECT * FROM template_settings WHERE school_id = ?");
            $stmt->execute([$school_id]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
            
        case 'toggle_multi_store':
            $enabled = $data['enabled'] ? 1 : 0;
            
            $stmt = $db->prepare("
                INSERT INTO store_settings (school_id, multi_store_enabled) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE multi_store_enabled = VALUES(multi_store_enabled)
            ");
            $stmt->execute([$school_id, $enabled]);
            echo json_encode(['success' => true, 'message' => 'Store settings updated']);
            break;
            
        case 'add_store':
            $store_name = $data['store_name'];
            $description = $data['description'] ?? '';
            
            $stmt = $db->prepare("INSERT INTO stores (school_id, store_name, description) VALUES (?, ?, ?)");
            $stmt->execute([$school_id, $store_name, $description]);
            echo json_encode(['success' => true, 'message' => 'Store added successfully', 'id' => $db->lastInsertId()]);
            break;
            
        case 'get_stores':
            $stmt = $db->prepare("SELECT id, store_name, description, is_default FROM stores WHERE school_id = ? AND status = 'active'");
            $stmt->execute([$school_id]);
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'stores' => $stores]);
            break;
            
        case 'get_multi_store_status':
            $stmt = $db->prepare("SELECT multi_store_enabled FROM store_settings WHERE school_id = ?");
            $stmt->execute([$school_id]);
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'enabled' => $setting ? $setting['multi_store_enabled'] : 0]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("Error in save_settings: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>