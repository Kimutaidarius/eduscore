<?php
// api/save_template_preference.php
session_start();
header('Content-Type: application/json');

// Allow both POST and GET for flexibility
$template = $_POST['template'] ?? $_GET['template'] ?? 'enhanced';
$stream_rank = ($_POST['stream_rank'] ?? $_GET['stream_rank'] ?? '1') == '1';
$class_rank = ($_POST['class_rank'] ?? $_GET['class_rank'] ?? '1') == '1';
$include_summary = ($_POST['include_summary'] ?? $_GET['include_summary'] ?? '1') == '1';

// Save to session
$_SESSION['report_template'] = $template;
$_SESSION['stream_rank'] = $stream_rank;
$_SESSION['class_rank'] = $class_rank;
$_SESSION['include_summary'] = $include_summary;

// Optional: Save to database if you want persistent storage across devices
if (isset($_SESSION['teacher_id']) && isset($_SESSION['school_id'])) {
    try {
        require_once dirname(__DIR__) . '/includes/config.php';
        
        // Create table if not exists
        $db->query("
            CREATE TABLE IF NOT EXISTS user_report_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT NOT NULL,
                school_id INT NOT NULL,
                template VARCHAR(50) DEFAULT 'enhanced',
                stream_rank TINYINT(1) DEFAULT 1,
                class_rank TINYINT(1) DEFAULT 1,
                include_summary TINYINT(1) DEFAULT 1,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user (teacher_id, school_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Upsert preference
        $stmt = $db->prepare("
            INSERT INTO user_report_preferences (teacher_id, school_id, template, stream_rank, class_rank, include_summary)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                template = VALUES(template),
                stream_rank = VALUES(stream_rank),
                class_rank = VALUES(class_rank),
                include_summary = VALUES(include_summary)
        ");
        $stmt->execute([
            $_SESSION['teacher_id'],
            $_SESSION['school_id'],
            $template,
            $stream_rank ? 1 : 0,
            $class_rank ? 1 : 0,
            $include_summary ? 1 : 0
        ]);
    } catch (Exception $e) {
        // Silent fail - session still works
        error_log("Failed to save template preference to DB: " . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Preferences saved',
    'template' => $template
]);