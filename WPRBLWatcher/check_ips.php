<?php
/**
 * Cron script to check all user IPs against RBLs
 * Run this via cron: php check_ips.php
 */
require_once 'rbl_checker.php';
require_once 'db.php';

$db = Database::getInstance()->getConnection();
$checker = new RBLChecker();

// Get all users
$stmt = $db->query("SELECT id, username FROM users");
$users = $stmt->fetchAll();

foreach ($users as $user) {
    echo "Checking IPs for user: {$user['username']} (ID: {$user['id']})\n";
    $result = $checker->checkUserIPs($user['id']);
    echo "  - Checked {$result['total_ips']} IPs, {$result['blacklisted_count']} blacklisted\n";
}

echo "All checks completed.\n";

