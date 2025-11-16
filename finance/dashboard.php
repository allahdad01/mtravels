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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'finance') {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to finance dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'handlers/dashboard_handler.php';

?>


<?php include '../includes/header_finance.php'; ?>

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
                                            <h3 class="dashboard-title"><?= __('welcome_back') ?>, <?= htmlspecialchars($user['name'] ?? 'finance') ?></h3>
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
                            
                            

                            
                            <div class="row">

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



<!-- Modal Structure -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiptModalLabel"><?= __('enter_receipt_details') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="receiptForm">
                    <div class="mb-3">
                        <label for="receiptNumber" class="form-label"><?= __('receipt_number') ?></label>
                        <input type="text" class="form-control" id="receiptNumber" name="receipt_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="remarks" class="form-label"><?= __('remarks') ?></label>
                        <input type="text" class="form-control" id="remarks" name="remarks" required>
                    </div>
                    <input type="hidden" id="hiddenNotificationId" name="notification_id">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('close') ?></button>
                <button type="button" id="submitReceipt" class="btn btn-success"><?= __('submit') ?></button>
            </div>
        </div>
    </div>
</div>


<!-- Debtors Modal -->
<div class="modal fade" id="debtorsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title" id="debtorsModalTitle"><?= __('debtors_list') ?></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?= __('name') ?></th>
                                <th><?= __('pnr') ?></th>
                                <th><?= __('phone') ?></th>
                                <th><?= __('amount_due') ?></th>
                                <th><?= __('date') ?></th>
                            </tr>
                        </thead>
                        <tbody id="debtorsTableBody">
                            <!-- Will be populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Sales Details Modal -->
<div class="modal fade" id="salesDetailsModal" tabindex="-1" role="dialog" aria-labelledby="salesDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <h4 id="salesPeriod"></h4>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr>
                                    <th><?= __('currency') ?></th>
                                    <th><?= __('total_amount') ?></th>
                                </tr>
                                <tr>
                                    <td><?= __('usd') ?></td>
                                    <td id="salesUsd"></td>
                                </tr>
                                <tr>
                                    <td><?= __('afs') ?></td>
                                    <td id="salesAfs"></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <h5><?= __('profit_sources') ?></h5>
                <div class="table-responsive">
                    <table class="table table-hover" id="transactionTable">
                        <thead>
                            <tr>
                                <th><?= __('source') ?></th>
                                <th><?= __('usd_profit') ?></th>
                                <th><?= __('afs_profit') ?></th>
                            </tr>
                        </thead>
                        <tbody id="transactionTableBody">
                        </tbody>
                    </table>
                </div>
                
                <!-- Transaction Details Section (Initially Hidden) -->
                <div id="transactionDetailsSection" class="mt-4" style="display: none;">
                    <h5 class="border-top pt-3"><span id="detailsSectionTitle"><?= __('transaction_details') ?></span></h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead id="transactionDetailsHeader">
                                <!-- Header will be dynamically generated -->
                            </thead>
                            <tbody id="transactionDetailsBody">
                                <tr>
                                    <td colspan="5" class="text-center"><?= __('loading_details') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="printProfitDetails"><i class="feather icon-printer mr-1"></i><?= __('print') ?></button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
            </div>
        </div>
    </div>
</div>

                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  <!-- Include finance Footer -->
<?php include '../includes/admin_footer.php'; ?>
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
	<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    


<!-- Add ApexCharts JS if not already included -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <!-- Dashboard JS files -->
    <script src="js/dashboard-charts.js"></script>
    <script src="js/dashboard-notifications.js"></script>
    <script src="js/dashboard-sales.js"></script>
    <script src="js/dashboard-filters.js"></script>
    <script src="js/dashboard-receipt.js"></script>
    <script src="js/dashboard-debtors.js"></script>
    <script src="js/dashboard-profile.js"></script>
    <script src="js/dashboard-dues.js"></script>
    
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