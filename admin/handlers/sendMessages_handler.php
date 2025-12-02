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
include '../includes/db.php';
include '../includes/conn.php';

// Get any flash messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear flash messages
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch clients only
$clients_query = "SELECT id, name, 'client' as role FROM clients WHERE tenant_id = ? AND branch_id = ? ORDER BY name";
$clients_stmt = mysqli_prepare($conn, $clients_query);
mysqli_stmt_bind_param($clients_stmt, "ii", $tenant_id, $branch_id);
mysqli_stmt_execute($clients_stmt);
$clients_result = mysqli_stmt_get_result($clients_stmt);

// Store clients
$clients = [];

while ($row = mysqli_fetch_assoc($clients_result)) {
    $clients[] = $row;
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $recipient_type = mysqli_real_escape_string($conn, $_POST['recipient_type']);
    $recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : null;
    $sender_id = $_SESSION['user_id'];

    // Validate recipient_id if individual type is selected
    if ($recipient_type === 'individual' && $recipient_id) {
        // Check if recipient exists in clients table only
        $client_check_stmt = mysqli_prepare($conn, "SELECT 1 FROM clients WHERE id = ? AND tenant_id = ? AND branch_id = ?");
        mysqli_stmt_bind_param($client_check_stmt, "iii", $recipient_id, $tenant_id, $branch_id);
        mysqli_stmt_execute($client_check_stmt);
        $client_check_result = mysqli_stmt_get_result($client_check_stmt);

        if (mysqli_num_rows($client_check_result) > 0) {
            $recipient_table = 'clients';
            $valid_recipient = true;
        } else {
            $valid_recipient = false;
        }
        mysqli_stmt_close($client_check_stmt);

        if (!$valid_recipient) {
            $_SESSION['error_message'] = __("invalid_recipient_selected");
        } else {
            $query = "INSERT INTO messages (subject, message, sender_id, recipient_type, recipient_id, recipient_table, tenant_id, branch_id)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssisssii", $subject, $message, $sender_id, $recipient_type, $recipient_id, $recipient_table, $tenant_id, $branch_id);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if ($result) {
                $message_id = mysqli_insert_id($conn);
                
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
                $activity_log_stmt = $conn->prepare("INSERT INTO activity_log
                    (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                    VALUES (?, 'add', 'messages', ?, '{}', ?, ?, ?, NOW(), ?, ?)");

                $new_values_json = json_encode($new_values);
                $activity_log_stmt->bind_param("iissssii", $user_id, $message_id, $new_values_json, $ip_address, $user_agent, $tenant_id, $branch_id);
                $activity_log_stmt->execute();
                $activity_log_stmt->close();
                
                $_SESSION['success_message'] = __("message_sent_successfully");
            } else {
                $_SESSION['error_message'] = __("error_sending_message") . mysqli_error($conn);
            }
        }
    } else {
        // For non-individual messages
        $query = "INSERT INTO messages (subject, message, sender_id, recipient_type, recipient_id, recipient_table, tenant_id, branch_id)
                  VALUES (?, ?, ?, ?, NULL, NULL, ?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssisii", $subject, $message, $sender_id, $recipient_type, $tenant_id, $branch_id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($result) {
            $message_id = mysqli_insert_id($conn);

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
            $activity_log_stmt = $conn->prepare("INSERT INTO activity_log
                (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
                VALUES (?, 'add', 'messages', ?, '{}', ?, ?, ?, NOW(), ?, ?)");

            $new_values_json = json_encode($new_values);
            $activity_log_stmt->bind_param("iissssii", $user_id, $message_id, $new_values_json, $ip_address, $user_agent, $tenant_id, $branch_id);
            $activity_log_stmt->execute();
            $activity_log_stmt->close();
            
            $_SESSION['success_message'] = __("message_sent_successfully");
        } else {
            $_SESSION['error_message'] = __("error_sending_message") . mysqli_error($conn);
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
$recent_messages_stmt = mysqli_prepare($conn, $recent_messages_query);
mysqli_stmt_bind_param($recent_messages_stmt, "iiiiii", $tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id);
mysqli_stmt_execute($recent_messages_stmt);
$recent_messages_result = mysqli_stmt_get_result($recent_messages_stmt);
?>