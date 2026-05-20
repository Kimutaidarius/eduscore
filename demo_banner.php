<?php
// Demo banner - shows demo session information
if (isset($_SESSION['demo_mode']) && $_SESSION['demo_mode'] === true):
    $is_expired = isset($_SESSION['demo_expired']) && $_SESSION['demo_expired'] === true;
    $remaining_time = isset($_SESSION['demo_expiry_time']) ? $_SESSION['demo_expiry_time'] - time() : 0;
    $minutes_left = floor($remaining_time / 60);
    $seconds_left = $remaining_time % 60;
?>
<style>
.demo-banner {
    background: linear-gradient(135deg, #f4c430 0%, #f59e0b 100%);
    color: #1e293b;
    padding: 12px 20px;
    text-align: center;
    font-weight: 600;
    position: relative;
    z-index: 999;
    border-bottom: 2px solid #d97706;
    animation: slideDown 0.5s ease;
}

.demo-banner.expired {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border-bottom-color: #991b1b;
}

.demo-banner-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
}

.demo-banner-icon {
    font-size: 1.2rem;
    animation: pulse 2s infinite;
}

.demo-banner-text {
    font-size: 0.95rem;
}

.demo-banner-timer {
    background: rgba(0, 0, 0, 0.1);
    padding: 4px 12px;
    border-radius: 20px;
    font-family: monospace;
    font-size: 0.9rem;
    font-weight: 700;
}

.demo-banner-actions {
    display: flex;
    gap: 10px;
}

.demo-banner-btn {
    background: white;
    border: none;
    padding: 5px 15px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    text-decoration: none;
    color: #1e293b;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.demo-banner-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.demo-banner-btn.register {
    background: #10b981;
    color: white;
}

.demo-banner-btn.close {
    background: #6b7280;
    color: white;
}

@keyframes slideDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}

@media (max-width: 768px) {
    .demo-banner-content {
        flex-direction: column;
        gap: 8px;
    }
    
    .demo-banner-text {
        font-size: 0.85rem;
    }
    
    .demo-banner-actions {
        gap: 8px;
    }
    
    .demo-banner-btn {
        padding: 4px 12px;
        font-size: 0.8rem;
    }
}
</style>

<div class="demo-banner <?php echo $is_expired ? 'expired' : ''; ?>" id="demoBanner">
    <div class="demo-banner-content">
        <div class="demo-banner-icon">
            <i class="fas fa-flask"></i>
        </div>
        <div class="demo-banner-text">
            <?php if ($is_expired): ?>
                <strong>⚠️ Demo Session Expired!</strong> Your demo session has ended. 
                <strong>Register now</strong> to continue using EduScore with full features.
            <?php else: ?>
                <strong>🎯 Demo Mode Active</strong> You're viewing a live demo with sample data. 
                Session expires in: 
                <span class="demo-banner-timer" id="demoTimer">
                    <?php echo sprintf('%02d:%02d', $minutes_left, $seconds_left); ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="demo-banner-actions">
            <?php if ($is_expired): ?>
                <a href="register.php?type=teacher" class="demo-banner-btn register">
                    <i class="fas fa-user-plus"></i> Register Now
                </a>
                <button onclick="closeDemoBanner()" class="demo-banner-btn close">
                    <i class="fas fa-times"></i> Dismiss
                </button>
            <?php else: ?>
                <a href="register.php?type=teacher" class="demo-banner-btn register">
                    <i class="fas fa-rocket"></i> Upgrade to Full Version
                </a>
                <button onclick="extendDemoSession()" class="demo-banner-btn">
                    <i class="fas fa-hourglass-half"></i> Extend Demo (+5 min)
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$is_expired): ?>
<script>
// Demo timer countdown
let demoTimeLeft = <?php echo $remaining_time; ?>;
const timerElement = document.getElementById('demoTimer');

function updateDemoTimer() {
    if (demoTimeLeft <= 0) {
        if (timerElement) {
            timerElement.textContent = 'Expired';
        }
        // Reload to show expired banner
        setTimeout(() => {
            location.reload();
        }, 1000);
        return;
    }
    
    const minutes = Math.floor(demoTimeLeft / 60);
    const seconds = demoTimeLeft % 60;
    if (timerElement) {
        timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
    demoTimeLeft--;
}

function extendDemoSession() {
    fetch('api/extend_demo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            demoTimeLeft += 300; // Add 5 minutes (300 seconds)
            showToast('Demo Extended', 'Demo session extended by 5 minutes!', 'success');
        } else {
            showToast('Error', 'Could not extend demo session', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'Network error while extending session', 'error');
    });
}

function closeDemoBanner() {
    const banner = document.getElementById('demoBanner');
    if (banner) {
        banner.style.display = 'none';
    }
}

function showToast(title, message, type) {
    // You can reuse the toast function from your dashboard
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">${type === 'success' ? '✅' : '❌'}</div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// Start timer
if (demoTimeLeft > 0) {
    setInterval(updateDemoTimer, 1000);
}
</script>
<?php endif; ?>
<?php endif; ?>