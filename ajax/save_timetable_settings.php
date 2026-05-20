<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__).'/includes/config.php';
$input=json_decode(file_get_contents('php://input'),true);
$school_id=$_SESSION['school_id'];
$conn=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
$conn->query("CREATE TABLE IF NOT EXISTS tbl_timetable_settings(id INT AUTO_INCREMENT PRIMARY KEY,school_id INT UNIQUE,periods_per_day INT DEFAULT 8,first_period_start TIME DEFAULT '07:30:00',period_duration INT DEFAULT 60,school_days INT DEFAULT 5,include_saturday TINYINT DEFAULT 0,monday_assembly TINYINT DEFAULT 1,friday_games TINYINT DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
$stmt=$conn->prepare("INSERT INTO tbl_timetable_settings(school_id,periods_per_day,first_period_start,period_duration,school_days,include_saturday,monday_assembly,friday_games) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE periods_per_day=VALUES(periods_per_day),first_period_start=VALUES(first_period_start),period_duration=VALUES(period_duration),school_days=VALUES(school_days),include_saturday=VALUES(include_saturday),monday_assembly=VALUES(monday_assembly),friday_games=VALUES(friday_games)");
$stmt->bind_param("iisiiiii",$school_id,$input['periods_per_day'],$input['first_period_start'],$input['period_duration'],$input['school_days'],$input['include_saturday'],$input['monday_assembly'],$input['friday_games']);
$stmt->execute();
echo json_encode(['success'=>true,'message'=>'Settings saved']);
$conn->close();