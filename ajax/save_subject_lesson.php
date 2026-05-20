<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__).'/includes/config.php';
$conn=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
$conn->query("CREATE TABLE IF NOT EXISTS tbl_subject_lessons(id INT AUTO_INCREMENT PRIMARY KEY,school_id INT,class_id INT,subject_id INT,teacher_id INT,lessons_per_week INT DEFAULT 5,lesson_type ENUM('single','double') DEFAULT 'single',UNIQUE KEY unique_subj(school_id,class_id,subject_id))");
$stmt=$conn->prepare("INSERT INTO tbl_subject_lessons(school_id,class_id,subject_id,teacher_id,lessons_per_week,lesson_type) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE teacher_id=VALUES(teacher_id),lessons_per_week=VALUES(lessons_per_week),lesson_type=VALUES(lesson_type)");
$stmt->bind_param("iiiiis",$_POST['school_id'],$_POST['class_id'],$_POST['subject_id'],$_POST['teacher_id'],$_POST['lessons_per_week'],$_POST['lesson_type']);
$stmt->execute();
echo json_encode(['success'=>true,'message'=>'Subject lesson saved']);
$conn->close();