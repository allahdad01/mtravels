<?php
/**
 * Manual Monthly Report Generator
 * Allows tenant super admins to generate on-demand monthly reports
 */

include 'header.php';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];
$success_message = '';
$error_message = '';
$generated_report = null;

// Include the report generator
require_once dirname(__FILE__) . "/../cron/MonthlyReportGenerator.php";

// Handle manual report generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for all POST requests
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $error_message = 'Security token validation failed. Please try again.';
    } elseif (isset($_POST['generate_report'])) {
    try {
        // Get form data
        $month = $_POST['month'] ?? date('Y-m');
        list($year, $month_num) = explode('-', $month);

        // Calculate start and end dates
        $startDate = $year . '-' . $month_num . '-01';
        $endDate = date('Y-m-d', strtotime('last day of ' . $startDate));

        // Generate report
        $reportGenerator = new MonthlyReportGenerator($pdo);
        $reportData = $reportGenerator->generateMonthlyReport($tenant_id, $startDate, $endDate);

        if ($reportData) {
            $generated_report = $reportData;
            $success_message = "Report generated successfully!";

            // Option to send email
            if (isset($_POST['send_email'])) {
                // Get tenant email
                $stmt = $pdo->prepare("SELECT email, name FROM tenants WHERE id = ?");
                $stmt->execute([$tenant_id]);
                $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($tenant && $tenant['email']) {
                    $pdfPath = $reportGenerator->generatePDF($reportData, $tenant_id, $tenant['name']);
                    $emailSent = $reportGenerator->sendReportEmail(
                        $tenant['email'],
                        $tenant['name'],
                        $reportData,
                        $pdfPath
                    );

                    if ($emailSent) {
                        $success_message .= " Report has been sent to {$tenant['email']}";
                    } else {
                        $error_message = "Report generated but email sending failed. Please check your email configuration.";
                    }
                } else {
                    $error_message = "Cannot send email: Tenant email not configured.";
                }
            }
        } else {
            $error_message = "Failed to generate report. Please try again.";
        }
    } catch (Exception $e) {
        error_log("Report generation error: " . $e->getMessage());
        $error_message = "Error: " . $e->getMessage();
    }
    }
}

