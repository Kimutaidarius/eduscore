<?php

class DBHelper {

    protected $teacher_id;
    protected $school_id;
    protected $conn;

    public function __construct() {
        if (!isset($_SESSION['teacher_id'], $_SESSION['school_id'])) {
            error_log("DBHelper: Missing session variables");
            $this->sendError('Unauthorized - Please login again', 401);
        }

        $this->teacher_id = $_SESSION['teacher_id'];
        $this->school_id  = $_SESSION['school_id'];

        $this->initDB();
    }

    protected function initDB() {
        try {
            require_once __DIR__ . '/../includes/config.php';

            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

            if ($this->conn->connect_error) {
                throw new Exception($this->conn->connect_error);
            }

            $this->conn->set_charset('utf8mb4');

        } catch (Exception $e) {
            error_log("DB Connection Error: " . $e->getMessage());
            $this->sendError('Database connection failed', 500);
        }
    }

    protected function sendResponse($data = [], $message = '') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ]);
        exit;
    }

    protected function sendError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }
protected function fetchAll($sql, $params = []) {
    try {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception($this->conn->error);
        }

        if (!empty($params)) {
            $types = '';
            $values = [];

            foreach ($params as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $value;
            }

            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    } catch (Exception $e) {
        error_log("fetchAll Error: " . $e->getMessage());
        return [];
    }
}

    protected function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception($this->conn->error);
            }

            if (!empty($params)) {
                $types = '';
                $values = [];

                foreach ($params as $value) {
                    $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
                    $values[] = $value;
                }

                $stmt->bind_param($types, ...$values);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            return $result && $result->num_rows
                ? $result->fetch_assoc()
                : null;

        } catch (Exception $e) {
            error_log("fetchOne Error: " . $e->getMessage());
            return null;
        }
    }
}
