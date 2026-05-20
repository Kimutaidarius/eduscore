<?php
// demo_extend.php - Extend Demo Session
require_once 'demo_guard.php';

$max_extends = 2; // Maximum number of extensions
$extend_minutes = 5; // Minutes to extend

// Check if user can extend
$extend_count = $_SESSION['demo_extend_count'] ?? 0;
$can_extend = $extend_count < $max_extends;

// Handle extension request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_extend) {
    $_SESSION['demo_expiry_time'] += ($extend_minutes * 60);
    $_SESSION['demo_extend_count'] = $extend_count + 1;
    $_SESSION['demo_last_extend'] = time();
    
    // Redirect back to dashboard
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extend Demo - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --edu-blue: #1e40af;
            --edu-yellow: #facc15;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .extend-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(30, 64, 175, 0.15);
            border-top: 4px solid var(--edu-yellow);
        }
        
        .extend-icon {
            font-size: 3rem;
            color: var(--edu-yellow);
            margin-bottom: 20px;
        }
        
        .extend-btn {
            background: var(--edu-blue);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .extend-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="extend-card">
        <div class="extend-icon">
            <i class="fas fa-clock"></i>
        </div>
        
        <h2>Extend Your Demo</h2>
        
        <?php if ($can_extend): ?>
            <p>Extend your demo session by <?php echo $extend_minutes; ?> minutes.</p>
            <p>Extensions used: <?php echo $extend_count; ?> of <?php echo $max_extends; ?></p>
            
            <form method="POST">
                <button type="submit" class="extend-btn">
                    <i class="fas fa-plus-circle"></i> Add <?php echo $extend_minutes; ?> Minutes
                </button>
            </form>
        <?php else: ?>
            <p>You've used all available extensions.</p>
            <p>Please upgrade to a full account for unlimited access.</p>
            <a href="register.php" class="extend-btn">
                <i class="fas fa-crown"></i> Upgrade Now
            </a>
        <?php endif; ?>
        
        <p style="margin-top: 20px;">
            <a href="dashboard.php">Back to Dashboard</a>
        </p>
    </div>
</body>
</html>