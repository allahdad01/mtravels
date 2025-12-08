<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module
require_once dirname(__FILE__) . '/../security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Include database connection
require_once('../includes/db.php');

// Get any flash messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear flash messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch clients only
$clients_query = "SELECT id, name, 'client' as role FROM clients WHERE tenant_id = ? AND branch_id = ? ORDER BY name";
$clients_stmt = $pdo->prepare($clients_query);
$clients_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$clients_stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$clients_stmt->execute();
$clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $recipient_type = $_POST['recipient_type'];
    $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;
    $sender_id = $_SESSION['user_id'];

    // Validate recipient_id if individual type is selected
    if ($recipient_type === 'individual' && $recipient_id) {
        // Check if recipient exists in clients table only
        $client_check_stmt = $pdo->prepare("SELECT 1 FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        $client_check_stmt->bindParam(1, $recipient_id, PDO::PARAM_INT);
        $client_check_stmt->bindParam(2, $tenant_id, PDO::PARAM_INT);
        $client_check_stmt->bindParam(3, $branch_id, PDO::PARAM_INT);
        $client_check_stmt->execute();

        if ($client_check_stmt->fetch(PDO::FETCH_ASSOC)) {
            $recipient_table = 'clients';
            $valid_recipient = true;
        } else {
            $valid_recipient = false;
        }

        if (!$valid_recipient) {
            $_SESSION['error_message'] = __("invalid_recipient_selected");
        } else {
            $query = "INSERT INTO messages (subject, message, sender_id, recipient_type, recipient_id, recipient_table, tenant_id, branch_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(1, $subject, PDO::PARAM_STR);
            $stmt->bindParam(2, $message, PDO::PARAM_STR);
            $stmt->bindParam(3, $sender_id, PDO::PARAM_INT);
            $stmt->bindParam(4, $recipient_type, PDO::PARAM_STR);
            $stmt->bindParam(5, $recipient_id, PDO::PARAM_INT);
            $stmt->bindParam(6, $recipient_table, PDO::PARAM_STR);
            $stmt->bindParam(7, $tenant_id, PDO::PARAM_INT);
            $stmt->bindParam(8, $branch_id, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                $message_id = $pdo->lastInsertId();

                // Add activity logging
                $user_id = $_SESSION['user_id'] ?? 0;
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

                // Prepare new values data
                $new_values = [
                    'message_id' => $message_id,
                    'subject' => $subject,
                    'recipient_type' => $recipient_type,
                    'recipient_id' => $recipient_id,
                    'recipient_table' => $recipient_table
                ];

                // Insert activity log
                $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                    VALUES (?, 'add', 'messages', ?, '{}', ?, ?, ?, NOW(), ?, ?)");

                $new_values_json = json_encode($new_values);
                $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
                $activity_log_stmt->bindParam(2, $message_id, PDO::PARAM_INT);
                $activity_log_stmt->bindParam(3, $new_values_json, PDO::PARAM_STR);
                $activity_log_stmt->bindParam(4, $ip_address, PDO::PARAM_STR);
                $activity_log_stmt->bindParam(5, $user_agent, PDO::PARAM_STR);
                $activity_log_stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
                $activity_log_stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
                $activity_log_stmt->execute();
                
                $_SESSION['success_message'] = __("message_sent_successfully");
            } else {
                $_SESSION['error_message'] = __("error_sending_message");
            }
        }
    } else {
        // For non-individual messages
        $query = "INSERT INTO messages (subject, message, sender_id, recipient_type, recipient_id, recipient_table, tenant_id, branch_id)
                  VALUES (?, ?, ?, ?, NULL, NULL, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(1, $subject, PDO::PARAM_STR);
        $stmt->bindParam(2, $message, PDO::PARAM_STR);
        $stmt->bindParam(3, $sender_id, PDO::PARAM_INT);
        $stmt->bindParam(4, $recipient_type, PDO::PARAM_STR);
        $stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
        $stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
        $result = $stmt->execute();

        if ($result) {
            $message_id = $pdo->lastInsertId();

            // Add activity logging
            $user_id = $_SESSION['user_id'] ?? 0;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            // Prepare new values data
            $new_values = [
                'message_id' => $message_id,
                'subject' => $subject,
                'recipient_type' => $recipient_type
            ];

            // Insert activity log
            $activity_log_stmt = $pdo->prepare("INSERT INTO activity_log
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'add', 'messages', ?, '{}', ?, ?, ?, NOW(), ?, ?)");

            $new_values_json = json_encode($new_values);
            $activity_log_stmt->bindParam(1, $user_id, PDO::PARAM_INT);
            $activity_log_stmt->bindParam(2, $message_id, PDO::PARAM_INT);
            $activity_log_stmt->bindParam(3, $new_values_json, PDO::PARAM_STR);
            $activity_log_stmt->bindParam(4, $ip_address, PDO::PARAM_STR);
            $activity_log_stmt->bindParam(5, $user_agent, PDO::PARAM_STR);
            $activity_log_stmt->bindParam(6, $tenant_id, PDO::PARAM_INT);
            $activity_log_stmt->bindParam(7, $branch_id, PDO::PARAM_INT);
            $activity_log_stmt->execute();

            $_SESSION['success_message'] = __("message_sent_successfully");
        } else {
            $_SESSION['error_message'] = __("error_sending_message");
        }
    }

    // Redirect back to the same page
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch recent messages with recipient details
$recent_messages_query = "SELECT m.*,
    CASE
        WHEN m.recipient_type = 'individual' THEN
            CASE
                WHEN m.recipient_table = 'users' THEN (SELECT name FROM users WHERE id = m.recipient_id AND tenant_id = ? AND branch_id = ?)
                WHEN m.recipient_table = 'clients' THEN (SELECT name FROM clients WHERE id = m.recipient_id AND tenant_id = ? AND branch_id = ?)
                ELSE 'Unknown Recipient'
            END
        ELSE m.recipient_type
    END as recipient_name,
    u.name as sender_name,
    m.status
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.tenant_id = ? AND m.branch_id = ?
    ORDER BY created_at DESC
    LIMIT 10";
$recent_messages_stmt = $pdo->prepare($recent_messages_query);
$recent_messages_stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$recent_messages_stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$recent_messages_stmt->bindParam(3, $tenant_id, PDO::PARAM_INT);
$recent_messages_stmt->bindParam(4, $branch_id, PDO::PARAM_INT);
$recent_messages_stmt->bindParam(5, $tenant_id, PDO::PARAM_INT);
$recent_messages_stmt->bindParam(6, $branch_id, PDO::PARAM_INT);
$recent_messages_stmt->execute();
?>