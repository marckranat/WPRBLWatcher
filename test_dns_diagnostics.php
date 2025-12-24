<?php
/**
 * DNS Diagnostics Script
 * Run this to diagnose Spamhaus 127.255.255.254 issues
 * 
 * Usage: php test_dns_diagnostics.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/dns_lookup.php';

echo "=== DNS Diagnostics for Spamhaus RBL Queries ===\n\n";

// 1. Check DNS server configuration
echo "1. DNS Server Configuration:\n";
$dns_server = defined('DNS_SERVER') && !empty(DNS_SERVER) ? DNS_SERVER : 'system default';
echo "   Configured DNS Server: $dns_server\n\n";

// 2. Get server's public IP
echo "2. Server Public IP:\n";
$server_ip = null;
$context = stream_context_create([
    'http' => [
        'timeout' => 3,
        'method' => 'GET'
    ]
]);
$ip_services = ['https://api.ipify.org', 'https://ifconfig.me/ip', 'https://icanhazip.com'];
foreach ($ip_services as $service) {
    echo "   Trying $service... ";
    $ip = @file_get_contents($service, false, $context);
    if ($ip && filter_var(trim($ip), FILTER_VALIDATE_IP)) {
        $server_ip = trim($ip);
        echo "✓ Found: $server_ip\n";
        break;
    } else {
        echo "✗ Failed\n";
    }
}
if (!$server_ip) {
    echo "   WARNING: Could not determine server public IP\n";
}
echo "\n";

// 3. Check reverse DNS
if ($server_ip) {
    echo "3. Reverse DNS (PTR) Check:\n";
    echo "   Server IP: $server_ip\n";
    $rdns = @gethostbyaddr($server_ip);
    if ($rdns && $rdns !== $server_ip) {
        echo "   Reverse DNS: $rdns\n";
        
        // Check if rDNS looks generic
        $generic_patterns = [
            'ec2-' => 'AWS EC2 generic hostname',
            'compute.amazonaws.com' => 'AWS generic hostname',
            'amazonaws.com' => 'AWS generic',
            '.cloud' => 'Generic cloud hostname',
            'hosting' => 'Generic hosting',
            'server' => 'Generic server',
            'ip-' => 'IP-based hostname'
        ];
        
        $is_generic = false;
        foreach ($generic_patterns as $pattern => $description) {
            if (stripos($rdns, $pattern) !== false) {
                echo "   ⚠ WARNING: $description detected in rDNS\n";
                $is_generic = true;
            }
        }
        
        if (!$is_generic) {
            echo "   ✓ Reverse DNS looks good (not generic)\n";
        }
        
        // Verify forward DNS matches
        $forward_ip = @gethostbyname($rdns);
        if ($forward_ip === $rdns) {
            echo "   ⚠ WARNING: Forward DNS (A record) not found for $rdns\n";
        } elseif ($forward_ip !== $server_ip) {
            echo "   ⚠ WARNING: Forward DNS mismatch - $rdns resolves to $forward_ip (expected $server_ip)\n";
        } else {
            echo "   ✓ Forward DNS (A record) matches server IP\n";
        }
    } else {
        echo "   ✗ ERROR: No reverse DNS configured (PTR record missing)\n";
        echo "   This is likely the cause of 127.255.255.254 errors!\n";
        echo "   Contact your hosting provider to set up reverse DNS.\n";
    }
    echo "\n";
}

// 4. Test DNS lookup method
echo "4. DNS Lookup Test:\n";
$test_hostname = "13.228.93.185.zen.spamhaus.org";
echo "   Testing lookup: $test_hostname\n";

if (!empty($dns_server) && $dns_server !== 'system default') {
    echo "   Using custom DNS server: $dns_server\n";
    $result = DNS_Lookup::lookup_a($test_hostname, $dns_server, 5);
} else {
    echo "   Using system default DNS resolver\n";
    $result = @dns_get_record($test_hostname, DNS_A);
}

if ($result === false) {
    echo "   ✗ DNS lookup failed\n";
} elseif (empty($result)) {
    echo "   ✓ DNS lookup successful - empty result (IP not listed, which is correct)\n";
} else {
    echo "   DNS lookup returned:\n";
    foreach ($result as $idx => $record) {
        echo "     [$idx] ";
        if (isset($record['ip'])) {
            echo "IP: " . $record['ip'];
            if ($record['ip'] === '127.255.255.254') {
                echo " ⚠ ERROR CODE: This indicates a query method problem\n";
            }
        }
        if (isset($record['host'])) {
            echo " Host: " . $record['host'];
        }
        echo "\n";
    }
}
echo "\n";

// 5. Recommendations
echo "5. Recommendations:\n";
if (!$server_ip || !$rdns || $rdns === $server_ip) {
    echo "   ⚠ Set up reverse DNS (PTR record) for your server IP\n";
    echo "      Contact your hosting provider to configure this\n";
}
if (empty($dns_server) || $dns_server === 'system default') {
    echo "   ⚠ Consider using a local DNS resolver (127.0.0.1)\n";
    echo "      Set DNS_SERVER to '127.0.0.1' in config.php\n";
    echo "      Ensure the local resolver does NOT forward to public DNS\n";
} else {
    echo "   ✓ Using custom DNS server: $dns_server\n";
    echo "   ⚠ Verify this resolver does NOT forward to 8.8.8.8, 1.1.1.1, etc.\n";
}
echo "\n";

echo "=== End of Diagnostics ===\n";

