<?php
// worker_generate_reports.php

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php'); // DB connection
require_once('../api_handlers/fpdf.php'); // Include FPDF path (adjust if needed)

/**
 * Fetch students who have scores in tblscores for the given class, exam (and optional stream)
 */
function getStudentsWithScores($dbh, $schoolId, $classId, $examId, $streamId = null) {
    $sql = "
        SELECT DISTINCT s.student_id AS id,
               st.AdmNo AS admission_number,
               st.FirstName AS firstname,
               st.SecondName AS secondname,
               st.LastName AS lastname
        FROM tblscores s
        JOIN tblstudents st ON s.student_id = st.id
        WHERE s.school_id = :school_id
          AND s.class_id = :class_id
          AND s.exam_id = :exam_id
    ";

    $params = [
        ':school_id' => $schoolId,
        ':class_id' => $classId,
        ':exam_id' => $examId,
    ];

    if (!empty($streamId)) {
        $sql .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $streamId;
    }

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all subject scores for a student in a given exam
 */
function getStudentScores($dbh, $studentId, $examId) {
    $sql = "
        SELECT s.subject_id, sub.subject_name, sub.alias, s.score_value AS score
        FROM tblscores s
        LEFT JOIN tblsubjects sub ON s.subject_id = sub.id
        WHERE s.student_id = :student_id AND s.exam_id = :exam_id
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        ':student_id' => $studentId,
        ':exam_id' => $examId
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateTotals($scores, $method = 'total') {
    $total = 0;
    $count = 0;
    foreach ($scores as $score) {
        $total += floatval($score['score']);
        $count++;
    }
    if ($count === 0) return 0;
    return ($method === 'average') ? $total / $count : $total;
}

function generateReportCardPDF($student, $scores, $reportConfig, $outputDir) {
    $pdf = new FPDF();
    $pdf->AddPage();

    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'School Report Card', 0, 1, 'C');

    // Report Title & Term
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $reportConfig['report_title'] . " (" . $reportConfig['report_term_details'] . ")", 0, 1, 'C');

    // Student info
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Student Name:');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $student['firstname'] . ' ' . $student['lastname'], 0, 1);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Admission No:');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $student['admission_number'], 0, 1);

    // Scores table header
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, 'Subject', 1);
    $pdf->Cell(40, 8, 'Score', 1, 1);

    $pdf->SetFont('Arial', '', 12);

foreach ($scores as $score) {
    $subjectName = $score['subject_name'] ?? ("Subject ID " . $score['subject_id']);
    $pdf->Cell(120, 8, $subjectName, 1);
    $pdf->Cell(40, 8, $score['score'], 1, 1);
}

    // Total / Average
    $totalScore = calculateTotals($scores, $reportConfig['computation_method']);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, ucfirst($reportConfig['computation_method']), 1);
    $pdf->Cell(40, 8, number_format($totalScore, 2), 1, 1);

    $filename = $outputDir . "/report_card_{$student['id']}_exam_{$reportConfig['exam_id']}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

