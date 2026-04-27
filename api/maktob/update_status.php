<?php
// Update maktob status (send/archive)
session_start();
require_once('../../admin/security.php');
require_once('../../includes/db.php');
require_once('../../includes/language_helpers.php');

// Enforce authentication
enforce_auth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$maktob_id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$maktob_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Get current maktob data for logging
    $stmt = $pdo->prepare("SELECT * FROM maktobs WHERE id = ? AND tenant_id = ? AND branch_id = ?");
    $stmt->execute([$maktob_id, $_SESSION['tenant_id'], $_SESSION['branch_id']]);
    $maktob = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$maktob) {
        echo json_encode(['success' => false, 'message' => 'Maktob not found']);
        exit();
    }

    $old_status = $maktob['status'];
    $new_status = '';

    if ($action === 'send') {
        if ($old_status !== 'draft') {
            echo json_encode(['success' => false, 'message' => 'Only draft maktobs can be sent']);
            exit();
        }
        $new_status = 'sent';
    } elseif ($action === 'archive') {
        if ($old_status === 'archived') {
            echo json_encode(['success' => false, 'message' => 'Maktob is already archived']);
            exit();
        }
        $new_status = 'archived';
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit();
    }

    // Update status
    $update_stmt = $pdo->prepare("UPDATE maktobs SET status = ?, updated_at = NOW() WHERE id = ? AND branch_id = ?");
    $update_stmt->execute([$new_status, $maktob_id, $_SESSION['branch_id']]);

    // Log the action
    $log_stmt = $pdo->prepare("INSERT INTO maktob_logs (tenant_id, maktob_id, user_id, action, old_values, new_values, ip_address, branch_id)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $log_stmt->execute([
        $_SESSION['tenant_id'],
        $maktob_id,
        $_SESSION['user_id'],
        $action,
        json_encode(['status' => $old_status]),
        json_encode(['status' => $new_status]),
        $_SERVER['REMOTE_ADDR'],
        $_SESSION['branch_id']
    ]);

    // Send notification if sent
    if ($action === 'send') {
        // Get tenant admin for notification
        $admin_stmt = $pdo->prepare("SELECT email, name, phone FROM users WHERE tenant_id = ? AND branch_id = ? AND role IN ('super_admin', 'tenant_super_admin', 'admin') ORDER BY role DESC LIMIT 1");
        $admin_stmt->execute([$_SESSION['tenant_id'], $_SESSION['branch_id']]);
        $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && $admin['email']) {
            // Send email notification
            require_once('../../includes/functions.php');
            $subject = "Maktob Sent: " . $maktob['maktob_number'];
            $body = "
            <h3>Maktob Sent Notification</h3>
            <p>A maktob has been sent to branch:</p>
            <ul>
                <li><strong>Number:</strong> {$maktob['maktob_number']}</li>
                <li><strong>Subject:</strong> {$maktob['subject']}</li>
                <li><strong>Company:</strong> {$maktob['company_name']}</li>
                <li><strong>Date:</strong> {$maktob['maktob_date']}</li>
                <li><strong>Sender:</strong> " . (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Unknown') . "</li>
            </ul>
            <p>Please check the admin panel for details.</p>
            ";
            sendEmail($admin['email'], $subject, $body, true, 'maktob_notification', $admin['name'], $_SESSION['tenant_id']);
        }

    }

    echo json_encode(['success' => true, 'message' => 'Maktob status updated successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
