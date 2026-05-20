<?php
require_once 'includes/config.php';

$stmt = $dbh->query("
    SELECT ac.*, s.school_email, s.school_name
    FROM tbl_activation_codes ac
    JOIN tblschoolinfo s ON s.id = ac.school_id
    WHERE ac.is_used = 0
");

foreach ($stmt as $row) {
    $now = time();
    $expires = strtotime($row['expires_at']);
    $remaining = $expires - $now;

    // 12 hours reminder
    if ($remaining <= 43200 && !$row['last_reminder_sent']) {
        mail(
            $row['school_email'],
            "Activation Pending – Eduscore",
            "Dear {$row['school_name']},\n\nYour activation is pending. Please complete payment."
        );

        $dbh->prepare("
            UPDATE tbl_activation_codes
            SET last_reminder_sent = NOW()
            WHERE id = :id
        ")->execute([':id' => $row['id']]);
    }
}
