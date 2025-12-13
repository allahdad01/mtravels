<?php
/**
 * MonthlyReportGenerator Class
 * 
 * Handles generation of monthly profit reports with detailed analytics
 * Includes PDF generation and email distribution
 */

require_once dirname(dirname(__FILE__)) . "/vendor/autoload.php";

use TCPDF;

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
            error_log("Error generating report: " . $e->getMessage());
            return false;
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
                COALESCE(SUM(CASE WHEN tb.currency = 'USD' THEN tb.profit ELSE 0 END), 0) as ticket_profit,
                COUNT(DISTINCT h.id) as total_hotels,
                COALESCE(SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END), 0) as hotel_profit,
                COUNT(DISTINCT v.id) as total_visas,
                COALESCE(SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END), 0) as visa_profit,
                COUNT(DISTINCT um.booking_id) as total_umrah,
                COALESCE(SUM(CASE WHEN um.currency = 'USD' THEN um.profit ELSE 0 END), 0) as umrah_profit
            FROM branches b
            LEFT JOIN users u ON b.id = u.branch_id AND u.tenant_id = ?
            LEFT JOIN ticket_bookings tb ON u.id = tb.created_by AND tb.created_at BETWEEN ? AND ?
            LEFT JOIN hotel_bookings h ON u.id = h.created_by AND h.created_at BETWEEN ? AND ?
            LEFT JOIN visa_applications v ON u.id = v.created_by AND v.created_at BETWEEN ? AND ?
            LEFT JOIN umrah_bookings um ON u.id = um.created_by AND um.created_at BETWEEN ? AND ?
            WHERE b.tenant_id = ? AND b.status = 'active'
            GROUP BY b.id, b.name, b.code
            ORDER BY (ticket_profit + hotel_profit + visa_profit + umrah_profit) DESC
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            $tenantId, $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $tenantId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top clients (by ticket sales amount)
     */
    private function getTopClients($tenantId, $startDate, $endDate, $limit = 10) {
        $query = "
            SELECT
                c.id,
                c.name,
                c.phone,
                COUNT(DISTINCT tb.id) as booking_count,
                COUNT(DISTINCT CASE WHEN tb.id IS NOT NULL THEN tb.id END) as tickets_purchased,
                COALESCE(SUM(CASE WHEN tb.currency = 'USD' THEN tb.profit ELSE 0 END), 0) as total_spent
            FROM clients c
            LEFT JOIN ticket_bookings tb ON c.id = tb.client_id AND tb.created_at BETWEEN ? AND ?
            WHERE c.tenant_id = ? AND tb.id IS NOT NULL
            GROUP BY c.id, c.name, c.phone
            ORDER BY total_spent DESC
            LIMIT ?
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$startDate, $endDate, $tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top suppliers (by transaction volume)
     */
    private function getTopSuppliers($tenantId, $startDate, $endDate, $limit = 10) {
        $query = "
            SELECT
                s.id,
                s.name,
                s.contact_person,
                s.phone,
                COUNT(DISTINCT CASE WHEN h.id IS NOT NULL THEN h.id END) as hotel_bookings,
                COALESCE(SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END), 0) as hotel_revenue,
                COUNT(DISTINCT CASE WHEN v.id IS NOT NULL THEN v.id END) as visa_services,
                COALESCE(SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END), 0) as visa_revenue
            FROM suppliers s
            LEFT JOIN hotel_bookings h ON s.id = h.supplier_id AND h.created_at BETWEEN ? AND ?
            LEFT JOIN visa_applications v ON s.id = v.supplier_id AND v.created_at BETWEEN ? AND ?
            WHERE s.tenant_id = ? AND (h.id IS NOT NULL OR v.id IS NOT NULL)
            GROUP BY s.id, s.name, s.contact_person, s.phone
            ORDER BY (hotel_revenue + visa_revenue) DESC
            LIMIT ?
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$startDate, $endDate, $startDate, $endDate, $tenantId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get financial summary for the period
     */
    private function getFinancialSummary($tenantId, $startDate, $endDate) {
        $query = "
            SELECT
                COALESCE(SUM(CASE WHEN tb.currency = 'USD' THEN tb.profit ELSE 0 END), 0) as ticket_profit,
                COUNT(DISTINCT tb.id) as total_tickets_sold,
                COALESCE(SUM(CASE WHEN h.currency = 'USD' THEN h.profit ELSE 0 END), 0) as hotel_profit,
                COUNT(DISTINCT h.id) as total_hotels,
                COALESCE(SUM(CASE WHEN v.currency = 'USD' THEN v.profit ELSE 0 END), 0) as visa_profit,
                COUNT(DISTINCT v.id) as total_visas,
                COALESCE(SUM(CASE WHEN um.currency = 'USD' THEN um.profit ELSE 0 END), 0) as umrah_profit,
                COUNT(DISTINCT um.booking_id) as total_umrah,
                COALESCE(SUM(CASE WHEN ap.currency = 'USD' THEN ap.profit ELSE 0 END), 0) as additional_profit
            FROM users u
            LEFT JOIN ticket_bookings tb ON u.id = tb.created_by AND tb.created_at BETWEEN ? AND ?
            LEFT JOIN hotel_bookings h ON u.id = h.created_by AND h.created_at BETWEEN ? AND ?
            LEFT JOIN visa_applications v ON u.id = v.created_by AND v.created_at BETWEEN ? AND ?
            LEFT JOIN umrah_bookings um ON u.id = um.created_by AND um.created_at BETWEEN ? AND ?
            LEFT JOIN additional_payments ap ON u.id = ap.created_by AND ap.created_at BETWEEN ? AND ?
            WHERE u.tenant_id = ?
        ";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $startDate, $endDate,
            $tenantId
        ]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['total_profit'] = 
            ($summary['ticket_profit'] ?? 0) +
            ($summary['hotel_profit'] ?? 0) +
            ($summary['visa_profit'] ?? 0) +
            ($summary['umrah_profit'] ?? 0) +
            ($summary['additional_profit'] ?? 0);

        return $summary;
    }

    /**
     * Get branch comparison data
     */
    private function getBranchComparison($tenantId, $startDate, $endDate) {
        $branches = $this->getBranchData($tenantId, $startDate, $endDate);
        
        $comparison = [];
        foreach ($branches as $branch) {
            $totalProfit = 
                ($branch['ticket_profit'] ?? 0) +
                ($branch['hotel_profit'] ?? 0) +
                ($branch['visa_profit'] ?? 0) +
                ($branch['umrah_profit'] ?? 0);

            $comparison[] = [
                'branch_name' => $branch['name'],
                'branch_code' => $branch['code'],
                'total_profit' => $totalProfit,
                'ticket_profit' => $branch['ticket_profit'],
                'hotel_profit' => $branch['hotel_profit'],
                'visa_profit' => $branch['visa_profit'],
                'umrah_profit' => $branch['umrah_profit'],
                'total_transactions' => $branch['total_tickets'] + $branch['total_hotels'] + $branch['total_visas'] + $branch['total_umrah']
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
            require_once dirname(dirname(__FILE__)) . "/vendor/autoload.php";
            
            use PhpOffice\PhpSpreadsheet\Spreadsheet;
            use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
            use PhpOffice\PhpSpreadsheet\Style\Alignment;
            use PhpOffice\PhpSpreadsheet\Style\Border;
            use PhpOffice\PhpSpreadsheet\Style\Fill;
            
            // Get branches for the tenant
            $stmt = $this->pdo->prepare("SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' ORDER BY name");
            $stmt->execute([$tenantId]);
            $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
                $sheet->mergeCells('A1:G1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $sheet->setCellValue('A2', 'Date Range: ' . date('d/m/Y', strtotime($startDate)) . ' to ' . date('d/m/Y', strtotime($endDate)));
                $sheet->mergeCells('A2:G2');
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
                $serviceData = $this->getBranchServiceBreakdown($tenantId, $branch['id'], $startDate, $endDate);
                
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
     * Get service breakdown for a branch
     */
    private function getBranchServiceBreakdown($tenantId, $branchId, $startDate, $endDate) {
        $services = [
            'Tickets' => 'SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM ticket_bookings WHERE tenant_id = ? AND created_by IN (SELECT id FROM users WHERE branch_id = ?) AND created_at BETWEEN ? AND ?',
            'Hotels' => 'SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM hotel_bookings WHERE tenant_id = ? AND created_by IN (SELECT id FROM users WHERE branch_id = ?) AND created_at BETWEEN ? AND ?',
            'Visas' => 'SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM visa_applications WHERE tenant_id = ? AND created_by IN (SELECT id FROM users WHERE branch_id = ?) AND created_at BETWEEN ? AND ?',
            'Umrah' => 'SELECT COUNT(*) as count, SUM(CASE WHEN currency = "USD" THEN profit ELSE 0 END) as usd_profit, SUM(CASE WHEN currency = "AFS" THEN profit ELSE 0 END) as afs_profit FROM umrah_bookings WHERE tenant_id = ? AND created_by IN (SELECT id FROM users WHERE branch_id = ?) AND created_at BETWEEN ? AND ?',
        ];
        
        $data = [];
        foreach ($services as $serviceName => $query) {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$tenantId, $branchId, $startDate, $endDate]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $data[] = [
                'service_type' => $serviceName,
                'count' => $result['count'] ?? 0,
                'usd_profit' => $result['usd_profit'] ?? 0,
                'afs_profit' => $result['afs_profit'] ?? 0
            ];
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
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_PAGE_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
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
Total Profit: \$" . number_format($summary['total_profit'], 2) . "
Ticket Profit: \$" . number_format($summary['ticket_profit'], 2) . " (" . ($summary['total_tickets_sold'] ?? 0) . " tickets)
Hotel Profit: \$" . number_format($summary['hotel_profit'], 2) . " (" . ($summary['total_hotels'] ?? 0) . " hotels)
Visa Profit: \$" . number_format($summary['visa_profit'], 2) . " (" . ($summary['total_visas'] ?? 0) . " visas)
Umrah Profit: \$" . number_format($summary['umrah_profit'], 2) . " (" . ($summary['total_umrah'] ?? 0) . " umrah)
            ";
            
            $pdf->MultiCell(0, 4, $summaryText, 0, 'L');
            $pdf->Ln(3);
            
            // Branch Comparison Table
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Branch Comparison', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            
            // Table header
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell(30, 6, 'Branch', 1, 0, 'L', true);
            $pdf->Cell(25, 6, 'Tickets', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'Hotels', 1, 0, 'C', true);
            $pdf->Cell(20, 6, 'Visas', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'Umrah', 1, 0, 'C', true);
            $pdf->Cell(40, 6, 'Total Profit', 1, 1, 'R', true);
            
            // Table rows
            $pdf->SetFillColor(255, 255, 255);
            foreach ($reportData['branch_comparison'] as $branch) {
                $pdf->Cell(30, 6, substr($branch['branch_name'], 0, 12), 1, 0, 'L');
                $pdf->Cell(25, 6, '$' . number_format($branch['ticket_profit'], 0), 1, 0, 'R');
                $pdf->Cell(25, 6, '$' . number_format($branch['hotel_profit'], 0), 1, 0, 'R');
                $pdf->Cell(20, 6, '$' . number_format($branch['visa_profit'], 0), 1, 0, 'R');
                $pdf->Cell(25, 6, '$' . number_format($branch['umrah_profit'], 0), 1, 0, 'R');
                $pdf->Cell(40, 6, '$' . number_format($branch['total_profit'], 0), 1, 1, 'R');
            }
            
            $pdf->Ln(5);
            
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
     * Send report via email with Excel attachment
     * @param string $email Recipient email
     * @param string $name Recipient name
     * @param array $reportData Report data
     * @param string $excelPath Path to Excel file
     * @param string $pdfPath Path to PDF file (optional)
     * @return bool
     */
    public function sendReportEmail($email, $name, $reportData, $excelPath, $pdfPath = null) {
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
            error_log("Email sending error: " . $e->getMessage());
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
                            <span class=\"summary-value\">\$" . number_format($summary['total_profit'], 2) . "</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Tickets Profit:</span>
                            <span>\$" . number_format($summary['ticket_profit'], 2) . " (" . ($summary['total_tickets_sold'] ?? 0) . " tickets)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Hotels Profit:</span>
                            <span>\$" . number_format($summary['hotel_profit'], 2) . " (" . ($summary['total_hotels'] ?? 0) . " bookings)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Visas Profit:</span>
                            <span>\$" . number_format($summary['visa_profit'], 2) . " (" . ($summary['total_visas'] ?? 0) . " applications)</span>
                        </div>
                        <div class=\"summary-row\">
                            <span class=\"summary-label\">Umrah Profit:</span>
                            <span>\$" . number_format($summary['umrah_profit'], 2) . " (" . ($summary['total_umrah'] ?? 0) . " bookings)</span>
                        </div>
                    </div>
                    
                    <h3>Top Branches by Revenue</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Total Profit</th>
                                <th>Transactions</th>
                            </tr>
                        </thead>
                        <tbody>
        ";
        
        foreach (array_slice($reportData['branch_comparison'], 0, 5) as $branch) {
            $html .= "
                            <tr>
                                <td>{$branch['branch_name']}</td>
                                <td>\$" . number_format($branch['total_profit'], 2) . "</td>
                                <td>{$branch['total_transactions']}</td>
                            </tr>
            ";
        }
        
        $html .= "
                        </tbody>
                    </table>
                    
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
