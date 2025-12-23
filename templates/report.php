<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wprbl-report">
    <h1>RBL Monitor - Report</h1>
    
    <div class="wprbl-section">
        <h2>Report Filters</h2>
        <form method="GET" class="wprbl-form-inline">
            <input type="hidden" name="page" value="wprbl-report">
            <label>Start Date:</label>
            <input type="date" name="start" value="<?php echo esc_attr($start_date); ?>">
            <label>End Date:</label>
            <input type="date" name="end" value="<?php echo esc_attr($end_date); ?>">
            <button type="submit" class="button button-primary">Filter</button>
        </form>
    </div>
    
    <div class="wprbl-section">
        <h2>Blacklisted IPs Report</h2>
        <p>Report Period: <?php echo esc_html($start_date); ?> to <?php echo esc_html($end_date); ?></p>
        
        <?php if (empty($blacklisted_ips)): ?>
            <p>No blacklisted IPs found for the selected period.</p>
        <?php else: ?>
            <p><strong>Total blacklisted IPs: <?php echo count($blacklisted_ips); ?></strong></p>
            <a href="<?php echo admin_url('admin.php?page=wprbl-report&start=' . urlencode($start_date) . '&end=' . urlencode($end_date) . '&format=csv'); ?>" class="button">Download CSV</a>
            
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
</div>

