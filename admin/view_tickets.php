<?php
session_start();
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/SupportTicketManager.php';

// Check if user is admin or tenant super admin
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['super_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

$ticketManager = new SupportTicketManager($pdo);

// Determine user type and ID
$user_id = $_SESSION['admin_id'] ?? $_SESSION['super_admin_id'];
$user_type = isset($_SESSION['admin_id']) ? 'admin' : 'tenant_super_admin';
$tenant_id = $_SESSION['tenant_id'] ?? 0;

// Get tickets submitted by this user
$tickets = $ticketManager->getTicketsByUser($tenant_id, $user_id, $user_type);

$pageTitle = 'My Support Tickets';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .tickets-container {
            margin: 30px auto;
            max-width: 1000px;
        }
        .ticket-card {
            border-left: 4px solid #007bff;
            margin-bottom: 15px;
            transition: box-shadow 0.3s;
        }
        .ticket-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .ticket-card.urgent {
            border-left-color: #dc3545;
        }
        .ticket-card.high {
            border-left-color: #fd7e14;
        }
        .ticket-card.medium {
            border-left-color: #ffc107;
        }
        .ticket-card.low {
            border-left-color: #28a745;
        }
        .status-badge {
            font-size: 12px;
            padding: 5px 10px;
        }
        .ticket-number {
            font-weight: bold;
            color: #007bff;
        }
        .btn-action {
            padding: 5px 10px;
            font-size: 12px;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
<div class="container tickets-container">
    <div class="header-section">
        <h2><?php echo $pageTitle; ?></h2>
        <a href="submit_ticket.php" class="btn btn-primary">Create New Ticket</a>
    </div>
    
    <?php if (empty($tickets)): ?>
        <div class="empty-state">
            <p>No support tickets found.</p>
            <a href="submit_ticket.php" class="btn btn-primary">Create your first ticket</a>
        </div>
    <?php else: ?>
        <div class="tickets-list">
            <?php foreach ($tickets as $ticket): ?>
                <div class="card ticket-card <?php echo $ticket['priority']; ?>">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <span class="ticket-number"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                <h5 class="card-title mt-2"><?php echo htmlspecialchars($ticket['subject']); ?></h5>
                                <p class="card-text text-muted mb-1">
                                    <small><?php echo htmlspecialchars($ticket['category']); ?> | 
                                    Created: <?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></small>
                                </p>
                            </div>
                            <div class="col-md-4 text-right">
                                <span class="badge badge-info status-badge">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                                <span class="badge badge-secondary status-badge">
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                                <div class="mt-2">
                                    <a href="view_ticket_details.php?id=<?php echo $ticket['id']; ?>" 
                                       class="btn btn-sm btn-info btn-action">View</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="../js/jquery.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
</body>
</html>
