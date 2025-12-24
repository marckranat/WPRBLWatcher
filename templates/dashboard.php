<?php
if (!defined('ABSPATH')) {
    exit;
}
// Get AJAX nonce for RBL details
$ajax_nonce = wp_create_nonce('wprbl_nonce');
?>
<div class="wrap wprbl-dashboard">
    <h1>RBL Watcher</h1>
    
    <?php settings_errors('wprbl'); ?>
    
    <div style="margin-bottom: 20px;">
        <form method="POST" style="display: inline;">
            <?php wp_nonce_field('wprbl_action'); ?>
            <input type="hidden" name="wprbl_action" value="sync_rbls">
            <button type="submit" class="button button-secondary">Sync RBL List</button>
        </form>
        <span style="margin-left: 10px; color: #666; font-size: 13px;">Synchronize RBL list with current configuration</span>
    </div>
    
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
            <p>
                <button type="button" class="button" onclick="toggleSelectAll()">Select All</button>
                <button type="button" class="button button-link-delete" id="bulk-delete-btn" style="display: none;" onclick="submitBulkDelete()">Delete Selected</button>
            </p>
            <form method="POST" id="bulk-delete-form" style="display: none;">
                <?php wp_nonce_field('wprbl_action'); ?>
                <input type="hidden" name="wprbl_action" value="delete_ips_bulk">
                <div id="bulk-delete-inputs"></div>
            </form>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" id="select-all-checkbox" onclick="toggleSelectAll()"></th>
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
                        $has_been_checked = isset($checked_ip_ids) && isset($checked_ip_ids[$ip['id']]);
                        
                        foreach ($blacklisted_ips as $blip) {
                            if ($blip['id'] == $ip['id']) {
                                $is_blacklisted = true;
                                $listing_count = $blip['listing_count'];
                                break;
                            }
                        }
                    ?>
                        <tr class="<?php echo $is_blacklisted ? 'wprbl-blacklisted' : ''; ?>" data-ip-id="<?php echo esc_attr($ip['id']); ?>">
                            <td><input type="checkbox" name="ip_ids[]" value="<?php echo esc_attr($ip['id']); ?>" class="ip-checkbox" onchange="updateBulkDeleteButton()"></td>
                            <td><?php echo esc_html($ip['ip_address']); ?></td>
                            <td>
                                <?php if ($is_blacklisted): ?>
                                    <span class="wprbl-badge wprbl-badge-danger wprbl-expandable" style="cursor: pointer;" onclick="toggleRblDetails(<?php echo $ip['id']; ?>, this)" title="Click to see which RBLs listed this IP">
                                        Blacklisted (<?php echo $listing_count; ?>) <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; vertical-align: middle;"></span>
                                    </span>
                                <?php elseif ($has_been_checked): ?>
                                    <span class="wprbl-badge wprbl-badge-success">Clean</span>
                                <?php else: ?>
                                    <span class="wprbl-badge" style="background-color: #999; color: #fff;">Unchecked</span>
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
                        <?php if ($is_blacklisted): ?>
                            <tr class="wprbl-rbl-details" id="rbl-details-<?php echo $ip['id']; ?>" style="display: none;">
                                <td colspan="5" style="padding: 10px 20px; background-color: #fff3cd; border-left: 4px solid #ffc107;">
                                    <div style="font-weight: bold; margin-bottom: 8px;">RBLs that listed this IP:</div>
                                    <div id="rbl-list-<?php echo $ip['id']; ?>" style="color: #856404;">
                                        <span class="spinner is-active" style="float: none; margin: 0;"></span> Loading...
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
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
                <tr>
                    <th><label for="from_email">From Email Address:</label></th>
                    <td>
                        <input type="email" name="from_email" id="from_email" value="<?php echo esc_attr($prefs['from_email'] ?? ''); ?>" class="regular-text" placeholder="rbl@<?php echo esc_attr(parse_url(site_url(), PHP_URL_HOST) ?: 'example.com'); ?>">
                        <p class="description">Email address to use as the sender for reports. Default: rbl@<?php echo esc_html(parse_url(site_url(), PHP_URL_HOST) ?: 'example.com'); ?></p>
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

function toggleSelectAll() {
    const selectAll = document.getElementById('select-all-checkbox');
    const checkboxes = document.querySelectorAll('.ip-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    // Toggle all checkboxes
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
    });
    
    // Update select all checkbox
    selectAll.checked = !allChecked;
    
    updateBulkDeleteButton();
}

function updateBulkDeleteButton() {
    const checkboxes = document.querySelectorAll('.ip-checkbox:checked');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    
    if (checkboxes.length > 0) {
        bulkDeleteBtn.style.display = 'inline-block';
        bulkDeleteBtn.textContent = 'Delete Selected (' + checkboxes.length + ')';
    } else {
        bulkDeleteBtn.style.display = 'none';
    }
    
    // Update select all checkbox state
    const allCheckboxes = document.querySelectorAll('.ip-checkbox');
    const selectAll = document.getElementById('select-all-checkbox');
    selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
}

function submitBulkDelete() {
    const checkboxes = document.querySelectorAll('.ip-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('No IP addresses selected.');
        return;
    }
    
    if (!confirm('Are you sure you want to delete ' + checkboxes.length + ' selected IP address(es)?')) {
        return;
    }
    
    // Build hidden inputs for selected IPs
    const form = document.getElementById('bulk-delete-form');
    const inputsDiv = document.getElementById('bulk-delete-inputs');
    inputsDiv.innerHTML = '';
    
    checkboxes.forEach(function(checkbox) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ip_ids[]';
        input.value = checkbox.value;
        inputsDiv.appendChild(input);
    });
    
    // Submit the form
    form.submit();
}

function toggleRblDetails(ipId, badgeElement) {
    const detailsRow = document.getElementById('rbl-details-' + ipId);
    const listDiv = document.getElementById('rbl-list-' + ipId);
    const arrow = badgeElement.querySelector('.dashicons');
    
    if (detailsRow.style.display === 'none' || detailsRow.style.display === '') {
        // Expand - load RBL details
        detailsRow.style.display = 'table-row';
        listDiv.innerHTML = '<span class="spinner is-active" style="float: none; margin: 0;"></span> Loading...';
        arrow.classList.remove('dashicons-arrow-down-alt2');
        arrow.classList.add('dashicons-arrow-up-alt2');
        
        // Load RBL details via AJAX
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wprbl_get_ip_rbls',
                    ip_id: ipId,
                    _ajax_nonce: '<?php echo esc_js($ajax_nonce); ?>'
                },
            success: function(response) {
                if (response.success && response.data.rbls.length > 0) {
                    let html = '<ul style="margin: 0; padding-left: 20px;">';
                    response.data.rbls.forEach(function(rbl) {
                        html += '<li style="margin: 5px 0;">';
                        html += '<strong>' + rbl.name + '</strong> (' + rbl.dns_suffix + ')';
                        if (rbl.response_text) {
                            html += ' - Response: <code>' + rbl.response_text + '</code>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                    listDiv.innerHTML = html;
                } else {
                    listDiv.innerHTML = '<em>No RBL details found.</em>';
                }
            },
            error: function() {
                listDiv.innerHTML = '<em style="color: #dc3545;">Error loading RBL details.</em>';
            }
        });
    } else {
        // Collapse
        detailsRow.style.display = 'none';
        arrow.classList.remove('dashicons-arrow-up-alt2');
        arrow.classList.add('dashicons-arrow-down-alt2');
    }
}
</script>

