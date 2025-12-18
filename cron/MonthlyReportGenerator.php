<?php
/**
 * MonthlyReportGenerator Class
 * 
 * Handles generation of monthly profit reports with detailed analytics
 * Includes PDF generation and email distribution
 */

require_once dirname(dirname(__FILE__)) . "/vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class MonthlyReportGenerator {
    
    private $pdo;
    private $tempDir;
    
    /**
     * Constructor
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->tempDir = dirname(dirname(__FILE__)) . "/temp/reports";
        
        // Create temp directory if it doesn't exist
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Generate comprehensive monthly report data
     * @param int $tenantId
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return array|false Report data or false on failure
     */
    public function generateMonthlyReport($tenantId, $startDate, $endDate) {
        try {
            // Validate inputs
            if (!$tenantId || !$startDate || !$endDate) {
                throw new Exception("Invalid parameters: tenant_id, startDate, and endDate are required");
            }

            // Verify database connection
            try {
                $this->pdo->query("SELECT 1");
            } catch (Exception $e) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            }

            // Verify tenant exists
            $stmt = $this->pdo->prepare("SELECT id FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            if (!$stmt->fetch()) {
                throw new Exception("Tenant with ID $tenantId not found in database");
            }

            // Check if branches exist for this tenant
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM branches WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $branchResult = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($branchResult['count'] == 0) {
                throw new Exception("No branches found for tenant ID $tenantId");
            }

            // Check if any booking data exists for this tenant
            $bookingCheck = $this->checkBookingDataExists($tenantId, $startDate, $endDate);
            if (!$bookingCheck['has_data']) {
                throw new Exception("No booking data found for tenant ID $tenantId in the date range $startDate to $endDate. Available data: Tickets(" . $bookingCheck['tickets'] . ") | Hotels(" . $bookingCheck['hotels'] . ") | Visas(" . $bookingCheck['visas'] . ") | Umrah(" . $bookingCheck['umrah'] . ")");
            }

            $reportData = [
                'tenant_id' => $tenantId,
                'month' => date('F Y', strtotime($startDate)),
                'period' => $startDate . ' to ' . $endDate,
                'generated_date' => date('Y-m-d H:i:s'),
                'branches' => $this->getBranchData($tenantId, $startDate, $endDate),
                'top_clients' => $this->getTopClients($tenantId, $startDate, $endDate, 10),
                'top_suppliers' => $this->getTopSuppliers($tenantId, $startDate, $endDate, 10),
                'financial_summary' => $this->getFinancialSummary($tenantId, $startDate, $endDate),
                'branch_comparison' => $this->getBranchComparison($tenantId, $startDate, $endDate),
            ];

            return $reportData;
        } catch (Exception $e) {
            error_log("Error generating report: " . $e->getMessage() . " | Tenant: $tenantId | Period: $startDate to $endDate");
            return false;
        }
    }

    /**
     * Check if booking data exists for the tenant and date range
     */
    private function checkBookingDataExists($tenantId, $startDate, $endDate) {
        $result = [
            'has_data' => false,
            'tickets' => 0,
            'hotels' => 0,
            'visas' => 0,
            'umrah' => 0
        ];

        try {
            // Check tickets
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM ticket_bookings WHERE tenant_id = ? AND created_at BETWEEN ? AND ?");
            $stmt->execute([$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result['tickets'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            // Check hotels
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM hotel_bookings WHERE tenant_id = ? AND created_at BETWEEN ? AND ?");
            $stmt->execute([$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result['hotels'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            // Check visas
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM visa_applications WHERE tenant_id = ? AND created_at BETWEEN ? AND ?");
            $stmt->execute([$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result['visas'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            // Check umrah
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM umrah_bookings WHERE tenant_id = ? AND created_at BETWEEN ? AND ?");
            $stmt->execute([$tenantId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result['umrah'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

            $result['has_data'] = ($result['tickets'] + $result['hotels'] + $result['visas'] + $result['umrah']) > 0;

            return $result;
        } catch (Exception $e) {
            error_log("Error checking booking data: " . $e->getMessage());
            return $result;
        }
    }

    /**
     * Get all branches with their performance data
     */
    private function getBranchData($tenantId, $startDate, $endDate) {
         $query = "
             SELECT
                 b.id,
                 b.name,
                 b.code,
                 COUNT(DISTINCT tb.id) as total_tickets,
                 COALESCE(SUM(CASE WHEN tb.currency = 'USD' THEN tb.profit ELSE 0 END), 0) as ticket_profit_usd,
                 COALESCE(SUM(CASE WHEN tb.currency = 'AFS' THEN tb.profit ELSE 0 END), 0) as ticket_profit_afs,
                 COUNT(DISTINCT tr.id) as total_ticket_reservations,
                 COALESCE(SUM(CASE WHEN tr.currency = 'USD' THEN tr.profit ELSE 0 END), 0) as ticket_reservation_profit_usd,
                 COALESCE(SUM(CASE WHEN tr.currency = 'AFS' THEN tr.profit ELSE 0 END), 0) as ticket_reservation_profit_afs,
                 COUNT(DISTINCT tw.id) as total_ticket_weights,
                 COALESCE(SUM(CASE WHEN tb3.currency = 'USD' THEN tw.profit ELSE 0 END), 0) as ticket_weight_profit_usd,
                 COALESCE(SUM(CASE WHEN tb3.currency = 'AFS' THEN tw.profit ELSE 0 END), 0) as ticket_weight_profit_afs,
                 COUNT(DISTINCT rt.id) as total_refunded_tickets,
                 COALESCE(SUM(CASE WHEN rt.currency = 'USD' THEN (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb2.profit, 0)) ELSE rt.service_penalty END) ELSE 0 END), 0) as refunded_tickets_profit_usd,
                 COALESCE(SUM(CASE WHEN rt.currency = 'AFS' THEN (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb2.profit, 0)) ELSE rt.service_penalty END) ELSE 0 END), 0) as refunded_tickets_profit_afs,
                 COUNT(DISTINCT dc.id) as total_date_changes,
                 COALESCE(SUM(CASE WHEN dc.currency = 'USD' THEN dc.service_penalty ELSE 0 END), 0) as date_change_profit_usd,
                 COALESCE(SUM(CASE WHEN dc.currency = 'AFS' THEN dc.service_penalty ELSE 0 END), 0) as date_change_profit_afs,
                 COUNT(DISTINCT h.id) as total_hotels,
                 COALESCE(SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END), 0) as hotel_profit_usd,
                 COALESCE(SUM(CASE WHEN h.currency = 'AFS' THEN h.profit ELSE 0 END), 0) as hotel_profit_afs,
                 COUNT(DISTINCT v.id) as total_visas,
                 COALESCE(SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END), 0) as visa_profit_usd,
                 COALESCE(SUM(CASE WHEN v.currency = 'AFS' THEN v.profit ELSE 0 END), 0) as visa_profit_afs,
                 COUNT(DISTINCT um.booking_id) as total_umrah,
                 COALESCE(SUM(CASE WHEN um.currency = 'USD' THEN um.profit ELSE 0 END), 0) as umrah_profit_usd,
                 COALESCE(SUM(CASE WHEN um.currency = 'AFS' THEN um.profit ELSE 0 END), 0) as umrah_profit_afs,
                 COUNT(DISTINCT ap.id) as total_additional_payments,
                 COALESCE(SUM(CASE WHEN ap.currency = 'USD' THEN ap.profit ELSE 0 END), 0) as additional_profit_usd,
                 COALESCE(SUM(CASE WHEN ap.currency = 'AFS' THEN ap.profit ELSE 0 END), 0) as additional_profit_afs
             FROM branches b
             LEFT JOIN ticket_bookings tb ON b.id = tb.branch_id AND tb.tenant_id = ? AND tb.created_at BETWEEN ? AND ?
             LEFT JOIN ticket_reservations tr ON b.id = tr.branch_id AND tr.tenant_id = ? AND tr.created_at BETWEEN ? AND ?
             LEFT JOIN ticket_weights tw ON b.id = tw.branch_id AND tw.tenant_id = ? AND tw.created_at BETWEEN ? AND ?
             LEFT JOIN ticket_bookings tb3 ON tw.ticket_id = tb3.id AND tb3.tenant_id = ?
             LEFT JOIN refunded_tickets rt ON b.id = rt.branch_id AND rt.tenant_id = ? AND rt.created_at BETWEEN ? AND ?
             LEFT JOIN ticket_bookings tb2 ON rt.ticket_id = tb2.id AND tb2.tenant_id = ?
             LEFT JOIN date_change_tickets dc ON b.id = dc.branch_id AND dc.tenant_id = ? AND dc.created_at BETWEEN ? AND ?
             LEFT JOIN hotel_bookings h ON b.id = h.branch_id AND h.tenant_id = ? AND h.created_at BETWEEN ? AND ?
             LEFT JOIN visa_applications v ON b.id = v.branch_id AND v.tenant_id = ? AND v.created_at BETWEEN ? AND ?
             LEFT JOIN umrah_bookings um ON b.id = um.branch_id AND um.tenant_id = ? AND um.created_at BETWEEN ? AND ?
             LEFT JOIN additional_payments ap ON b.id = ap.branch_id AND ap.tenant_id = ? AND ap.created_at BETWEEN ? AND ?
             WHERE b.tenant_id = ? AND b.status = 'active'
             GROUP BY b.id, b.name, b.code
             ORDER BY (COALESCE(SUM(CASE WHEN tb.currency = 'USD' THEN tb.profit ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN um.currency = 'USD' THEN um.profit ELSE 0 END), 0)) DESC
         ";

         $stmt = $this->pdo->prepare($query);
         $stmt->execute([
             $tenantId, $startDate, $endDate,
             $tenantId, $startDate, $endDate,
             $tenantId, $startDate, $endDate,
             $tenantId,
             $tenantId, $startDate, $endDate,
             $tenantId,
             $tenantId, $startDate, $endDate,
             $tenantId, $startDate, $endDate,
             $tenantId, $startDate, $endDate,
             $tenantId, $startDate, $endDate,
             $tenantId, $startDate, $endDate,
             $tenantId
         ]);

         return $stmt->fetchAll(PDO::FETCH_ASSOC);
     }

    /**
     * Get top clients (by ticket sales amount)
     */
    private function getTopClients($tenantId, $startDate, $endDate, $limit = 10) {
        $limit = (int)$limit; // Ensure limit is integer
        $query = "
            SELECT
                c.id,
                c.name,
                c.phone,
                COUNT(DISTINCT tb.id) as booking_count,
                COUNT(DISTINCT CASE WHEN tb.id IS NOT NULL THEN tb.id END) as tickets_purchased,
                COALESCE(SUM(CASE WHEN tb.currency = 'USD' THEN tb.profit ELSE 0 END), 0) as total_spent
            FROM clients c
            LEFT JOIN ticket_bookings tb ON c.id = tb.sold_to AND tb.tenant_id = ? AND tb.created_at BETWEEN ? AND ?
            WHERE c.tenant_id = ? AND tb.id IS NOT NULL
            GROUP BY c.id, c.name, c.phone
            ORDER BY total_spent DESC
            LIMIT " . $limit . "
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$tenantId, $startDate, $endDate, $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top suppliers (by transaction volume)
     */
    private function getTopSuppliers($tenantId, $startDate, $endDate, $limit = 10) {
        $limit = (int)$limit; // Ensure limit is integer
        $query = "
            SELECT
                s.id,
                s.name,
                s.contact_person,
                s.phone,
                COUNT(DISTINCT CASE WHEN h.id IS NOT NULL THEN h.id END) as hotel_bookings,
                COALESCE(SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END), 0) as hotel_revenue,
                COUNT(DISTINCT CASE WHEN v.id IS NOT NULL THEN v.id END) as visa_services,
                COALESCE(SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END), 0) as visa_revenue,
                (COALESCE(SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END), 0) +
                 COALESCE(SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END), 0)) as total_revenue
            FROM suppliers s
            LEFT JOIN hotel_bookings h ON s.id = h.supplier_id AND h.tenant_id = ? AND h.created_at BETWEEN ? AND ?
            LEFT JOIN visa_applications v ON s.id = v.supplier AND v.tenant_id = ? AND v.created_at BETWEEN ? AND ?
            WHERE s.tenant_id = ? AND (h.id IS NOT NULL OR v.id IS NOT NULL)
            GROUP BY s.id, s.name, s.contact_person, s.phone
            ORDER BY total_revenue DESC
            LIMIT " . $limit . "
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$tenantId, $startDate, $endDate, $tenantId, $startDate, $endDate, $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get financial summary for the period
     */
    private function getFinancialSummary($tenantId, $startDate, $endDate) {
        $query = "
            SELECT
                COALESCE(SUM(CASE WHEN tb.currency = 'USD' THEN tb.profit ELSE 0 END), 0) as ticket_profit,
                COALESCE(SUM(CASE WHEN tb.currency = 'AFS' THEN tb.profit ELSE 0 END), 0) as ticket_afs_profit,
                COUNT(DISTINCT tb.id) as total_tickets_sold,
                COALESCE(SUM(CASE WHEN tr.currency = 'USD' THEN tr.profit ELSE 0 END), 0) as ticket_reservation_profit,
                COALESCE(SUM(CASE WHEN tr.currency = 'AFS' THEN tr.profit ELSE 0 END), 0) as ticket_reservation_afs_profit,
                COUNT(DISTINCT tr.id) as total_ticket_reservations,
                COALESCE(SUM(CASE WHEN tb3.currency = 'USD' THEN tw.profit ELSE 0 END), 0) as ticket_weight_profit,
                COALESCE(SUM(CASE WHEN tb3.currency = 'AFS' THEN tw.profit ELSE 0 END), 0) as ticket_weight_afs_profit,
                COUNT(DISTINCT tw.id) as total_ticket_weights,
                COALESCE(SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END), 0) as hotel_profit,
                COALESCE(SUM(CASE WHEN h.currency = 'AFS' THEN h.profit ELSE 0 END), 0) as hotel_afs_profit,
                COUNT(DISTINCT h.id) as total_hotels,
                COALESCE(SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END), 0) as visa_profit,
                COALESCE(SUM(CASE WHEN v.currency = 'AFS' THEN v.profit ELSE 0 END), 0) as visa_afs_profit,
                COUNT(DISTINCT v.id) as total_visas,
                COALESCE(SUM(CASE WHEN um.currency = 'USD' THEN um.profit ELSE 0 END), 0) as umrah_profit,
                COALESCE(SUM(CASE WHEN um.currency = 'AFS' THEN um.profit ELSE 0 END), 0) as umrah_afs_profit,
                COUNT(DISTINCT um.booking_id) as total_umrah,
                COALESCE(SUM(CASE WHEN ap.currency = 'USD' THEN ap.profit ELSE 0 END), 0) as additional_profit,
                COALESCE(SUM(CASE WHEN ap.currency = 'AFS' THEN ap.profit ELSE 0 END), 0) as additional_afs_profit,
                COALESCE(SUM(CASE WHEN rt.currency = 'USD' THEN
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb2.profit, 0))
                          ELSE rt.service_penalty END)
                    ELSE 0 END), 0) as refunded_tickets_usd_profit,
                COALESCE(SUM(CASE WHEN rt.currency = 'AFS' THEN
                    (CASE WHEN rt.calculation_method = 'base' THEN rt.service_penalty
                          WHEN rt.calculation_method = 'sold' THEN (rt.service_penalty - COALESCE(tb2.profit, 0))
                          ELSE rt.service_penalty END)
                    ELSE 0 END), 0) as refunded_tickets_afs_profit,
                COUNT(DISTINCT rt.id) as total_refunded_tickets,
                COALESCE(SUM(CASE WHEN dc.currency = 'USD' THEN dc.service_penalty ELSE 0 END), 0) as date_change_usd_profit,
                COALESCE(SUM(CASE WHEN dc.currency = 'AFS' THEN dc.service_penalty ELSE 0 END), 0) as date_change_afs_profit,
                COUNT(DISTINCT dc.id) as total_date_changes
            FROM (SELECT ? as tenant_id) as t
            LEFT JOIN ticket_bookings tb ON tb.tenant_id = t.tenant_id AND tb.created_at BETWEEN ? AND ?
            LEFT JOIN ticket_reservations tr ON tr.tenant_id = t.tenant_id AND tr.created_at BETWEEN ? AND ?
            LEFT JOIN ticket_weights tw ON tw.tenant_id = t.tenant_id AND tw.created_at BETWEEN ? AND ?
            LEFT JOIN ticket_bookings tb3 ON tw.ticket_id = tb3.id AND tb3.tenant_id = t.tenant_id
            LEFT JOIN hotel_bookings h ON h.tenant_id = t.tenant_id AND h.created_at BETWEEN ? AND ?
            LEFT JOIN visa_applications v ON v.tenant_id = t.tenant_id AND v.created_at BETWEEN ? AND ?
            LEFT JOIN umrah_bookings um ON um.tenant_id = t.tenant_id AND um.created_at BETWEEN ? AND ?
            LEFT JOIN additional_payments ap ON ap.tenant_id = t.tenant_id AND ap.created_at BETWEEN ? AND ?
            LEFT JOIN refunded_tickets rt ON rt.tenant_id = t.tenant_id AND rt.created_at BETWEEN ? AND ?
            LEFT JOIN ticket_bookings tb2 ON rt.ticket_id = tb2.id AND tb2.tenant_id = t.tenant_id
            LEFT JOIN date_change_tickets dc ON dc.tenant_id = t.tenant_id AND dc.created_at BETWEEN ? AND ?
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            $tenantId,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate
        ]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate totals by combining USD and AFS for all services
        $summary['ticket_profit_total'] = 
            ($summary['ticket_profit'] ?? 0) +
            ($summary['ticket_afs_profit'] ?? 0);
        
        $summary['ticket_reservation_profit_total'] = 
            ($summary['ticket_reservation_profit'] ?? 0) +
            ($summary['ticket_reservation_afs_profit'] ?? 0);
        
        $summary['ticket_weight_profit_total'] = 
            ($summary['ticket_weight_profit'] ?? 0) +
            ($summary['ticket_weight_afs_profit'] ?? 0);
        
        $summary['hotel_profit_total'] = 
            ($summary['hotel_profit'] ?? 0) +
            ($summary['hotel_afs_profit'] ?? 0);
        
        $summary['visa_profit_total'] = 
            ($summary['visa_profit'] ?? 0) +
            ($summary['visa_afs_profit'] ?? 0);
        
        $summary['umrah_profit_total'] = 
            ($summary['umrah_profit'] ?? 0) +
            ($summary['umrah_afs_profit'] ?? 0);
        
        $summary['additional_profit_total'] = 
            ($summary['additional_profit'] ?? 0) +
            ($summary['additional_afs_profit'] ?? 0);
        
        $summary['refunded_tickets_profit'] = 
            ($summary['refunded_tickets_usd_profit'] ?? 0) +
            ($summary['refunded_tickets_afs_profit'] ?? 0);
        
        $summary['date_change_profit'] = 
            ($summary['date_change_usd_profit'] ?? 0) +
            ($summary['date_change_afs_profit'] ?? 0);
        
        $summary['total_profit'] =
            $summary['ticket_profit_total'] +
            $summary['ticket_reservation_profit_total'] +
            $summary['ticket_weight_profit_total'] +
            $summary['hotel_profit_total'] +
            $summary['visa_profit_total'] +
            $summary['umrah_profit_total'] +
            $summary['additional_profit_total'] +
            $summary['refunded_tickets_profit'] +
            $summary['date_change_profit'];

        // Calculate separate USD and AFS totals
        $summary['total_usd_profit'] =
            ($summary['ticket_profit'] ?? 0) +
            ($summary['ticket_reservation_profit'] ?? 0) +
            ($summary['ticket_weight_profit'] ?? 0) +
            ($summary['hotel_profit'] ?? 0) +
            ($summary['visa_profit'] ?? 0) +
            ($summary['umrah_profit'] ?? 0) +
            ($summary['additional_profit'] ?? 0) +
            ($summary['refunded_tickets_usd_profit'] ?? 0) +
            ($summary['date_change_usd_profit'] ?? 0);

        $summary['total_afs_profit'] =
            ($summary['ticket_afs_profit'] ?? 0) +
            ($summary['ticket_reservation_afs_profit'] ?? 0) +
            ($summary['ticket_weight_afs_profit'] ?? 0) +
            ($summary['hotel_afs_profit'] ?? 0) +
            ($summary['visa_afs_profit'] ?? 0) +
            ($summary['umrah_afs_profit'] ?? 0) +
            ($summary['additional_afs_profit'] ?? 0) +
            ($summary['refunded_tickets_afs_profit'] ?? 0) +
            ($summary['date_change_afs_profit'] ?? 0);

        return $summary;
    }

    /**
     * Get branch comparison data
     */
    private function getBranchComparison($tenantId, $startDate, $endDate) {
         $branches = $this->getBranchData($tenantId, $startDate, $endDate);
         
         $comparison = [];
         foreach ($branches as $branch) {
             $totalProfitUSD = 
                 ($branch['ticket_profit_usd'] ?? 0) +
                 ($branch['ticket_reservation_profit_usd'] ?? 0) +
                 ($branch['ticket_weight_profit_usd'] ?? 0) +
                 ($branch['refunded_tickets_profit_usd'] ?? 0) +
                 ($branch['date_change_profit_usd'] ?? 0) +
                 ($branch['hotel_profit_usd'] ?? 0) +
                 ($branch['visa_profit_usd'] ?? 0) +
                 ($branch['umrah_profit_usd'] ?? 0) +
                 ($branch['additional_profit_usd'] ?? 0);
             
             $totalProfitAFS = 
                 ($branch['ticket_profit_afs'] ?? 0) +
                 ($branch['ticket_reservation_profit_afs'] ?? 0) +
                 ($branch['ticket_weight_profit_afs'] ?? 0) +
                 ($branch['refunded_tickets_profit_afs'] ?? 0) +
                 ($branch['date_change_profit_afs'] ?? 0) +
                 ($branch['hotel_profit_afs'] ?? 0) +
                 ($branch['visa_profit_afs'] ?? 0) +
                 ($branch['umrah_profit_afs'] ?? 0) +
                 ($branch['additional_profit_afs'] ?? 0);

             $comparison[] = [
                 'branch_name' => $branch['name'],
                 'branch_code' => $branch['code'],
                 'ticket_profit_usd' => $branch['ticket_profit_usd'],
                 'ticket_profit_afs' => $branch['ticket_profit_afs'],
                 'ticket_reservation_profit_usd' => $branch['ticket_reservation_profit_usd'],
                 'ticket_reservation_profit_afs' => $branch['ticket_reservation_profit_afs'],
                 'ticket_weight_profit_usd' => $branch['ticket_weight_profit_usd'],
                 'ticket_weight_profit_afs' => $branch['ticket_weight_profit_afs'],
                 'refunded_tickets_profit_usd' => $branch['refunded_tickets_profit_usd'],
                 'refunded_tickets_profit_afs' => $branch['refunded_tickets_profit_afs'],
                 'date_change_profit_usd' => $branch['date_change_profit_usd'],
                 'date_change_profit_afs' => $branch['date_change_profit_afs'],
                 'hotel_profit_usd' => $branch['hotel_profit_usd'],
                 'hotel_profit_afs' => $branch['hotel_profit_afs'],
                 'visa_profit_usd' => $branch['visa_profit_usd'],
                 'visa_profit_afs' => $branch['visa_profit_afs'],
                 'umrah_profit_usd' => $branch['umrah_profit_usd'],
                 'umrah_profit_afs' => $branch['umrah_profit_afs'],
                 'additional_profit_usd' => $branch['additional_profit_usd'],
                 'additional_profit_afs' => $branch['additional_profit_afs'],
                 'total_profit_usd' => $totalProfitUSD,
                 'total_profit_afs' => $totalProfitAFS
             ];
         }

         return $comparison;
     }

    /**
     * Generate comprehensive Excel report using existing export_comprehensive_report logic
     * @param int $tenantId
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return string Path to generated Excel file
     */
    public function generateExcelReport($tenantId, $startDate, $endDate) {
        try {
            // Use the existing export_comprehensive_report logic via AJAX simulation
            $excelPath = $this->generateExcelReportViaExistingScript($tenantId, $startDate, $endDate);
            
            if (!$excelPath) {
                error_log("Failed to generate Excel report using existing script");
                return false;
            }
            
            return $excelPath;
        } catch (Exception $e) {
            error_log("Excel Report Generation Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate comprehensive Excel report using the existing export_comprehensive_report.php logic
     * @param int $tenantId
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return string|false Path to generated Excel file or false on failure
     */
    private function generateExcelReportViaExistingScript($tenantId, $startDate, $endDate) {
        try {
            // Path to the existing export script
            $exportScriptPath = dirname(dirname(__FILE__)) . "/tenant_super_admin/export_comprehensive_report.php";
            
            if (!file_exists($exportScriptPath)) {
                error_log("Export script not found at: $exportScriptPath");
                return false;
            }

            // Temporarily set session variables for the script
            $_SESSION['tenant_id'] = $tenantId;
            $_GET['startDate'] = $startDate;
            $_GET['endDate'] = $endDate;
            
            // Start output buffering to capture JSON response
            ob_start();
            
            // Include and execute the export script
            include $exportScriptPath;
            
            // Get the output
            $output = ob_get_clean();
            
            // Parse JSON response
            $response = json_decode($output, true);
            
            if (!$response || !$response['success']) {
                error_log("Excel generation failed: " . ($response['message'] ?? 'Unknown error'));
                return false;
            }
            
            // Decode base64 file content
            $fileContent = base64_decode($response['file']);
            
            if ($fileContent === false) {
                error_log("Failed to decode base64 file content");
                return false;
            }
            
            // Save to temporary file
            $filename = $this->tempDir . '/comprehensive_report_' . $tenantId . '_' . date('Y-m-d_His') . '.xlsx';
            
            if (file_put_contents($filename, $fileContent) === false) {
                error_log("Failed to write Excel file to: $filename");
                return false;
            }
            
            return $filename;
        } catch (Exception $e) {
            error_log("Error generating Excel report via existing script: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Old method - kept for reference, now replaced by generateExcelReportViaExistingScript
     * Generate comprehensive Excel report using PhpSpreadsheet directly
     * @param int $tenantId
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return string Path to generated Excel file
     */
    private function generateExcelReportDirect($tenantId, $startDate, $endDate) {
        try {
            // Get branches for the tenant
            try {
                $stmt = $this->pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name");
                $stmt->execute([$tenantId]);
                $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error fetching branches for tenant $tenantId: " . $e->getMessage());
                return false;
            }
            
            if (empty($branches)) {
                error_log("No branches found for tenant: $tenantId");
                return false;
            }
            
            // Initialize spreadsheet
            $spreadsheet = new Spreadsheet();
            $spreadsheet->getProperties()
                ->setCreator('Travel Agency Financial System')
                ->setLastModifiedBy('Travel Agency Financial System')
                ->setTitle('Comprehensive Financial Report - ' . date('F Y', strtotime($startDate)))
                ->setSubject('Monthly Financial Report')
                ->setDescription('Comprehensive financial report with income, expenses and profit/loss');
            
            $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
            
            // Styles
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '4472C4']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
            ];
            
            $dataStyle = [
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
            ];
            
            $currencyFormat = '#,##0.00_-';
            
            // Create sheet for each branch
            $comparisonData = [];
            $sheetIndex = 0;
            
            foreach ($branches as $branch) {
                if ($sheetIndex === 0) {
                    $sheet = $spreadsheet->getActiveSheet();
                } else {
                    $sheet = $spreadsheet->createSheet();
                }
                
                $sheet->setTitle(substr($branch['name'], 0, 31)); // Excel sheet name limit
                
                // Headers
                $sheet->setCellValue('A1', 'FINANCIAL REPORT - ' . strtoupper($branch['name']));
                $sheet->mergeCells('A1:D1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $sheet->setCellValue('A2', 'Date Range: ' . date('d/m/Y', strtotime($startDate)) . ' to ' . date('d/m/Y', strtotime($endDate)));
                $sheet->mergeCells('A2:D2');
                $sheet->getStyle('A2')->getFont()->setBold(true);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                // Financial data by service type
                $sheet->setCellValue('A4', 'SUMMARY BY SERVICE');
                $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(14);
                
                $sheet->setCellValue('A6', 'Service Type');
                $sheet->setCellValue('B6', 'Transactions');
                $sheet->setCellValue('C6', 'Profit (USD)');
                $sheet->setCellValue('D6', 'Profit (AFS)');
                $sheet->getStyle('A6:D6')->applyFromArray($headerStyle);
                
                // Query service data for branch
                try {
                    $serviceData = $this->getBranchServiceBreakdown($tenantId, $branch['id'], $startDate, $endDate);
                } catch (Exception $e) {
                    error_log("Error generating service breakdown for branch {$branch['id']}: " . $e->getMessage());
                    $sheet->setCellValue('A7', 'ERROR - Unable to fetch service data');
                    $sheet->setCellValue('B7', $e->getMessage());
                    $sheet->getStyle('A7:D7')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF0000'));
                    $serviceData = [];
                }
                
                $row = 7;
                foreach ($serviceData as $service) {
                    $sheet->setCellValue('A' . $row, $service['service_type']);
                    $sheet->setCellValue('B' . $row, $service['count']);
                    $sheet->setCellValue('C' . $row, $service['usd_profit']);
                    $sheet->setCellValue('D' . $row, $service['afs_profit']);
                    $sheet->getStyle('C' . $row . ':D' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
                    $row++;
                }
                
                // Totals
                $sheet->setCellValue('A' . $row, 'TOTAL');
                $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
                $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($dataStyle);
                
                // Auto-size columns
                foreach (range('A', 'D') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                
                $comparisonData[$branch['name']] = $serviceData;
                $sheetIndex++;
            }
            
            // Create comparison sheet if multiple branches
            if (count($branches) > 1) {
                $compSheet = $spreadsheet->createSheet();
                $compSheet->setTitle('Comparison');
                
                $compSheet->setCellValue('A1', 'BRANCH COMPARISON');
                $compSheet->mergeCells('A1:E1');
                $compSheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $compSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $compSheet->setCellValue('A2', 'Date Range: ' . date('d/m/Y', strtotime($startDate)) . ' to ' . date('d/m/Y', strtotime($endDate)));
                $compSheet->mergeCells('A2:E2');
                $compSheet->getStyle('A2')->getFont()->setBold(true);
                
                $compSheet->setCellValue('A4', 'Branch Name');
                $compSheet->setCellValue('B4', 'Total Transactions');
                $compSheet->setCellValue('C4', 'Total Profit (USD)');
                $compSheet->setCellValue('D4', 'Total Profit (AFS)');
                $compSheet->setCellValue('E4', 'Total Profit');
                $compSheet->getStyle('A4:E4')->applyFromArray($headerStyle);
                
                $row = 5;
                foreach ($comparisonData as $branchName => $services) {
                    $totalTrans = array_sum(array_column($services, 'count'));
                    $totalUSD = array_sum(array_column($services, 'usd_profit'));
                    $totalAFS = array_sum(array_column($services, 'afs_profit'));
                    
                    $compSheet->setCellValue('A' . $row, $branchName);
                    $compSheet->setCellValue('B' . $row, $totalTrans);
                    $compSheet->setCellValue('C' . $row, $totalUSD);
                    $compSheet->setCellValue('D' . $row, $totalAFS);
                    $compSheet->setCellValue('E' . $row, $totalUSD + $totalAFS);
                    $compSheet->getStyle('C' . $row . ':E' . $row)->getNumberFormat()->setFormatCode($currencyFormat);
                    $row++;
                }
                
                foreach (range('A', 'E') as $col) {
                    $compSheet->getColumnDimension($col)->setAutoSize(true);
                }
            }
            
            // Save to file
            $writer = new Xlsx($spreadsheet);
            $filename = $this->tempDir . '/comprehensive_report_' . $tenantId . '_' . date('Y-m-d') . '.xlsx';
            $writer->save($filename);
            
            return $filename;
        } catch (Exception $e) {
            error_log("Excel generation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get service breakdown for a branch with comprehensive error logging
     */
    private function getBranchServiceBreakdown($tenantId, $branchId, $startDate, $endDate) {
        $data = [];
        $errors = [];
        
        // Ticket Bookings
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM ticket_bookings WHERE tenant_id = ? AND branch_id = ? AND created_at BETWEEN ? AND ?');
            $stmt->execute([$tenantId, $branchId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = ['service_type' => 'Ticket Bookings', 'count' => $result['count'] ?? 0, 'usd_profit' => $result['usd_profit'] ?? 0, 'afs_profit' => $result['afs_profit'] ?? 0];
        } catch (Exception $e) {
            $error = "Error querying table 'ticket_bookings': " . $e->getMessage();
            error_log($error);
            $errors[] = $error;
            $data[] = ['service_type' => 'Ticket Bookings', 'count' => 0, 'usd_profit' => 0, 'afs_profit' => 0];
        }
        
        // Ticket Reservations
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM ticket_reservations WHERE tenant_id = ? AND branch_id = ? AND created_at BETWEEN ? AND ?');
            $stmt->execute([$tenantId, $branchId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = ['service_type' => 'Ticket Reservations', 'count' => $result['count'] ?? 0, 'usd_profit' => $result['usd_profit'] ?? 0, 'afs_profit' => $result['afs_profit'] ?? 0];
        } catch (Exception $e) {
            $error = "Error querying table 'ticket_reservations': " . $e->getMessage();
            error_log($error);
            $errors[] = $error;
            $data[] = ['service_type' => 'Ticket Reservations', 'count' => 0, 'usd_profit' => 0, 'afs_profit' => 0];
        }
        
        // Ticket Weights
        try {
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*) as count, 
                       SUM(CASE WHEN tb.currency = "USD" THEN tw.profit ELSE 0 END) as usd_profit, 
                       SUM(CASE WHEN tb.currency = "AFS" THEN tw.profit ELSE 0 END) as afs_profit 
                FROM ticket_weights tw
                LEFT JOIN ticket_bookings tb ON tw.ticket_id = tb.id AND tb.tenant_id = ? AND tb.branch_id = ?
                WHERE tw.tenant_id = ? AND tw.branch_id = ? AND tw.created_at BETWEEN ? AND ?
            ');
            $stmt->execute([$tenantId, $branchId, $tenantId, $branchId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = ['service_type' => 'Ticket Weights', 'count' => $result['count'] ?? 0, 'usd_profit' => $result['usd_profit'] ?? 0, 'afs_profit' => $result['afs_profit'] ?? 0];
        } catch (Exception $e) {
            $error = "Error querying table 'ticket_weights' with JOIN to 'ticket_bookings': " . $e->getMessage();
            error_log($error);
            $errors[] = $error;
            $data[] = ['service_type' => 'Ticket Weights', 'count' => 0, 'usd_profit' => 0, 'afs_profit' => 0];
        }
        
        // Hotels
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM hotel_bookings WHERE tenant_id = ? AND branch_id = ? AND created_at BETWEEN ? AND ?');
            $stmt->execute([$tenantId, $branchId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = ['service_type' => 'Hotels', 'count' => $result['count'] ?? 0, 'usd_profit' => $result['usd_profit'] ?? 0, 'afs_profit' => $result['afs_profit'] ?? 0];
        } catch (Exception $e) {
            $error = "Error querying table 'hotel_bookings': " . $e->getMessage();
            error_log($error);
            $errors[] = $error;
            $data[] = ['service_type' => 'Hotels', 'count' => 0, 'usd_profit' => 0, 'afs_profit' => 0];
        }
        
        // Visas
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM visa_applications WHERE tenant_id = ? AND branch_id = ? AND created_at BETWEEN ? AND ?');
            $stmt->execute([$tenantId, $branchId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = ['service_type' => 'Visas', 'count' => $result['count'] ?? 0, 'usd_profit' => $result['usd_profit'] ?? 0, 'afs_profit' => $result['afs_profit'] ?? 0];
        } catch (Exception $e) {
            $error = "Error querying table 'visa_applications': " . $e->getMessage();
            error_log($error);
            $errors[] = $error;
            $data[] = ['service_type' => 'Visas', 'count' => 0, 'usd_profit' => 0, 'afs_profit' => 0];
        }
        
        // Umrah
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM umrah_bookings WHERE tenant_id = ? AND branch_id = ? AND created_at BETWEEN ? AND ?');
            $stmt->execute([$tenantId, $branchId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = ['service_type' => 'Umrah', 'count' => $result['count'] ?? 0, 'usd_profit' => $result['usd_profit'] ?? 0, 'afs_profit' => $result['afs_profit'] ?? 0];
        } catch (Exception $e) {
            $error = "Error querying table 'umrah_bookings': " . $e->getMessage();
            error_log($error);
            $errors[] = $error;
            $data[] = ['service_type' => 'Umrah', 'count' => 0, 'usd_profit' => 0, 'afs_profit' => 0];
        }
        
        // Additional Payments
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM additional_payments WHERE tenant_id = ? AND branch_id = ? AND created_at BETWEEN ? AND ?');
            $stmt->execute([$tenantId, $branchId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = ['service_type' => 'Additional Payments', 'count' => $result['count'] ?? 0, 'usd_profit' => $result['usd_profit'] ?? 0, 'afs_profit' => $result['afs_profit'] ?? 0];
        } catch (Exception $e) {
            $error = "Error querying table 'additional_payments': " . $e->getMessage();
            error_log($error);
            $errors[] = $error;
            $data[] = ['service_type' => 'Additional Payments', 'count' => 0, 'usd_profit' => 0, 'afs_profit' => 0];
        }
        
        // Refunded Tickets - with proper profit calculation based on calculation_method
        try {
            $stmt = $this->pdo->prepare('
                SELECT 
                    COUNT(rt.id) as count,
                    SUM(CASE WHEN rt.currency = "USD" THEN
                        (CASE WHEN rt.calculation_method = "base" THEN rt.service_penalty
                              WHEN rt.calculation_method = "sold" THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                              ELSE rt.service_penalty END)
                        ELSE 0 END) as usd_profit,
                    SUM(CASE WHEN rt.currency = "AFS" THEN
                        (CASE WHEN rt.calculation_method = "base" THEN rt.service_penalty
                              WHEN rt.calculation_method = "sold" THEN (rt.service_penalty - COALESCE(tb.profit, 0))
                              ELSE rt.service_penalty END)
                        ELSE 0 END) as afs_profit
                FROM refunded_tickets rt
                LEFT JOIN ticket_bookings tb ON rt.ticket_id = tb.id AND tb.tenant_id = ? AND tb.branch_id = ?
                WHERE rt.tenant_id = ? AND rt.branch_id = ? AND rt.created_at BETWEEN ? AND ? 
            ');
            $stmt->execute([$tenantId, $branchId, $tenantId, $branchId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = ['service_type' => 'Refunded Tickets', 'count' => $result['count'] ?? 0, 'usd_profit' => $result['usd_profit'] ?? 0, 'afs_profit' => $result['afs_profit'] ?? 0];
        } catch (Exception $e) {
            $error = "Error querying table 'refunded_tickets' with JOIN to 'ticket_bookings': " . $e->getMessage();
            error_log($error);
            $errors[] = $error;
            $data[] = ['service_type' => 'Refunded Tickets', 'count' => 0, 'usd_profit' => 0, 'afs_profit' => 0];
        }
        
        // Date Changes
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN service_penalty ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN service_penalty ELSE 0 END) as afs_profit FROM date_change_tickets WHERE tenant_id = ? AND branch_id = ? AND created_at BETWEEN ? AND ?');
            $stmt->execute([$tenantId, $branchId, $startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = ['service_type' => 'Date Changes', 'count' => $result['count'] ?? 0, 'usd_profit' => $result['usd_profit'] ?? 0, 'afs_profit' => $result['afs_profit'] ?? 0];
        } catch (Exception $e) {
            $error = "Error querying table 'date_change_tickets': " . $e->getMessage();
            error_log($error);
            $errors[] = $error;
            $data[] = ['service_type' => 'Date Changes', 'count' => 0, 'usd_profit' => 0, 'afs_profit' => 0];
        }
        
        // Log summary of errors if any occurred
        if (!empty($errors)) {
            error_log("Service breakdown query errors for Tenant: $tenantId, Branch: $branchId, Period: $startDate to $endDate | Errors: " . implode(" | ", $errors));
        }
        
        return $data;
    }

    /**
     * Generate PDF report
     * @param array $reportData
     * @param int $tenantId
     * @param string $tenantName
     * @return string Path to generated PDF
     */
    public function generatePDF($reportData, $tenantId, $tenantName) {
        try {
            // TCPDF defines its own constants in config file, use defaults directly
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Set margins
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetHeaderMargin(10);
            $pdf->SetFooterMargin(10);
            
            // Add a page
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'Monthly Profit Report', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, $reportData['month'], 0, 1, 'C');
            $pdf->Cell(0, 5, 'Company: ' . htmlspecialchars($tenantName), 0, 1, 'C');
            $pdf->Ln(5);
            
            // Financial Summary Section
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Financial Summary', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            
            $summary = $reportData['financial_summary'];
            $summaryText = "
            Total Profit: \$" . number_format($summary['total_usd_profit'], 2) . " USD + \$" . number_format($summary['total_afs_profit'], 2) . " AFS
            Ticket Bookings Profit: \$" . number_format(($summary['ticket_profit'] ?? 0) + ($summary['ticket_afs_profit'] ?? 0), 2) . " (" . ($summary['total_tickets_sold'] ?? 0) . " bookings)
            Ticket Reservations Profit: \$" . number_format(($summary['ticket_reservation_profit'] ?? 0) + ($summary['ticket_reservation_afs_profit'] ?? 0), 2) . " (" . ($summary['total_ticket_reservations'] ?? 0) . " reservations)
            Ticket Weights Profit: \$" . number_format(($summary['ticket_weight_profit'] ?? 0) + ($summary['ticket_weight_afs_profit'] ?? 0), 2) . " (" . ($summary['total_ticket_weights'] ?? 0) . " weights)
            Refunded Tickets Profit: \$" . number_format(($summary['refunded_tickets_usd_profit'] ?? 0) + ($summary['refunded_tickets_afs_profit'] ?? 0), 2) . " (" . ($summary['total_refunded_tickets'] ?? 0) . " refunds)
            Date Changes Profit: \$" . number_format(($summary['date_change_usd_profit'] ?? 0) + ($summary['date_change_afs_profit'] ?? 0), 2) . " (" . ($summary['total_date_changes'] ?? 0) . " changes)
            Hotel Profit: \$" . number_format(($summary['hotel_profit'] ?? 0) + ($summary['hotel_afs_profit'] ?? 0), 2) . " (" . ($summary['total_hotels'] ?? 0) . " bookings)
            Visa Profit: \$" . number_format(($summary['visa_profit'] ?? 0) + ($summary['visa_afs_profit'] ?? 0), 2) . " (" . ($summary['total_visas'] ?? 0) . " applications)
            Umrah Profit: \$" . number_format(($summary['umrah_profit'] ?? 0) + ($summary['umrah_afs_profit'] ?? 0), 2) . " (" . ($summary['total_umrah'] ?? 0) . " bookings)
            Additional Payments Profit: \$" . number_format(($summary['additional_profit'] ?? 0) + ($summary['additional_afs_profit'] ?? 0), 2) . "
            ";
            
            $pdf->MultiCell(0, 4, $summaryText, 0, 'L');
            $pdf->Ln(3);
            
            // Branch Comparison Table - USD
             $pdf->SetFont('helvetica', 'B', 12);
             $pdf->Cell(0, 8, 'Branch Comparison - USD', 0, 1, 'L');
             $pdf->SetFont('helvetica', '', 7);
             
             // Table header
             $pdf->SetFillColor(200, 200, 200);
             $pdf->Cell(18, 6, 'Branch', 1, 0, 'L', true);
             $pdf->Cell(12, 6, 'Tickets', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'T.Res', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'T.Wgt', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Refund', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'D.Chg', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Hotels', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Visas', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Umrah', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Add.Pay', 1, 0, 'C', true);
             $pdf->Cell(16, 6, 'Total USD', 1, 1, 'R', true);
             
             // Table rows
             $pdf->SetFillColor(255, 255, 255);
             foreach ($reportData['branch_comparison'] as $branch) {
                 // Check if we need a new page
                 if ($pdf->GetY() > 250) {
                     $pdf->AddPage();
                     // Repeat header on new page
                     $pdf->SetFillColor(200, 200, 200);
                     $pdf->Cell(18, 6, 'Branch', 1, 0, 'L', true);
                     $pdf->Cell(12, 6, 'Tickets', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'T.Res', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'T.Wgt', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Refund', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'D.Chg', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Hotels', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Visas', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Umrah', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Add.Pay', 1, 0, 'C', true);
                     $pdf->Cell(16, 6, 'Total USD', 1, 1, 'R', true);
                     $pdf->SetFillColor(255, 255, 255);
                 }
                 $pdf->Cell(18, 6, substr($branch['branch_name'], 0, 9), 1, 0, 'L');
                 $pdf->Cell(12, 6, '$' . number_format($branch['ticket_profit_usd'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['ticket_reservation_profit_usd'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['ticket_weight_profit_usd'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['refunded_tickets_profit_usd'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['date_change_profit_usd'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['hotel_profit_usd'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['visa_profit_usd'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['umrah_profit_usd'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['additional_profit_usd'], 0), 1, 0, 'R');
                 $pdf->Cell(16, 6, '$' . number_format($branch['total_profit_usd'], 0), 1, 1, 'R');
             }
             
             $pdf->Ln(3);
             
             // Branch Comparison Table - AFS
             $pdf->SetFont('helvetica', 'B', 12);
             $pdf->Cell(0, 8, 'Branch Comparison - AFS', 0, 1, 'L');
             $pdf->SetFont('helvetica', '', 7);
             
             // Table header
             $pdf->SetFillColor(200, 200, 200);
             $pdf->Cell(18, 6, 'Branch', 1, 0, 'L', true);
             $pdf->Cell(12, 6, 'Tickets', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'T.Res', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'T.Wgt', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Refund', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'D.Chg', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Hotels', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Visas', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Umrah', 1, 0, 'C', true);
             $pdf->Cell(12, 6, 'Add.Pay', 1, 0, 'C', true);
             $pdf->Cell(16, 6, 'Total AFS', 1, 1, 'R', true);
             
             // Table rows
             $pdf->SetFillColor(255, 255, 255);
             foreach ($reportData['branch_comparison'] as $branch) {
                 // Check if we need a new page
                 if ($pdf->GetY() > 250) {
                     $pdf->AddPage();
                     // Repeat header on new page
                     $pdf->SetFillColor(200, 200, 200);
                     $pdf->Cell(18, 6, 'Branch', 1, 0, 'L', true);
                     $pdf->Cell(12, 6, 'Tickets', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'T.Res', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'T.Wgt', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Refund', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'D.Chg', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Hotels', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Visas', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Umrah', 1, 0, 'C', true);
                     $pdf->Cell(12, 6, 'Add.Pay', 1, 0, 'C', true);
                     $pdf->Cell(16, 6, 'Total AFS', 1, 1, 'R', true);
                     $pdf->SetFillColor(255, 255, 255);
                 }
                 $pdf->Cell(18, 6, substr($branch['branch_name'], 0, 9), 1, 0, 'L');
                 $pdf->Cell(12, 6, '$' . number_format($branch['ticket_profit_afs'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['ticket_reservation_profit_afs'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['ticket_weight_profit_afs'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['refunded_tickets_profit_afs'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['date_change_profit_afs'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['hotel_profit_afs'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['visa_profit_afs'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['umrah_profit_afs'], 0), 1, 0, 'R');
                 $pdf->Cell(12, 6, '$' . number_format($branch['additional_profit_afs'], 0), 1, 0, 'R');
                 $pdf->Cell(16, 6, '$' . number_format($branch['total_profit_afs'], 0), 1, 1, 'R');
             }
            
            $pdf->Ln(5);
            
            // Service Breakdown Section
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Service Breakdown (by Branch)', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 8);
            
            foreach ($reportData['branches'] as $branch) {
                // Check if we need a new page
                if ($pdf->GetY() > 240) {
                    $pdf->AddPage();
                }
                
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->Cell(0, 6, $branch['name'], 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 8);
                
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(50, 5, 'Service Type', 1, 0, 'L', true);
                $pdf->Cell(25, 5, 'Transactions', 1, 0, 'C', true);
                $pdf->Cell(30, 5, 'Profit USD', 1, 0, 'R', true);
                $pdf->Cell(30, 5, 'Profit AFS', 1, 1, 'R', true);
                
                $pdf->SetFillColor(255, 255, 255);
                // Get service breakdown for this branch
                $branchServiceData = [];
                try {
                    // Extract start and end dates from period string (format: "YYYY-MM-DD to YYYY-MM-DD")
                    $dates = explode(' to ', $reportData['period']);
                    $startDate = $dates[0] ?? date('Y-m-01');
                    $endDate = $dates[1] ?? date('Y-m-t');
                    $branchServiceData = $this->getBranchServiceBreakdown($reportData['tenant_id'], $branch['id'], $startDate, $endDate);
                } catch (Exception $e) {
                    error_log("Error fetching service breakdown for branch {$branch['id']}: " . $e->getMessage());
                }
                
                foreach ($branchServiceData as $service) {
                    // Check page break within service rows too
                    if ($pdf->GetY() > 250) {
                        $pdf->AddPage();
                        $pdf->SetFillColor(200, 200, 200);
                        $pdf->Cell(50, 5, 'Service Type', 1, 0, 'L', true);
                        $pdf->Cell(25, 5, 'Transactions', 1, 0, 'C', true);
                        $pdf->Cell(30, 5, 'Profit USD', 1, 0, 'R', true);
                        $pdf->Cell(30, 5, 'Profit AFS', 1, 1, 'R', true);
                        $pdf->SetFillColor(255, 255, 255);
                    }
                    $pdf->Cell(50, 5, $service['service_type'], 1, 0, 'L');
                    $pdf->Cell(25, 5, $service['count'], 1, 0, 'C');
                    $pdf->Cell(30, 5, '$' . number_format($service['usd_profit'], 2), 1, 0, 'R');
                    $pdf->Cell(30, 5, '$' . number_format($service['afs_profit'], 2), 1, 1, 'R');
                }
                $pdf->Ln(3);
            }
            
            // Top Clients Section
            if (!empty($reportData['top_clients'])) {
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Cell(0, 8, 'Top 10 Clients', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 9);
                
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(40, 6, 'Client Name', 1, 0, 'L', true);
                $pdf->Cell(30, 6, 'Tickets', 1, 0, 'C', true);
                $pdf->Cell(45, 6, 'Total Spent', 1, 1, 'R', true);
                
                $pdf->SetFillColor(255, 255, 255);
                foreach (array_slice($reportData['top_clients'], 0, 5) as $client) {
                    $pdf->Cell(40, 6, substr($client['name'], 0, 20), 1, 0, 'L');
                    $pdf->Cell(30, 6, $client['tickets_purchased'], 1, 0, 'C');
                    $pdf->Cell(45, 6, '$' . number_format($client['total_spent'], 2), 1, 1, 'R');
                }
            }
            
            $pdf->Ln(5);
            
            // Top Suppliers Section
            if (!empty($reportData['top_suppliers'])) {
                $pdf->SetFont('helvetica', 'B', 12);
                $pdf->Cell(0, 8, 'Top 10 Suppliers', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 9);
                
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(40, 6, 'Supplier', 1, 0, 'L', true);
                $pdf->Cell(25, 6, 'Hotels', 1, 0, 'C', true);
                $pdf->Cell(25, 6, 'Visas', 1, 0, 'C', true);
                $pdf->Cell(35, 6, 'Total Revenue', 1, 1, 'R', true);
                
                $pdf->SetFillColor(255, 255, 255);
                foreach (array_slice($reportData['top_suppliers'], 0, 5) as $supplier) {
                    $totalRevenue = ($supplier['hotel_revenue'] ?? 0) + ($supplier['visa_revenue'] ?? 0);
                    $pdf->Cell(40, 6, substr($supplier['name'], 0, 20), 1, 0, 'L');
                    $pdf->Cell(25, 6, $supplier['hotel_bookings'] ?? 0, 1, 0, 'C');
                    $pdf->Cell(25, 6, $supplier['visa_services'] ?? 0, 1, 0, 'C');
                    $pdf->Cell(35, 6, '$' . number_format($totalRevenue, 2), 1, 1, 'R');
                }
            }
            
            // Generate filename
            $filename = $this->tempDir . '/monthly_report_' . $tenantId . '_' . date('Y-m-d') . '.pdf';
            
            // Output PDF
            $pdf->Output($filename, 'F');
            
            return $filename;
        } catch (Exception $e) {
            error_log("PDF Generation Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send report via email with Excel attachment using tenant SMTP
     * @param string $email Recipient email
     * @param string $name Recipient name
     * @param array $reportData Report data
     * @param string $excelPath Path to Excel file
     * @param string $pdfPath Path to PDF file (optional)
     * @param int $tenantId Tenant ID for SMTP config lookup
     * @return bool
     */
    public function sendReportEmail($email, $name, $reportData, $excelPath, $pdfPath = null, $tenantId = null) {
        try {
            // Try to use PHPMailer with tenant SMTP config if available
            if ($tenantId && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendEmailViaSMTP($email, $name, $reportData, $excelPath, $pdfPath, $tenantId);
            }
            
            // Fallback to default PHP mail
            return $this->sendEmailViaPhpMail($email, $name, $reportData, $excelPath, $pdfPath);
        } catch (Exception $e) {
            error_log("Email sending error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using tenant SMTP configuration
     */
    private function sendEmailViaSMTP($email, $name, $reportData, $excelPath, $pdfPath, $tenantId) {
        try {
            // Fetch tenant SMTP configuration
            $stmt = $this->pdo->prepare("
                SELECT smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, smtp_from_name, smtp_from_email, agency_name
                FROM settings
                WHERE tenant_id = ? AND smtp_host IS NOT NULL
            ");
            $stmt->execute([$tenantId]);
            $smtpConfig = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);
            
            if ($smtpConfig && $smtpConfig['smtp_host']) {
                // Use SMTP
                $mail->isSMTP();
                $mail->Host = $smtpConfig['smtp_host'];
                $mail->Port = $smtpConfig['smtp_port'] ?? 587;
                $mail->SMTPAuth = !empty($smtpConfig['smtp_username']);
                
                if ($mail->SMTPAuth) {
                    $mail->Username = $smtpConfig['smtp_username'];
                    $mail->Password = $smtpConfig['smtp_password'];
                }
                
                $encryption = strtolower($smtpConfig['smtp_encryption'] ?? 'tls');
                if ($encryption === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
                
                $fromEmail = $smtpConfig['smtp_from_email'] ?? $smtpConfig['email'] ?? 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
                $fromName = $smtpConfig['smtp_from_name'] ?? $smtpConfig['agency_name'] ?? 'Travel Agency';
            } else {
                // Use sendmail
                $mail->isSendmail();
                $fromEmail = 'noreply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
                $fromName = $smtpConfig['agency_name'] ?? 'Travel Agency';
            }
            
            // Set email details
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($email, $name);
            $mail->Subject = "Monthly Profit Report - " . $reportData['month'];
            $mail->isHTML(true);
            $mail->Body = $this->generateEmailHTML($reportData, $name);
            
            // Attach files
            if (file_exists($excelPath)) {
                $mail->addAttachment($excelPath, basename($excelPath));
            }
            if ($pdfPath && file_exists($pdfPath)) {
                $mail->addAttachment($pdfPath, basename($pdfPath));
            }
            
            // Send email
            return $mail->send();
        } catch (Exception $e) {
            error_log("SMTP Email sending error: " . $e->getMessage());
            // Fallback to PHP mail
            return $this->sendEmailViaPhpMail($email, $name, $reportData, $excelPath, $pdfPath);
        }
    }

    /**
     * Send email using default PHP mail function
     */
    private function sendEmailViaPhpMail($email, $name, $reportData, $excelPath, $pdfPath) {
        try {
            // Prepare email content
            $subject = "Monthly Profit Report - " . $reportData['month'];
            $htmlContent = $this->generateEmailHTML($reportData, $name);
            
            // Create email with attachments
            if (file_exists($excelPath)) {
                $boundary = md5(time() . microtime());
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
                $headers .= "From: noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";
                
                // Create message body
                $message = "--{$boundary}\r\n";
                $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
                $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
                $message .= $htmlContent . "\r\n";
                
                // Attach Excel file
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; name=\"" . basename($excelPath) . "\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"" . basename($excelPath) . "\"\r\n\r\n";
                $message .= chunk_split(base64_encode(file_get_contents($excelPath))) . "\r\n";
                
                // Attach PDF if exists
                if ($pdfPath && file_exists($pdfPath)) {
                    $message .= "--{$boundary}\r\n";
                    $message .= "Content-Type: application/pdf; name=\"" . basename($pdfPath) . "\"\r\n";
                    $message .= "Content-Transfer-Encoding: base64\r\n";
                    $message .= "Content-Disposition: attachment; filename=\"" . basename($pdfPath) . "\"\r\n\r\n";
                    $message .= chunk_split(base64_encode(file_get_contents($pdfPath))) . "\r\n";
                }
                
                $message .= "--{$boundary}--";
                
                $result = mail($email, $subject, $message, $headers);
            } else {
                // Fallback to HTML only if Excel not available
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n";
                $result = mail($email, $subject, $htmlContent, $headers);
            }
            
            if (!$result) {
                error_log("Failed to send email to: " . $email);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("PHP Mail sending error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate HTML email content
     */
    private function generateEmailHTML($reportData, $recipientName) {
        $summary = $reportData['financial_summary'];
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; }
                .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .summary { background-color: #ecf0f1; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #bdc3c7; }
                .summary-row:last-child { border-bottom: none; }
                .summary-label { font-weight: bold; }
                .summary-value { color: #27ae60; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background-color: #34495e; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ecf0f1; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .footer { background-color: #ecf0f1; padding: 15px; text-align: center; font-size: 12px; color: #7f8c8d; }
                .btn { background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"header\">
                    <h1>Monthly Profit Report</h1>
                    <p>{$reportData['month']}</p>
                </div>
                
                <div class=\"content\">
                    <p>Dear {$recipientName},</p>
                    
                    <p>Please find your monthly profit report for {$reportData['month']} below. The detailed PDF is attached to this email.</p>
                    
                    <div class=\"summary\">
                        <h3>Financial Summary</h3>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Total Profit:</span>
                            <span class=\"summary-value\">\$" . number_format($summary['total_usd_profit'], 2) . " USD + \$" . number_format($summary['total_afs_profit'], 2) . " AFS</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Ticket Bookings Profit:</span>
                            <span>\$" . number_format($summary['ticket_profit'], 2) . " USD + \$" . number_format($summary['ticket_afs_profit'], 2) . " AFS (" . ($summary['total_tickets_sold'] ?? 0) . " bookings)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Ticket Reservations Profit:</span>
                            <span>\$" . number_format($summary['ticket_reservation_profit'], 2) . " USD + \$" . number_format($summary['ticket_reservation_afs_profit'], 2) . " AFS (" . ($summary['total_ticket_reservations'] ?? 0) . " reservations)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Ticket Weights Profit:</span>
                            <span>\$" . number_format($summary['ticket_weight_profit'], 2) . " USD + \$" . number_format($summary['ticket_weight_afs_profit'], 2) . " AFS (" . ($summary['total_ticket_weights'] ?? 0) . " weights)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Refunded Tickets Profit:</span>
                            <span>\$" . number_format($summary['refunded_tickets_usd_profit'], 2) . " USD + \$" . number_format($summary['refunded_tickets_afs_profit'], 2) . " AFS (" . ($summary['total_refunded_tickets'] ?? 0) . " refunds)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Date Changes Profit:</span>
                            <span>\$" . number_format($summary['date_change_usd_profit'], 2) . " USD + \$" . number_format($summary['date_change_afs_profit'], 2) . " AFS (" . ($summary['total_date_changes'] ?? 0) . " changes)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Hotels Profit:</span>
                            <span>\$" . number_format($summary['hotel_profit'], 2) . " USD + \$" . number_format($summary['hotel_afs_profit'], 2) . " AFS (" . ($summary['total_hotels'] ?? 0) . " bookings)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Visas Profit:</span>
                            <span>\$" . number_format($summary['visa_profit'], 2) . " USD + \$" . number_format($summary['visa_afs_profit'], 2) . " AFS (" . ($summary['total_visas'] ?? 0) . " applications)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Umrah Profit:</span>
                            <span>\$" . number_format($summary['umrah_profit'], 2) . " USD + \$" . number_format($summary['umrah_afs_profit'], 2) . " AFS (" . ($summary['total_umrah'] ?? 0) . " bookings)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Additional Payments:</span>
                            <span>\$" . number_format($summary['additional_profit'], 2) . " USD + \$" . number_format($summary['additional_afs_profit'], 2) . " AFS</span>
                        </div>
                    </div>
                    
                    <h3>Top Branches by Revenue</h3>
                     <table>
                         <thead>
                             <tr>
                                 <th>Branch</th>
                                 <th>Profit (USD & AFS)</th>
                                 <th>Total Combined</th>
                             </tr>
                         </thead>
                         <tbody>
                    ";
                    
                    foreach (array_slice($reportData['branch_comparison'], 0, 5) as $branch) {
                         $totalProfit = ($branch['total_profit_usd'] ?? 0) + ($branch['total_profit_afs'] ?? 0);
                         $html .= "
                                         <tr>
                                             <td>{$branch['branch_name']}</td>
                                             <td>\$" . number_format($branch['total_profit_usd'], 2) . " USD + \$" . number_format($branch['total_profit_afs'], 2) . " AFS</td>
                                             <td>\$" . number_format($totalProfit, 2) . "</td>
                                         </tr>
                         ";
                     }
                    
                    $html .= "
                                    </tbody>
                                </table>
                                
                                <h3>Service Breakdown (by Branch)</h3>
                    ";
                    
                    foreach ($reportData['branches'] as $branch) {
                        $html .= "
                                <h4>" . htmlspecialchars($branch['name']) . "</h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Service Type</th>
                                            <th>Transactions</th>
                                            <th>Profit (USD)</th>
                                            <th>Profit (AFS)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        ";
                        
                        // Get service breakdown for this branch
                        try {
                            $dates = explode(' to ', $reportData['period']);
                            $startDate = $dates[0] ?? date('Y-m-01');
                            $endDate = $dates[1] ?? date('Y-m-t');
                            $branchServiceData = $this->getBranchServiceBreakdown($reportData['tenant_id'], $branch['id'], $startDate, $endDate);
                            
                            foreach ($branchServiceData as $service) {
                                $html .= "
                                        <tr>
                                            <td>" . htmlspecialchars($service['service_type']) . "</td>
                                            <td>" . $service['count'] . "</td>
                                            <td>\$" . number_format($service['usd_profit'], 2) . "</td>
                                            <td>\$" . number_format($service['afs_profit'], 2) . "</td>
                                        </tr>
                                ";
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching service breakdown for branch {$branch['id']}: " . $e->getMessage());
                            $html .= "
                                        <tr>
                                            <td colspan=\"4\">Error loading service breakdown: " . htmlspecialchars($e->getMessage()) . "</td>
                                        </tr>
                            ";
                        }
                        
                        $html .= "
                                    </tbody>
                                </table>
                        ";
                    }
                    
                    $html .= "
                                
                                <p>For a complete breakdown of client interactions, supplier performance, and detailed branch analytics, please refer to the attached PDF report.</p>
                    
                    <p style=\"text-align: center;\">
                        <a href=\"" . $_SERVER['SERVER_NAME'] . "/tenant_super_admin/reports.php\" class=\"btn\">View Full Reports</a>
                    </p>
                </div>
                
                <div class=\"footer\">
                    <p>This is an automated report generated on " . date('Y-m-d H:i:s') . "</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }
}
?>
