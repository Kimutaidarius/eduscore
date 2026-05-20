<?php
// performance.php - Full Performance Analysis Page
$page_title = "Performance Analysis";
require_once 'includes/header.php';

$school_id = $student_details['school_id'] ?? 0;
if ($school_id == 0 && $selected_student_id > 0) {
    $schoolStmt = $db->prepare("SELECT school_id FROM tblstudents WHERE id = ?");
    $schoolStmt->execute([$selected_student_id]);
    $school_result = $schoolStmt->fetch(PDO::FETCH_ASSOC);
    $school_id = $school_result['school_id'] ?? 0;
}

$exam_results = [];
$subject_history = [];
$grading_scales = [];
$subject_list = [];
$current_results = null;

try {
    $class_id = $student_details['class_id'] ?? 0;
    if ($class_id) {
        $gradingStmt = $db->prepare("
            SELECT lower_limit, upper_limit, grade, points, remarks, grade_alias 
            FROM tblgradingscale 
            WHERE class_id = ? AND school_id = ? 
            ORDER BY lower_limit ASC
        ");
        $gradingStmt->execute([$class_id, $school_id]);
        $grading_scales = $gradingStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($grading_scales)) {
            $gradingStmt = $db->prepare("
                SELECT lower_limit, upper_limit, grade, points, remarks, grade_alias 
                FROM tblgradingscale 
                WHERE class_id = 0 AND is_default = 1 
                ORDER BY lower_limit ASC
            ");
            $gradingStmt->execute();
            $grading_scales = $gradingStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // Get subject details
    $subjectsStmt = $db->prepare("
        SELECT id, subject_name, alias 
        FROM tblsubjects 
        WHERE school_id = ?
    ");
    $subjectsStmt->execute([$school_id]);
    $subject_list = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $subject_id_to_name = [];
    foreach ($subject_list as $subj) {
        $subject_id_to_name[$subj['id']] = $subj['subject_name'];
    }
    
    // Get current results
    if ($selected_exam_id > 0) {
        $meritStmt = $db->prepare("
            SELECT m.*, e.examname, t.term_name 
            FROM tblmeritlist m 
            LEFT JOIN tblexam e ON m.exam_id = e.id 
            LEFT JOIN tblterms t ON m.term_id = t.id 
            WHERE m.student_id = ? AND m.exam_id = ? 
            ORDER BY m.id DESC LIMIT 1
        ");
        $meritStmt->execute([$selected_student_id, $selected_exam_id]);
    } else {
        $meritStmt = $db->prepare("
            SELECT m.*, e.examname, t.term_name 
            FROM tblmeritlist m 
            LEFT JOIN tblexam e ON m.exam_id = e.id 
            LEFT JOIN tblterms t ON m.term_id = t.id 
            WHERE m.student_id = ? AND m.academic_year = ? AND m.term_id = ? 
            ORDER BY m.id DESC LIMIT 1
        ");
        $meritStmt->execute([$selected_student_id, $selected_year, $selected_term_id]);
    }
    $current_results = $meritStmt->fetch(PDO::FETCH_ASSOC);
    
    // Parse subject scores
    if ($current_results && !empty($current_results['subject_scores_json'])) {
        $raw_scores = json_decode($current_results['subject_scores_json'], true);
        foreach ($raw_scores as $subject_key => $data) {
            $display_name = $subject_key;
            if (is_numeric($subject_key) && isset($subject_id_to_name[$subject_key])) {
                $display_name = $subject_id_to_name[$subject_key];
            }
            $exam_results[$display_name] = $data;
        }
    }
    
    // Get subject history for trend chart
    $historyStmt = $db->prepare("
        SELECT m.academic_year, t.term_name, t.term_number, m.subject_scores_json, e.examname 
        FROM tblmeritlist m 
        LEFT JOIN tblterms t ON m.term_id = t.id 
        LEFT JOIN tblexam e ON m.exam_id = e.id 
        WHERE m.student_id = ? AND m.academic_year = ? 
        ORDER BY t.term_number ASC
    ");
    $historyStmt->execute([$selected_student_id, $selected_year]);
    $subject_history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Performance error: " . $e->getMessage());
}
?>

<div class="welcome-banner reveal">
    <h1><i class="fas fa-chart-line"></i> Performance Analysis</h1>
    <p>Detailed subject performance for <?php echo htmlspecialchars($student_details['FirstName'] . ' ' . ($student_details['LastName'] ?? '')); ?></p>
</div>

<div class="card reveal">
    <div class="card-header">
        <h2><i class="fas fa-book-open"></i> Subject Performance - <?php echo htmlspecialchars($current_results['examname'] ?? 'Current Exam'); ?></h2>
    </div>
    <?php if (!empty($exam_results)): ?>
        <div class="subject-list">
            <?php foreach ($exam_results as $subject => $data): 
                $percentage = round($data['percentage'] ?? 0, 1);
                $grade_info = getGradeFromPercentage($percentage, $grading_scales);
            ?>
                <div class="subject-item">
                    <div class="subject-name"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $subject))); ?></div>
                    <div class="subject-score">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $grade_info['color']; ?>"></div>
                        </div>
                        <div class="score-value"><?php echo $percentage; ?>%</div>
                        <div class="grade-badge" style="background: <?php echo $grade_info['color']; ?>"><?php echo $grade_info['grade']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="overall-grade" style="margin-top: 20px; text-align: center;">
            <h3>Overall Performance</h3>
            <div class="grade-circle" style="margin: 15px auto;">
                <div class="grade-letter"><?php echo htmlspecialchars($current_results['overall_grade'] ?? 'N/A'); ?></div>
            </div>
            <div class="grade-percentage">Average: <?php echo round($current_results['mean_percentage'] ?? 0, 1); ?>%</div>
            <div class="grade-meaning">
                <?php 
                $overall_grade_info = getGradeFromPercentage($current_results['mean_percentage'] ?? 0, $grading_scales);
                echo $overall_grade_info['meaning'];
                ?>
            </div>
        </div>
    <?php else: ?>
        <div class="no-data">No performance data available for the selected period.</div>
    <?php endif; ?>
</div>

<div class="card reveal delay-1">
    <div class="card-header">
        <h2><i class="fas fa-chart-line"></i> Performance Trend by Term</h2>
    </div>
    <?php if (!empty($subject_history)): ?>
        <div style="height: 400px;">
            <canvas id="performanceTrendChart"></canvas>
        </div>
    <?php else: ?>
        <div class="no-data">No trend data available for the selected year.</div>
    <?php endif; ?>
</div>

<script>
// Prepare chart data
const historyData = (function() {
    const terms = [];
    const subjectsData = {};
    const colors = ['#00BFFF', '#facc15', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#f97316'];
    
    <?php foreach ($subject_history as $history): 
        if (!empty($history['subject_scores_json'])):
            $scores = json_decode($history['subject_scores_json'], true);
            $term_name = addslashes($history['term_name'] . ' (' . ($history['examname'] ?? 'Exam') . ')');
    ?>
            terms.push('<?php echo $term_name; ?>');
            <?php foreach ($scores as $subject_key => $data): 
                $subject_name = is_numeric($subject_key) ? 'Subject_' . $subject_key : addslashes(str_replace('_', ' ', $subject_key));
                $percentage = round($data['percentage'] ?? 0, 1);
            ?>
                if (!subjectsData['<?php echo $subject_name; ?>']) {
                    subjectsData['<?php echo $subject_name; ?>'] = [];
                }
                subjectsData['<?php echo $subject_name; ?>'].push(<?php echo $percentage; ?>);
            <?php endforeach; ?>
    <?php endif; endforeach; ?>
    
    const datasets = [];
    let colorIndex = 0;
    for (const [subject, data] of Object.entries(subjectsData)) {
        datasets.push({
            label: subject.replace(/_/g, ' '),
            data: data,
            borderColor: colors[colorIndex % colors.length],
            backgroundColor: colors[colorIndex % colors.length] + '20',
            tension: 0.3,
            fill: true,
            pointBackgroundColor: colors[colorIndex % colors.length],
            pointBorderColor: '#fff',
            pointRadius: 4,
            pointHoverRadius: 6
        });
        colorIndex++;
    }
    
    return { labels: terms, datasets: datasets };
})();

// Render chart if data exists
if (historyData.labels.length > 0 && historyData.datasets.length > 0) {
    const ctx = document.getElementById('performanceTrendChart')?.getContext('2d');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: historyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { boxWidth: 12, font: { size: 11 } } 
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                },
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        max: 100, 
                        title: { display: true, text: 'Score (%)' } 
                    } 
                }
            }
        });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>