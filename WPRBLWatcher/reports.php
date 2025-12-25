<?php
/**
 * Report Generation
 */
require_once 'db.php';
require_once 'config.php';

class ReportGenerator {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Get blacklisted IPs for a user, sorted by number of listings
     */
    public function getBlacklistedIPs($userId, $startDate = null, $endDate = null) {
        $dateFilter = "";
        $params = [$userId];
        
        if ($startDate && $endDate) {
            $dateFilter = "AND rcr.checked_at BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                ip.id,
                ip.ip_address,
                ip.label,
                COUNT(DISTINCT rcr.rbl_id) as listing_count,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') as rbl_names,
                MAX(rcr.checked_at) as last_checked
            FROM ip_addresses ip
            INNER JOIN rbl_check_results rcr ON ip.id = rcr.ip_address_id
            INNER JOIN rbls r ON rcr.rbl_id = r.id
            WHERE ip.user_id = ? 
                AND rcr.is_listed = 1
                $dateFilter
            GROUP BY ip.id, ip.ip_address, ip.label
            ORDER BY listing_count DESC, ip.ip_address ASC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Generate HTML report
     */
    public function generateHTMLReport($userId, $startDate = null, $endDate = null) {
        $blacklistedIPs = $this->getBlacklistedIPs($userId, $startDate, $endDate);
        
        if (empty($blacklistedIPs)) {
            return "<p>No blacklisted IPs found for the selected period.</p>";
        }
        
        $html = "<h2>RBL Watcher Report</h2>";
        $html .= "<p>Report generated: " . date('Y-m-d H:i:s') . "</p>";
        $html .= "<p>Total blacklisted IPs: " . count($blacklistedIPs) . "</p>";
        $html .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        $html .= "<thead><tr>";
        $html .= "<th>IP Address</th>";
        $html .= "<th>Label</th>";
        $html .= "<th>Listings</th>";
        $html .= "<th>RBLs</th>";
        $html .= "<th>Last Checked</th>";
        $html .= "</tr></thead><tbody>";
        
        foreach ($blacklistedIPs as $ip) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($ip['ip_address']) . "</td>";
            $html .= "<td>" . htmlspecialchars($ip['label'] ?? '') . "</td>";
            $html .= "<td style='text-align: center; font-weight: bold;'>" . $ip['listing_count'] . "</td>";
            $html .= "<td>" . htmlspecialchars($ip['rbl_names']) . "</td>";
            $html .= "<td>" . htmlspecialchars($ip['last_checked']) . "</td>";
            $html .= "</tr>";
        }
        
        $html .= "</tbody></table>";
        return $html;
    }
    
    /**
     * Generate CSV report
     */
    public function generateCSVReport($userId, $startDate = null, $endDate = null) {
        $blacklistedIPs = $this->getBlacklistedIPs($userId, $startDate, $endDate);
        
        $csv = "IP Address,Label,Listings,RBLs,Last Checked\n";
        
        foreach ($blacklistedIPs as $ip) {
            $csv .= sprintf(
                "%s,%s,%d,%s,%s\n",
                $ip['ip_address'],
                $ip['label'] ?? '',
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
    public function sendEmailReport($userId, $email, $reportType = 'daily') {
        $prefs = $this->getUserPreferences($userId);
        
        if (!$prefs['email_notifications']) {
            return false;
        }
        
        // Determine date range
        $endDate = date('Y-m-d H:i:s');
        if ($reportType === 'weekly') {
            $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
        } else {
            $startDate = date('Y-m-d H:i:s', strtotime('-1 day'));
        }
        
        $html = $this->generateHTMLReport($userId, $startDate, $endDate);
        $subject = "RBL Watcher " . ucfirst($reportType) . " Report - " . date('Y-m-d');
        
        // Simple email sending (you may want to use PHPMailer or similar)
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        
        $sent = mail($email, $subject, $html, $headers);
        
        if ($sent) {
            $this->updateLastReportSent($userId);
        }
        
        return $sent;
    }
    
    private function getUserPreferences($userId) {
        $stmt = $this->db->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch();
        
        if (!$prefs) {
            // Create default preferences
            $stmt = $this->db->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
            $stmt->execute([$userId]);
            return ['report_frequency' => 'daily', 'email_notifications' => 1];
        }
        
        return $prefs;
    }
    
    private function updateLastReportSent($userId) {
        $stmt = $this->db->prepare("UPDATE user_preferences SET last_report_sent = NOW() WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
}

