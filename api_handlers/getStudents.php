<?php
session_start();

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [
    "draw" => isset($_GET['draw']) ? intval($_GET['draw']) : 0,
    "recordsTotal" => 0,
    "recordsFiltered" => 0,
    "data" => [],
    "error" => ""
];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $response['error'] = 'Invalid request method for fetching students. Only GET allowed.';
    http_response_code(405);
    echo json_encode($response);
    exit();
}

require_once '../includes/config.php';

$schoolId = $_SESSION['school_id'] ?? null;
if (!$schoolId) {
    $response['error'] = 'Authentication required or session expired.';
    echo json_encode($response);
    exit();
}

try {
    $start  = isset($_GET['start']) ? intval($_GET['start']) : 0;
    $length = isset($_GET['length']) ? intval($_GET['length']) : 10;

    $class_id     = filter_input(INPUT_GET, 'class_id', FILTER_SANITIZE_NUMBER_INT) ?? '';
    $stream_id    = filter_input(INPUT_GET, 'stream_id', FILTER_SANITIZE_NUMBER_INT) ?? '';
    $search_query = htmlspecialchars(filter_input(INPUT_GET, 'search_query', FILTER_UNSAFE_RAW) ?? '', ENT_QUOTES, 'UTF-8');

    $selectColumns = "
        s.id AS student_pk_id,
        s.FirstName,
        s.SecondName,
        s.LastName,
        s.Gender,
        s.AdmNo,
        s.Nemis,
        s.class_id AS class_fk_id,
        s.StreamId,
        s.Status,
        s.GuardianPhone AS GuardiansContact,
        tc.class_level AS ClassName,
        ts.stream_name AS StreamName
    ";

    $joinTables = "
        FROM tblstudents s
        LEFT JOIN tblclasses tc ON s.class_id = tc.id
        LEFT JOIN tblstreams ts ON s.StreamId = ts.id
    ";

    $whereClauses = ["s.school_id = :school_id"];
    $bindParams   = [':school_id' => $schoolId];
    $paramTypes   = [':school_id' => PDO::PARAM_INT];

    if (!empty($class_id)) {
        $whereClauses[] = "s.class_id = :class_id";
        $bindParams[':class_id'] = (int)$class_id;
        $paramTypes[':class_id'] = PDO::PARAM_INT;
    }

    if (!empty($stream_id)) {
        $whereClauses[] = "s.StreamId = :stream_id";
        $bindParams[':stream_id'] = (int)$stream_id;
        $paramTypes[':stream_id'] = PDO::PARAM_INT;
    }

    if (!empty($search_query)) {
        $whereClauses[] = "(
            s.AdmNo LIKE :search_query_admno OR
            s.FirstName LIKE :search_query_name OR
            s.SecondName LIKE :search_query_name OR
            s.LastName LIKE :search_query_name
        )";
        $bindParams[':search_query_admno'] = "%{$search_query}%";
        $bindParams[':search_query_name']  = "%{$search_query}%";
        $paramTypes[':search_query_admno'] = PDO::PARAM_STR;
        $paramTypes[':search_query_name']  = PDO::PARAM_STR;
    }

    // Total records
    $stmtTotal = $dbh->prepare("SELECT COUNT(id) AS total FROM tblstudents WHERE school_id = :school_id");
    $stmtTotal->bindParam(':school_id', $schoolId, PDO::PARAM_INT);
    $stmtTotal->execute();
    $response['recordsTotal'] = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

    // Filtered count
    $filteredSql = "SELECT COUNT(s.id) AS total_filtered {$joinTables} WHERE " . implode(" AND ", $whereClauses);
    $stmtFiltered = $dbh->prepare($filteredSql);
    foreach ($bindParams as $key => &$val) {
        $stmtFiltered->bindParam($key, $val, $paramTypes[$key]);
    }
    $stmtFiltered->execute();
    $response['recordsFiltered'] = $stmtFiltered->fetch(PDO::FETCH_ASSOC)['total_filtered'];

    // Main data query
    $dataSql = "SELECT {$selectColumns} {$joinTables} WHERE " . implode(" AND ", $whereClauses);

    $orderColumns = [
        0 => 's.id',
        1 => 's.FirstName',
        2 => 's.Gender',
        3 => 's.AdmNo',
        4 => 's.Nemis',
        5 => 'tc.class_level',
        6 => 'ts.stream_name',
        7 => 's.Status',
        8 => 's.GuardianPhone'
    ];

    if (isset($_GET['order']) && count($_GET['order'])) {
        $orderBy = [];
        foreach ($_GET['order'] as $order) {
            $colIdx = intval($order['column']);
            if (isset($orderColumns[$colIdx]) && $_GET['columns'][$colIdx]['orderable'] === 'true') {
                $dir = $order['dir'] === 'asc' ? 'ASC' : 'DESC';
                $orderBy[] = ($colIdx === 1)
                    ? "s.FirstName {$dir}, s.LastName {$dir}"
                    : "{$orderColumns[$colIdx]} {$dir}";
            }
        }
        $dataSql .= !empty($orderBy)
            ? " ORDER BY " . implode(", ", $orderBy)
            : " ORDER BY tc.class_level ASC, s.LastName ASC, s.FirstName ASC";
    } else {
        $dataSql .= " ORDER BY tc.class_level ASC, s.LastName ASC, s.FirstName ASC";
    }

    $dataSql .= " LIMIT :start, :length";
    $bindParams[':start']  = $start;
    $bindParams[':length'] = $length;
    $paramTypes[':start']  = PDO::PARAM_INT;
    $paramTypes[':length'] = PDO::PARAM_INT;

    $stmtData = $dbh->prepare($dataSql);
    foreach ($bindParams as $key => &$val) {
        $stmtData->bindParam($key, $val, $paramTypes[$key]);
    }
    $stmtData->execute();
    $students = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    $formatted = [];
    foreach ($students as $student) {
        $name = trim(implode(" ", array_filter([
            $student['FirstName'],
            $student['SecondName'],
            $student['LastName']
        ])));
        $formatted[] = [
            "student_pk_id"    => $student['student_pk_id'],
            "Name"             => $name,
            "Gender"           => $student['Gender'],
            "AdmNo"            => $student['AdmNo'],
            "Nemis"            => $student['Nemis'],
            "ClassName"        => $student['ClassName'],
            "StreamName"       => $student['StreamName'],
            "Status"           => $student['Status'],
            "GuardiansContact" => $student['GuardiansContact']
        ];
    }
    $response['data'] = $formatted;

} catch (PDOException $e) {
    error_log("DB error in getStudents.php: " . $e->getMessage());
    $response['error'] = 'Failed to fetch students: ' . $e->getMessage();
} catch (Exception $e) {
    error_log("General error in getStudents.php: " . $e->getMessage());
    $response['error'] = 'Unexpected error: ' . $e->getMessage();
}

echo json_encode($response);
exit;
