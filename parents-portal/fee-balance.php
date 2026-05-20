<?php
// fee-balance.php - Fee Balance Page
$page_title = "Fee Balance";
require_once 'includes/header.php';
require_once '../includes/config.php';

$fee_transactions = [];
$fee_balance = 0;
$total_debit = 0;
$total_paid = 0;

try {
    $feeStmt = $db->prepare("
        SELECT 
            ft.*,
            vh.name as vote_head_name
        FROM fee_transactions ft
        LEFT JOIN vote_heads vh ON ft.vote_head_id = vh.id
        WHERE ft.student_id = ?
        ORDER BY ft.created_at DESC
    ");
    $feeStmt->execute([$selected_student_id]);
    $fee_transactions = $feeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summaryStmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END), 0) as total_debit,
            COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) as total_paid
        FROM fee_transactions WHERE student_id = ?
    ");
    $summaryStmt->execute([$selected_student_id]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    $total_debit = $summary['total_debit'] ?? 0;
    $total_paid = $summary['total_paid'] ?? 0;
    $fee_balance = $total_debit - $total_paid;
    
} catch (PDOException $e) {
    error_log("Fee balance error: " . $e->getMessage());
}
?>

<div class="welcome-banner reveal">
    <h1><i class="fas fa-coins"></i> Fee Balance</h1>
    <p>View and track your child's fee payments and balances</p>
</div>

<div class="stats-grid reveal delay-1">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-plus-circle"></i></div>
        <div class="stat-info">
            <h3>Total Fees</h3>
            <div class="stat-value">KSh <?php echo number_format($total_debit, 0); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3>Total Paid</h3>
            <div class="stat-value">KSh <?php echo number_format($total_paid, 0); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info">
            <h3>Outstanding Balance</h3>
            <div class="stat-value" style="color: <?php echo $fee_balance > 0 ? '#ef4444' : '#10b981'; ?>">
                KSh <?php echo number_format(max(0, $fee_balance), 0); ?>
            </div>
        </div>
    </div>
</div>

<div class="card reveal">
    <div class="card-header">
        <h2><i class="fas fa-receipt"></i> Fee Transaction History</h2>
        <a href="fee-report.php" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.7rem;">Download Report</a>
    </div>
    <?php if (!empty($fee_transactions)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Amount (KSh)</th>
                    <th>Receipt/Reference</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fee_transactions as $transaction): ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($transaction['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['description'] ?? ($transaction['vote_head_name'] ?? 'Fee Transaction')); ?></td>
                        <td>
                            <span class="grade-badge" style="background: <?php echo $transaction['transaction_type'] == 'payment' ? '#10b981' : '#ef4444'; ?>">
                                <?php echo ucfirst($transaction['transaction_type']); ?>
                            </span>
                        </td>
                        <td style="color: <?php echo $transaction['transaction_type'] == 'payment' ? '#10b981' : '#ef4444'; ?>">
                            <?php echo $transaction['transaction_type'] == 'payment' ? '-' : ''; ?> KSh <?php echo number_format($transaction['amount'], 0); ?>
                        </td>
                        <td><?php echo htmlspecialchars($transaction['receipt_no'] ?? $transaction['payment_code'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No fee transactions found.</div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>