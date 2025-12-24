<?php
/**
 * Report Generation Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPRBL_Reports {
    
    /**
     * Get blacklisted IPs for a user, sorted by number of listings
     */
    public function get_blacklisted_ips($user_id, $start_date = null, $end_date = null) {
        global $wpdb;
        $table_ips = $wpdb->prefix . 'wprbl_ip_addresses';
        $table_results = $wpdb->prefix . 'wprbl_check_results';
        $table_rbls = $wpdb->prefix . 'wprbl_rbls';
        
        $date_filter = "";
        $params = [$user_id];
        
        if ($start_date && $end_date) {
            $date_filter = "AND rcr.checked_at BETWEEN %s AND %s";
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        $query = $wpdb->prepare("
            SELECT 
                ip.id,
                ip.ip_address,
                COUNT(DISTINCT rcr.rbl_id) as listing_count,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') as rbl_names,
                MAX(rcr.checked_at) as last_checked
            FROM $table_ips ip
            INNER JOIN $table_results rcr ON ip.id = rcr.ip_address_id
            INNER JOIN $table_rbls r ON rcr.rbl_id = r.id
            WHERE ip.user_id = %d 
                AND rcr.is_listed = 1
                $date_filter
            GROUP BY ip.id, ip.ip_address
            ORDER BY listing_count DESC, ip.ip_address ASC
        ", $params);
        
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Generate HTML report
     */
    public function generate_html_report($user_id, $start_date = null, $end_date = null) {
        $blacklisted_ips = $this->get_blacklisted_ips($user_id, $start_date, $end_date);
        
        if (empty($blacklisted_ips)) {
            return "<p>No blacklisted IPs found for the selected period.</p>";
        }
        
        $html = "<h2>RBL Watcher Report</h2>";
        $html .= "<p>Report generated: " . current_time('mysql') . "</p>";
        $html .= "<p>Total blacklisted IPs: " . count($blacklisted_ips) . "</p>";
        $html .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        $html .= "<thead><tr>";
        $html .= "<th>IP Address</th>";
        $html .= "<th>Listings</th>";
        $html .= "<th>RBLs</th>";
        $html .= "<th>Last Checked</th>";
        $html .= "</tr></thead><tbody>";
        
        foreach ($blacklisted_ips as $ip) {
            $html .= "<tr>";
            $html .= "<td>" . esc_html($ip['ip_address']) . "</td>";
            $html .= "<td style='text-align: center; font-weight: bold;'>" . $ip['listing_count'] . "</td>";
            $html .= "<td>" . esc_html($ip['rbl_names']) . "</td>";
            $html .= "<td>" . esc_html($ip['last_checked']) . "</td>";
            $html .= "</tr>";
        }
        
        $html .= "</tbody></table>";
        return $html;
    }
    
    /**
     * Generate CSV report
     */
    public function generate_csv_report($user_id, $start_date = null, $end_date = null) {
        $blacklisted_ips = $this->get_blacklisted_ips($user_id, $start_date, $end_date);
        
        $csv = "IP Address,Listings,RBLs,Last Checked\n";
        
        foreach ($blacklisted_ips as $ip) {
            $csv .= sprintf(
                "%s,%d,%s,%s\n",
                $ip['ip_address'],
                $ip['listing_count'],
                '"' . str_replace('"', '""', $ip['rbl_names']) . '"',
                $ip['last_checked']
            );
        }
        
        return $csv;
    }
    
    /**
     * Send email report
     */
    public function send_email_report($user_id, $email, $report_type = 'daily') {
        $prefs = $this->get_user_preferences($user_id);
        
        if (!$prefs['email_notifications']) {
            return false;
        }
        
        // Determine date range
        $end_date = current_time('mysql');
        if ($report_type === 'weekly') {
            $start_date = date('Y-m-d H:i:s', strtotime('-7 days', strtotime($end_date)));
        } else {
            $start_date = date('Y-m-d H:i:s', strtotime('-1 day', strtotime($end_date)));
        }
        
        $html = $this->generate_html_report($user_id, $start_date, $end_date);
        $subject = "RBL Watcher " . ucfirst($report_type) . " Report - " . date('Y-m-d');
        
        // Get from_email from preferences
        $from_email = !empty($prefs['from_email']) ? $prefs['from_email'] : null;
        if (empty($from_email)) {
            $site_url = site_url();
            $domain = parse_url($site_url, PHP_URL_HOST);
            $from_email = 'rbl@' . ($domain ? $domain : 'example.com');
        }
        
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if ($from_email) {
            $headers[] = 'From: ' . $from_email;
        }
        
        $sent = wp_mail($email, $subject, $html, $headers);
        
        if ($sent) {
            $this->update_last_report_sent($user_id);
        }
        
        return $sent;
    }
    
    private function get_user_preferences($user_id) {
        global $wpdb;
        $table_prefs = $wpdb->prefix . 'wprbl_user_preferences';
        
        $prefs = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_prefs WHERE user_id = %d
        ", $user_id), ARRAY_A);
        
        if (!$prefs) {
            // Get default domain for from_email
            $site_url = site_url();
            $domain = parse_url($site_url, PHP_URL_HOST);
            $default_email = 'rbl@' . ($domain ? $domain : 'example.com');
            
            // Create default preferences
            $wpdb->insert(
                $table_prefs,
                [
                    'user_id' => $user_id, 
                    'report_frequency' => 'daily', 
                    'email_notifications' => 1,
                    'from_email' => $default_email
                ],
                ['%d', '%s', '%d', '%s']
            );
            return [
                'report_frequency' => 'daily', 
                'email_notifications' => 1, 
                'report_day' => null,
                'from_email' => $default_email
            ];
        }
        
        // Set default from_email if not set
        if (empty($prefs['from_email'])) {
            $site_url = site_url();
            $domain = parse_url($site_url, PHP_URL_HOST);
            $prefs['from_email'] = 'rbl@' . ($domain ? $domain : 'example.com');
        }
        
        return $prefs;
    }
    
    private function update_last_report_sent($user_id) {
        global $wpdb;
        $table_prefs = $wpdb->prefix . 'wprbl_user_preferences';
        
        $wpdb->update(
            $table_prefs,
            ['last_report_sent' => current_time('mysql')],
            ['user_id' => $user_id],
            ['%s'],
            ['%d']
        );
    }
}