function generateMeritListPDF($rankedStudents, $reportConfig, $outputDir) {
    $pdf = new FPDF();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Merit List', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $reportConfig['report_title'] . " (" . $reportConfig['report_term_details'] . ")", 0, 1, 'C');

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(20, 8, 'Rank', 1);
    $pdf->Cell(80, 8, 'Student Name', 1);
    $pdf->Cell(40, 8, 'Admission No', 1);
    $pdf->Cell(40, 8, ucfirst($reportConfig['computation_method']), 1, 1);

    $pdf->SetFont('Arial', '', 12);

    foreach ($rankedStudents as $student) {
        $pdf->Cell(20, 8, $student['rank'], 1);
        $pdf->Cell(80, 8, $student['firstname'] . ' ' . $student['lastname'], 1);
        $pdf->Cell(40, 8, $student['admission_number'], 1);
        $pdf->Cell(40, 8, number_format($student['total_score'], 2), 1, 1);
    }

    $filename = $outputDir . "/merit_list_{$reportConfig['id']}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

// Main worker
try {
    $stmt = $dbh->prepare("SELECT * FROM tblreportconfigurations WHERE batch_status = 'pending' ORDER BY created_at ASC LIMIT 5");
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$batches) {
        echo "No pending report batches found.\n";
        exit;
    }

    foreach ($batches as $batch) {
        echo "Processing batch ID {$batch['id']}...\n";

        $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'in_progress' WHERE id = :id")
            ->execute([':id' => $batch['id']]);

        $schoolId = $batch['school_id'];
        $classId = $batch['class_id'];
        $streamId = $batch['stream_id'] ?? null;
        $examId = $batch['exam_id'];
        $computationMethod = $batch['computation_method'] ?? 'total';

        $outputDir = __DIR__ . "/generated_reports/{$batch['id']}";
        if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

        $students = getStudentsWithScores($dbh, $schoolId, $classId, $examId, $streamId);

        if (!$students) {
            throw new Exception("No students found in tblscores for class_id $classId, exam_id $examId, and stream_id " . ($streamId ?? 'NULL'));
        }

        $rankedStudents = [];

        foreach ($students as $student) {
            $scores = getStudentScores($dbh, $student['id'], $examId);
            $totalScore = calculateTotals($scores, $computationMethod);

            $rankedStudents[] = [
                'id' => $student['id'],
                'firstname' => $student['firstname'],
                'lastname' => $student['lastname'],
                'admission_number' => $student['admission_number'],
                'total_score' => $totalScore,
            ];

            $pdfFile = generateReportCardPDF($student, $scores, $batch, $outputDir);
            echo "Report card generated: $pdfFile\n";
        }

        usort($rankedStudents, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        $rank = 1;
        foreach ($rankedStudents as &$student) {
            $student['rank'] = $rank++;
        }

        $meritListFile = generateMeritListPDF($rankedStudents, $batch, $outputDir);
        echo "Merit list generated: $meritListFile\n";

        $reportFilesJson = json_encode([
            'report_cards_dir' => $outputDir,
            'merit_list' => $meritListFile,
        ]);

        $dbh->prepare("UPDATE tblreportconfigurations 
                       SET report_files_json = :files_json, batch_status = 'completed' 
                       WHERE id = :id")
            ->execute([
                ':files_json' => $reportFilesJson,
                ':id' => $batch['id'],
            ]);

        echo "Batch {$batch['id']} processing completed.\n";
    }
}catch (Exception $e) {
    error_log("worker_generate_reports.php error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";

    // Update batch status to 'failed' so it won't get stuck
    if (isset($batch['id'])) {
        $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'failed' WHERE id = :id")
            ->execute([':id' => $batch['id']]);
    }
}

<?php
// worker_generate_reports.php

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php'); // DB connection
require_once('../api_handlers/fpdf.php'); // Include FPDF path (adjust if needed)

/**
 * Fetch students who have scores in tblscores for the given class, exam (and optional stream)
 */
function getStudentsWithScores($dbh, $schoolId, $classId, $examId, $streamId = null) {
    $sql = "
        SELECT DISTINCT s.student_id AS id,
               st.AdmNo AS admission_number,
               st.FirstName AS firstname,
               st.SecondName AS secondname,
               st.LastName AS lastname
        FROM tblscores s
        JOIN tblstudents st ON s.student_id = st.id
        WHERE s.school_id = :school_id
          AND s.class_id = :class_id
          AND s.exam_id = :exam_id
    ";

    $params = [
        ':school_id' => $schoolId,
        ':class_id' => $classId,
        ':exam_id' => $examId,
    ];

    if (!empty($streamId)) {
        $sql .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $streamId;
    }

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all subject scores for a student in a given exam
 */
function getStudentScores($dbh, $studentId, $examId) {
    $sql = "
        SELECT s.subject_id, sub.subject_name, sub.alias, s.score_value AS score
        FROM tblscores s
        LEFT JOIN tblsubjects sub ON s.subject_id = sub.id
        WHERE s.student_id = :student_id AND s.exam_id = :exam_id
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        ':student_id' => $studentId,
        ':exam_id' => $examId
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateTotals($scores, $method = 'total') {
    $total = 0;
    $count = 0;
    foreach ($scores as $score) {
        $total += floatval($score['score']);
        $count++;
    }
    if ($count === 0) return 0;
    return ($method === 'average') ? $total / $count : $total;
}

function generateReportCardPDF($student, $scores, $reportConfig, $outputDir) {
    $pdf = new FPDF();
    $pdf->AddPage();

    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'School Report Card', 0, 1, 'C');

    // Report Title & Term
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $reportConfig['report_title'] . " (" . $reportConfig['report_term_details'] . ")", 0, 1, 'C');

    // Student info
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Student Name:');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $student['firstname'] . ' ' . $student['lastname'], 0, 1);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Admission No:');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $student['admission_number'], 0, 1);

    // Scores table header
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, 'Subject', 1);
    $pdf->Cell(40, 8, 'Score', 1, 1);

    $pdf->SetFont('Arial', '', 12);

foreach ($scores as $score) {
    $subjectName = $score['subject_name'] ?? ("Subject ID " . $score['subject_id']);
    $pdf->Cell(120, 8, $subjectName, 1);
    $pdf->Cell(40, 8, $score['score'], 1, 1);
}

    // Total / Average
    $totalScore = calculateTotals($scores, $reportConfig['computation_method']);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, ucfirst($reportConfig['computation_method']), 1);
    $pdf->Cell(40, 8, number_format($totalScore, 2), 1, 1);

    $filename = $outputDir . "/report_card_{$student['id']}_exam_{$reportConfig['exam_id']}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

