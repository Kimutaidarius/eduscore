<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request.';
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id'];
$uploadDir = '../uploads/logos/';
$defaultLogo = 'default.png';

if (isset($dbh) && $dbh instanceof PDO) {
    try {
        $dbh->beginTransaction();

        // 1. Get the current logo file name
        $sql = "SELECT school_logo FROM tblschoolinfo WHERE id = :school_id FOR UPDATE";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt->execute();
        $existingLogo = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldLogoFileName = $existingLogo['school_logo'] ?? null;

        // 2. Check if the current logo is the default one
        if ($oldLogoFileName === $defaultLogo) {
            $dbh->rollBack();
            $response['success'] = true;
            $response['message'] = 'Default logo is currently in use. No changes were made.';
            echo json_encode($response);
            exit();
        }

        // 3. Update the database to use the default logo
        $sql = "UPDATE tblschoolinfo SET school_logo = :logo_filename WHERE id = :school_id";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':logo_filename', $defaultLogo, PDO::PARAM_STR);
        $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt->execute();

        $dbh->commit();

        // 4. Update the session variable
        $_SESSION['school_logo'] = $defaultLogo;

        // 5. Delete the old logo file if it exists and is not the default
        if ($oldLogoFileName && $oldLogoFileName !== $defaultLogo && file_exists($uploadDir . $oldLogoFileName)) {
            unlink($uploadDir . $oldLogoFileName);
        }
        
        $response['success'] = true;
        $response['message'] = 'School logo deleted successfully. Default logo is now in use.';

    } catch (PDOException $e) {
        $dbh->rollBack();
        error_log("Database error in delete_logo.php: " . $e->getMessage());
        $response['message'] = 'Database error. Please try again later.';
    }
} else {
    $response['message'] = 'Database connection not available.';
}

echo json_encode($response);
?><?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../includes/config.php');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['id']) || empty($_SESSION['id']) || !isset($_SESSION['school_id']) || empty($_SESSION['school_id'])) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request.';
    echo json_encode($response);
    exit();
}

$schoolId = $_SESSION['school_id'];
$uploadDir = '../uploads/logos/';
$defaultLogo = 'default.png';

if (isset($dbh) && $dbh instanceof PDO) {
    try {
        $dbh->beginTransaction();

        // 1. Get the current logo file name
        $sql = "SELECT school_logo FROM tblschoolinfo WHERE id = :school_id FOR UPDATE";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt->execute();
        $existingLogo = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldLogoFileName = $existingLogo['school_logo'] ?? null;

        // 2. Check if the current logo is the default one
        if ($oldLogoFileName === $defaultLogo) {
            $dbh->rollBack();
            $response['success'] = true;
            $response['message'] = 'Default logo is currently in use. No changes were made.';
            echo json_encode($response);
            exit();
        }

        // 3. Update the database to use the default logo
        $sql = "UPDATE tblschoolinfo SET school_logo = :logo_filename WHERE id = :school_id";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':logo_filename', $defaultLogo, PDO::PARAM_STR);
        $stmt->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
        $stmt->execute();

        $dbh->commit();

        // 4. Update the session variable
        $_SESSION['school_logo'] = $defaultLogo;

        // 5. Delete the old logo file if it exists and is not the default
        if ($oldLogoFileName && $oldLogoFileName !== $defaultLogo && file_exists($uploadDir . $oldLogoFileName)) {
            unlink($uploadDir . $oldLogoFileName);
        }
        
        $response['success'] = true;
        $response['message'] = 'School logo deleted successfully. Default logo is now in use.';

    } catch (PDOException $e) {
        $dbh->rollBack();
        error_log("Database error in delete_logo.php: " . $e->getMessage());
        $response['message'] = 'Database error. Please try again later.';
    }
} else {
    $response['message'] = 'Database connection not available.';
}

echo json_encode($response);
?>