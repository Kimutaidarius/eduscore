<?php
// report-card.php - Report Cards Page
$page_title = "Report Cards";
require_once 'includes/header.php';
require_once '../includes/config.php';

$report_cards = [];

try {
    $reportStmt = $db->prepare("
        SELECT rc.*, e.examname, t.term_name, rc.created_at
        FROM report_cards rc
        LEFT JOIN tblexam e ON rc.exam_id = e.id
        LEFT JOIN tblterms t ON rc.term_id = t.id
        WHERE rc.student_id = ? AND rc.school_id = ?
        ORDER BY rc.created_at DESC
    ");
    $reportStmt->execute([$selected_student_id, $school_id]);
    $report_cards = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Report cards error: " . $e->getMessage());
}
?>

<div class="welcome-banner reveal">
    <h1><i class="fas fa-graduation-cap"></i> Report Cards</h1>
    <p>View and download your child's academic report cards</p>
</div>

<div class="card reveal">
    <div class="card-header">
        <h2><i class="fas fa-file-pdf"></i> Available Report Cards</h2>
    </div>
    <?php if (!empty($report_cards)): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Exam</th>
                    <th>Term</th>
                    <th>Academic Year</th>
                    <th>Mean Score</th>
                    <th>Grade</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_cards as $report): 
                    $grade_info = getGradeFromPercentage($report['mean_score'] ?? 0, $grading_scales);
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($report['examname'] ?? 'Assessment'); ?></td>
                        <td><?php echo htmlspecialchars($report['term_name'] ?? 'Term'); ?></td>
                        <td><?php echo $report['academic_year']; ?></td>
                        <td><?php echo round($report['mean_score'] ?? 0, 1); ?>%</td>
                        <td><span class="grade-badge" style="background: <?php echo $grade_info['color']; ?>"><?php echo $grade_info['grade']; ?></span></td>
                        <td><?php echo date('d M Y', strtotime($report['created_at'])); ?></td>
                        <td>
                            <?php if (!empty($report['pdf_url'])): ?>
                                <a href="<?php echo $report['pdf_url']; ?>" target="_blank" class="btn btn-outline" style="padding: 4px 10px; font-size: 0.7rem;">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            <?php else: ?>
                                <span class="no-data" style="padding: 0;">Not available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No report cards available. Please check back after exams are processed.</div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>