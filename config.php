<?php
/**
 * RBL Monitor Configuration
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rbl_monitor');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('MAX_IPS_PER_USER', 250);
define('DEFAULT_RATE_LIMIT_MS', 100); // Default delay between RBL checks in milliseconds
define('SESSION_LIFETIME', 3600); // 1 hour
define('DNS_LOOKUP_TIMEOUT', 3); // DNS lookup timeout in seconds (prevents hanging lookups)
// Adjust this value if you experience timeouts with slow DNS servers (recommended: 2-5 seconds)

// Email configuration (for reports)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('FROM_EMAIL', 'noreply@rblmonitor.com');
define('FROM_NAME', 'RBL Monitor');

// RBL list - Based on rblmon.com monitored RBLs (excluding false positive prone RBLs)
// Format: ['name' => 'Display Name', 'dns_suffix' => 'rbl.example.com', 'requires_paid' => false, 'rate_limit_ms' => 0]
// Note: UCEPROTECT and other false-positive prone RBLs have been excluded
$RBL_LIST = [
    ['name' => 'Barracuda', 'dns_suffix' => 'b.barracudacentral.org', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SpamCop', 'dns_suffix' => 'bl.spamcop.net', 'requires_paid' => false, 'rate_limit_ms' => 200],
    ['name' => 'SORBS DNSBL', 'dns_suffix' => 'dnsbl.sorbs.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SORBS Spam', 'dns_suffix' => 'spam.dnsbl.sorbs.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SpamRats', 'dns_suffix' => 'spam.spamrats.com', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'PSBL', 'dns_suffix' => 'psbl.surriel.com', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SpamLab', 'dns_suffix' => 'rbl.spamlab.com', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SureSupport', 'dns_suffix' => 'rbl.suresupport.com', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'Kundenserver Relays', 'dns_suffix' => 'relays.bl.kundenserver.de', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'Nether Relays', 'dns_suffix' => 'relays.nether.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SORBS SMTP', 'dns_suffix' => 'smtp.dnsbl.sorbs.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SORBS SOCKS', 'dns_suffix' => 'socks.dnsbl.sorbs.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'MSRBL Spam', 'dns_suffix' => 'spam.rbl.msrbl.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SpamGuard', 'dns_suffix' => 'spamguard.leadmon.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'IMP Spam', 'dns_suffix' => 'spamrbl.imp.ch', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'Unsubscribe Score', 'dns_suffix' => 'ubl.unsubscore.com', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'MSRBL Virus', 'dns_suffix' => 'virus.rbl.msrbl.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SORBS Web', 'dns_suffix' => 'web.dnsbl.sorbs.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'IMP Worm', 'dns_suffix' => 'wormrbl.imp.ch', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'Spamhaus XBL', 'dns_suffix' => 'xbl.spamhaus.org', 'requires_paid' => false, 'rate_limit_ms' => 200],
    ['name' => 'Spamhaus ZEN', 'dns_suffix' => 'zen.spamhaus.org', 'requires_paid' => false, 'rate_limit_ms' => 200],
    ['name' => 'SORBS Zombie', 'dns_suffix' => 'zombie.dnsbl.sorbs.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'DroneBL', 'dns_suffix' => 'dnsbl.dronebl.org', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'INPS', 'dns_suffix' => 'dnsbl.inps.de', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'NJABL', 'dns_suffix' => 'dnsbl.njabl.org', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'Tornevall', 'dns_suffix' => 'dnsbl.tornevall.org', 'requires_paid' => false, 'rate_limit_ms' => 100],
];

// Timezone
date_default_timezone_set('UTC');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

