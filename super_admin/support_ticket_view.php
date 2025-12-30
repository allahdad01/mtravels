<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to support_ticket_view.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';

$ticketManager = new SupportTicketManager($pdo);
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? $_SESSION['email'];

// Get ticket ID
$ticket_id = intval($_GET['id'] ?? 0);
if ($ticket_id <= 0) {
    header('Location: support_tickets_list.php');
    exit();
}

$ticket = $ticketManager->getTicketDetails($ticket_id);

if (!$ticket) {
    header('Location: support_tickets_list.php');
    exit();
}

$replies = $ticketManager->getTicketReplies($ticket_id);
$message = '';
$alert_type = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $status = trim($_POST['status'] ?? '');
        $resolution_notes = !empty($_POST['resolution_notes']) ? trim($_POST['resolution_notes']) : null;
        
        if (in_array($status, ['open', 'in_progress', 'resolved', 'closed'])) {
            if ($ticketManager->updateTicketStatus($ticket_id, $status, $user_id, $resolution_notes)) {
                $message = 'Ticket status updated successfully';
                $alert_type = 'success';
                $ticket = $ticketManager->getTicketDetails($ticket_id);
            } else {
                $message = 'Failed to update ticket status';
                $alert_type = 'danger';
            }
        }
    } 
    elseif ($action === 'update_priority') {
        $priority = trim($_POST['priority'] ?? '');
        
        if (in_array($priority, ['low', 'medium', 'high', 'urgent'])) {
            if ($ticketManager->updateTicketPriority($ticket_id, $priority)) {
                $message = 'Ticket priority updated successfully';
                $alert_type = 'success';
                $ticket = $ticketManager->getTicketDetails($ticket_id);
            } else {
                $message = 'Failed to update priority';
                $alert_type = 'danger';
            }
        }
    }
    elseif ($action === 'add_reply') {
        $reply_text = trim($_POST['reply_text'] ?? '');
        
        if (empty($reply_text)) {
            $message = 'Reply cannot be empty';
            $alert_type = 'danger';
        } else {
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
                $_POST['reply_text'] = '';
            } else {
                $message = 'Failed to add reply: ' . ($result['error'] ?? '');
                $alert_type = 'danger';
            }
        }
    }
}

include '../includes/header_super_admin.php';
?>

<style>
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
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
    font-size: 11px;
    text-transform: uppercase;
    display: block;
    margin-bottom: 5px;
}

.info-item p {
    margin: 0;
    color: #333;
}

.reply-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    border-left: 4px solid #4099ff;
}

.reply-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.reply-author {
    font-weight: 600;
    color: #007bff;
}

.reply-type-badge {
    font-size: 10px;
    padding: 3px 8px;
    margin-left: 8px;
}

.reply-text {
    color: #333;
    line-height: 1.6;
}

.reply-time {
    font-size: 12px;
    color: #999;
}

.screenshot {
    max-width: 400px;
    margin: 15px 0;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 10px;
    background: #f9f9f9;
}

.screenshot img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
}

.description-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.textarea-resize {
    resize: vertical;
    min-height: 100px;
}

