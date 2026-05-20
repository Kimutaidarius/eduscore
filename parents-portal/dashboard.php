<?php
// dashboard.php - Main Dashboard Page
$page_title = "Dashboard";
require_once 'includes/header.php';

// Get the student's school_id
$school_id = $student_details['school_id'] ?? 0;
if ($school_id == 0 && $selected_student_id > 0) {
    $schoolStmt = $db->prepare("SELECT school_id FROM tblstudents WHERE id = ?");
    $schoolStmt->execute([$selected_student_id]);
    $school_result = $schoolStmt->fetch(PDO::FETCH_ASSOC);
    $school_id = $school_result['school_id'] ?? 0;
}

// Initialize variables
$current_results = null;
$exam_results = [];
$recent_exams = [];
$fee_balance = 0;
$attendance_summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
$grading_scales = [];

try {
    // Get grading scales
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
    
    // Get recent exams
    $recentStmt = $db->prepare("
        SELECT m.id, m.mean_percentage, m.overall_grade, m.academic_year,
               t.term_name, e.examname, e.id as exam_id
        FROM tblmeritlist m
        LEFT JOIN tblexam e ON m.exam_id = e.id
        LEFT JOIN tblterms t ON m.term_id = t.id
        WHERE m.student_id = ?
        ORDER BY m.created_at DESC LIMIT 5
    ");
    $recentStmt->execute([$selected_student_id]);
    $recent_exams = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get fee balance
    $feeStmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END), 0) as total_debit,
            COALESCE(SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END), 0) as total_paid
        FROM fee_transactions WHERE student_id = ?
    ");
    $feeStmt->execute([$selected_student_id]);
    $fee_data = $feeStmt->fetch(PDO::FETCH_ASSOC);
    $fee_balance = ($fee_data['total_debit'] ?? 0) - ($fee_data['total_paid'] ?? 0);
    
    // Get attendance
    $attStmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
            COUNT(CASE WHEN status = 'Late' THEN 1 END) as late,
            COUNT(*) as total
        FROM tblattendance
        WHERE student_id = ? AND YEAR(attendance_date) = ?
    ");
    $attStmt->execute([$selected_student_id, $selected_year]);
    $attendance_summary = $attStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

$attendance_rate = $attendance_summary['total'] > 0 ? round(($attendance_summary['present'] / $attendance_summary['total']) * 100, 1) : 0;
$current_exam_name = $current_results['examname'] ?? 'Current Assessment';
?>

<div class="welcome-banner reveal">
    <h1>Welcome back, Parent</h1>
    <p>Stay updated with your child's academic journey at <?php echo htmlspecialchars($student_details['class_name'] ?? 'School'); ?></p>
</div>

<div class="stats-grid reveal delay-1">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="stat-info">
            <h3>Current Average</h3>
            <div class="stat-value"><?php echo isset($current_results['mean_percentage']) ? round($current_results['mean_percentage'], 1) : '0'; ?><span class="stat-unit">%</span></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div class="stat-info">
            <h3>Overall Grade</h3>
            <div class="stat-value"><?php echo htmlspecialchars($current_results['overall_grade'] ?? 'N/A'); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div class="stat-info">
            <h3>Fee Balance</h3>
            <div class="stat-value">KSh <?php echo number_format(max(0, $fee_balance), 0); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-info">
            <h3>Attendance Rate</h3>
            <div class="stat-value"><?php echo $attendance_rate; ?><span class="stat-unit">%</span></div>
        </div>
    </div>
</div>

<div class="card reveal">
    <div class="card-header">
        <h2><i class="fas fa-book-open"></i> Recent Performance</h2>
        <div class="exam-badge"><?php echo htmlspecialchars($current_exam_name); ?></div>
    </div>
    <?php if (!empty($exam_results)): ?>
        <div class="subject-list">
            <?php 
            $display_count = 0;
            foreach ($exam_results as $subject => $data): 
                if ($display_count++ >= 5) break;
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
        <?php if (count($exam_results) > 5): ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="performance.php" class="btn btn-outline">View All Subjects <i class="fas fa-arrow-right"></i></a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="no-data">No exam results available for the selected period.</div>
    <?php endif; ?>
</div>

<div class="card reveal delay-1">
    <div class="card-header">
        <h2><i class="fas fa-history"></i> Recent Examinations</h2>
        <a href="performance.php" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.7rem;">View All</a>
    </div>
    <?php if (!empty($recent_exams)): ?>
        <div class="exam-list">
            <?php foreach ($recent_exams as $exam): 
                $grade_info = getGradeFromPercentage($exam['mean_percentage'] ?? 0, $grading_scales);
            ?>
                <div class="exam-item" onclick="selectExam(<?php echo $exam['exam_id']; ?>)">
                    <div class="exam-info">
                        <h4><?php echo htmlspecialchars($exam['examname'] ?? 'Assessment'); ?></h4>
                        <p><?php echo htmlspecialchars($exam['term_name'] ?? 'Term'); ?> - <?php echo $exam['academic_year']; ?></p>
                    </div>
                    <div class="exam-score">
                        <div class="exam-percentage"><?php echo round($exam['mean_percentage'] ?? 0, 1); ?>%</div>
                        <div class="grade-badge" style="background: <?php echo $grade_info['color']; ?>"><?php echo $grade_info['grade']; ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="no-data">No exam records found.</div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>