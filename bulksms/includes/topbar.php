<?php
// includes/topbar.php
// This file contains the top navigation bar
// Make sure to include this after session_start() and before any HTML output

// Get user balance if not already available
if (!isset($user) && isset($_SESSION['user_id'])) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

// Get SMS balance directly from database
$sms_balance = isset($user['sms_balance']) ? (int)$user['sms_balance'] : 0;
?>
<!-- Topbar -->
<div class="topbar">
    <button class="btn btn-outline-light d-md-none me-2" id="menuToggle">
        <i class="bi bi-list"></i>
    </button>
    <div class="d-flex justify-content-between align-items-center w-100">
        <div>
            <h5 class="mb-0 text-white">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>!</h5>
        </div>
        <div class="d-flex align-items-center">
            <?php if (isset($user)): ?>
                <!-- Balance Badge - Shows SMS Credits -->
                <div class="balance-badge me-3">
                    <i class="bi bi-envelope-paper"></i>
                    <span id="topbarBalance"><?php echo number_format($sms_balance); ?></span>
                    <small>SMS Credits</small>
                </div>
                
                <!-- Top Up Button -->
                <a href="topup.php" class="btn btn-sm btn-success me-3" id="topupBtn">
                    <i class="bi bi-plus-circle"></i> Top Up
                </a>
                
                <span class="me-3 text-white-50"><i class="bi bi-envelope"></i> <?php echo $user['email'] ?? ''; ?></span>
                <span class="me-3 text-white-50"><i class="bi bi-phone"></i> <?php echo $user['phone'] ?? 'Not set'; ?></span>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?php echo $_SESSION['username'] ?? 'User'; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.topbar {
    background-color: #1e3a8a; /* Solid dark blue */
    height: 70px;
    position: fixed;
    top: 0;
    left: 250px;
    right: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    padding: 0 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-bottom: 1px solid #2e4a9a;
}

.topbar h5 {
    color: #ffffff;
    font-weight: 500;
    font-size: 1.1rem;
}

.topbar .btn-outline-light {
    border-color: #ffffff;
    color: #ffffff;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
    background-color: transparent;
}

.topbar .btn-outline-light:hover {
    background-color: rgba(255,255,255,0.1);
    border-color: #ffffff;
    color: #ffffff;
}

/* Balance Badge - Updated for SMS credits */
.balance-badge {
    background-color: #2e4a9a; /* Lighter blue */
    border: 1px solid #3e5aaa;
    border-radius: 30px;
    padding: 6px 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: white;
    font-size: 14px;
    font-weight: 500;
}

.balance-badge i {
    font-size: 16px;
    color: #ffd700; /* Gold icon */
}

.balance-badge span {
    font-weight: 700;
    font-size: 16px;
    color: #ffd700;
}

.balance-badge small {
    font-size: 11px;
    opacity: 0.9;
    font-weight: 400;
    color: rgba(255,255,255,0.9);
}

/* Top Up Button */
.btn-success {
    background-color: #10b981; /* Solid green */
    border: 1px solid #0ea271;
    padding: 6px 18px;
    border-radius: 30px;
    font-weight: 500;
    font-size: 14px;
    color: white;
    transition: all 0.2s ease;
}

.btn-success:hover {
    background-color: #0ea271; /* Darker green on hover */
    border-color: #0c8a5c;
    color: white;
    transform: translateY(-1px);
}

.btn-success i {
    margin-right: 5px;
    font-size: 14px;
}

.topbar .text-white-50 {
    color: rgba(255,255,255,0.7) !important;
    font-size: 14px;
    font-weight: 400;
}

.topbar .text-white-50 i {
    margin-right: 5px;
}

.dropdown-menu {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-top: 10px;
    padding: 8px 0;
    min-width: 200px;
}

.dropdown-item {
    color: #333333;
    padding: 8px 20px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.dropdown-item:hover {
    background-color: #f5f5f5;
    color: #1e3a8a;
}

.dropdown-item i {
    margin-right: 10px;
    width: 18px;
    color: #666666;
    font-size: 16px;
}

.dropdown-item:hover i {
    color: #1e3a8a;
}

.dropdown-divider {
    margin: 5px 0;
    border-top: 1px solid #e0e0e0;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .topbar {
        left: 0;
        height: 60px;
        padding: 0 15px;
    }
    
    .topbar h5 {
        font-size: 1rem;
    }
    
    .balance-badge {
        display: none !important;
    }
    
    #topupBtn {
        display: none !important;
    }
    
    .topbar .text-white-50 {
        display: none !important;
    }
    
    .topbar .dropdown {
        margin-left: auto;
    }
}

/* Tablet Responsive */
@media (min-width: 769px) and (max-width: 1024px) {
    .topbar .text-white-50 {
        display: none !important;
    }
    
    .balance-badge small {
        display: none;
    }
}

/* Animation for balance update */
@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.03);
    }
    100% {
        transform: scale(1);
    }
}

.balance-updated {
    animation: pulse 0.3s ease;
}
</style>

<script>
// Add balance update animation when balance changes
document.addEventListener('DOMContentLoaded', function() {
    const balanceSpan = document.getElementById('topbarBalance');
    
    // Function to update balance display via AJAX
    window.updateTopbarBalance = function(newBalance) {
        if (balanceSpan) {
            // Add animation class to parent
            const badge = document.querySelector('.balance-badge');
            if (badge) {
                badge.classList.add('balance-updated');
                setTimeout(() => {
                    badge.classList.remove('balance-updated');
                }, 300);
            }
            
            // Update the balance text
            balanceSpan.textContent = newBalance.toLocaleString();
        }
    };
    
    // Function to refresh balance from server
    window.refreshTopbarBalance = function() {
        fetch('../ajax/get_balance.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTopbarBalance(data.raw_balance);
                }
            })
            .catch(error => console.error('Error refreshing balance:', error));
    };
});
</script>