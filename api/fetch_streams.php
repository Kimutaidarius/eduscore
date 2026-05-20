<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auth check
if (!isset($_SESSION['teacher_id'], $_SESSION['school_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once 'db_helper.php';

class FetchStreams extends DBHelper {

    public function __construct() {
        parent::__construct();

        if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
            $this->sendError('Method not allowed', 405);
        }

        $this->handleRequest();
    }

    private function handleRequest() {
        $class_id = isset($_REQUEST['class_id']) ? (int) $_REQUEST['class_id'] : 0;

        if (!$class_id) {
            $this->sendError('Class ID is required', 400);
        }

        /**
         * STEP 1: Verify class belongs to school
         * (do NOT block teacher yet)
         */
        $classSql = "
            SELECT id 
            FROM tblclasses 
            WHERE id = ? AND school_id = ?
            LIMIT 1
        ";

        $classExists = $this->fetchOne($classSql, [
            $class_id,
            $this->school_id
        ]);

        if (!$classExists) {
            $this->sendError('Access denied to this class.', 403);
        }

        /**
         * STEP 2: Fetch streams
         */
        $sql = "
            SELECT 
                id,
                stream_name,
                CONCAT(stream_name, ' Stream') AS display_name
            FROM tblstreams
            WHERE class_id = ?
              AND school_id = ?
            ORDER BY stream_name ASC
        ";

        $streams = $this->fetchAll($sql, [
            $class_id,
            $this->school_id
        ]);

        $this->sendResponse($streams, 'Streams fetched successfully');
    }
}

new FetchStreams();
