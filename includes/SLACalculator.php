<?php
// Ensure timezone consistency
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Kabul'); // Afghanistan Time (UTC+4:30)
}

/**
 * SLA Calculator Class
 * Handles all SLA-related calculations and updates
 */
class SLACalculator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate SLA due date based on priority
     */
    public function calculateDueDate($priority) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT first_response_hours, resolution_hours 
                FROM ticket_sla_rules 
                WHERE priority = ?
            ");
            $stmt->execute([$priority]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$rule) {
                return null;
            }
            
            $dueDate = new DateTime();
            $dueDate->add(new DateInterval('PT' . $rule['first_response_hours'] . 'H'));
            
            return $dueDate->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Calculate and update SLA status for a ticket
     */
    public function calculateSLAStatus($ticketId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT st.id, st.status, st.sla_due_at, st.created_at, st.priority
                FROM support_tickets st
                WHERE st.id = ?
            ");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$ticket) {
                return null;
            }
            
            $now = new DateTime();
            $dueAt = new DateTime($ticket['sla_due_at']);
            
            $slaStatus = 'on_track';
            
            // If resolved, mark as resolved
            if ($ticket['status'] === 'resolved' || $ticket['status'] === 'closed') {
                $slaStatus = 'resolved';
            }
            // If overdue
            elseif ($now > $dueAt) {
                $slaStatus = 'breached';
            }
            // If at 75% of time
            else {
                $createdAt = new DateTime($ticket['created_at']);
                $totalTime = $dueAt->getTimestamp() - $createdAt->getTimestamp();
                $usedTime = $now->getTimestamp() - $createdAt->getTimestamp();
                $percentageUsed = ($usedTime / $totalTime) * 100;
                
                if ($percentageUsed >= 75) {
                    $slaStatus = 'at_risk';
                }
            }
            
            // Update in database
            $updateStmt = $this->pdo->prepare("
                UPDATE support_tickets 
                SET sla_status = ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$slaStatus, $ticketId]);
            
            return $slaStatus;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get SLA status for display (human readable)
     */
    public function getSLADisplay($ticket) {
        $now = new DateTime();
        $dueAt = new DateTime($ticket['sla_due_at']);
        $difference = $dueAt->diff($now);
        
        $display = [
            'status' => $ticket['sla_status'],
            'due_date' => $ticket['sla_due_at'],
            'is_overdue' => $now > $dueAt,
            'hours_remaining' => ceil($difference->days * 24 + $difference->h + $difference->i / 60),
            'percentage' => 0,
            'color' => 'success'
        ];
        
        if ($ticket['status'] === 'resolved' || $ticket['status'] === 'closed') {
            $display['color'] = 'success';
            $display['status'] = 'Resolved';
        } else {
            if ($now > $dueAt) {
                $display['color'] = 'danger';
                $display['status'] = 'Breached';
            } elseif ($ticket['sla_status'] === 'at_risk') {
                $display['color'] = 'warning';
                $display['status'] = 'At Risk';
            } else {
                $display['color'] = 'info';
                $display['status'] = 'On Track';
            }
        }
        
        // Calculate percentage used
        $createdAt = new DateTime($ticket['created_at']);
        $totalSeconds = $dueAt->getTimestamp() - $createdAt->getTimestamp();
        $usedSeconds = min($now->getTimestamp() - $createdAt->getTimestamp(), $totalSeconds);
        $display['percentage'] = max(0, min(100, ($usedSeconds / $totalSeconds) * 100));
        
        return $display;
    }
    
    /**
     * Update all SLA statuses (run via cron)
     */
    public function updateAllSLAStatuses() {
        try {
            $stmt = $this->pdo->query("
                SELECT id FROM support_tickets 
                WHERE status NOT IN ('resolved', 'closed')
            ");
            
            $tickets = $stmt->fetchAll();
            $updated = 0;
            
            foreach ($tickets as $ticket) {
                if ($this->calculateSLAStatus($ticket['id'])) {
                    $updated++;
                }
            }
            
            return ['success' => true, 'updated' => $updated];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get SLA statistics
     */
    public function getSLAStatistics($filters = []) {
        try {
            $where = "WHERE st.status NOT IN ('closed')";
            $params = [];
            
            if (!empty($filters['tenant_id'])) {
                $where .= " AND st.tenant_id = ?";
                $params[] = $filters['tenant_id'];
            }
            
            if (!empty($filters['branch_id'])) {
                $where .= " AND st.branch_id = ?";
                $params[] = $filters['branch_id'];
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN st.sla_status = 'on_track' THEN 1 ELSE 0 END) as on_track,
                    SUM(CASE WHEN st.sla_status = 'at_risk' THEN 1 ELSE 0 END) as at_risk,
                    SUM(CASE WHEN st.sla_status = 'breached' THEN 1 ELSE 0 END) as breached,
                    SUM(CASE WHEN st.sla_status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    AVG(TIMESTAMPDIFF(HOUR, st.created_at, st.first_response_at)) as avg_response_hours,
                    AVG(TIMESTAMPDIFF(HOUR, st.created_at, st.resolved_at)) as avg_resolution_hours
                FROM support_tickets st
                $where
            ");
            
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Check if first response SLA is met
     */
    public function isFirstResponseMet($ticket) {
        if (!$ticket['first_response_at']) {
            return false;
        }
        
        $responseTime = new DateTime($ticket['first_response_at']);
        $createdAt = new DateTime($ticket['created_at']);
        $difference = $responseTime->diff($createdAt);
        
        // Get SLA rule
        $stmt = $this->pdo->prepare("SELECT first_response_hours FROM ticket_sla_rules WHERE priority = ?");
        $stmt->execute([$ticket['priority']]);
        $rule = $stmt->fetch();
        
        if (!$rule) {
            return true;
        }
        
        $hoursUsed = $difference->days * 24 + $difference->h;
        return $hoursUsed <= $rule['first_response_hours'];
    }
    
    /**
     * Check if resolution SLA is met
     */
    public function isResolutionMet($ticket) {
        if (!$ticket['resolved_at']) {
            return false;
        }
        
        $resolvedTime = new DateTime($ticket['resolved_at']);
        $createdAt = new DateTime($ticket['created_at']);
        $difference = $resolvedTime->diff($createdAt);
        
        // Get SLA rule
        $stmt = $this->pdo->prepare("SELECT resolution_hours FROM ticket_sla_rules WHERE priority = ?");
        $stmt->execute([$ticket['priority']]);
        $rule = $stmt->fetch();
        
        if (!$rule) {
            return true;
        }
        
        $hoursUsed = $difference->days * 24 + $difference->h;
        return $hoursUsed <= $rule['resolution_hours'];
    }
}
?>
