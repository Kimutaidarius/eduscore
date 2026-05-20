<?php
// includes/SMSApiGenerator.php
class SMSApiGenerator {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function generateApiKey($school_id, $created_by = null) {
        // Generate unique API key and secret
        $api_key = 'ES_' . bin2hex(random_bytes(16));
        $api_secret = bin2hex(random_bytes(32));
        
        // Set expiry date (1 year from now)
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));
        
        $query = "INSERT INTO sms_api_credentials 
                  (school_id, api_key, api_secret, expires_at, created_by, environment) 
                  VALUES (?, ?, ?, ?, ?, 'production')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("isssi", $school_id, $api_key, $api_secret, $expires_at, $created_by);
        
        if ($stmt->execute()) {
            // Initialize credits if not exists
            $this->initializeCredits($school_id);
            
            return [
                'success' => true,
                'api_key' => $api_key,
                'api_secret' => $api_secret,
                'expires_at' => $expires_at
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to generate API key'];
    }
    
    public function revokeApiKey($api_key, $revoked_by) {
        $query = "UPDATE sms_api_credentials 
                  SET status = 'revoked', revoked_at = NOW(), revoked_by = ? 
                  WHERE api_key = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("is", $revoked_by, $api_key);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'API key revoked successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to revoke API key'];
    }
    
    private function initializeCredits($school_id) {
        $check = "SELECT id FROM sms_credits WHERE school_id = ?";
        $stmt = $this->conn->prepare($check);
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $insert = "INSERT INTO sms_credits (school_id) VALUES (?)";
            $stmt = $this->conn->prepare($insert);
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
        }
    }
    
    public function validateApiKey($api_key, $api_secret) {
        $query = "SELECT * FROM sms_api_credentials 
                  WHERE api_key = ? AND api_secret = ? 
                  AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ss", $api_key, $api_secret);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            // Update last used
            $update = "UPDATE sms_api_credentials SET last_used = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($update);
            $stmt->bind_param("i", $data['id']);
            $stmt->execute();
            
            return ['valid' => true, 'school_id' => $data['school_id'], 'api_key_id' => $data['id']];
        }
        
        return ['valid' => false, 'message' => 'Invalid API credentials'];
    }
}