function generateMeritListPDF($rankedStudents, $reportConfig, $outputDir) {
    $pdf = new FPDF();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Merit List', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $reportConfig['report_title'] . " (" . $reportConfig['report_term_details'] . ")", 0, 1, 'C');

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(20, 8, 'Rank', 1);
    $pdf->Cell(80, 8, 'Student Name', 1);
    $pdf->Cell(40, 8, 'Admission No', 1);
    $pdf->Cell(40, 8, ucfirst($reportConfig['computation_method']), 1, 1);

    $pdf->SetFont('Arial', '', 12);

    foreach ($rankedStudents as $student) {
        $pdf->Cell(20, 8, $student['rank'], 1);
        $pdf->Cell(80, 8, $student['firstname'] . ' ' . $student['lastname'], 1);
        $pdf->Cell(40, 8, $student['admission_number'], 1);
        $pdf->Cell(40, 8, number_format($student['total_score'], 2), 1, 1);
    }

    $filename = $outputDir . "/merit_list_{$reportConfig['id']}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

// Main worker
try {
    $stmt = $dbh->prepare("SELECT * FROM tblreportconfigurations WHERE batch_status = 'pending' ORDER BY created_at ASC LIMIT 5");
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$batches) {
        echo "No pending report batches found.\n";
        exit;
    }

    foreach ($batches as $batch) {
        echo "Processing batch ID {$batch['id']}...\n";

        $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'in_progress' WHERE id = :id")
            ->execute([':id' => $batch['id']]);

        $schoolId = $batch['school_id'];
        $classId = $batch['class_id'];
        $streamId = $batch['stream_id'] ?? null;
        $examId = $batch['exam_id'];
        $computationMethod = $batch['computation_method'] ?? 'total';

        $outputDir = __DIR__ . "/generated_reports/{$batch['id']}";
        if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

        $students = getStudentsWithScores($dbh, $schoolId, $classId, $examId, $streamId);

        if (!$students) {
            throw new Exception("No students found in tblscores for class_id $classId, exam_id $examId, and stream_id " . ($streamId ?? 'NULL'));
        }

        $rankedStudents = [];

        foreach ($students as $student) {
            $scores = getStudentScores($dbh, $student['id'], $examId);
            $totalScore = calculateTotals($scores, $computationMethod);

            $rankedStudents[] = [
                'id' => $student['id'],
                'firstname' => $student['firstname'],
                'lastname' => $student['lastname'],
                'admission_number' => $student['admission_number'],
                'total_score' => $totalScore,
            ];

            $pdfFile = generateReportCardPDF($student, $scores, $batch, $outputDir);
            echo "Report card generated: $pdfFile\n";
        }

        usort($rankedStudents, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        $rank = 1;
        foreach ($rankedStudents as &$student) {
            $student['rank'] = $rank++;
        }

        $meritListFile = generateMeritListPDF($rankedStudents, $batch, $outputDir);
        echo "Merit list generated: $meritListFile\n";

        $reportFilesJson = json_encode([
            'report_cards_dir' => $outputDir,
            'merit_list' => $meritListFile,
        ]);

        $dbh->prepare("UPDATE tblreportconfigurations 
                       SET report_files_json = :files_json, batch_status = 'completed' 
                       WHERE id = :id")
            ->execute([
                ':files_json' => $reportFilesJson,
                ':id' => $batch['id'],
            ]);

        echo "Batch {$batch['id']} processing completed.\n";
    }
}catch (Exception $e) {
    error_log("worker_generate_reports.php error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";

    // Update batch status to 'failed' so it won't get stuck
    if (isset($batch['id'])) {
        $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'failed' WHERE id = :id")
            ->execute([':id' => $batch['id']]);
    }
}

<?php
// worker_generate_reports.php

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php'); // DB connection
require_once('../api_handlers/fpdf.php'); // Include FPDF path (adjust if needed)

/**
 * Fetch students who have scores in tblscores for the given class, exam (and optional stream)
 */
function getStudentsWithScores($dbh, $schoolId, $classId, $examId, $streamId = null) {
    $sql = "
        SELECT DISTINCT s.student_id AS id,
               st.AdmNo AS admission_number,
               st.FirstName AS firstname,
               st.SecondName AS secondname,
               st.LastName AS lastname
        FROM tblscores s
        JOIN tblstudents st ON s.student_id = st.id
        WHERE s.school_id = :school_id
          AND s.class_id = :class_id
          AND s.exam_id = :exam_id
    ";

    $params = [
        ':school_id' => $schoolId,
        ':class_id' => $classId,
        ':exam_id' => $examId,
    ];

    if (!empty($streamId)) {
        $sql .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $streamId;
    }

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all subject scores for a student in a given exam
 */
function getStudentScores($dbh, $studentId, $examId) {
    $sql = "
        SELECT s.subject_id, sub.subject_name, sub.alias, s.score_value AS score
        FROM tblscores s
        LEFT JOIN tblsubjects sub ON s.subject_id = sub.id
        WHERE s.student_id = :student_id AND s.exam_id = :exam_id
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        ':student_id' => $studentId,
        ':exam_id' => $examId
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateTotals($scores, $method = 'total') {
    $total = 0;
    $count = 0;
    foreach ($scores as $score) {
        $total += floatval($score['score']);
        $count++;
    }
    if ($count === 0) return 0;
    return ($method === 'average') ? $total / $count : $total;
}

function generateReportCardPDF($student, $scores, $reportConfig, $outputDir) {
    $pdf = new FPDF();
    $pdf->AddPage();

    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'School Report Card', 0, 1, 'C');

    // Report Title & Term
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $reportConfig['report_title'] . " (" . $reportConfig['report_term_details'] . ")", 0, 1, 'C');

    // Student info
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Student Name:');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $student['firstname'] . ' ' . $student['lastname'], 0, 1);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Admission No:');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $student['admission_number'], 0, 1);

    // Scores table header
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, 'Subject', 1);
    $pdf->Cell(40, 8, 'Score', 1, 1);

    $pdf->SetFont('Arial', '', 12);

foreach ($scores as $score) {
    $subjectName = $score['subject_name'] ?? ("Subject ID " . $score['subject_id']);
    $pdf->Cell(120, 8, $subjectName, 1);
    $pdf->Cell(40, 8, $score['score'], 1, 1);
}

    // Total / Average
    $totalScore = calculateTotals($scores, $reportConfig['computation_method']);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, ucfirst($reportConfig['computation_method']), 1);
    $pdf->Cell(40, 8, number_format($totalScore, 2), 1, 1);

    $filename = $outputDir . "/report_card_{$student['id']}_exam_{$reportConfig['exam_id']}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

