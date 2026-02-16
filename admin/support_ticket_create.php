<?php
session_start();

// Security checks
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header('Location: ../access_denied.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'] ?? 0;
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
require_once '../includes/SecureFileUpload.php';

$ticketManager = new SupportTicketManager($pdo);
$slaCalculator = new SLACalculator($pdo);
$notificationService = new TicketNotificationService($pdo);

// Reinitialize manager with services
$ticketManager = new SupportTicketManager($pdo, $slaCalculator, $notificationService);

$categories = $ticketManager->getCategories();
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    
    if (empty($title) || empty($description) || empty($category_id)) {
        $error = 'Please fill in all required fields';
    } elseif (!in_array($priority, ['low', 'medium', 'high', 'critical'])) {
        $error = 'Invalid priority selected';
    } else {
        // Handle screenshot upload - SECURE VERSION
         $screenshot_path = null;
         if (isset($_FILES['screenshot'])) {
             $uploader = new SecureFileUpload(
                 5 * 1024 * 1024, // 5MB max size
                 '../uploads/'
             );
             
             $result = $uploader->upload('screenshot', 'support_tickets');
             
             if ($result['success']) {
                 $screenshot_path = 'uploads/support_tickets/' . $result['data']['filename'];
                 // Optionally log the upload
                 error_log("Support ticket screenshot uploaded: {$result['data']['filename']} by user {$_SESSION['user_id']}");
             } else {
                 $error = "File upload failed: " . $result['error'];
             }
         }
        
        if (empty($error)) {
            // Create ticket
            try {
                $result = $ticketManager->createTicket([
                    'tenant_id' => $tenant_id,
                    'branch_id' => $branch_id,
                    'created_by_user_id' => $user_id,
                    'created_by_role' => $user_role,
                    'category_id' => intval($category_id),
                    'title' => $title,
                    'description' => $description,
                    'priority' => $priority,
                    'screenshot_path' => $screenshot_path
                ]);
                
                if ($result['success']) {
                    $success = true;
                    $ticket_id = $result['ticket_id'];
                } else {
                    $error = $result['error'] ?? 'Failed to create ticket';
                }
            } catch (Exception $e) {
                $error = 'Error creating ticket: ' . $e->getMessage();
                error_log("Support Ticket Creation Error: " . $e->getMessage());
            }
        }
    }
}

$pageTitle = "Create Support Ticket";
require_once '../includes/header.php';
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
                        <h5 class="mb-0">Create Support Ticket</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <a href="support_tickets.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to Tickets
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <h4 class="alert-heading">Success!</h4>
                    <p>Your support ticket has been created successfully.</p>
                    <strong>Ticket Number:</strong> 
                    <a href="support_ticket_detail.php?id=<?php echo $ticket_id; ?>">
                        Click here to view your ticket
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">New Ticket</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label class="form-label" for="title">Title <span class="text-danger">*</span></label>
                                    <input 
                                        type="text" 
                                        class="form-control" 
                                        id="title" 
                                        name="title" 
                                        placeholder="Brief description of the issue"
                                        required
                                    >
                                    <small class="text-muted">Maximum 255 characters</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
                                    <textarea 
                                        class="form-control" 
                                        id="description" 
                                        name="description" 
                                        rows="6" 
                                        placeholder="Provide detailed information about your issue"
                                        required
                                    ></textarea>
                                    <small class="text-muted">Include steps to reproduce, error messages, etc.</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="category">Category <span class="text-danger">*</span></label>
                                        <select class="form-control" id="category" name="category_id" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>">
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="priority">Priority <span class="text-danger">*</span></label>
                                        <select class="form-control" id="priority" name="priority" required>
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                            <option value="critical">Critical</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="screenshot">Screenshot</label>
                                    <input 
                                        type="file" 
                                        class="form-control" 
                                        id="screenshot" 
                                        name="screenshot" 
                                        accept="image/*"
                                    >
                                    <small class="text-muted">JPG, PNG, or GIF (max 5MB)</small>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="reset" class="btn btn-light">Clear</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Submit Ticket
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle"></i> Help & Tips
                            </h5>
                        </div>
                        <div class="card-body small">
                            <h6>Priority Guidelines:</h6>
                            <ul class="mb-3">
                                <li><strong>Critical:</strong> System down, data loss risk</li>
                                <li><strong>High:</strong> Major functionality broken</li>
                                <li><strong>Medium:</strong> Non-critical issues</li>
                                <li><strong>Low:</strong> Enhancement requests</li>
                            </ul>

                            <h6>Response Times:</h6>
                            <ul class="mb-3">
                                <li><strong>Critical:</strong> 1 hour</li>
                                <li><strong>High:</strong> 4 hours</li>
                                <li><strong>Medium:</strong> 12 hours</li>
                                <li><strong>Low:</strong> 24 hours</li>
                            </ul>

                            <div class="alert alert-info p-2">
                                <small>Include screenshots and steps to reproduce to help us resolve your issue faster.</small>
                            </div>
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
