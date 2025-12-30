<?php
session_start();
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/SupportTicketManager.php';

// Check if user is super admin
if (!isset($_SESSION['super_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$ticketManager = new SupportTicketManager($pdo);

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$tenant_filter = $_GET['tenant_id'] ?? '';

$filters = [];
if ($status_filter) {
    $filters['status'] = $status_filter;
}
if ($tenant_filter) {
    $filters['tenant_id'] = intval($tenant_filter);
}

// Get all tickets for super admin
$tickets = $ticketManager->getTicketsForSuperAdmin($filters);
$stats = $ticketManager->getStatistics();

// Get list of tenants
try {
    $stmt = $pdo->query("SELECT id, name FROM tenants ORDER BY name");
    $tenants = $stmt->fetchAll();
} catch (Exception $e) {
    $tenants = [];
}

$pageTitle = 'Support Tickets Management';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .page-container {
            padding: 30px 20px;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid #007bff;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        .filters-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .ticket-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table {
            margin: 0;
        }
        .priority-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-actions {
            display: flex;
            gap: 5px;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 11px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .page-header {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: #ffffff;
            padding: 25px 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h2 {
            color: #ffffff;
            margin: 0;
            flex: 1;
        }
        .page-header .btn-back {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .page-header .btn-back:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            color: #ffffff;
        }
    </style>
</head>
<body>
<div class="page-container">
    <div class="page-header">
        <h2>Support Tickets Management</h2>
        <a href="../admin/support_tickets.php" class="btn-back">← Back to Admin</a>
    </div>
    
    <!-- Statistics -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Tickets</div>
        </div>
        <div class="stat-card" style="border-top-color: #dc3545;">
            <div class="stat-number" style="color: #dc3545;"><?php echo $stats['open'] ?? 0; ?></div>
            <div class="stat-label">Open</div>
        </div>
        <div class="stat-card" style="border-top-color: #ffc107;">
            <div class="stat-number" style="color: #ffc107;"><?php echo $stats['in_progress'] ?? 0; ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card" style="border-top-color: #28a745;">
            <div class="stat-number" style="color: #28a745;"><?php echo $stats['resolved'] ?? 0; ?></div>
            <div class="stat-label">Resolved</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" class="form-inline">
            <label class="mr-2">Filter by Status:</label>
            <select name="status" class="form-control mr-3" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
            
            <label class="mr-2">Filter by Tenant:</label>
            <select name="tenant_id" class="form-control" onchange="this.form.submit()">
                <option value="">All Tenants</option>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?php echo $tenant['id']; ?>" 
                            <?php echo $tenant_filter === (string)$tenant['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tenant['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
    <!-- Tickets Table -->
    <?php if (empty($tickets)): ?>
        <div class="empty-state">
            <p>No support tickets found.</p>
        </div>
    <?php else: ?>
        <div class="ticket-table">
            <table class="table table-hover">
                <thead class="bg-light">
                    <tr>
                        <th>Ticket #</th>
                        <th>Subject</th>
                        <th>Submitted By</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars(substr($ticket['subject'], 0, 40)); ?>...</td>
                            <td>
                                <small><?php echo htmlspecialchars($ticket['submitted_by_name']); ?></small>
                            </td>
                            <td><small><?php echo htmlspecialchars($ticket['category']); ?></small></td>
                            <td>
                                <span class="priority-badge badge-<?php 
                                    echo $ticket['priority'] === 'urgent' ? 'danger' : 
                                    ($ticket['priority'] === 'high' ? 'warning' : 
                                    ($ticket['priority'] === 'medium' ? 'info' : 'success')); 
                                ?>">
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $ticket['status'] === 'open' ? 'primary' : 
                                    ($ticket['status'] === 'in_progress' ? 'warning' : 
                                    ($ticket['status'] === 'resolved' ? 'success' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></small>
                            </td>
                            <td>
                                <a href="view_support_ticket.php?id=<?php echo $ticket['id']; ?>" 
                                   class="btn btn-sm btn-info">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="../js/jquery.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
</body>
</html>
