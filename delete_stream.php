<?php
// delete_stream.php
session_start();

// Database connection details

include('includes/config.php');     // **IMPORTANT: Replace with your actual database password**

header('Content-Type: application/json'); // Ensure the response is JSON

try {
    $dbh = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Enable exceptions for errors
        ]
    );
} catch (PDOException $e) {
    // Instead of exiting, return a JSON error response for AJAX calls
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Set header to indicate JSON response
header('Content-Type: application/json');

// Check if stream ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Stream ID not provided.']);
    exit();
}

$streamId = $_GET['id'];

// Start a transaction for atomicity. This ensures all or nothing.
$dbh->beginTransaction();

try {
    // IMPORTANT: Consider what to do with associated data (e.g., students).
    // Option 1: Delete students associated with this stream (USE WITH CAUTION!)
    // If you have a 'tblstudents' table with a 'stream_id' foreign key:
    // $stmt_students = $dbh->prepare("DELETE FROM tblstudents WHERE stream_id = :stream_id");
    // $stmt_students->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    // $stmt_students->execute();

    // Option 2: Set 'stream_id' to NULL for students in this stream (safer)
    // If students can exist without a stream:
    // $stmt_update_students = $dbh->prepare("UPDATE tblstudents SET stream_id = NULL WHERE stream_id = :stream_id");
    // $stmt_update_students->bindParam(':stream_id', $streamId, PDO::PARAM_INT);
    // $stmt_update_students->execute();

    // Option 3: Rely on database-level ON DELETE CASCADE (most robust if set up)
    // If your 'tblstudents' table's foreign key constraint on 'stream_id' has 'ON DELETE CASCADE',
    // the database handles deleting associated students automatically when the stream is deleted.
    // In this case, you don't need the above PHP code.

    // Delete the stream itself
    $sql_delete_stream = "DELETE FROM tblstreams WHERE id = :id";
    $stmt_delete_stream = $dbh->prepare($sql_delete_stream);
    $stmt_delete_stream->bindParam(':id', $streamId, PDO::PARAM_INT);
    $stmt_delete_stream->execute();

    // Check if any row was affected
    if ($stmt_delete_stream->rowCount() > 0) {
        $dbh->commit(); // Commit the transaction
        echo json_encode(['status' => 'success', 'message' => 'Stream deleted successfully.']);
    } else {
        $dbh->rollBack(); // Rollback if no stream was found with that ID
        echo json_encode(['status' => 'error', 'message' => 'Stream not found or could not be deleted.']);
    }

} catch (PDOException $e) {
    $dbh->rollBack(); // Rollback on any error during the transaction
    error_log("Error deleting stream: " . $e->getMessage()); // Log the actual error for debugging
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete stream: ' . $e->getMessage()]);
}
?>