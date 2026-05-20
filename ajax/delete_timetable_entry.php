<?php
session_start();
header('Content-Type: application/json');
require_once dirname(__DIR__).'/includes/config.php';
$conn=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
$stmt=$conn->prepare("DELETE FROM tbl_timetable WHERE id=? AND school_id=?");
$stmt->bind_param("ii",$_POST['id'],$_SESSION['school_id']);
$stmt->execute();
echo json_encode(['success'=>true,'message'=>'Deleted']);
$conn->close();