.status-badge-lg {
    font-size: 12px;
    padding: 6px 12px;
    margin-right: 8px;
    margin-bottom: 8px;
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
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Ticket Details: <?php echo htmlspecialchars($ticket['ticket_number']); ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="support_tickets_list.php">Support Tickets</a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?php echo htmlspecialchars($ticket['ticket_number']); ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $alert_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                <?php echo $message; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5><?php echo htmlspecialchars($ticket['subject']); ?></h5>
                        <div class="mt-2">
                            <span class="badge badge-info status-badge-lg"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                            <span class="badge badge-<?php 
                                echo $ticket['status'] === 'open' ? 'primary' : 
                                ($ticket['status'] === 'in_progress' ? 'warning' : 
                                ($ticket['status'] === 'resolved' ? 'success' : 'secondary')); 
                            ?> status-badge-lg">
                                <?php echo ucfirst($ticket['status']); ?>
                            </span>
                            <span class="badge badge-<?php 
                                echo $ticket['priority'] === 'urgent' ? 'danger' : 
                                ($ticket['priority'] === 'high' ? 'warning' : 
                                ($ticket['priority'] === 'medium' ? 'info' : 'success')); 
                            ?> status-badge-lg">
                                <?php echo ucfirst($ticket['priority']); ?> Priority
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Ticket Information</h6>
                        <div class="info-row">
                            <div class="info-item">
                                <label>Category</label>
                                <p><?php echo htmlspecialchars($ticket['category']); ?></p>
                            </div>
                            <div class="info-item">
                                <label>Submitted By</label>
                                <p><?php echo htmlspecialchars($ticket['submitted_by_name']); ?></p>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-item">
                                <label>Email</label>
                                <p>
                                    <a href="mailto:<?php echo htmlspecialchars($ticket['submitted_by_email']); ?>">
                                        <?php echo htmlspecialchars($ticket['submitted_by_email']); ?>
                                    </a>
                                </p>
                            </div>
                            <div class="info-item">
                                <label>Type</label>
                                <p><?php echo ucfirst(str_replace('_', ' ', $ticket['submitted_by_type'])); ?></p>
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

                        <h6 class="mt-4 mb-3">Description</h6>
                        <div class="description-section">
                            <?php echo nl2br(htmlspecialchars($ticket['description'])); ?>
                        </div>

                        <?php if ($ticket['screenshot_path']): ?>
                            <h6 class="mt-4 mb-3">Attached Screenshot</h6>
                            <div class="screenshot">
                                <img src="../<?php echo htmlspecialchars($ticket['screenshot_path']); ?>" alt="Screenshot">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Updates & Replies</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($replies)): ?>
                            <p class="text-muted text-center py-4">No replies yet.</p>
                        <?php else: ?>
                            <div class="replies-container">
                                <?php foreach ($replies as $reply): ?>
                                    <div class="reply-item">
                                        <div class="reply-header">
                                            <div>
                                                <span class="reply-author"><?php echo htmlspecialchars($reply['replied_by_name']); ?></span>
                                                <span class="badge badge-secondary reply-type-badge">
                                                    <?php echo ucfirst(str_replace('_', ' ', $reply['replied_by_type'])); ?>
                                                </span>
                                            </div>
                                            <span class="reply-time"><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></span>
                                        </div>
                                        <div class="reply-text">
                                            <?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 pt-3 border-top">
                            <h6 class="mb-3">Add Reply</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_reply">
                                <div class="form-group">
                                    <textarea class="form-control textarea-resize" name="reply_text" 
                                              placeholder="Type your response..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-send mr-2"></i>Send Reply
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Status Control -->
                <div class="card">
                    <div class="card-header">
                        <h5>Status Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="control-section">
                            <div class="control-label">Update Status</div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <div class="form-group">
                                    <select name="status" class="form-control form-control-sm" required>
                                        <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <textarea class="form-control form-control-sm" name="resolution_notes" 
                                              rows="2" placeholder="Resolution notes (optional)"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                    <i class="fas fa-save mr-2"></i>Update Status
                                </button>
                            </form>
                        </div>

                        <div class="control-section">
                            <div class="control-label">Update Priority</div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_priority">
                                <div class="form-group">
                                    <select name="priority" class="form-control form-control-sm" required>
                                        <option value="low" <?php echo $ticket['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $ticket['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $ticket['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                        <option value="urgent" <?php echo $ticket['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-warning btn-sm btn-block">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>Update Priority
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5>Ticket Meta</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted mb-2" style="font-size: 11px; text-transform: uppercase;">Current Status</label>
                            <p class="mb-0">
                                <span class="badge badge-<?php 
                                    echo $ticket['status'] === 'open' ? 'primary' : 
                                    ($ticket['status'] === 'in_progress' ? 'warning' : 
                                    ($ticket['status'] === 'resolved' ? 'success' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted mb-2" style="font-size: 11px; text-transform: uppercase;">Priority Level</label>
                            <p class="mb-0">
                                <span class="badge badge-<?php 
                                    echo $ticket['priority'] === 'urgent' ? 'danger' : 
                                    ($ticket['priority'] === 'high' ? 'warning' : 
                                    ($ticket['priority'] === 'medium' ? 'info' : 'success')); 
                                ?>">
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <a href="support_tickets_list.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Tickets
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
