<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('includes/config.php');
include('includes/session_timeout.php'); 



if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(403);
    header('Location: login.php');
    exit;
}


// Enhanced security: Validate session variables
if (
    empty($_SESSION['authenticated']) ||
    empty($_SESSION['school_id']) ||
    empty($_SESSION['teacher_id'])
) {
    session_destroy();
    header("Location: login.php");
    exit;
}


$teacherId = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
$schoolId = $_SESSION['school_id'];

// Helper function to safely escape potentially null values
function safeHtml($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Fetch teacher details along with role name
$teacherQuery = $dbh->prepare("
    SELECT t.*, r.role_name 
    FROM tblteachers t
    LEFT JOIN roles r ON t.role_id = r.id AND t.school_id = r.school_id
    WHERE t.id = :tid AND t.school_id = :sid
    LIMIT 1
");
$teacherQuery->bindParam(':tid', $teacherId, PDO::PARAM_INT);
$teacherQuery->bindParam(':sid', $schoolId, PDO::PARAM_INT);
$teacherQuery->execute();
$teacher = $teacherQuery->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    echo "<p class='text-center'>Teacher not found.</p>";
    exit();
}

// Fetch subjects assigned to teacher
$subjectsQuery = $dbh->prepare("
    SELECT s.subject_name, COUNT(sa.class_id) AS class_count
    FROM tblsubjectassignments sa
    INNER JOIN tblsubjects s ON sa.subject_id = s.id
    WHERE sa.teacher_id = :tid AND sa.school_id = :sid
    GROUP BY sa.subject_id, s.subject_name
");
$subjectsQuery->bindParam(':tid', $teacherId, PDO::PARAM_INT);
$subjectsQuery->bindParam(':sid', $schoolId, PDO::PARAM_INT);
$subjectsQuery->execute();
$subjects = $subjectsQuery->fetchAll(PDO::FETCH_ASSOC);
// Fetch roles for this school
$rolesQuery = $dbh->prepare("SELECT id, role_name FROM roles WHERE school_id = :sid ORDER BY role_name ASC");
$rolesQuery->bindParam(':sid', $schoolId, PDO::PARAM_INT);
$rolesQuery->execute();
$roles = $rolesQuery->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EduScore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="shortcut icon" href="images/icon.png" type="image/x-icon">
<style>
  /* Base Futuristic Styling - LIGHT THEME */
:root {
    --primary-color: #007bff; /* Vibrant Electric Blue */
    --secondary-color: #ff4081; /* Accent Pink */
    --light-bg: #f5f7fa; /* Very light background */
    --card-bg: #ffffff; /* Pure white card background */
    --text-dark: #333333; /* Dark text for contrast */
    --text-medium: #666666; /* Medium gray text */
}

/* --- THEME OVERRIDES FOR LIGHT MODE --- */

/* Ensure the main container has a light theme */
.content-container {
    background-color: var(--light-bg);
    color: var(--text-dark);
    min-height: 100vh;
    padding: 30px;
}

.title {
    color: var(--primary-color);
    /* Subtle blue light effect on the title */
    text-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
    margin-bottom: 25px;
}

/* Profile Card Styling - applies to both profile and subjects card */
.teacher-profile-card, .subjects-card {
    background: var(--card-bg);
    border-radius: 15px;
    padding: 40px;
    /* Clean, floating shadow effect */
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08), 0 0 5px rgba(0, 123, 255, 0.05) inset;
    border: 1px solid rgba(0, 123, 255, 0.1);
    backdrop-filter: blur(8px); /* Subtle blur effect */
    transition: all 0.3s ease-in-out;
    /* Margin for spacing the profile card on small screens */
    margin-bottom: 30px; 
}

.subjects-card {
    /* Adjusted padding for the subject list card */
    padding: 30px;
}

.teacher-profile-card:hover, .subjects-card:hover {
    /* Slightly stronger shadow and blue border on hover */
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15), 0 0 8px rgba(0, 123, 255, 0.2) inset;
    transform: translateY(-2px);
}

/* Profile Header (Avatar and Name) */
.profile-header {
    text-align: center;
    margin-bottom: 40px;
}

