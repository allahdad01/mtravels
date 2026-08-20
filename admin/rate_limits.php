<?php
/**
 * Admin Rate Limits Management Interface
 * 
 * Allows admins to monitor and manage rate limiting:
 * - View rate limit statistics
 * - Monitor violations
 * - Manage IP blacklist
 * - View user quotas
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission('security.settings');

$tenantId = $_SESSION['tenant_id'] ?? 1;
$adminId = $_SESSION['user_id'];

// Handle form submissions
$action = $_POST['action'] ?? '';
$message = '';
$error = '';

if ($action === 'unblock_ip') {
    $ipAddress = $_POST['ip_address'] ?? '';
    if ($ipAddress && RateLimiter::unblockIP($ipAddress, $tenantId)) {
        $message = "IP address unblocked successfully.";
    } else {
        $error = "Failed to unblock IP address.";
    }
}

if ($action === 'block_ip') {
    $ipAddress = $_POST['ip_address'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $duration = $_POST['duration'] ?? '0';
    
    if ($ipAddress && $reason) {
        $durationSeconds = $duration == 'permanent' ? 0 : (int)$duration * 3600;
        if (RateLimiter::blockIP($ipAddress, $reason, $durationSeconds, $tenantId, $adminId)) {
            $message = "IP address blocked successfully.";
        } else {
            $error = "Failed to block IP address.";
        }
    } else {
        $error = "Please provide both IP address and reason.";
    }
}

// Get statistics
$stats = [
    'rate_limit_records' => 0,
    'violations_today' => 0,
    'violations_week' => 0,
    'blocked_ips' => 0,
    'active_limits' => []
];

try {
    // Count rate limit records
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rate_limits WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $stats['rate_limit_records'] = $stmt->fetch()['count'];
    
    // Count violations today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM rate_limit_violations 
        WHERE tenant_id = ? AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$tenantId]);
    $stats['violations_today'] = $stmt->fetch()['count'];
    
    // Count violations this week
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM rate_limit_violations 
        WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$tenantId]);
    $stats['violations_week'] = $stmt->fetch()['count'];
    
    // Count blocked IPs
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM ip_blacklist 
        WHERE (tenant_id = ? OR tenant_id IS NULL) 
        AND (permanent = 1 OR blocked_until > NOW())
    ");
    $stmt->execute([$tenantId]);
    $stats['blocked_ips'] = $stmt->fetch()['count'];
    
    // Get active limits by type
    $stmt = $pdo->prepare("
        SELECT limit_name, COUNT(*) as count, AVG(current_count) as avg_usage 
        FROM rate_limits 
        WHERE tenant_id = ? 
        GROUP BY limit_name 
        ORDER BY count DESC
    ");
    $stmt->execute([$tenantId]);
    $stats['active_limits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Rate limits stats error: " . $e->getMessage());
}

// Get violations for display
$violations = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.*, u.name, u.email 
        FROM rate_limit_violations v 
        LEFT JOIN users u ON v.user_id = u.id 
        WHERE v.tenant_id = ? 
        ORDER BY v.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$tenantId]);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Get violations error: " . $e->getMessage());
}

// Get blocked IPs
$blockedIPs = RateLimiter::getBlockedIPs($tenantId, 100);

// Get top users by quota usage
$topUsers = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.key_value, r.limit_name, r.current_count, r.limit_value,
               u.name, u.email, u.id
        FROM rate_limits r 
        LEFT JOIN users u ON r.key_value = u.id AND r.key_type = 'user'
        WHERE r.tenant_id = ? AND r.key_type = 'user'
        ORDER BY r.current_count DESC 
        LIMIT 20
    ");
    $stmt->execute([$tenantId]);
    $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Get top users error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Limits Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { color: #333; margin-bottom: 30px; }
        h2 { color: #555; margin-top: 30px; margin-bottom: 20px; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 32px; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; font-size: 14px; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; color: #333; }
        tr:hover { background: #f9f9f9; }
        .btn { padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .form-inline { display: flex; gap: 10px; }
        .form-inline input { flex: 1; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-success { background: #28a745; color: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; }
        .modal-close { cursor: pointer; font-size: 28px; float: right; color: #666; }
        .limit-bar { background: #e9ecef; border-radius: 4px; height: 20px; overflow: hidden; }
        .limit-progress { background: #007bff; height: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Rate Limits Management</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['rate_limit_records'] ?></div>
                <div class="stat-label">Active Rate Limits</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['violations_today'] ?></div>
                <div class="stat-label">Violations Today</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['violations_week'] ?></div>
                <div class="stat-label">Violations (7 Days)</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['blocked_ips'] ?></div>
                <div class="stat-label">Blocked IPs</div>
            </div>
        </div>
        
        <!-- Active Limits by Type -->
        <div class="card">
            <h2>Rate Limits by Type</h2>
            <table>
                <thead>
                    <tr>
                        <th>Limit Type</th>
                        <th>Active Records</th>
                        <th>Avg Usage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['active_limits'] as $limit): ?>
                        <tr>
                            <td><?= htmlspecialchars($limit['limit_name']) ?></td>
                            <td><?= $limit['count'] ?></td>
                            <td><?= round($limit['avg_usage'], 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Top Users by Quota Usage -->
        <div class="card">
            <h2>Top Users by Quota Usage</h2>
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Limit Type</th>
                        <th>Usage / Limit</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topUsers as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['name'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($user['limit_name']) ?></td>
                            <td><?= $user['current_count'] ?> / <?= $user['limit_value'] ?></td>
                            <td>
                                <div class="limit-bar">
                                    <div class="limit-progress" style="width: <?= min(100, ($user['current_count'] / $user['limit_value']) * 100) ?>%"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Recent Violations -->
        <div class="card">
            <h2>Recent Violations</h2>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>User</th>
                        <th>Limit Type</th>
                        <th>Current / Limit</th>
                        <th>Action Taken</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($violations as $v): ?>
                        <tr>
                            <td><?= date('Y-m-d H:i:s', strtotime($v['created_at'])) ?></td>
                            <td><?= htmlspecialchars($v['name'] ?? 'Unknown') ?> (ID: <?= $v['user_id'] ?>)</td>
                            <td><?= htmlspecialchars($v['limit_name']) ?></td>
                            <td><?= $v['current_value'] ?> / <?= $v['limit_value'] ?></td>
                            <td><span class="badge badge-warning"><?= htmlspecialchars($v['action_taken']) ?></span></td>
                            <td><?= htmlspecialchars($v['ip_address'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- IP Blacklist Management -->
        <div class="card">
            <h2>IP Blacklist Management</h2>
            
            <h3 style="margin-top: 0; margin-bottom: 15px;">Block New IP Address</h3>
            <form method="POST" class="form-inline" style="display: flex; flex-direction: column; gap: 15px;">
                <input type="hidden" name="action" value="block_ip">
                
                <div class="form-group">
                    <label>IP Address:</label>
                    <input type="text" name="ip_address" placeholder="e.g., 192.168.1.1" required>
                </div>
                
                <div class="form-group">
                    <label>Reason:</label>
                    <input type="text" name="reason" placeholder="e.g., Suspicious activity" required>
                </div>
                
                <div class="form-group">
                    <label>Duration:</label>
                    <select name="duration">
                        <option value="1">1 hour</option>
                        <option value="24">1 day</option>
                        <option value="168">1 week</option>
                        <option value="permanent" selected>Permanent</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Block IP Address</button>
            </form>
            
            <h3 style="margin-top: 30px; margin-bottom: 15px;">Currently Blocked IPs</h3>
            <?php if (count($blockedIPs) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>IP Address</th>
                            <th>Reason</th>
                            <th>Blocked At</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blockedIPs as $ip): ?>
                            <tr>
                                <td><?= htmlspecialchars($ip['ip_address']) ?></td>
                                <td><?= htmlspecialchars($ip['reason'] ?? '-') ?></td>
                                <td><?= date('Y-m-d H:i:s', strtotime($ip['blocked_at'])) ?></td>
                                <td><?= $ip['permanent'] ? 'Permanent' : date('Y-m-d H:i:s', strtotime($ip['blocked_until'])) ?></td>
                                <td>
                                    <?php if ($ip['permanent']): ?>
                                        <span class="badge badge-danger">Permanent</span>
                                    <?php else: ?>
                                        <?php if (strtotime($ip['blocked_until']) > time()): ?>
                                            <span class="badge badge-danger">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Expired</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="unblock_ip">
                                        <input type="hidden" name="ip_address" value="<?= htmlspecialchars($ip['ip_address']) ?>">
                                        <button type="submit" class="btn btn-success" onclick="return confirm('Unblock this IP?');">Unblock</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No blocked IPs.</p>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
