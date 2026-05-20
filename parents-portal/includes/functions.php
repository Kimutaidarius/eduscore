<?php
// includes/functions.php - Shared functions for parents portal

function getGradeFromPercentage($percentage, $scales) {
    if (empty($scales)) {
        if ($percentage >= 75) return ['grade' => 'EE', 'meaning' => 'Exceeding Expectations', 'color' => '#10b981'];
        if ($percentage >= 50) return ['grade' => 'ME', 'meaning' => 'Meeting Expectations', 'color' => '#3b82f6'];
        if ($percentage >= 25) return ['grade' => 'AE', 'meaning' => 'Approaching Expectations', 'color' => '#f59e0b'];
        return ['grade' => 'BE', 'meaning' => 'Below Expectations', 'color' => '#ef4444'];
    }
    foreach ($scales as $scale) {
        if ($percentage >= $scale['lower_limit'] && $percentage <= $scale['upper_limit']) {
            return [
                'grade' => $scale['grade'],
                'meaning' => $scale['remarks'] ?? $scale['grade_alias'] ?? $scale['grade'],
                'color' => getGradeColor($scale['grade'])
            ];
        }
    }
    return ['grade' => 'N/A', 'meaning' => 'Not Available', 'color' => '#6b7280'];
}

function getGradeColor($grade) {
    $colors = [
        'EE' => '#10b981',
        'ME' => '#3b82f6',
        'AE' => '#f59e0b',
        'BE' => '#ef4444',
        'X' => '#6b7280'
    ];
    return $colors[$grade] ?? '#6b7280';
}
?>