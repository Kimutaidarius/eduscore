<?php
/**
 * SMS Gateway Handler
 * Uses TextBelt as the SMS gateway (supports free SMS for testing)
 */

function sendSMS($recipient, $message, $sender_id = 'EduScore') {
    global $pdo;
    
    // Get active gateway settings
    $stmt = $pdo->prepare("SELECT * FROM sms_gateway_settings WHERE is_active = 1 LIMIT 1");
    $stmt->execute();
    $gateway = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$gateway) {
        return ['success' => false, 'error' => 'No active SMS gateway found'];
    }
    
    // Format phone number (ensure it has country code)
    $phone = formatPhoneNumber($recipient);
    
    // TextBelt requires API key for free tier
    // For testing, you can use 'textbelt' as API key (1 free SMS per day)
    $api_key = 'textbelt'; // Replace with your actual API key for production
    
    // Prepare data for TextBelt
    $data = [
        'phone' => $phone,
        'message' => $message,
        'key' => $api_key,
        'sender' => $sender_id // Optional: Some gateways support sender ID
    ];
    
    // Send request to TextBelt
    $ch = curl_init('https://textbelt.com/text');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return ['success' => false, 'error' => 'Gateway connection failed'];
    }
    
    $result = json_decode($response, true);
    
    return [
        'success' => $result['success'] ?? false,
        'message_id' => $result['textId'] ?? null,
        'quota_remaining' => $result['quotaRemaining'] ?? null,
        'error' => $result['error'] ?? null
    ];
}

function formatPhoneNumber($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Add country code if missing (Kenya = 254)
    if (strlen($phone) == 9) {
        $phone = '254' . $phone;
    } elseif (strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
        $phone = '254' . substr($phone, 1);
    }
    
    return $phone;
}

function calculateSMSCount($message) {
    // Standard GSM character set length
    $gsm_chars = 160;
    $unicode_chars = 70;
    
    // Check if message contains Unicode characters
    if (preg_match('/[^\x{20}-\x{7E}]/u', $message)) {
        return ceil(mb_strlen($message) / $unicode_chars);
    }
    
    return ceil(strlen($message) / $gsm_chars);
}
?>