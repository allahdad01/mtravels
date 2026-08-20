<?php
session_start();
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/SupportTicketManager.php';

// Check if user is admin or tenant super admin
if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once dirname(__DIR__) . '/includes/permissions.php';
require_permission('support.view');

$ticketManager = new SupportTicketManager($pdo);
$message = '';
$alert_type = '';

// Determine user type and ID
$user_id = $_SESSION['user_id'] ?? $_SESSION['user_id'];
$user_type = isset($_SESSION['user_id']) ? 'admin' : 'tenant_super_admin';
$user_name = $_SESSION['name'] ?? $_SESSION['email'];
$user_email = $_SESSION['email'];
$tenant_id = $_SESSION['tenant_id'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = sanitize_input($_POST['category'] ?? '');
    $subject = sanitize_input($_POST['subject'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $priority = sanitize_input($_POST['priority'] ?? 'medium');
    
    // Validate inputs
    if (empty($category) || empty($subject) || empty($description)) {
        $message = 'Please fill in all required fields';
        $alert_type = 'danger';
    } else {
        $ticketData = [
            'tenant_id' => $tenant_id,
            'submitted_by_type' => $user_type,
            'submitted_by_id' => $user_id,
            'submitted_by_name' => $user_name,
            'submitted_by_email' => $user_email,
            'category' => $category,
            'subject' => $subject,
            'description' => $description,
            'priority' => $priority
        ];
        
        // Handle screenshot upload if provided
        if (!empty($_FILES['screenshot']['name'])) {
            $uploadResult = $ticketManager->uploadScreenshot($_FILES['screenshot'], 0);
            if ($uploadResult['success']) {
                $ticketData['screenshot_path'] = $uploadResult['path'];
            }
        }
        
        $result = $ticketManager->createTicket($ticketData);
        
        if ($result['success']) {
            $message = 'Support ticket created successfully! Ticket #' . $result['ticket_number'];
            $alert_type = 'success';
            // Redirect after 2 seconds
            echo "<script>setTimeout(function() { window.location.href = 'view_tickets.php'; }, 2000);</script>";
        } else {
            $message = 'Failed to create ticket: ' . ($result['error'] ?? 'Unknown error');
            $alert_type = 'danger';
        }
    }
}

$pageTitle = 'Submit Support Ticket';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        .ticket-form {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        .required:after {
            content: ' *';
            color: red;
        }
        .btn-submit {
            width: 100%;
            padding: 10px;
            margin-top: 20px;
        }
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="ticket-form">
        <h2 class="mb-4">Submit Support Ticket</h2>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $alert_type; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="category" class="required">Category</label>
                <select class="form-control" id="category" name="category" required>
                    <option value="">Select Category</option>
                    <option value="bug_report">Bug Report</option>
                    <option value="feature_request">Feature Request</option>
                    <option value="payment_issue">Payment Issue</option>
                    <option value="technical_support">Technical Support</option>
                    <option value="account_issue">Account Issue</option>
                    <option value="general">General Inquiry</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="subject" class="required">Subject</label>
                <input type="text" class="form-control" id="subject" name="subject" 
                       placeholder="Brief subject" required maxlength="255">
            </div>
            
            <div class="form-group">
                <label for="description" class="required">Description</label>
                <textarea class="form-control" id="description" name="description" 
                          rows="5" placeholder="Detailed description of your issue" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="priority" class="required">Priority</label>
                <select class="form-control" id="priority" name="priority" required>
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="screenshot">Screenshot (Optional)</label>
                <input type="file" class="form-control-file" id="screenshot" name="screenshot" 
                       accept="image/*">
                <small class="form-text text-muted">Max 5MB. Supported: JPG, PNG, GIF</small>
            </div>
            
            <button type="submit" class="btn btn-primary btn-submit">Submit Ticket</button>
            <a href="view_tickets.php" class="btn btn-secondary btn-submit">View My Tickets</a>
        </form>
    </div>
</div>

<script src="../js/jquery.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
</body>
</html>