.profile-avatar-container {
    display: inline-block;
    padding: 8px;
    border-radius: 50%;
    /* Subtle pulsing effect for the container border */
    box-shadow: 0 0 0 2px var(--primary-color), 0 0 15px rgba(0, 123, 255, 0.5);
    animation: pulse-border 2s infinite alternate;
    margin-bottom: 20px;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    /* Inner background is light */
    background: var(--light-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 50px;
    color: var(--primary-color);
    /* Inner shadow adjusted for light theme */
    box-shadow: inset 0 0 15px rgba(0, 0, 0, 0.1);
    border: 3px solid var(--primary-color);
}

.teacher-name-display {
    color: var(--text-dark);
    font-size: 2.2em;
    letter-spacing: 1.5px;
    margin-bottom: 5px;
    text-shadow: none; /* Removed white glow */
}

.profile-tagline {
    color: var(--secondary-color);
    font-size: 1.1em;
    font-style: italic;
    opacity: 0.9;
}

/* Data Grid (Inputs) */
.profile-data-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.data-field {
    position: relative;
    padding-bottom: 10px;
}

.data-field label {
    display: block;
    color: var(--text-medium); /* Label color is subdued */
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 0.9em;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.data-field i {
    margin-right: 5px;
    color: var(--primary-color); /* Icons use the accent color */
    opacity: 1;
}

.data-field input {
    width: 100%;
    padding: 12px 15px;
    background: var(--light-bg); /* Very light background for inputs */
    border: 1px solid #e0e0e0;
    color: var(--text-dark);
    font-size: 1.1em;
    border-radius: 5px;
    transition: all 0.3s;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.data-field input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 15px rgba(0, 123, 255, 0.2); /* Subtle glow on focus */
}

/* The 'Glow Border' element */
.glow-border {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--primary-color);
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.5); /* Glowing underline */
    transition: width 0.4s ease-out;
}

.data-field:hover .glow-border {
    width: 100%;
}

/* Actions Section */
.profile-actions {
    text-align: center;
}
        .action-btn {
            color: var(--card-bg); /* Text is white on primary background */
            border: none;
            padding: 12px 25px;
            margin: 0 10px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 700;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        
        .edit-btn {
            background: var(--primary-color);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.4);
        }

        .edit-btn:hover {
            background: #0069d9;
            box-shadow: 0 5px 25px rgba(0, 123, 255, 0.7);
            transform: translateY(-3px) scale(1.05);
        }
        
        .delete-btn {
            background: var(--secondary-color);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }

        .delete-btn:hover {
            background: #c82333;
            /* Stronger, more intense shadow and lift on hover */
            box-shadow: 0 8px 30px rgba(220, 53, 69, 0.8);
            transform: translateY(-4px) scale(1.05);
        }
/* --- SUBJECTS TAUGHT STYLING --- */

.card-title-accent {
    font-size: 1.5em;
    color: var(--primary-color);
    border-bottom: 2px solid rgba(0, 123, 255, 0.1);
    padding-bottom: 10px;
    margin-bottom: 20px;
    font-weight: 700;
}

.subject-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.subject-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    border-bottom: 1px dashed rgba(0, 123, 255, 0.1);
    transition: background-color 0.2s;
}

.subject-item:last-child {
    border-bottom: none;
}

.subject-item:hover {
    background-color: rgba(0, 123, 255, 0.05);
    border-radius: 5px;
    padding: 15px 10px;
    margin: 0 -10px; /* Extend hover background */
    box-shadow: 0 0 10px rgba(0, 123, 255, 0.1);
}

.subject-name {
    font-weight: 600;
    color: var(--text-dark);
}

.subject-detail {
    font-size: 0.9em;
    color: white;
    padding: 5px 10px;
    background-color: var(--secondary-color); /* Use accent pink for detail chips */
    border-radius: 5px;
    font-weight: bold;
    letter-spacing: 0.5px;
}

