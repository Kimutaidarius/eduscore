<?php
// jobs/ReportQueueProcessor.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/ReportGenerator.php';

class ReportQueueProcessor {
    private $db;
    private $running = true;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function start() {
        echo "Report Queue Processor started...\n";
        
        while ($this->running) {
            $this->processNextJob();
            sleep(2); // Wait before checking for next job
        }
    }
    
    private function processNextJob() {
        // Get next pending job
        $stmt = $this->db->prepare("
            SELECT * FROM report_generation_queue 
            WHERE status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            return;
        }
        
        // Mark as processing
        $this->updateJobStatus($job['id'], 'processing', null, null, null, time());
        
        try {
            $student_ids = json_decode($job['student_ids'], true);
            $school_id = $job['school_id'];
            
            // Get teacher info for this job
            $teacherStmt = $this->db->prepare("
                SELECT teacher_id FROM merged_reports WHERE id = :report_id
            ");
            $teacherStmt->execute([':report_id' => $job['result_report_id']]);
            $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
            
            $reportGenerator = new ReportGenerator($this->db, $school_id, $teacher['teacher_id'] ?? null);
            
            // Process in chunks to avoid memory issues
            $chunkSize = 10;
            $processed = 0;
            $totalPages = 0;
            
            for ($i = 0; $i < count($student_ids); $i += $chunkSize) {
                $chunk = array_slice($student_ids, $i, $chunkSize);
                
                // Process chunk
                $result = $reportGenerator->generateMergedReport(
                    $job['class_id'],
                    $job['stream_id'],
                    $job['exam_id'],
                    $job['term_id'],
                    $job['academic_year'],
                    $chunk
                );
                
                $processed += count($chunk);
                $totalPages = $result['total_pages'] ?? $totalPages;
                
                // Update progress
                $progress = ($processed / $job['total_students']) * 100;
                $this->updateJobStatus($job['id'], 'processing', $progress, $processed, null);
                
                // Update merged report
                $this->updateMergedReport($job['result_report_id'], $totalPages, $processed);
            }
            
            // Mark as completed
            $this->updateJobStatus($job['id'], 'completed', 100, $processed, time());
            $this->updateMergedReport($job['result_report_id'], $totalPages, $processed, 'completed');
            
            echo "Job {$job['id']} completed. Processed {$processed} students.\n";
            
        } catch (Exception $e) {
            // Mark as failed
            $this->updateJobStatus($job['id'], 'failed', null, null, null, null, $e->getMessage());
            $this->updateMergedReport($job['result_report_id'], null, null, 'failed');
            
            echo "Job {$job['id']} failed: " . $e->getMessage() . "\n";
        }
    }
    
    private function updateJobStatus($job_id, $status, $progress = null, $processed = null, $completed_at = null, $started_at = null, $error = null) {
        $updates = [];
        $params = [':job_id' => $job_id];
        
        if ($status !== null) {
            $updates[] = "status = :status";
            $params[':status'] = $status;
        }
        if ($progress !== null) {
            $updates[] = "progress = :progress";
            $params[':progress'] = $progress;
        }
        if ($processed !== null) {
            $updates[] = "processed_students = :processed";
            $params[':processed'] = $processed;
        }
        if ($completed_at !== null) {
            $updates[] = "completed_at = FROM_UNIXTIME(:completed_at)";
            $params[':completed_at'] = $completed_at;
        }
        if ($started_at !== null) {
            $updates[] = "started_at = FROM_UNIXTIME(:started_at)";
            $params[':started_at'] = $started_at;
        }
        if ($error !== null) {
            $updates[] = "error_message = :error";
            $params[':error'] = $error;
        }
        
        if (empty($updates)) {
            return;
        }
        
        $sql = "UPDATE report_generation_queue SET " . implode(', ', $updates) . " WHERE id = :job_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
    
    private function updateMergedReport($report_id, $total_pages = null, $total_students = null, $status = null) {
        $updates = [];
        $params = [':report_id' => $report_id];
        
        if ($total_pages !== null) {
            $updates[] = "total_pages = :total_pages";
            $params[':total_pages'] = $total_pages;
        }
        if ($total_students !== null) {
            $updates[] = "total_students = :total_students";
            $params[':total_students'] = $total_students;
        }
        if ($status !== null) {
            $updates[] = "status = :status";
            $params[':status'] = $status;
        }
        
        if (empty($updates)) {
            return;
        }
        
        $sql = "UPDATE merged_reports SET " . implode(', ', $updates) . " WHERE id = :report_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
    
    public function stop() {
        $this->running = false;
    }
}

// Run the processor
if (php_sapi_name() === 'cli') {
    $processor = new ReportQueueProcessor($db);
    $processor->start();
}