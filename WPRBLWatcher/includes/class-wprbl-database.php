<?php
/**
 * Database Management Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPRBL_Database {
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // IP addresses table
        $table_ips = $wpdb->prefix . 'wprbl_ip_addresses';
        $sql_ips = "CREATE TABLE IF NOT EXISTS $table_ips (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            ip_address varchar(45) NOT NULL,
            label varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_user_ip (user_id, ip_address),
            KEY idx_user_id (user_id),
            KEY idx_ip_address (ip_address)
        ) $charset_collate;";
        
        // RBLs table
        $table_rbls = $wpdb->prefix . 'wprbl_rbls';
        $sql_rbls = "CREATE TABLE IF NOT EXISTS $table_rbls (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            dns_suffix varchar(255) NOT NULL,
            enabled tinyint(1) DEFAULT 1,
            requires_paid tinyint(1) DEFAULT 0,
            rate_limit_delay_ms int DEFAULT 0,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY idx_enabled (enabled)
        ) $charset_collate;";
        
        // RBL check results table
        $table_results = $wpdb->prefix . 'wprbl_check_results';
        $sql_results = "CREATE TABLE IF NOT EXISTS $table_results (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ip_address_id bigint(20) UNSIGNED NOT NULL,
            rbl_id bigint(20) UNSIGNED NOT NULL,
            is_listed tinyint(1) NOT NULL DEFAULT 0,
            response_text text,
            checked_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_ip_rbl (ip_address_id, rbl_id),
            KEY idx_ip_address_id (ip_address_id),
            KEY idx_rbl_id (rbl_id),
            KEY idx_checked_at (checked_at),
            KEY idx_is_listed (is_listed)
        ) $charset_collate;";
        
        // User preferences table
        $table_prefs = $wpdb->prefix . 'wprbl_user_preferences';
        $sql_prefs = "CREATE TABLE IF NOT EXISTS $table_prefs (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            report_frequency varchar(20) DEFAULT 'daily',
            report_day int DEFAULT NULL,
            email_notifications tinyint(1) DEFAULT 1,
            from_email varchar(255) DEFAULT NULL,
            last_report_sent datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        dbDelta($sql_prefs);
        
        // Add from_email column if it doesn't exist (for existing installations)
        $column_exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'from_email'
        ", $table_prefs));
        
        if ($column_exists == 0) {
            $wpdb->query("ALTER TABLE $table_prefs ADD COLUMN from_email varchar(255) DEFAULT NULL AFTER email_notifications");
        }
        
        // Add debug_logging and log_filename columns if they don't exist
        $debug_logging_exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'debug_logging'
        ", $table_prefs));
        
        if ($debug_logging_exists == 0) {
            $wpdb->query("ALTER TABLE $table_prefs ADD COLUMN debug_logging tinyint(1) DEFAULT 0 AFTER from_email");
        }
        
        $log_filename_exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'log_filename'
        ", $table_prefs));
        
        if ($log_filename_exists == 0) {
            $wpdb->query("ALTER TABLE $table_prefs ADD COLUMN log_filename varchar(255) DEFAULT NULL AFTER debug_logging");
        }
        
        // Check history table
        $table_history = $wpdb->prefix . 'wprbl_check_history';
        $sql_history = "CREATE TABLE IF NOT EXISTS $table_history (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            check_started_at datetime DEFAULT CURRENT_TIMESTAMP,
            check_completed_at datetime DEFAULT NULL,
            total_ips int DEFAULT 0,
            total_checks int DEFAULT 0,
            blacklisted_count int DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_check_started_at (check_started_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_ips);
        dbDelta($sql_rbls);
        dbDelta($sql_results);
        dbDelta($sql_prefs);
        dbDelta($sql_history);
    }
}

