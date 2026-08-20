<?php
/**
 * IP Blacklist Management
 * 
 * Admin interface for managing blocked IP addresses
 * - View all blocked IPs
 * - Add manual blocks
 * - Remove/unblock IPs
 * - Set block duration
 * - View block history
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

require_once __DIR__ . '/../includes/permissions.php';
require_permission('security.settings');

$currentUserId = (int)$_SESSION['user_id'];
$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 1;

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if ($action === 'block_ip') {
        $ip = $_POST['ip_address'] ?? '';
        $reason = $_POST['reason'] ?? '';
        $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 3600;
        $permanent = isset($_POST['permanent']) && $_POST['permanent'] == '1';
        
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $durationSeconds = $permanent ? 0 : $duration;
            if (RateLimiter::blockIP($ip, $reason, $durationSeconds, $tenantId, $currentUserId)) {
                echo json_encode(['success' => true, 'message' => 'IP blocked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to block IP']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid IP address']);
        }
        exit;
    }
    
    if ($action === 'unblock_ip') {
        $ip = $_POST['ip_address'] ?? '';
        
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            if (RateLimiter::unblockIP($ip, $tenantId)) {
                echo json_encode(['success' => true, 'message' => 'IP unblocked successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to unblock IP']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid IP address']);
        }
        exit;
    }
    
    if ($action === 'cleanup_expired') {
        $cleaned = RateLimiter::cleanupExpiredBlocks();
        echo json_encode(['success' => true, 'cleaned' => $cleaned]);
        exit;
    }
}

// Get all blocked IPs
$stmt = $pdo->prepare("
    SELECT * FROM ip_blacklist 
    WHERE tenant_id IS NULL OR tenant_id = ? 
    ORDER BY blocked_at DESC
");
$stmt->execute([$tenantId]);
$blockedIPs = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Count active blocks
$activeCount = 0;
$expiredCount = 0;
foreach ($blockedIPs as $block) {
    if ($block['permanent'] || ($block['blocked_until'] && strtotime($block['blocked_until']) > time())) {
        $activeCount++;
    } else {
        $expiredCount++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Blacklist Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-badge.active {
            background-color: #fee;
            color: #c33;
        }
        .status-badge.expired {
            background-color: #f0f0f0;
            color: #666;
        }
        .status-badge.permanent {
            background-color: #dcccff;
            color: #4c0099;
        }
        .card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 15px;
            font-weight: 600;
        }
        .ip-row {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .ip-row:last-child {
            border-bottom: none;
        }
        .ip-row:hover {
            background-color: #f8f9fa;
        }
        .ip-address {
            font-family: monospace;
            font-size: 16px;
            font-weight: bold;
        }
        .nav-tabs .nav-link {
            color: #667eea;
            border: none;
            border-bottom: 2px solid transparent;
        }
        .nav-tabs .nav-link.active {
            color: #667eea;
            background-color: transparent;
            border-bottom: 2px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="mb-0">
                    <i class="fas fa-ban"></i> IP Blacklist Management
                </h1>
                <small class="text-muted">Monitor and manage blocked IP addresses</small>
            </div>
            <div class="col-md-4 text-end">
                <a href="rate_limits.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Rate Limits
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($blockedIPs); ?></div>
                    <div class="stat-label">Total Blocked IPs</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-number"><?php echo $activeCount; ?></div>
                    <div class="stat-label">Active Blocks</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="stat-number"><?php echo $expiredCount; ?></div>
                    <div class="stat-label">Expired Blocks</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#blocked">Blocked IPs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#add">Add Block</a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Blocked IPs Tab -->
            <div id="blocked" class="tab-pane fade show active">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-list"></i> All Blocked IPs
                    </div>
                    
                    <?php if (!empty($blockedIPs)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($blockedIPs as $block): 
                            $isActive = $block['permanent'] || ($block['blocked_until'] && strtotime($block['blocked_until']) > time());
                            $status = $isActive ? 'active' : 'expired';
                            $typeClass = $block['permanent'] ? 'permanent' : $status;
                        ?>
                        <div class="ip-row">
                            <div class="row align-items-center">
                                <div class="col-md-3">
                                    <div class="ip-address"><?php echo htmlspecialchars($block['ip_address']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($block['reason'] ?? 'No reason provided'); ?></small>
                                </div>
                                <div class="col-md-3">
                                    <?php if ($block['permanent']): ?>
                                        <span class="status-badge permanent">
                                            <i class="fas fa-lock"></i> Permanent
                                        </span>
                                    <?php elseif ($isActive): ?>
                                        <span class="status-badge active">
                                            <i class="fas fa-hourglass-end"></i> Active
                                        </span><br>
                                        <small class="text-muted">Until: <?php echo date('Y-m-d H:i:s', strtotime($block['blocked_until'])); ?></small>
                                    <?php else: ?>
                                        <span class="status-badge expired">
                                            <i class="fas fa-check"></i> Expired
                                        </span><br>
                                        <small class="text-muted">Expired: <?php echo date('Y-m-d H:i:s', strtotime($block['blocked_until'])); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> Blocked: <?php echo date('Y-m-d H:i:s', strtotime($block['blocked_at'])); ?><br>
                                        <?php if ($block['created_by']): ?>
                                            <i class="fas fa-user"></i> By admin ID: <?php echo $block['created_by']; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="col-md-3 text-end">
                                    <button class="btn btn-sm btn-outline-success" onclick="unblockIP('<?php echo htmlspecialchars($block['ip_address']); ?>')">
                                        <i class="fas fa-unlock"></i> Unblock
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <p>No blocked IPs</p>
                    </div>
                    <?php endif; ?>

                    <?php if ($expiredCount > 0): ?>
                    <div class="card-footer">
                        <button class="btn btn-sm btn-outline-secondary" onclick="cleanupExpired()">
                            <i class="fas fa-trash"></i> Clean Expired Blocks (<?php echo $expiredCount; ?>)
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Block Tab -->
            <div id="add" class="tab-pane fade">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-plus"></i> Block New IP
                    </div>
                    <div class="card-body">
                        <form id="blockIPForm">
                            <div class="mb-3">
                                <label class="form-label">IP Address</label>
                                <input type="text" class="form-control" name="ip_address" placeholder="192.168.1.100" required>
                                <small class="text-muted">Enter IPv4 or IPv6 address</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <textarea class="form-control" name="reason" rows="2" placeholder="Why is this IP being blocked?"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Block Type</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="block_type" id="temporaryBlock" value="temporary" checked>
                                    <label class="form-check-label" for="temporaryBlock">
                                        Temporary
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="block_type" id="permanentBlock" value="permanent">
                                    <label class="form-check-label" for="permanentBlock">
                                        Permanent
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3" id="durationDiv">
                                <label class="form-label">Block Duration</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="duration_value" value="1" min="1">
                                    <select class="form-select" name="duration_unit" style="max-width: 120px;">
                                        <option value="3600">Hours</option>
                                        <option value="86400">Days</option>
                                        <option value="604800">Weeks</option>
                                    </select>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <h6>Quick Presets:</h6>
                                <button type="button" class="btn btn-sm btn-outline-info me-2 mb-2" onclick="setDuration(3600, 'hours')">1 Hour</button>
                                <button type="button" class="btn btn-sm btn-outline-info me-2 mb-2" onclick="setDuration(86400, 'days')">1 Day</button>
                                <button type="button" class="btn btn-sm btn-outline-info me-2 mb-2" onclick="setDuration(604800, 'days')">1 Week</button>
                                <button type="button" class="btn btn-sm btn-outline-info me-2 mb-2" onclick="setPermanent()">Permanent</button>
                            </div>

                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-ban"></i> Block IP
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">Reset</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle duration input based on block type
        document.querySelectorAll('input[name="block_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('durationDiv').style.display = 
                    this.value === 'temporary' ? 'block' : 'none';
            });
        });

        // Form submission
        document.getElementById('blockIPForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const blockType = document.querySelector('input[name="block_type"]:checked').value;
            const formData = new FormData();
            
            formData.append('action', 'block_ip');
            formData.append('ip_address', this.ip_address.value);
            formData.append('reason', this.reason.value);
            formData.append('permanent', blockType === 'permanent' ? '1' : '0');
            
            if (blockType === 'temporary') {
                const duration = parseInt(this.duration_value.value) * parseInt(this.duration_unit.value);
                formData.append('duration', duration);
            }
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('IP blocked successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Error: ' + err));
        });

        function unblockIP(ip) {
            if (!confirm('Are you sure you want to unblock ' + ip + '?')) return;
            
            const formData = new FormData();
            formData.append('action', 'unblock_ip');
            formData.append('ip_address', ip);
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('IP unblocked successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Error: ' + err));
        }

        function cleanupExpired() {
            if (!confirm('Remove all expired blocks?')) return;
            
            const formData = new FormData();
            formData.append('action', 'cleanup_expired');
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Cleaned ' + data.cleaned + ' expired blocks');
                    location.reload();
                }
            });
        }

        function setDuration(seconds, unit) {
            const hours = seconds / 3600;
            const days = seconds / 86400;
            const weeks = seconds / 604800;
            
            document.querySelector('input[name="duration_value"]').value = 
                unit === 'hours' ? Math.round(hours) : 
                unit === 'days' ? Math.round(days) : 
                Math.round(weeks);
            
            document.querySelector('select[name="duration_unit"]').value = 
                unit === 'hours' ? '3600' : '86400';
            
            document.getElementById('temporaryBlock').checked = true;
            document.getElementById('durationDiv').style.display = 'block';
        }

        function setPermanent() {
            document.getElementById('permanentBlock').checked = true;
            document.getElementById('durationDiv').style.display = 'none';
        }
    </script>
</body>
</html>
