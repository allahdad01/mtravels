<?php
require_once '../../includes/language_helpers.php';
require_once '../../includes/db.php';
require_once '../../admin/security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];



// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => __('invalid_csrf_token')]));
}

$report_type = $_POST['report_type'] ?? '';
$format = $_POST['format'] ?? 'pdf';

if (empty($report_type)) {
    die(json_encode(['success' => false, 'message' => __('report_type_required')]));
}

// Set JSON content type for responses
header('Content-Type: application/json');

try {
    $pdo->beginTransaction();

    switch ($report_type) {
        case 'employee_overview':
            $data = generateEmployeeOverviewReport($pdo, $tenant_id, $branch_id);
            break;
        case 'termination_summary':
            $data = generateTerminationSummaryReport($pdo, $tenant_id, $branch_id);
            break;
        case 'role_distribution':
            $data = generateRoleDistributionReport($pdo, $tenant_id, $branch_id);
            break;
        case 'tenure_analysis':
            $data = generateTenureAnalysisReport($pdo, $tenant_id, $branch_id);
            break;
        default:
            throw new Exception(__('invalid_report_type'));
    }

    $pdo->commit();

    // Generate the report file
    $filename = generateReportFile($data, $report_type, $format);

    echo json_encode([
        'success' => true,
        'message' => __('report_generated_successfully'),
        'filename' => $filename,
        'download_url' => 'download_report.php?file=' . urlencode($filename)
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("HR Report Generation Error: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function generateEmployeeOverviewReport($pdo, $tenant_id, $branch_id) {
    // Get all employees with their details
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.phone,
            u.role,
            u.hire_date,
            u.fired,
            u.fired_at,
            u.created_at,
            sm.base_salary,
            sm.currency as salary_currency,
            sm.status as salary_status
        FROM users u
        LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id AND sm.branch_id = u.branch_id
        WHERE u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin'
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'title' => __('employee_overview_report'),
        'generated_at' => date('Y-m-d H:i:s'),
        'total_employees' => count($employees),
        'data' => $employees,
        'columns' => [
            'name' => __('full_name'),
            'email' => __('email'),
            'phone' => __('phone'),
            'role' => __('role'),
            'hire_date' => __('hire_date'),
            'status' => __('status'),
            'base_salary' => __('base_salary'),
            'salary_currency' => __('currency')
        ]
    ];
}

function generateTerminationSummaryReport($pdo, $tenant_id, $branch_id) {
    // Get termination data
    $stmt = $pdo->prepare("
        SELECT
            et.*,
            u.name as employee_name,
            u.email as employee_email,
            u.role as employee_role,
            t.name as terminated_by_name
        FROM employee_terminations et
        JOIN users u ON et.employee_id = u.id
        LEFT JOIN users t ON et.terminated_by = t.id
        WHERE et.tenant_id = ? AND et.branch_id = ?
        ORDER BY et.termination_date DESC
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $terminations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get termination reasons summary
    $stmt = $pdo->prepare("
        SELECT termination_reason, COUNT(*) as count
        FROM employee_terminations
        WHERE tenant_id = ? AND branch_id = ?
        GROUP BY termination_reason
        ORDER BY count DESC
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $reasons_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'title' => __('termination_summary_report'),
        'generated_at' => date('Y-m-d H:i:s'),
        'total_terminations' => count($terminations),
        'terminations' => $terminations,
        'reasons_summary' => $reasons_summary,
        'columns' => [
            'employee_name' => __('employee_name'),
            'employee_email' => __('email'),
            'employee_role' => __('role'),
            'termination_reason' => __('termination_reason'),
            'termination_date' => __('termination_date'),
            'terminated_by_name' => __('terminated_by')
        ]
    ];
}

function generateRoleDistributionReport($pdo, $tenant_id, $branch_id) {
    // Get role distribution
    $stmt = $pdo->prepare("
        SELECT role, COUNT(*) as count
        FROM users
        WHERE tenant_id = ? AND branch_id = ? AND role != 'super_admin' AND fired = 0
        GROUP BY role
        ORDER BY count DESC
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $role_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total active employees
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_active
        FROM users
        WHERE tenant_id = ? AND branch_id = ? AND role != 'super_admin' AND fired = 0
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $total_active = $stmt->fetch(PDO::FETCH_ASSOC)['total_active'];

    return [
        'title' => __('role_distribution_report'),
        'generated_at' => date('Y-m-d H:i:s'),
        'total_active_employees' => $total_active,
        'role_distribution' => $role_distribution,
        'columns' => [
            'role' => __('role'),
            'count' => __('count'),
            'percentage' => __('percentage')
        ]
    ];
}

function generateTenureAnalysisReport($pdo, $tenant_id, $branch_id) {
    // Get tenure data
    $stmt = $pdo->prepare("
        SELECT
            name,
            email,
            role,
            hire_date,
            fired,
            fired_at,
            DATEDIFF(CURDATE(), hire_date) as days_employed,
            ROUND(DATEDIFF(CURDATE(), hire_date) / 30, 1) as months_employed
        FROM users
        WHERE tenant_id = ? AND branch_id = ? AND role != 'super_admin'
        ORDER BY hire_date ASC
    ");
    $stmt->execute([$tenant_id, $branch_id]);
    $tenure_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate averages
    $active_employees = array_filter($tenure_data, function($emp) {
        return $emp['fired'] == 0;
    });

    $avg_tenure_days = 0;
    if (count($active_employees) > 0) {
        $total_days = array_sum(array_column($active_employees, 'days_employed'));
        $avg_tenure_days = round($total_days / count($active_employees), 1);
    }

    return [
        'title' => __('tenure_analysis_report'),
        'generated_at' => date('Y-m-d H:i:s'),
        'total_employees' => count($tenure_data),
        'active_employees' => count($active_employees),
        'average_tenure_days' => $avg_tenure_days,
        'average_tenure_months' => round($avg_tenure_days / 30, 1),
        'tenure_data' => $tenure_data,
        'columns' => [
            'name' => __('full_name'),
            'email' => __('email'),
            'role' => __('role'),
            'hire_date' => __('hire_date'),
            'months_employed' => __('months_employed'),
            'status' => __('status')
        ]
    ];
}

function generateReportFile($data, $report_type, $format) {
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "hr_{$report_type}_report_{$timestamp}";

    // Create reports directory if it doesn't exist
    $reports_dir = __DIR__ . '/reports';
    if (!is_dir($reports_dir)) {
        mkdir($reports_dir, 0755, true);
    }

    switch ($format) {
        case 'pdf':
            $filename .= '.pdf';
            generatePDFReport($data, $reports_dir . '/' . $filename);
            break;
        case 'excel':
        case 'xlsx':
            $filename .= '.xlsx';
            generateExcelReport($data, $reports_dir . '/' . $filename);
            break;
        case 'csv':
            $filename .= '.csv';
            generateCSVReport($data, $reports_dir . '/' . $filename);
            break;
        default:
            throw new Exception(__('unsupported_format'));
    }

    return $filename;
}

function generatePDFReport($data, $filepath) {
    // Check if mPDF library is available
    if (!file_exists('../vendor/autoload.php')) {
        // Fallback to HTML file if libraries not installed
        $html = generateHTMLReport($data);
        $html_filepath = str_replace('.pdf', '.html', $filepath);
        file_put_contents($html_filepath, $html);

        // Create a note about PDF generation
        $content = "HR Report: {$data['title']}\n";
        $content .= "Generated: {$data['generated_at']}\n\n";
        $content .= "PDF libraries not installed. HTML version generated instead.\n";
        $content .= "Please run 'composer install' to enable PDF generation.\n";
        file_put_contents($filepath, $content);
        return;
    }

    try {
        // Use mPDF library
        require_once '../vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf([
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 10,
            'margin_footer' => 10
        ]);

        // Set document properties
        $mpdf->SetTitle($data['title']);
        $mpdf->SetAuthor('HR Management System');
        $mpdf->SetCreator('HR Management System');

        // Add header
        $header = "
        <div style='text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px;'>
            <h1 style='color: #333; margin: 0;'>{$data['title']}</h1>
            <p style='color: #666; margin: 5px 0;'>Generated: {$data['generated_at']}</p>
        </div>
        ";

        // Generate content based on report type
        $content = generatePDFContent($data);

        // Combine header and content
        $html = $header . $content;

        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, 'F');

    } catch (Exception $e) {
        // Fallback to HTML file if PDF generation fails
        error_log("PDF Generation Error: " . $e->getMessage());
        $html = generateHTMLReport($data);
        $html_filepath = str_replace('.pdf', '.html', $filepath);
        file_put_contents($html_filepath, $html);

        $content = "HR Report: {$data['title']}\n";
        $content .= "Generated: {$data['generated_at']}\n\n";
        $content .= "Error generating PDF. HTML version generated instead.\n";
        $content .= "Error: " . $e->getMessage() . "\n";
        file_put_contents($filepath, $content);
    }
}

function generateExcelReport($data, $filepath) {
    // Check if PhpSpreadsheet library is available
    if (!file_exists('../vendor/autoload.php')) {
        // Fallback to CSV if libraries not installed
        generateCSVReport($data, str_replace('.xlsx', '.csv', $filepath));

        // Create a note about Excel generation
        $content = "HR Report: {$data['title']}\n";
        $content .= "Generated: {$data['generated_at']}\n\n";
        $content .= "Excel libraries not installed. CSV version generated instead.\n";
        $content .= "Please run 'composer install' to enable Excel generation.\n";
        file_put_contents($filepath, $content);
        return;
    }

    try {
        // Use PhpSpreadsheet library
        require_once '../vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set document properties
        $spreadsheet->getProperties()
            ->setTitle($data['title'])
            ->setSubject('HR Report')
            ->setDescription('Generated HR Report')
            ->setCreator('HR Management System');

        // Add title and generation info
        $sheet->setCellValue('A1', $data['title']);
        $sheet->setCellValue('A2', 'Generated: ' . $data['generated_at']);
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        // Generate content based on report type
        $startRow = generateExcelContent($data, $sheet, 4);

        // Auto-size columns
        foreach (range('A', 'Z') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Save the file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filepath);

    } catch (Exception $e) {
        // Fallback to CSV if Excel generation fails
        error_log("Excel Generation Error: " . $e->getMessage());
        generateCSVReport($data, str_replace('.xlsx', '.csv', $filepath));

        // Create error note
        $content = "HR Report: {$data['title']}\n";
        $content .= "Generated: {$data['generated_at']}\n\n";
        $content .= "Error generating Excel file. CSV version generated instead.\n";
        $content .= "Error: " . $e->getMessage() . "\n";
        file_put_contents($filepath, $content);
    }
}

function generateCSVReport($data, $filepath) {
    $output = fopen($filepath, 'w');

    // Write report header
    fprintf($output, "HR Report: %s\n", $data['title']);
    fprintf($output, "Generated: %s\n\n", $data['generated_at']);

    // Write data based on report type
    switch (key($data)) {
        case 'data': // Employee overview
            if (isset($data['columns'])) {
                fputcsv($output, array_values($data['columns']));
            }
            foreach ($data['data'] as $row) {
                $csv_row = [];
                if (isset($data['columns'])) {
                    foreach (array_keys($data['columns']) as $column) {
                        $value = $row[$column] ?? '';
                        if ($column === 'hire_date' && $value) {
                            $value = date('Y-m-d', strtotime($value));
                        }
                        if ($column === 'fired') {
                            $value = $value ? 'Terminated' : 'Active';
                        }
                        $csv_row[] = $value;
                    }
                } else {
                    $csv_row = array_values($row);
                }
                fputcsv($output, $csv_row);
            }
            break;

        case 'terminations': // Termination summary
            fputcsv($output, ['Employee Name', 'Email', 'Role', 'Termination Reason', 'Termination Date', 'Terminated By']);
            foreach ($data['terminations'] as $termination) {
                fputcsv($output, [
                    $termination['employee_name'],
                    $termination['employee_email'],
                    $termination['employee_role'],
                    $termination['termination_reason'],
                    date('Y-m-d', strtotime($termination['termination_date'])),
                    $termination['terminated_by_name']
                ]);
            }
            break;

        case 'role_distribution': // Role distribution
            fputcsv($output, ['Role', 'Count', 'Percentage']);
            $total = $data['total_active_employees'];
            foreach ($data['role_distribution'] as $role) {
                $percentage = $total > 0 ? round(($role['count'] / $total) * 100, 1) : 0;
                fputcsv($output, [$role['role'], $role['count'], $percentage . '%']);
            }
            break;

        case 'tenure_data': // Tenure analysis
            fputcsv($output, ['Name', 'Email', 'Role', 'Hire Date', 'Months Employed', 'Status']);
            foreach ($data['tenure_data'] as $employee) {
                fputcsv($output, [
                    $employee['name'],
                    $employee['email'],
                    $employee['role'],
                    date('Y-m-d', strtotime($employee['hire_date'])),
                    $employee['months_employed'],
                    $employee['fired'] ? 'Terminated' : 'Active'
                ]);
            }
            break;
    }

    fclose($output);
}

function generatePDFContent($data) {
    $html = "<div style='font-family: Arial, sans-serif;'>";

    // Add summary information
    if (isset($data['total_employees'])) {
        $html .= "<div style='background: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 5px;'>";
        $html .= "<h3 style='margin: 0 0 10px 0; color: #333;'>Summary</h3>";
        $html .= "<p><strong>Total Employees:</strong> {$data['total_employees']}</p>";
        if (isset($data['total_active_employees'])) {
            $html .= "<p><strong>Active Employees:</strong> {$data['total_active_employees']}</p>";
        }
        if (isset($data['average_tenure_days'])) {
            $html .= "<p><strong>Average Tenure:</strong> " . round($data['average_tenure_days'] / 30, 1) . " months</p>";
        }
        $html .= "</div>";
    }

    // Generate table content
    if (isset($data['data']) && !empty($data['data'])) {
        $html .= "<table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>";
        $html .= "<thead><tr style='background-color: #f8f9fa;'>";

        if (isset($data['columns'])) {
            foreach ($data['columns'] as $column) {
                $html .= "<th style='border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;'>{$column}</th>";
            }
        }
        $html .= "</tr></thead><tbody>";

        foreach ($data['data'] as $row) {
            $html .= "<tr>";
            if (isset($data['columns'])) {
                foreach (array_keys($data['columns']) as $column) {
                    $value = $row[$column] ?? '';
                    if ($column === 'hire_date' && $value) {
                        $value = date('Y-m-d', strtotime($value));
                    }
                    if ($column === 'fired') {
                        $value = $value ? 'Terminated' : 'Active';
                    }
                    $html .= "<td style='border: 1px solid #ddd; padding: 8px;'>{$value}</td>";
                }
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
    }

    // Add termination summary if available
    if (isset($data['terminations']) && !empty($data['terminations'])) {
        $html .= "<h3 style='margin-top: 30px; color: #333;'>Termination Details</h3>";
        $html .= "<table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>";
        $html .= "<thead><tr style='background-color: #f8f9fa;'>";
        $html .= "<th style='border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;'>Employee</th>";
        $html .= "<th style='border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;'>Reason</th>";
        $html .= "<th style='border: 1px solid #ddd; padding: 8px; text-align: left; font-weight: bold;'>Date</th>";
        $html .= "</tr></thead><tbody>";

        foreach ($data['terminations'] as $termination) {
            $html .= "<tr>";
            $html .= "<td style='border: 1px solid #ddd; padding: 8px;'>{$termination['employee_name']}</td>";
            $html .= "<td style='border: 1px solid #ddd; padding: 8px;'>{$termination['termination_reason']}</td>";
            $html .= "<td style='border: 1px solid #ddd; padding: 8px;'>" . date('Y-m-d', strtotime($termination['termination_date'])) . "</td>";
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
    }

    $html .= "</div>";
    return $html;
}

function generateExcelContent($data, $sheet, $startRow) {
    $currentRow = $startRow;

    // Add summary information
    if (isset($data['total_employees'])) {
        $sheet->setCellValue('A' . $currentRow, 'Summary');
        $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
        $currentRow++;

        $sheet->setCellValue('A' . $currentRow, 'Total Employees:');
        $sheet->setCellValue('B' . $currentRow, $data['total_employees']);
        $currentRow++;

        if (isset($data['total_active_employees'])) {
            $sheet->setCellValue('A' . $currentRow, 'Active Employees:');
            $sheet->setCellValue('B' . $currentRow, $data['total_active_employees']);
            $currentRow++;
        }

        if (isset($data['average_tenure_days'])) {
            $sheet->setCellValue('A' . $currentRow, 'Average Tenure (months):');
            $sheet->setCellValue('B' . $currentRow, round($data['average_tenure_days'] / 30, 1));
            $currentRow++;
        }

        $currentRow += 2; // Add some space
    }

    // Add data table
    if (isset($data['data']) && !empty($data['data'])) {
        // Add headers
        if (isset($data['columns'])) {
            $col = 'A';
            foreach ($data['columns'] as $column) {
                $sheet->setCellValue($col . $currentRow, $column);
                $sheet->getStyle($col . $currentRow)->getFont()->setBold(true);
                $sheet->getStyle($col . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');
                $col++;
            }
            $currentRow++;
        }

        // Add data rows
        foreach ($data['data'] as $row) {
            $col = 'A';
            if (isset($data['columns'])) {
                foreach (array_keys($data['columns']) as $column) {
                    $value = $row[$column] ?? '';
                    if ($column === 'hire_date' && $value) {
                        $value = date('Y-m-d', strtotime($value));
                    }
                    if ($column === 'fired') {
                        $value = $value ? 'Terminated' : 'Active';
                    }
                    $sheet->setCellValue($col . $currentRow, $value);
                    $col++;
                }
            }
            $currentRow++;
        }
    }

    // Add termination details if available
    if (isset($data['terminations']) && !empty($data['terminations'])) {
        $currentRow += 2; // Add space

        $sheet->setCellValue('A' . $currentRow, 'Termination Details');
        $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(14);
        $currentRow++;

        // Headers
        $sheet->setCellValue('A' . $currentRow, 'Employee Name');
        $sheet->setCellValue('B' . $currentRow, 'Email');
        $sheet->setCellValue('C' . $currentRow, 'Role');
        $sheet->setCellValue('D' . $currentRow, 'Termination Reason');
        $sheet->setCellValue('E' . $currentRow, 'Termination Date');
        $sheet->setCellValue('F' . $currentRow, 'Terminated By');

        // Style headers
        $sheet->getStyle('A' . $currentRow . ':F' . $currentRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $currentRow . ':F' . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('E9ECEF');
        $currentRow++;

        // Data rows
        foreach ($data['terminations'] as $termination) {
            $sheet->setCellValue('A' . $currentRow, $termination['employee_name']);
            $sheet->setCellValue('B' . $currentRow, $termination['employee_email']);
            $sheet->setCellValue('C' . $currentRow, $termination['employee_role']);
            $sheet->setCellValue('D' . $currentRow, $termination['termination_reason']);
            $sheet->setCellValue('E' . $currentRow, date('Y-m-d', strtotime($termination['termination_date'])));
            $sheet->setCellValue('F' . $currentRow, $termination['terminated_by_name'] ?? 'Unknown');
            $currentRow++;
        }
    }

    return $currentRow;
}

function generateHTMLReport($data) {
    $html = "
    <html>
    <head>
        <title>{$data['title']}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            .header { margin-bottom: 30px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>{$data['title']}</h1>
            <p>Generated: {$data['generated_at']}</p>
        </div>
    ";

    if (isset($data['data'])) {
        $html .= "<table><thead><tr>";
        if (isset($data['columns'])) {
            foreach ($data['columns'] as $column) {
                $html .= "<th>{$column}</th>";
            }
        }
        $html .= "</tr></thead><tbody>";

        foreach ($data['data'] as $row) {
            $html .= "<tr>";
            if (isset($data['columns'])) {
                foreach (array_keys($data['columns']) as $column) {
                    $value = $row[$column] ?? '';
                    if ($column === 'hire_date' && $value) {
                        $value = date('Y-m-d', strtotime($value));
                    }
                    if ($column === 'fired') {
                        $value = $value ? 'Terminated' : 'Active';
                    }
                    $html .= "<td>{$value}</td>";
                }
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";
    }

    $html .= "</body></html>";
    return $html;
}
?>