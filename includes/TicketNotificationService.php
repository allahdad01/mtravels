<?php
/**
 * Ticket Notification Service Class
 * Handles all notification logic for support tickets
 */
class TicketNotificationService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Send notification when ticket is created
     */
    public function notifyTicketCreated($ticketId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.id, st.ticket_number, st.title, st.description,
                    st.priority, st.created_by_user_id,
                    u.name, u.email, 
                    tc.name as category_name,
                    t.name as tenant_name
                FROM support_tickets st
                JOIN users u ON st.created_by_user_id = u.id
                JOIN tenants t ON st.tenant_id = t.id
                JOIN ticket_categories tc ON st.category_id = tc.id
                WHERE st.id = ?
            ");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) return false;
            
            // Get super admin emails for notification
            $adminStmt = $this->pdo->prepare("
                SELECT email FROM users WHERE role = 'super_admin' AND status = 'active'
            ");
            $adminStmt->execute();
            $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Send email to super admins
            foreach ($admins as $admin) {
                $this->sendEmail(
                    $admin['email'],
                    'New Support Ticket: ' . $ticket['ticket_number'],
                    $this->buildTicketCreatedEmail($ticket)
                );
            }
            
            // Record notification
            $this->recordNotification($ticketId, $ticket['created_by_user_id'], 'created');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Notify Ticket Created Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification when admin replies to ticket
     */
    public function notifyTicketReply($ticketId, $replyId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.id, st.ticket_number, st.title, st.created_by_user_id,
                    u.name, u.email,
                    tr.reply_text, tr.replied_by_user_id,
                    ru.name as replied_by_name
                FROM support_tickets st
                JOIN users u ON st.created_by_user_id = u.id
                JOIN ticket_replies tr ON tr.id = ?
                JOIN users ru ON tr.replied_by_user_id = ru.id
                WHERE st.id = ?
            ");
            $stmt->execute([$replyId, $ticketId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) return false;
            
            // Only notify if reply is not internal note
            $replyStmt = $this->pdo->prepare("SELECT is_internal_note FROM ticket_replies WHERE id = ?");
            $replyStmt->execute([$replyId]);
            $reply = $replyStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reply['is_internal_note']) return false;
            
            // Send email to ticket creator
            $this->sendEmail(
                $data['email'],
                'Reply to Your Support Ticket: ' . $data['ticket_number'],
                $this->buildTicketReplyEmail($data)
            );
            
            // Record notification
            $this->recordNotification($ticketId, $data['created_by_user_id'], 'reply');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Notify Ticket Reply Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification when ticket status changes
     */
    public function notifyStatusChange($ticketId, $oldStatus, $newStatus) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.id, st.ticket_number, st.title, st.created_by_user_id,
                    u.name, u.email
                FROM support_tickets st
                JOIN users u ON st.created_by_user_id = u.id
                WHERE st.id = ?
            ");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) return false;
            
            // Send email to ticket creator
            $this->sendEmail(
                $ticket['email'],
                'Ticket Status Updated: ' . $ticket['ticket_number'],
                $this->buildStatusChangeEmail($ticket, $oldStatus, $newStatus)
            );
            
            // Record notification
            $this->recordNotification($ticketId, $ticket['created_by_user_id'], 'status_change');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Notify Status Change Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification for SLA breach
     */
    public function notifySLABreach($ticketId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.id, st.ticket_number, st.title, st.priority,
                    st.sla_due_at, st.created_by_user_id,
                    u.name, u.email
                FROM support_tickets st
                JOIN users u ON st.created_by_user_id = u.id
                WHERE st.id = ?
            ");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) return false;
            
            // Send email to ticket creator
            $this->sendEmail(
                $ticket['email'],
                'SLA Breach Alert: ' . $ticket['ticket_number'],
                $this->buildSLABreachEmail($ticket)
            );
            
            // Get super admin emails for notification
            $adminStmt = $this->pdo->prepare("
                SELECT email FROM users WHERE role = 'super_admin' AND status = 'active'
            ");
            $adminStmt->execute();
            $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Send email to super admins
            foreach ($admins as $admin) {
                $this->sendEmail(
                    $admin['email'],
                    'SLA Breach Alert: ' . $ticket['ticket_number'],
                    $this->buildSLABreachEmail($ticket, true)
                );
            }
            
            // Record notification
            $this->recordNotification($ticketId, $ticket['created_by_user_id'], 'sla_breach');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Notify SLA Breach Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build ticket created email body
     */
    private function buildTicketCreatedEmail($ticket) {
        return "
A new support ticket has been created:

Ticket #: {$ticket['ticket_number']}
Title: {$ticket['title']}
Category: {$ticket['category_name']}
Priority: {$ticket['priority']}
Tenant: {$ticket['tenant_name']}
Created by: {$ticket['name']} ({$ticket['email']})

Description:
{$ticket['description']}

Please log in to the admin panel to respond to this ticket.
        ";
    }
    
    /**
     * Build ticket reply email body
     */
    private function buildTicketReplyEmail($data) {
        return "
There is a new reply to your support ticket {$data['ticket_number']}:

Ticket: {$data['title']}
Replied by: {$data['replied_by_name']}

Reply:
{$data['reply_text']}

Please log in to check the full ticket details and respond if needed.
        ";
    }
    
    /**
     * Build status change email body
     */
    private function buildStatusChangeEmail($ticket, $oldStatus, $newStatus) {
        return "
Your support ticket {$ticket['ticket_number']} status has been updated.

Ticket: {$ticket['title']}
Previous Status: $oldStatus
New Status: $newStatus

Please log in for more details.
        ";
    }
    
    /**
     * Build SLA breach email body
     */
    private function buildSLABreachEmail($ticket, $isAdmin = false) {
        $message = "
SLA Breach Alert!

Ticket #: {$ticket['ticket_number']}
Title: {$ticket['title']}
Priority: {$ticket['priority']}
Due Date: {$ticket['sla_due_at']}
";
        
        if ($isAdmin) {
            $message .= "\nThis ticket has exceeded its SLA deadline. Immediate action is required.\n";
        } else {
            $message .= "\nYour support ticket has exceeded the response time. Our support team will prioritize this.\n";
        }
        
        return $message;
    }
    
    /**
     * Send email (integrates with existing email system)
     */
    private function sendEmail($to, $subject, $body) {
        try {
            // Use the application's email sending function if available
            if (function_exists('sendEmail')) {
                return sendEmail($to, $subject, $body);
            }
            
            // Fallback to PHP mail
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type: text/plain; charset=UTF-8" . "\r\n";
            $headers .= "From: support@mtravels.com" . "\r\n";
            
            return mail($to, $subject, $body, $headers);
            
        } catch (Exception $e) {
            error_log("Email Send Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record notification in database
     */
    private function recordNotification($ticketId, $userId, $type) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ticket_notifications (ticket_id, user_id, notification_type, sent_via, sent_at)
                VALUES (?, ?, ?, 'email', NOW())
            ");
            return $stmt->execute([$ticketId, $userId, $type]);
            
        } catch (Exception $e) {
            error_log("Record Notification Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
