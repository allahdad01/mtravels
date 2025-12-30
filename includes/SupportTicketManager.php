<?php
// Ensure timezone consistency - set to Afghanistan Time (AFT)
// Adjust this to your application's timezone if different
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Kabul'); // Afghanistan Time (UTC+4:30)
}

/**
 * Support Ticket Manager Class
 * Handles all CRUD operations for support tickets
 */
class SupportTicketManager {
    private $pdo;
    private $slaCalculator;
    private $notificationService;
    
    public function __construct($pdo, $slaCalculator = null, $notificationService = null) {
        $this->pdo = $pdo;
        $this->slaCalculator = $slaCalculator;
        $this->notificationService = $notificationService;
    }
    
    /**
     * Create a new support ticket
     */
    public function createTicket($data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO support_tickets 
                (tenant_id, branch_id, created_by_user_id, created_by_role, category_id, title, description, priority, screenshot_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['tenant_id'],
                $data['branch_id'],
                $data['created_by_user_id'],
                $data['created_by_role'],
                $data['category_id'],
                $data['title'],
                $data['description'],
                $data['priority'] ?? 'medium',
                $data['screenshot_path'] ?? null
            ]);
            
            if (!$result) {
                return ['success' => false, 'error' => 'Failed to create ticket'];
            }
            
            $ticketId = $this->pdo->lastInsertId();
            
            // Notify about ticket creation
            if ($this->notificationService) {
                try {
                    $this->notificationService->notifyTicketCreated($ticketId);
                } catch (Exception $e) {
                    error_log("Notification error (non-fatal): " . $e->getMessage());
                    // Continue even if notification fails
                }
            }
            
