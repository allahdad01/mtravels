<?php
session_start();

// Security checks
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: ../access_denied.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$user_role = $_SESSION['role'];

// Check role access
if (!in_array($user_role, ['admin', 'finance', 'sales', 'umrah'])) {
    header('Location: ../access_denied.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/SupportTicketManager.php';
require_once '../includes/SLACalculator.php';
require_once '../includes/TicketNotificationService.php';

$ticketManager = new SupportTicketManager($pdo);
$slaCalculator = new SLACalculator($pdo);
$notificationService = new TicketNotificationService($pdo);

$ticket_id = $_GET['id'] ?? 0;
if (!$ticket_id) {
    header('Location: support_tickets.php');
    exit();
}

$ticket = $ticketManager->getTicket($ticket_id);
if (!$ticket || $ticket['tenant_id'] != $tenant_id) {
    header('Location: ../access_denied.php');
    exit();
}

$replies = $ticketManager->getReplies($ticket_id, false);
$error = '';
$success = '';

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reply_text = $_POST['reply'] ?? '';
    
    if (empty($reply_text)) {
        $error = 'Reply cannot be empty';
    } else {
        // Handle screenshot upload
        $screenshot_path = null;
        if (!empty($_FILES['screenshot']['name'])) {
            $upload_dir = '../uploads/support_tickets/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['screenshot'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Only JPG, PNG, and GIF images are allowed';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Image must be less than 5MB';
            } else {
                $filename = 'reply_' . time() . '_' . basename($file['name']);
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $screenshot_path = 'uploads/support_tickets/' . $filename;
                } else {
                    $error = 'Failed to upload screenshot';
                }
            }
        }
        
        if (empty($error)) {
            // Reinitialize manager with services
            $ticketManager = new SupportTicketManager($pdo, $slaCalculator, $notificationService);
            
            $result = $ticketManager->addReply($ticket_id, $user_id, $reply_text, false, $screenshot_path);
            
            if ($result['success']) {
                $success = 'Reply added successfully!';
                // Refresh ticket and replies
                $ticket = $ticketManager->getTicket($ticket_id);
                $replies = $ticketManager->getReplies($ticket_id, false);
            } else {
                $error = $result['error'] ?? 'Failed to add reply';
            }
        }
    }
}

// Handle status update (only if ticket is not closed)
if ($ticket['status'] !== 'closed' && isset($_POST['update_status'])) {
    $new_status = $_POST['update_status'] ?? '';
    if (in_array($new_status, ['open', 'in_progress', 'resolved', 'closed'])) {
        $ticketManager = new SupportTicketManager($pdo, $slaCalculator, $notificationService);
        $result = $ticketManager->updateStatus($ticket_id, $new_status, $user_id);
        
        if ($result['success']) {
            $success = 'Ticket status updated!';
            $ticket = $ticketManager->getTicket($ticket_id);
        } else {
            $error = $result['error'] ?? 'Failed to update status';
        }
    }
}

$sla_display = $slaCalculator->getSLADisplay($ticket);

$pageTitle = "Ticket " . htmlspecialchars($ticket['ticket_number']);
require_once '../includes/header.php';
?>

<style>
/* Enhanced custom styles for better layout and design */
.page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: #ffffff;
    border: none;
    margin-bottom: 20px;
    padding: 20px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 10px;
}

.page-header.card .row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-header.card h5 {
    color: #ffffff;
    margin: 0;
    font-weight: 600;
}

.page-header.card .text-end {
    text-align: right;
}

.page-header.card .btn {
    background: rgba(255,255,255,0.2);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 25px;
    transition: all 0.3s ease;
}

.page-header.card .btn:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

.card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 1rem 1.5rem;
    border: none;
}

.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.progress {
    border-radius: 15px;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.progress-bar {
    transition: width 0.6s ease;
}

.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}

