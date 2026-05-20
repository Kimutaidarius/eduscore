<?php
session_start();

// Include AJAX handler


error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['teacher_id']) || !isset($_SESSION['school_id'])) {
    header('Location: login.php');
    exit();
}

// Session variables
$teacher_id = $_SESSION['teacher_id'];
$school_id = $_SESSION['school_id'];
$academic_level = $_SESSION['academic_level'] ?? 'primary';
$academic_level_id = $_SESSION['academic_level_id'] ?? null;

// Database connection
require_once 'includes/config.php';
require_once 'includes/session_timeout.php'; 

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch classes for dropdown - FILTERED BY ACADEMIC LEVEL
$classesQuery = $conn->prepare("
    SELECT id, class_level as display_name 
    FROM tblclasses 
    WHERE school_id = ? AND academic_level = ?
    ORDER BY class_level
");
$classesQuery->bind_param("is", $school_id, $academic_level);
$classesQuery->execute();
$classesResult = $classesQuery->get_result();
$classes = [];
while ($class = $classesResult->fetch_assoc()) {
    $classes[] = $class;
}
$classesQuery->close();

// Fetch terms for dropdown
$termsQuery = $conn->prepare("
    SELECT id, term_name, academic_year 
    FROM tblterms 
    WHERE school_id = ?
    ORDER BY academic_year DESC, term_number
");
$termsQuery->bind_param("i", $school_id);
$termsQuery->execute();
$termsResult = $termsQuery->get_result();
$terms = [];
while ($term = $termsResult->fetch_assoc()) {
    $terms[] = $term;
}
$termsQuery->close();

// Get distinct years from terms
$yearsQuery = $conn->prepare("
    SELECT DISTINCT academic_year as year 
    FROM tblterms 
    WHERE school_id = ?
    ORDER BY academic_year DESC
");
$yearsQuery->bind_param("i", $school_id);
$yearsQuery->execute();
$yearsResult = $yearsQuery->get_result();
$years = [];
while ($year = $yearsResult->fetch_assoc()) {
    $years[] = $year['year'];
}
$yearsQuery->close();

// Fetch subjects for dropdown
$subjectsQuery = $conn->prepare("
    SELECT id, subject_name 
    FROM tblsubjects 
    WHERE school_id = ?
    ORDER BY subject_name
");
$subjectsQuery->bind_param("i", $school_id);
$subjectsQuery->execute();
$subjectsResult = $subjectsQuery->get_result();
$subjects = [];
while ($subject = $subjectsResult->fetch_assoc()) {
    $subjects[] = $subject;
}
$subjectsQuery->close();

// Fetch merit lists (exams) for dropdown
$meritListsQuery = $conn->prepare("
    SELECT id, examname 
    FROM tblexam 
    WHERE school_id = ?
    ORDER BY examname
");
$meritListsQuery->bind_param("i", $school_id);
$meritListsQuery->execute();
$meritListsResult = $meritListsQuery->get_result();
$meritLists = [];
while ($meritList = $meritListsResult->fetch_assoc()) {
    $meritLists[] = $meritList;
}
$meritListsQuery->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <link rel="apple-touch-icon" href="images/logo.png">
    <link rel="stylesheet" href="assets/banner/banner.css">
    <!-- Add SheetJS for Excel export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <!-- Add jsPDF for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --secondary-blue: #2563eb;
            --light-blue: #dbeafe;
            --accent-green: #10b981;
            --dark-blue: #1e3a8a;
            --success-green: #10b981;
            --warning-orange: #f59e0b;
            --error-red: #ef4444;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Grade colors */
            --grade-ee: #10b981;
            --grade-me: #3b82f6;
            --grade-ae: #f59e0b;
            --grade-ap: #8b5cf6;
            --grade-be: #ef4444;
            --grade-x: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            padding: 100px 2rem 2rem;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 100px 1rem 1rem;
            }
        }

        /* Academic Level Indicator */
        .academic-level-indicator {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            border-left: 4px solid var(--accent-green);
        }

        .academic-level-icon {
            width: 50px;
            height: 50px;
            background: #d1fae5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-green);
            font-size: 1.25rem;
        }

        .academic-level-content h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .academic-level-content p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .academic-level-badge {
            background: #d1fae5;
            color: var(--accent-green);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Toggle Navigation */
        .analytics-nav {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            border: 1px solid var(--border-color);
        }

        .nav-toggle-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
            color: var(--text-light);
            flex: 1 1 auto;
            justify-content: center;
        }

        .nav-toggle-btn i {
            font-size: 1rem;
        }

        .nav-toggle-btn:hover {
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .nav-toggle-btn.active {
            background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white;
            box-shadow: var(--shadow);
        }

        /* Filter Section */
        .filter-section {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .filter-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .filter-title i {
            color: var(--accent-green);
        }

        .filter-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1rem;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 200px;
            flex: 1 1 auto;
        }

        .filter-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-label i {
            color: var(--primary-blue);
            font-size: 1rem;
        }

        .filter-select, .filter-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--bg-white);
            cursor: pointer;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-select:disabled {
            background: var(--bg-light);
            cursor: not-allowed;
            opacity: 0.7;
        }

        .filter-input {
            cursor: text;
        }

        /* Dual Card Layout for Improvement Analysis */
        .dual-card-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .analytics-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header i {
            color: var(--accent-green);
        }

        .card-header h3 {
            font-size: 1rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
            background: var(--bg-white);
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-green), #059669);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .btn-secondary:hover {
            background: var(--secondary-blue);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
            box-shadow: none;
        }

        .btn-outline:hover {
            background: var(--bg-light);
            border-color: var(--primary-blue);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Analytics Cards (Summary) */
        .analytics-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 1.25rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
            border-top: 3px solid var(--accent-green);
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .summary-icon.mean { background: var(--light-blue); color: var(--primary-blue); }
        .summary-icon.rubric { background: #d1fae5; color: var(--accent-green); }
        .summary-icon.change { background: #fef3c7; color: var(--warning-orange); }
        .summary-icon.teacher { background: #ede9fe; color: var(--primary-blue); }

        .summary-content {
            flex: 1;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .summary-label {
            font-size: 0.875rem;
            color: var(--text-light);
        }

        .summary-trend {
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.25rem;
        }

        .trend-up { color: var(--accent-green); }
        .trend-down { color: var(--error-red); }

        /* Analytics Table Container */
        .analytics-table-container {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
        }

        .table-title {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-title i {
            color: var(--accent-green);
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .grade-legend {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            background: rgba(255,255,255,0.1);
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 4px;
        }

        .legend-color.ee { background: var(--grade-ee); }
        .legend-color.me { background: var(--grade-me); }
        .legend-color.ae { background: var(--grade-ae); }
        .legend-color.ap { background: var(--grade-ap); }
        .legend-color.be { background: var(--grade-be); }
        .legend-color.x { background: var(--grade-x); }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .analytics-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .analytics-table th {
            background: #f8fafc;
            padding: 1rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 2px solid var(--border-color);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .analytics-table th i {
            margin-right: 0.5rem;
            color: var(--accent-green);
        }

        .analytics-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.9rem;
            vertical-align: middle;
        }

        .analytics-table tr:last-child td {
            border-bottom: none;
        }

        .analytics-table tr:hover {
            background: var(--bg-light);
        }

        /* Subject Cell */
        .subject-cell {
            display: flex;
            flex-direction: column;
        }

        .subject-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .subject-code {
            font-size: 0.75rem;
            color: var(--text-light);
        }

        /* Grade Badge */
        .grade-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
            min-width: 60px;
        }

        .grade-badge.ee {
            background: rgba(16, 185, 129, 0.15);
            color: var(--grade-ee);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .grade-badge.me {
            background: rgba(59, 130, 246, 0.15);
            color: var(--grade-me);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .grade-badge.ae {
            background: rgba(245, 158, 11, 0.15);
            color: var(--grade-ae);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .grade-badge.ap {
            background: rgba(139, 92, 246, 0.15);
            color: var(--grade-ap);
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .grade-badge.be {
            background: rgba(239, 68, 68, 0.15);
            color: var(--grade-be);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .grade-badge.x {
            background: rgba(107, 114, 128, 0.15);
            color: var(--grade-x);
            border: 1px solid rgba(107, 114, 128, 0.3);
        }

        /* Score Display */
        .score-display {
            font-weight: 700;
            font-size: 1rem;
        }

        .score-positive {
            color: var(--accent-green);
        }

        .score-negative {
            color: var(--error-red);
        }

        .score-neutral {
            color: var(--warning-orange);
        }

        /* Rubric Bar */
        .rubric-bar-container {
            width: 100px;
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.25rem;
        }

        .rubric-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-green), var(--primary-blue));
            border-radius: 3px;
        }

        /* Change Indicator */
        .change-indicator {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 600;
        }

        /* Champion Badge */
        .champion-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #1e3a8a;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .champion-badge i {
            color: #ff6b00;
        }

        /* Progress Bar */
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.25rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-green), var(--primary-blue));
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: var(--accent-green);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .empty-state p {
            margin-bottom: 1.5rem;
        }

        /* Loading Spinner */
        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid rgba(59, 130, 246, 0.3);
            border-top-color: var(--primary-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 100px;
            right: 2rem;
            z-index: 3000;
            max-width: 400px;
        }

        .toast {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--success-green);
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideInRight 0.3s ease;
        }

        .toast.error {
            border-left-color: var(--error-red);
        }

        .toast.warning {
            border-left-color: var(--warning-orange);
        }

        .toast.info {
            border-left-color: var(--secondary-blue);
        }

        .toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
        }

        .toast.success .toast-icon {
            background: var(--success-green);
        }

        .toast.error .toast-icon {
            background: var(--error-red);
        }

        .toast.warning .toast-icon {
            background: var(--warning-orange);
        }

        .toast.info .toast-icon {
            background: var(--secondary-blue);
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .toast-message {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        /* Export Dropdown */
        .export-dropdown {
            position: relative;
            display: inline-block;
        }

        .export-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            z-index: 1000;
            display: none;
            margin-top: 0.5rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .export-menu.active {
            display: block;
        }

        .export-item {
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-dark);
            transition: var(--transition);
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
        }

        .export-item:last-child {
            border-bottom: none;
        }

        .export-item:hover {
            background: var(--light-blue);
            color: var(--primary-blue);
        }

        .export-item i {
            width: 20px;
            text-align: center;
        }

        .export-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0.5rem 0;
        }

        .export-header {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--bg-light);
        }

        /* Print styles */
        @media print {
            .analytics-nav, .filter-section, .action-bar, .academic-level-indicator, 
            .toast-container, .export-dropdown, .grade-legend, .table-actions,
            .nav-toggle-btn, .btn, .print-hide {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .analytics-table-container {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }

            .analytics-table th {
                background: #f0f0f0 !important;
                color: black !important;
            }

            .table-header {
                background: #f0f0f0 !important;
                color: black !important;
            }
        }

        /* Hide/Show Views */
        .view-container {
            display: none;
        }

        .view-container.active {
            display: block;
        }

        /* Animations */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .main-content {
                padding: 100px 1rem 1rem;
            }

            .analytics-nav {
                flex-direction: column;
            }

            .nav-toggle-btn {
                width: 100%;
            }

            .dual-card-container {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                flex-direction: column;
                gap: 1rem;
            }

            .filter-group {
                width: 100%;
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .action-buttons {
                width: 100%;
            }

            .action-buttons .btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <?php 
    // Get school info for trial banner
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $schoolQuery = $conn->prepare("SELECT * FROM tblschoolinfo WHERE id = ?");
    $schoolQuery->bind_param("i", $school_id);
    $schoolQuery->execute();
    $schoolResult = $schoolQuery->get_result();
    $school = $schoolResult->fetch_assoc();
    $schoolQuery->close();
    $conn->close();

    // Include the trial banner if not activated
    if (isset($school) && $school && (!isset($school['is_activated']) || $school['is_activated'] == 0)) {
        include 'trial_banner.php';
    }
    ?>
    
    <!-- Include Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Include Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Academic Level Indicator -->
        <div class="academic-level-indicator">
            <div class="academic-level-icon">
                <?php 
                $academic_icons = [
                    'primary' => 'fas fa-school',
                    'junior_secondary' => 'fas fa-graduation-cap',
                    'senior_secondary' => 'fas fa-university',
                    'college' => 'fas fa-graduation-cap'
                ];
                echo '<i class="' . ($academic_icons[$academic_level] ?? 'fas fa-graduation-cap') . '"></i>';
                ?>
            </div>
            <div class="academic-level-content">
                <h3>Academic Level: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $academic_level))); ?></h3>
                <p>Analytics Dashboard - Performance Analysis and Insights</p>
            </div>
            <div class="academic-level-badge">
                <i class="fas fa-chart-line"></i> Analytics
            </div>
        </div>

        <!-- Analytics Navigation Toggle -->
        <div class="analytics-nav">
            <button class="nav-toggle-btn active" data-view="learning-area-analysis">
                <i class="fas fa-book-open"></i>
                Learning Area Analysis
            </button>
            <button class="nav-toggle-btn" data-view="learning-area-merit-analysis">
                <i class="fas fa-trophy"></i>
                Learning Area Merit Analysis
            </button>
            <button class="nav-toggle-btn" data-view="champions">
                <i class="fas fa-crown"></i>
                Champions
            </button>
            <button class="nav-toggle-btn" data-view="improvement-analysis">
                <i class="fas fa-arrow-trend-up"></i>
                Improvement Analysis
            </button>
            <button class="nav-toggle-btn" data-view="gender-analysis">
                <i class="fas fa-venus-mars"></i>
                Gender Analysis
            </button>
        </div>

        <!-- Learning Area Analysis View -->
        <div id="view-learning-area-analysis" class="view-container active">
            <div class="filter-section">
                <h3 class="filter-title">
                    <i class="fas fa-filter"></i>
                    Learning Area Analysis Filters
                </h3>
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-graduation-cap"></i>
                            Class
                        </label>
                        <select id="laClass" class="filter-select">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo $class['display_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar"></i>
                            Year
                        </label>
                        <select id="laYear" class="filter-select">
                            <option value="">Select Year</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar-alt"></i>
                            Term
                        </label>
                        <select id="laTerm" class="filter-select">
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?php echo $term['id']; ?>"><?php echo $term['term_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-file-alt"></i>
                            Select MeritList
                        </label>
                        <select id="laMeritList" class="filter-select">
                            <option value="">Select MeritList</option>
                            <?php foreach ($meritLists as $meritList): ?>
                                <option value="<?php echo $meritList['id']; ?>"><?php echo $meritList['examname']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Removed Load Data Button -->
                </div>
            </div>

            <div class="analytics-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-book-open"></i>
                        Learning Area Analysis
                    </div>
                    <div class="table-actions">
                        <div class="export-dropdown">
                            <button class="btn btn-success btn-sm" id="laExportBtn">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <div class="export-menu" id="laExportMenu">
                                <div class="export-header">Export Options</div>
                                <div class="export-item" data-format="pdf" data-view="learning-area-analysis">
                                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                                    Export as PDF
                                </div>
                                <div class="export-item" data-format="excel" data-view="learning-area-analysis">
                                    <i class="fas fa-file-excel" style="color: #10b981;"></i>
                                    Export as Excel
                                </div>
                                <div class="export-item" data-format="csv" data-view="learning-area-analysis">
                                    <i class="fas fa-file-csv" style="color: #3b82f6;"></i>
                                    Export as CSV
                                </div>
                                <div class="export-divider"></div>
                                <div class="export-item" data-format="print" data-view="learning-area-analysis">
                                    <i class="fas fa-print"></i>
                                    Print
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="analytics-table" id="laTable">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>EE</th>
                                <th>ME</th>
                                <th>AE</th>
                                <th>AP</th>
                                <th>BE</th>
                                <th>X</th>
                                <th>Mean</th>
                                <th>Rubric</th>
                                <th>Avg Rubric</th>
                                <th>Teacher</th>
                            </tr>
                        </thead>
                        <tbody id="laTableBody">
                            <tr>
                                <td colspan="12" class="empty-state">
                                    <i class="fas fa-chart-pie"></i>
                                    <h3>No Data</h3>
                                    <p>Select filters to load data</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Learning Area Merit Analysis View -->
        <div id="view-learning-area-merit-analysis" class="view-container">
            <div class="filter-section">
                <h3 class="filter-title">
                    <i class="fas fa-filter"></i>
                    Learning Area Merit Analysis Filters
                </h3>
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-graduation-cap"></i>
                            Class
                        </label>
                        <select id="lamaClass" class="filter-select">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo $class['display_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar"></i>
                            Year
                        </label>
                        <select id="lamaYear" class="filter-select">
                            <option value="">Select Year</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar-alt"></i>
                            Term
                        </label>
                        <select id="lamaTerm" class="filter-select">
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?php echo $term['id']; ?>"><?php echo $term['term_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-file-alt"></i>
                            MeritList
                        </label>
                        <select id="lamaMeritList" class="filter-select">
                            <option value="">Select MeritList</option>
                            <?php foreach ($meritLists as $meritList): ?>
                                <option value="<?php echo $meritList['id']; ?>"><?php echo $meritList['examname']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-book"></i>
                            Select Subject
                        </label>
                        <select id="lamaSubject" class="filter-select">
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>"><?php echo $subject['subject_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Removed Load Data Button -->
                </div>
            </div>

            <div class="analytics-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-trophy"></i>
                        Learning Area Merit Analysis
                    </div>
                    <div class="table-actions">
                        <div class="export-dropdown">
                            <button class="btn btn-success btn-sm" id="lamaExportBtn">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <div class="export-menu" id="lamaExportMenu">
                                <div class="export-header">Export Options</div>
                                <div class="export-item" data-format="pdf" data-view="learning-area-merit-analysis">
                                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                                    Export as PDF
                                </div>
                                <div class="export-item" data-format="excel" data-view="learning-area-merit-analysis">
                                    <i class="fas fa-file-excel" style="color: #10b981;"></i>
                                    Export as Excel
                                </div>
                                <div class="export-item" data-format="csv" data-view="learning-area-merit-analysis">
                                    <i class="fas fa-file-csv" style="color: #3b82f6;"></i>
                                    Export as CSV
                                </div>
                                <div class="export-divider"></div>
                                <div class="export-item" data-format="print" data-view="learning-area-merit-analysis">
                                    <i class="fas fa-print"></i>
                                    Print
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="analytics-table" id="lamaTable">
                        <thead>
                            <tr>
                                <th>No:</th>
                                <th>ADMN</th>
                                <th>Name</th>
                                <th>Score/Grade</th>
                                <th>Rubric</th>
                            </tr>
                        </thead>
                        <tbody id="lamaTableBody">
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-chart-pie"></i>
                                    <h3>No Data</h3>
                                    <p>Select filters to load data</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Champions View -->
        <div id="view-champions" class="view-container">
            <div class="filter-section">
                <h3 class="filter-title">
                    <i class="fas fa-filter"></i>
                    Champions Filters
                </h3>
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-graduation-cap"></i>
                            Class
                        </label>
                        <select id="champClass" class="filter-select">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo $class['display_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar"></i>
                            Year
                        </label>
                        <select id="champYear" class="filter-select">
                            <option value="">Select Year</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar-alt"></i>
                            Term
                        </label>
                        <select id="champTerm" class="filter-select">
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?php echo $term['id']; ?>"><?php echo $term['term_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-file-alt"></i>
                            MeritList
                        </label>
                        <select id="champMeritList" class="filter-select">
                            <option value="">Select MeritList</option>
                            <?php foreach ($meritLists as $meritList): ?>
                                <option value="<?php echo $meritList['id']; ?>"><?php echo $meritList['examname']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-book"></i>
                            Select Subject
                        </label>
                        <select id="champSubject" class="filter-select">
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>"><?php echo $subject['subject_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-sort-amount-up"></i>
                            Show Top
                        </label>
                        <input type="number" id="champTop" class="filter-input" value="10" min="1" max="100">
                    </div>
                    <!-- Removed Load Champions Button -->
                </div>
            </div>

            <div class="analytics-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-crown"></i>
                        Champions - Top Performers
                    </div>
                    <div class="table-actions">
                        <div class="export-dropdown">
                            <button class="btn btn-success btn-sm" id="champExportBtn">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <div class="export-menu" id="champExportMenu">
                                <div class="export-header">Export Options</div>
                                <div class="export-item" data-format="pdf" data-view="champions">
                                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                                    Export as PDF
                                </div>
                                <div class="export-item" data-format="excel" data-view="champions">
                                    <i class="fas fa-file-excel" style="color: #10b981;"></i>
                                    Export as Excel
                                </div>
                                <div class="export-item" data-format="csv" data-view="champions">
                                    <i class="fas fa-file-csv" style="color: #3b82f6;"></i>
                                    Export as CSV
                                </div>
                                <div class="export-divider"></div>
                                <div class="export-item" data-format="print" data-view="champions">
                                    <i class="fas fa-print"></i>
                                    Print
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="analytics-table" id="champTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ADM No.</th>
                                <th>Full name</th>
                                <th>Class</th>
                                <th>Score</th>
                                <th>Grade</th>
                                <th>Rubric</th>
                                <th>Str Pos</th>
                                <th>Cls Pos</th>
                            </tr>
                        </thead>
                        <tbody id="champTableBody">
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <i class="fas fa-crown"></i>
                                    <h3>No Champions Data</h3>
                                    <p>Select filters to load data</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Improvement Analysis View -->
        <div id="view-improvement-analysis" class="view-container">
            <div class="dual-card-container">
                <!-- First MeritList Card -->
                <div class="analytics-card">
                    <div class="card-header">
                        <i class="fas fa-history"></i>
                        <h3>First MeritList</h3>
                    </div>
                    <div class="card-body">
                        <div class="filter-grid" style="margin-bottom: 0;">
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-graduation-cap"></i>
                                    Class
                                </label>
                                <select id="impFirstClass" class="filter-select">
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>"><?php echo $class['display_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-calendar"></i>
                                    Year
                                </label>
                                <select id="impFirstYear" class="filter-select">
                                    <option value="">Select Year</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Term
                                </label>
                                <select id="impFirstTerm" class="filter-select">
                                    <option value="">Select Term</option>
                                    <?php foreach ($terms as $term): ?>
                                        <option value="<?php echo $term['id']; ?>"><?php echo $term['term_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-file-alt"></i>
                                    MeritList
                                </label>
                                <select id="impFirstMeritList" class="filter-select">
                                    <option value="">Select MeritList</option>
                                    <?php foreach ($meritLists as $meritList): ?>
                                        <option value="<?php echo $meritList['id']; ?>"><?php echo $meritList['examname']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current MeritList Card -->
                <div class="analytics-card">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i>
                        <h3>Current MeritList</h3>
                    </div>
                    <div class="card-body">
                        <div class="filter-grid" style="margin-bottom: 0;">
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-graduation-cap"></i>
                                    Class
                                </label>
                                <select id="impCurrentClass" class="filter-select">
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>"><?php echo $class['display_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-calendar"></i>
                                    Year
                                </label>
                                <select id="impCurrentYear" class="filter-select">
                                    <option value="">Select Year</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Term
                                </label>
                                <select id="impCurrentTerm" class="filter-select">
                                    <option value="">Select Term</option>
                                    <?php foreach ($terms as $term): ?>
                                        <option value="<?php echo $term['id']; ?>"><?php echo $term['term_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">
                                    <i class="fas fa-file-alt"></i>
                                    MeritList
                                </label>
                                <select id="impCurrentMeritList" class="filter-select">
                                    <option value="">Select MeritList</option>
                                    <?php foreach ($meritLists as $meritList): ?>
                                        <option value="<?php echo $meritList['id']; ?>"><?php echo $meritList['examname']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Removed Compare MeritLists Button -->

            <div class="analytics-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-arrow-trend-up"></i>
                        Improvement Analysis
                    </div>
                    <div class="table-actions">
                        <div class="export-dropdown">
                            <button class="btn btn-success btn-sm" id="impExportBtn">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <div class="export-menu" id="impExportMenu">
                                <div class="export-header">Export Options</div>
                                <div class="export-item" data-format="pdf" data-view="improvement-analysis">
                                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                                    Export as PDF
                                </div>
                                <div class="export-item" data-format="excel" data-view="improvement-analysis">
                                    <i class="fas fa-file-excel" style="color: #10b981;"></i>
                                    Export as Excel
                                </div>
                                <div class="export-item" data-format="csv" data-view="improvement-analysis">
                                    <i class="fas fa-file-csv" style="color: #3b82f6;"></i>
                                    Export as CSV
                                </div>
                                <div class="export-divider"></div>
                                <div class="export-item" data-format="print" data-view="improvement-analysis">
                                    <i class="fas fa-print"></i>
                                    Print
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="analytics-table" id="impTable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Admission</th>
                                <th>Class/Stream</th>
                                <th>First Score</th>
                                <th>Current Score</th>
                                <th>Improvement</th>
                                <th>% Change</th>
                            </tr>
                        </thead>
                        <tbody id="impTableBody">
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <i class="fas fa-arrow-trend-up"></i>
                                    <h3>No Improvement Data</h3>
                                    <p>Select filters for both merit lists to load data</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Gender Analysis View -->
        <div id="view-gender-analysis" class="view-container">
            <div class="filter-section">
                <h3 class="filter-title">
                    <i class="fas fa-filter"></i>
                    Gender Analysis Filters
                </h3>
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-graduation-cap"></i>
                            Class
                        </label>
                        <select id="genderClass" class="filter-select">
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo $class['display_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar"></i>
                            Year
                        </label>
                        <select id="genderYear" class="filter-select">
                            <option value="">Select Year</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-calendar-alt"></i>
                            Term
                        </label>
                        <select id="genderTerm" class="filter-select">
                            <option value="">Select Term</option>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?php echo $term['id']; ?>"><?php echo $term['term_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">
                            <i class="fas fa-file-alt"></i>
                            Select MeritList
                        </label>
                        <select id="genderMeritList" class="filter-select">
                            <option value="">Select MeritList</option>
                            <?php foreach ($meritLists as $meritList): ?>
                                <option value="<?php echo $meritList['id']; ?>"><?php echo $meritList['examname']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Removed Load Data Button -->
                </div>
            </div>

            <div class="analytics-table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-venus-mars"></i>
                        Gender Analysis
                    </div>
                    <div class="table-actions">
                        <div class="export-dropdown">
                            <button class="btn btn-success btn-sm" id="genderExportBtn">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <div class="export-menu" id="genderExportMenu">
                                <div class="export-header">Export Options</div>
                                <div class="export-item" data-format="pdf" data-view="gender-analysis">
                                    <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                                    Export as PDF
                                </div>
                                <div class="export-item" data-format="excel" data-view="gender-analysis">
                                    <i class="fas fa-file-excel" style="color: #10b981;"></i>
                                    Export as Excel
                                </div>
                                <div class="export-item" data-format="csv" data-view="gender-analysis">
                                    <i class="fas fa-file-csv" style="color: #3b82f6;"></i>
                                    Export as CSV
                                </div>
                                <div class="export-divider"></div>
                                <div class="export-item" data-format="print" data-view="gender-analysis">
                                    <i class="fas fa-print"></i>
                                    Print
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="analytics-table" id="genderTable">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Entry</th>
                                <th>EE</th>
                                <th>ME</th>
                                <th>AE</th>
                                <th>AP</th>
                                <th>BE</th>
                                <th>X</th>
                                <th>M RB</th>
                                <th>M.Mark</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody id="genderTableBody">
                            <tr>
                                <td colspan="11" class="empty-state">
                                    <i class="fas fa-venus-mars"></i>
                                    <h3>No Gender Data</h3>
                                    <p>Select filters to load data</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Global Action Bar -->
        <div class="action-bar">
            <div class="grade-legend">
                <div class="legend-item">
                    <span class="legend-color ee"></span>
                    <span>EE (80-100%)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color me"></span>
                    <span>ME (65-79%)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color ae"></span>
                    <span>AE (50-64%)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color ap"></span>
                    <span>AP (40-49%)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color be"></span>
                    <span>BE (0-39%)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color x"></span>
                    <span>X (Ungraded)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // State management
            const state = {
                currentView: 'learning-area-analysis',
                schoolId: <?php echo $school_id; ?>,
                teacherId: <?php echo $teacher_id; ?>,
                academicLevel: '<?php echo $academic_level; ?>',
                dataLoaded: false,
                loadingTimers: {} // For debouncing
            };

            // View switching
            $('.nav-toggle-btn').on('click', function() {
                const view = $(this).data('view');
                
                $('.nav-toggle-btn').removeClass('active');
                $(this).addClass('active');
                
                $('.view-container').removeClass('active');
                $('#view-' + view).addClass('active');
                
                state.currentView = view;
                
                // Trigger auto-load for the new view if all filters are selected
                setTimeout(() => {
                    triggerAutoLoad(view);
                }, 100);
            });

            // Toast notification system
            function showToast(type, title, message) {
                const toastContainer = $('#toastContainer');
                
                const icons = {
                    success: 'fas fa-check-circle',
                    error: 'fas fa-exclamation-circle',
                    warning: 'fas fa-exclamation-triangle',
                    info: 'fas fa-info-circle'
                };

                const toast = $(`
                    <div class="toast ${type}">
                        <div class="toast-icon">
                            <i class="${icons[type]}"></i>
                        </div>
                        <div class="toast-content">
                            <div class="toast-title">${title}</div>
                            <div class="toast-message">${message}</div>
                        </div>
                    </div>
                `);

                toastContainer.append(toast);

                setTimeout(() => {
                    toast.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 4000);
            }

            // Debounce function to prevent multiple rapid calls
            function debounce(func, wait, key) {
                return function executedFunction(...args) {
                    if (state.loadingTimers[key]) {
                        clearTimeout(state.loadingTimers[key]);
                    }
                    state.loadingTimers[key] = setTimeout(() => {
                        func(...args);
                        delete state.loadingTimers[key];
                    }, wait);
                };
            }

            // Trigger auto-load based on view
            function triggerAutoLoad(view) {
                switch(view) {
                    case 'learning-area-analysis':
                        checkAndLoadLearningAreaAnalysis();
                        break;
                    case 'learning-area-merit-analysis':
                        checkAndLoadMeritAnalysis();
                        break;
                    case 'champions':
                        checkAndLoadChampions();
                        break;
                    case 'improvement-analysis':
                        checkAndLoadImprovementAnalysis();
                        break;
                    case 'gender-analysis':
                        checkAndLoadGenderAnalysis();
                        break;
                }
            }

            // Check and load Learning Area Analysis
            function checkAndLoadLearningAreaAnalysis() {
                const classId = $('#laClass').val();
                const year = $('#laYear').val();
                const termId = $('#laTerm').val();
                const meritListId = $('#laMeritList').val();

                if (classId && year && termId && meritListId) {
                    loadLearningAreaAnalysis(classId, year, termId, meritListId);
                }
            }

            // Load Learning Area Analysis
            function loadLearningAreaAnalysis(classId, year, termId, meritListId) {
                $('#laTableBody').html(`
                    <tr>
                        <td colspan="12" class="loading-container">
                            <div class="loading-spinner"></div>
                            <p>Loading data...</p>
                        </td>
                    </tr>
                `);

                $.ajax({
                    url: 'ajax/get_learning_area_analysis.php',
                    method: 'POST',
                    data: {
                        class_id: classId,
                        year: year,
                        term_id: termId,
                        exam_id: meritListId,
                        school_id: state.schoolId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let html = '';
                            response.data.forEach(item => {
                                const meanClass = item.mean >= 50 ? 'score-positive' : 'score-negative';
                                html += `
                                    <tr>
                                        <td><strong>${item.subject_name}</strong></td>
                                        <td>${item.stream_name || 'All'}</td>
                                        <td>${item.ee_count}</td>
                                        <td>${item.me_count}</td>
                                        <td>${item.ae_count}</td>
                                        <td>${item.ap_count}</td>
                                        <td>${item.be_count}</td>
                                        <td>${item.x_count}</td>
                                        <td><span class="${meanClass}">${item.mean.toFixed(1)}</span></td>
                                        <td>${item.avg_rubric.toFixed(1)}</td>
                                        <td>${item.avg_rubric.toFixed(1)}</td>
                                        <td>${item.teacher_name || 'Not Assigned'}</td>
                                    </tr>
                                `;
                            });
                            $('#laTableBody').html(html);
                        } else {
                            $('#laTableBody').html(`
                                <tr>
                                    <td colspan="12" class="empty-state">
                                        <i class="fas fa-chart-pie"></i>
                                        <h3>No Data Found</h3>
                                        <p>No records available for the selected filters</p>
                                    </td>
                                </tr>
                            `);
                        }
                    },
                    error: function() {
                        showToast('error', 'Error', 'Failed to load data');
                        $('#laTableBody').html(`
                            <tr>
                                <td colspan="12" class="empty-state">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <h3>Error</h3>
                                    <p>Failed to load data. Please try again.</p>
                                </td>
                            </tr>
                        `);
                    }
                });
            }

            // Check and load Merit Analysis
            function checkAndLoadMeritAnalysis() {
                const classId = $('#lamaClass').val();
                const year = $('#lamaYear').val();
                const termId = $('#lamaTerm').val();
                const meritListId = $('#lamaMeritList').val();
                const subjectId = $('#lamaSubject').val();

                if (classId && year && termId && meritListId && subjectId) {
                    loadMeritAnalysis(classId, year, termId, meritListId, subjectId);
                }
            }

            // Load Merit Analysis
            function loadMeritAnalysis(classId, year, termId, meritListId, subjectId) {
                $('#lamaTableBody').html(`
                    <tr>
                        <td colspan="5" class="loading-container">
                            <div class="loading-spinner"></div>
                            <p>Loading data...</p>
                        </td>
                    </tr>
                `);

                $.ajax({
                    url: 'ajax/get_merit_analysis.php',
                    method: 'POST',
                    data: {
                        class_id: classId,
                        year: year,
                        term_id: termId,
                        exam_id: meritListId,
                        subject_id: subjectId,
                        school_id: state.schoolId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let html = '';
                            response.data.forEach((item, index) => {
                                let gradeClass = 'grade-badge';
                                if (item.grade === 'EE') gradeClass += ' ee';
                                else if (item.grade === 'ME') gradeClass += ' me';
                                else if (item.grade === 'AE') gradeClass += ' ae';
                                else if (item.grade === 'AP') gradeClass += ' ap';
                                else if (item.grade === 'BE') gradeClass += ' be';
                                else gradeClass += ' x';
                                
                                html += `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td>${item.admission_no}</td>
                                        <td>${item.student_name}</td>
                                        <td><span class="${gradeClass}">${item.score} ${item.grade}</span></td>
                                        <td>${item.rubric}</td>
                                    </tr>
                                `;
                            });
                            $('#lamaTableBody').html(html);
                        } else {
                            $('#lamaTableBody').html(`
                                <tr>
                                    <td colspan="5" class="empty-state">
                                        <i class="fas fa-chart-pie"></i>
                                        <h3>No Data Found</h3>
                                        <p>No records available for the selected filters</p>
                                    </td>
                                </tr>
                            `);
                        }
                    },
                    error: function() {
                        showToast('error', 'Error', 'Failed to load data');
                        $('#lamaTableBody').html(`
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <h3>Error</h3>
                                    <p>Failed to load data. Please try again.</p>
                                </td>
                            </tr>
                        `);
                    }
                });
            }

            // Check and load Champions
            function checkAndLoadChampions() {
                const classId = $('#champClass').val();
                const year = $('#champYear').val();
                const termId = $('#champTerm').val();
                const meritListId = $('#champMeritList').val();
                const subjectId = $('#champSubject').val();
                const top = $('#champTop').val() || 10;

                if (classId && year && termId && meritListId && subjectId) {
                    loadChampions(classId, year, termId, meritListId, subjectId, top);
                }
            }

            // Load Champions
            function loadChampions(classId, year, termId, meritListId, subjectId, top) {
                $('#champTableBody').html(`
                    <tr>
                        <td colspan="9" class="loading-container">
                            <div class="loading-spinner"></div>
                            <p>Loading champions...</p>
                        </td>
                    </tr>
                `);

                $.ajax({
                    url: 'ajax/get_champions.php',
                    method: 'POST',
                    data: {
                        class_id: classId,
                        year: year,
                        term_id: termId,
                        exam_id: meritListId,
                        subject_id: subjectId,
                        limit: top,
                        school_id: state.schoolId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let html = '';
                            response.data.forEach((item, index) => {
                                const medalColor = index === 0 ? '#ffd700' : (index === 1 ? '#c0c0c0' : (index === 2 ? '#cd7f32' : '#6b7280'));
                                
                                let gradeClass = 'grade-badge';
                                if (item.grade === 'EE') gradeClass += ' ee';
                                else if (item.grade === 'ME') gradeClass += ' me';
                                else if (item.grade === 'AE') gradeClass += ' ae';
                                else if (item.grade === 'AP') gradeClass += ' ap';
                                else if (item.grade === 'BE') gradeClass += ' be';
                                else gradeClass += ' x';
                                
                                html += `
                                    <tr>
                                        <td><span style="color: ${medalColor}; font-weight: 700;">#${index + 1}</span></td>
                                        <td>${item.admission_no}</td>
                                        <td>${item.student_name}</td>
                                        <td>${item.class_name} ${item.stream_name || ''}</td>
                                        <td><span class="score-display">${item.score}</span></td>
                                        <td><span class="${gradeClass}">${item.grade}</span></td>
                                        <td>${item.rubric}</td>
                                        <td>${item.stream_position}</td>
                                        <td>${item.class_position}</td>
                                    </tr>
                                `;
                            });
                            $('#champTableBody').html(html);
                        } else {
                            $('#champTableBody').html(`
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <i class="fas fa-crown"></i>
                                        <h3>No Champions Data</h3>
                                        <p>No records available for the selected filters</p>
                                    </td>
                                </tr>
                            `);
                        }
                    },
                    error: function() {
                        showToast('error', 'Error', 'Failed to load champions');
                        $('#champTableBody').html(`
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <h3>Error</h3>
                                    <p>Failed to load data. Please try again.</p>
                                </td>
                            </tr>
                        `);
                    }
                });
            }

            // Check and load Improvement Analysis
            function checkAndLoadImprovementAnalysis() {
                const firstClass = $('#impFirstClass').val();
                const firstYear = $('#impFirstYear').val();
                const firstTerm = $('#impFirstTerm').val();
                const firstMerit = $('#impFirstMeritList').val();
                
                const currentClass = $('#impCurrentClass').val();
                const currentYear = $('#impCurrentYear').val();
                const currentTerm = $('#impCurrentTerm').val();
                const currentMerit = $('#impCurrentMeritList').val();

                if (firstClass && firstYear && firstTerm && firstMerit && 
                    currentClass && currentYear && currentTerm && currentMerit) {
                    loadImprovementAnalysis(firstClass, firstYear, firstTerm, firstMerit, 
                                          currentClass, currentYear, currentTerm, currentMerit);
                }
            }

            // Load Improvement Analysis
            function loadImprovementAnalysis(firstClass, firstYear, firstTerm, firstMerit, 
                                           currentClass, currentYear, currentTerm, currentMerit) {
                $('#impTableBody').html(`
                    <tr>
                        <td colspan="7" class="loading-container">
                            <div class="loading-spinner"></div>
                            <p>Comparing merit lists...</p>
                        </td>
                    </tr>
                `);

                $.ajax({
                    url: 'ajax/get_improvement_analysis.php',
                    method: 'POST',
                    data: {
                        first_class_id: firstClass,
                        first_year: firstYear,
                        first_term_id: firstTerm,
                        first_exam_id: firstMerit,
                        current_class_id: currentClass,
                        current_year: currentYear,
                        current_term_id: currentTerm,
                        current_exam_id: currentMerit,
                        school_id: state.schoolId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let html = '';
                            response.data.forEach(item => {
                                const changeClass = item.improvement > 0 ? 'trend-up' : (item.improvement < 0 ? 'trend-down' : 'trend-neutral');
                                const changeIcon = item.improvement > 0 ? 'fa-arrow-up' : (item.improvement < 0 ? 'fa-arrow-down' : 'fa-minus');
                                const changePercentClass = item.percentage_change > 0 ? 'trend-up' : (item.percentage_change < 0 ? 'trend-down' : 'trend-neutral');
                                
                                html += `
                                    <tr>
                                        <td>${item.student_name}</td>
                                        <td>${item.admission_no}</td>
                                        <td>${item.class_name} ${item.stream_name || ''}</td>
                                        <td>${item.first_score.toFixed(1)}</td>
                                        <td>${item.current_score.toFixed(1)}</td>
                                        <td class="${changeClass}">
                                            <i class="fas ${changeIcon}"></i>
                                            ${item.improvement > 0 ? '+' : ''}${item.improvement.toFixed(1)}
                                        </td>
                                        <td class="${changePercentClass}">
                                            ${item.percentage_change > 0 ? '+' : ''}${item.percentage_change.toFixed(1)}%
                                        </td>
                                    </tr>
                                `;
                            });
                            $('#impTableBody').html(html);
                        } else {
                            $('#impTableBody').html(`
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-arrow-trend-up"></i>
                                        <h3>No Improvement Data</h3>
                                        <p>No records available for the selected filters</p>
                                    </td>
                                </tr>
                            `);
                        }
                    },
                    error: function() {
                        showToast('error', 'Error', 'Failed to compare merit lists');
                        $('#impTableBody').html(`
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <h3>Error</h3>
                                    <p>Failed to load data. Please try again.</p>
                                </td>
                            </tr>
                        `);
                    }
                });
            }

            // Check and load Gender Analysis
            function checkAndLoadGenderAnalysis() {
                const classId = $('#genderClass').val();
                const year = $('#genderYear').val();
                const termId = $('#genderTerm').val();
                const meritListId = $('#genderMeritList').val();

                if (classId && year && termId && meritListId) {
                    loadGenderAnalysis(classId, year, termId, meritListId);
                }
            }

            // Load Gender Analysis
            function loadGenderAnalysis(classId, year, termId, meritListId) {
                $('#genderTableBody').html(`
                    <tr>
                        <td colspan="11" class="loading-container">
                            <div class="loading-spinner"></div>
                            <p>Loading gender data...</p>
                        </td>
                    </tr>
                `);

                $.ajax({
                    url: 'ajax/get_gender_analysis.php',
                    method: 'POST',
                    data: {
                        class_id: classId,
                        year: year,
                        term_id: termId,
                        exam_id: meritListId,
                        school_id: state.schoolId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let html = '';
                            response.data.forEach(item => {
                                let gradeClass = 'grade-badge';
                                if (item.grade === 'EE') gradeClass += ' ee';
                                else if (item.grade === 'ME') gradeClass += ' me';
                                else if (item.grade === 'AE') gradeClass += ' ae';
                                else if (item.grade === 'AP') gradeClass += ' ap';
                                else if (item.grade === 'BE') gradeClass += ' be';
                                else gradeClass += ' x';
                                
                                html += `
                                    <tr>
                                        <td><strong>${item.class_display}</strong></td>
                                        <td>${item.entry_count}</td>
                                        <td>${item.ee_count}</td>
                                        <td>${item.me_count}</td>
                                        <td>${item.ae_count}</td>
                                        <td>${item.ap_count}</td>
                                        <td>${item.be_count}</td>
                                        <td>${item.x_count}</td>
                                        <td>${item.mean_rubric.toFixed(1)}</td>
                                        <td><span class="${item.mean_mark >= 50 ? 'score-positive' : 'score-negative'}">${item.mean_mark.toFixed(1)}</span></td>
                                        <td><span class="${gradeClass}">${item.grade}</span></td>
                                    </tr>
                                `;
                            });
                            $('#genderTableBody').html(html);
                        } else {
                            $('#genderTableBody').html(`
                                <tr>
                                    <td colspan="11" class="empty-state">
                                        <i class="fas fa-venus-mars"></i>
                                        <h3>No Gender Data</h3>
                                        <p>No records available for the selected filters</p>
                                    </td>
                                </tr>
                            `);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.error('Response:', xhr.responseText);
                        showToast('error', 'Error', 'Failed to load gender data');
                        $('#genderTableBody').html(`
                            <tr>
                                <td colspan="11" class="empty-state">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <h3>Error</h3>
                                    <p>Failed to load data. Please try again.</p>
                                </td>
                            </tr>
                        `);
                    }
                });
            }

            // Attach change events to all filter selects with debouncing
            $('#laClass, #laYear, #laTerm, #laMeritList').on('change', debounce(function() {
                checkAndLoadLearningAreaAnalysis();
            }, 500, 'la'));

            $('#lamaClass, #lamaYear, #lamaTerm, #lamaMeritList, #lamaSubject').on('change', debounce(function() {
                checkAndLoadMeritAnalysis();
            }, 500, 'lama'));

            $('#champClass, #champYear, #champTerm, #champMeritList, #champSubject, #champTop').on('change input', debounce(function() {
                checkAndLoadChampions();
            }, 500, 'champ'));

            $('#impFirstClass, #impFirstYear, #impFirstTerm, #impFirstMeritList, #impCurrentClass, #impCurrentYear, #impCurrentTerm, #impCurrentMeritList').on('change', debounce(function() {
                checkAndLoadImprovementAnalysis();
            }, 500, 'imp'));

            $('#genderClass, #genderYear, #genderTerm, #genderMeritList').on('change', debounce(function() {
                checkAndLoadGenderAnalysis();
            }, 500, 'gender'));

            // Export dropdown toggle functionality
            $('.export-dropdown > button').on('click', function(e) {
                e.stopPropagation();
                const menu = $(this).siblings('.export-menu');
                $('.export-menu').not(menu).removeClass('active');
                menu.toggleClass('active');
            });

            // Close export menus when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.export-dropdown').length) {
                    $('.export-menu').removeClass('active');
                }
            });

            // Export functionality
// Export functionality
$('.export-item').on('click', function() {
    const format = $(this).data('format');
    const view = $(this).data('view');
    
    // Close the menu
    $(this).closest('.export-menu').removeClass('active');
    
    if (format === 'pdf') {
        exportToAnalyticsPDF(view);
    } else if (format === 'excel' || format === 'csv') {
        exportToExcel(view, format);
    } else if (format === 'print') {
        printView(view);
    }
});

function exportToAnalyticsPDF(view) {
    let url, data, selections, formattedData = [];
    
    // Get the table data
    const table = getTableDataForView(view);
    if (!table || table.length === 0) {
        showToast('warning', 'No Data', 'No data available to export');
        return;
    }
    
    // Format data based on view
    switch(view) {
        case 'learning-area-analysis':
            selections = {
                class_name: $('#laClass option:selected').text(),
                exam_name: $('#laMeritList option:selected').text(),
                term_name: $('#laTerm option:selected').text(),
                year: $('#laYear').val()
            };
            
            // Format learning area data - map from API response to PDF expected fields
            formattedData = table.map(row => {
                // Create a new object with the field names the PDF expects
                return {
                    subject_name: row['subject_name'] || row['Subject'] || '',
                    class_display: row['class_display'] || row['Class'] || 'All',
                    ee_count: parseInt(row['ee_count'] || row['EE'] || 0),
                    me_count: parseInt(row['me_count'] || row['ME'] || 0),
                    ae_count: parseInt(row['ae_count'] || row['AE'] || 0),
                    ap_count: parseInt(row['ap_count'] || row['AP'] || 0),
                    be_count: parseInt(row['be_count'] || row['BE'] || 0),
                    x_count: parseInt(row['x_count'] || row['X'] || 0),
                    mean: parseFloat(row['mean'] || row['Mean'] || 0),
                    rubric: parseFloat(row['avg_rubric'] || row['rubric'] || row['Avg Rubric'] || 0),
                    avg_rubric: parseFloat(row['avg_rubric'] || row['Avg Rubric'] || 0),
                    teacher_name: row['teacher_name'] || row['Teacher'] || 'Not Assigned'
                };
            });
            
            url = 'ajax/export_learning_area_pdf.php';
            break;
            
            
        case 'learning-area-merit-analysis':
            selections = {
                class_name: $('#lamaClass option:selected').text(),
                exam_name: $('#lamaMeritList option:selected').text(),
                term_name: $('#lamaTerm option:selected').text(),
                year: $('#lamaYear').val(),
                subject_name: $('#lamaSubject option:selected').text()
            };
            
            // Format merit analysis data
            formattedData = table.map(row => ({
                no: row['No:'] || row['no'] || '',
                admission_no: row['ADMN'] || row['admn'] || '',
                student_name: row['Name'] || row['name'] || '',
                score: parseFloat(row['Score/Grade'] || row['score'] || 0),
                grade: (row['Score/Grade'] || '').split(' ').pop() || 'N/A',
                rubric: row['Rubric'] || row['rubric'] || 0
            }));
            
            url = 'ajax/export_merit_analysis_pdf.php';
            break;
            
        case 'champions':
            selections = {
                class_name: $('#champClass option:selected').text(),
                exam_name: $('#champMeritList option:selected').text(),
                term_name: $('#champTerm option:selected').text(),
                year: $('#champYear').val(),
                subject_name: $('#champSubject option:selected').text(),
                top: $('#champTop').val()
            };
            
            // Format champions data
            formattedData = table.map(row => ({
                rank: row['#'] || row['rank'] || '',
                admission_no: row['ADM No.'] || row['ADM No'] || '',
                student_name: row['Full name'] || row['full name'] || '',
                class_name: row['Class'] || row['class'] || '',
                score: parseFloat(row['Score'] || row['score'] || 0),
                grade: row['Grade'] || row['grade'] || '',
                rubric: row['Rubric'] || row['rubric'] || 0,
                stream_position: row['Str Pos'] || row['str pos'] || '-',
                class_position: row['Cls Pos'] || row['cls pos'] || '-'
            }));
            
            url = 'ajax/export_champions_pdf.php';
            break;
            
        case 'improvement-analysis':
            selections = {
                class_name: $('#impCurrentClass option:selected').text(),
                first_exam_name: $('#impFirstMeritList option:selected').text(),
                current_exam_name: $('#impCurrentMeritList option:selected').text(),
                first_term: $('#impFirstTerm option:selected').text(),
                current_term: $('#impCurrentTerm option:selected').text(),
                year: $('#impCurrentYear').val()
            };
            
            // Format improvement data
            formattedData = table.map(row => ({
                student_name: row['Student'] || row['student'] || '',
                admission_no: row['Admission'] || row['admission'] || '',
                class_name: (row['Class/Stream'] || '').split(' ')[0] || '',
                stream_name: (row['Class/Stream'] || '').split(' ').slice(1).join(' ') || '',
                first_score: parseFloat(row['First Score'] || row['first score'] || 0),
                current_score: parseFloat(row['Current Score'] || row['current score'] || 0),
                improvement: parseFloat(row['Improvement'] || row['improvement'] || 0),
                percentage_change: parseFloat((row['% Change'] || '0').replace('%', '')) || 0
            }));
            
            url = 'ajax/export_improvement_pdf.php';
            break;
            
        case 'gender-analysis':
            selections = {
                class_name: $('#genderClass option:selected').text(),
                exam_name: $('#genderMeritList option:selected').text(),
                term_name: $('#genderTerm option:selected').text(),
                year: $('#genderYear').val()
            };
            
            // Format gender data
            formattedData = table.map(row => ({
                class_display: row['Class'] || row['class'] || '',
                entry_count: parseInt(row['Entry'] || row['entry'] || 0),
                ee_count: parseInt(row['EE'] || row['ee'] || 0),
                me_count: parseInt(row['ME'] || row['me'] || 0),
                ae_count: parseInt(row['AE'] || row['ae'] || 0),
                ap_count: parseInt(row['AP'] || row['ap'] || 0),
                be_count: parseInt(row['BE'] || row['be'] || 0),
                x_count: parseInt(row['X'] || row['x'] || 0),
                mean_rubric: parseFloat(row['M RB'] || row['m rb'] || 0),
                mean_mark: parseFloat(row['M.Mark'] || row['m.mark'] || 0),
                grade: row['Grade'] || row['grade'] || ''
            }));
            
            url = 'ajax/export_gender_pdf.php';
            break;
    }
    
    if (formattedData.length === 0) {
        showToast('warning', 'No Data', 'No data available to export');
        return;
    }
    
    showToast('info', 'Generating PDF', 'Preparing PDF document...');
    
    // Create a form with multiple fields
    const form = $('<form>', {
        'action': url,
        'method': 'POST',
        'target': '_blank'
    });
    
    // Add school_id as a separate field
    form.append($('<input>', {
        'type': 'hidden',
        'name': 'school_id',
        'value': state.schoolId
    }));
    
    // Add selections as JSON string
    form.append($('<input>', {
        'type': 'hidden',
        'name': 'selections',
        'value': JSON.stringify(selections)
    }));
    
    // Add formatted data as JSON string
    form.append($('<input>', {
        'type': 'hidden',
        'name': 'data',
        'value': JSON.stringify(formattedData)
    }));
    
    // Add subject if exists
    if (selections.subject_name) {
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'subject',
            'value': selections.subject_name
        }));
    }
    
    // Add print_mode
    form.append($('<input>', {
        'type': 'hidden',
        'name': 'print_mode',
        'value': 'false'
    }));
    
    $('body').append(form);
    form.submit();
    form.remove();
}

