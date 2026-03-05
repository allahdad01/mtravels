<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once 'security.php';

// Check if user is a super admin (system administrator, not tenant-based)
if (!check_super_admin()) {
    http_response_code(403);
    echo '<p class="text-danger">Access denied</p>';
    exit();
}

// Database connection
require_once '../includes/db.php';

$id = intval($_GET['id'] ?? 0);
$basic = isset($_GET['basic']);

if (!$id) {
    echo '<p class="text-danger">Invalid request ID</p>';
    exit();
}

// Fetch demo request
$stmt = $pdo->prepare("SELECT * FROM demo_requests WHERE id = ?");
$stmt->execute([$id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    echo '<p class="text-danger">Request not found</p>';
    exit();
}

function getStatusPillClass($status) {
    $classes = [
        'pending' => 'pill-pending',
        'contacted' => 'pill-contacted',
        'scheduled' => 'pill-scheduled',
        'completed' => 'pill-completed',
        'cancelled' => 'pill-cancelled'
    ];
    return $classes[$status] ?? 'pill-pending';
}

if ($basic): ?>
<div style="padding: 8px;">
    <p><strong>Name:</strong> <?php echo htmlspecialchars($request['name']); ?></p>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($request['email']); ?></p>
    <p><strong>Company:</strong> <?php echo htmlspecialchars($request['company']); ?></p>
</div>
<?php else: ?>
<style>
    .detail-card {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
    }
    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #e0e0e0;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-label {
        font-weight: 600;
        color: #666;
        font-size: 0.85rem;
    }
    .detail-value {
        color: #333;
        font-size: 0.9rem;
        text-align: right;
    }
    .detail-value i {
        margin-right: 6px;
        color: #888;
    }
    .status-pill {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .pill-pending { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
    .pill-contacted { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
    .pill-scheduled { background: rgba(139, 92, 246, 0.12); color: #8b5cf6; }
    .pill-completed { background: rgba(16, 185, 129, 0.12); color: #10b981; }
    .pill-cancelled { background: rgba(239, 68, 68, 0.12); color: #ef4444; }
</style>

<div class="detail-card">
    <div class="detail-row">
        <span class="detail-label">Name</span>
        <span class="detail-value"><?php echo htmlspecialchars($request['name']); ?></span>
    </div>
    <div class="detail-row">
        <span class="detail-label">Email</span>
        <span class="detail-value">
            <i class="feather icon-mail"></i>
            <?php echo htmlspecialchars($request['email']); ?>
        </span>
    </div>
    <?php if ($request['phone']): ?>
    <div class="detail-row">
        <span class="detail-label">Phone</span>
        <span class="detail-value">
            <i class="feather icon-phone"></i>
            <?php echo htmlspecialchars($request['phone']); ?>
        </span>
    </div>
    <?php endif; ?>
    <div class="detail-row">
        <span class="detail-label">Company</span>
        <span class="detail-value">
            <i class="feather icon-briefcase"></i>
            <?php echo htmlspecialchars($request['company']); ?>
        </span>
    </div>
    <?php if ($request['company_size']): ?>
    <div class="detail-row">
        <span class="detail-label">Company Size</span>
        <span class="detail-value"><?php echo htmlspecialchars($request['company_size']); ?></span>
    </div>
    <?php endif; ?>
    <div class="detail-row">
        <span class="detail-label">Status</span>
        <span class="detail-value">
            <span class="status-pill <?php echo getStatusPillClass($request['status']); ?>">
                <?php echo ucfirst(htmlspecialchars($request['status'])); ?>
            </span>
        </span>
    </div>
    <?php if ($request['preferred_date']): ?>
    <div class="detail-row">
        <span class="detail-label">Preferred Date</span>
        <span class="detail-value">
            <i class="feather icon-calendar"></i>
            <?php echo date('M d, Y', strtotime($request['preferred_date'])); ?>
            <?php if ($request['preferred_time']): ?>
                at <?php echo date('H:i', strtotime($request['preferred_time'])); ?>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
    <?php if ($request['message']): ?>
    <div class="detail-row" style="flex-direction: column; align-items: flex-start;">
        <span class="detail-label">Message</span>
        <span class="detail-value" style="text-align: left; margin-top: 8px;">
            <?php echo nl2br(htmlspecialchars($request['message'])); ?>
        </span>
    </div>
    <?php endif; ?>
    <div class="detail-row">
        <span class="detail-label">Created</span>
        <span class="detail-value">
            <i class="feather icon-clock"></i>
            <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?>
        </span>
    </div>
    <?php if ($request['updated_at']): ?>
    <div class="detail-row">
        <span class="detail-label">Last Updated</span>
        <span class="detail-value">
            <i class="feather icon-refresh-ccw"></i>
            <?php echo date('M d, Y H:i', strtotime($request['updated_at'])); ?>
        </span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