.badge-success {
    background-color: #28a745;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

.badge-info {
    background-color: #17a2b8;
}

.badge-primary {
    background-color: #007bff;
}

.badge-secondary {
    background-color: #6c757d;
}

.badge-danger {
    background-color: #dc3545;
}

.table-responsive {
    border-radius: 10px;

}

.table {
    margin-bottom: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
}

.table tbody tr:hover {
    background-color: #f1f3f4;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

.form-control {
    border-radius: 8px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    padding: 0.75rem;
}

.form-control:focus {
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

.btn-primary {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
}

.btn-secondary {
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-warning {
    background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    color: #212529;
}

.btn-warning:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
}

.btn-info {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-info:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    border: none;
    border-radius: 25px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}

.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}

.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}

.h2 {
    font-size: 2.5rem;
}

.h4 {
    font-size: 1.5rem;
}

.h5 {
    font-size: 1.25rem;
}

.h6 {
    font-size: 1rem;
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
                    <div class="pcoded-inner-content">
                        <div class="main-body">
                            <div class="page-wrapper">
                                <!-- [ Main Content ] start -->
                                <div class="main-content">
                    <div class="page-header card">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0"><i class="feather icon-file-text mr-2"></i><?php echo htmlspecialchars($ticket['ticket_number']); ?></h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="support_tickets.php" class="btn btn-outline-secondary btn-sm">
                                                    <i class="feather icon-arrow-left mr-1"></i> Back to Tickets
                                                </a>
                    </div>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
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
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <?php echo htmlspecialchars($ticket['title']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <small class="text-muted">Status</small>
                                    <div>
                                        <span class="badge badge-<?php
                                                                                     echo $ticket['status'] === 'open' ? 'primary' :
                                                                                         ($ticket['status'] === 'in_progress' ? 'warning' :
                                                                                         ($ticket['status'] === 'resolved' ? 'success' : 'secondary'));
                                                                                 ?>">
                                                                                 <?php echo ucwords(str_replace('_', ' ', $ticket['status'])); ?>
                                                                                 </span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Priority</small>
                                    <div>
                                        <span class="badge badge-<?php
                                                                                     echo $ticket['priority'] === 'critical' ? 'danger' :
                                                                                         ($ticket['priority'] === 'high' ? 'warning' : 'info');
                                                                                 ?>">
                                                                                 <?php echo ucfirst($ticket['priority']); ?>
                                                                                 </span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Category</small>
                                    <div>
                                        <span class="badge badge-info">
                                                                                     <?php echo htmlspecialchars($ticket['category_name']); ?>
                                                                                 </span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">SLA Status</small>
                                    <div>
                                        <span class="badge badge-<?php echo $sla_display['color']; ?>">
                                                                                     <?php echo $sla_display['status']; ?>
                                                                                 </span>
                                    </div>
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

                    <!-- Conversation -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-comments"></i> Conversation (<?php echo count($replies); ?>)
                            </h5>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($replies)): ?>
                                <p class="text-muted text-center py-4">No replies yet</p>
                            <?php else: ?>
                                <?php foreach ($replies as $reply): ?>
                                    <div class="mb-3 p-3" style="background-color: #f8f9fa; border-radius: 5px;">
                                        <div class="d-flex justify-content-between mb-2">
                                            <strong><?php echo htmlspecialchars($reply['replied_by_name']); ?></strong>
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

                    <!-- Reply Form -->
                    <?php if ($ticket['status'] !== 'closed'): ?>
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
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <?php if ($ticket['status'] !== 'closed'): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Update Status</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="d-grid gap-2">
                                        <?php 
                                        $status_options = [
                                            'open' => ['label' => 'Open', 'color' => 'primary'],
                                            'in_progress' => ['label' => 'In Progress', 'color' => 'warning'],
                                            'resolved' => ['label' => 'Resolved', 'color' => 'success'],
                                            'closed' => ['label' => 'Closed', 'color' => 'secondary']
                                        ];
                                        
                                        foreach ($status_options as $status => $options):
                                            if ($status !== $ticket['status']):
                                        ?>
                                            <button 
                                                type="submit" 
                                                name="update_status" 
                                                value="<?php echo $status; ?>" 
                                                class="btn btn-<?php echo $options['color']; ?> btn-sm"
                                            >
                                                <?php echo $options['label']; ?>
                                            </button>
                                        <?php endif; endforeach; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Ticket Information -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Ticket Information</h5>
                        </div>
                        <div class="card-body small">
                            <dl class="row">
                                <dt class="col-sm-5">Ticket #:</dt>
                                <dd class="col-sm-7"><strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong></dd>

                                <dt class="col-sm-5">Created by:</dt>
                                <dd class="col-sm-7"><?php echo htmlspecialchars($ticket['created_by_name']); ?></dd>

                                <dt class="col-sm-5">Created:</dt>
                                <dd class="col-sm-7"><?php echo date('M d, Y H:i', strtotime($ticket['created_at'])); ?></dd>

                                <dt class="col-sm-5">SLA Due:</dt>
                                <dd class="col-sm-7"><?php echo date('M d, Y H:i', strtotime($ticket['sla_due_at'])); ?></dd>

                                <dt class="col-sm-5">Time Left:</dt>
                                <dd class="col-sm-7">
                                    <?php echo $sla_display['hours_remaining'] . ' hours'; ?>
                                </dd>

                                <dt class="col-sm-5">Progress:</dt>
                                <dd class="col-sm-7">
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-<?php echo $sla_display['color']; ?>" style="width: <?php echo $sla_display['percentage']; ?>%"></div>
                                    </div>
                                    <?php echo round($sla_display['percentage'], 1); ?>%
                                </dd>
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