.card-footer-info {
    text-align: center;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
    font-size: 0.9em;
    color: var(--text-medium);
}

        .modal-btn {
            /* Inherit base button styles */
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.95em;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
          .save-btn{
            background: var(--light-bg);
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 123, 255, 0.2);
          }   
          .save-btn:hover{
            background: var(--primary-color);
            color: var(--card-bg);
            box-shadow: 0 5px 20px rgba(0, 123, 255, 0.5);
            transform: translateY(-2px);
          }   
        /* Cancel Button - Primary/Neutral Style */
        .cancel-btn {
            background: var(--light-bg);
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            box-shadow: 0 2px 10px rgba(0, 123, 255, 0.2);
        }

        .cancel-btn:hover {
            background: var(--primary-color);
            color: var(--card-bg);
            box-shadow: 0 5px 20px rgba(0, 123, 255, 0.5);
            transform: translateY(-2px);
        }
        /* Delete Confirmation Button - Danger Style */
        .delete-confirm-btn {
            background: var(--secondary-color);
            color: var(--card-bg);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.5);
        }

        .delete-confirm-btn:hover {
            background: #c82333;
            box-shadow: 0 8px 30px rgba(220, 53, 69, 0.9);
            transform: translateY(-3px);
        }

        .delete-confirm-btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 5px rgba(220, 53, 69, 0.4);
        }

/* Keyframe Animations */
@keyframes pulse-border {
    0% {
        box-shadow: 0 0 0 2px var(--primary-color), 0 0 15px rgba(0, 123, 255, 0.5);
    }
    100% {
        box-shadow: 0 0 0 4px rgba(0, 123, 255, 0), 0 0 25px rgba(0, 123, 255, 0.8);
    }
}

/* --- PROGRESS CARD STYLING (NEW) --- */

.progress-item {
    padding: 20px 0;
    border-bottom: 1px dashed rgba(0, 123, 255, 0.1);
}

.progress-item:last-child {
    border-bottom: none;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.subject-name-progress {
    font-weight: 600;
    color: var(--text-dark);
    font-size: 1.1em;
}

.progress-percent {
    font-size: 1.2em;
    font-weight: 700;
    color: var(--primary-color);
    text-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
}

.progress-bar-container {
    height: 10px;
    background: var(--light-bg);
    border-radius: 5px;
    overflow: hidden;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 5px;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #00c6ff); /* Gradient Blue */
    transition: width 1s ease-out;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0, 198, 255, 0.5); /* Inner glow effect */
}

.progress-label {
    font-size: 0.85em;
    color: var(--text-medium);
    margin: 0;
}
/* Style select dropdowns like inputs */
.data-field select {
    width: 100%;
    padding: 12px 15px;
    background: var(--light-bg);
    border: 1px solid #e0e0e0;
    color: var(--text-dark);
    font-size: 1.1em;
    border-radius: 5px;
    transition: all 0.3s;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    appearance: none; /* Remove default arrow */
    -webkit-appearance: none;
    -moz-appearance: none;
    cursor: pointer;
    position: relative;
}

/* Add focus glow like inputs */
.data-field select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 15px rgba(0, 123, 255, 0.2);
}

/* Optional: add custom arrow */
.data-field select {
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 140 140' xmlns='http://www.w3.org/2000/svg'%3E%3Cpolygon points='70,105 35,45 105,45' fill='%23007bff'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 12px;
}
/* ==========================
    RESPONSIVE STYLING
========================== */

/* Small devices (mobile phones) */
@media (max-width: 767px) {
    .content-container {
        padding: 15px;
    }

    .teacher-profile-card, .subjects-card {
        padding: 20px;
    }

    .profile-data-grid {
        grid-template-columns: 1fr; /* Stack inputs vertically */
        gap: 20px;
    }

    .profile-avatar {
        width: 90px;
        height: 90px;
        font-size: 36px;
    }

    .teacher-name-display {
        font-size: 1.8em;
    }

    .profile-tagline {
        font-size: 1em;
    }

    .data-field input,
    .data-field select {
        padding: 10px 12px;
        font-size: 1em;
    }

    .card-title-accent {
        font-size: 1.3em;
    }

    .subject-name,
    .subject-name-progress {
        font-size: 1em;
    }

    .progress-percent {
        font-size: 1em;
    }
    
    .page-title-div {
        flex-direction: column; /* Stack button and title */
        align-items: stretch;
    }
    .back-btn {
        margin-right: 0;
        margin-bottom: 15px;
    }
    .title {
        text-align: center;
    }
}

/* Medium devices (tablets) */
@media (min-width: 768px) and (max-width: 991px) {
    .teacher-profile-card, .subjects-card {
        padding: 25px;
    }

    .profile-data-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        font-size: 42px;
    }

    .teacher-name-display {
        font-size: 2em;
    }

    .profile-tagline {
        font-size: 1.05em;
    }
}

