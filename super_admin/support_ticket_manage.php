<?php
session_start();

// Security checks
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../access_denied.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';
require_once '../includes/SLACalculator.php';
require_once '../includes/TicketNotificationService.php';
require_once 'includes/file_upload_security.php';

$ticketManager = new SupportTicketManager($pdo);
$slaCalculator = new SLACalculator($pdo);
$notificationService = new TicketNotificationService($pdo);

$ticket_id = $_GET['id'] ?? 0;
if (!$ticket_id) {
    header('Location: support_tickets_manage.php');
    exit();
}

$ticket = $ticketManager->getTicket($ticket_id);
if (!$ticket) {
    header('Location: ../access_denied.php');
    exit();
}

// Fetch tenant name
try {
    $tenantStmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
    $tenantStmt->execute([$ticket['tenant_id']]);
    $tenantResult = $tenantStmt->fetch(PDO::FETCH_ASSOC);
    if ($tenantResult) {
        $ticket['tenant_name'] = $tenantResult['name'];
    }
} catch (Exception $e) {
    $ticket['tenant_name'] = 'Unknown';
}

$replies = $ticketManager->getReplies($ticket_id, true); // Include internal notes for super admin
$error = '';
$success = '';

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply_text = $_POST['reply'] ?? '';
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    
    if (empty($reply_text)) {
        $error = 'Reply cannot be empty';
    } else {
        // Handle screenshot upload with MIME validation
        $screenshot_path = null;
        if (!empty($_FILES['screenshot']['name']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/support_tickets/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Validate file using FileUploadSecurity (not $_FILES['type'] which is user-controlled)
            $validation = FileUploadSecurity::validateUpload($_FILES['screenshot'], 'image', 5242880);
            
            if (!$validation['success']) {
                $error = $validation['error'];
            } else {
                // Move file using secure method
                $moveResult = FileUploadSecurity::moveUploadedFile(
                    $_FILES['screenshot']['tmp_name'],
                    $upload_dir,
                    $validation['safe_name']
                );
                
                if ($moveResult['success']) {
                    $screenshot_path = 'uploads/support_tickets/' . $validation['safe_name'];
                } else {
                    $error = $moveResult['error'];
                }
            }
        }
        
        if (empty($error)) {
            $ticketManager = new SupportTicketManager($pdo, $slaCalculator, $notificationService);
            $result = $ticketManager->addReply($ticket_id, $_SESSION['user_id'], $reply_text, $is_internal, $screenshot_path);
            
            if ($result['success']) {
                $success = 'Reply added successfully!';
                $ticket = $ticketManager->getTicket($ticket_id);
                $replies = $ticketManager->getReplies($ticket_id, true);
            } else {
                $error = $result['error'] ?? 'Failed to add reply';
            }
        }
    }
}

// Handle status update
if (isset($_POST['update_status'])) {
    $new_status = $_POST['update_status'] ?? '';
    if (in_array($new_status, ['open', 'in_progress', 'resolved', 'closed'])) {
        $ticketManager = new SupportTicketManager($pdo, $slaCalculator, $notificationService);
        $result = $ticketManager->updateStatus($ticket_id, $new_status, $_SESSION['user_id']);
        
        if ($result['success']) {
            $success = 'Ticket status updated!';
            $ticket = $ticketManager->getTicket($ticket_id);
        } else {
            $error = $result['error'] ?? 'Failed to update status';
        }
    }
}

$sla_display = $slaCalculator->getSLADisplay($ticket);

$pageTitle = "Manage Ticket " . htmlspecialchars($ticket['ticket_number']);
require_once '../includes/header_super_admin.php';
?>

<style>
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #ffffff;
    border: none;
    margin-bottom: 20px;
    padding: 20px !important;
}

.page-header.card .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header.card h5 {
    color: #ffffff;
    margin: 0;
}

