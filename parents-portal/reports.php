<?php
// reports.php - Reports Landing Page
$page_title = "Reports";
require_once 'includes/header.php';
?>

<div class="welcome-banner reveal">
    <h1><i class="fas fa-file-alt"></i> Reports Center</h1>
    <p>Access and download your child's academic and fee reports</p>
</div>

<div class="stats-grid reveal delay-1">
    <div class="stat-card" onclick="window.location.href='report-card.php'" style="cursor: pointer;">
        <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
        <div class="stat-info">
            <h3>Report Cards</h3>
            <div class="stat-value">View & Download</div>
        </div>
    </div>
    <div class="stat-card" onclick="window.location.href='fee-report.php'" style="cursor: pointer;">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <h3>Fee Reports</h3>
            <div class="stat-value">View & Download</div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>