/* Large devices (desktops) */
@media (min-width: 992px) {
    .profile-data-grid {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    }

    .teacher-profile-card, .subjects-card {
        padding: 40px;
    }
}

/* Ensure select and inputs scale well */
.data-field input,
.data-field select {
    max-width: 100%;
    box-sizing: border-box;
}

/* Optional: make buttons responsive */
.action-btn {
    width: auto;
    max-width: 250px;
    margin: 10px 10px;
    display: inline-block;
}


/* Make subject list items wrap on small screens */
.subject-item {
    flex-wrap: wrap;
}

.subject-detail {
    margin-top: 5px;
}

/* NEW CSS for Back Button and Layout */
.page-title-div {
    display: flex;
    align-items: center; /* Vertically align items */
    justify-content: flex-start; /* Start items from the left */
    margin-bottom: 25px; /* Add margin below the entire div */
    position: relative;
}

.title {
    /* Title centered in the available space */
    flex-grow: 1; 
    text-align: center;
    /* Maintain existing style: */
    color: var(--primary-color);
    /* Subtle blue light effect on the title */
    text-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
    margin-bottom: 0; /* Handled by page-title-div margin */
}

.back-btn {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    color: var(--primary-color);
    font-weight: 600;
    font-size: 1em;
    padding: 8px 15px;
    border: 2px solid var(--primary-color);
    border-radius: 20px;
    background-color: var(--card-bg);
    transition: all 0.3s ease-in-out;
    margin-right: 20px; /* Space between button and title */
    min-width: 150px;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0, 123, 255, 0.2);
}

.back-btn i {
    margin-right: 8px;
    font-size: 1.1em;
}

.back-btn:hover {
    background-color: var(--primary-color);
    color: white;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
    transform: translateY(-1px);
}
</style>
</head>
<body>
<div class="main-wrapper">
    <?php include('includes/sidebar.php'); ?>
    <div class="content-wrapper">
        <?php include('includes/header.php'); ?>
        <div class="content-container">
            <div class="main-page">
                <div class="container-fluid">
                    <div class="page-title-div">
                         <a href="teacher.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                        <h2 class="title">Teacher Profile</h2>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="teacher-profile-card">
                                <div class="profile-header">
                                    <div class="profile-avatar-container">
                                        <div class="profile-avatar">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                    </div>
                                    <h3 class="teacher-name-display"><?php echo safeHtml($teacher['firstname'] . ' ' . $teacher['secondname'] . ' ' . $teacher['lastname']); ?></h3>
                                    <p class="profile-tagline"><?php echo safeHtml($teacher['specialization'] ?? 'N/A'); ?></p>
                                </div>

                                <div class="profile-data-grid">
                                    <div class="data-field">
                                        <label for="teacher-name"><i class="fas fa-id-card"></i> Name</label>
                                        <input type="text" id="teacher-name" value="<?php echo safeHtml($teacher['firstname'] . ' ' . $teacher['secondname'] . ' ' . $teacher['lastname']); ?>" readonly>
                                        <div class="glow-border"></div>
                                    </div>

                                    <div class="data-field">
                                        <label for="teacher-email"><i class="fas fa-at"></i> Email</label>
                                        <input type="email" id="teacher-email" value="<?php echo safeHtml($teacher['email']); ?>" readonly>
                                        <div class="glow-border"></div>
                                    </div>

                                    <div class="data-field">
                                        <label for="teacher-phone"><i class="fas fa-phone-alt"></i> Phone Number</label>
                                        <input type="tel" id="teacher-phone" value="<?php echo safeHtml($teacher['phonenumber']); ?>" readonly>
                                        <div class="glow-border"></div>
                                    </div>
