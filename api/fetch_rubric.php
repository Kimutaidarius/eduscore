<?php
require_once 'db_helper.php';

class FetchRubric extends DBHelper {
    public function __construct() {
        parent::__construct();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendError('Method not allowed.', 405);
        }
        
        $this->handleRequest();
    }

    private function handleRequest() {
        try {
            $score_value = isset($_GET['score_value']) ? (float)$_GET['score_value'] : 0;
            $total_score = isset($_GET['total_score']) ? (float)$_GET['total_score'] : 100;
            $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
            $subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
            $stream_id = isset($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            
            if ($score_value < 0 || $score_value > $total_score) {
                $this->sendError("Score must be between 0 and $total_score", 400);
            }

            // Calculate percentage
            $percentage = ($total_score > 0) ? ($score_value / $total_score) * 100 : 0;
            
            // Get grading information
            $grading = $this->getGradingInfo($percentage, $class_id, $subject_id, $stream_id);
            
            $response = [
                'score_value' => $score_value,
                'total_score' => $total_score,
                'percentage' => round($percentage, 2),
                'grading' => $grading
            ];
            
            $this->sendResponse($response, 'success', 'Grading calculated successfully');
            
        } catch (Exception $e) {
            error_log("Error fetching rubric: " . $e->getMessage());
            $this->sendError('Failed to calculate grading.', 500);
        }
    }

    private function getGradingInfo($percentage, $class_id, $subject_id, $stream_id) {
        // Try subject-specific grading first
        $gradingSql = "SELECT grade, grade_alias, remarks, principal_remarks, points
                      FROM tblsubjectgrading 
                      WHERE class_id = :class_id 
                      AND subject_id = :subject_id 
                      AND school_id = :school_id 
                      AND :percentage BETWEEN lower_limit AND upper_limit
                      LIMIT 1";
        
        $gradingParams = [
            ':class_id' => $class_id,
            ':subject_id' => $subject_id,
            ':school_id' => $this->school_id,
            ':percentage' => $percentage
        ];
        
        if ($stream_id) {
            $gradingSql = "SELECT grade, grade_alias, remarks, principal_remarks, points
                          FROM tblsubjectgrading 
                          WHERE class_id = :class_id 
                          AND subject_id = :subject_id 
                          AND stream_id = :stream_id
                          AND school_id = :school_id 
                          AND :percentage BETWEEN lower_limit AND upper_limit
                          LIMIT 1";
            $gradingParams[':stream_id'] = $stream_id;
        }
        
        $subjectGrading = $this->fetchOne($gradingSql, $gradingParams);
        
        if ($subjectGrading) {
            return [
                'grade' => $subjectGrading['grade_alias'] ?: $subjectGrading['grade'],
                'rubric' => $subjectGrading['grade'],
                'remarks' => $subjectGrading['remarks'],
                'principal_remarks' => $subjectGrading['principal_remarks'],
                'points' => (float)$subjectGrading['points'],
                'is_custom' => true
            ];
        }
        
        // Fallback to default grading
        $defaultGrading = $this->fetchOne(
            "SELECT grade, grade_alias, remarks, principal_remarks, points
             FROM tblsubjectgrading 
             WHERE class_id = 0 
             AND subject_id = 0 
             AND school_id = 0 
             AND :percentage BETWEEN lower_limit AND upper_limit
             LIMIT 1",
            [':percentage' => $percentage]
        );
        
        if ($defaultGrading) {
            return [
                'grade' => $defaultGrading['grade_alias'] ?: $defaultGrading['grade'],
                'rubric' => $defaultGrading['grade'],
                'remarks' => $defaultGrading['remarks'],
                'principal_remarks' => $defaultGrading['principal_remarks'],
                'points' => (float)$defaultGrading['points'],
                'is_custom' => false
            ];
        }
        
        // Default fallback
        return [
            'grade' => 'N/A',
            'rubric' => 'Not Graded',
            'remarks' => 'Score recorded',
            'principal_remarks' => '',
            'points' => 0,
            'is_custom' => false
        ];
    }
}

new FetchRubric();
?>