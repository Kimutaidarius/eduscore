<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
include('../includes/config.php');

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// IMPORTANT: Corrected paths to include 'src/'
require '../includes/PHPMailer/src/Exception.php';
require '../includes/PHPMailer/src/PHPMailer.php';
require '../includes/PHPMailer/src/SMTP.php';

header('Content-Type: application/json');

$response = ["success" => false, "message" => ""]; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response["message"] = "Invalid request method.";
    echo json_encode($response);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$action = $data['action'] ?? ''; // 'verify_email' or 'reset_password'
$email = trim($data['email'] ?? ''); // Used for 'verify_email'
$newPassword = $data['new_password'] ?? ''; // Used for 'reset_password'
$token = $data['token'] ?? ''; // Used for 'reset_password'

if (!isset($dbh) || !($dbh instanceof PDO)) {
    $response["message"] = "Database connection failed. Please contact support.";
    error_log("DB connection failed in api/handle_password_reset.php");
    echo json_encode($response);
    exit();
}

try {
    if ($action === 'verify_email') {
        if (empty($email)) {
            $response["message"] = "Email address is required.";
            echo json_encode($response);
            exit();
        }

        // 1. Check if the email exists in tblteachers
        $stmt = $dbh->prepare("SELECT id FROM tblteachers WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // For security, do not reveal if email exists or not.
            // Always return a generic success message if the email format is valid.
            // This prevents enumeration attacks.
            $response["success"] = true; // Still true, even if email not found
            $response["message"] = "If an account with that email exists, a password reset link has been sent.";
            echo json_encode($response);
            exit();
        }

        $userId = $user['id'];

        // 2. Generate a unique, time-limited token
        $reset_token = bin2hex(random_bytes(32)); // Generates a 64-character hex string
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour

        // 3. Store the token and expiry in tblteachers for the user
        // Ensure tblteachers has 'reset_token' (VARCHAR) and 'reset_token_expiry' (DATETIME) columns
        $stmt = $dbh->prepare("UPDATE tblteachers SET reset_token = :token, reset_token_expiry = :expiry WHERE id = :id");
        $stmt->bindParam(':token', $reset_token, PDO::PARAM_STR);
        $stmt->bindParam(':expiry', $expires_at, PDO::PARAM_STR);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // 4. Send the reset email using PHPMailer
        $mail = new PHPMailer(true); // Passing `true` enables exceptions

        try {
            //Server settings
            $mail->SMTPDebug = 0; // Set to 2 for detailed debug output in development, 0 for production
            $mail->isSMTP(); // Send using SMTP
            $mail->Host       = 'smtp.gmail.com'; // Gmail SMTP server
            $mail->SMTPAuth   = true; // Enable SMTP authentication
            $mail->Username   = 'your_gmail_email@gmail.com'; // Your Gmail address
            $mail->Password   = 'your_gmail_app_password'; // Your Gmail App Password or regular password (less secure)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable implicit TLS encryption
            $mail->Port       = 465; // TCP port to connect to; use 587 for `PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $mail->setFrom('no-reply@eduscore.com', 'EduScore Password Reset'); // Sender email and name
            $mail->addAddress($email); // Add a recipient

            // Content
            $mail->isHTML(false); // Set email format to plain text
            $mail->Subject = "EduScore Password Reset Request";
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $reset_token; // Adjust URL as needed
            $mail->Body    = "Dear User,\n\n";
            $mail->Body   .= "You have requested to reset your password for EduScore. ";
            $mail->Body   .= "Please click on the following link to reset your password:\n\n";
            $mail->Body   .= $reset_link . "\n\n";
            $mail->Body   .= "This link will expire in 1 hour. If you did not request a password reset, please ignore this email.\n\n";
            $mail->Body   .= "Regards,\nEduScore Team";

            $mail->send();
            $response["success"] = true;
            $response["message"] = "If an account with that email exists, a password reset link has been sent to your email address.";

        } catch (Exception $e) {
            // PHPMailer exception caught
            $response["success"] = true; // Still true to prevent enumeration
            $response["message"] = "A password reset request was processed, but there was an issue sending the email. Please try again later.";
            error_log("PHPMailer Error: " . $mail->ErrorInfo); // Log the detailed PHPMailer error
        }

    } elseif ($action === 'reset_password') {
        if (empty($token) || empty($newPassword) || strlen($newPassword) < 6) {
            $response["message"] = "Invalid request. Missing token or new password (must be at least 6 characters).";
            echo json_encode($response);
            exit();
        }

        // 1. Validate the token and ensure it's not expired
        $stmt = $dbh->prepare("SELECT id FROM tblteachers WHERE reset_token = :token AND reset_token_expiry > NOW()");
        $stmt->bindParam(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $response["message"] = "Invalid or expired password reset link. Please request a new one.";
            echo json_encode($response);
            exit();
        }

        $userId = $user['id'];

        // 2. Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // 3. Update password and clear the token/expiry
        $stmt = $dbh->prepare("UPDATE tblteachers SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE id = :id");
        $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $response["success"] = true;
            $response["message"] = "Your password has been reset successfully. You can now log in.";
        } else {
            $response["message"] = "Failed to update password. Please try again.";
        }
    } else {
        $response["message"] = "Invalid action specified.";
    }

} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
    error_log("PDO Error in api/handle_password_reset.php: " . $e->getMessage());
} catch (Exception $e) {
    $response["message"] = "An unexpected error occurred: " . $e->getMessage();
    error_log("General Error in api/handle_password_reset.php: " . $e->getMessage());
} finally {
    if (isset($stmt) && $stmt instanceof PDOStatement) {
        $stmt = null;
    }
}

echo json_encode($response);
exit();