.page-header.card .text-end {
    text-align: right;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="page-header card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0"><?php echo htmlspecialchars($ticket['ticket_number']); ?></h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="support_tickets_manage.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <!-- Ticket Details -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($ticket['title']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <small class="text-muted">Status</small>
                                    <div>
                                        <span class="badge bg-<?php 
                                            echo $ticket['status'] === 'open' ? 'primary' : 
                                                ($ticket['status'] === 'in_progress' ? 'warning' : 
                                                ($ticket['status'] === 'resolved' ? 'success' : 'secondary'));
                                        ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <small class="text-muted">Priority</small>
                                    <div>
                                        <span class="badge bg-<?php 
                                            echo $ticket['priority'] === 'critical' ? 'danger' : 
                                                ($ticket['priority'] === 'high' ? 'warning' : 'info');
                                        ?>">
                                            <?php echo ucfirst($ticket['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">SLA Status</small>
                                    <div>
                                        <span class="badge bg-<?php echo $sla_display['color']; ?>">
                                            <?php echo $sla_display['status']; ?>
                                        </span>
                                        <span class="text-muted small">(<?php echo $sla_display['hours_remaining']; ?>h left)</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Created by</small>
                                    <div><?php echo htmlspecialchars($ticket['created_by_name']); ?></div>
                                </div>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <h6>Description</h6>
                                <p><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                            </div>

                            <?php if ($ticket['screenshot_path']): ?>
                                <div class="mb-3">
                                    <h6>Screenshot</h6>
                                    <img src="../<?php echo htmlspecialchars($ticket['screenshot_path']); ?>" class="img-fluid" style="max-width: 100%; max-height: 300px;">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Conversation with Internal Notes Highlighted -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-comments"></i> Full Conversation (<?php echo count($replies); ?>)
                            </h5>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($replies)): ?>
                                <p class="text-muted text-center py-4">No replies yet</p>
                            <?php else: ?>
                                <?php foreach ($replies as $reply): ?>
                                    <div class="mb-3 p-3" style="background-color: <?php echo $reply['is_internal_note'] ? '#fff3cd' : '#f8f9fa'; ?>; border-radius: 5px; border-left: 4px solid <?php echo $reply['is_internal_note'] ? '#ff9800' : '#e0e0e0'; ?>;">
                                        <div class="d-flex justify-content-between mb-2">
                                            <strong>
                                                <?php echo htmlspecialchars($reply['replied_by_name']); ?>
                                                <?php if ($reply['is_internal_note']): ?>
                                                    <span class="badge bg-warning">Internal Note</span>
                                                <?php endif; ?>
                                            </strong>
                                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?></p>
                                        <?php if ($reply['screenshot_path']): ?>
                                            <div class="mt-2">
                                                <img src="../<?php echo htmlspecialchars($reply['screenshot_path']); ?>" class="img-fluid" style="max-width: 200px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Reply Form for Super Admin -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Add Reply</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <textarea 
                                        class="form-control" 
                                        name="reply" 
                                        rows="4" 
                                        placeholder="Type your reply here..."
                                        required
                                    ></textarea>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_internal" name="is_internal">
                                        <label class="form-check-label" for="is_internal">
                                            <strong>Mark as Internal Note</strong> (visible only to support team)
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Attach Screenshot</label>
                                    <input 
                                        type="file" 
                                        class="form-control" 
                                        name="screenshot" 
                                        accept="image/*"
                                    >
                                    <small class="text-muted">JPG, PNG, or GIF (max 5MB)</small>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send Reply
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Sidebar for Super Admin Actions -->
                <div class="col-lg-4">
                    <!-- Status Control -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Update Status</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="d-grid gap-2">
                                    <?php 
                                    $status_options = [
                                        'open' => 'Open',
                                        'in_progress' => 'In Progress',
                                        'resolved' => 'Resolved',
                                        'closed' => 'Closed'
                                    ];
                                    
                                    foreach ($status_options as $status => $label):
                                    ?>
                                        <button 
                                            type="submit" 
                                            name="update_status" 
                                            value="<?php echo $status; ?>"
                                            class="btn btn-outline-secondary btn-sm <?php echo $ticket['status'] === $status ? 'active' : ''; ?>"
                                        >
                                            <?php echo $label; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Ticket Metadata -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Ticket Details</h5>
                        </div>
                        <div class="card-body small">
                            <dl class="row">
                                <dt class="col-sm-5">Ticket #:</dt>
                                <dd class="col-sm-7"><strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong></dd>

                                <dt class="col-sm-5">Tenant:</dt>
                                <dd class="col-sm-7">
                                    <a href="edit_tenant.php?id=<?php echo $ticket['tenant_id']; ?>">
                                        <?php echo htmlspecialchars($ticket['tenant_name'] ?? 'Unknown'); ?>
                                    </a>
                                </dd>

                                <dt class="col-sm-5">Created:</dt>
                                <dd class="col-sm-7"><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></dd>

                                <dt class="col-sm-5">SLA Due:</dt>
                                <dd class="col-sm-7"><?php echo date('M d, Y H:i', strtotime($ticket['sla_due_at'])); ?></dd>

                                <dt class="col-sm-5">Progress:</dt>
                                <dd class="col-sm-7">
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-<?php echo $sla_display['color']; ?>" style="width: <?php echo $sla_display['percentage']; ?>%"></div>
                                    </div>
                                    <?php echo round($sla_display['percentage'], 1); ?>%
                                </dd>

                                <?php if ($ticket['first_response_at']): ?>
                                    <dt class="col-sm-5">First Response:</dt>
                                    <dd class="col-sm-7"><?php echo date('M d, H:i', strtotime($ticket['first_response_at'])); ?></dd>
                                <?php endif; ?>

                                <?php if ($ticket['resolved_at']): ?>
                                    <dt class="col-sm-5">Resolved:</dt>
                                    <dd class="col-sm-7"><?php echo date('M d, H:i', strtotime($ticket['resolved_at'])); ?></dd>
                                    <dt class="col-sm-5">Resolved by:</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($ticket['resolved_by_name'] ?? 'N/A'); ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php require_once '../includes/admin_footer.php'; ?>
