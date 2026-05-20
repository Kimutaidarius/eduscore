<?php
require '../includes/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$schools = $conn->query("SELECT id,total_students FROM tblschoolinfo");

while($school = $schools->fetch_assoc()){

$school_id = $school['id'];
$allowed_students = $school['total_students'];

$result = $conn->query("
SELECT COUNT(*) as total 
FROM tblstudents 
WHERE school_id='$school_id'
AND Status='Active'
");

$row = $result->fetch_assoc();
$current_students = $row['total'];

$extra_students = max(0,$current_students - $allowed_students);

$extra_price = 20;

$extra_charges = $extra_students * $extra_price;

$plan_price = 2000;

$total_invoice = $plan_price + $extra_charges;

$conn->query("
INSERT INTO tbl_invoices
(school_id,invoice_number,amount,extra_students,extra_charges,status,due_date)
VALUES
('$school_id',UUID(),'$total_invoice','$extra_students','$extra_charges','UNPAID',DATE_ADD(NOW(),INTERVAL 7 DAY))
");

}