<div class="data-field">
    <label for="teacher-role"><i class="fas fa-user-tag"></i> Role</label>
    <select id="teacher-role" disabled>
        <?php foreach($roles as $role): ?>
            <option value="<?php echo intval($role['id']); ?>" <?php echo ($teacher['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                <?php echo safeHtml($role['role_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <div class="glow-border"></div>
</div>


                                </div>

                                <div class="profile-actions">
                                    <button class="action-btn edit-btn"><i class="fas fa-edit"></i> Edit Profile</button>
                                    <button class="action-btn delete-btn" id="show-delete-modal"><i class="fas fa-trash"></i> Delete User</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subjects Taught -->
                    <div class="row" style="margin-top:30px;">
                        <div class="col-lg-12">
                            <div class="subjects-card">
                                <h3 class="card-title-accent"><i class="fas fa-book-reader"></i> Teaching Subjects</h3>
                                <ul class="subject-list">
                                    <?php foreach($subjects as $sub): ?>
                                        <li class="subject-item">
                                            <span class="subject-name"><?php echo safeHtml($sub['subject_name']); ?></span>
                                            <span class="subject-detail"><?php echo intval($sub['class_count']); ?> Classes</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="card-footer-info">
                                    Total <?php echo array_sum(array_column($subjects,'class_count')); ?> classes assigned in <?php echo count($subjects); ?> subjects.
                                </div>
                            </div>
                        </div>
                    </div>

                                       
                    <!-- Teacher Progress Card (NEW SECTION) -->
                    <div class="row" style="margin-top:30px;">
                        <div class="col-lg-12">
                            <div class="progress-card subjects-card"> <!-- Reusing subjects-card style base -->
                                <h3 class="card-title-accent"><i class="fas fa-chart-line"></i> Subject Progress Overview</h3>
                                
                                <div class="progress-list">
                                    <?php if (empty($subjects)): ?>
                                        <p class="text-center card-footer-info">No subjects are currently assigned to this teacher to track progress.</p>
                                    <?php else: ?>
                                        <?php foreach($subjects as $sub): ?>
                                            <div class="progress-item">
                                                <div class="progress-header">
                                                    <span class="subject-name-progress"><?php echo safeHtml($sub['subject_name']); ?></span>
                                                    <span class="progress-percent"><?php echo intval($sub['progress']); ?>%</span>
                                                </div>
                                                <div class="progress-bar-container">
                                                    <div class="progress-bar-fill" style="width: <?php echo intval($sub['progress']); ?>%;"></div>
                                                </div>
                                                <p class="progress-label">Taught in <?php echo intval($sub['class_count']); ?> classes.</p>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer-info">
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- END: Teacher Progress Card -->

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div id="delete-modal-overlay" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3 class="modal-title">Confirm User Deletion</h3>
        </div>
        <div class="modal-body">
            <p>Are you absolutely sure you want to delete this profile?</p>
            <p class="warning-text">This action is irreversible and will permanently remove all associated data.</p>
        </div>
        <div class="modal-footer">
            <button class="modal-btn cancel-btn" id="cancel-delete"><i class="fas fa-times"></i> Cancel</button>
            <button class="modal-btn delete-confirm-btn"><i class="fas fa-trash-alt"></i> Delete Permanently</button>
        </div>
    </div>
</div>

<!-- Modal JS -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('delete-modal-overlay');
    const showButton = document.getElementById('show-delete-modal');
    const cancelButton = document.getElementById('cancel-delete');
    const inputs = document.querySelectorAll('.profile-data-grid input, .profile-data-grid select');


    if (showButton) {
        showButton.onclick = () => {
            modal.style.display = 'flex';
            setTimeout(() => { modal.style.opacity = '1'; }, 10);
        };
    }

    const hideModal = () => {
        modal.style.opacity = '0';
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    };

    if (cancelButton) cancelButton.onclick = hideModal;

    if (modal) {
        modal.onclick = (event) => { if (event.target === modal) hideModal(); };
    }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {
    const editBtn = document.querySelector('.edit-btn');
    const inputs = document.querySelectorAll('.profile-data-grid input');

    editBtn.addEventListener('click', () => {
        // Enable all inputs for editing
        inputs.forEach(input => input.removeAttribute('readonly'));
        inputs[0].focus(); // Focus first input
        editBtn.textContent = "Save Changes";
        editBtn.classList.remove('edit-btn');
        editBtn.classList.add('save-btn');

        // Save functionality
        editBtn.onclick = () => {
            const formData = new FormData();
            formData.append('teacher_id', <?php echo $teacherId; ?>);
            inputs.forEach(input => formData.append(input.id.replace('teacher-', ''), input.value.trim()));

            fetch('api/update_teacher.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(resp => {
                if(resp.status === 'success'){
                    alert(resp.message);
                    location.reload();
                } else {
                    alert('Error: ' + resp.message);
                }
            })
            .catch(err => console.error(err));
        };
    });
});
</script>

</body>
</html>