// Helper function to get table data
// Helper function to get table data
function getTableDataForView(view) {
    let tableId;
    switch(view) {
        case 'learning-area-analysis':
            tableId = '#laTable';
            break;
        case 'learning-area-merit-analysis':
            tableId = '#lamaTable';
            break;
        case 'champions':
            tableId = '#champTable';
            break;
        case 'improvement-analysis':
            tableId = '#impTable';
            break;
        case 'gender-analysis':
            tableId = '#genderTable';
            break;
        default:
            return [];
    }
    
    const table = $(tableId);
    const headers = [];
    const data = [];
    
    // Get headers - IMPORTANT: Make sure we're getting the correct headers
    table.find('thead tr th').each(function() {
        // Get the text content without any HTML tags
        let text = $(this).clone().children().remove().end().text().trim();
        // Remove any extra whitespace
        text = text.replace(/\s+/g, ' ').trim();
        if (text) {
            headers.push(text);
            console.log('Found header:', text); // Debug log
        }
    });
    
    console.log('Headers for', view, ':', headers); // Debug log
    
    // Get data rows
    table.find('tbody tr').each(function() {
        const row = {};
        const cells = $(this).find('td');
        
        if (cells.length === 0 || cells.first().hasClass('empty-state') || cells.first().hasClass('loading-container')) {
            return;
        }
        
        cells.each(function(index) {
            if (index < headers.length) {
                let text = $(this).clone().children().remove().end().text().trim();
                // Remove any extra whitespace
                text = text.replace(/\s+/g, ' ').trim();
                row[headers[index]] = text;
            }
        });
        
        if (Object.keys(row).length > 0) {
            data.push(row);
            console.log('Row data:', row); // Debug log
        }
    });
    
    return data;
}

