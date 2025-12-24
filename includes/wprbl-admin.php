<?php
/**
 * Admin Interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPRBL_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('wp_ajax_wprbl_add_ip', [$this, 'ajax_add_ip']);
        add_action('wp_ajax_wprbl_delete_ip', [$this, 'ajax_delete_ip']);
        add_action('wp_ajax_wprbl_run_check', [$this, 'ajax_run_check']);
        add_action('wp_ajax_wprbl_update_preferences', [$this, 'ajax_update_preferences']);
        add_action('wp_ajax_wprbl_get_ip_rbls', [$this, 'ajax_get_ip_rbls']);
    }
    
    public function add_admin_menu() {
        add_management_page(
            'RBL Watcher',
            'RBL Watcher',
            'read',
            'wprbl-monitor',
            [$this, 'render_dashboard']
        );
    }
    
    public function enqueue_styles($hook) {
        if (strpos($hook, 'wprbl') === false) {
            return;
        }
        
        wp_enqueue_style('wprbl-style', WPRBL_PLUGIN_URL . 'assets/style.css', [], WPRBL_VERSION);
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'ajaxurl', admin_url('admin-ajax.php'));
    }
    
    public function render_dashboard() {
        global $wpdb;
        $user_id = get_current_user_id();
        $table_ips = $wpdb->prefix . 'wprbl_ip_addresses';
        $table_prefs = $wpdb->prefix . 'wprbl_user_preferences';
        
        // Handle form submissions
        if (isset($_POST['wprbl_action'])) {
            $this->handle_form_submission();
        }
        
        // Get user's IPs
        $ips = $wpdb->get_results($wpdb->prepare("
            SELECT id, ip_address, created_at 
            FROM $table_ips 
            WHERE user_id = %d 
            ORDER BY created_at DESC
        ", $user_id), ARRAY_A);
        
        $ip_count = count($ips);
        
        // Get preferences
        $prefs = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $table_prefs WHERE user_id = %d
        ", $user_id), ARRAY_A);
        
        if (!$prefs) {
            // Get default domain for from_email
            $site_url = site_url();
            $domain = parse_url($site_url, PHP_URL_HOST);
            $default_email = 'rbl@' . ($domain ? $domain : 'example.com');
            
            $prefs = [
                'report_frequency' => 'daily', 
                'email_notifications' => 1, 
                'report_day' => null,
                'from_email' => $default_email
            ];
        } elseif (empty($prefs['from_email'])) {
            // Set default if not set
            $site_url = site_url();
            $domain = parse_url($site_url, PHP_URL_HOST);
            $prefs['from_email'] = 'rbl@' . ($domain ? $domain : 'example.com');
        }
        
        // Get blacklisted IPs
        $reports = new WPRBL_Reports();
        $blacklisted_ips = $reports->get_blacklisted_ips($user_id);
        
        // Get IPs that have been checked (to distinguish from unchecked IPs)
        $table_results = $wpdb->prefix . 'wprbl_check_results';
        $checked_ip_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT ip_address_id 
            FROM $table_results 
            WHERE ip_address_id IN (
                SELECT id FROM $table_ips WHERE user_id = %d
            )
        ", $user_id));
        $checked_ip_ids = array_flip($checked_ip_ids); // Convert to hash for faster lookup
        
        // Show debug notice if logging is enabled
        if (defined('WPRBL_DEBUG') && WPRBL_DEBUG) {
            $log_file = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : ABSPATH . 'wp-content';
            $log_file .= '/wprbl-debug.log';
            add_settings_error('wprbl', 'debug_info', 
                'Debug logging is enabled. Log file: ' . esc_html($log_file), 
                'info');
        }
        
        include WPRBL_PLUGIN_DIR . 'templates/dashboard.php';
    }
    
    private function handle_form_submission() {
        check_admin_referer('wprbl_action');
        
        $action = sanitize_text_field($_POST['wprbl_action']);
        $user_id = get_current_user_id();
        
        switch ($action) {
            case 'add_ip':
                $this->add_ip($user_id);
                break;
            case 'delete_ip':
                $this->delete_ip($user_id);
                break;
            case 'delete_ips_bulk':
                $this->delete_ips_bulk($user_id);
                break;
            case 'run_check':
                $this->run_check($user_id);
                break;
            case 'update_preferences':
                $this->update_preferences($user_id);
                break;
            case 'sync_rbls':
                $this->sync_rbls();
                break;
        }
    }
    
    private function add_ip($user_id) {
        global $wpdb;
        $table_ips = $wpdb->prefix . 'wprbl_ip_addresses';
        
        $ip_input = trim($_POST['ip_address'] ?? '');
        
        if (empty($ip_input)) {
            add_settings_error('wprbl', 'empty_ip', 'Please enter at least one IP address.');
            return;
        }
        
        // Split by newlines and process each IP
        $ip_lines = preg_split('/\r\n|\r|\n/', $ip_input);
        $valid_ips = [];
        $invalid_ips = [];
        
        foreach ($ip_lines as $line) {
            $ip = trim($line);
            if (empty($ip)) {
                continue; // Skip empty lines
            }
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $valid_ips[] = $ip;
            } else {
                $invalid_ips[] = $ip;
            }
        }
        
        // Show warnings for invalid IPs
        if (!empty($invalid_ips)) {
            $invalid_list = implode(', ', array_map('esc_html', $invalid_ips));
            add_settings_error('wprbl', 'invalid_ips', 
                'Invalid IP addresses (skipped): ' . $invalid_list, 
                'error');
        }
        
        if (empty($valid_ips)) {
            return; // No valid IPs to add
        }
        
        // Check IP limit
        $current_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table_ips WHERE user_id = %d
        ", $user_id));
        
        $total_after_add = $current_count + count($valid_ips);
        if ($total_after_add > WPRBL_MAX_IPS_PER_USER) {
            $can_add = WPRBL_MAX_IPS_PER_USER - $current_count;
            if ($can_add > 0) {
                $valid_ips = array_slice($valid_ips, 0, $can_add);
                add_settings_error('wprbl', 'ip_limit_partial', 
                    'IP limit reached. Only ' . $can_add . ' IP(s) were added.', 
                    'error');
            } else {
                add_settings_error('wprbl', 'ip_limit', 
                    'Maximum of ' . WPRBL_MAX_IPS_PER_USER . ' IPs per account reached.', 
                    'error');
                return;
            }
        }
        
        // Add valid IPs
        $added_count = 0;
        $duplicate_count = 0;
        
        foreach ($valid_ips as $ip) {
            $result = $wpdb->insert(
                $table_ips,
                [
                    'user_id' => $user_id,
                    'ip_address' => $ip
                ],
                ['%d', '%s']
            );
            
            if ($result === false) {
                // Check if it's a duplicate
                $exists = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM $table_ips 
                    WHERE user_id = %d AND ip_address = %s
                ", $user_id, $ip));
                
                if ($exists > 0) {
                    $duplicate_count++;
                }
            } else {
                $added_count++;
            }
        }
        
        // Show success/error messages
        if ($added_count > 0) {
            $message = $added_count . ' IP address(es) added successfully.';
            if ($duplicate_count > 0) {
                $message .= ' ' . $duplicate_count . ' duplicate(s) skipped.';
            }
            add_settings_error('wprbl', 'success', $message, 'updated');
        } else {
            if ($duplicate_count > 0) {
                add_settings_error('wprbl', 'duplicates', 
                    'All IP addresses already exist in your list.', 
                    'error');
            } else {
                add_settings_error('wprbl', 'db_error', 'Error adding IP addresses.');
            }
        }
    }
    
    private function delete_ip($user_id) {
        global $wpdb;
        $table_ips = $wpdb->prefix . 'wprbl_ip_addresses';
        
        $ip_id = intval($_POST['ip_id'] ?? 0);
        
        $wpdb->delete(
            $table_ips,
            ['id' => $ip_id, 'user_id' => $user_id],
            ['%d', '%d']
        );
        
        add_settings_error('wprbl', 'success', 'IP address removed.', 'updated');
    }
    
    private function delete_ips_bulk($user_id) {
        global $wpdb;
        $table_ips = $wpdb->prefix . 'wprbl_ip_addresses';
        
        $ip_ids = isset($_POST['ip_ids']) ? array_map('intval', $_POST['ip_ids']) : [];
        
        if (empty($ip_ids)) {
            add_settings_error('wprbl', 'no_selection', 'No IP addresses selected.', 'error');
            return;
        }
        
        // Sanitize and validate IP IDs
        $ip_ids = array_filter($ip_ids, function($id) {
            return $id > 0;
        });
        
        if (empty($ip_ids)) {
            add_settings_error('wprbl', 'invalid_ids', 'Invalid IP address IDs.', 'error');
            return;
        }
        
        // Use a safer approach: delete one by one to ensure proper security
        $deleted = 0;
        foreach ($ip_ids as $ip_id) {
            $result = $wpdb->delete(
                $table_ips,
                ['id' => $ip_id, 'user_id' => $user_id],
                ['%d', '%d']
            );
            if ($result !== false) {
                $deleted += $result;
            }
        }
        
        if ($deleted === 0) {
            add_settings_error('wprbl', 'no_deletes', 'No IP addresses were deleted. They may not exist or belong to another user.', 'error');
        } else {
            add_settings_error('wprbl', 'success', 
                $deleted . ' IP address(es) removed.', 
                'updated');
        }
    }
    
    private function run_check($user_id) {
        $checker = new WPRBL_Checker();
        $result = $checker->check_user_ips($user_id);
        add_settings_error('wprbl', 'success', 
            "Check completed: {$result['total_ips']} IPs checked, {$result['blacklisted_count']} blacklisted.", 
            'updated');
    }
    
    private function update_preferences($user_id) {
        global $wpdb;
        $table_prefs = $wpdb->prefix . 'wprbl_user_preferences';
        
        $frequency = sanitize_text_field($_POST['report_frequency'] ?? 'daily');
        $report_day = isset($_POST['report_day']) ? intval($_POST['report_day']) : null;
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $from_email = sanitize_email($_POST['from_email'] ?? '');
        
        // Validate email
        if (!empty($from_email) && !is_email($from_email)) {
            add_settings_error('wprbl', 'invalid_email', 'Invalid email address format.', 'error');
            return;
        }
        
        // Set default if empty
        if (empty($from_email)) {
            $site_url = site_url();
            $domain = parse_url($site_url, PHP_URL_HOST);
            $from_email = 'rbl@' . ($domain ? $domain : 'example.com');
        }
        
        $wpdb->replace(
            $table_prefs,
            [
                'user_id' => $user_id,
                'report_frequency' => $frequency,
                'report_day' => $report_day,
                'email_notifications' => $email_notifications,
                'from_email' => $from_email
            ],
            ['%d', '%s', '%d', '%d', '%s']
        );
        
        add_settings_error('wprbl', 'success', 'Preferences updated.', 'updated');
    }
    
    public function ajax_add_ip() {
        check_ajax_referer('wprbl_nonce');
        $this->add_ip(get_current_user_id());
        wp_send_json_success();
    }
    
    public function ajax_delete_ip() {
        check_ajax_referer('wprbl_nonce');
        $this->delete_ip(get_current_user_id());
        wp_send_json_success();
    }
    
    public function ajax_run_check() {
        check_ajax_referer('wprbl_nonce');
        $this->run_check(get_current_user_id());
        wp_send_json_success();
    }
    
    public function ajax_update_preferences() {
        check_ajax_referer('wprbl_nonce');
        $this->update_preferences(get_current_user_id());
        wp_send_json_success();
    }
    
    public function ajax_get_ip_rbls() {
        check_ajax_referer('wprbl_nonce');
        global $wpdb;
        $user_id = get_current_user_id();
        $ip_id = intval($_POST['ip_id'] ?? 0);
        
        if ($ip_id <= 0) {
            wp_send_json_error(['message' => 'Invalid IP ID']);
            return;
        }
        
        // Verify IP belongs to user
        $table_ips = $wpdb->prefix . 'wprbl_ip_addresses';
        $ip = $wpdb->get_row($wpdb->prepare("
            SELECT id, ip_address 
            FROM $table_ips 
            WHERE id = %d AND user_id = %d
        ", $ip_id, $user_id), ARRAY_A);
        
        if (!$ip) {
            wp_send_json_error(['message' => 'IP not found']);
            return;
        }
        
        // Get RBLs that listed this IP
        $table_results = $wpdb->prefix . 'wprbl_check_results';
        $table_rbls = $wpdb->prefix . 'wprbl_rbls';
        
        $rbls = $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.name,
                r.dns_suffix,
                rcr.response_text,
                rcr.checked_at
            FROM $table_results rcr
            INNER JOIN $table_rbls r ON rcr.rbl_id = r.id
            WHERE rcr.ip_address_id = %d 
                AND rcr.is_listed = 1
            ORDER BY r.name ASC
        ", $ip_id), ARRAY_A);
        
        wp_send_json_success([
            'ip_address' => $ip['ip_address'],
            'rbls' => $rbls
        ]);
    }
    
    private function sync_rbls() {
        WPRBL_Config::initialize_rbls();
        add_settings_error('wprbl', 'rbls_synced', 
            'RBL list has been synchronized with the configuration.', 
            'success');
    }
}

