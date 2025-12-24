<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'reports.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance()->getConnection();
$userId = $auth->getUserId();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_ip':
                $ip = trim($_POST['ip_address'] ?? '');
                $label = trim($_POST['label'] ?? '');
                
                if (empty($ip)) {
                    $error = 'IP address is required';
                } elseif (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    $error = 'Invalid IP address format';
                } else {
                    // Check IP limit
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM ip_addresses WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $count = $stmt->fetch()['count'];
                    
                    if ($count >= MAX_IPS_PER_USER) {
                        $error = "Maximum of " . MAX_IPS_PER_USER . " IPs per account reached";
                    } else {
                        try {
                            $stmt = $db->prepare("INSERT INTO ip_addresses (user_id, ip_address, label) VALUES (?, ?, ?)");
                            $stmt->execute([$userId, $ip, $label ?: null]);
                            $message = 'IP address added successfully';
                        } catch (PDOException $e) {
                            if ($e->getCode() == 23000) {
                                $error = 'This IP address is already in your list';
                            } else {
                                $error = 'Error adding IP address';
                            }
                        }
                    }
                }
                break;
                
            case 'delete_ip':
                $ipId = $_POST['ip_id'] ?? 0;
                $stmt = $db->prepare("DELETE FROM ip_addresses WHERE id = ? AND user_id = ?");
                $stmt->execute([$ipId, $userId]);
                $message = 'IP address removed';
                break;
                
            case 'update_preferences':
                $frequency = $_POST['report_frequency'] ?? 'daily';
                $reportDay = $_POST['report_day'] ?? null;
                $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
                
                $stmt = $db->prepare("
                    UPDATE user_preferences 
                    SET report_frequency = ?, report_day = ?, email_notifications = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$frequency, $reportDay, $emailNotifications, $userId]);
                $message = 'Preferences updated';
                break;
                
            case 'run_check':
                require_once 'rbl_checker.php';
                $checker = new RBLChecker();
                $result = $checker->checkUserIPs($userId);
                $message = "Check completed: {$result['total_ips']} IPs checked, {$result['blacklisted_count']} blacklisted";
                break;
        }
    }
}

// Get user's IPs
$stmt = $db->prepare("SELECT id, ip_address, label, created_at FROM ip_addresses WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$ips = $stmt->fetchAll();

// Get IP count
$stmt = $db->prepare("SELECT COUNT(*) as count FROM ip_addresses WHERE user_id = ?");
$stmt->execute([$userId]);
$ipCount = $stmt->fetch()['count'];

// Get user preferences
$stmt = $db->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->execute([$userId]);
$prefs = $stmt->fetch();
if (!$prefs) {
    $prefs = ['report_frequency' => 'daily', 'email_notifications' => 1, 'report_day' => null];
}

// Get blacklisted IPs summary
$reportGen = new ReportGenerator();
$blacklistedIPs = $reportGen->getBlacklistedIPs($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RBL Watcher - Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>RBL Watcher</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($auth->getUsername()); ?></span>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </header>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="dashboard">
            <div class="stats">
                <div class="stat-card">
                    <h3>IP Addresses</h3>
                    <p class="stat-number"><?php echo $ipCount; ?> / <?php echo MAX_IPS_PER_USER; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Blacklisted IPs</h3>
                    <p class="stat-number"><?php echo count($blacklistedIPs); ?></p>
                </div>
            </div>
            
            <div class="section">
                <h2>Add IP Address</h2>
                <form method="POST" class="form-inline">
                    <input type="hidden" name="action" value="add_ip">
                    <input type="text" name="ip_address" placeholder="192.168.1.1" required>
                    <input type="text" name="label" placeholder="Optional label">
                    <button type="submit" class="btn btn-primary">Add IP</button>
                </form>
            </div>
            
            <div class="section">
                <h2>Your IP Addresses</h2>
                <?php if (empty($ips)): ?>
                    <p>No IP addresses added yet.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Label</th>
                                <th>Status</th>
                                <th>Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ips as $ip): 
                                $isBlacklisted = false;
                                $listingCount = 0;
                                foreach ($blacklistedIPs as $blip) {
                                    if ($blip['id'] == $ip['id']) {
                                        $isBlacklisted = true;
                                        $listingCount = $blip['listing_count'];
                                        break;
                                    }
                                }
                            ?>
                                <tr class="<?php echo $isBlacklisted ? 'blacklisted' : ''; ?>">
                                    <td><?php echo htmlspecialchars($ip['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($ip['label'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($isBlacklisted): ?>
                                            <span class="badge badge-danger">Blacklisted (<?php echo $listingCount; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Clean</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($ip['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                            <input type="hidden" name="action" value="delete_ip">
                                            <input type="hidden" name="ip_id" value="<?php echo $ip['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h2>Blacklisted IPs Report</h2>
                <?php if (empty($blacklistedIPs)): ?>
                    <p>No blacklisted IPs found.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Label</th>
                                <th>Listings</th>
                                <th>RBLs</th>
                                <th>Last Checked</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blacklistedIPs as $ip): ?>
                                <tr class="blacklisted">
                                    <td><?php echo htmlspecialchars($ip['ip_address']); ?></td>
                                    <td><?php echo htmlspecialchars($ip['label'] ?? '-'); ?></td>
                                    <td><strong><?php echo $ip['listing_count']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($ip['rbl_names']); ?></td>
                                    <td><?php echo htmlspecialchars($ip['last_checked']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h2>Actions</h2>
                <form method="POST" style="display: inline-block; margin-right: 10px;">
                    <input type="hidden" name="action" value="run_check">
                    <button type="submit" class="btn btn-primary">Run RBL Check Now</button>
                </form>
                <a href="report.php" class="btn btn-secondary">View Full Report</a>
                <a href="report.php?format=csv" class="btn btn-secondary">Download CSV</a>
            </div>
            
            <div class="section">
                <h2>Preferences</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_preferences">
                    <div class="form-group">
                        <label for="report_frequency">Report Frequency:</label>
                        <select name="report_frequency" id="report_frequency" onchange="toggleReportDay()">
                            <option value="daily" <?php echo $prefs['report_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $prefs['report_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        </select>
                    </div>
                    <div class="form-group" id="report-day-group" style="display: <?php echo $prefs['report_frequency'] === 'weekly' ? 'block' : 'none'; ?>;">
                        <label for="report_day">Report Day (Weekly):</label>
                        <select name="report_day" id="report_day">
                            <option value="0" <?php echo $prefs['report_day'] == 0 ? 'selected' : ''; ?>>Sunday</option>
                            <option value="1" <?php echo $prefs['report_day'] == 1 ? 'selected' : ''; ?>>Monday</option>
                            <option value="2" <?php echo $prefs['report_day'] == 2 ? 'selected' : ''; ?>>Tuesday</option>
                            <option value="3" <?php echo $prefs['report_day'] == 3 ? 'selected' : ''; ?>>Wednesday</option>
                            <option value="4" <?php echo $prefs['report_day'] == 4 ? 'selected' : ''; ?>>Thursday</option>
                            <option value="5" <?php echo $prefs['report_day'] == 5 ? 'selected' : ''; ?>>Friday</option>
                            <option value="6" <?php echo $prefs['report_day'] == 6 ? 'selected' : ''; ?>>Saturday</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="email_notifications" value="1" <?php echo $prefs['email_notifications'] ? 'checked' : ''; ?>>
                            Email Notifications
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function toggleReportDay() {
            const frequency = document.getElementById('report_frequency').value;
            document.getElementById('report-day-group').style.display = frequency === 'weekly' ? 'block' : 'none';
        }
    </script>
</body>
</html>

