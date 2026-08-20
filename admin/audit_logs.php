<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ChatAudit.php';

// Check if user is logged in
require_permission('security.view');

require_once __DIR__ . '/../includes/permissions.php';
require_permission('security.view');

$currentUserId = (int)$_SESSION['user_id'];

// Get user tenant and branch info
$userStmt = secure_query($pdo, 'SELECT tenant_id, branch_id FROM users WHERE id = ?', [$currentUserId]);
$currentUser = $userStmt ? $userStmt->fetch(PDO::FETCH_ASSOC) : null;
if (!$currentUser) {
    die('User not found');
}

$tenantId = (int)$currentUser['tenant_id'];
$branchId = (int)$currentUser['branch_id'];

// Get filter parameters
$filters = [];
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$targetUserId = isset($_GET['target_user_id']) ? (int)$_GET['target_user_id'] : null;
$messageId = isset($_GET['message_id']) ? (int)$_GET['message_id'] : null;
$export = isset($_GET['export']) ? $_GET['export'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

// Build filters
if ($userId) $filters['user_id'] = $userId;
if ($action) $filters['action'] = $action;
if ($status) $filters['status'] = $status;
if ($startDate) $filters['start_date'] = $startDate;
if ($endDate) $filters['end_date'] = $endDate;
if ($targetUserId) $filters['target_user_id'] = $targetUserId;
if ($messageId) $filters['message_id'] = $messageId;
$filters['limit'] = $limit;

// Handle export
if ($export) {
    $logs = ChatAudit::getAuditLog($tenantId, $filters);
    $csv = ChatAudit::exportAuditLog($tenantId, $filters, 'csv');
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_log_' . date('Y-m-d_H-i-s') . '.csv"');
    echo $csv;
    exit;
}

// Get logs
$logs = ChatAudit::getAuditLog($tenantId, $filters);
$totalCount = ChatAudit::getAuditLogCount($tenantId, $filters);

// Get summary
$summary = ChatAudit::getSummary($tenantId, 7);

// Get list of actions for filter dropdown
$actionsStmt = secure_query($pdo, 
    'SELECT DISTINCT action FROM chat_audit_log WHERE tenant_id = ? ORDER BY action', 
    [$tenantId]
);
$actions = $actionsStmt ? $actionsStmt->fetchAll(PDO::FETCH_COLUMN) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs</title>
    <link rel="stylesheet" href="../css/audit_logs/styles.css">
</head>
<body>
    <div class="container">
        <h1>Audit Logs</h1>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="user_id">User ID</label>
                        <input type="number" id="user_id" name="user_id" value="<?php echo $userId ? htmlspecialchars($userId) : ''; ?>" placeholder="User ID">
                    </div>
                    <div class="filter-group">
                        <label for="action">Action</label>
                        <select id="action" name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $act): ?>
                                <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $action === $act ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($act); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                            <option value="denied" <?php echo $status === 'denied' ? 'selected' : ''; ?>>Denied</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="error" <?php echo $status === 'error' ? 'selected' : ''; ?>>Error</option>
                        </select>
                    </div>
                </div>
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="start_date">Start Date</label>
                        <input type="datetime-local" id="start_date" name="start_date" value="<?php echo $startDate ? htmlspecialchars($startDate) : ''; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="end_date">End Date</label>
                        <input type="datetime-local" id="end_date" name="end_date" value="<?php echo $endDate ? htmlspecialchars($endDate) : ''; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="limit">Limit</label>
                        <input type="number" id="limit" name="limit" value="<?php echo $limit; ?>" min="10" max="1000">
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit">Filter</button>
                    <button type="reset" style="background: #666;">Clear</button>
                    <button type="submit" name="export" value="1" class="export-btn">Export CSV</button>
                </div>
            </form>
        </div>

        <?php if (!empty($summary)): ?>
        <div class="summary">
            <?php 
            $actionCounts = [];
            foreach ($summary as $item) {
                $key = $item['action'] . '_' . $item['status'];
                if (!isset($actionCounts[$key])) {
                    $actionCounts[$key] = 0;
                }
                $actionCounts[$key] += $item['count'];
            }
            
            foreach ($actionCounts as $key => $count): ?>
                <div class="summary-card">
                    <h3><?php echo htmlspecialchars($key); ?></h3>
                    <div class="value"><?php echo $count; ?></div>
                    <div class="status">Last 7 days</div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($logs)): ?>
        <p style="color: #666; font-size: 14px;">
            Showing <?php echo count($logs); ?> of <?php echo $totalCount; ?> entries
        </p>
        
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Target User</th>
                    <th>Message ID</th>
                    <th>Status</th>
                    <th>IP Address</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($log['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                    <td><?php echo $log['target_user_id'] ? htmlspecialchars($log['target_user_id']) : '-'; ?></td>
                    <td><?php echo $log['message_id'] ? htmlspecialchars($log['message_id']) : '-'; ?></td>
                    <td>
                        <span class="status-badge status-<?php echo htmlspecialchars($log['status']); ?>">
                            <?php echo htmlspecialchars(strtoupper($log['status'])); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    <td>
                        <?php if ($log['details'] || $log['error_message']): ?>
                            <button class="details-btn" onclick="showDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)">Details</button>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="no-data">
            <p>No audit logs found matching your filters.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDetails()">&times;</span>
            <h2>Audit Log Details</h2>
            <div id="detailsContent"></div>
        </div>
    </div>

    <script>
        function showDetails(log) {
            let html = '<pre>';
            html += 'ID: ' + log.id + '\n';
            html += 'User ID: ' + log.user_id + '\n';
            html += 'Action: ' + log.action + '\n';
            html += 'Target User ID: ' + (log.target_user_id || 'N/A') + '\n';
            html += 'Status: ' + log.status + '\n';
            html += 'IP Address: ' + log.ip_address + '\n';
            html += 'User Agent: ' + log.user_agent + '\n';
            html += 'Created At: ' + log.created_at + '\n\n';
            
            if (log.details) {
                html += 'Details:\n' + JSON.stringify(JSON.parse(log.details), null, 2) + '\n\n';
            }
            
            if (log.error_message) {
                html += 'Error: ' + log.error_message + '\n';
            }
            
            html += '</pre>';
            document.getElementById('detailsContent').innerHTML = html;
            document.getElementById('detailsModal').style.display = 'block';
        }

        function closeDetails() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        window.onclick = function(event) {
            let modal = document.getElementById('detailsModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
