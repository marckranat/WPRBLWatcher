<?php
/**
 * RBL Checker Class
 * Performs DNS lookups to check if IPs are listed in RBLs
 */
require_once 'db.php';
require_once 'config.php';

class RBLChecker {
    private $db;
    private $rateLimitDelays = [];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadRBLs();
    }
    
    private function loadRBLs() {
        global $RBL_LIST;
        
        // Load RBLs from database or initialize them
        $stmt = $this->db->query("SELECT id, name, dns_suffix, rate_limit_delay_ms, requires_paid, enabled FROM rbls WHERE enabled = 1");
        $rbls = $stmt->fetchAll();
        
        if (empty($rbls)) {
            // Initialize RBLs from config
            $this->initializeRBLs();
            $stmt = $this->db->query("SELECT id, name, dns_suffix, rate_limit_delay_ms, requires_paid, enabled FROM rbls WHERE enabled = 1");
            $rbls = $stmt->fetchAll();
        }
        
        foreach ($rbls as $rbl) {
            $this->rateLimitDelays[$rbl['id']] = $rbl['rate_limit_delay_ms'];
        }
        
        return $rbls;
    }
    
    private function initializeRBLs() {
        global $RBL_LIST;
        
        $stmt = $this->db->prepare("INSERT IGNORE INTO rbls (name, dns_suffix, rate_limit_delay_ms, requires_paid, enabled) VALUES (?, ?, ?, ?, 1)");
        
        foreach ($RBL_LIST as $rbl) {
            $stmt->execute([
                $rbl['name'],
                $rbl['dns_suffix'],
                $rbl['rate_limit_ms'] ?? DEFAULT_RATE_LIMIT_MS,
                $rbl['requires_paid'] ? 1 : 0
            ]);
        }
    }
    
    /**
     * Reverse IP address for DNS lookup
     * Converts 192.168.1.1 to 1.1.168.192
     * Note: Most RBLs only support IPv4
     */
    private function reverseIP($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_reverse(explode('.', $ip)));
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6 reverse lookup (most RBLs don't support IPv6)
            // This is included for completeness but may not work with most RBLs
            $unpacked = unpack('H*', inet_pton($ip));
            if ($unpacked) {
                $hex = str_split($unpacked[1]);
                return implode('.', array_reverse($hex)) . '.ip6.arpa';
            }
        }
        return false;
    }
    
    /**
     * Check if IP is listed in a specific RBL
     * Uses timeout to prevent hanging lookups
     */
    public function checkRBL($ip, $rblId, $dnsSuffix) {
        $reversedIP = $this->reverseIP($ip);
        if (!$reversedIP) {
            return ['listed' => false, 'error' => 'Invalid IP address'];
        }
        
        // Skip IPv6 for now as most RBLs don't support it
        if (strpos($reversedIP, 'ip6.arpa') !== false) {
            return ['listed' => false, 'error' => 'IPv6 not supported by most RBLs'];
        }
        
        $lookup = $reversedIP . '.' . $dnsSuffix;
        
        // Set timeout for DNS operations
        $originalTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', DNS_LOOKUP_TIMEOUT);
        
        $startTime = microtime(true);
        $result = null;
        
        // Perform DNS lookup - use custom DNS resolver if configured
        // This allows queries to originate from server IP (via local resolver) instead of public DNS
        $dnsServer = defined('DNS_SERVER') && !empty(DNS_SERVER) ? DNS_SERVER : null;
        
        if (!empty($dnsServer)) {
            // Use custom DNS lookup class for local resolver
            require_once __DIR__ . '/dns_lookup.php';
            $dnsResult = DNS_Lookup::lookup_a($lookup, $dnsServer, DNS_LOOKUP_TIMEOUT);
        } else {
            // Use system default DNS resolver
            $dnsResult = @dns_get_record($lookup, DNS_A + DNS_AAAA);
        }
        
        $elapsed = microtime(true) - $startTime;
        
        // Restore original timeout
        ini_set('default_socket_timeout', $originalTimeout);
        
        // Check for timeout (if elapsed time is close to timeout, likely timed out)
        if ($elapsed >= DNS_LOOKUP_TIMEOUT * 0.9) {
            return ['listed' => false, 'error' => 'DNS lookup timeout', 'response' => null];
        }
        
        // Check if we got a result
        if ($dnsResult !== false && !empty($dnsResult)) {
            // IP is listed - get response text
            $responseText = 'Listed';
            if (isset($dnsResult[0]['ip'])) {
                $responseText = $dnsResult[0]['ip'];
            } elseif (isset($dnsResult[0]['host'])) {
                $responseText = $dnsResult[0]['host'];
            } elseif (isset($dnsResult[0]['target'])) {
                $responseText = $dnsResult[0]['target'];
            }
            return ['listed' => true, 'response' => $responseText];
        }
        
        // Not listed (no DNS record found)
        return ['listed' => false, 'response' => null];
    }
    
    /**
     * Check all enabled RBLs for a single IP
     */
    public function checkIP($ipAddressId, $ip) {
        $stmt = $this->db->prepare("
            SELECT id, dns_suffix, rate_limit_delay_ms, requires_paid 
            FROM rbls 
            WHERE enabled = 1 AND requires_paid = 0
            ORDER BY id
        ");
        $stmt->execute();
        $rbls = $stmt->fetchAll();
        
        $results = [];
        $lastCheckTime = microtime(true);
        
        foreach ($rbls as $rbl) {
            // Rate limiting
            $delay = $rbl['rate_limit_delay_ms'] ?? DEFAULT_RATE_LIMIT_MS;
            if ($delay > 0) {
                $elapsed = (microtime(true) - $lastCheckTime) * 1000;
                if ($elapsed < $delay) {
                    usleep(($delay - $elapsed) * 1000);
                }
            }
            
            // Set maximum execution time per check to prevent hanging
            $checkStartTime = microtime(true);
            $maxCheckTime = DNS_LOOKUP_TIMEOUT + 1; // Add 1 second buffer
            
            $checkResult = $this->checkRBL($ip, $rbl['id'], $rbl['dns_suffix']);
            
            // Verify we didn't exceed timeout
            $checkElapsed = microtime(true) - $checkStartTime;
            if ($checkElapsed > $maxCheckTime) {
                // This check took too long, mark as error
                $checkResult = ['listed' => false, 'error' => 'Check exceeded timeout', 'response' => null];
            }
            
            // Store result in database
            $resultStmt = $this->db->prepare("
                INSERT INTO rbl_check_results (ip_address_id, rbl_id, is_listed, response_text, checked_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                is_listed = VALUES(is_listed),
                response_text = VALUES(response_text),
                checked_at = VALUES(checked_at)
            ");
            
            $resultStmt->execute([
                $ipAddressId,
                $rbl['id'],
                $checkResult['listed'] ? 1 : 0,
                $checkResult['response'] ?? null
            ]);
            
            $results[$rbl['id']] = $checkResult;
            $lastCheckTime = microtime(true);
        }
        
        return $results;
    }
    
    /**
     * Check all IPs for a user
     */
    public function checkUserIPs($userId) {
        $stmt = $this->db->prepare("SELECT id, ip_address FROM ip_addresses WHERE user_id = ?");
        $stmt->execute([$userId]);
        $ips = $stmt->fetchAll();
        
        $checkHistoryId = $this->startCheckHistory($userId, count($ips));
        $totalChecks = 0;
        $blacklistedCount = 0;
        
        foreach ($ips as $ip) {
            $results = $this->checkIP($ip['id'], $ip['ip_address']);
            $totalChecks += count($results);
            
            // Count if any RBL listed this IP
            foreach ($results as $result) {
                if ($result['listed']) {
                    $blacklistedCount++;
                    break; // Count IP only once
                }
            }
        }
        
        $this->completeCheckHistory($checkHistoryId, $totalChecks, $blacklistedCount);
        
        return [
            'total_ips' => count($ips),
            'total_checks' => $totalChecks,
            'blacklisted_count' => $blacklistedCount
        ];
    }
    
    private function startCheckHistory($userId, $totalIPs) {
        $stmt = $this->db->prepare("
            INSERT INTO check_history (user_id, total_ips, check_started_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $totalIPs]);
        return $this->db->lastInsertId();
    }
    
    private function completeCheckHistory($historyId, $totalChecks, $blacklistedCount) {
        $stmt = $this->db->prepare("
            UPDATE check_history 
            SET check_completed_at = NOW(), 
                total_checks = ?, 
                blacklisted_count = ?
            WHERE id = ?
        ");
        $stmt->execute([$totalChecks, $blacklistedCount, $historyId]);
    }
}

