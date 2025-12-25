<?php
/**
 * Plugin Name: WP RBL Watcher
 * Plugin URI: https://github.com/marckranat/WPRBLWatcher
 * Description: Monitor IP addresses against Real-time Blackhole Lists (RBLs). Track up to 1000 IPs per user with daily/weekly reports.
 * Version: 1.0.3
 * Author: Marc Kranat
 * Author URI: https://github.com/marckranat
 * License: Free for any use
 * Text Domain: wprbl-watcher
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPRBL_VERSION', '1.0.3');
define('WPRBL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPRBL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPRBL_PLUGIN_FILE', __FILE__);
define('WPRBL_MAX_IPS_PER_USER', 1000);
define('WPRBL_DEFAULT_RATE_LIMIT_MS', 100);
define('WPRBL_DNS_LOOKUP_TIMEOUT', 5);
// DNS Resolver Configuration
// Set to '127.0.0.1' or 'localhost' to use a local DNS resolver (recommended for Spamhaus)
// Set to null or empty string to use system default DNS resolver
// IMPORTANT: To avoid Spamhaus 127.255.255.254 errors, use a local resolver (Unbound/dnsmasq)
// See SPAMHAUS_DNS_SETUP.md for setup instructions
define('WPRBL_DNS_SERVER', '1.1.1.1'); // null = system default, '127.0.0.1' = local resolver, '1.1.1.1' = Cloudflare DNS

// Include required files
require_once WPRBL_PLUGIN_DIR . 'includes/class-wprbl-database.php';
require_once WPRBL_PLUGIN_DIR . 'includes/class-wprbl-checker.php';
require_once WPRBL_PLUGIN_DIR . 'includes/class-wprbl-reports.php';
require_once WPRBL_PLUGIN_DIR . 'includes/wprbl-config.php';
require_once WPRBL_PLUGIN_DIR . 'includes/wprbl-admin.php';
require_once WPRBL_PLUGIN_DIR . 'includes/wprbl-cron.php';

// Activation hook
register_activation_hook(__FILE__, 'wprbl_activate');
function wprbl_activate() {
    WPRBL_Database::create_tables();
    WPRBL_Config::initialize_rbls();
    
    // Schedule cron events
    if (!wp_next_scheduled('wprbl_check_ips')) {
        wp_schedule_event(time(), 'daily', 'wprbl_check_ips');
    }
    if (!wp_next_scheduled('wprbl_send_reports')) {
        wp_schedule_event(time(), 'daily', 'wprbl_send_reports');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'wprbl_deactivate');
function wprbl_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('wprbl_check_ips');
    wp_clear_scheduled_hook('wprbl_send_reports');
}

// Initialize plugin
add_action('plugins_loaded', 'wprbl_init');
function wprbl_init() {
    // Check if RBLs need to be synced (version change or manual sync)
    $stored_version = get_option('wprbl_version', '0.0.0');
    if (version_compare($stored_version, WPRBL_VERSION, '<')) {
        WPRBL_Config::initialize_rbls();
        update_option('wprbl_version', WPRBL_VERSION);
    }
    
    // Load text domain for translations
    load_plugin_textdomain('wprbl-watcher', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Initialize admin
    if (is_admin()) {
        new WPRBL_Admin();
    }
}

