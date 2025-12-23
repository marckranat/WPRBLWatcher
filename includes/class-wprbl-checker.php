<?php
/**
 * RBL Checker Class - WordPress version
 * Version: 1.0.1 - Added Spamhaus response validation
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPRBL_Checker {
    
    const VERSION = '1.0.1'; // Force reload on update
    
    /**
     * Reverse IP address for DNS lookup
     */
    private function reverse_ip($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_reverse(explode('.', $ip)));
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $unpacked = unpack('H*', inet_pton($ip));
            if ($unpacked) {
                $hex = str_split($unpacked[1]);
                return implode('.', array_reverse($hex)) . '.ip6.arpa';
            }
        }
        return false;
    }
    
    /**
     * Log RBL check details for debugging
     */
    private function log_rbl_check($ip, $rbl_id, $dns_suffix, $lookup, $dns_result, $result) {
        // Enable logging by adding define('WPRBL_DEBUG', true) to wp-config.php
        if (!defined('WPRBL_DEBUG') || !WPRBL_DEBUG) {
            return;
        }
        
        $log_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
        $log_file = $log_dir . '/wprbl-debug.log';
        
        $dns_result_str = 'UNKNOWN';
        if ($dns_result === false) {
            $dns_result_str = 'FALSE (DNS error)';
        } elseif (is_array($dns_result)) {
            if (empty($dns_result)) {
                $dns_result_str = 'EMPTY_ARRAY (not listed)';
            } else {
                $dns_result_str = 'ARRAY with ' . count($dns_result) . ' record(s): ';
                foreach ($dns_result as $idx => $record) {
                    $dns_result_str .= "\n  [$idx] type=" . ($record['type'] ?? 'unknown');
                    if (isset($record['ip'])) {
                        $dns_result_str .= " ip=" . $record['ip'];
                    }
                    if (isset($record['host'])) {
                        $dns_result_str .= " host=" . $record['host'];
                    }
                    if (isset($record['target'])) {
                        $dns_result_str .= " target=" . $record['target'];
                    }
                }
            }
        }
        
        $final_status = $result['listed'] ? 'LISTED' : 'NOT_LISTED';
        if (isset($result['error'])) {
            $final_status .= ' (error: ' . $result['error'] . ')';
        }
        if (isset($result['response'])) {
            $final_status .= ' (response: ' . $result['response'] . ')';
        }
        
        $log_entry = sprintf(
            "[%s] [Code v1.0.1] IP: %s | RBL: %s (ID: %d) | Lookup: %s\n",
            current_time('mysql'),
            $ip,
            $dns_suffix,
            $rbl_id,
            $lookup
        );
        $log_entry .= "  DNS Result: " . $dns_result_str . "\n";
        if (isset($result['validation_debug']) && is_array($result['validation_debug'])) {
            $log_entry .= "  Validation: " . implode(" | ", $result['validation_debug']) . "\n";
        }
        $log_entry .= "  Final Status: " . $final_status . "\n";
        $log_entry .= str_repeat('-', 80) . "\n";
        
        @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Check if IP is listed in a specific RBL
     * RBLs return an A record if the IP is listed, or NXDOMAIN if not listed
     * Most RBLs return 127.0.0.x addresses as listing responses
     * Version 1.0.1: Added Spamhaus response validation
     */
    public function check_rbl($ip, $rbl_id, $dns_suffix) {
        // Force code reload - this line should appear in logs if new code is running
        $code_version = '1.0.1';
        $reversed_ip = $this->reverse_ip($ip);
        if (!$reversed_ip) {
            $result = ['listed' => false, 'error' => 'Invalid IP address'];
            $this->log_rbl_check($ip, $rbl_id, $dns_suffix, 'N/A', false, $result);
            return $result;
        }
        
        if (strpos($reversed_ip, 'ip6.arpa') !== false) {
            $result = ['listed' => false, 'error' => 'IPv6 not supported by most RBLs'];
            $this->log_rbl_check($ip, $rbl_id, $dns_suffix, 'N/A', false, $result);
            return $result;
        }
        
        $lookup = $reversed_ip . '.' . $dns_suffix;
        
        // Set timeout for DNS operations
        $original_timeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', WPRBL_DNS_LOOKUP_TIMEOUT);
        
        $start_time = microtime(true);
        
        // Perform DNS lookup - only check for A records (IPv4)
        // RBLs typically return A records with 127.0.0.x addresses when listed
        $dns_result = @dns_get_record($lookup, DNS_A);
        
        $elapsed = microtime(true) - $start_time;
        
        // Restore original timeout
        ini_set('default_socket_timeout', $original_timeout);
        
        // Check for timeout
        if ($elapsed >= WPRBL_DNS_LOOKUP_TIMEOUT * 0.9) {
            $result = ['listed' => false, 'error' => 'DNS lookup timeout', 'response' => null];
            $this->log_rbl_check($ip, $rbl_id, $dns_suffix, $lookup, 'TIMEOUT', $result);
            return $result;
        }
        
        // Check if we got a valid A record result
        // $dns_result will be:
        // - false: DNS error occurred
        // - empty array []: No records found (IP is NOT listed)
        // - array with elements: Records found (IP IS listed)
        if ($dns_result === false) {
            // DNS error - treat as not listed but log the error
            $result = ['listed' => false, 'error' => 'DNS lookup error', 'response' => null];
            $this->log_rbl_check($ip, $rbl_id, $dns_suffix, $lookup, false, $result);
            return $result;
        }
        
        if (!empty($dns_result) && is_array($dns_result)) {
            // We got a DNS response - check if it's a valid listing response
            $response_ip = null;
            if (isset($dns_result[0]['ip']) && !empty($dns_result[0]['ip'])) {
                $response_ip = $dns_result[0]['ip'];
            }
            
            // Validate the response IP for Spamhaus RBLs
            // Spamhaus uses specific response codes: 127.0.0.2-127.0.0.11
            // Other RBLs typically use 127.0.0.1-127.0.0.255 range
            $is_valid_response = false;
            $validation_debug = [];
            
            if ($response_ip) {
                $validation_debug[] = "Response IP: $response_ip";
                
                // Check if it's a valid RBL response (127.0.0.x range)
                if (preg_match('/^127\.0\.0\.(\d+)$/', $response_ip, $matches)) {
                    $last_octet = (int)$matches[1];
                    $validation_debug[] = "Matches 127.0.0.x pattern, last octet: $last_octet";
                    
                    // Spamhaus specific validation
                    if (strpos($dns_suffix, 'spamhaus.org') !== false) {
                        $validation_debug[] = "Spamhaus RBL detected";
                        // Spamhaus valid codes: 2, 3, 4, 9, 10, 11
                        // 127.0.0.2 = XBL, 127.0.0.3 = PBL, 127.0.0.4 = SBL
                        // 127.0.0.9 = DROP, 127.0.0.10 = EDROP, 127.0.0.11 = ZEN
                        if (in_array($last_octet, [2, 3, 4, 9, 10, 11])) {
                            $is_valid_response = true;
                            $validation_debug[] = "Valid Spamhaus code: $last_octet";
                        } else {
                            $validation_debug[] = "Invalid Spamhaus code: $last_octet (not in [2,3,4,9,10,11])";
                        }
                    } else {
                        // For other RBLs, accept any 127.0.0.x response (1-255)
                        if ($last_octet >= 1 && $last_octet <= 255) {
                            $is_valid_response = true;
                            $validation_debug[] = "Valid non-Spamhaus code: $last_octet";
                        } else {
                            $validation_debug[] = "Invalid code: $last_octet (not in 1-255)";
                        }
                    }
                } else {
                    $validation_debug[] = "Does NOT match 127.0.0.x pattern";
                }
            } else {
                $validation_debug[] = "No response IP found";
            }
            
            $validation_debug[] = "Final validation result: " . ($is_valid_response ? 'VALID' : 'INVALID');
            
            if ($is_valid_response) {
                // Valid listing response
                $response_text = $response_ip;
                $result = ['listed' => true, 'response' => $response_text, 'validation_debug' => $validation_debug];
                $this->log_rbl_check($ip, $rbl_id, $dns_suffix, $lookup, $dns_result, $result);
                return $result;
            } else {
                // Invalid response - likely a DNS error or misconfiguration
                // Treat as not listed
                $error_msg = 'Invalid RBL response: ' . ($response_ip ?? 'unknown');
                if ($response_ip && !preg_match('/^127\.0\.0\.(\d+)$/', $response_ip)) {
                    $error_msg .= ' (not in 127.0.0.x range)';
                } elseif ($response_ip && strpos($dns_suffix, 'spamhaus.org') !== false) {
                    if (preg_match('/^127\.0\.0\.(\d+)$/', $response_ip, $m)) {
                        $error_msg .= ' (Spamhaus code ' . $m[1] . ' not in valid range [2,3,4,9,10,11])';
                    }
                }
                $result = [
                    'listed' => false, 
                    'error' => $error_msg,
                    'response' => null,
                    'validation_debug' => $validation_debug
                ];
                $this->log_rbl_check($ip, $rbl_id, $dns_suffix, $lookup, $dns_result, $result);
                return $result;
            }
        }
        
        // Empty result means IP is not listed
        $result = ['listed' => false, 'response' => null];
        $this->log_rbl_check($ip, $rbl_id, $dns_suffix, $lookup, $dns_result, $result);
        return $result;
    }
    
    /**
     * Check all enabled RBLs for a single IP
     */
    public function check_ip($ip_address_id, $ip) {
        global $wpdb;
        $table_rbls = $wpdb->prefix . 'wprbl_rbls';
        $table_results = $wpdb->prefix . 'wprbl_check_results';
        
        $rbls = $wpdb->get_results($wpdb->prepare("
            SELECT id, dns_suffix, rate_limit_delay_ms, requires_paid 
            FROM $table_rbls 
            WHERE enabled = 1 AND requires_paid = 0
            ORDER BY id
        "), ARRAY_A);
        
        $results = [];
        $last_check_time = microtime(true);
        
        foreach ($rbls as $rbl) {
            // Rate limiting
            $delay = $rbl['rate_limit_delay_ms'] ?? WPRBL_DEFAULT_RATE_LIMIT_MS;
            if ($delay > 0) {
                $elapsed = (microtime(true) - $last_check_time) * 1000;
                if ($elapsed < $delay) {
                    usleep(($delay - $elapsed) * 1000);
                }
            }
            
            $check_start_time = microtime(true);
            $max_check_time = WPRBL_DNS_LOOKUP_TIMEOUT + 1;
            
            $check_result = $this->check_rbl($ip, $rbl['id'], $rbl['dns_suffix']);
            
            $check_elapsed = microtime(true) - $check_start_time;
            if ($check_elapsed > $max_check_time) {
                $check_result = ['listed' => false, 'error' => 'Check exceeded timeout', 'response' => null];
            }
            
            // Store result
            $wpdb->replace(
                $table_results,
                [
                    'ip_address_id' => $ip_address_id,
                    'rbl_id' => $rbl['id'],
                    'is_listed' => $check_result['listed'] ? 1 : 0,
                    'response_text' => $check_result['response'] ?? null,
                    'checked_at' => current_time('mysql')
                ],
                ['%d', '%d', '%d', '%s', '%s']
            );
            
            $results[$rbl['id']] = $check_result;
            $last_check_time = microtime(true);
        }
        
        return $results;
    }
    
    /**
     * Check all IPs for a user
     */
    public function check_user_ips($user_id) {
        global $wpdb;
        $table_ips = $wpdb->prefix . 'wprbl_ip_addresses';
        $table_history = $wpdb->prefix . 'wprbl_check_history';
        
        $ips = $wpdb->get_results($wpdb->prepare("
            SELECT id, ip_address 
            FROM $table_ips 
            WHERE user_id = %d
        ", $user_id), ARRAY_A);
        
        $history_id = $this->start_check_history($user_id, count($ips));
        $total_checks = 0;
        $blacklisted_count = 0;
        
        foreach ($ips as $ip) {
            $results = $this->check_ip($ip['id'], $ip['ip_address']);
            $total_checks += count($results);
            
            foreach ($results as $result) {
                if ($result['listed']) {
                    $blacklisted_count++;
                    break;
                }
            }
        }
        
        $this->complete_check_history($history_id, $total_checks, $blacklisted_count);
        
        return [
            'total_ips' => count($ips),
            'total_checks' => $total_checks,
            'blacklisted_count' => $blacklisted_count
        ];
    }
    
    private function start_check_history($user_id, $total_ips) {
        global $wpdb;
        $table_history = $wpdb->prefix . 'wprbl_check_history';
        
        $wpdb->insert(
            $table_history,
            [
                'user_id' => $user_id,
                'total_ips' => $total_ips,
                'check_started_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s']
        );
        
        return $wpdb->insert_id;
    }
    
    private function complete_check_history($history_id, $total_checks, $blacklisted_count) {
        global $wpdb;
        $table_history = $wpdb->prefix . 'wprbl_check_history';
        
        $wpdb->update(
            $table_history,
            [
                'check_completed_at' => current_time('mysql'),
                'total_checks' => $total_checks,
                'blacklisted_count' => $blacklisted_count
            ],
            ['id' => $history_id],
            ['%s', '%d', '%d'],
            ['%d']
        );
    }
}

