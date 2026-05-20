<?php
// process_reports_trigger.php

/**
 * This function checks if the report worker should run and executes it.
 * It's registered as a shutdown function, so it runs after the page is sent to the user.
 */
function run_report_worker_on_shutdown() {
    // --- Configuration ---
    // How often to run the worker, in seconds. 300 seconds = 5 minutes.
    // Adjust this value based on how frequently you need reports generated.
    $runInterval = 300; 

    // Path to a lock file to prevent the script from running multiple times simultaneously.
    $lockFile = __DIR__ . '/../locks/worker.lock'; 
    
    // Path to a file to store the timestamp of the last run.
    $lastRunFile = __DIR__ . '/../locks/worker.lastrun';

    // --- Execution Logic ---
    
    // 1. Check for a lock. If the script is already running, exit silently.
    // The '@' suppresses errors if the file doesn't exist yet.
    $lockHandle = @fopen($lockFile, 'w');
    if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        if ($lockHandle) {
            @fclose($lockHandle);
        }
        return; // Exit if already running or can't create lock file.
    }

    // 2. Check if the interval has passed since the last run.
    $lastRunTime = 0;
    if (file_exists($lastRunFile)) {
        $lastRunTime = (int)@file_get_contents($lastRunFile);
    }

    if (time() - $lastRunTime < $runInterval) {
        // Not time yet. Release the lock and exit.
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return;
    }

    // 3. It's time to run.
    // Update the last run time immediately to prevent another process from starting.
    @file_put_contents($lastRunFile, time());
    
    // This allows the script to continue running even if the user closes their browser.
    ignore_user_abort(true);

    // We will run the main worker script inside a try/finally block
    // to ensure the lock is always released, even if an error occurs.
    try {
        // IMPORTANT: Make sure this path correctly points to your worker script.
        // Assuming 'process_reports_trigger.php' is in the root alongside 'worker_generate_reports.php'
        require_once(__DIR__ . 'worker_generate_reports.php'); 
    } finally {
        // 4. IMPORTANT: Always release the lock.
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

// Register the function to run at the end of the script execution.
// This is the key to the "poor man's cron" technique.
register_shutdown_function('run_report_worker_on_shutdown');