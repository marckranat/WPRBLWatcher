<?php
/**
 * Cron script to send scheduled reports
 * Run this daily: php send_reports.php
 * For weekly reports, check the day of week
 */
require_once 'reports.php';
require_once 'db.php';

$db = Database::getInstance()->getConnection();
$reportGen = new ReportGenerator();

// Get all users with email notifications enabled
$stmt = $db->query("
    SELECT u.id, u.email, up.report_frequency, up.report_day, up.last_report_sent
    FROM users u
    INNER JOIN user_preferences up ON u.id = up.user_id
    WHERE up.email_notifications = 1
");
$users = $stmt->fetchAll();

$currentDay = date('w'); // 0 = Sunday, 6 = Saturday
$today = date('Y-m-d');

foreach ($users as $user) {
    $shouldSend = false;
    
    if ($user['report_frequency'] === 'daily') {
        // Send daily if not sent today
        $lastSent = $user['last_report_sent'] ? date('Y-m-d', strtotime($user['last_report_sent'])) : null;
        $shouldSend = ($lastSent !== $today);
    } elseif ($user['report_frequency'] === 'weekly') {
        // Send weekly if it's the configured day and not sent today
        $lastSent = $user['last_report_sent'] ? date('Y-m-d', strtotime($user['last_report_sent'])) : null;
        $shouldSend = ($currentDay == $user['report_day'] && $lastSent !== $today);
    }
    
    if ($shouldSend) {
        echo "Sending {$user['report_frequency']} report to {$user['email']}...\n";
        $sent = $reportGen->sendEmailReport($user['id'], $user['email'], $user['report_frequency']);
        if ($sent) {
            echo "  - Report sent successfully\n";
        } else {
            echo "  - Failed to send report\n";
        }
    }
}

echo "Report sending completed.\n";

