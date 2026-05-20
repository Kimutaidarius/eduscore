<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!isset($_SESSION['school_id']) || !isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
    
    $school_id = $_SESSION['school_id'];
    $user_id = $_SESSION['user_id'];
    
    // Get and validate form data
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
    $stream_id = isset($_POST['stream_id']) && !empty($_POST['stream_id']) ? intval($_POST['stream_id']) : null;
    $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $compute_by = isset($_POST['compute_by']) ? $_POST['compute_by'] : 'exam_total';
    $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
    $rank_by = isset($_POST['rank_by']) ? $_POST['rank_by'] : 'total_marks';
    $show_rank = isset($_POST['show_rank']) ? intval($_POST['show_rank']) : 0;
    $subject_count = isset($_POST['subject_count']) ? intval($_POST['subject_count']) : 0;
    
    // Validate required fields
    if ($class_id <= 0 || $term_id <= 0 || $exam_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields: class, term, and exam are required.']);
        exit;
    }
    
    if ($subject_count <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please select the number of subjects to include.']);
        exit;
    }
    
    // Verify class belongs to school - FIXED: using class_level instead of class_name
    $classQuery = "SELECT academic_level, class_level FROM tblclasses WHERE id = :class_id AND school_id = :school_id";
    $classStmt = $db->prepare($classQuery);
    $classStmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
    $classStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $classStmt->execute();
    
    if ($classStmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Class not found or unauthorized.']);
        exit;
    }
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);
    
    // Create class name from academic_level and class_level
    $class_name = $class['class_level'] . ($class['academic_level'] ? ' (' . $class['academic_level'] . ')' : '');
    
    // Verify stream belongs to school if provided
    $stream_name = '';
    if ($stream_id) {
        $streamQuery = "SELECT stream_name FROM tblstreams WHERE id = :stream_id AND school_id = :school_id";
        $streamStmt = $db->prepare($streamQuery);
        $streamStmt->bindParam(":stream_id", $stream_id, PDO::PARAM_INT);
        $streamStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        $streamStmt->execute();
        
        if ($streamStmt->rowCount() > 0) {
            $stream = $streamStmt->fetch(PDO::FETCH_ASSOC);
            $stream_name = $stream['stream_name'];
        }
    }
    
    // Verify term belongs to school
    $termQuery = "SELECT term_name, academic_year FROM tblterms WHERE id = :term_id AND school_id = :school_id";
    $termStmt = $db->prepare($termQuery);
    $termStmt->bindParam(":term_id", $term_id, PDO::PARAM_INT);
    $termStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $termStmt->execute();
    
    if ($termStmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Term not found or unauthorized.']);
        exit;
    }
    $term = $termStmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify exam belongs to school and class
    $examQuery = "SELECT examname FROM tblexam WHERE id = :exam_id AND school_id = :school_id";
    $examStmt = $db->prepare($examQuery);
    $examStmt->bindParam(":exam_id", $exam_id, PDO::PARAM_INT);
    $examStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $examStmt->execute();
    
    if ($examStmt->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Exam not found or unauthorized.']);
        exit;
    }
    
    // Get all subjects for this class (compulsory + optional)
    $subjectQuery = "SELECT id, subject_name, subject_type 
                    FROM tblsubjects 
                    WHERE class_id = :class_id 
                    AND school_id = :school_id 
                    AND subject_type IN ('Compulsory', 'Optional')
                    ORDER BY subject_type DESC, subject_name ASC";
    
    $subjectStmt = $db->prepare($subjectQuery);
    $subjectStmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
    $subjectStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $subjectStmt->execute();
    $allSubjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allSubjects)) {
        echo json_encode(['success' => false, 'message' => 'No subjects found for this class.']);
        exit;
    }
    
    // Separate compulsory and optional subjects
    $compulsorySubjects = [];
    $optionalSubjects = [];
    
    foreach ($allSubjects as $subject) {
        if ($subject['subject_type'] === 'Compulsory') {
            $compulsorySubjects[] = $subject['id'];
        } else {
            $optionalSubjects[] = $subject['id'];
        }
    }
    
    // Calculate how many subjects to select based on subject_count
    $totalSubjectsCount = count($compulsorySubjects) + count($optionalSubjects);
    $compulsoryCount = count($compulsorySubjects);
    
    // Ensure subject_count is valid
    if ($subject_count < $compulsoryCount) {
        echo json_encode(['success' => false, 'message' => "You must include at least $compulsoryCount compulsory subjects."]);
        exit;
    }
    
    if ($subject_count > $totalSubjectsCount) {
        $subject_count = $totalSubjectsCount;
    }
    
    // Determine which subjects to include
    $subjectsToInclude = $compulsorySubjects; // Always include all compulsory
    
    // Add optional subjects until we reach the desired count
    $optionalNeeded = $subject_count - $compulsoryCount;
    if ($optionalNeeded > 0 && !empty($optionalSubjects)) {
        // For simplicity, take the first N optional subjects
        // You might want to let users choose which optional subjects later
        $optionalToInclude = array_slice($optionalSubjects, 0, $optionalNeeded);
        $subjectsToInclude = array_merge($subjectsToInclude, $optionalToInclude);
    }
    
    // Convert arrays to comma-separated strings for database storage
    $subjects_str = implode(',', $subjectsToInclude);
    $compulsory_subjects_str = implode(',', $compulsorySubjects);
    
    // Generate report title - FIXED: using $class_name instead of $class['class_name']
    $reportTitle = $class_name . ($stream_name ? " - $stream_name" : "") . " Term " . $term['term_name'] . " " . $year;
    
    // Insert report configuration
