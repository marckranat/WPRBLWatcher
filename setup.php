<?php
/**
 * Setup script to initialize RBLs in the database
 * Run this once after creating the database
 */
require_once 'config.php';
require_once 'db.php';

$db = Database::getInstance()->getConnection();
global $RBL_LIST;

echo "RBL Watcher Setup\n";
echo "=================\n\n";

// Check if RBLs already exist
$stmt = $db->query("SELECT COUNT(*) as count FROM rbls");
$existing = $stmt->fetch()['count'];

if ($existing > 0) {
    echo "RBLs already exist in database ($existing found).\n";
    echo "Do you want to update them? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim($line) !== 'y') {
        echo "Setup cancelled.\n";
        exit;
    }
    fclose($handle);
}

echo "Initializing RBLs...\n";

$stmt = $db->prepare("
    INSERT INTO rbls (name, dns_suffix, rate_limit_delay_ms, requires_paid, enabled) 
    VALUES (?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
    rate_limit_delay_ms = VALUES(rate_limit_delay_ms),
    requires_paid = VALUES(requires_paid),
    enabled = VALUES(enabled)
");

$count = 0;
foreach ($RBL_LIST as $rbl) {
    $stmt->execute([
        $rbl['name'],
        $rbl['dns_suffix'],
        $rbl['rate_limit_ms'] ?? DEFAULT_RATE_LIMIT_MS,
        $rbl['requires_paid'] ? 1 : 0
    ]);
    $count++;
    echo "  - Added: {$rbl['name']} ({$rbl['dns_suffix']})\n";
}

echo "\nSetup complete! $count RBLs initialized.\n";
echo "\nNext steps:\n";
echo "1. Configure database settings in config.php\n";
echo "2. Configure email settings in config.php\n";
echo "3. Set up cron jobs (see README.md)\n";
echo "4. Access the application via your web server\n";

