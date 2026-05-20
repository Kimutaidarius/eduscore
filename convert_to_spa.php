<?php
/**
 * REMOVE SPA FUNCTIONALITY - Revert all pages to normal full-page reload
 * Run this script once to remove all SPA-related code
 * WARNING: Backup your files first!
 */

$pages = [
    'dashboard.php',
    'classes.php', 
    'students.php',
    'studentslist.php',
    'teachers.php',
    'subjects.php',
    'roles.php',
    'lessons.php',
    'grading.php',
    'exams.php',
    'scores.php',
    'reports.php',
    'templates.php',
    'meritlist.php',
    'analytics-page.php',
    'sms.php',
    'promotion.php',
    'utility.php',
    'timetable.php',
    'attendance.php',
    'subscription.php'
];

$conversionLog = [];

foreach ($pages as $page) {
    if (!file_exists($page)) {
        $conversionLog[] = "Skipping: $page (not found)";
        continue;
    }
    
    $content = file_get_contents($page);
    $originalContent = $content;
    
    // Step 1: Remove AJAX handler include
    $content = preg_replace('/\/\/ Include AJAX handler.*?\nrequire_once \'includes\/ajax-handler.php\';\n\$isAjax = isAjaxRequest\(\);\n/', '', $content);
    $content = preg_replace('/require_once \'includes\/ajax-handler.php\';/', '', $content);
    $content = preg_replace('/\$isAjax = isAjaxRequest\(\);/', '', $content);
    
    // Step 2: Remove the conditional wrappers <?php if (!$isAjax): ?> and <?php endif; ?>
    // Remove opening conditional
    $content = preg_replace('/<\?php if \(\!\$isAjax\): \?>\s*/', '', $content);
    $content = preg_replace('/<\?php if \(\$isAjax\): \?>/', '', $content);
    
    // Remove closing conditional
    $content = preg_replace('/<\?php endif; \?>/', '', $content);
    $content = preg_replace('/<\?php endif; \?>\s*$/', '', $content);
    
    // Step 3: Remove spa-router.js script tag
    $content = preg_replace('/<script src="assets\/js\/spa-router\.js"><\/script>/', '', $content);
    $content = preg_replace('/<script src="assets\/js\/spa-router\.js"><\/script>\s*/', '', $content);
    
    // Step 4: Remove spa-transitions.css link
    $content = preg_replace('/<link rel="stylesheet" href="assets\/css\/spa-transitions\.css">/', '', $content);
    
    // Step 5: Remove any SPA custom event listeners and related code
    $content = preg_replace('/\/\/ SPA Sidebar Integration.*?\/\/ =+.*?(?=\/\/ |$)/s', '', $content);
    $content = preg_replace('/document\.addEventListener\(\'spa:contentLoaded\'.*?\}\);/s', '', $content);
    $content = preg_replace('/window\.updateDataByAcademicLevel.*?function.*?\(\) \{[^}]*\}/s', '', $content);
    
    // Step 6: Remove any data-spa attributes
    $content = preg_replace('/data-spa-[a-z-]+="[^"]*"/', '', $content);
    
    // Step 7: Fix any broken HTML structure (remove extra conditional tags)
    $content = preg_replace('/\?>\s*<\?php/', '?> <?php', $content);
    $content = preg_replace('/\?>\s*<\?php\s*\?>/', '?>', $content);
    
    // Step 8: Restore normal HTML structure - ensure DOCTYPE is at the top
    $content = preg_replace('/^\s*<\?php.*?\?>\s*<!DOCTYPE/', '<!DOCTYPE', $content);
    
    // Step 9: Clean up any leftover PHP tags
    $content = preg_replace('/\?>\s*<\?php/', '?> <?php', $content);
    
    // Step 10: Fix any double ?> tags
    $content = preg_replace('/\?>\s*\?>/', '?>', $content);
    
    // Save the reverted file
    if ($content !== $originalContent) {
        file_put_contents($page, $content);
        $conversionLog[] = "✓ Reverted: $page";
    } else {
        $conversionLog[] = "⚠ No changes made to: $page (may already be reverted)";
    }
}

// Also remove the SPA router file and CSS file if they exist
$filesToRemove = [
    'assets/js/spa-router.js',
    'assets/css/spa-transitions.css',
    'includes/ajax-handler.php'
];

foreach ($filesToRemove as $file) {
    if (file_exists($file)) {
        // Optionally backup before removing
        $backupFile = $file . '.backup';
        copy($file, $backupFile);
        unlink($file);
        $conversionLog[] = "✓ Removed: $file (backup saved as $backupFile)";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "SPA FUNCTIONALITY REMOVAL COMPLETE\n";
echo str_repeat("=", 60) . "\n\n";

foreach ($conversionLog as $log) {
    echo $log . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "WHAT WAS REMOVED:\n";
echo "1. AJAX handler includes (\$isAjax, isAjaxRequest)\n";
echo "2. Conditional wrappers (<?php if (!\$isAjax): ?> and <?php endif; ?>)\n";
echo "3. spa-router.js script tags\n";
echo "4. spa-transitions.css links\n";
echo "5. SPA-specific event listeners and JavaScript\n";
echo "6. SPA data attributes\n\n";

echo "IMPORTANT:\n";
echo "1. Pages now use normal full-page reloads\n";
echo "2. Sidebar links will reload the entire page\n";
echo "3. Backups of SPA files were saved with .backup extension\n";
echo "4. Test your application to ensure everything works normally\n";