$query = "INSERT INTO tblreportconfigurations (
            school_id, generated_by, generated_by_teacher_id, report_title, class_id, stream_id,
            term_id, exam_id, report_year, computation_method,
            total_learning_areas, ranking_option, show_rank,
            selected_subjects, compulsory_subjects, batch_status,
            report_term_details, period, created_at, updated_at
          ) VALUES (
            :school_id, :generated_by, :generated_by_teacher_id, :report_title, :class_id, :stream_id,
            :term_id, :exam_id, :report_year, :computation_method,
            :total_learning_areas, :ranking_option, :show_rank,
            :selected_subjects, :compulsory_subjects, 'processing',
            :report_term_details, :period, NOW(), NOW()
          )";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
    $stmt->bindParam(":generated_by", $user_id, PDO::PARAM_INT);
    $stmt->bindParam(":report_title", $reportTitle);
    $stmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
    $stmt->bindParam(":stream_id", $stream_id, PDO::PARAM_INT);
    $stmt->bindParam(":term_id", $term_id, PDO::PARAM_INT);
    $stmt->bindParam(":exam_id", $exam_id, PDO::PARAM_INT);
    $stmt->bindParam(":report_year", $year, PDO::PARAM_INT);
    $stmt->bindParam(":computation_method", $compute_by);
    $stmt->bindParam(":total_learning_areas", $subject_count, PDO::PARAM_INT);
    $stmt->bindParam(":ranking_option", $rank_by);
    $stmt->bindParam(":show_rank", $show_rank, PDO::PARAM_INT);
    $stmt->bindParam(":selected_subjects", $subjects_str);
    $stmt->bindParam(":compulsory_subjects", $compulsory_subjects_str);
    
    $reportTermDetails = "Term " . $term['term_name'] . ", " . $year;
    $period = $term['term_name'];
    $stmt->bindParam(":report_term_details", $reportTermDetails);
    $stmt->bindParam(":period", $period);
    $stmt->bindParam(":generated_by_teacher_id", $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $reportId = $db->lastInsertId();
        
        // Return report data - FIXED: using $class_name
        $reportData = [
            'id' => $reportId,
            'report_title' => $reportTitle,
            'class_name' => $class_name,
            'stream_name' => $stream_name,
            'ranking_option' => $rank_by,
            'learning_areas' => $subject_count,
            'mean_grade' => 'N/A',
            'status' => 'processing'
        ];
        
        // Start background processing immediately
        try {
            // Process report immediately (you can make this async if needed)
            $backgroundResult = startReportProcessing($reportId, $school_id, $class_id, $stream_id, $exam_id, $subjectsToInclude, $compulsorySubjects, $db);
            
            if ($backgroundResult) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Report created and processing started successfully',
                    'report' => $reportData
                ]);
            } else {
                // Report was created but processing failed
                echo json_encode([
                    'success' => true,
                    'message' => 'Report created but processing failed. Please try regenerating.',
                    'report' => $reportData
                ]);
            }
            
        } catch (Exception $e) {
            // Report was created but processing threw an error
            echo json_encode([
                'success' => true,
                'message' => 'Report created but processing error occurred: ' . $e->getMessage(),
                'report' => $reportData
            ]);
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create report configuration.']);
    }
    
} catch(Exception $e) {
    error_log("Create report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}

function startReportProcessing($reportId, $school_id, $class_id, $stream_id, $exam_id, $subjectsToInclude, $compulsorySubjects, $db) {
    try {
        // Update status to processing
        $updateQuery = "UPDATE tblreportconfigurations SET batch_status = 'processing' WHERE id = :id AND school_id = :school_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(":id", $reportId, PDO::PARAM_INT);
        $updateStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        $updateStmt->execute();
        
        // Get students for this class/stream with school filter
        $studentQuery = "SELECT id, FirstName, SecondName, LastName, AdmNo 
                        FROM tblstudents 
                        WHERE school_id = :school_id 
                        AND class_id = :class_id 
                        AND Status = 'Active'";
        
        if ($stream_id) {
            $studentQuery .= " AND StreamId = :stream_id";
        }
        
        $studentStmt = $db->prepare($studentQuery);
        $studentStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        $studentStmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
        if ($stream_id) {
            $studentStmt->bindParam(":stream_id", $stream_id, PDO::PARAM_INT);
        }
        $studentStmt->execute();
        $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($students)) {
            throw new Exception("No active students found for this class/stream.");
        }
        
        $studentScores = [];
        
        // Calculate scores for each student
        foreach ($students as $student) {
            $totalMarks = 0;
            $subjectCount = 0;
            $subjectScores = [];
            
            foreach ($subjectsToInclude as $subjectId) {
                $subjectId = intval(trim($subjectId));
                if ($subjectId <= 0) continue;
                
                // Verify subject belongs to school
                $subjectCheck = $db->prepare("SELECT id FROM tblsubjects WHERE id = :subject_id AND school_id = :school_id");
                $subjectCheck->bindParam(":subject_id", $subjectId, PDO::PARAM_INT);
                $subjectCheck->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                $subjectCheck->execute();
                
                if ($subjectCheck->rowCount() == 0) continue;
                
                // Get student's score for this subject and exam
                $scoreQuery = "SELECT score_value FROM tblscores 
                              WHERE school_id = :school_id 
                              AND student_id = :student_id 
                              AND subject_id = :subject_id 
                              AND exam_id = :exam_id 
                              LIMIT 1";
                
                $scoreStmt = $db->prepare($scoreQuery);
                $scoreStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
                $scoreStmt->bindParam(":student_id", $student['id'], PDO::PARAM_INT);
                $scoreStmt->bindParam(":subject_id", $subjectId, PDO::PARAM_INT);
                $scoreStmt->bindParam(":exam_id", $exam_id, PDO::PARAM_INT);
                $scoreStmt->execute();
                $score = $scoreStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($score && $score['score_value'] !== null) {
                    $scoreValue = floatval($score['score_value']);
                    $totalMarks += $scoreValue;
                    $subjectCount++;
                    $subjectScores[$subjectId] = $scoreValue;
                }
            }
            
            if ($subjectCount > 0) {
                $average = $totalMarks / $subjectCount;
                $studentScores[] = [
                    'student_id' => $student['id'],
                    'total_marks' => $totalMarks,
                    'average' => $average,
                    'subject_count' => $subjectCount,
                    'subject_scores' => $subjectScores,
                    'name' => $student['FirstName'] . ' ' . $student['SecondName'],
                    'adm_no' => $student['AdmNo']
                ];
            }
        }
        
        if (empty($studentScores)) {
            throw new Exception("No scores found for any students.");
        }
        
        // Sort by total marks (descending)
        usort($studentScores, function($a, $b) {
            return $b['total_marks'] <=> $a['total_marks'];
        });
        
        // Assign ranks
        $rank = 1;
        $previousMarks = null;
        
        foreach ($studentScores as $index => &$student) {
            if ($previousMarks !== null && $student['total_marks'] < $previousMarks) {
                $rank = $index + 1;
            }
            $student['rank'] = $rank;
            $previousMarks = $student['total_marks'];
        }
        
        // Store results in report_cards table
        foreach ($studentScores as $student) {
            // Calculate grade based on average
            $grade = calculateGrade($student['average']);
            
            // Check if report card already exists
            $checkQuery = "SELECT id FROM report_cards 
                          WHERE school_id = :school_id 
                          AND student_id = :student_id 
                          AND exam_id = :exam_id";
            
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
            $checkStmt->bindParam(":student_id", $student['student_id'], PDO::PARAM_INT);
            $checkStmt->bindParam(":exam_id", $exam_id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing record
                $updateCardQuery = "UPDATE report_cards 
                                   SET mean_score = :mean_score, 
                                       grade = :grade, 
                                       status = 'Completed',
                                       updated_at = NOW()
                                   WHERE school_id = :school_id 
                                   AND student_id = :student_id 
                                   AND exam_id = :exam_id";
                
                $updateCardStmt = $db->prepare($updateCardQuery);
            } else {
                // Insert new record
                $updateCardQuery = "INSERT INTO report_cards (
                                    school_id, student_id, class_id, stream_id, exam_id,
                                    mean_score, grade, status, created_at, updated_at
                                  ) VALUES (
                                    :school_id, :student_id, :class_id, :stream_id, :exam_id,
                                    :mean_score, :grade, 'Completed', NOW(), NOW()
                                  )";
                
                $updateCardStmt = $db->prepare($updateCardQuery);
                $updateCardStmt->bindParam(":class_id", $class_id, PDO::PARAM_INT);
                $updateCardStmt->bindParam(":stream_id", $stream_id, PDO::PARAM_INT);
            }
            
            $updateCardStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
            $updateCardStmt->bindParam(":student_id", $student['student_id'], PDO::PARAM_INT);
            $updateCardStmt->bindParam(":exam_id", $exam_id, PDO::PARAM_INT);
            $updateCardStmt->bindParam(":mean_score", $student['average']);
            $updateCardStmt->bindParam(":grade", $grade);
            $updateCardStmt->execute();
        }
        
        // Update report status to completed
        $studentCount = count($studentScores);
        $statusMessage = "Report processed successfully for $studentCount students";
        
        $finalUpdateQuery = "UPDATE tblreportconfigurations 
                            SET batch_status = 'completed', 
                            status_message = :status_message,
                            updated_at = NOW()
                            WHERE id = :id AND school_id = :school_id";
        
        $finalStmt = $db->prepare($finalUpdateQuery);
        $finalStmt->bindParam(":id", $reportId, PDO::PARAM_INT);
        $finalStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        $finalStmt->bindParam(":status_message", $statusMessage);
        $finalStmt->execute();
        
        return true;
        
    } catch(Exception $e) {
        error_log("Process report error: " . $e->getMessage());
        
        // Update status to failed
        $errorQuery = "UPDATE tblreportconfigurations 
                      SET batch_status = 'failed', 
                      status_message = :error_message,
                      updated_at = NOW()
                      WHERE id = :id AND school_id = :school_id";
        
        $errorStmt = $db->prepare($errorQuery);
        $errorMessage = "Processing failed: " . $e->getMessage();
        $errorStmt->bindParam(":error_message", $errorMessage);
        $errorStmt->bindParam(":id", $reportId, PDO::PARAM_INT);
        $errorStmt->bindParam(":school_id", $school_id, PDO::PARAM_INT);
        $errorStmt->execute();
        
        return false;
    }
}

function calculateGrade($score) {
    if ($score >= 80) return 'A';
    if ($score >= 70) return 'B';
    if ($score >= 60) return 'C';
    if ($score >= 50) return 'D';
    return 'E';
}
?>