function generateMeritListPDF($rankedStudents, $reportConfig, $outputDir) {
    $pdf = new FPDF();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Merit List', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $reportConfig['report_title'] . " (" . $reportConfig['report_term_details'] . ")", 0, 1, 'C');

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(20, 8, 'Rank', 1);
    $pdf->Cell(80, 8, 'Student Name', 1);
    $pdf->Cell(40, 8, 'Admission No', 1);
    $pdf->Cell(40, 8, ucfirst($reportConfig['computation_method']), 1, 1);

    $pdf->SetFont('Arial', '', 12);

    foreach ($rankedStudents as $student) {
        $pdf->Cell(20, 8, $student['rank'], 1);
        $pdf->Cell(80, 8, $student['firstname'] . ' ' . $student['lastname'], 1);
        $pdf->Cell(40, 8, $student['admission_number'], 1);
        $pdf->Cell(40, 8, number_format($student['total_score'], 2), 1, 1);
    }

    $filename = $outputDir . "/merit_list_{$reportConfig['id']}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

// Main worker
try {
    $stmt = $dbh->prepare("SELECT * FROM tblreportconfigurations WHERE batch_status = 'pending' ORDER BY created_at ASC LIMIT 5");
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$batches) {
        echo "No pending report batches found.\n";
        exit;
    }

    foreach ($batches as $batch) {
        echo "Processing batch ID {$batch['id']}...\n";

        $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'in_progress' WHERE id = :id")
            ->execute([':id' => $batch['id']]);

        $schoolId = $batch['school_id'];
        $classId = $batch['class_id'];
        $streamId = $batch['stream_id'] ?? null;
        $examId = $batch['exam_id'];
        $computationMethod = $batch['computation_method'] ?? 'total';

        $outputDir = __DIR__ . "/generated_reports/{$batch['id']}";
        if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

        $students = getStudentsWithScores($dbh, $schoolId, $classId, $examId, $streamId);

        if (!$students) {
            throw new Exception("No students found in tblscores for class_id $classId, exam_id $examId, and stream_id " . ($streamId ?? 'NULL'));
        }

        $rankedStudents = [];

        foreach ($students as $student) {
            $scores = getStudentScores($dbh, $student['id'], $examId);
            $totalScore = calculateTotals($scores, $computationMethod);

            $rankedStudents[] = [
                'id' => $student['id'],
                'firstname' => $student['firstname'],
                'lastname' => $student['lastname'],
                'admission_number' => $student['admission_number'],
                'total_score' => $totalScore,
            ];

            $pdfFile = generateReportCardPDF($student, $scores, $batch, $outputDir);
            echo "Report card generated: $pdfFile\n";
        }

        usort($rankedStudents, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        $rank = 1;
        foreach ($rankedStudents as &$student) {
            $student['rank'] = $rank++;
        }

        $meritListFile = generateMeritListPDF($rankedStudents, $batch, $outputDir);
        echo "Merit list generated: $meritListFile\n";

        $reportFilesJson = json_encode([
            'report_cards_dir' => $outputDir,
            'merit_list' => $meritListFile,
        ]);

        $dbh->prepare("UPDATE tblreportconfigurations 
                       SET report_files_json = :files_json, batch_status = 'completed' 
                       WHERE id = :id")
            ->execute([
                ':files_json' => $reportFilesJson,
                ':id' => $batch['id'],
            ]);

        echo "Batch {$batch['id']} processing completed.\n";
    }
}catch (Exception $e) {
    error_log("worker_generate_reports.php error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";

    // Update batch status to 'failed' so it won't get stuck
    if (isset($batch['id'])) {
        $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'failed' WHERE id = :id")
            ->execute([':id' => $batch['id']]);
    }
}

<?php
// worker_generate_reports.php

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/config.php'); // DB connection
require_once('../api_handlers/fpdf.php'); // Include FPDF path (adjust if needed)

/**
 * Fetch students who have scores in tblscores for the given class, exam (and optional stream)
 */
function getStudentsWithScores($dbh, $schoolId, $classId, $examId, $streamId = null) {
    $sql = "
        SELECT DISTINCT s.student_id AS id,
               st.AdmNo AS admission_number,
               st.FirstName AS firstname,
               st.SecondName AS secondname,
               st.LastName AS lastname
        FROM tblscores s
        JOIN tblstudents st ON s.student_id = st.id
        WHERE s.school_id = :school_id
          AND s.class_id = :class_id
          AND s.exam_id = :exam_id
    ";

    $params = [
        ':school_id' => $schoolId,
        ':class_id' => $classId,
        ':exam_id' => $examId,
    ];

    if (!empty($streamId)) {
        $sql .= " AND s.StreamId = :stream_id";
        $params[':stream_id'] = $streamId;
    }

    $stmt = $dbh->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all subject scores for a student in a given exam
 */
function getStudentScores($dbh, $studentId, $examId) {
    $sql = "
        SELECT s.subject_id, sub.subject_name, sub.alias, s.score_value AS score
        FROM tblscores s
        LEFT JOIN tblsubjects sub ON s.subject_id = sub.id
        WHERE s.student_id = :student_id AND s.exam_id = :exam_id
    ";
    $stmt = $dbh->prepare($sql);
    $stmt->execute([
        ':student_id' => $studentId,
        ':exam_id' => $examId
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateTotals($scores, $method = 'total') {
    $total = 0;
    $count = 0;
    foreach ($scores as $score) {
        $total += floatval($score['score']);
        $count++;
    }
    if ($count === 0) return 0;
    return ($method === 'average') ? $total / $count : $total;
}

function generateReportCardPDF($student, $scores, $reportConfig, $outputDir) {
    $pdf = new FPDF();
    $pdf->AddPage();

    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'School Report Card', 0, 1, 'C');

    // Report Title & Term
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $reportConfig['report_title'] . " (" . $reportConfig['report_term_details'] . ")", 0, 1, 'C');

    // Student info
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Student Name:');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $student['firstname'] . ' ' . $student['lastname'], 0, 1);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Admission No:');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $student['admission_number'], 0, 1);

    // Scores table header
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, 'Subject', 1);
    $pdf->Cell(40, 8, 'Score', 1, 1);

    $pdf->SetFont('Arial', '', 12);

foreach ($scores as $score) {
    $subjectName = $score['subject_name'] ?? ("Subject ID " . $score['subject_id']);
    $pdf->Cell(120, 8, $subjectName, 1);
    $pdf->Cell(40, 8, $score['score'], 1, 1);
}

    // Total / Average
    $totalScore = calculateTotals($scores, $reportConfig['computation_method']);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, ucfirst($reportConfig['computation_method']), 1);
    $pdf->Cell(40, 8, number_format($totalScore, 2), 1, 1);

    $filename = $outputDir . "/report_card_{$student['id']}_exam_{$reportConfig['exam_id']}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

function generateMeritListPDF($rankedStudents, $reportConfig, $outputDir) {
    $pdf = new FPDF();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Merit List', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $reportConfig['report_title'] . " (" . $reportConfig['report_term_details'] . ")", 0, 1, 'C');

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(20, 8, 'Rank', 1);
    $pdf->Cell(80, 8, 'Student Name', 1);
    $pdf->Cell(40, 8, 'Admission No', 1);
    $pdf->Cell(40, 8, ucfirst($reportConfig['computation_method']), 1, 1);

    $pdf->SetFont('Arial', '', 12);

    foreach ($rankedStudents as $student) {
        $pdf->Cell(20, 8, $student['rank'], 1);
        $pdf->Cell(80, 8, $student['firstname'] . ' ' . $student['lastname'], 1);
        $pdf->Cell(40, 8, $student['admission_number'], 1);
        $pdf->Cell(40, 8, number_format($student['total_score'], 2), 1, 1);
    }

    $filename = $outputDir . "/merit_list_{$reportConfig['id']}.pdf";
    $pdf->Output('F', $filename);

    return $filename;
}

// Main worker
try {
    $stmt = $dbh->prepare("SELECT * FROM tblreportconfigurations WHERE batch_status = 'pending' ORDER BY created_at ASC LIMIT 5");
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$batches) {
        echo "No pending report batches found.\n";
        exit;
    }

    foreach ($batches as $batch) {
        echo "Processing batch ID {$batch['id']}...\n";

        $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'in_progress' WHERE id = :id")
            ->execute([':id' => $batch['id']]);

        $schoolId = $batch['school_id'];
        $classId = $batch['class_id'];
        $streamId = $batch['stream_id'] ?? null;
        $examId = $batch['exam_id'];
        $computationMethod = $batch['computation_method'] ?? 'total';

        $outputDir = __DIR__ . "/generated_reports/{$batch['id']}";
        if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

        $students = getStudentsWithScores($dbh, $schoolId, $classId, $examId, $streamId);

        if (!$students) {
            throw new Exception("No students found in tblscores for class_id $classId, exam_id $examId, and stream_id " . ($streamId ?? 'NULL'));
        }

        $rankedStudents = [];

        foreach ($students as $student) {
            $scores = getStudentScores($dbh, $student['id'], $examId);
            $totalScore = calculateTotals($scores, $computationMethod);

            $rankedStudents[] = [
                'id' => $student['id'],
                'firstname' => $student['firstname'],
                'lastname' => $student['lastname'],
                'admission_number' => $student['admission_number'],
                'total_score' => $totalScore,
            ];

            $pdfFile = generateReportCardPDF($student, $scores, $batch, $outputDir);
            echo "Report card generated: $pdfFile\n";
        }

        usort($rankedStudents, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        $rank = 1;
        foreach ($rankedStudents as &$student) {
            $student['rank'] = $rank++;
        }

        $meritListFile = generateMeritListPDF($rankedStudents, $batch, $outputDir);
        echo "Merit list generated: $meritListFile\n";

        $reportFilesJson = json_encode([
            'report_cards_dir' => $outputDir,
            'merit_list' => $meritListFile,
        ]);

        $dbh->prepare("UPDATE tblreportconfigurations 
                       SET report_files_json = :files_json, batch_status = 'completed' 
                       WHERE id = :id")
            ->execute([
                ':files_json' => $reportFilesJson,
                ':id' => $batch['id'],
            ]);

        echo "Batch {$batch['id']} processing completed.\n";
    }
}catch (Exception $e) {
    error_log("worker_generate_reports.php error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";

    // Update batch status to 'failed' so it won't get stuck
    if (isset($batch['id'])) {
        $dbh->prepare("UPDATE tblreportconfigurations SET batch_status = 'failed' WHERE id = :id")
            ->execute([':id' => $batch['id']]);
    }
}