// Get tenant info
$stmt = $pdo->prepare("SELECT name, email FROM tenants WHERE id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Generate Monthly Report</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="report_settings.php">Report Settings</a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0)">Generate Report</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="feather icon-check-circle"></i> <?= $success_message ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="feather icon-alert-circle"></i> <?= $error_message ?>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-calendar"></i> Select Month & Generate</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-group">
                                <label for="month">Select Month:</label>
                                <input type="month" class="form-control" id="month" name="month"
                                       value="<?= date('Y-m') ?>" required>
                                <small class="form-text text-muted">Choose the month for which to generate the report</small>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="send_email"
                                           name="send_email" <?= !empty($tenant['email']) ? '' : 'disabled' ?>>
                                    <label class="custom-control-label" for="send_email">
                                        Send Report Email
                                    </label>
                                </div>
                                <small class="form-text text-muted">
                                    <?php if (!empty($tenant['email'])): ?>
                                        Will send to: <?= htmlspecialchars($tenant['email']) ?>
                                    <?php else: ?>
                                        Email not configured for this tenant
                                    <?php endif; ?>
                                </small>
                            </div>

                            <button type="submit" name="generate_report" class="btn btn-primary btn-block">
                                <i class="feather icon-zap"></i> Generate Report
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-info"></i> About Monthly Reports</h5>
                    </div>
                    <div class="card-body">
                        <h6>Report Includes:</h6>
                        <ul class="mb-3">
                            <li>Financial Summary (Ticket, Hotel, Visa, Umrah Profits)</li>
                            <li>Branch Comparison Report</li>
                            <li>Top 10 Clients by Spending</li>
                            <li>Top 10 Suppliers by Revenue</li>
                            <li>Total Transaction Count</li>
                        </ul>

                        <h6>Report Format:</h6>
                        <ul>
                            <li>Email Summary (HTML)</li>
                            <li>Downloadable PDF</li>
                        </ul>

                        <hr>
                        <p class="text-muted small">
                            <i class="feather icon-clock"></i> Reports can be set to generate automatically on a scheduled day each month.
                        </p>
                        <a href="report_settings.php" class="btn btn-sm btn-outline-primary">
                            <i class="feather icon-settings"></i> Configure Auto-Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Display Generated Report -->
        <?php if (!empty($generated_report)): ?>
        <div class="row mt-4">
            <!-- Financial Summary -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-dollar-sign"></i> Financial Summary - <?= $generated_report['month'] ?></h5>
                    </div>
                    <div class="card-body">
                        <?php $summary = $generated_report['financial_summary']; ?>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card card-event bg-light">
                                    <div class="card-block">
                                        <h6 class="m-0">Total Profit</h6>
                                        <h3 class="mt-2 mb-0">$<?= number_format($summary['total_profit'], 2) ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-event bg-light">
                                    <div class="card-block">
                                        <h6 class="m-0">Tickets</h6>
                                        <h3 class="mt-2">$<?= number_format($summary['ticket_profit'], 2) ?></h3>
                                        <small class="text-muted"><?= $summary['total_tickets_sold'] ?> tickets</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-event bg-light">
                                    <div class="card-block">
                                        <h6 class="m-0">Hotels</h6>
                                        <h3 class="mt-2">$<?= number_format($summary['hotel_profit'], 2) ?></h3>
                                        <small class="text-muted"><?= $summary['total_hotels'] ?> bookings</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card card-event bg-light">
                                    <div class="card-block">
                                        <h6 class="m-0">Visas & Umrah</h6>
                                        <h3 class="mt-2">$<?= number_format($summary['visa_profit'] + $summary['umrah_profit'], 2) ?></h3>
                                        <small class="text-muted"><?= ($summary['total_visas'] + $summary['total_umrah']) ?> applications</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Branch Comparison -->
            <div class="col-md-12 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-git-branch"></i> Branch Comparison</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Branch</th>
                                        <th>Ticket Profit</th>
                                        <th>Hotel Profit</th>
                                        <th>Visa Profit</th>
                                        <th>Umrah Profit</th>
                                        <th>Total Profit</th>
                                        <th>Transactions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($generated_report['branch_comparison'] as $branch): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($branch['branch_name']) ?></strong></td>
                                        <td>$<?= number_format($branch['ticket_profit'], 2) ?></td>
                                        <td>$<?= number_format($branch['hotel_profit'], 2) ?></td>
                                        <td>$<?= number_format($branch['visa_profit'], 2) ?></td>
                                        <td>$<?= number_format($branch['umrah_profit'], 2) ?></td>
                                        <td><strong>$<?= number_format($branch['total_profit'], 2) ?></strong></td>
                                        <td><?= $branch['total_transactions'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Clients -->
            <?php if (!empty($generated_report['top_clients'])): ?>
            <div class="col-md-6 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-award"></i> Top Clients</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Tickets</th>
                                        <th>Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($generated_report['top_clients'], 0, 10) as $client): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($client['name']) ?></td>
                                        <td><?= $client['tickets_purchased'] ?></td>
                                        <td>$<?= number_format($client['total_spent'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Suppliers -->
            <?php if (!empty($generated_report['top_suppliers'])): ?>
            <div class="col-md-6 mt-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-briefcase"></i> Top Suppliers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Hotels</th>
                                        <th>Visas</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($generated_report['top_suppliers'], 0, 5) as $supplier): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($supplier['name']) ?></td>
                                        <td><?= $supplier['hotel_bookings'] ?></td>
                                        <td><?= $supplier['visa_services'] ?></td>
                                        <td>$<?= number_format(($supplier['hotel_revenue'] ?? 0) + ($supplier['visa_revenue'] ?? 0), 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>
