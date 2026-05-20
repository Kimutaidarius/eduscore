<?php
require_once 'config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $email = sanitize($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);
            
            // In a real application, send email here
            // For demo, we'll show the reset link
            $reset_link = APP_URL . "/reset-password.php?token=" . $token;
            
            $success = "Password reset link has been sent to your email.<br>";
            $success .= "<small>Demo link: <a href='$reset_link'>$reset_link</a></small>";
        } else {
            // Don't reveal that email doesn't exist
            $success = "If the email exists, a reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        .container {
            max-width: 400px;
            margin: 100px auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: #e3f2fd;
            color: #333;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
            border-bottom: none;
        }
        .btn-primary {
            background-color: #e3f2fd;
            border-color: #b8d9f5;
            color: #333;
        }
        .btn-primary:hover {
            background-color: #d0e8ff;
            border-color: #a8c9f0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold" style="color: #333;"><?php echo APP_NAME; ?></h2>
            <p class="text-muted">Reset your password</p>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-key me-2"></i>Forgot Password</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                </form>
            </div>
            <div class="card-footer bg-white text-center py-3">
                <a href="login.php" style="color: #333;">Back to Login</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>