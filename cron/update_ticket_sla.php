<?php
/**
 * Support Ticket SLA Status Update Cron Job
 *
 * This script should be run hourly via cron job to:
 * - Calculate and update SLA status for all open tickets
 * - Identify tickets that are on-track, at-risk, or breached
 * - Log SLA violations for reporting
 */

// Include database connection
require_once '../config.php';
require_once '../includes/db.php';
require_once '../includes/SLACalculator.php';

if (!isset($pdo) || !$pdo) {
    error_log("SLA update cron: Database connection failed");
    exit(1);
}

echo "Starting support ticket SLA status update...\n";

try {
    // Initialize SLA Calculator
    $slaCalculator = new SLACalculator($pdo);
    
    // Update all SLA statuses
    $result = $slaCalculator->updateAllSLAStatuses();
    
    if ($result['success']) {
        echo "SLA status update completed successfully\n";
        echo "Updated: " . $result['updated'] . " tickets\n";
        
        // Log the update
        logSLAUpdate($pdo, $result['updated']);
        
    } else {
        error_log("SLA update failed: " . $result['error']);
        echo "Error: " . $result['error'] . "\n";
        exit(1);
    }
    
    // Check for newly breached tickets and log them
    checkAndLogBreachedTickets($pdo, $slaCalculator);
    
    echo "SLA status check completed successfully\n";
    
} catch (Exception $e) {
    error_log("SLA update cron error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Log SLA update event
 */
function logSLAUpdate($pdo, $ticketsUpdated) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sla_update_log (tickets_updated, last_run_at, status)
            VALUES (?, NOW(), 'success')
            ON DUPLICATE KEY UPDATE 
                tickets_updated = VALUES(tickets_updated),
                last_run_at = VALUES(last_run_at)
        ");
        $stmt->execute([$ticketsUpdated]);
    } catch (Exception $e) {
        error_log("Error logging SLA update: " . $e->getMessage());
    }
}

/**
 * Check for newly breached tickets and log them
 */
function checkAndLogBreachedTickets($pdo, $slaCalculator) {
    try {
        // Get tickets that just turned breached
        $stmt = $pdo->prepare("
            SELECT 
                st.id,
                st.ticket_number,
                st.tenant_id,
                st.branch_id,
                st.priority,
                st.sla_due_at,
                st.sla_status,
                t.name as tenant_name,
                u.email as admin_email
            FROM support_tickets st
            JOIN tenants t ON st.tenant_id = t.id
            JOIN users u ON st.created_by_user_id = u.id
            WHERE st.sla_status = 'breached'
            AND st.status NOT IN ('resolved', 'closed')
            AND st.breach_notification_sent = 0
        ");
        $stmt->execute();
        $breachedTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($breachedTickets)) {
            echo "Found " . count($breachedTickets) . " newly breached tickets\n";
            
            foreach ($breachedTickets as $ticket) {
                // Mark notification as sent
                $updateStmt = $pdo->prepare("
                    UPDATE support_tickets 
                    SET breach_notification_sent = 1 
                    WHERE id = ?
                ");
                $updateStmt->execute([$ticket['id']]);
                
                // Create notification for admins
                $notificationStmt = $pdo->prepare("
                    INSERT INTO notifications
                    (tenant_id, transaction_id, transaction_type, message, recipient_role, status)
                    VALUES (?, ?, 'support_ticket_sla_breach', ?, 'Admin', 'Unread')
                ");
                
                $message = "SLA Breach Alert: Ticket #{$ticket['ticket_number']} has exceeded its SLA deadline.\n" .
                           "Priority: {$ticket['priority']}\n" .
                           "Due Date: {$ticket['sla_due_at']}\n" .
                           "Please take immediate action to resolve this ticket.";
                
                $notificationStmt->execute([
                    $ticket['tenant_id'],
                    $ticket['id'],
                    $message
                ]);
                
                echo "  SLA breach notification sent for ticket #{$ticket['ticket_number']}\n";
            }
        }
        
    } catch (Exception $e) {
        error_log("Error checking breached tickets: " . $e->getMessage());
    }
}
?>
