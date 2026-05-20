<?php
session_start();

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

// Database connection
require_once 'includes/config.php';
require_once 'includes/session_timeout.php'; 

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch school details
$schoolQuery = $conn->prepare("SELECT * FROM tblschoolinfo WHERE id = ?");
$schoolQuery->bind_param("i", $school_id);
$schoolQuery->execute();
$schoolResult = $schoolQuery->get_result();
$school = $schoolResult->fetch_assoc();
$schoolQuery->close();

// Fetch classes
$classesQuery = $conn->prepare("
    SELECT id, class_level as display_name 
    FROM tblclasses 
    WHERE school_id = ? 
    ORDER BY class_level
");
$classesQuery->bind_param("i", $school_id);
$classesQuery->execute();
$classesResult = $classesQuery->get_result();
$classes = [];
while ($class = $classesResult->fetch_assoc()) {
    $classes[] = $class;
}
$classesQuery->close();

// Fetch terms
$termsQuery = $conn->prepare("
    SELECT * FROM tblterms 
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

// Get distinct years
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utility Settings - EduScore</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png" />
    <link rel="apple-touch-icon" href="images/logo.png">
    <link rel="stylesheet" href="assets/banner/banner.css">
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
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-light); color: var(--text-dark); min-height: 100vh; }
        .main-content { margin-left: 280px; min-height: 100vh; padding: 100px 2rem 2rem; transition: margin-left 0.3s ease; }
        @media (max-width: 992px) { .main-content { margin-left: 0; padding: 100px 1rem 1rem; } }
        
        .academic-level-indicator { background: var(--bg-white); border-radius: var(--border-radius); padding: 1rem 1.5rem; margin-bottom: 1.5rem; box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem; border-left: 4px solid var(--accent-green); }
        .academic-level-icon { width: 50px; height: 50px; background: #d1fae5; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--accent-green); font-size: 1.25rem; }
        .academic-level-content h3 { font-size: 1.1rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.25rem; }
        .academic-level-content p { color: var(--text-light); font-size: 0.9rem; }
        .academic-level-badge { background: #d1fae5; color: var(--accent-green); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        
        .page-header { background: var(--bg-white); border-radius: var(--border-radius); padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow); border-left: 4px solid var(--accent-green); position: relative; }
        .utility-page-title { font-size: 1.8rem; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 0.75rem; }
        .utility-page-title i { color: var(--accent-green); }
        .page-description { color: var(--text-light); font-size: 1rem; }
        
        .filter-section { background: var(--bg-white); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow); }
        .filter-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-dark); display: flex; align-items: center; gap: 0.75rem; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-label { font-size: 0.9rem; font-weight: 500; color: var(--text-dark); display: flex; align-items: center; gap: 0.5rem; }
        .filter-select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.9rem; background: var(--bg-white); cursor: pointer; }
        
        .utility-container { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        @media (max-width: 992px) { .utility-container { grid-template-columns: 1fr; } }
        
        .settings-card { background: var(--bg-white); border-radius: var(--border-radius); box-shadow: var(--shadow); overflow: hidden; transition: var(--transition); height: fit-content; }
        .settings-card:hover { box-shadow: var(--shadow-lg); }
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem; background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); color: white; }
        .card-header i { font-size: 1.5rem; color: var(--accent-green); }
        .card-header h2 { font-size: 1.2rem; font-weight: 600; margin: 0; }
        .card-body { padding: 1.5rem; }
        
        .logo-section { display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem; flex-wrap: wrap; }
        .logo-preview { width: 100px; height: 100px; border-radius: var(--border-radius); background: var(--bg-light); border: 2px dashed var(--border-color); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
        .logo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .logo-preview i { font-size: 2rem; color: var(--text-light); opacity: 0.5; }
        .logo-actions { flex: 1; }
        .logo-actions h3 { font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; }
        .logo-actions p { font-size: 0.8rem; color: var(--text-light); margin-bottom: 1rem; }
        .logo-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        
        .btn-icon { padding: 0.5rem 0.75rem; border: none; border-radius: var(--border-radius); font-weight: 500; font-size: 0.8rem; cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-upload { background: var(--light-blue); color: var(--primary-blue); }
        .btn-upload:hover { background: var(--primary-blue); color: white; }
        .btn-delete { background: #fee2e2; color: var(--error-red); }
        .btn-delete:hover { background: var(--error-red); color: white; }
        
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-label { font-size: 0.9rem; font-weight: 500; color: var(--text-dark); display: flex; align-items: center; gap: 0.5rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.9rem; background: var(--bg-white); }
        .form-control:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .form-control[readonly] { background: var(--bg-light); cursor: not-allowed; }
        
        .term-cards-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 0.75rem; margin-bottom: 1.5rem; }
        @media (max-width:1100px) { .term-cards-grid { grid-template-columns: repeat(2,1fr); } }
        @media (max-width:768px) { .term-cards-grid { grid-template-columns: 1fr; } }
        
        .term-card { background: var(--bg-light); border-radius: var(--border-radius); padding: 0.75rem; border: 1px solid var(--border-color); transition: var(--transition); display: flex; flex-direction: column; height: 100%; }
        .term-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); border-color: var(--primary-blue); }
        .term-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
        .term-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; }
        .term-icon.term1 { background: rgba(16,185,129,0.15); color: var(--accent-green); }
        .term-icon.term2 { background: rgba(59,130,246,0.15); color: var(--secondary-blue); }
        .term-icon.term3 { background: rgba(245,158,11,0.15); color: var(--warning-orange); }
        .term-header h3 { font-size: 0.95rem; font-weight: 600; margin-right: auto; }
        .term-status { font-size: 0.65rem; padding: 0.15rem 0.3rem; border-radius: 4px; display: inline-block; }
        .status-active { background: rgba(16,185,129,0.15); color: var(--accent-green); }
        .status-inactive { background: rgba(107,114,128,0.15); color: var(--text-light); }
        .term-date-fields { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.75rem; flex: 1; }
        .date-input-group { display: flex; flex-direction: column; gap: 0.2rem; }
        .date-input-group label { font-size: 0.65rem; color: var(--text-light); font-weight: 500; }
        .date-input-group input { padding: 0.4rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.75rem; width: 100%; }
        .term-actions { display: flex; gap: 0.5rem; margin-top: auto; }
        .term-actions button { flex: 1; padding: 0.4rem; border: none; border-radius: 6px; font-size: 0.65rem; font-weight: 500; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.25rem; white-space: nowrap; }
        .btn-save-term { background: var(--light-blue); color: var(--primary-blue); }
        .btn-save-term:hover { background: var(--primary-blue); color: white; }
        .btn-set-active { background: #d1fae5; color: var(--accent-green); }
        .btn-set-active:hover { background: var(--accent-green); color: white; }
        
        .action-bar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; background: var(--bg-white); padding: 1rem 1.5rem; border-radius: var(--border-radius); box-shadow: var(--shadow); margin-top: 1rem; }
        .btn { padding: 0.75rem 1.25rem; border: none; border-radius: var(--border-radius); font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: var(--transition); display: flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: linear-gradient(135deg, var(--accent-green), #059669); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .btn-secondary { background: var(--light-blue); color: var(--primary-blue); }
        .btn-secondary:hover { background: var(--secondary-blue); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-dark); }
        .btn-outline:hover { background: var(--bg-light); border-color: var(--primary-blue); }
        
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 1rem; margin-top: 2rem; }
        .summary-card { background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue)); border-radius: var(--border-radius); padding: 1.5rem; color: white; text-align: center; }
        .summary-card i { font-size: 2rem; margin-bottom: 0.5rem; color: var(--accent-green); }
        .summary-card h4 { font-size: 0.9rem; font-weight: 500; margin-bottom: 0.25rem; opacity: 0.9; }
        .summary-card .value { font-size: 1.5rem; font-weight: 700; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-container { background: var(--bg-white); border-radius: var(--border-radius); width: 90%; max-width: 400px; box-shadow: var(--shadow-lg); transform: scale(0.9); transition: transform 0.3s ease; overflow: hidden; }
        .modal-overlay.active .modal-container { transform: scale(1); }
        .modal-header { padding: 1.5rem; background: linear-gradient(135deg, #fee2e2, #fecaca); display: flex; align-items: center; gap: 1rem; }
        .modal-icon { width: 48px; height: 48px; background: var(--error-red); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; }
        .modal-title { font-size: 1.2rem; font-weight: 600; color: #991b1b; margin: 0; }
        .modal-body { padding: 1.5rem; }
        .modal-message { color: var(--text-dark); font-size: 1rem; line-height: 1.5; margin-bottom: 1rem; }
        .modal-warning-text { background: #fff3cd; border-left: 4px solid #ffc107; padding: 0.75rem 1rem; border-radius: 8px; color: #856404; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem; }
        .modal-footer { padding: 1rem 1.5rem 1.5rem; display: flex; gap: 0.75rem; justify-content: flex-end; }
        .modal-btn { padding: 0.75rem 1.25rem; border: none; border-radius: var(--border-radius); font-weight: 600; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; }
        .modal-btn-cancel { background: var(--bg-light); color: var(--text-dark); border: 1px solid var(--border-color); }
        .modal-btn-cancel:hover { background: var(--border-color); }
        .modal-btn-confirm { background: var(--error-red); color: white; }
        .modal-btn-confirm:hover { background: #dc2626; transform: translateY(-2px); }
        
        .toast-container { position: fixed; top: 100px; right: 2rem; z-index: 3000; max-width: 400px; }
        .toast { background: var(--bg-white); border-radius: var(--border-radius); padding: 1rem 1.5rem; margin-bottom: 1rem; box-shadow: var(--shadow-lg); border-left: 4px solid var(--success-green); display: flex; align-items: center; gap: 1rem; animation: slideInRight 0.3s ease; }
        .toast.error { border-left-color: var(--error-red); }
        .toast.warning { border-left-color: var(--warning-orange); }
        .toast-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; }
        .toast.success .toast-icon { background: var(--success-green); }
        .toast.error .toast-icon { background: var(--error-red); }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
        .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(59,130,246,0.3); border-top-color: var(--primary-blue); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width:768px) { .main-content { padding: 100px 1rem 1rem; } .form-row { grid-template-columns: 1fr; } .toast-container { right: 1rem; left: 1rem; max-width: none; } }
    </style>
</head>
<body>
    <?php 
    if (isset($school) && $school && (!isset($school['is_activated']) || $school['is_activated'] == 0)) {
        include 'trial_banner.php';
    }
    ?>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="academic-level-indicator">
            <div class="academic-level-icon"><i class="<?php echo $academic_level === 'primary' ? 'fas fa-school' : ($academic_level === 'junior_secondary' ? 'fas fa-graduation-cap' : 'fas fa-university'); ?>"></i></div>
            <div class="academic-level-content">
                <h3>Academic Level: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $academic_level))); ?></h3>
                <p>Configure school details and term dates</p>
            </div>
            <div class="academic-level-badge"><i class="fas fa-tools"></i> Settings</div>
        </div>

        <div class="page-header">
            <h1 class="utility-page-title"><i class="fas fa-tools"></i> Utility Settings</h1>
            <p class="page-description">Manage school information and academic terms</p>
        </div>

        <div class="utility-container">
            <div class="settings-card">
                <div class="card-header"><i class="fas fa-school"></i><h2>School Details</h2></div>
                <div class="card-body">
                    <div class="logo-section">
                        <div class="logo-preview" id="logoPreview">
                            <?php if (!empty($school['school_logo'])): ?>
                                <img src="<?php echo htmlspecialchars($school['school_logo']); ?>" alt="School Logo">
                            <?php else: ?>
                                <i class="fas fa-image"></i>
                            <?php endif; ?>
                        </div>
                        <div class="logo-actions">
                            <h3>School Logo</h3>
                            <p>Upload a logo for your school. Recommended size: 200x200px</p>
                            <div class="logo-buttons">
                                <button class="btn-icon btn-upload" id="uploadLogoBtn"><i class="fas fa-upload"></i> Upload</button>
                                <button class="btn-icon btn-delete" id="deleteLogoBtn" <?php echo empty($school['school_logo']) ? 'style="display:none;"' : ''; ?>><i class="fas fa-trash"></i> Delete</button>
                            </div>
                            <input type="file" id="logoFile" accept="image/*" style="display: none;">
                        </div>
                    </div>

                    <form id="schoolDetailsForm">
                        <div class="form-grid">
                            <div class="form-group"><label class="form-label"><i class="fas fa-building"></i> School Name</label><input type="text" class="form-control" id="schoolName" name="school_name" value="<?php echo htmlspecialchars($school['school_name'] ?? ''); ?>" placeholder="Enter school name" readonly></div>
                            <div class="form-group"><label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label><textarea class="form-control" id="address" name="address" placeholder="Enter school address"><?php echo htmlspecialchars($school['school_address'] ?? ''); ?></textarea></div>
                            <div class="form-row">
                                <div class="form-group"><label class="form-label"><i class="fas fa-envelope"></i> Email</label><input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($school['school_email'] ?? ''); ?>" placeholder="school@example.com"></div>
                                <div class="form-group"><label class="form-label"><i class="fas fa-phone"></i> Phone</label><input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($school['school_phone'] ?? ''); ?>" placeholder="254XXXXXXXXX"></div>
                            </div>
                            <div class="form-group"><label class="form-label"><i class="fas fa-quote-right"></i> School Motto</label><input type="text" class="form-control" id="motto" name="motto" value="<?php echo htmlspecialchars($school['school_motto'] ?? ''); ?>" placeholder="Enter school motto"></div>
                        </div>
                        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;"><button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-save"></i> Save Details</button></div>
                    </form>
                </div>
            </div>

            <div class="settings-card">
                <div class="card-header"><i class="fas fa-calendar-alt"></i><h2>Academic Terms</h2></div>
                <div class="card-body">
                    <div class="filter-grid" style="margin-bottom: 1.5rem;">
                        <div class="filter-group"><label class="filter-label"><i class="fas fa-calendar"></i> Academic Year</label><select id="termYear" class="filter-select"><?php $current_year = date('Y'); for ($year = $current_year - 1; $year <= $current_year + 3; $year++): ?><option value="<?php echo $year; ?>" <?php echo ($year == $current_year) ? 'selected' : ''; ?>><?php echo $year; ?></option><?php endfor; ?></select></div>
                    </div>

                    <div class="term-cards-grid" id="termCards">
                        <div class="term-card" id="term1Card">
                            <div class="term-header"><div class="term-icon term1"><i class="fas fa-1"></i></div><h3>Term 1</h3><span class="term-status status-inactive" id="term1Status">Not Set</span></div>
                            <div class="term-date-fields"><div class="date-input-group"><label>Start</label><input type="date" class="term-date" id="term1Start" data-term="1" data-field="start"></div><div class="date-input-group"><label>End</label><input type="date" class="term-date" id="term1End" data-term="1" data-field="end"></div></div>
                            <div class="term-actions"><button class="btn-save-term" onclick="saveTerm(1)"><i class="fas fa-save"></i> Save</button><button class="btn-set-active" onclick="setActiveTerm(1)"><i class="fas fa-check-circle"></i> Active</button></div>
                        </div>
                        <div class="term-card" id="term2Card">
                            <div class="term-header"><div class="term-icon term2"><i class="fas fa-2"></i></div><h3>Term 2</h3><span class="term-status status-inactive" id="term2Status">Not Set</span></div>
                            <div class="term-date-fields"><div class="date-input-group"><label>Start</label><input type="date" class="term-date" id="term2Start" data-term="2" data-field="start"></div><div class="date-input-group"><label>End</label><input type="date" class="term-date" id="term2End" data-term="2" data-field="end"></div></div>
                            <div class="term-actions"><button class="btn-save-term" onclick="saveTerm(2)"><i class="fas fa-save"></i> Save</button><button class="btn-set-active" onclick="setActiveTerm(2)"><i class="fas fa-check-circle"></i> Active</button></div>
                        </div>
                        <div class="term-card" id="term3Card">
                            <div class="term-header"><div class="term-icon term3"><i class="fas fa-3"></i></div><h3>Term 3</h3><span class="term-status status-inactive" id="term3Status">Not Set</span></div>
                            <div class="term-date-fields"><div class="date-input-group"><label>Start</label><input type="date" class="term-date" id="term3Start" data-term="3" data-field="start"></div><div class="date-input-group"><label>End</label><input type="date" class="term-date" id="term3End" data-term="3" data-field="end"></div></div>
                            <div class="term-actions"><button class="btn-save-term" onclick="saveTerm(3)"><i class="fas fa-save"></i> Save</button><button class="btn-set-active" onclick="setActiveTerm(3)"><i class="fas fa-check-circle"></i> Active</button></div>
                        </div>
                    </div>

                    <div class="action-bar">
                        <button class="btn btn-secondary" id="loadTermsBtn"><i class="fas fa-sync-alt"></i> Load Terms</button>
                        <button class="btn btn-primary" id="saveAllTermsBtn"><i class="fas fa-save"></i> Save All Terms</button>
                        <button class="btn btn-outline" id="applyToAllBtn"><i class="fas fa-copy"></i> Apply to All Classes</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="summary-cards">
            <div class="summary-card"><i class="fas fa-calendar-check"></i><h4>Current Year</h4><div class="value" id="currentYear"><?php echo date('Y'); ?></div></div>
            <div class="summary-card"><i class="fas fa-book-open"></i><h4>Active Term</h4><div class="value" id="activeTermDisplay"><?php $active_term = 'Not Set'; foreach ($terms as $term) { if (!empty($term['is_current']) && $term['is_current'] == 1) { $active_term = 'Term ' . $term['term_number']; break; } } echo $active_term; ?></div></div>
            <div class="summary-card"><i class="fas fa-school"></i><h4>Classes</h4><div class="value"><?php echo count($classes); ?></div></div>
        </div>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <div class="modal-container">
            <div class="modal-header"><div class="modal-icon"><i class="fas fa-exclamation-triangle"></i></div><h3 class="modal-title">Confirm Deletion</h3></div>
            <div class="modal-body">
                <div class="modal-message" id="modalMessage">Are you sure you want to delete the school logo? This action cannot be undone.</div>
                <div class="modal-warning-text"><i class="fas fa-info-circle"></i><span id="modalWarning">The logo will be permanently removed from your school profile.</span></div>
            </div>
            <div class="modal-footer"><button class="modal-btn modal-btn-cancel" id="modalCancelBtn"><i class="fas fa-times"></i> Cancel</button><button class="modal-btn modal-btn-confirm" id="modalConfirmBtn"><i class="fas fa-trash"></i> Delete</button></div>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let deleteCallback = null;

            function showToast(type, title, message) {
                const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
                const toast = $(`<div class="toast ${type}"><div class="toast-icon"><i class="${icons[type]}"></i></div><div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div></div>`);
                $('#toastContainer').append(toast);
                setTimeout(() => toast.fadeOut(300, function() { $(this).remove(); }), 4000);
            }

            function showDeleteModal(options = {}) {
                const { message = 'Are you sure you want to delete this item?', warning = 'This action cannot be undone.', onConfirm = null } = options;
                $('#modalMessage').text(message);
                $('#modalWarning').text(warning);
                deleteCallback = onConfirm;
                $('#deleteModal').addClass('active');
                $('body').css('overflow', 'hidden');
            }

            function hideDeleteModal() {
                $('#deleteModal').removeClass('active');
                $('body').css('overflow', '');
                deleteCallback = null;
            }

            $('#modalCancelBtn').on('click', hideDeleteModal);
            $('#modalConfirmBtn').on('click', function() { if (deleteCallback) deleteCallback(); hideDeleteModal(); });
            $('#deleteModal').on('click', function(e) { if ($(e.target).is('#deleteModal')) hideDeleteModal(); });
            $(document).on('keydown', function(e) { if (e.key === 'Escape' && $('#deleteModal').hasClass('active')) hideDeleteModal(); });

            function loadTermsData() {
                const year = $('#termYear').val();
                $.ajax({ url: 'ajax/get_terms.php', method: 'POST', data: { year: year }, dataType: 'json', success: function(response) { if (response.success) updateTermCards(response.terms); else showToast('error', 'Error', response.message || 'Failed to load terms'); }, error: function() { showToast('error', 'Error', 'Network error.'); } });
            }

            function updateTermCards(terms) {
                for (let i = 1; i <= 3; i++) { $(`#term${i}Start`).val(''); $(`#term${i}End`).val(''); $(`#term${i}Status`).text('Not Set').removeClass('status-active').addClass('status-inactive'); }
                terms.forEach(term => { const termNum = term.term_number; if (termNum >= 1 && termNum <= 3) { $(`#term${termNum}Start`).val(term.start_date); $(`#term${termNum}End`).val(term.end_date); if (term.is_current == 1) { $(`#term${termNum}Status`).text('Active').removeClass('status-inactive').addClass('status-active'); $('#activeTermDisplay').text('Term ' + termNum); } else { $(`#term${termNum}Status`).text('Set').removeClass('status-inactive'); } } });
            }

            window.saveTerm = function(termNumber) {
                const startDate = $(`#term${termNumber}Start`).val();
                const endDate = $(`#term${termNumber}End`).val();
                const year = $('#termYear').val();
                if (!startDate || !endDate) { showToast('warning', 'Missing Dates', 'Please select both start and end dates.'); return; }
                if (new Date(endDate) <= new Date(startDate)) { showToast('error', 'Date Error', 'End date must be after start date.'); return; }
                showToast('info', 'Saving', `Saving Term ${termNumber}...`);
                $.ajax({ url: 'ajax/save_term.php', method: 'POST', data: { term_number: termNumber, start_date: startDate, end_date: endDate, academic_year: year }, dataType: 'json', success: function(response) { if (response.success) { showToast('success', 'Success', `Term ${termNumber} saved successfully.`); loadTermsData(); } else { showToast('error', 'Error', response.message || 'Failed to save term.'); } }, error: function() { showToast('error', 'Error', 'Network error.'); } });
            };

            window.saveAllTerms = function() {
                const year = $('#termYear').val();
                const terms = [];
                for (let i = 1; i <= 3; i++) { const startDate = $(`#term${i}Start`).val(); const endDate = $(`#term${i}End`).val(); if (startDate && endDate) { terms.push({ term_number: i, start_date: startDate, end_date: endDate, academic_year: year, is_current: $(`#term${i}Status`).text() === 'Active' ? 1 : 0 }); } }
                if (terms.length === 0) { showToast('warning', 'No Data', 'No term dates to save.'); return; }
                showToast('info', 'Saving', 'Saving all terms...');
                $.ajax({ url: 'ajax/save_terms.php', method: 'POST', contentType: 'application/json', data: JSON.stringify({ terms: terms }), dataType: 'json', success: function(response) { if (response.success) { showToast('success', 'Success', response.message); loadTermsData(); } else { showToast('error', 'Error', response.message || 'Failed to save terms.'); } }, error: function() { showToast('error', 'Error', 'Network error.'); } });
            };

            window.setActiveTerm = function(termNumber) {
                const year = $('#termYear').val();
                if (!confirm(`Set Term ${termNumber} as the active term?`)) return;
                $.ajax({ url: 'ajax/set_active_term.php', method: 'POST', data: { term_number: termNumber, academic_year: year }, dataType: 'json', success: function(response) { if (response.success) { showToast('success', 'Success', `Term ${termNumber} is now active.`); loadTermsData(); } else { showToast('error', 'Error', response.message || 'Failed to set active term.'); } }, error: function() { showToast('error', 'Error', 'Network error.'); } });
            };

            $('#uploadLogoBtn').on('click', function() { $('#logoFile').click(); });
            $('#logoFile').on('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                if (file.size > 2 * 1024 * 1024) { showToast('error', 'File Too Large', 'Logo must be less than 2MB.'); return; }
                if (!file.type.match('image.*')) { showToast('error', 'Invalid File', 'Please select an image file.'); return; }
                const formData = new FormData();
                formData.append('logo', file);
                formData.append('school_id', <?php echo $school_id; ?>);
                showToast('info', 'Uploading', 'Uploading logo...');
                $.ajax({ url: 'ajax/upload_school_logo.php', method: 'POST', data: formData, processData: false, contentType: false, dataType: 'json', success: function(response) { if (response.success) { $('#logoPreview').html('<img src="' + response.logo_path + '?t=' + new Date().getTime() + '">'); $('#deleteLogoBtn').show(); showToast('success', 'Success', 'Logo uploaded successfully.'); } else { showToast('error', 'Error', response.message || 'Failed to upload logo.'); } }, error: function() { showToast('error', 'Error', 'Network error.'); } });
            });

            $('#deleteLogoBtn').on('click', function() {
                showDeleteModal({ message: 'Are you sure you want to delete the school logo?', warning: 'The logo will be permanently removed from your school profile.', onConfirm: function() { $.ajax({ url: 'ajax/delete_school_logo.php', method: 'POST', data: { school_id: <?php echo $school_id; ?> }, dataType: 'json', success: function(response) { if (response.success) { $('#logoPreview').html('<i class="fas fa-image"></i>'); $('#deleteLogoBtn').hide(); showToast('success', 'Deleted', 'Logo removed successfully.'); } else { showToast('error', 'Error', response.message || 'Failed to delete logo.'); } }, error: function() { showToast('error', 'Error', 'Network error.'); } }); } });
            });

            $('#schoolDetailsForm').on('submit', function(e) {
                e.preventDefault();
                showToast('info', 'Saving', 'Saving school details...');
                $.ajax({ url: 'ajax/update_school_details.php', method: 'POST', data: { school_id: <?php echo $school_id; ?>, school_name: $('#schoolName').val(), address: $('#address').val(), email: $('#email').val(), phone: $('#phone').val(), motto: $('#motto').val() }, dataType: 'json', success: function(response) { if (response.success) showToast('success', 'Success', 'School details updated successfully.'); else showToast('error', 'Error', response.message || 'Failed to update school details.'); }, error: function() { showToast('error', 'Error', 'Network error.'); } });
            });

            $('#loadTermsBtn').on('click', loadTermsData);
            $('#saveAllTermsBtn').on('click', saveAllTerms);
            $('#termYear').on('change', loadTermsData);
            $('#applyToAllBtn').on('click', function() { showToast('info', 'Info', 'This will apply terms to all classes (coming soon).'); });

            loadTermsData();
        });
    </script>
</body>
</html>