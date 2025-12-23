<?php
/**
 * Cron Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

// Hook into WordPress cron
add_action('wprbl_check_ips', 'wprbl_cron_check_ips');
add_action('wprbl_send_reports', 'wprbl_cron_send_reports');

function wprbl_cron_check_ips() {
    global $wpdb;
    $table_ips = $wpdb->prefix . 'wprbl_ip_addresses';
    $checker = new WPRBL_Checker();
    
    // Get all users with IPs
    $user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM $table_ips");
    
    foreach ($user_ids as $user_id) {
        $checker->check_user_ips($user_id);
    }
}

function wprbl_cron_send_reports() {
    global $wpdb;
    $table_prefs = $wpdb->prefix . 'wprbl_user_preferences';
    $reports = new WPRBL_Reports();
    
    $users = $wpdb->get_results("
        SELECT u.ID as user_id, u.user_email, up.report_frequency, up.report_day, up.last_report_sent
        FROM {$wpdb->users} u
        INNER JOIN $table_prefs up ON u.ID = up.user_id
        WHERE up.email_notifications = 1
    ", ARRAY_A);
    
    $current_day = (int) date('w'); // 0 = Sunday, 6 = Saturday
    $today = date('Y-m-d');
    
    foreach ($users as $user) {
        $should_send = false;
        
        if ($user['report_frequency'] === 'daily') {
            $last_sent = $user['last_report_sent'] ? date('Y-m-d', strtotime($user['last_report_sent'])) : null;
            $should_send = ($last_sent !== $today);
        } elseif ($user['report_frequency'] === 'weekly') {
            $last_sent = $user['last_report_sent'] ? date('Y-m-d', strtotime($user['last_report_sent'])) : null;
            $should_send = ($current_day == $user['report_day'] && $last_sent !== $today);
        }
        
        if ($should_send) {
            $reports->send_email_report($user['user_id'], $user['user_email'], $user['report_frequency']);
        }
    }
}

