<?php
/**
 * Token-Based Authentication System
 * Secure, scalable, production-ready
 */

class Auth {
    private $db;
    private $token_expiry = 86400; // 24 hours in seconds
    
    public function __construct($dbh) {
        $this->db = $dbh;
    }
    
    /**
     * Generate a secure random token
     */
    public function generateToken(): string {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Hash token for storage (prevents token theft from DB)
     */
    public function hashToken(string $token): string {
        return hash('sha256', $token);
    }
    
    /**
     * Create a new authentication token
     */
    public function createToken(int $user_id, ?string $ip_address = null, ?string $user_agent = null): string {
        $token = $this->generateToken();
        $token_hash = $this->hashToken($token);
        $expires_at = date('Y-m-d H:i:s', time() + $this->token_expiry);
        
        // Get IP if not provided
        if (!$ip_address) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        }
        
        // Get User Agent if not provided
        if (!$user_agent) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            // Truncate to prevent overflow
            $user_agent = substr($user_agent, 0, 255);
        }
        
        // Clean up old tokens for this user (keep last 10 for multi-device)
        $cleanup = $this->db->prepare("
            DELETE FROM user_tokens 
            WHERE user_id = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM user_tokens 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ) AS keep_tokens
            )
        ");
        $cleanup->execute([$user_id, $user_id]);
        
        // Insert new token
        $stmt = $this->db->prepare("
            INSERT INTO user_tokens (user_id, token_hash, ip_address, user_agent, expires_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$user_id, $token_hash, $ip_address, $user_agent, $expires_at]);
        
        return $token;
    }
    
    /**
     * Validate a token and return user ID if valid
     */
    public function validateToken(string $token): ?int {
        $token_hash = $this->hashToken($token);
        
        $stmt = $this->db->prepare("
            SELECT user_id, ip_address, user_agent, expires_at
            FROM user_tokens 
            WHERE token_hash = ? 
            AND expires_at > NOW()
            LIMIT 1
        ");
        
        $stmt->execute([$token_hash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        // Optional: IP validation (uncomment for extra security)
        // $current_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        // if ($result['ip_address'] && $current_ip && $result['ip_address'] !== $current_ip) {
        //     return null; // IP changed - invalidate
        // }
        
        // Update last used timestamp
        $update = $this->db->prepare("
            UPDATE user_tokens 
            SET last_used_at = NOW() 
            WHERE token_hash = ?
        ");
        $update->execute([$token_hash]);
        
        return (int)$result['user_id'];
    }
    
    /**
     * Delete a specific token (logout)
     */
    public function revokeToken(string $token): bool {
        $token_hash = $this->hashToken($token);
        
        $stmt = $this->db->prepare("DELETE FROM user_tokens WHERE token_hash = ?");
        return $stmt->execute([$token_hash]);
    }
    
    /**
     * Delete all tokens for a user (logout all devices)
     */
    public function revokeAllUserTokens(int $user_id): bool {
        $stmt = $this->db->prepare("DELETE FROM user_tokens WHERE user_id = ?");
        return $stmt->execute([$user_id]);
    }
    
    /**
     * Clean up expired tokens (run via cron)
     */
    public function cleanupExpiredTokens(): int {
        $stmt = $this->db->prepare("DELETE FROM user_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Set secure authentication cookie
     */
    public function setAuthCookie(string $token, bool $remember = false): void {
        $expiry = $remember ? time() + (86400 * 30) : time() + $this->token_expiry;
        
        setcookie(
            "auth_token",
            $token,
            [
                "expires" => $expiry,
                "path" => "/",
                "domain" => "", // Auto-detect current domain
                "secure" => true,
                "httponly" => true,
                "samesite" => "Lax"
            ]
        );
    }
    
    /**
     * Clear authentication cookie
     */
    public function clearAuthCookie(): void {
        setcookie("auth_token", "", [
            "expires" => time() - 3600,
            "path" => "/",
            "domain" => "",
            "secure" => true,
            "httponly" => true,
            "samesite" => "Lax"
        ]);
    }
    
    /**
     * Get token from cookie
     */
    public function getTokenFromCookie(): ?string {
        return $_COOKIE['auth_token'] ?? null;
    }
    
    /**
     * Get token from Authorization header (for API/mobile)
     */
    public function getTokenFromHeader(): ?string {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Check if user is authenticated (from cookie or header)
     */
    public function check(): ?int {
        // Try cookie first
        $token = $this->getTokenFromCookie();
        
        // Try header if no cookie
        if (!$token) {
            $token = $this->getTokenFromHeader();
        }
        
        if (!$token) {
            return null;
        }
        
        return $this->validateToken($token);
    }
    
    /**
     * Require authentication (redirect if not)
     */
    public function requireAuth(string $redirect_url = "login.php"): int {
        $user_id = $this->check();
        if (!$user_id) {
            header("Location: " . $redirect_url);
            exit;
        }
        return $user_id;
    }
    
    /**
     * Get user data for authenticated user
     */
    public function getUser(int $user_id): ?array {
        $stmt = $this->db->prepare("
            SELECT id, email, phonenumber, school_id, created_at 
            FROM tblteachers 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

// Helper function for easy access
function getAuth($dbh): Auth {
    return new Auth($dbh);
}
?>