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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'sales') {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to sales dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'handlers/dashboard_handler.php';

?>


<?php include '../includes/header_sales.php'; ?>

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
                                            <h3 class="dashboard-title"><?= __('welcome_back') ?>, <?= htmlspecialchars($user['name'] ?? 'sales') ?></h3>
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
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            

                            
                            <div class="row">

                                <!-- Dues Summary Section -->
                                <div class="col-xl-12 col-md-6">



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

                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  <!-- Include sales Footer -->
<?php include '../includes/admin_footer.php'; ?>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
	<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    


<!-- Add ApexCharts JS if not already included -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    
    </body>
    </html>