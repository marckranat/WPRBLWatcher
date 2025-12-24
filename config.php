<?php
/**
 * RBL Watcher Configuration
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'rbl_monitor');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('MAX_IPS_PER_USER', 1000);
define('DEFAULT_RATE_LIMIT_MS', 100); // Default delay between RBL checks in milliseconds
define('SESSION_LIFETIME', 3600); // 1 hour
define('DNS_LOOKUP_TIMEOUT', 5); // DNS lookup timeout in seconds (prevents hanging lookups)
// Adjust this value if you experience timeouts with slow DNS servers (recommended: 2-5 seconds)

// DNS Resolver Configuration
// Set to '127.0.0.1' or 'localhost' to use a local DNS resolver (recommended for Spamhaus)
// Set to null or empty string to use system default DNS resolver
// IMPORTANT: To avoid Spamhaus 127.255.255.254 errors, use a local resolver (Unbound/dnsmasq)
// See SPAMHAUS_DNS_SETUP.md for setup instructions
define('DNS_SERVER', '1.1.1.1'); // null = system default, '127.0.0.1' = local resolver, '1.1.1.1' = Cloudflare DNS

// Email configuration (for reports)
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 25);
define('SMTP_USER', '');
define('SMTP_PASS', '');
// FROM_EMAIL is configured per-user in preferences (default: rbl@domain.com)
define('FROM_NAME', 'RBL Watcher');

// RBL list - Curated list of reliable, fast-responding RBLs
// Format: ['name' => 'Display Name', 'dns_suffix' => 'rbl.example.com', 'requires_paid' => false, 'rate_limit_ms' => 0]
// Note: Only includes RBLs that respond reliably and quickly. Excludes:
// - Spamhaus (requires complex DNS setup)
// - SpamRats, NJABL, INPS (unreliable/slow)
// - UCEPROTECT and other false-positive prone RBLs
// - Paid RBLs (automatically excluded)
$RBL_LIST = [
    ['name' => 'Barracuda', 'dns_suffix' => 'b.barracudacentral.org', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SpamCop', 'dns_suffix' => 'bl.spamcop.net', 'requires_paid' => false, 'rate_limit_ms' => 200],
    ['name' => 'SORBS DNSBL', 'dns_suffix' => 'dnsbl.sorbs.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'SORBS Spam', 'dns_suffix' => 'spam.dnsbl.sorbs.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
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
    ['name' => 'SORBS Zombie', 'dns_suffix' => 'zombie.dnsbl.sorbs.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'DroneBL', 'dns_suffix' => 'dnsbl.dronebl.org', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'Tornevall', 'dns_suffix' => 'dnsbl.tornevall.org', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'S5H', 'dns_suffix' => 'all.s5h.net', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'HostKarma', 'dns_suffix' => 'hostkarma.junkemailfilter.com', 'requires_paid' => false, 'rate_limit_ms' => 100],
    ['name' => 'Anonmails', 'dns_suffix' => 'spam.dnsbl.anonmails.de', 'requires_paid' => false, 'rate_limit_ms' => 100],
];

// Timezone
date_default_timezone_set('UTC');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

