<?php
// demo_expired.php - Demo Session Expired Page
session_start();

// Clear demo session data
unset($_SESSION['demo_name']);
unset($_SESSION['demo_email']);
unset($_SESSION['demo_phone']);
unset($_SESSION['demo_start_time']);
unset($_SESSION['demo_expiry_time']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Session Expired - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --edu-blue: #1e40af;
            --edu-blue-dark: #1e3a8a;
            --edu-yellow: #facc15;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --success-color: #059669;
            --warning-color: #d97706;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
        
        .expired-container {
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        
        .expired-card {
            background: var(--bg-white);
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 20px 60px rgba(30, 64, 175, 0.15);
            border-top: 4px solid var(--edu-yellow);
            position: relative;
            overflow: hidden;
        }
        
        .expired-icon {
            width: 80px;
            height: 80px;
            background: rgba(220, 38, 38, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            border: 3px solid rgba(220, 38, 38, 0.2);
        }
        
        .expired-icon i {
            font-size: 2.5rem;
            color: #dc2626;
        }
        
        .expired-title {
            color: var(--text-dark);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .expired-message {
            color: var(--text-light);
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 1rem;
        }
        
        .features-list {
            background: rgba(30, 64, 175, 0.05);
            border-radius: 12px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
            border: 1px solid rgba(30, 64, 175, 0.1);
        }
        
        .features-list h3 {
            color: var(--edu-blue);
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .features-list ul {
            list-style: none;
            padding-left: 0;
        }
        
        .features-list li {
            padding: 8px 0;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .features-list li i {
            color: var(--success-color);
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .action-btn {
            padding: 14px 28px;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 180px;
        }
        
        .btn-primary {
            background: var(--edu-blue);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--edu-blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(30, 64, 175, 0.25);
        }
        
        .btn-secondary {
            background: rgba(30, 64, 175, 0.1);
            color: var(--edu-blue);
            border: 2px solid rgba(30, 64, 175, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(30, 64, 175, 0.2);
            transform: translateY(-2px);
        }
        
        .demo-again {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        .demo-again a {
            color: var(--edu-blue);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .demo-again a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .expired-card {
                padding: 40px 25px;
            }
            
            .expired-title {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="expired-container">
        <div class="expired-card">
            <div class="expired-icon">
                <i class="fas fa-hourglass-end"></i>
            </div>
            
            <h1 class="expired-title">Demo Session Expired</h1>
            
            <p class="expired-message">
                Your 15-minute demo session has ended. We hope you enjoyed exploring EduScore's features!
            </p>
            
            <div class="features-list">
                <h3>
                    <i class="fas fa-star"></i>
                    What you experienced:
                </h3>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Exam Analysis & Reporting</li>
                    <li><i class="fas fa-check-circle"></i> Fee Management System</li>
                    <li><i class="fas fa-check-circle"></i> Bulk SMS Integration</li>
                    <li><i class="fas fa-check-circle"></i> Exam Generator Tools</li>
                    <li><i class="fas fa-check-circle"></i> Timetable Management</li>
                </ul>
            </div>
            
            <div class="action-buttons">
                <a href="register.php" class="action-btn btn-primary">
                    <i class="fas fa-crown"></i> Get Full Access
                </a>
                <a href="pricing.php" class="action-btn btn-secondary">
                    <i class="fas fa-tag"></i> View Pricing
                </a>
            </div>
            
            <div class="demo-again">
                <p>Want to explore more?</p>
                <a href="demo_login.php">
                    <i class="fas fa-redo"></i> Start Another Demo
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-redirect after 60 seconds
        setTimeout(function() {
            window.location.href = 'demo_login.php';
        }, 60000); // 60 seconds
        
        // Show redirect countdown
        let countdown = 60;
        const countdownEl = document.createElement('div');
        countdownEl.style.cssText = `
            margin-top: 15px;
            font-size: 0.9rem;
            color: #6b7280;
        `;
        document.querySelector('.demo-again').appendChild(countdownEl);
        
        const timer = setInterval(function() {
            countdown--;
            countdownEl.textContent = `Redirecting to demo login in ${countdown} seconds...`;
            
            if (countdown <= 0) {
                clearInterval(timer);
            }
        }, 1000);
    </script>
</body>
</html>