            return ['success' => true, 'ticket_id' => $ticketId];
            
        } catch (Exception $e) {
            error_log("Create Ticket Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get ticket by ID
     */
    public function getTicket($ticketId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.*,
                    tc.name as category_name,
                    u.name as created_by_name, u.email as created_by_email,
                    ru.name as resolved_by_name,
                    sr.first_response_hours, sr.resolution_hours
                FROM support_tickets st
                JOIN ticket_categories tc ON st.category_id = tc.id
                JOIN users u ON st.created_by_user_id = u.id
                LEFT JOIN users ru ON st.resolved_by_user_id = ru.id
                LEFT JOIN ticket_sla_rules sr ON st.priority = sr.priority
                WHERE st.id = ?
            ");
            $stmt->execute([$ticketId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Ticket Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get ticket by ticket number
     */
    public function getTicketByNumber($ticketNumber) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.*,
                    tc.name as category_name,
                    u.name as created_by_name, u.email as created_by_email
                FROM support_tickets st
                JOIN ticket_categories tc ON st.category_id = tc.id
                JOIN users u ON st.created_by_user_id = u.id
                WHERE st.ticket_number = ?
            ");
            $stmt->execute([$ticketNumber]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Ticket By Number Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all tickets for a tenant
     */
    public function getTicketsByTenant($tenantId, $filters = []) {
        try {
            $where = "WHERE st.tenant_id = ?";
            $params = [$tenantId];
            
            if (!empty($filters['status'])) {
                $where .= " AND st.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['priority'])) {
                $where .= " AND st.priority = ?";
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['category_id'])) {
                $where .= " AND st.category_id = ?";
                $params[] = $filters['category_id'];
            }
            
            $orderBy = "ORDER BY st.created_at DESC";
            if (!empty($filters['sort'])) {
                if ($filters['sort'] === 'sla_due') {
                    $orderBy = "ORDER BY st.sla_due_at ASC";
                } elseif ($filters['sort'] === 'priority') {
                    $orderBy = "ORDER BY FIELD(st.priority, 'critical', 'high', 'medium', 'low')";
                }
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.*,
                    tc.name as category_name,
                    u.name as created_by_name
                FROM support_tickets st
                JOIN ticket_categories tc ON st.category_id = tc.id
                JOIN users u ON st.created_by_user_id = u.id
                $where
                $orderBy
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Tickets By Tenant Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all tickets (for super admin)
     */
    public function getAllTickets($filters = []) {
        try {
            $where = "WHERE 1=1";
            $params = [];
            
            if (!empty($filters['status'])) {
                $where .= " AND st.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['priority'])) {
                $where .= " AND st.priority = ?";
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['tenant_id'])) {
                $where .= " AND st.tenant_id = ?";
                $params[] = $filters['tenant_id'];
            }
            
            if (!empty($filters['sla_status'])) {
                $where .= " AND st.sla_status = ?";
                $params[] = $filters['sla_status'];
            }
            
            $orderBy = "ORDER BY st.created_at DESC";
            if (!empty($filters['sort'])) {
                if ($filters['sort'] === 'sla_due') {
                    $orderBy = "ORDER BY st.sla_due_at ASC";
                } elseif ($filters['sort'] === 'priority') {
                    $orderBy = "ORDER BY FIELD(st.priority, 'critical', 'high', 'medium', 'low')";
                }
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    st.*,
                    tc.name as category_name,
                    u.name as created_by_name,
                    t.name as tenant_name
                FROM support_tickets st
                JOIN ticket_categories tc ON st.category_id = tc.id
                JOIN users u ON st.created_by_user_id = u.id
                JOIN tenants t ON st.tenant_id = t.id
                $where
                $orderBy
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get All Tickets Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update ticket status
     */
    public function updateStatus($ticketId, $newStatus, $userId = null) {
        try {
            $ticket = $this->getTicket($ticketId);
            $oldStatus = $ticket['status'];
            
            $updates = [];
            $params = [];
            
            $updates[] = "status = ?";
            $params[] = $newStatus;
            
            if ($newStatus === 'resolved') {
                $updates[] = "resolved_at = NOW()";
                $updates[] = "resolved_by_user_id = ?";
                $params[] = $userId;
                
                // Mark first response if not already marked
                if (!$ticket['first_response_at']) {
                    $updates[] = "first_response_at = NOW()";
                }
            }
            
            $setClause = implode(', ', $updates);
            $stmt = $this->pdo->prepare("UPDATE support_tickets SET $setClause WHERE id = ?");
            $stmt->execute(array_merge($params, [$ticketId]));
            
            // Update SLA status
            if ($this->slaCalculator) {
                $this->slaCalculator->calculateSLAStatus($ticketId);
            }
            
            // Send notification
            if ($this->notificationService) {
                $this->notificationService->notifyStatusChange($ticketId, $oldStatus, $newStatus);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Update Status Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Add a reply to ticket
     */
    public function addReply($ticketId, $userId, $replyText, $isInternalNote = false, $screenshotPath = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ticket_replies (ticket_id, replied_by_user_id, is_internal_note, reply_text, screenshot_path)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([$ticketId, $userId, $isInternalNote ? 1 : 0, $replyText, $screenshotPath]);
            
            if (!$result) {
                return ['success' => false, 'error' => 'Failed to add reply'];
            }
            
            $replyId = $this->pdo->lastInsertId();
            
            // Mark first response if this is the first reply from admin
             if (!$isInternalNote) {
                 $ticket = $this->getTicket($ticketId);
                 if (!$ticket['first_response_at']) {
                     $stmt = $this->pdo->prepare("
                         UPDATE support_tickets 
                         SET first_response_at = NOW()
                         WHERE id = ?
                     ");
                     $stmt->execute([$ticketId]);
                    
                    // Update SLA status
                    if ($this->slaCalculator) {
                        $this->slaCalculator->calculateSLAStatus($ticketId);
                    }
                }
                
                // Send notification
                if ($this->notificationService) {
                    $this->notificationService->notifyTicketReply($ticketId, $replyId);
                }
            }
            
            return ['success' => true, 'reply_id' => $replyId];
            
        } catch (Exception $e) {
            error_log("Add Reply Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get all replies for a ticket
     */
    public function getReplies($ticketId, $includeInternal = false) {
        try {
            $where = "WHERE tr.ticket_id = ?";
            $params = [$ticketId];
            
            if (!$includeInternal) {
                $where .= " AND tr.is_internal_note = 0";
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    tr.*,
                    u.name as replied_by_name, u.email as replied_by_email
                FROM ticket_replies tr
                JOIN users u ON tr.replied_by_user_id = u.id
                $where
                ORDER BY tr.created_at ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Replies Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get ticket categories
     */
    public function getCategories() {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM ticket_categories 
                WHERE status = 'active'
                ORDER BY sort_order ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Categories Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get SLA rules
     */
    public function getSLARules() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM ticket_sla_rules ORDER BY priority DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get SLA Rules Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get ticket statistics
     */
    public function getStatistics($tenantId = null) {
        try {
            $where = "";
            $params = [];
            
            if ($tenantId) {
                $where = "WHERE tenant_id = ?";
                $params[] = $tenantId;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                    SUM(CASE WHEN sla_status = 'breached' THEN 1 ELSE 0 END) as breached,
                    SUM(CASE WHEN sla_status = 'at_risk' THEN 1 ELSE 0 END) as at_risk
                FROM support_tickets
                $where
            ");
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Statistics Error: " . $e->getMessage());
            return null;
        }
    }
}
?>
