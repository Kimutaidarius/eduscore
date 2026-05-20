<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "\nSession Data:\n";
print_r($_SESSION);
echo "</pre>";

// Check specific session variables
echo "<h3>Checking Authentication Variables:</h3>";
echo "teacher_id: " . ($_SESSION['teacher_id'] ?? 'NOT SET') . "<br>";
echo "school_id: " . ($_SESSION['school_id'] ?? 'NOT SET') . "<br>";
echo "academic_level: " . ($_SESSION['academic_level'] ?? 'NOT SET') . "<br>";

// Test if this is an AJAX request
echo "<h3>Request Info:</h3>";
echo "Is AJAX: " . (isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? 'Yes' : 'No') . "<br>";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "<br>";
?>