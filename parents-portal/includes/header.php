<?php
// includes/header.php - Common Header for Parents Portal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as parent
if (empty($_SESSION['is_logged_in']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__) . '/../includes/config.php';
require_once 'functions.php';

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$parent_id = $_SESSION['parent_id'];
$parent_phone = $_SESSION['parent_phone'];
$students = $_SESSION['parent_students'] ?? [];

// Get selected student from URL or session
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (isset($_SESSION['selected_student_id']) ? $_SESSION['selected_student_id'] : ($students[0]['id'] ?? 0));
$_SESSION['selected_student_id'] = $selected_student_id;

// Get available academic years, terms, and exams
$available_years = [];
$terms = [];
$available_exams = [];

try {
    // Get distinct academic years from exam results
    $yearsStmt = $db->prepare("
        SELECT DISTINCT m.academic_year 
        FROM tblmeritlist m 
        WHERE m.student_id = ? 
        AND m.academic_year IS NOT NULL 
        ORDER BY m.academic_year DESC
    ");
    $yearsStmt->execute([$selected_student_id]);
    $available_years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get terms with their details
    $termsStmt = $db->prepare("
        SELECT DISTINCT t.id, t.term_name, t.term_number, t.academic_year
        FROM tblterms t
        JOIN tblmeritlist m ON m.term_id = t.id
        WHERE m.student_id = ?
        ORDER BY t.academic_year DESC, t.term_number DESC
    ");
    $termsStmt->execute([$selected_student_id]);
    $terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available exams for the student
    $examsStmt = $db->prepare("
        SELECT DISTINCT m.exam_id, e.examname, m.academic_year, m.term_id
        FROM tblmeritlist m
        LEFT JOIN tblexam e ON m.exam_id = e.id
        WHERE m.student_id = ?
        ORDER BY m.created_at DESC
    ");
    $examsStmt->execute([$selected_student_id]);
    $available_exams = $examsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Header data fetch error: " . $e->getMessage());
    $available_years = [date('Y')];
    $terms = [];
    $available_exams = [];
}

// Get selected filters
$selected_year = isset($_GET['year']) ? $_GET['year'] : ($available_years[0] ?? date('Y'));
$selected_term_id = isset($_GET['term_id']) ? (int)$_GET['term_id'] : ($terms[0]['id'] ?? 0);
$selected_exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// Fetch student details
$student_details = [];
foreach ($students as $s) {
    if ($s['id'] == $selected_student_id) {
        $student_details = $s;
        break;
    }
}

$page_title = isset($page_title) ? $page_title : "Parents Portal - " . htmlspecialchars($student_details['FirstName'] . ' ' . ($student_details['LastName'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="Parents Portal - Monitor your child's academic performance, exam results, and school progress.">
    
    <link rel="shortcut icon" href="../images/logo.png" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/logo.png">
    <link rel="apple-touch-icon" href="../images/logo.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-wrapper">
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <header class="top-header">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="student-info">
                <i class="fas fa-user-graduate"></i>
                <span class="student-name">
                    <?php echo htmlspecialchars($student_details['FirstName'] . ' ' . ($student_details['LastName'] ?? '')); ?>
                </span>
                <select class="student-select" id="studentSelect" onchange="changeStudent(this.value)">
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" <?php echo $student['id'] == $selected_student_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['FirstName'] . ' ' . ($student['LastName'] ?? '')); ?> (<?php echo htmlspecialchars($student['AdmNo'] ?? 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-controls">
                <select id="examSelect" onchange="changeExam()">
                    <option value="0" <?php echo $selected_exam_id == 0 ? 'selected' : ''; ?>>-- Select Exam --</option>
                    <?php foreach ($available_exams as $exam): ?>
                        <option value="<?php echo $exam['exam_id']; ?>" <?php echo $exam['exam_id'] == $selected_exam_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($exam['examname'] ?? 'Exam'); ?> (<?php echo $exam['academic_year']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="yearSelect" onchange="changeFilters()">
                    <?php foreach ($available_years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php echo $year == $selected_year ? 'selected' : ''; ?>>
                            Year <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select id="termSelect" onchange="changeFilters()">
                    <?php foreach ($terms as $term): ?>
                        <option value="<?php echo $term['id']; ?>" <?php echo $term['id'] == $selected_term_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($term['term_name']); ?> - <?php echo $term['academic_year']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                    <i class="fas fa-sun"></i>
                    <span>Dark</span>
                </button>
                
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>
        
        <main class="page-content">