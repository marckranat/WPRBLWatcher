<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wprbl-dashboard">
    <h1>RBL Monitor</h1>
    
    <?php settings_errors('wprbl'); ?>
    
    <div class="wprbl-stats">
        <div class="wprbl-stat-card">
            <h3>IP Addresses</h3>
            <p class="wprbl-stat-number"><?php echo esc_html($ip_count); ?> / <?php echo WPRBL_MAX_IPS_PER_USER; ?></p>
        </div>
        <div class="wprbl-stat-card">
            <h3>Blacklisted IPs</h3>
            <p class="wprbl-stat-number"><?php echo esc_html(count($blacklisted_ips)); ?></p>
        </div>
    </div>
    
    <div class="wprbl-section">
        <h2>Add IP Addresses</h2>
        <p>Enter one IP address per line. Invalid IPs will be skipped with a warning.</p>
        <form method="POST">
            <?php wp_nonce_field('wprbl_action'); ?>
            <input type="hidden" name="wprbl_action" value="add_ip">
            <textarea name="ip_address" rows="5" cols="50" placeholder="192.168.1.1&#10;10.0.0.1&#10;172.16.0.1" style="width: 100%; max-width: 500px;" required></textarea>
            <p class="submit">
                <button type="submit" class="button button-primary">Add IPs</button>
            </p>
        </form>
    </div>
    
    <div class="wprbl-section">
        <h2>Your IP Addresses</h2>
        <?php if (empty($ips)): ?>
            <p>No IP addresses added yet.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ips as $ip): 
                        $is_blacklisted = false;
                        $listing_count = 0;
                        foreach ($blacklisted_ips as $blip) {
                            if ($blip['id'] == $ip['id']) {
                                $is_blacklisted = true;
                                $listing_count = $blip['listing_count'];
                                break;
                            }
                        }
                    ?>
                        <tr class="<?php echo $is_blacklisted ? 'wprbl-blacklisted' : ''; ?>">
                            <td><?php echo esc_html($ip['ip_address']); ?></td>
                            <td>
                                <?php if ($is_blacklisted): ?>
                                    <span class="wprbl-badge wprbl-badge-danger">Blacklisted (<?php echo $listing_count; ?>)</span>
                                <?php else: ?>
                                    <span class="wprbl-badge wprbl-badge-success">Clean</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date('Y-m-d', strtotime($ip['created_at']))); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                    <?php wp_nonce_field('wprbl_action'); ?>
                                    <input type="hidden" name="wprbl_action" value="delete_ip">
                                    <input type="hidden" name="ip_id" value="<?php echo esc_attr($ip['id']); ?>">
                                    <button type="submit" class="button button-small button-link-delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="wprbl-section">
        <h2>Blacklisted IPs Report</h2>
        <?php if (empty($blacklisted_ips)): ?>
            <p>No blacklisted IPs found.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Listings</th>
                        <th>RBLs</th>
                        <th>Last Checked</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blacklisted_ips as $ip): ?>
                        <tr class="wprbl-blacklisted">
                            <td><?php echo esc_html($ip['ip_address']); ?></td>
                            <td><strong><?php echo esc_html($ip['listing_count']); ?></strong></td>
                            <td><?php echo esc_html($ip['rbl_names']); ?></td>
                            <td><?php echo esc_html($ip['last_checked']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="wprbl-section">
        <h2>Actions</h2>
        <form method="POST" style="display: inline-block; margin-right: 10px;">
            <?php wp_nonce_field('wprbl_action'); ?>
            <input type="hidden" name="wprbl_action" value="run_check">
            <button type="submit" class="button button-primary">Run RBL Check Now</button>
        </form>
        <a href="<?php echo admin_url('admin.php?page=wprbl-report'); ?>" class="button">View Full Report</a>
        <a href="<?php echo admin_url('admin.php?page=wprbl-report&format=csv'); ?>" class="button">Download CSV</a>
    </div>
    
    <div class="wprbl-section">
        <h2>Preferences</h2>
        <form method="POST">
            <?php wp_nonce_field('wprbl_action'); ?>
            <input type="hidden" name="wprbl_action" value="update_preferences">
            <table class="form-table">
                <tr>
                    <th><label for="report_frequency">Report Frequency:</label></th>
                    <td>
                        <select name="report_frequency" id="report_frequency" onchange="toggleReportDay()">
                            <option value="daily" <?php selected($prefs['report_frequency'], 'daily'); ?>>Daily</option>
                            <option value="weekly" <?php selected($prefs['report_frequency'], 'weekly'); ?>>Weekly</option>
                        </select>
                    </td>
                </tr>
                <tr id="report-day-row" style="display: <?php echo $prefs['report_frequency'] === 'weekly' ? 'table-row' : 'none'; ?>;">
                    <th><label for="report_day">Report Day (Weekly):</label></th>
                    <td>
                        <select name="report_day" id="report_day">
                            <option value="0" <?php selected($prefs['report_day'], 0); ?>>Sunday</option>
                            <option value="1" <?php selected($prefs['report_day'], 1); ?>>Monday</option>
                            <option value="2" <?php selected($prefs['report_day'], 2); ?>>Tuesday</option>
                            <option value="3" <?php selected($prefs['report_day'], 3); ?>>Wednesday</option>
                            <option value="4" <?php selected($prefs['report_day'], 4); ?>>Thursday</option>
                            <option value="5" <?php selected($prefs['report_day'], 5); ?>>Friday</option>
                            <option value="6" <?php selected($prefs['report_day'], 6); ?>>Saturday</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="email_notifications">Email Notifications:</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="email_notifications" value="1" <?php checked($prefs['email_notifications'], 1); ?>>
                            Enable email notifications
                        </label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Save Preferences</button>
            </p>
        </form>
    </div>
</div>

<script>
function toggleReportDay() {
    const frequency = document.getElementById('report_frequency').value;
    document.getElementById('report-day-row').style.display = frequency === 'weekly' ? 'table-row' : 'none';
}
</script>

