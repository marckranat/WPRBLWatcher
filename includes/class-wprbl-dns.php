<?php
/**
 * Custom DNS Lookup Class
 * Allows querying a specific DNS server to avoid Spamhaus 127.255.255.254 errors
 * 
 * This class provides DNS lookup functionality that can query a specific DNS server
 * (like a local resolver at 127.0.0.1) instead of using the system default resolver.
 * This is essential for Spamhaus RBL queries, which require queries to originate
 * from your server's IP address with proper reverse DNS, not from public DNS resolvers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPRBL_DNS {
    
    /**
     * Perform DNS A record lookup using a specific DNS server
     * 
     * @param string $hostname The hostname to lookup
     * @param string|null $dns_server DNS server IP (null = use system default)
     * @param int $timeout Timeout in seconds
     * @param bool $recursion_desired Whether to request recursion (RD bit)
     * @return array|false Array of A records or false on failure
     */
    public static function lookup_a($hostname, $dns_server = null, $timeout = 3, $recursion_desired = true) {
        // If no custom DNS server specified, use system default
        if (empty($dns_server)) {
            return self::_system_lookup($hostname, DNS_A);
        }
        
        // Use custom DNS server via socket
        return self::_socket_lookup($hostname, $dns_server, $timeout, DNS_A, $recursion_desired);
    }
    
    /**
     * Perform DNS lookup using system default resolver
     * 
     * @param string $hostname The hostname to lookup
     * @param int $type DNS record type (DNS_A, DNS_AAAA, etc.)
     * @return array|false Array of records or false on failure
     */
    private static function _system_lookup($hostname, $type) {
        return @dns_get_record($hostname, $type);
    }
    
    /**
     * Perform DNS lookup via socket to a specific DNS server
     * 
     * @param string $hostname The hostname to lookup
     * @param string $dns_server DNS server IP address
     * @param int $timeout Timeout in seconds
     * @param int $type DNS record type (DNS_A = 1)
     * @param bool $recursion_desired Whether to request recursion (RD bit)
     * @return array|false Array of A records or false on failure
     */
    private static function _socket_lookup($hostname, $dns_server, $timeout, $type, $recursion_desired = true) {
        // Only support A records for now (RBL lookups only need A records)
        if ($type !== DNS_A) {
            return false;
        }
        
        // Generate a random transaction ID
        $transaction_id = mt_rand(1, 65535);
        
        // Build DNS query packet
        // For local resolvers, we want recursion so they can resolve the query
        // But if the resolver forwards to public DNS, Spamhaus will still reject it
        $query = self::_build_dns_query($hostname, $transaction_id, $recursion_desired);
        
        if ($query === false) {
            return false;
        }
        
        // Open UDP socket to DNS server
        $socket = @fsockopen('udp://' . $dns_server, 53, $errno, $errstr, $timeout);
        if (!$socket) {
            return false;
        }
        
        // Set socket timeout
        stream_set_timeout($socket, $timeout);
        
        // Send query
        $sent = @fwrite($socket, $query);
        if ($sent === false) {
            @fclose($socket);
            return false;
        }
        
        // Wait for response - UDP can be tricky, so we'll try multiple approaches
        $start_time = microtime(true);
        $response = false;
        $read_attempts = 0;
        $max_read_attempts = 50; // Try reading multiple times
        
        // First, try using stream_select to wait for data
        $read = [$socket];
        $write = null;
        $except = null;
        
        // Wait up to timeout seconds for data
        while (microtime(true) - $start_time < $timeout) {
            $remaining_time = $timeout - (microtime(true) - $start_time);
            if ($remaining_time <= 0) {
                break;
            }
            
            // Use stream_select to wait for data (more reliable than fread on UDP)
            $ready = @stream_select($read, $write, $except, (int)$remaining_time, (int)(($remaining_time - (int)$remaining_time) * 1000000));
            
            if ($ready === false) {
                // Error occurred, but try reading anyway (UDP responses might be there)
                $response = @fread($socket, 512);
                if ($response !== false && strlen($response) >= 12) {
                    break;
                }
                // If no data and error, wait a bit and try again
                usleep(50000); // 50ms
                continue;
            }
            
            if ($ready > 0 && in_array($socket, $read)) {
                // Data is available, read it
                $response = @fread($socket, 512);
                if ($response !== false && strlen($response) >= 12) {
                    break; // Got valid response
                }
            } else {
                // No data indicated, but try reading anyway (UDP can be asynchronous)
                $response = @fread($socket, 512);
                if ($response !== false && strlen($response) >= 12) {
                    break; // Got response even though stream_select didn't indicate it
                }
            }
            
            // Check if socket timed out
            $meta = @stream_get_meta_data($socket);
            if ($meta['timed_out']) {
                // Even if timed out, try one more read (response might have arrived)
                $response = @fread($socket, 512);
                if ($response !== false && strlen($response) >= 12) {
                    break;
                }
                break;
            }
            
            // Small delay to avoid busy waiting
            usleep(10000); // 10ms
            $read_attempts++;
            if ($read_attempts >= $max_read_attempts) {
                break;
            }
        }
        
        @fclose($socket);
        
        // Check if we got a valid response
        if ($response === false || strlen($response) < 12) {
            return false;
        }
        
        // Parse DNS response (this will handle NXDOMAIN correctly)
        return self::_parse_dns_response($response, $transaction_id);
    }
    
    /**
     * Build DNS query packet
     * 
     * @param string $hostname The hostname to query
     * @param int $transaction_id Transaction ID
     * @param bool $recursion_desired Whether to request recursion (RD bit)
     * @return string|false DNS query packet or false on failure
     */
    private static function _build_dns_query($hostname, $transaction_id, $recursion_desired = true) {
        // DNS header: ID (2 bytes) + Flags (2 bytes) + Questions (2 bytes) + 
        //             Answers (2 bytes) + Authority (2 bytes) + Additional (2 bytes)
        // Flags: QR=0 (query), Opcode=0 (standard), AA=0, TC=0, RD=1/0, RA=0, Z=0, RCODE=0
        $flags = 0x0000; // Standard query
        if ($recursion_desired) {
            $flags |= 0x0100; // Set RD (Recursion Desired) bit
        }
        
        $header = pack('n*', 
            $transaction_id,  // Transaction ID
            $flags,           // Flags: Standard query, RD set based on parameter
            1,                // Questions: 1
            0,                // Answers: 0
            0,                // Authority: 0
            0                 // Additional: 0
        );
        
        // Build question section
        $question = '';
        $parts = explode('.', $hostname);
        foreach ($parts as $part) {
            if (strlen($part) > 63) {
                return false; // Invalid hostname
            }
            $question .= chr(strlen($part)) . $part;
        }
        $question .= "\0"; // Null terminator
        
        // Question type: A record (1) and class: IN (1)
        $question .= pack('n*', 1, 1);
        
        return $header . $question;
    }
    
    /**
     * Parse DNS response packet
     * 
     * @param string $response DNS response packet
     * @param int $expected_transaction_id Expected transaction ID
     * @return array|false Array of A records or false on failure
     */
    private static function _parse_dns_response($response, $expected_transaction_id) {
        if (strlen($response) < 12) {
            return false;
        }
        
        // Parse header
        $header = unpack('n*', substr($response, 0, 12));
        $transaction_id = $header[1];
        $flags = $header[2];
        $questions = $header[3];
        $answers = $header[4];
        
        // Check transaction ID matches
        if ($transaction_id !== $expected_transaction_id) {
            return false;
        }
        
        // Check response code (bits 0-3 of flags)
        $rcode = $flags & 0x000F;
        if ($rcode !== 0) {
            // NXDOMAIN (3) means not found, which is valid for RBL lookups
            if ($rcode === 3) {
                return []; // Empty array = not listed
            }
            return false; // Other errors
        }
        
        // No answers means not found
        if ($answers === 0) {
            return [];
        }
        
        // Skip question section
        $offset = 12;
        for ($i = 0; $i < $questions; $i++) {
            // Skip QNAME (variable length, null-terminated)
            while ($offset < strlen($response) && ord($response[$offset]) !== 0) {
                $len = ord($response[$offset]);
                if ($len > 63 || $offset + $len >= strlen($response)) {
                    return false;
                }
                $offset += $len + 1;
            }
            $offset++; // Skip null terminator
            $offset += 4; // Skip QTYPE and QCLASS (2 bytes each)
        }
        
        // Parse answer section
        $results = [];
        for ($i = 0; $i < $answers && $offset < strlen($response); $i++) {
            // Parse name (may be compressed)
            $name_result = self::_parse_dns_name($response, $offset);
            if ($name_result === false) {
                break;
            }
            $offset = $name_result['offset'];
            
            // Parse type, class, TTL, and data length
            if ($offset + 10 > strlen($response)) {
                break;
            }
            
            $answer = unpack('ntype/nclass/Nttl/ndlength', substr($response, $offset, 10));
            $offset += 10;
            
            // Only process A records
            if ($answer['type'] === 1 && $answer['class'] === 1) {
                // Verify we have enough data for the IP address
                if ($offset + 4 > strlen($response)) {
                    break;
                }
                
                // Verify data length matches (should be 4 for IPv4)
                if ($answer['dlength'] !== 4) {
                    // Skip this record if data length is wrong
                    $offset += $answer['dlength'];
                    continue;
                }
                
                // Read IP address (4 bytes)
                $ip_bytes = substr($response, $offset, 4);
                if (strlen($ip_bytes) !== 4) {
                    break;
                }
                
                $ip = unpack('C*', $ip_bytes);
                $ip_address = sprintf('%d.%d.%d.%d', 
                    $ip[1], $ip[2], $ip[3], $ip[4]);
                
                $results[] = [
                    'host' => $name_result['name'],
                    'class' => 'IN',
                    'ttl' => $answer['ttl'],
                    'type' => 'A',
                    'ip' => $ip_address
                ];
            }
            
            // Move to next record
            $offset += $answer['dlength'];
        }
        
        return $results;
    }
    
    /**
     * Parse DNS name (handles compression)
     * 
     * @param string $response DNS response packet
     * @param int $offset Starting offset
     * @return array|false Array with 'name' and 'offset' or false on failure
     */
    private static function _parse_dns_name($response, $offset) {
        $name = '';
        $original_offset = $offset;
        $jumps = 0;
        $max_jumps = 10; // Prevent infinite loops
        $visited_offsets = []; // Track visited offsets to prevent loops
        
        while ($offset < strlen($response) && $jumps < $max_jumps) {
            $len = ord($response[$offset]);
            
            // Check for compression pointer (bits 11-14 are 1)
            if (($len & 0xC0) === 0xC0) {
                // Compressed name - get pointer
                if ($offset + 1 >= strlen($response)) {
                    return false;
                }
                $pointer = unpack('n', substr($response, $offset, 2))[1] & 0x3FFF;
                
                // If this is the first jump, remember we need to return offset after the pointer
                if ($jumps === 0) {
                    $return_offset = $original_offset + 2; // Compression pointer is 2 bytes
                }
                
                // Check for infinite loop
                if (isset($visited_offsets[$pointer])) {
                    return false;
                }
                $visited_offsets[$pointer] = true;
                
                $offset = $pointer;
                $jumps++;
                continue;
            }
            
            // End of name
            if ($len === 0) {
                // If we jumped, return offset after compression pointer, otherwise after null terminator
                if ($jumps > 0 && isset($return_offset)) {
                    $offset = $return_offset;
                } else {
                    $offset++;
                }
                break;
            }
            
            // Read label
            if ($offset + $len + 1 > strlen($response)) {
                return false;
            }
            
            if ($name !== '') {
                $name .= '.';
            }
            $name .= substr($response, $offset + 1, $len);
            $offset += $len + 1;
        }
        
        if ($jumps >= $max_jumps) {
            return false;
        }
        
        // If we used compression, return offset after the pointer
        if ($jumps > 0 && isset($return_offset)) {
            $offset = $return_offset;
        }
        
        return [
            'name' => $name,
            'offset' => $offset
        ];
    }
}

