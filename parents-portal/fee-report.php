<?php
// fee-report.php - Fee Report Page
$page_title = "Fee Reports";
require_once 'includes/header.php';
require_once '../includes/config.php';

$fee_transactions = [];
$fee_summary = [];

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
            COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) as total_paid,
            COUNT(CASE WHEN transaction_type = 'debit' THEN 1 END) as debit_count,
            COUNT(CASE WHEN transaction_type = 'payment' THEN 1 END) as payment_count
        FROM fee_transactions WHERE student_id = ?
    ");
    $summaryStmt->execute([$selected_student_id]);
    $fee_summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Fee report error: " . $e->getMessage());
}

$fee_balance = ($fee_summary['total_debit'] ?? 0) - ($fee_summary['total_paid'] ?? 0);
?>

<div class="welcome-banner reveal">
    <h1><i class="fas fa-receipt"></i> Fee Reports</h1>
    <p>Complete fee statement for <?php echo htmlspecialchars($student_details['FirstName'] . ' ' . ($student_details['LastName'] ?? '')); ?></p>
</div>

<div class="stats-grid reveal delay-1">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
        <div class="stat-info">
            <h3>Total Fees Charged</h3>
            <div class="stat-value">KSh <?php echo number_format($fee_summary['total_debit'] ?? 0, 0); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3>Total Payments Made</h3>
            <div class="stat-value">KSh <?php echo number_format($fee_summary['total_paid'] ?? 0, 0); ?></div>
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
        <h2><i class="fas fa-list-alt"></i> Detailed Fee Statement</h2>
        <button onclick="window.print()" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.7rem;">
            <i class="fas fa-print"></i> Print Statement
        </button>
    </div>
    <?php if (!empty($fee_transactions)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Type</th>
                    <th>Amount (KSh)</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $running_balance = 0;
                foreach ($fee_transactions as $transaction):
                    if ($transaction['transaction_type'] == 'debit') {
                        $running_balance += $transaction['amount'];
                    } else {
                        $running_balance -= $transaction['amount'];
                    }
                ?>
                    <tr>
                        <td><?php echo date('d M Y', strtotime($transaction['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['description'] ?? ($transaction['vote_head_name'] ?? 'Fee Transaction')); ?></td>
                        <td>
                            <span class="grade-badge" style="background: <?php echo $transaction['transaction_type'] == 'payment' ? '#10b981' : '#ef4444'; ?>">
                                <?php echo ucfirst($transaction['transaction_type']); ?>
                            </span>
                        </td>
                        <td style="color: <?php echo $transaction['transaction_type'] == 'payment' ? '#10b981' : '#ef4444'; ?>">
                            KSh <?php echo number_format($transaction['amount'], 0); ?>
                        </td>
                        <td><?php echo htmlspecialchars($transaction['receipt_no'] ?? $transaction['payment_code'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background: var(--kappel_15); font-weight: bold;">
                    <td colspan="3"><strong>BALANCE</strong></td>
                    <td colspan="2"><strong>KSh <?php echo number_format(max(0, $fee_balance), 0); ?></strong></td>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No fee transactions found.</div>
    <?php endif; ?>
</div>

<style>
    @media print {
        .sidebar, .top-header, .filter-controls, .theme-toggle, .logout-btn, .btn-outline, .welcome-banner {
            display: none !important;
        }
        .main-content { margin-left: 0 !important; }
        .page-content { padding: 0 !important; }
        .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>

<?php require_once 'includes/footer.php'; ?>