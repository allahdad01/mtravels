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
$user_id = $_SESSION['super_admin_id'];
$user_name = $_SESSION['name'] ?? $_SESSION['email'];

// Get ticket ID
$ticket_id = intval($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    header('Location: support_tickets.php');
    exit;
}

$ticket = $ticketManager->getTicketDetails($ticket_id);

if (!$ticket) {
    header('Location: support_tickets.php');
    exit;
}

$replies = $ticketManager->getTicketReplies($ticket_id);
$message = '';
$alert_type = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $status = sanitize_input($_POST['status']);
        $resolution_notes = !empty($_POST['resolution_notes']) ? sanitize_input($_POST['resolution_notes']) : null;
        
        if ($ticketManager->updateTicketStatus($ticket_id, $status, $user_id, $resolution_notes)) {
            $message = 'Ticket status updated successfully';
            $alert_type = 'success';
            $ticket = $ticketManager->getTicketDetails($ticket_id);
        } else {
            $message = 'Failed to update ticket status';
            $alert_type = 'danger';
        }
    } 
    elseif ($action === 'update_priority') {
        $priority = sanitize_input($_POST['priority']);
        
        if ($ticketManager->updateTicketPriority($ticket_id, $priority)) {
            $message = 'Ticket priority updated successfully';
            $alert_type = 'success';
            $ticket = $ticketManager->getTicketDetails($ticket_id);
        } else {
            $message = 'Failed to update priority';
            $alert_type = 'danger';
        }
    }
    elseif ($action === 'add_reply') {
        $reply_text = sanitize_input($_POST['reply_text']);
        
        $replyData = [
            'replied_by_type' => 'super_admin',
            'replied_by_id' => $user_id,
            'replied_by_name' => $user_name,
            'reply_text' => $reply_text
        ];
        
        $result = $ticketManager->addReply($ticket_id, $replyData);
        
        if ($result['success']) {
            $message = 'Reply added successfully';
            $alert_type = 'success';
            $replies = $ticketManager->getTicketReplies($ticket_id);
        } else {
            $message = 'Failed to add reply';
            $alert_type = 'danger';
        }
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
        .container-main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .ticket-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .row-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .description-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .control-panel {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: fit-content;
        }
        .control-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .control-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .control-label {
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        .replies-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
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
        .reply-type-badge {
            font-size: 10px;
            padding: 2px 6px;
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
        .alert {
            margin-bottom: 20px;
        }
        .info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }
        .info-item label {
            font-weight: 600;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 5px;
        }
        .info-item p {
            color: #333;
            margin: 0;
        }
    </style>
</head>
<body>
<div class="container-main">
    <a href="support_tickets.php" class="btn btn-secondary mb-3">← Back to Tickets</a>
    
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
    
    <div class="row-section">
        <div>
            <div class="description-box">
                <h5>Ticket Information</h5>
                <div class="info-row">
                    <div class="info-item">
                        <label>Submitted By</label>
                        <p><?php echo htmlspecialchars($ticket['submitted_by_name']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <p><?php echo htmlspecialchars($ticket['submitted_by_email']); ?></p>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-item">
                        <label>Category</label>
                        <p><?php echo htmlspecialchars($ticket['category']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Type</label>
                        <p><?php echo ucfirst($ticket['submitted_by_type']); ?></p>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-item">
                        <label>Created</label>
                        <p><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Last Updated</label>
                        <p><?php echo date('M d, Y H:i', strtotime($ticket['updated_at'])); ?></p>
                    </div>
                </div>
                
                <h5 style="margin-top: 20px;">Description</h5>
                <p><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                
                <?php if ($ticket['screenshot_path']): ?>
                    <div class="screenshot">
                        <p><strong>Screenshot:</strong></p>
                        <img src="../<?php echo htmlspecialchars($ticket['screenshot_path']); ?>" alt="Screenshot">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Control Panel -->
        <div class="control-panel">
            <h5>Controls</h5>
            
            <!-- Status Update -->
            <div class="control-section">
                <div class="control-label">Update Status</div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <select name="status" class="form-control form-control-sm mb-2">
                        <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                    <textarea class="form-control form-control-sm mb-2" name="resolution_notes" 
                              rows="2" placeholder="Resolution notes (optional)"></textarea>
                    <button type="submit" class="btn btn-sm btn-primary btn-block">Update Status</button>
                </form>
            </div>
            
            <!-- Priority Update -->
            <div class="control-section">
                <div class="control-label">Update Priority</div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_priority">
                    <select name="priority" class="form-control form-control-sm mb-2">
                        <option value="low" <?php echo $ticket['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $ticket['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $ticket['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="urgent" <?php echo $ticket['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-warning btn-block">Update Priority</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Replies Section -->
    <div class="replies-section">
        <h5>Updates & Replies</h5>
        
        <?php if (empty($replies)): ?>
            <p class="text-muted">No replies yet.</p>
        <?php else: ?>
            <?php foreach ($replies as $reply): ?>
                <div class="reply-item">
                    <div class="reply-header">
                        <div>
                            <span class="reply-author"><?php echo htmlspecialchars($reply['replied_by_name']); ?></span>
                            <span class="badge badge-secondary reply-type-badge"><?php echo ucfirst($reply['replied_by_type']); ?></span>
                        </div>
                        <span class="text-muted" style="font-size: 12px;"><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></span>
                    </div>
                    <div>
                        <?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
            <h6>Add Reply</h6>
            <form method="POST">
                <input type="hidden" name="action" value="add_reply">
                <div class="form-group">
                    <textarea class="form-control" name="reply_text" rows="4" 
                              placeholder="Type your response..." required></textarea>
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
