<?php
/**
 * RBL Configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPRBL_Config {
    
    /**
     * Get RBL list - Based on rblmon.com monitored RBLs (excluding false positive prone RBLs)
     */
    public static function get_rbl_list() {
        return [
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
    }
    
    /**
     * Initialize RBLs in database
     */
    public static function initialize_rbls() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wprbl_rbls';
        $rbl_list = self::get_rbl_list();
        
        foreach ($rbl_list as $rbl) {
            $wpdb->replace(
                $table_name,
                [
                    'name' => $rbl['name'],
                    'dns_suffix' => $rbl['dns_suffix'],
                    'rate_limit_delay_ms' => $rbl['rate_limit_ms'],
                    'requires_paid' => $rbl['requires_paid'] ? 1 : 0,
                    'enabled' => 1
                ],
                ['%s', '%s', '%d', '%d', '%d']
            );
        }
    }
}

