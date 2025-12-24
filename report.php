<?php
require_once 'auth.php';
require_once 'reports.php';

$auth = new Auth();
$auth->requireLogin();

$userId = $auth->getUserId();
$reportGen = new ReportGenerator();

// Get date range
$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'html';

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="rbl_report_' . date('Y-m-d') . '.csv"');
    echo $reportGen->generateCSVReport($userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59');
    exit;
}

$blacklistedIPs = $reportGen->getBlacklistedIPs($userId, $startDate . ' 00:00:00', $endDate . ' 23:59:59');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RBL Watcher - Report</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>RBL Watcher - Report</h1>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>
        
        <div class="section">
            <h2>Report Filters</h2>
            <form method="GET" class="form-inline">
                <label>Start Date:</label>
                <input type="date" name="start" value="<?php echo htmlspecialchars($startDate); ?>">
                <label>End Date:</label>
                <input type="date" name="end" value="<?php echo htmlspecialchars($endDate); ?>">
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
        
        <div class="section">
            <h2>Blacklisted IPs Report</h2>
            <p>Report Period: <?php echo htmlspecialchars($startDate); ?> to <?php echo htmlspecialchars($endDate); ?></p>
            
            <?php if (empty($blacklistedIPs)): ?>
                <p>No blacklisted IPs found for the selected period.</p>
            <?php else: ?>
                <p><strong>Total blacklisted IPs: <?php echo count($blacklistedIPs); ?></strong></p>
                <a href="?start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>&format=csv" class="btn btn-secondary">Download CSV</a>
                
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
    </div>
</body>
</html>

