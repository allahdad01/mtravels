<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    // Session expired, destroy session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity time

// Check if user is logged in with proper role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to admin dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../api/dashboard/dashboard_handler.php';

?>


<?php include '../includes/header.php'; ?>

<?php
// Define ticket features visibility
$showTickets = hasFeature('ticket_bookings', $allowed_features) ||
                              hasFeature('ticket_reservations', $allowed_features) ||
                              hasFeature('refunded_tickets', $allowed_features) ||
                              hasFeature('date_change_tickets', $allowed_features) ||
                              hasFeature('ticket_weights', $allowed_features);
?>
<?php
if (!file_exists($imagePath)) {
    $imagePath = "../assets/images/user/avatar-1.jpg";
}
?>
<link rel="stylesheet" href="css/dashboard.css">
<link href="css/dashboard-styles.css" rel="stylesheet">
<link rel="stylesheet" href="css/modal-styles.css">


    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->

                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            
                            <!-- Dashboard Header -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="dashboard-header d-flex justify-content-between align-items-center flex-wrap">
                                        <div>
                                            <h3 class="dashboard-title"><?= __('welcome_back') ?>, <?= htmlspecialchars($user['name'] ?? 'Admin') ?></h3>
                                            <p class="dashboard-subtitle"><?= __('dashboard_subtitle') ?></p>
                                        </div>
                                        <div class="d-flex flex-wrap">
                                           
                                            <div class="dropdown">
                                                <button class="btn btn-light dropdown-toggle mb-2 mb-md-0" type="button" id="quickActionsDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="feather icon-zap mr-1"></i><?= __('quick_actions') ?>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="quickActionsDropdown">
                                                    <a class="dropdown-item" href="ticket.php">
                                                        <i class="feather icon-plus-circle mr-2 text-primary"></i><?= __('add_ticket') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="client.php">
                                                        <i class="feather icon-user-plus mr-2 text-success"></i><?= __('add_client') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="supplier.php">
                                                        <i class="feather icon-truck mr-2 text-warning"></i><?= __('add_supplier') ?>
                                                    </a>
                                                    
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            
                            <!-- Financial Wealth Distribution Chart -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-white">
                                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                                <h5 class="mb-0 mb-md-0">
                                                    <i class="feather icon-bar-chart-2 text-primary mr-2"></i><?= __('financial_wealth_distribution') ?>
                                                </h5>
                                                <div class="chart-controls d-flex flex-column flex-md-row mt-2 mt-md-0">
                                                    <div class="chart-period mr-md-2 mb-2 mb-md-0 w-100 w-md-auto">
                                                        <label class="mb-0 mr-2 d-block d-md-inline-block text-muted small"><?= __('period') ?>:</label>
                                                        <select id="financeChartPeriod" class="form-control form-control-sm">
                                                            <option value="daily"><?= __('daily') ?></option>
                                                            <option value="monthly" selected><?= __('monthly') ?></option>
                                                            <option value="yearly"><?= __('yearly') ?></option>
                                                        </select>
                                                    </div>
                                                    <div class="chart-currency w-100 w-md-auto">
                                                        <label class="mb-0 mr-2 d-block d-md-inline-block text-muted small"><?= __('currency') ?>:</label>
                                                        <select id="financeChartCurrency" class="form-control form-control-sm">
                                                            <option value="USD" selected><?= __('usd') ?></option>
                                                            <option value="AFS"><?= __('afs') ?></option>
                                                            <option value="EUR"><?= __('eur') ?></option>
                                                            <option value="AED"><?= __('aed') ?></option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-lg-8 mb-4 mb-lg-0">
                                                    <div id="financeFlowChart" style="height: 400px;"></div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                                        <h6 class="text-muted mb-0"><?= __('wealth_distribution') ?></h6>
                                                        <span class="badge badge-pill badge-light" id="currentDateBadge"></span>
                                                    </div>
                                                    <div class="wealth-distribution-summary p-3 rounded">
                                                        <div class="d-flex flex-column flex-sm-row justify-content-between mb-3">
                                                            <span class="mb-1 mb-sm-0"><?= __('main_accounts') ?>:</span>
                                                            <span id="mainAccountBalance" class="font-weight-bold">$0.00</span>
                                                        </div>
                                                        <div class="d-flex flex-column flex-sm-row justify-content-between mb-3">
                                                            <span class="mb-1 mb-sm-0"><?= __('supplier_credits') ?>:</span>
                                                            <span id="supplierBalance" class="font-weight-bold">$0.00</span>
                                                        </div>
                                                        <div class="d-flex flex-column flex-sm-row justify-content-between mb-3">
                                                            <span class="mb-1 mb-sm-0"><?= __('client_credits') ?>:</span>
                                                            <span id="clientBalance" class="font-weight-bold">$0.00</span>
                                                        </div>
                                                        <div class="d-flex flex-column flex-sm-row justify-content-between mb-3">
                                                            <span class="mb-1 mb-sm-0"><?= __('debtor_balance') ?>:</span>
                                                            <span id="debtorBalance" class="font-weight-bold">$0.00</span>
                                                        </div>

                                                        <hr>
                                                        <div class="d-flex flex-column flex-sm-row justify-content-between">
                                                            <span class="font-weight-bold mb-1 mb-sm-0"><?= __('total_net_worth') ?>:</span>
                                                            <span id="totalNetWorth" class="font-weight-bold text-primary">$0.00</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!--[ daily sales section ] start-->
                                <div class="col-md-6 col-xl-4">
                                    <div class="filter-controls">
                                        <h6><?= __('daily_sales') ?></h6>
                                        <div class="date-filter-toggle">
                                            <i class="feather icon-filter filter-icon" data-toggle="collapse" data-target="#dailyDateFilter"></i>
                                        </div>
                                    </div>
                                    <div class="collapse mb-2" id="dailyDateFilter">
                                        <div class="date-filter-panel">
                                            <label class="small text-muted"><?= __('select_date') ?></label>
                                            <input type="date" class="form-control form-control-sm date-filter" id="dailyDateInput" value="<?= date('Y-m-d') ?>">
                                            <button class="btn btn-sm btn-primary mt-2 apply-daily-filter"><?= __('apply_filter') ?></button>
                                        </div>
                                    </div>
                                    <div class="card dashboard-card sales-card animate-card daily-sales" data-type="daily" data-usd="<?= number_format($dailySales['usd_profit'], 2) ?>" data-afs="<?= number_format($dailySales['afs_profit'], 2) ?>">
                                        <div class="card-block">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="card-icon">
                                                    <i class="feather icon-calendar"></i>
                                                </div>
                                                <div>
                                                    <h6 class="card-title"><?= __('daily_sales') ?></h6>
                                                    <span class="card-trend trend-up"><?= $dailyTrendPercent ?>%</span>
                                                </div>
                                            </div>
                                            <h3 class="card-amount">$<span id="dailyUsdProfit"><?= number_format($dailySales['usd_profit'], 2) ?></span></h3>
                                            <p class="card-secondary-amount">؋<span id="dailyAfsProfit"><?= number_format($dailySales['afs_profit'], 2) ?></span></p>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <span class="card-date" id="dailyDateDisplay"><?= __('today') ?></span>
                                                <i class="feather icon-external-link text-primary"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!--[ daily sales section ] end-->
                                <!--[ Monthly  sales section ] starts-->
                                <div class="col-md-6 col-xl-4">
                                    <div class="filter-controls">
                                        <h6><?= __('monthly_sales') ?></h6>
                                        <div class="date-filter-toggle">
                                            <i class="feather icon-filter filter-icon" data-toggle="collapse" data-target="#monthlyDateFilter"></i>
                                        </div>
                                    </div>
                                    <div class="collapse mb-2" id="monthlyDateFilter">
                                        <div class="date-filter-panel">
                                            <label class="small text-muted"><?= __('select_month') ?></label>
                                            <input type="month" class="form-control form-control-sm date-filter" id="monthlyDateInput" value="<?= date('Y-m') ?>">
                                            <button class="btn btn-sm btn-primary mt-2 apply-monthly-filter"><?= __('apply_filter') ?></button>
                                        </div>
                                    </div>
                                    <div class="card dashboard-card sales-card animate-card monthly-sales" data-type="monthly" data-usd="<?= number_format($monthlySales['usd_profit'], 2) ?>" data-afs="<?= number_format($monthlySales['afs_profit'], 2) ?>">
                                        <div class="card-block">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="card-icon">
                                                    <i class="feather icon-bar-chart-2"></i>
                                                </div>
                                                <div>
                                                    <h6 class="card-title"><?= __('monthly_sales') ?></h6>
                                                    <span class="card-trend trend-up"><?= $monthlyTrendPercent ?>%</span>
                                                </div>
                                            </div>
                                            <h3 class="card-amount">$<span id="monthlyUsdProfit"><?= number_format($monthlySales['usd_profit'], 2) ?></span></h3>
                                            <p class="card-secondary-amount">؋<span id="monthlyAfsProfit"><?= number_format($monthlySales['afs_profit'], 2) ?></span></p>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <span class="card-date" id="monthlyDateDisplay"><?= date('M Y') ?></span>
                                                <i class="feather icon-external-link text-primary"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!--[ Monthly  sales section ] end-->
                                <!--[ year  sales section ] starts-->
                                <div class="col-md-12 col-xl-4">
                                    <div class="filter-controls">
                                        <h6><?= __('yearly_sales') ?></h6>
                                        <div class="date-filter-toggle">
                                            <i class="feather icon-filter filter-icon" data-toggle="collapse" data-target="#yearlyDateFilter"></i>
                                        </div>
                                    </div>
                                    <div class="collapse mb-2" id="yearlyDateFilter">
                                        <div class="date-filter-panel">
                                            <label class="small text-muted"><?= __('select_year') ?></label>
                                            <select class="form-control form-control-sm date-filter" id="yearlyDateInput">
                                                <?php 
                                                $currentYear = date('Y');
                                                for($y = $currentYear; $y >= $currentYear - 5; $y--) {
                                                    echo "<option value='$y'" . ($y == $currentYear ? " selected" : "") . ">$y</option>";
                                                }
                                                ?>
                                            </select>
                                            <button class="btn btn-sm btn-primary mt-2 apply-yearly-filter"><?= __('apply_filter') ?></button>
                                        </div>
                                    </div>
                                    <div class="card dashboard-card sales-card animate-card yearly-sales" data-type="yearly" data-usd="<?= number_format($yearlySales['usd_profit'], 2) ?>" data-afs="<?= number_format($yearlySales['afs_profit'], 2) ?>">
                                        <div class="card-block">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="card-icon">
                                                    <i class="feather icon-trending-up"></i>
                                                </div>
                                                <div>
                                                    <h6 class="card-title"><?= __('yearly_sales') ?></h6>
                                                    <span class="card-trend trend-up"><?= $yearlyTrendPercent ?>%</span>
                                                </div>
                                            </div>
                                            <h3 class="card-amount">$<span id="yearlyUsdProfit"><?= number_format($yearlySales['usd_profit'], 2) ?></span></h3>
                                            <p class="card-secondary-amount">؋<span id="yearlyAfsProfit"><?= number_format($yearlySales['afs_profit'], 2) ?></span></p>
                                            <div class="d-flex justify-content-between align-items-center mt-3">
                                                <span class="card-date" id="yearlyDateDisplay"><?= date('Y') ?></span>
                                                <i class="feather icon-external-link text-primary"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Dues Summary Section -->
                                <div class="col-xl-12 col-md-6">
                                        <div class="card Recent-Users">
                                            <div class="card-header">
                                                <h5><?= __('outstanding_dues') ?></h5>
                                            </div>
                                            <div class="card-block px-0 py-3">
                                                <div class="row px-3">
                                                    <!-- Ticket Bookings Dues -->
                                                    <?php if (hasFeature('ticket_bookings', $allowed_features)): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card dashboard-card due-card animate-card" data-type="ticket">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="due-title"><?= __('ticket_bookings') ?></h6>
                                                                        <h3 class="due-amount" id="ticketDuesUSD">$0.00</h3>
                                                                        <p class="due-secondary-amount" id="ticketDuesAFS">؋0.00</p>
                                                                    </div>
                                                                    <div class="bg-light-danger">
                                                                        <i class="fas fa-ticket-alt text-danger"></i>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Date Change Dues -->
                                                    <?php if (hasFeature('date_change_tickets', $allowed_features)): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card dashboard-card due-card animate-card" data-type="datechange">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="due-title"><?= __('date_change') ?></h6>
                                                                        <h3 class="due-amount" id="dateChangeDuesUSD">$0.00</h3>
                                                                        <p class="due-secondary-amount" id="dateChangeDuesAFS">؋0.00</p>
                                                                    </div>
                                                                    <div class="bg-light-warning">
                                                                        <i class="feather icon-calendar text-warning"></i>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Refunded Tickets Dues -->
                                                    <?php if (hasFeature('refunded_tickets', $allowed_features)): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card dashboard-card due-card animate-card" data-type="refunded">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="due-title"><?= __('refunded_tickets') ?></h6>
                                                                        <h3 class="due-amount" id="refundedDuesUSD">$0.00</h3>
                                                                        <p class="due-secondary-amount" id="refundedDuesAFS">؋0.00</p>
                                                                    </div>
                                                                    <div class="bg-light-info">
                                                                        <i class="feather icon-refresh-cw text-info"></i>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>


                                                    <!-- Umrah Dues -->
                                                    <?php if (hasFeature('umrah_bookings', $allowed_features)): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card dashboard-card due-card animate-card" data-type="umrah">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="due-title"><?= __('umrah') ?></h6>
                                                                        <h3 class="due-amount" id="umrahDuesUSD">$0.00</h3>
                                                                        <p class="due-secondary-amount" id="umrahDuesAFS">؋0.00</p>
                                                                    </div>
                                                                    <div class="bg-light-success">
                                                                        <i class="feather icon-map text-success"></i>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Visa Dues -->
                                                    <?php if (hasFeature('visa_applications', $allowed_features)): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card dashboard-card due-card animate-card" data-type="visa">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="due-title"><?= __('visa') ?></h6>
                                                                        <h3 class="due-amount" id="visaDuesUSD">$0.00</h3>
                                                                        <p class="due-secondary-amount" id="visaDuesAFS">؋0.00</p>
                                                                    </div>
                                                                    <div class="bg-light-primary">
                                                                        <i class="feather icon-file-text text-primary"></i>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Hotel Dues -->
                                                    <?php if (hasFeature('hotel_bookings', $allowed_features)): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card dashboard-card due-card animate-card" data-type="hotel">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="due-title"><?= __('hotel') ?></h6>
                                                                        <h3 class="due-amount" id="hotelDuesUSD">$0.00</h3>
                                                                        <p class="due-secondary-amount" id="hotelDuesAFS">؋0.00</p>
                                                                    </div>
                                                                    <div class="bg-light-warning">
                                                                        <i class="feather icon-home text-warning"></i>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Additional Payments Dues -->
                                                    <?php if (hasFeature('additional_payments', $allowed_features)): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card dashboard-card due-card animate-card" data-type="addpayment">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="due-title"><?= __('additional_payments') ?></h6>
                                                                        <h3 class="due-amount" id="addpaymentDuesUSD">$0.00</h3>
                                                                        <p class="due-secondary-amount" id="addpaymentDuesAFS">؋0.00</p>
                                                                    </div>
                                                                    <div class="bg-light-secondary">
                                                                        <i class="fas fa-dollar-sign text-secondary"></i>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <!-- Weight Dues -->
                                                    <?php if (hasFeature('ticket_weights', $allowed_features)): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card dashboard-card due-card animate-card" data-type="weight">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="due-title"><?= __('weight') ?></h6>
                                                                        <h3 class="due-amount" id="weightDuesUSD">$0.00</h3>
                                                                        <p class="due-secondary-amount" id="weightDuesAFS">؋0.00</p>
                                                                    </div>
                                                                    <div class="bg-light-danger">
                                                                        <i class="feather icon-package text-danger"></i>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <!-- Ticket Reserve Dues -->
                                                    <?php if (hasFeature('ticket_reservations', $allowed_features)): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card dashboard-card due-card animate-card" data-type="ticket_reserve">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h6 class="due-title"><?= __('ticket_reserve') ?></h6>
                                                                        <h3 class="due-amount" id="ticketReserveDuesUSD">$0.00</h3>
                                                                        <p class="due-secondary-amount" id="ticketReserveDuesAFS">؋0.00</p>
                                                                    </div>
                                                                    <div class="bg-light-danger">
                                                                        <i class="feather icon-package text-danger"></i>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                </div>

                                <!-- Client Debts Section -->
                                <div class="col-xl-12 col-md-6">
                                    <div class="card Recent-Users">
                                        <div class="card-header">
                                            <h5><?= __('client_debts') ?></h5>
                                            <span class="text-muted small"><?= __('clients_with_negative_balance') ?></span>
                                        </div>
                                        <div class="card-block px-0 py-3">
                                            <div class="table-responsive px-3">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('client_name') ?></th>
                                                            <th><?= __('usd_balance') ?></th>
                                                            <th><?= __('afs_balance') ?></th>
                                                            <th><?= __('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $clientsWithDebts = getClientsWithDebts();
                                                        if (count($clientsWithDebts) > 0) {
                                                            foreach ($clientsWithDebts as $client) {
                                                                echo '<tr>';
                                                                echo '<td>' . htmlspecialchars($client['name']) . '</td>';
                                                                echo '<td class="' . ($client['usd_balance'] < 0 ? 'text-danger' : '') . '">' . number_format($client['usd_balance'], 2) . '</td>';
                                                                echo '<td class="' . ($client['afs_balance'] < 0 ? 'text-danger' : '') . '">' . number_format($client['afs_balance'], 2) . '</td>';
                                                                echo '<td><a href="client_detail.php?id=' . $client['id'] . '" class="btn btn-sm btn-primary">' . __('view') . '</a></td>';
                                                                echo '</tr>';
                                                            }
                                                        } else {
                                                            echo '<tr><td colspan="4" class="text-center">' . __('no_clients_with_negative_balance_found') . '</td></tr>';
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Notifications -->
                                <div class="col-xl-12 col-md-6">
                                    <div class="card Recent-Users">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <h5 class="mb-0 mr-2"><?= __('recent_notifications') ?></h5>
                                                <span class="badge badge-pill badge-danger notification-count">
                                                    <?php
                                                    try {
                                                        $countStmt = $pdo->prepare("SELECT COUNT(*)
                                                                                    FROM notifications
                                                                                    WHERE status = 'unread'
                                                                                    AND tenant_id = :tenant_id");
                                                        $countStmt->execute(['tenant_id' => $tenant_id]);
                                                        echo $countStmt->fetchColumn();
                                                    } catch (PDOException $e) {
                                                        echo "0";
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#notificationBody" aria-expanded="false" aria-controls="notificationBody" id="notificationToggle">
                                                <i class="feather icon-chevron-down"></i>
                                            </button>
                                        </div>
                                        <div class="collapse" id="notificationBody">
                                            <div class="card-header border-top-0 pt-0">
                                                <ul class="nav nav-pills nav-fill" id="notificationTabs" role="tablist">
                                                <li class="nav-item">
                                                    <a class="nav-link active" id="unread-tab" data-toggle="tab" href="#unread" role="tab">
                                                        <i class="feather icon-bell mr-1"></i><?= __('unread') ?> 
                                                        <span class="badge badge-pill badge-danger notification-count ml-1">
                                                            <?php 
                                                        try {
                                                            $countStmt = $pdo->prepare("SELECT COUNT(*) 
                                                                                        FROM notifications 
                                                                                        WHERE status = 'unread' 
                                                                                        AND tenant_id = :tenant_id");
                                                            $countStmt->execute(['tenant_id' => $tenant_id]);
                                                            echo $countStmt->fetchColumn();
                                                        } catch (PDOException $e) {
                                                            echo "0";
                                                        }
                                                        
                                                            ?>
                                                        </span>
                                                    </a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link" id="read-tab" data-toggle="tab" href="#read" role="tab">
                                                        <i class="feather icon-check-circle mr-1"></i><?= __('read') ?>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="card-block px-0 py-3">
                                            <div class="tab-content" id="notificationTabContent">
                                                <!-- Unread Notifications Tab -->
                                                <div class="tab-pane fade show active" id="unread" role="tabpanel">
                                                    <div class="px-3">
                                                        <?php
                                                        try {
                                                            // Query to fetch unread notifications for the current tenant
                                                            $query = "
                                                                SELECT n.*, 
                                                                    CASE 
                                                                        WHEN n.transaction_type = 'visa' THEN va.applicant_name 
                                                                        WHEN n.transaction_type = 'supplier' THEN s.name
                                                                        WHEN n.transaction_type = 'umrah' THEN ub.name 
                                                                        ELSE NULL 
                                                                    END AS related_name,
                                                                    CASE 
                                                                        WHEN n.transaction_type = 'visa' THEN va.base 
                                                                        WHEN n.transaction_type = 'supplier' THEN st.amount
                                                                        WHEN n.transaction_type = 'umrah' THEN ub.sold_price 
                                                                        ELSE 0 
                                                                    END AS transaction_amount,
                                                                    CASE 
                                                                        WHEN n.transaction_type = 'visa' THEN va.currency 
                                                                        WHEN n.transaction_type = 'supplier' THEN s.currency 
                                                                        ELSE NULL 
                                                                    END AS transaction_currency
                                                                FROM notifications n
                                                                LEFT JOIN visa_applications va 
                                                                    ON n.transaction_id = va.id 
                                                                    AND n.transaction_type = 'visa'
                                                                LEFT JOIN umrah_bookings ub 
                                                                    ON n.transaction_id = ub.booking_id 
                                                                    AND n.transaction_type = 'umrah'
                                                                LEFT JOIN supplier_transactions st 
                                                                    ON n.transaction_id = st.id 
                                                                    AND n.transaction_type = 'supplier'
                                                                LEFT JOIN suppliers s 
                                                                    ON st.supplier_id = s.id 
                                                                    OR va.supplier = s.id
                                                                WHERE n.status = 'unread'
                                                                AND n.tenant_id = :tenant_id
                                                                ORDER BY n.created_at DESC
                                                            ";
                                                        
                                                            $stmt = $pdo->prepare($query);
                                                            $stmt->execute(['tenant_id' => $tenant_id]);
                                                        
                                                            if ($stmt->rowCount() > 0) {
                                                                displayModernNotifications($stmt, 'unread');
                                                            } else {
                                                                echo '<div class="empty-state text-center py-4">
                                                                        <i class="feather icon-bell-off text-muted" style="font-size: 48px;"></i>
                                                                        <p class="text-muted mt-2">' . __('no_unread_notifications_available') . '</p>
                                                                    </div>';
                                                            }
                                                        } catch (PDOException $e) {
                                                            error_log("Error fetching unread notifications: " . $e->getMessage());
                                                            echo '<div class="alert alert-danger">' . __('error_loading_notifications') . '</div>';
                                                        }
                                                        
                                                        ?>
                                                    </div>
                                                </div>

                                                <!-- Read Notifications Tab -->
                                                <div class="tab-pane fade" id="read" role="tabpanel">
                                                    <div class="d-flex justify-content-between align-items-center px-3 py-2">
                                                        <h6 class="mb-0"><?= __('read_notifications') ?></h6>
                                                        <div class="date-filter">
                                                            <div class="input-group input-group-sm">
                                                                <input type="date" class="form-control form-control-sm" id="readNotificationsDate" value="<?= date('Y-m-d') ?>">
                                                                <div class="input-group-append">
                                                                    <button class="btn btn-sm btn-primary" id="applyReadDateFilter">
                                                                        <i class="feather icon-filter"></i> <?= __('filter') ?>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div id="readNotificationsBody" class="px-3">
                                                        <?php
                                                    try {
                                                        // Query to fetch read notifications (filtered by today's date by default)
                                                        $today = date('Y-m-d');
                                                        $query = "
                                                            SELECT n.*, 
                                                                CASE 
                                                                    WHEN n.transaction_type = 'visa' THEN va.applicant_name 
                                                                    WHEN n.transaction_type = 'supplier' THEN s.name
                                                                    WHEN n.transaction_type = 'umrah' THEN ub.name 
                                                                    ELSE NULL 
                                                                END AS related_name,
                                                                CASE 
                                                                    WHEN n.transaction_type = 'visa' THEN va.base 
                                                                    WHEN n.transaction_type = 'supplier' THEN st.amount
                                                                    WHEN n.transaction_type = 'umrah' THEN ub.sold_price 
                                                                    ELSE 0 
                                                                END AS transaction_amount,
                                                                CASE 
                                                                    WHEN n.transaction_type = 'visa' THEN va.currency 
                                                                    WHEN n.transaction_type = 'supplier' THEN s.currency 
                                                                    ELSE NULL 
                                                                END AS transaction_currency
                                                            FROM notifications n
                                                            LEFT JOIN visa_applications va 
                                                                ON n.transaction_id = va.id 
                                                                AND n.transaction_type = 'visa'
                                                            LEFT JOIN umrah_bookings ub 
                                                                ON n.transaction_id = ub.booking_id 
                                                                AND n.transaction_type = 'umrah'
                                                            LEFT JOIN supplier_transactions st 
                                                                ON n.transaction_id = st.id 
                                                                AND n.transaction_type = 'supplier'
                                                            LEFT JOIN suppliers s 
                                                                ON st.supplier_id = s.id 
                                                                OR va.supplier = s.id
                                                            WHERE n.status = 'read' 
                                                            AND DATE(n.created_at) = :today
                                                            AND n.tenant_id = :tenant_id
                                                            ORDER BY n.created_at DESC
                                                        ";
                                                        
                                                        $stmt = $pdo->prepare($query);
                                                        $stmt->execute([
                                                            'today' => $today,
                                                            'tenant_id' => $tenant_id
                                                        ]);
                                                    
                                                        if ($stmt->rowCount() > 0) {
                                                            displayModernNotifications($stmt, 'read');
                                                        } else {
                                                            echo '<div class="empty-state text-center py-4">
                                                                    <i class="feather icon-inbox text-muted" style="font-size: 48px;"></i>
                                                                    <p class="text-muted mt-2">' . __('no_read_notifications_for_selected_date') . '</p>
                                                                </div>';
                                                        }
                                                    } catch (PDOException $e) {
                                                        error_log("Error fetching read notifications: " . $e->getMessage());
                                                        echo '<div class="alert alert-danger">' . __('error_loading_notifications') . '</div>';
                                                    }
                                                    
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                // Function to display notifications with a modern design
                                function displayModernNotifications($stmt, $status) {
                                    // Group notifications by date
                                    $notificationsByDate = [];
                                    
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $date = date('Y-m-d', strtotime($row['created_at']));
                                        if (!isset($notificationsByDate[$date])) {
                                            $notificationsByDate[$date] = [];
                                        }
                                        $notificationsByDate[$date][] = $row;
                                    }
                                    
                                    foreach ($notificationsByDate as $date => $notifications) {
                                        // Format the date for display
                                        $formattedDate = date('l, F j, Y', strtotime($date));
                                        if ($date === date('Y-m-d')) {
                                            $formattedDate = __('today');
                                        } elseif ($date === date('Y-m-d', strtotime('-1 day'))) {
                                            $formattedDate = __('yesterday');
                                        }
                                        
                                        echo '<div class="notification-date-group mb-3">';
                                        echo '<div class="date-header mb-2 d-flex align-items-center">';
                                        echo '<span class="date-badge mr-2">' . $formattedDate . '</span>';
                                        echo '<hr class="flex-grow-1 my-0">';
                                        echo '</div>';
                                        
                                        foreach ($notifications as $row) {
                                            $notification_id = htmlspecialchars($row['id']);
                                            $message = htmlspecialchars($row['message']);
                                            $related_name = htmlspecialchars($row['related_name'] ?? '');
                                            $transaction_amount = htmlspecialchars($row['transaction_amount'] ?? '');
                                            $transaction_currency = htmlspecialchars($row['transaction_currency'] ?? '');
                                            $created_at = htmlspecialchars($row['created_at']);
                                            $transaction_type = htmlspecialchars($row['transaction_type'] ?? '');
                                            $time = date('g:i A', strtotime($created_at));
                                            
                                            // Determine notification icon and color based on type
                                            $icon = 'bell';
                                            $iconColor = 'primary';
                                            
                                            switch ($transaction_type) {
                                                case 'visa':
                                                    $icon = 'file-text';
                                                    $iconColor = 'info';
                                                    break;
                                                case 'supplier':
                                                    $icon = 'truck';
                                                    $iconColor = 'warning';
                                                    break;
                                                case 'umrah':
                                                    $icon = 'map';
                                                    $iconColor = 'success';
                                                    break;
                                                case 'ticket':
                                                    $icon = 'ticket';
                                                    $iconColor = 'primary';
                                                    break;
                                                case 'refund':
                                                    $icon = 'refresh-cw';
                                                    $iconColor = 'danger';
                                                    break;
                                                case 'expense':
                                                case 'expense_update':
                                                case 'expense_delete':
                                                    $icon = 'dollar-sign';
                                                    $iconColor = 'danger';
                                                    break;
                                                case 'hotel':
                                                    $icon = 'home';
                                                    $iconColor = 'warning';
                                                    break;
                                                case 'deposit_sarafi':
                                                case 'hawala_sarafi':
                                                case 'withdrawal_sarafi':
                                                    $icon = 'repeat';
                                                    $iconColor = 'info';
                                                    break;
                                            }
                                            
                                            // Array of transaction types that should only show read button
                                            $read_only_types = ['deposit_sarafi', 'hawala_sarafi', 'withdrawal_sarafi', 'supplier_fund', 'client_fund', 'expense', 'expense_update', 'expense_delete'];
                                            $show_only_read = in_array($transaction_type, $read_only_types);
                                            ?>
                                            
                                            <div class="notification-card mb-3 card border-0 shadow-sm notification-<?= $status ?>" data-id="<?= $notification_id ?>">
                                                <div class="card-body">
                                                    <div class="d-flex">
                                                        <div class="notification-icon bg-light-<?= $iconColor ?> rounded-circle p-3 mr-3 align-self-start">
                                                            <i class="fa fa-<?= $icon ?> text-<?= $iconColor ?>"></i>
                                                        </div>
                                                        
                                                        <div class="notification-content flex-grow-1">
                                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                                <h6 class="mb-0 font-weight-bold notification-type"><?= ucfirst($transaction_type) ?></h6>
                                                                <small class="text-muted notification-time"><?= $time ?></small>
                                                            </div>
                                                            
                                                            <p class="notification-message mb-1"><?= $message ?></p>
                                                            
                                                            <?php if ($related_name || $transaction_amount): ?>
                                                            <div class="notification-details small text-muted mb-2">
                                                                <?php if ($related_name): ?>
                                                                <span class="mr-2"><i class="feather icon-user mr-1"></i><?= $related_name ?></span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($transaction_amount): ?>
                                                                <span>
                                                                    <i class="feather icon-credit-card mr-1"></i>
                                                                    <?= $transaction_currency === 'USD' ? '$' : ($transaction_currency === 'AFS' ? '؋' : '') ?>
                                                                    <?= number_format($transaction_amount, 2) ?>
                                                                </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($status === 'unread'): ?>
                                                            <div class="notification-actions">
                                                                <?php if (!$show_only_read): ?>
                                                                <button class="btn btn-sm btn-outline-success approve-button" 
                                                                        data-id="<?= $notification_id ?>" 
                                                                        data-amount="<?= $transaction_amount ?>" 
                                                                        data-currency="<?= $transaction_currency ?>"
                                                                        data-type="<?= $transaction_type ?>">
                                                                    <i class="feather icon-check mr-1"></i><?= __('received') ?>
                                                                </button>
                                                                <?php endif; ?>
                                                                
                                                                <button class="btn btn-sm btn-outline-info read-button" 
                                                                        data-id="<?= $notification_id ?>">
                                                                    <i class="feather icon-eye mr-1"></i><?= __('mark_as_read') ?>
                                                                </button>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                        }
                                        
                                        echo '</div>';
                                    }
                                }
                                ?>

                                <?php

                                // Query for tickets booked today with supplier name and transaction status
                                $today_query = "SELECT ticket_bookings.*,
                                                    suppliers.name AS supplier_name
                                                FROM ticket_bookings
                                                LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id AND suppliers.tenant_id = ticket_bookings.tenant_id
                                                WHERE DATE(ticket_bookings.created_at) = CURDATE()
                                                AND ticket_bookings.tenant_id = :tenant_id";
                                try {
                                    $today_stmt = $pdo->prepare($today_query);
                                    $today_stmt->execute(['tenant_id' => $tenant_id]);
                                } catch (PDOException $e) {
                                    error_log("Error fetching today's tickets: " . $e->getMessage());
                                    $today_stmt = null;
                                }

                                // Fetch this week's tickets
                                $this_week_query = "SELECT ticket_bookings.*,
                                                        suppliers.name AS supplier_name
                                                    FROM ticket_bookings
                                                    LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id AND suppliers.tenant_id = ticket_bookings.tenant_id
                                                    WHERE YEARWEEK(ticket_bookings.created_at, 1) = YEARWEEK(CURDATE(), 1)
                                                    AND ticket_bookings.tenant_id = :tenant_id";
                                try {
                                    $this_week_stmt = $pdo->prepare($this_week_query);
                                    $this_week_stmt->execute(['tenant_id' => $tenant_id]);
                                } catch (PDOException $e) {
                                    error_log("Error fetching this week's tickets: " . $e->getMessage());
                                    $this_week_stmt = null;
                                }

                                // Fetch this month's tickets
                                $this_month_query = "SELECT ticket_bookings.*,
                                                            suppliers.name AS supplier_name
                                                    FROM ticket_bookings
                                                    LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id AND suppliers.tenant_id = ticket_bookings.tenant_id
                                                    WHERE YEAR(ticket_bookings.created_at) = YEAR(CURDATE())
                                                    AND MONTH(ticket_bookings.created_at) = MONTH(CURDATE())
                                                    AND ticket_bookings.tenant_id = :tenant_id";
                                try {
                                    $this_month_stmt = $pdo->prepare($this_month_query);
                                    $this_month_stmt->execute(['tenant_id' => $tenant_id]);
                                } catch (PDOException $e) {
                                    error_log("Error fetching this month's tickets: " . $e->getMessage());
                                    $this_month_stmt = null;
                                }

                                // Get selected date for departures filter, default to today
                                $selected_date = isset($_GET['departure_date']) ? $_GET['departure_date'] : date('Y-m-d');

                                // Validate date format
                                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
                                    $selected_date = date('Y-m-d');
                                }

                                // Query for departures on selected date
                                $today_departures_query = "SELECT ticket_bookings.*,
                                                                suppliers.name AS supplier_name,
                                                                ticket_bookings.phone AS passenger_phone
                                                        FROM ticket_bookings
                                                        LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id AND suppliers.tenant_id = ticket_bookings.tenant_id

                                                        WHERE DATE(ticket_bookings.departure_date) = ? AND ticket_bookings.tenant_id = ?";
                                $today_departures_stmt = null;
                                try {
                                    $today_departures_stmt = $pdo->prepare($today_departures_query);
                                    $today_departures_stmt->execute([$selected_date, $tenant_id]);
                                } catch (PDOException $e) {
                                    error_log("Error fetching departures for date $selected_date: " . $e->getMessage());
                                    $today_departures_stmt = null;
                                }
                                ?>



                                
                                <?php if ($showTickets): ?>
                                <div class="col-xl-12 col-md-6">
                                    <div class="card">
                                        <div>
                                            <div class="card-header">
                                                <h5><?= __('ticket_bookings_overview') ?></h5>
                                            </div>
                                            <ul class="nav nav-pills nav-fill" id="ticketTabs" role="tablist">
                                            <li class="nav-item">
                                                <a class="nav-link active" id="departures-tab" data-toggle="tab" href="#today-departures" role="tab">
                                                    <i class="fas fa-plane mr-1"></i>
                                                    <?php
                                                    if ($selected_date === date('Y-m-d')) {
                                                        echo __('todays_departures');
                                                    } else {
                                                        echo 'Departures on ' . date('M d, Y', strtotime($selected_date));
                                                    }
                                                    ?>
                                                </a>
                                            </li>
                                                <li class="nav-item">
                                                        <a class="nav-link" id="today-tab" data-toggle="tab" href="#today" role="tab">
                                                            <i class="feather icon-clock mr-1"></i><?= __('today') ?>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="week-tab" data-toggle="tab" href="#this-week" role="tab">
                                                                <i class="feather icon-calendar mr-1"></i><?= __('this_week') ?>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="month-tab" data-toggle="tab" href="#this-month" role="tab">
                                                            <i class="feather icon-calendar mr-1"></i><?= __('this_month') ?>
                                                        </a>
                                                    </li>
                                                </li>
                                            </ul>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="tab-content" id="ticketTabContent">
                                                    <!-- Today's Departures -->
                                                <div class="tab-pane fade show active" id="today-departures" role="tabpanel">
                                                <!-- Date Filter Form -->
                                                <div class="mb-3">
                                                    <form method="GET" class="form-inline">
                                                        <div class="form-group mr-3">
                                                            <label for="departure_date" class="mr-2">Select Departure Date:</label>
                                                            <input type="date" class="form-control" id="departure_date" name="departure_date"
                                                                    value="<?php echo htmlspecialchars($selected_date); ?>">
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">Filter</button>
                                                        <a href="dashboard.php" class="btn btn-secondary ml-2">Today</a>
                                                    </form>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-hover table-striped mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th><?= __('passenger_info') ?></th>
                                                                <th><?= __('flight_details') ?></th>
                                                                <th><?= __('route') ?></th>
                                                                <th><?= __('dates') ?></th>
                                                                <th><?= __('sold') ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            if ($today_departures_stmt && $today_departures_stmt->rowCount() > 0) {
                                                                while ($row = $today_departures_stmt->fetch(PDO::FETCH_ASSOC)) {
                                                            ?>
                                                                <tr>
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="flex-grow-1">
                                                                                <h6 class="mb-1"><?= htmlspecialchars($row['passenger_name']) ?></h6>
                                                                                <small class="text-muted">
                                                                                    <?= __('pnr') ?>: <?= htmlspecialchars($row['pnr']) ?>
                                                                                </small>
                                                                                <?php if (!empty($row['passenger_phone'])): ?>
                                                                                <small class="d-block text-primary">
                                                                                    <i class="fas fa-phone-alt mr-1"></i>
                                                                                    <?= htmlspecialchars($row['passenger_phone']) ?>
                                                                                </small>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold">
                                                                                <i class="fas fa-plane mr-1"></i>
                                                                                <?= htmlspecialchars($row['airline']) ?>
                                                                            </span>
                                                                            <small class="text-muted">
                                                                                <?= htmlspecialchars($row['supplier_name']) ?>
                                                                            </small>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <span class="font-weight-bold">
                                                                                <i class="fas fa-route mr-1"></i>
                                                                                <?php
                                                                                $origin = isset($row['origin']) ? htmlspecialchars($row['origin']) : (isset($row['from_city']) ? htmlspecialchars($row['from_city']) : 'N/A');
                                                                                $destination = isset($row['destination']) ? htmlspecialchars($row['destination']) : (isset($row['to_city']) ? htmlspecialchars($row['to_city']) : 'N/A');
                                                                                echo $origin . ' → ' . $destination;
                                                                                ?>
                                                                            </span>
                                                                            <small class="text-muted">
                                                                                <?php
                                                                                $origin_code = isset($row['origin_code']) ? htmlspecialchars($row['origin_code']) : (isset($row['from_code']) ? htmlspecialchars($row['from_code']) : '');
                                                                                $destination_code = isset($row['destination_code']) ? htmlspecialchars($row['destination_code']) : (isset($row['to_code']) ? htmlspecialchars($row['to_code']) : '');
                                                                                if ($origin_code && $destination_code) {
                                                                                    echo $origin_code . ' → ' . $destination_code;
                                                                                }
                                                                                ?>
                                                                            </small>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex flex-column">
                                                                            <small class="text-muted">
                                                                                <i class="fas fa-calendar-check mr-1"></i>
                                                                                <?= __('issue') ?>: <?= date('d M Y', strtotime($row['issue_date'])) ?>
                                                                            </small>
                                                                            <small class="text-danger font-weight-bold">
                                                                                <i class="fas fa-plane-departure mr-1"></i>
                                                                                <?= __('departure') ?>: <?= date('d M Y', strtotime($row['departure_date'])) ?>
                                                                            </small>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="big mt-1">
                                                                            <span class="text-success font-weight-bold">
                                                                                <?= htmlspecialchars($row['sold']) ?>
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php } } ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                                <!-- Today's Tickets -->
                                                <div class="tab-pane fade" id="today" role="tabpanel">
                                                    <div class="table-responsive">
                                                        <table class="table table-hover table-striped mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th><?= __('passenger_info') ?></th>
                                                                    <th><?= __('flight_details') ?></th>
                                                                    <th><?= __('dates') ?></th>
                                                                    <th><?= __('sold') ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                if ($today_stmt) {
                                                                    while ($row = $today_stmt->fetch(PDO::FETCH_ASSOC)) {
                                                                ?>
                                                                    <tr>
                                                                        <td>
                                                                            <div class="d-flex align-items-center">
                                                                                <div class="flex-grow-1">
                                                                                    <h6 class="mb-1"><?= htmlspecialchars($row['passenger_name']) ?></h6>
                                                                                    <small class="text-muted">
                                                                                        <?= __('pnr') ?>: <?= htmlspecialchars($row['pnr']) ?>
                                                                                    </small>
                                                                                </div>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="d-flex flex-column">
                                                                                <span class="font-weight-bold">
                                                                                    <i class="fas fa-plane mr-1"></i>
                                                                                    <?= htmlspecialchars($row['airline']) ?>
                                                                                </span>
                                                                                <small class="text-muted">
                                                                                    <?= htmlspecialchars($row['supplier_name']) ?>
                                                                                </small>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="d-flex flex-column">
                                                                                <small class="text-muted">
                                                                                    <i class="fas fa-calendar-check mr-1"></i>
                                                                                    <?= __('issue') ?>: <?= date('d M Y', strtotime($row['issue_date'])) ?>
                                                                                </small>
                                                                                <small class="text-muted">
                                                                                    <i class="fas fa-plane-departure mr-1"></i>
                                                                                    <?= __('departure') ?>: <?= date('d M Y', strtotime($row['departure_date'])) ?>
                                                                                </small>
                                                                            </div>
                                                                        </td>
                                                                        <td>

                                                                            <div class="big mt-1">
                                                                                <span class="text-success font-weight-bold">
                                                                                    <?= htmlspecialchars($row['sold']) ?>
                                                                                </span>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php } } ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <!-- This Week's Tickets -->
                                                <div class="tab-pane fade" id="this-week" role="tabpanel">
                                                    <div class="table-responsive">
                                                        <table class="table table-hover table-striped mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th><?= __('passenger_info') ?></th>
                                                                    <th><?= __('flight_details') ?></th>
                                                                    <th><?= __('dates') ?></th>
                                                                    <th><?= __('sold') ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                if ($this_week_stmt) {
                                                                    while ($row = $this_week_stmt->fetch(PDO::FETCH_ASSOC)) {
                                                                ?>
                                                                    <tr>
                                                                        <td>
                                                                            <div class="d-flex align-items-center">
                                                                                <div class="flex-grow-1">
                                                                                    <h6 class="mb-1"><?= htmlspecialchars($row['passenger_name']) ?></h6>
                                                                                    <small class="text-muted">
                                                                                        <?= __('pnr') ?>: <?= htmlspecialchars($row['pnr']) ?>
                                                                                    </small>
                                                                                </div>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="d-flex flex-column">
                                                                                <span class="font-weight-bold">
                                                                                    <i class="fas fa-plane mr-1"></i>
                                                                                    <?= htmlspecialchars($row['airline']) ?>
                                                                                </span>
                                                                                <small class="text-muted">
                                                                                    <?= htmlspecialchars($row['supplier_name']) ?>
                                                                                </small>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="d-flex flex-column">
                                                                                <small class="text-muted">
                                                                                    <i class="fas fa-calendar-check mr-1"></i>
                                                                                    <?= __('issue') ?>: <?= date('d M Y', strtotime($row['issue_date'])) ?>
                                                                                </small>
                                                                                <small class="text-muted">
                                                                                    <i class="fas fa-plane-departure mr-1"></i>
                                                                                    <?= __('departure') ?>: <?= date('d M Y', strtotime($row['departure_date'])) ?>
                                                                                </small>
                                                                            </div>
                                                                        </td>
                                                                        <td>

                                                                            <div class="big mt-1">
                                                                                <span class="text-success font-weight-bold">
                                                                                    <?= htmlspecialchars($row['sold']) ?>
                                                                                </span>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php } } ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <!-- This Month's Tickets -->
                                                <div class="tab-pane fade" id="this-month" role="tabpanel">
                                                    <div class="table-responsive">
                                                        <table class="table table-hover table-striped mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th><?= __('passenger_info') ?></th>
                                                                    <th><?= __('flight_details') ?></th>
                                                                    <th><?= __('dates') ?></th>
                                                                    <th><?= __('sold') ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php
                                                                if ($this_month_stmt) {
                                                                    while ($row = $this_month_stmt->fetch(PDO::FETCH_ASSOC)) {
                                                                ?>
                                                                    <tr>
                                                                        <td>
                                                                            <div class="d-flex align-items-center">
                                                                                <div class="flex-grow-1">
                                                                                    <h6 class="mb-1"><?= htmlspecialchars($row['passenger_name']) ?></h6>
                                                                                    <small class="text-muted">
                                                                                        <?= __('pnr') ?>: <?= htmlspecialchars($row['pnr']) ?>
                                                                                    </small>
                                                                                </div>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="d-flex flex-column">
                                                                                <span class="font-weight-bold">
                                                                                    <i class="fas fa-plane mr-1"></i>
                                                                                    <?= htmlspecialchars($row['airline']) ?>
                                                                                </span>
                                                                                <small class="text-muted">
                                                                                    <?= htmlspecialchars($row['supplier_name']) ?>
                                                                                </small>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <div class="d-flex flex-column">
                                                                                <small class="text-muted">
                                                                                    <i class="fas fa-calendar-check mr-1"></i>
                                                                                    <?= __('issue') ?>: <?= date('d M Y', strtotime($row['issue_date'])) ?>
                                                                                </small>
                                                                                <small class="text-muted">
                                                                                    <i class="fas fa-plane-departure mr-1"></i>
                                                                                    <?= __('departure') ?>: <?= date('d M Y', strtotime($row['departure_date'])) ?>
                                                                                </small>
                                                                            </div>
                                                                        </td>
                                                                        <td>

                                                                            <div class="big mt-1">
                                                                                <span class="text-success font-weight-bold">
                                                                                    <?= htmlspecialchars($row['sold']) ?>
                                                                                </span>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php } } ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Top Performers Section -->
                                <?php if ($showTickets): ?>
                                <div class="col-xl-12 col-md-6">
                                    <div class="card Recent-Users top-performers-card">
                                        <div class="card-header">
                                            <h5><i class="feather icon-award text-warning mr-2"></i><?= __('top_performers') ?> - <?= __('ticket_sales') ?></h5>
                                            <span class="text-muted small"><?= __('users_with_highest_ticket_profit') ?></span>
                                        </div>
                                        <div class="filter-controls">
                                            <h6><?= __('performance_period') ?></h6>
                                            <div class="date-filter-toggle">
                                                <i class="feather icon-filter filter-icon" data-toggle="collapse" data-target="#performanceDateFilter"></i>
                                            </div>
                                        </div>
                                        <div class="collapse mb-2" id="performanceDateFilter">
                                            <div class="date-filter-panel">
                                                <label class="small text-muted"><?= __('select_month') ?></label>
                                                <input type="month" class="form-control form-control-sm date-filter" id="performanceDateInput" value="<?= date('Y-m') ?>">
                                                <button class="btn btn-sm btn-primary mt-2 apply-performance-filter"><?= __('apply_filter') ?></button>
                                            </div>
                                        </div>
                                        <div class="card-block px-0 py-3">
                                            <div class="table-responsive px-3">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th width="60" class="text-center">#</th>
                                                            <th><?= __('user_name') ?></th>
                                                            <th class="text-center"><?= __('total_tickets') ?></th>
                                                            <th class="text-right"><?= __('total_profit_usd') ?></th>
                                                            <th class="text-right"><?= __('total_profit_afs') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="topPerformersTableBody">
                                                        <?php
                                                        $topPerformers = getTopPerformersByTicketProfit(date('m'), date('Y'));
                                                        if (count($topPerformers) > 0) {
                                                            $rank = 1;
                                                            foreach ($topPerformers as $performer) {
                                                                $rankClass = '';
                                                                $rankIconClass = '';

                                                                // Add styling for top 3 performers
                                                                if ($rank === 1) {
                                                                    $rankClass = 'rank-1';
                                                                    $rankIconClass = 'fas fa-trophy';
                                                                } elseif ($rank === 2) {
                                                                    $rankClass = 'rank-2';
                                                                    $rankIconClass = 'fas fa-medal';
                                                                } elseif ($rank === 3) {
                                                                    $rankClass = 'rank-3';
                                                                    $rankIconClass = 'fas fa-award';
                                                                }

                                                                echo '<tr>';
                                                                echo '<td class="text-center">';
                                                                if ($rank <= 3) {
                                                                    echo '<span class="rank-badge ' . $rankClass . '"><i class="' . $rankIconClass . '"></i></span>';
                                                                } else {
                                                                    echo '<span class="font-weight-bold">' . $rank . '</span>';
                                                                }
                                                                echo '</td>';
                                                                echo '<td><span class="performer-name">' . htmlspecialchars($performer['user_name']) . '</span></td>';
                                                                echo '<td class="text-center"><span class="ticket-count-badge">' . htmlspecialchars($performer['total_tickets']) . '</span></td>';
                                                                echo '<td class="text-right"><span class="performer-profit">$' . number_format($performer['total_profit_usd'], 2) . '</span></td>';
                                                                echo '<td class="text-right"><span class="performer-profit">' . number_format($performer['total_profit_afs'], 2) . '</span></td>';
                                                                echo '</tr>';
                                                                $rank++;
                                                            }
                                                        } else {
                                                            echo '<tr><td colspan="4" class="text-center">' . __('no_ticket_sales_data_available') . '</td></tr>';
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- Include Admin Footer -->
<?php include '../modals/dashboard/receipt_modal.php'; ?>
<?php include '../modals/dashboard/debtor_modal.php'; ?>
<?php include '../modals/dashboard/sales_modal.php'; ?>
    <?php include '../includes/admin_footer.php'; ?>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
	<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    


    <!-- Add ApexCharts JS if not already included -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- Dashboard JS files -->
    <script src="../js/dashboard/dashboard-charts.js"></script>
    <script src="../js/dashboard/dashboard-notifications.js"></script>
    <script src="../js/dashboard/dashboard-sales.js"></script>
    <script src="../js/dashboard/dashboard-filters.js"></script>
    <script src="../js/dashboard/dashboard-receipt.js"></script>
    <script src="../js/dashboard/dashboard-debtors.js"></script>
    <script src="../js/dashboard/dashboard-dues.js"></script>
    
    <script>
    $(document).ready(function() {
        var $icon = $('#notificationToggle').find('i');
        var $collapse = $('#notificationBody');
    
        // Set initial icon state based on collapse state
        if ($collapse.hasClass('show')) {
            $icon.removeClass('icon-chevron-down').addClass('icon-chevron-up');
        } else {
            $icon.removeClass('icon-chevron-up').addClass('icon-chevron-down');
        }
    
        $collapse.on('shown.bs.collapse', function() {
            $icon.removeClass('icon-chevron-down').addClass('icon-chevron-up');
        });
    
        $collapse.on('hidden.bs.collapse', function() {
            $icon.removeClass('icon-chevron-up').addClass('icon-chevron-down');
        });
    });
    </script>
    
    </body>
    </html>