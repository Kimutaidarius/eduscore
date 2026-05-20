<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// ----------------------------------------------------
// AUTH CHECK
// ----------------------------------------------------
if (!isset($_SESSION['teacher_id'], $_SESSION['school_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Please login first'
    ]);
    exit();
}

// ----------------------------------------------------
// CONFIG LOAD
// ----------------------------------------------------
$configPath = __DIR__ . '/../includes/config.php';
if (!file_exists($configPath)) {
    error_log("Config file missing: {$configPath}");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Configuration file not found'
    ]);
    exit();
}

require_once $configPath;

if (!defined('DB_HOST')) {
    error_log("Database constants not loaded");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database configuration error'
    ]);
    exit();
}

// ----------------------------------------------------
// CLASS
// ----------------------------------------------------
class FetchExams {
    private PDO $pdo;
    private int $teacher_id;
    private int $school_id;

    public function __construct(int $teacher_id, int $school_id) {
        $this->teacher_id = $teacher_id;
        $this->school_id  = $school_id;

        $this->connect();
        $this->handle();
    }

    private function connect(): void {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false
                ]
            );
        } catch (PDOException $e) {
            error_log("DB CONNECT ERROR: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed'
            ]);
            exit();
        }
    }

    private function handle(): void {
        try {
            $class_id   = $_REQUEST['class_id']   ?? null;
            $stream_id  = isset($_REQUEST['stream_id'])  ? (int)$_REQUEST['stream_id']  : null;
            $subject_id = isset($_REQUEST['subject_id']) ? (int)$_REQUEST['subject_id'] : null;

            $today = date('Y-m-d');

            // ------------------------------------------------
            // BASE QUERY (ALL PLACEHOLDERS UNIQUE)
            // ------------------------------------------------
            $sql = "
                SELECT
                    e.id,
                    e.examname AS exam_name,
                    e.class_id,
                    e.stream_id,
                    e.DateAdded AS created_date,
                    e.deadline_date,
                    e.status,
                    e.last_updated,
                    c.class_level,
                    c.academic_level,
                    s.stream_name
                FROM tblexam e
                LEFT JOIN tblclasses c
                    ON e.class_id = c.id
                   AND c.school_id = :school_classes
                LEFT JOIN tblstreams s
                    ON e.stream_id = s.id
                   AND s.school_id = :school_streams
                WHERE e.school_id = :school_main
                  AND e.status = 'Active'
                  AND (e.deadline_date IS NULL OR e.deadline_date >= :today)
            ";

            $params = [
                ':school_main'     => $this->school_id,
                ':school_classes'  => $this->school_id,
                ':school_streams'  => $this->school_id,
                ':today'           => $today
            ];

            if (!empty($class_id)) {
                $sql .= " AND e.class_id = :class_id";
                $params[':class_id'] = $class_id;
            }

            if (!empty($stream_id)) {
                $sql .= " AND (e.stream_id = :stream_id OR e.stream_id IS NULL OR e.stream_id = 0)";
                $params[':stream_id'] = $stream_id;
            }

            if (!empty($subject_id)) {
                $sql .= "
                    AND EXISTS (
                        SELECT 1
                        FROM tbllessons l
                        WHERE l.subject_id = :subject_id
                          AND l.class_id = e.class_id
                          AND (l.stream_id = e.stream_id OR l.stream_id IS NULL OR l.stream_id = 0)
                          AND l.teacher_id = :teacher_id
                          AND l.school_id = :school_lessons
                    )
                ";

                $params[':subject_id']     = $subject_id;
                $params[':teacher_id']     = $this->teacher_id;
                $params[':school_lessons'] = $this->school_id;
            }

            $sql .= "
                ORDER BY
                    CASE WHEN e.deadline_date IS NULL THEN 1 ELSE 0 END,
                    e.deadline_date ASC,
                    e.examname ASC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $data = $this->format($rows, $today);

            $this->respond($data);

        } catch (PDOException $e) {
            error_log("FETCH ERROR: " . $e->getMessage());
            $this->error('Failed to fetch exams');
        }
    }

    private function format(array $rows, string $today): array {
        return array_map(function ($exam) use ($today) {

            $deadline_status = 'open';
            $days_left = null;

            if (!empty($exam['deadline_date'])) {
                $d1 = new DateTime($today);
                $d2 = new DateTime($exam['deadline_date']);
                $d1->setTime(0,0,0);
                $d2->setTime(0,0,0);

                $diff = $d1->diff($d2);
                $days_left = $diff->days * ($d1 > $d2 ? -1 : 1);

                if ($days_left < 0)       $deadline_status = 'past';
                elseif ($days_left <= 3) $deadline_status = 'urgent';
                elseif ($days_left <= 7) $deadline_status = 'approaching';
            }

            $display = $exam['exam_name'];
            if ($exam['class_level']) $display .= " - {$exam['class_level']}";
            if ($exam['stream_name']) $display .= " ({$exam['stream_name']})";
            if ($deadline_status === 'urgent') $display .= " ⚠️ ({$days_left} days left)";
            if ($deadline_status === 'approaching') $display .= " ({$days_left} days left)";

            return [
                'id' => $exam['id'],
                'exam_name' => $exam['exam_name'],
                'display_name' => $display,
                'class_id' => $exam['class_id'],
                'stream_id' => $exam['stream_id'],
                'class_level' => $exam['class_level'],
                'academic_level' => $exam['academic_level'],
                'stream_name' => $exam['stream_name'],
                'created_date' => $exam['created_date'],
                'deadline_date' => $exam['deadline_date'],
                'status' => $exam['status'],
                'last_updated' => $exam['last_updated'],
                'days_until_deadline' => $days_left,
                'deadline_status' => $deadline_status,
                'is_past_deadline' => ($deadline_status === 'past')
            ];
        }, $rows);
    }

    private function respond(array $data): void {
        echo json_encode([
            'success' => true,
            'message' => 'Exams fetched successfully',
            'count' => count($data),
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }

    private function error(string $msg): void {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $msg,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
}

// ----------------------------------------------------
// BOOT
// ----------------------------------------------------
new FetchExams(
    (int)$_SESSION['teacher_id'],
    (int)$_SESSION['school_id']
);
