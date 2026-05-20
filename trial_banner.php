<?php
// trial_banner.php

require_once __DIR__ . '/includes/config.php';

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) return; // no user logged in

try {
    // Fetch school info
    $stmt = $dbh->prepare("
        SELECT created_at, activation_status, is_activated, grace_expires_at 
        FROM tblschoolinfo 
        WHERE idPrimary = ?
    ");
    $stmt->execute([$school_id]);
    $school = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$school) return; // user not found

    $createdAt = strtotime($school['created_at']);
    $activationStatus = $school['activation_status']; // pending, activated, expired
    $isActivated = (int)$school['is_activated'];
    $graceExpiresAt = $school['grace_expires_at'] ? strtotime($school['grace_expires_at']) : null;

    $now = time();
    $trialDuration = 14 * 24 * 60 * 60; // 14 days in seconds

    // Set grace period if not yet set
    if ($activationStatus === 'pending' && !$graceExpiresAt) {
        $graceExpiresAt = $createdAt + $trialDuration;
        $stmt = $dbh->prepare("UPDATE tblschoolinfo SET grace_expires_at = FROM_UNIXTIME(?) WHERE idPrimary = ?");
        $stmt->execute([$graceExpiresAt, $school_id]);
    }

    // Determine trial state
    $trialActive = $activationStatus === 'pending' && $now < $graceExpiresAt;
    $trialExpired = $activationStatus === 'pending' && $now >= $graceExpiresAt;
    $fullAccess = $activationStatus === 'activated' || $isActivated;

    if ($trialActive) {
        $remainingSeconds = $graceExpiresAt - $now;
        $minutes = floor($remainingSeconds / 60);
        $seconds = $remainingSeconds % 60;
    }

} catch (Exception $e) {
    error_log("Trial banner error: " . $e->getMessage());
    return;
}
?>
<link rel="stylesheet" href="/assets/css/banners.css">
<?php if ($trialActive): ?>
<div id="trialBanner" class="demo-session-banner">
    <div class="banner-content">
        <div class="banner-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="banner-text">
            <span class="banner-title">Free 14-Day Trial Active</span>
            <span class="banner-subtitle">
                Welcome <?php echo htmlspecialchars($_SESSION['school_name'] ?? 'User'); ?>
            </span>
        </div>
        <div class="countdown-container">
            <span class="countdown-label">Expires in:</span>
            <span id="trialCountdown" data-expiry="<?php echo $graceExpiresAt; ?>" class="countdown-timer">
                <?php printf('%02d:%02d', $minutes, $seconds); ?>
            </span>
        </div>
        <div class="banner-actions">
            <a href="register.php" class="banner-btn upgrade-btn">
                <i class="fas fa-crown"></i> Subscribe Now
            </a>
        </div>
    </div>
    <div class="progress-bar">
        <div id="trialProgress" class="progress-fill" style="width: <?php echo min(100, ($remainingSeconds / $trialDuration) * 100); ?>%;"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const countdownEl = document.getElementById('trialCountdown');
    const progressEl = document.getElementById('trialProgress');
    if (!countdownEl) return;

    const expiryTime = parseInt(countdownEl.dataset.expiry) * 1000;
    const totalSeconds = 14 * 24 * 60 * 60;

    function updateCountdown() {
        const now = Date.now();
        const remaining = expiryTime - now;

        if (remaining <= 0) {
            countdownEl.textContent = '00:00';
            if (progressEl) progressEl.style.width = '0%';
            setTimeout(() => { window.location.reload(); }, 1000);
            return;
        }

        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        countdownEl.textContent = `${minutes.toString().padStart(2,'0')}:${seconds.toString().padStart(2,'0')}`;

        if (progressEl) {
            const progressPercentage = (remaining / (totalSeconds * 1000)) * 100;
            progressEl.style.width = Math.max(0, Math.min(100, progressPercentage)) + '%';
        }
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
});
</script>
<?php endif; ?>