// Get table data as array of objects
function getTableData(tableId) {
    const table = $(tableId);
    const headers = [];
    const data = [];
    
    // Get headers
    table.find('thead tr th').each(function() {
        let text = $(this).clone().children().remove().end().text().trim();
        if (text) headers.push(text);
    });
    
    // Get data rows
    table.find('tbody tr').each(function() {
        const row = {};
        const cells = $(this).find('td');
        
        if (cells.length === 0 || cells.first().hasClass('empty-state') || cells.first().hasClass('loading-container')) {
            return;
        }
        
        cells.each(function(index) {
            if (index < headers.length) {
                let text = $(this).clone().children().remove().end().text().trim();
                row[headers[index]] = text;
            }
        });
        
        if (Object.keys(row).length > 0) {
            data.push(row);
        }
    });
    
    return data;
}

// Export to Excel
function exportToExcel(view, format) {
    let tableId, title, data;
    
    switch(view) {
        case 'learning-area-analysis':
            tableId = '#laTable';
            title = 'Learning Area Analysis';
            break;
        case 'learning-area-merit-analysis':
            tableId = '#lamaTable';
            title = 'Learning Area Merit Analysis';
            break;
        case 'champions':
            tableId = '#champTable';
            title = 'Champions - Top Performers';
            break;
        case 'improvement-analysis':
            tableId = '#impTable';
            title = 'Improvement Analysis';
            break;
        case 'gender-analysis':
            tableId = '#genderTable';
            title = 'Gender Analysis';
            break;
    }
    
    const table = $(tableId);
    data = getTableData(tableId);
    
    if (data.length === 0) {
        showToast('warning', 'No Data', 'No data available to export');
        return;
    }
    
    // Convert to worksheet
    const headers = Object.keys(data[0]);
    const ws_data = [headers];
    
    data.forEach(row => {
        ws_data.push(headers.map(h => row[h] || ''));
    });
    
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(ws_data);
    XLSX.utils.book_append_sheet(wb, ws, 'Analytics');
    
    const filename = title.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_' + Date.now() + (format === 'excel' ? '.xlsx' : '.csv');
    XLSX.writeFile(wb, filename);
    
    showToast('success', 'Export Complete', `Exported as ${format.toUpperCase()}`);
}

