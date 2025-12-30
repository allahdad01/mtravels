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
$user_id = $_SESSION['admin_id'] ?? $_SESSION['super_admin_id'];
$user_type = isset($_SESSION['admin_id']) ? 'admin' : 'tenant_super_admin';
$user_name = $_SESSION['name'] ?? $_SESSION['email'];

// Get ticket ID
$ticket_id = intval($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    header('Location: view_tickets.php');
    exit;
}

$ticket = $ticketManager->getTicketDetails($ticket_id);

// Verify ownership or admin access
if (!$ticket || ($ticket['submitted_by_id'] != $user_id && $user_type !== 'admin')) {
    header('Location: view_tickets.php');
    exit;
}

$replies = $ticketManager->getTicketReplies($ticket_id);
$message = '';
$alert_type = '';

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['reply_text'])) {
    $reply_text = sanitize_input($_POST['reply_text']);
    
    $replyData = [
        'replied_by_type' => $user_type,
        'replied_by_id' => $user_id,
        'replied_by_name' => $user_name,
        'reply_text' => $reply_text
    ];
    
    $result = $ticketManager->addReply($ticket_id, $replyData);
    
    if ($result['success']) {
        $message = 'Reply added successfully';
        $alert_type = 'success';
        // Refresh ticket and replies
        $ticket = $ticketManager->getTicketDetails($ticket_id);
        $replies = $ticketManager->getTicketReplies($ticket_id);
    } else {
        $message = 'Failed to add reply';
        $alert_type = 'danger';
    }
}

$pageTitle = 'Ticket Details - ' . htmlspecialchars($ticket['ticket_number']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .details-container {
            margin: 30px auto;
            max-width: 900px;
        }
        .ticket-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .ticket-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 15px 0;
        }
        .info-item label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }
        .info-item p {
            margin: 5px 0 0 0;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            margin-right: 10px;
        }
        .description-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .replies-section {
            margin-top: 30px;
            border-top: 2px solid #eee;
            padding-top: 20px;
        }
        .reply-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .reply-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .reply-author {
            font-weight: 600;
            color: #007bff;
        }
        .reply-time {
            font-size: 12px;
            color: #999;
        }
        .reply-text {
            color: #333;
            line-height: 1.6;
        }
        .screenshot {
            max-width: 300px;
            margin: 15px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            background: #f9f9f9;
        }
        .screenshot img {
            max-width: 100%;
            height: auto;
        }
        .reply-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container details-container">
    <a href="view_tickets.php" class="btn btn-secondary mb-3">← Back to Tickets</a>
    
    <div class="ticket-header">
        <h3><?php echo htmlspecialchars($ticket['subject']); ?></h3>
        <div style="margin-top: 10px;">
            <span class="badge badge-info"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
            <span class="badge badge-<?php 
                echo $ticket['status'] === 'open' ? 'primary' : 
                ($ticket['status'] === 'in_progress' ? 'warning' : 
                ($ticket['status'] === 'resolved' ? 'success' : 'secondary')); 
            ?>">
                <?php echo ucfirst($ticket['status']); ?>
            </span>
            <span class="badge badge-<?php 
                echo $ticket['priority'] === 'urgent' ? 'danger' : 
                ($ticket['priority'] === 'high' ? 'warning' : 
                ($ticket['priority'] === 'medium' ? 'info' : 'success')); 
            ?>">
                <?php echo ucfirst($ticket['priority']); ?> Priority
            </span>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $alert_type; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="ticket-info">
        <div class="info-item">
            <label>Submitted By</label>
            <p><?php echo htmlspecialchars($ticket['submitted_by_name']); ?></p>
        </div>
        <div class="info-item">
            <label>Email</label>
            <p><?php echo htmlspecialchars($ticket['submitted_by_email']); ?></p>
        </div>
        <div class="info-item">
            <label>Category</label>
            <p><?php echo htmlspecialchars($ticket['category']); ?></p>
        </div>
        <div class="info-item">
            <label>Created</label>
            <p><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></p>
        </div>
    </div>
    
    <div class="description-box">
        <h5>Description</h5>
        <p><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
        
        <?php if ($ticket['screenshot_path']): ?>
            <div class="screenshot">
                <p><strong>Screenshot:</strong></p>
                <img src="../<?php echo htmlspecialchars($ticket['screenshot_path']); ?>" alt="Screenshot">
            </div>
        <?php endif; ?>
    </div>
    
    <div class="replies-section">
        <h4>Updates & Replies</h4>
        
        <?php if (empty($replies)): ?>
            <p class="text-muted">No replies yet.</p>
        <?php else: ?>
            <?php foreach ($replies as $reply): ?>
                <div class="reply-item">
                    <div class="reply-header">
                        <span class="reply-author"><?php echo htmlspecialchars($reply['replied_by_name']); ?></span>
                        <span class="reply-time"><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></span>
                    </div>
                    <div class="reply-text">
                        <?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="reply-form">
            <h5>Add Reply</h5>
            <form method="POST">
                <div class="form-group">
                    <textarea class="form-control" name="reply_text" rows="4" 
                              placeholder="Add your reply..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit Reply</button>
            </form>
        </div>
    </div>
</div>

<script src="../js/jquery.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
</body>
</html>
