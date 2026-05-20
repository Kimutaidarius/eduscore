<?php
require_once('../includes/config.php');
require_once('../fpdf/fpdf.php');
session_start();

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    die("School not identified in session.");
}

if (!isset($_GET['merit_id']) || empty($_GET['merit_id'])) {
    die("Merit list ID missing.");
}

$merit_id = intval($_GET['merit_id']);

try {
    $dbh->beginTransaction();

    // ================================
    // FETCH MERIT LIST METADATA
    // ================================
    $stmt = $dbh->prepare("
        SELECT ms.id, ms.class_id, ms.stream_id, ms.exam_id, ms.term_id,
               c.class_level AS class_name, c.academic_level,
               st.stream_name AS stream_name
        FROM tblmeritlist_status ms
        LEFT JOIN tblclasses c ON ms.class_id = c.id AND c.school_id = ms.school_id
        LEFT JOIN tblstreams st ON ms.stream_id = st.id AND st.school_id = ms.school_id
        WHERE ms.id = ? AND ms.school_id = ?
    ");
    $stmt->execute([$merit_id, $school_id]);
    $merit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$merit) throw new Exception("Merit list not found for your school.");

    $class_id = $merit['class_id'];
    $stream_id = $merit['stream_id'];
    $exam_id = $merit['exam_id'];
    $term_id = $merit['term_id'];

    // ================================
    // DELETE OLD MERIT LIST ROWS
    // ================================
    $del = $dbh->prepare("DELETE FROM tblmeritlist_rows WHERE merit_status_id = ? AND school_id = ?");
    $del->execute([$merit_id, $school_id]);

    // ================================
    // REGENERATE STUDENT SCORES
    // ================================
    $query = $dbh->prepare("
        SELECT s.id AS student_id, SUM(t.score_value) AS total_marks
        FROM tblscores t
        INNER JOIN tblstudents s ON s.id = t.student_id
        WHERE s.class_id = ? AND s.StreamId = ? AND t.exam_id = ? 
              AND s.school_id = ? AND t.school_id = ?
        GROUP BY s.id
        ORDER BY total_marks DESC
    ");
    $query->execute([$class_id, $stream_id, $exam_id, $school_id, $school_id]);
    $studentsData = $query->fetchAll(PDO::FETCH_ASSOC);
    if (!$studentsData) throw new Exception("No student data found for regeneration.");

    // ================================
    // INSERT NEW MERIT LIST ROWS
    // ================================
    $rank = 1;
    $insert = $dbh->prepare("
        INSERT INTO tblmeritlist_rows (merit_status_id, student_id, total_marks, rank, school_id)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($studentsData as $s) {
        $insert->execute([$merit_id, $s['student_id'], $s['total_marks'], $rank++, $school_id]);
    }

    // ================================
    // UPDATE MERIT LIST STATUS
    // ================================
    $update = $dbh->prepare("
        UPDATE tblmeritlist_status 
        SET status = 'Completed', last_updated = NOW() 
        WHERE id = ? AND school_id = ?
    ");
    $update->execute([$merit_id, $school_id]);

    $dbh->commit();

    // ================================
    // FETCH SCHOOL INFO
    // ================================
    $schoolStmt = $dbh->prepare("SELECT school_name, school_logo, school_motto FROM tblschoolinfo WHERE id = ?");
    $schoolStmt->execute([$school_id]);
    $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);

    // ================================
    // FETCH STUDENTS FOR PDF
    // ================================
    $studentsStmt = $dbh->prepare("
        SELECT CONCAT(s.FirstName,' ',s.SecondName,' ',COALESCE(s.LastName,'')) AS student_name,
               s.AdmNo AS adm_no, r.total_marks, r.rank AS position
        FROM tblmeritlist_rows r
        INNER JOIN tblstudents s ON s.id = r.student_id AND s.school_id = r.school_id
        WHERE r.merit_status_id = ? AND r.school_id = ?
        ORDER BY r.rank ASC
    ");
    $studentsStmt->execute([$merit_id, $school_id]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // ================================
    // FETCH EXAM AND TERM NAMES
    // ================================
    $examName = $termName = "-";
    $examStmt = $dbh->prepare("SELECT examname FROM tblexam WHERE id = ? AND school_id = ?");
    $examStmt->execute([$exam_id, $school_id]);
    if ($row = $examStmt->fetch(PDO::FETCH_ASSOC)) $examName = $row['examname'];

    $termStmt = $dbh->prepare("SELECT term_name FROM tblterms WHERE id = ? AND school_id = ?");
    $termStmt->execute([$term_id, $school_id]);
    if ($row = $termStmt->fetch(PDO::FETCH_ASSOC)) $termName = $row['term_name'];

    // ================================
    // GENERATE PDF
    // ================================
    $pdf = new FPDF();
    $pdf->AddPage();

    if (!empty($school['school_logo']) && file_exists("../uploads/" . $school['school_logo'])) {
        $pdf->Image("../uploads/" . $school['school_logo'], 10, 8, 25);
    }

    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,strtoupper($school['school_name'] ?? 'SCHOOL NAME'),0,1,'C');
    $pdf->SetFont('Arial','I',12);
    $pdf->Cell(0,8,$school['school_motto'] ? '"'.$school['school_motto'].'"' : '',0,1,'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial','B',13);
    $pdf->Cell(0,8,strtoupper("MERIT LIST REPORT"),0,1,'C');
    $pdf->Ln(2);

    $pdf->SetFont('Arial','B',12);
    $pdf->SetTextColor(0,102,204);
    $pdf->Cell(0,8,"Exam: ".strtoupper($examName)." | Term: ".strtoupper($termName),0,1,'C');
    $pdf->SetTextColor(0);
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,8,"Level: ".($merit['academic_level']??'-')." | Class: ".($merit['class_name']??'-')." | Stream: ".($merit['stream_name']??'-'),0,1,'C');
    $pdf->Ln(5);

    $pdf->SetFillColor(0,188,212);
    $pdf->SetTextColor(255);
    $pdf->SetFont('Arial','B',11);
    $pdf->Cell(10,10,'#',1,0,'C',true);
    $pdf->Cell(70,10,'Student Name',1,0,'C',true);
    $pdf->Cell(35,10,'Adm No',1,0,'C',true);
    $pdf->Cell(35,10,'Total Marks',1,0,'C',true);
    $pdf->Cell(30,10,'Rank',1,1,'C',true);

    $pdf->SetFont('Arial','',10);
    $pdf->SetTextColor(0);
    $fill = false;
    foreach($students as $i=>$s){
        $pdf->SetFillColor(245,248,250);
        $pdf->Cell(10,8,$i+1,1,0,'C',$fill);
        $pdf->Cell(70,8,utf8_decode($s['student_name']),1,0,'L',$fill);
        $pdf->Cell(35,8,$s['adm_no'],1,0,'C',$fill);
        $pdf->Cell(35,8,$s['total_marks'],1,0,'C',$fill);
        $pdf->Cell(30,8,$s['position'],1,1,'C',$fill);
        $fill = !$fill;
    }

    $pdf->Ln(5);
    $pdf->SetFont('Arial','I',9);
    $pdf->Cell(0,10,'Generated on '.date('d M Y, h:i A'),0,0,'R');

    $filename = ($school['school_name'] ?? 'Merit_List')."_Class_".($merit['class_name'] ?? '').".pdf";
    if(isset($_GET['preview']) && $_GET['preview']==1){
        $pdf->Output('I',$filename);
    } else {
        $pdf->Output('D',$filename);
    }
    exit;

} catch(Exception $e){
    if($dbh->inTransaction()) $dbh->rollBack();
    die("Error: ".$e->getMessage());
}
?>
