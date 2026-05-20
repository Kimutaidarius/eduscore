<?php
// get_stream_details.php
session_start();

// Include your database connection file
// For demonstration, directly embedding your DB connection:
// DB credentials.
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','srms'); // Your database name

// Establish database connection.
try {
    $dbh = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

header('Content-Type: application/json'); // Ensure JSON response

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Stream ID not provided.']);
    exit();
}

$streamId = $_GET['id'];

try {
    $sql = "SELECT id, stream_name, class_id FROM tblstreams WHERE id = :id";
    $query = $dbh->prepare($sql);
    $query->bindParam(':id', $streamId, PDO::PARAM_INT);
    $query->execute();
    $stream = $query->fetch(PDO::FETCH_ASSOC);

    if ($stream) {
        echo json_encode(['status' => 'success', 'stream' => $stream]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Stream not found.']);
    }

} catch (PDOException $e) {
    error_log("Error fetching stream details: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch stream details: ' . $e->getMessage()]);
}
?>