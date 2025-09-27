<?php
// Error reporting configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];

require_once '../includes/db.php';
require_once '../vendor/autoload.php';

if (!isset($_GET['category_id'])) {
    die('Category ID is required');
}

$categoryId = $_GET['category_id'];

try {
    // Get category name
    $categoryStmt = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ? AND tenant_id = ?");
    $categoryStmt->execute([$categoryId, $tenant_id]);
    $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        die('Category not found');
    }

    // Get current month's expenses for this category
    $startDate = date('Y-m-01'); // First day of current month
    $endDate = date('Y-m-t');    // Last day of current month

    $expenseQuery = "SELECT e.*, ma.name as account_name 
                    FROM expenses e 
                    LEFT JOIN main_account ma ON e.main_account_id = ma.id 
                    WHERE e.category_id = ? AND e.date BETWEEN ? AND ? AND e.tenant_id = ?
                    ORDER BY e.date DESC";
    $expenseStmt = $pdo->prepare($expenseQuery);
    $expenseStmt->execute([$categoryId, $startDate, $endDate, $tenant_id]);
    $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new mPDF instance with font configuration
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_footer' => 5,
        'default_font' => 'xwzar',
        'fontDir' => ['../assets/fonts/'],
        'fontdata' => [
            'xwzar' => [
                'R' => 'XW Zar Bd_0.ttf',
                'useOTL' => 0xFF,
            ]
        ]
    ]);

    // Set right-to-left direction
    $mpdf->SetDirectionality('rtl');

    // Define the HTML content
    $html = '
    <!DOCTYPE html>
    <html dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($category['name']) . ' - Monthly Expenses Report</title>
        <style>
            body {
                font-family: xwzar;
                line-height: 1.6;
                margin: 0;
                padding: 0;
                color: #333;
            }
            .container {
                position: relative;
                padding: 15px;
            }
            .title {
                text-align: center;
                margin-bottom: 20px;
                font-size: 18px;
                font-weight: bold;
            }
            .period {
                text-align: center;
                margin-bottom: 30px;
                color: #666;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th {
                background-color: #f5f5f5;
                padding: 8px;
                border: 1px solid #ddd;
                font-weight: bold;
            }
            td {
                padding: 8px;
                border: 1px solid #ddd;
            }
            .totals {
                margin-top: 20px;
                padding: 10px;
                background-color: #f9f9f9;
                border-top: 2px solid #ddd;
            }
            .total-row {
                margin: 5px 0;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="title">' . htmlspecialchars($category['name']) . ' - د لګښتونو راپور</div>
            <div class="period">دوره: ' . date('F Y') . '</div>
            
            <table>
                <thead>
                    <tr>
                        <th>نیټه</th>
                        <th>تفصیل</th>
                        <th>مقدار</th>
                        <th>اسعار</th>
                        <th>حساب</th>
                    </tr>
                </thead>
                <tbody>';

    $totalUSD = 0;
    $totalAFS = 0;

    foreach ($expenses as $expense) {
        $html .= '<tr>
            <td>' . date('Y/m/d', strtotime($expense['date'])) . '</td>
            <td>' . htmlspecialchars($expense['description']) . '</td>
            <td style="text-align: right;">' . number_format($expense['amount'], 2) . '</td>
            <td style="text-align: center;">' . htmlspecialchars($expense['currency']) . '</td>
            <td>' . htmlspecialchars($expense['account_name'] ?? 'N/A') . '</td>
        </tr>';

        if ($expense['currency'] === 'USD') {
            $totalUSD += $expense['amount'];
        } elseif ($expense['currency'] === 'AFS') {
            $totalAFS += $expense['amount'];
        }
    }

    $html .= '</tbody>
            </table>
            
            <div class="totals">
                <div class="total-row">ټول USD: $' . number_format($totalUSD, 2) . '</div>
                <div class="total-row">ټول AFS: ' . number_format($totalAFS, 2) . ' AFS</div>
            </div>
        </div>
    </body>
    </html>';

    // Write the HTML content to the PDF
    $mpdf->WriteHTML($html);

    // Output the PDF
    $mpdf->Output($category['name'] . '_expenses_' . date('Y-m') . '.pdf', 'I');
    exit;

} catch (Exception $e) {
    error_log("PDF Generation Error: " . $e->getMessage());
    die("Error generating PDF: " . $e->getMessage());
}
?> 