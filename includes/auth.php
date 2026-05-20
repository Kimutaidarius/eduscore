<?php
class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($email, $password) {
        try {
            $query = "SELECT t.*, r.role_name, s.school_name 
                      FROM tblteachers t 
                      LEFT JOIN roles r ON t.role_id = r.id 
                      LEFT JOIN tblschoolinfo s ON t.school_id = s.id 
                      WHERE t.email = :email AND t.Status = 'Active'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // For demo, using simple password check. In production, use password_verify
                if ($user['password'] === md5($password) || password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['firstname'] . ' ' . $user['lastname'];
                    $_SESSION['role_id'] = $user['role_id'];
                    $_SESSION['role_name'] = $user['role_name'];
                    $_SESSION['school_id'] = $user['school_id'];
                    $_SESSION['school_name'] = $user['school_name'];
                    
                    return true;
                }
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function logout() {
        session_destroy();
        header("Location: login.php");
        exit;
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $query = "SELECT password FROM tblteachers WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (password_verify($currentPassword, $user['password'])) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_ALGO, PASSWORD_OPTIONS);
                    
                    $updateQuery = "UPDATE tblteachers SET password = :password WHERE id = :id";
                    $updateStmt = $this->conn->prepare($updateQuery);
                    $updateStmt->bindParam(":password", $hashedPassword);
                    $updateStmt->bindParam(":id", $userId);
                    
                    return $updateStmt->execute();
                }
            }
            return false;
        } catch(PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }
}
?>