// Print view
function printView(view) {
    let tableId, title;
    
    switch(view) {
        case 'learning-area-analysis':
            tableId = '#laTable';
            title = 'Learning Area Analysis';
            break;
        case 'learning-area-merit-analysis':
            tableId = '#lamaTable';
            title = 'Learning Area Merit Analysis';
            break;
        case 'champions':
            tableId = '#champTable';
            title = 'Champions - Top Performers';
            break;
        case 'improvement-analysis':
            tableId = '#impTable';
            title = 'Improvement Analysis';
            break;
        case 'gender-analysis':
            tableId = '#genderTable';
            title = 'Gender Analysis';
            break;
    }
    
    const table = $(tableId);
    const data = getTableData(tableId);
    
    if (data.length === 0) {
        showToast('warning', 'No Data', 'No data available to print');
        return;
    }
    
    // Create print window
    const printWindow = window.open('', '_blank');
    const headers = Object.keys(data[0]);
    
    let tableHtml = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    tableHtml += '<thead><tr>';
    headers.forEach(h => {
        tableHtml += '<th style="background: #1e3a8a; color: white; padding: 8px;">' + h + '</th>';
    });
    tableHtml += '</tr></thead><tbody>';
    
    data.forEach(row => {
        tableHtml += '<tr>';
        headers.forEach(h => {
            tableHtml += '<td style="border: 1px solid #ddd; padding: 8px;">' + (row[h] || '') + '</td>';
        });
        tableHtml += '</tr>';
    });
    tableHtml += '</tbody></table>';
    
    printWindow.document.write(`
        
<!DOCTYPE html>
        <html>
        <head>
            <title>${title}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #1e3a8a; }
                .date { color: #666; margin-bottom: 20px; }
                @media print {
                    body { margin: 0; }
                    button { display: none; }
                }
            </style>
        </head>
        <body>

            <h1>${title}</h1>
            <div class="date">Generated: ${new Date().toLocaleString()}</div>
            ${tableHtml}
            <script>
                window.onload = function() { window.print(); window.close(); }
            <\/script>
        </body>
        </html>
    `);
    
    printWindow.document.close();
}

            // Initial load check for the active view
            setTimeout(() => {
                triggerAutoLoad(state.currentView);
            }, 500);
        });
    </script>
</body>

</body>
</html>
