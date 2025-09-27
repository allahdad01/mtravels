<?php
// Start session if not already started
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'client')) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
include '../includes/conn.php';

// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    // For debugging
    echo "<!-- Debug: Database Error = " . $e->getMessage() . " -->";
    
    $user = null;
}


// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}

// Get the user ID from the session
$user_id = $_SESSION["user_id"];
$ticketsQuery = "
   SELECT 
    tb.id, tb.supplier, tb.sold_to, tb.title, tb.passenger_name, tb.pnr, tb.airline, 
    tb.origin, tb.destination, tb.issue_date, tb.departure_date, tb.sold, tb.price, 
    tb.profit, tb.gender, tb.currency, tb.phone, tb.description, tb.status, 
    tb.trip_type, tb.return_date, tb.return_origin, tb.return_destination,tb.payment_currency,
    tb.market_exchange_rate,
    tb.exchange_rate,
    s.name as supplier_name,
    c.name as sold_to_name,
    ma.name as paid_to_name,
    
    
    tb.price as price,
    tb.profit as profit,
    tb.currency currency,
    tb.phone as phone,
    tb.gender as gender,
    
    tb.description as description -- Ensure description field is also included
FROM 
    ticket_reservations tb

LEFT JOIN 
    suppliers s ON tb.supplier = s.id
LEFT JOIN 
    clients c ON tb.sold_to = c.id
LEFT JOIN 
    main_account ma ON tb.paid_to = ma.id
    WHERE tb.sold_to = " . $_SESSION['user_id'] . "
ORDER BY 
    tb.id ASC
";

$ticketsResult = $conn->query($ticketsQuery);

$tickets = [];
if ($ticketsResult) {
    while ($row = $ticketsResult->fetch_assoc()) {
        // Map base ticket data
        $ticket_id = $row['id'];
        if (!isset($tickets[$ticket_id])) {
            $tickets[$ticket_id] = [
                'ticket' => [
                    'id' => $row['id'],
                    'supplier_name' => $row['supplier_name'],
                    'sold_to' => $row['sold_to_name'],
                    'paid_to' => $row['paid_to_name'],
                    'title' => $row['title'],
                    'passenger_name' => $row['passenger_name'],
                    'pnr' => $row['pnr'],
                    'airline' => $row['airline'],
                    'origin' => $row['origin'],
                    'destination' => $row['destination'],
                    'issue_date' => $row['issue_date'],
                    'departure_date' => $row['departure_date'],
                    'sold' => $row['sold'],
                    'price' => $row['price'],
                    'profit' => $row['profit'],
                    'paymentAmount' => $row['payment_amount'],
                    'gender' => $row['gender'],
                    'currency' => $row['currency'],
                    'paymentCurrency' => $row['payment_currency'],
                    
                    'exchangeRate' => $row['exchange_rate'],
                    'phone' => $row['phone'],
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'trip_type' => $row['trip_type'],
                    'return_date' => $row['return_date'],
                    'return_origin' => $row['return_origin'],
                    'return_destination' => $row['return_destination']
                ]
            ];
        }

       
    }
} else {
    echo "Error: " . $conn->error;
}




// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers where status = 'active'";
$suppliersResult = $conn->query($suppliersQuery);
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);

// Create an associative array of supplier id to supplier name for easy lookup
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}
$profilePic = !empty($user['image']) ? htmlspecialchars($user['image']) : 'default-avatar.jpg';
$imagePath = "../assets/images/client/" . $profilePic;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?= htmlspecialchars($settings['agency_name']) ?></title>
  
    <!-- Meta -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="<?= htmlspecialchars($settings['description'] ?? '') ?>" />
    <meta name="keywords" content="<?= htmlspecialchars($settings['keywords'] ?? '') ?>"/>
    <meta name="author" content="<?= htmlspecialchars($settings['author'] ?? 'CodedThemes') ?>"/>

    <!-- Favicon icon -->
    <link rel="icon" href="../assets/images/log.png" type="image/x-icon">
    <!-- fontawesome icon -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <!-- animation css -->
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <!-- vendor css -->
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->
<!-- [ navigation menu ] start -->
<nav class="pcoded-navbar">
    <div class="navbar-wrapper">
        <div class="navbar-brand header-logo">
            <a href="dashboard.php" class="b-brand">
                <div class="b-bg">
                    <img class="rounded-circle" style="width:40px;" src="../uploads/<?= htmlspecialchars($settings['logo']) ?>" alt="activity-user">
                </div>
                <span class="b-title"><?= htmlspecialchars($settings['agency_name']) ?></span>
            </a>
            <a class="mobile-menu" id="mobile-collapse" href="javascript:"><span></span></a>
        </div>
        <div class="navbar-content scroll-div">
            <ul class="nav pcoded-inner-navbar">
                <li class="nav-item pcoded-menu-caption">
                    <label>Navigation</label>
                </li>
                <li data-username="dashboard" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <a href="dashboard.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
                        <span class="pcoded-mtext">Dashboard</span>
                    </a>
                </li>
                
                <li class="nav-item pcoded-menu-caption">
                    <label>Pages</label>
                </li>
                
                <?php 
                $bookingPages = ['ticket.php', 'refund_ticket.php', 'date_change.php', 'hotel.php', 'ticket_reserve.php'];
                $isBookingActive = in_array(basename($_SERVER['PHP_SELF']), $bookingPages);
                ?>
                <li data-username="bookings" class="nav-item pcoded-hasmenu <?php echo $isBookingActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext">Bookings</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <?php 
                        $ticketPages = ['ticket.php', 'refund_ticket.php', 'date_change.php', 'ticket_reserve.php'];
                        $isTicketActive = in_array(basename($_SERVER['PHP_SELF']), $ticketPages);
                        ?>
                        <li data-username="tickets" class="nav-item pcoded-hasmenu <?php echo $isTicketActive ? 'active pcoded-trigger' : ''; ?>">
                            <a href="javascript:" class="nav-link">
                                <span class="pcoded-mtext">Tickets</span>
                            </a>
                            <ul class="pcoded-submenu">
                                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket.php' ? 'active' : ''; ?>">
                                    <a href="ticket.php" class="">Book Tickets</a>
                                </li>
                                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'refund_ticket.php' ? 'active' : ''; ?>">
                                    <a href="refund_ticket.php" class="">Refunded Tickets</a>
                                </li>
                                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'date_change.php' ? 'active' : ''; ?>">
                                    <a href="date_change.php" class="">Date Changed Tickets</a>
                                </li>
                                <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'ticket_reserve.php' ? 'active' : ''; ?>">
                                    <a href="ticket_reserve.php" class="">Ticket Reserve</a>
                                </li>
                            </ul>
                        </li>
                        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'hotel.php' ? 'active' : ''; ?>">
                            <a href="hotel.php" class="">Hotel</a>
                        </li>
                    </ul>
                </li>
                <li data-username="umrah" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'umrah.php' ? 'active' : ''; ?>">
                    <a href="umrah.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-map"></i></span>
                        <span class="pcoded-mtext">Umrah Management</span>
                    </a>
                </li>
                <li data-username="visa" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'visa.php' ? 'active' : ''; ?>">
                    <a href="visa.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-globe"></i></span>
                        <span class="pcoded-mtext">Visa</span>
                    </a>
                </li>
                
                <li data-username="report" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : ''; ?>">
                    <a href="report.php" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-file"></i></span>
                        <span class="pcoded-mtext">Reports</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- [ navigation menu ] end -->


<!-- [ Header ] start -->
<header class="navbar pcoded-header navbar-expand-lg navbar-light">
    <div class="m-header">
        <a class="mobile-menu" id="mobile-collapse1" href="javascript:"><span></span></a>
        <a href="dashboard.php" class="b-brand">
            <div class="b-bg">
                <img class="rounded-circle" style="width:40px;" src="../uploads/<?= htmlspecialchars($settings['logo']) ?>" alt="activity-user">
            </div>
            <span class="b-title"><?= htmlspecialchars($settings['agency_name']) ?></span>
        </a>
    </div>
    <a class="mobile-menu" id="mobile-header" href="javascript:">
        <i class="feather icon-more-horizontal"></i>
    </a>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li><a href="javascript:" class="full-screen" onclick="javascript:toggleFullScreen()"><i class="feather icon-maximize"></i></a></li>
            <li class="nav-item">
                <div class="main-search">
                    <div class="input-group">
                        <input type="text" id="m-search" class="form-control" placeholder="Search . . .">
                        <a href="javascript:" class="input-group-append search-close">
                            <i class="feather icon-x input-group-text"></i>
                        </a>
                        <span class="input-group-append search-btn btn btn-primary">
                            <i class="feather icon-search input-group-text"></i>
                        </span>
                    </div>
                </div>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li>
                <div class="dropdown drp-user">
                    <a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown">
                        <i class="icon feather icon-settings"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right profile-notification">
                        <div class="pro-head">
                            <img src="<?= $imagePath ?>" class="img-radius" alt="User-Profile-Image"> 
                            <span><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest'; ?></span>
                            <span class="text-muted"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User'; ?></span>
                            <a href="logout.php" class="dud-logout" title="Logout">
                                <i class="feather icon-log-out"></i>
                            </a>
                        </div>
                        <ul class="pro-body">
                            <li>
                                <a href="javascript:void(0)" class="dropdown-item" data-toggle="modal" data-target="#profileModal">
                                    <i class="feather icon-user"></i> Profile
                                </a>
                            </li>
                            <li>
                                <a href="javascript:void(0)" class="dropdown-item" data-toggle="modal" data-target="#settingsModal">
                                    <i class="feather icon-settings"></i> Settings
                                </a>
                            </li>
                            <li>
                                <a href="logout.php" class="dropdown-item">
                                    <i class="feather icon-log-out"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</header>
<!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header">
                        <div class="page-block">
                            <div class="row align-items-center">
                                <div class="col-md-12">
                                    <div class="page-header-title">
                                        <h5 class="m-b-10">Ticket</h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:">Ticket Reservations</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="mb-3 text-right">
                                        <!-- Filter Input -->
                                    <input type="text" id="pnrFilter" class="form-control mb-3" placeholder="Search by PNR...">
                                       
                                    </div>
                                    <div class="card">
                                        <!-- body -->
                                        
                                         <div class="table-responsive">
                                            


                                <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Action</th>
                                                <th>Payment</th>
                                                <th>Sold To</th>
                                                <th>Paid To</th>
                                                <th>Title</th>
                                                <th>Passenger Name</th>
                                                <th>PNR</th>
                                                <th>Sector</th>
                                                <th>Airline</th>
                                                <th>Issue Date</th>
                                                <th>Departure Date</th>
                                                <th>Sold</th>
                                            </tr>
                                        </thead>
                                       <tbody id="ticketTable">
                                            <?php 
                                            $counter = 1; // Start counter from 1
                                            foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td><?= $counter++ ?></td> <!-- Increment counter for each row -->
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn btn-secondary dropdown-toggle" type="button" id="actionDropdown<?= $ticket['ticket']['id'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            <i class="feather icon-more-vertical"></i> Actions
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="actionDropdown<?= $ticket['ticket']['id'] ?>">
                                                            <button class="dropdown-item view-details" data-ticket='<?= htmlspecialchars(json_encode($ticket)) ?>'>
                                                                <i class="feather icon-eye text-primary mr-2"></i> View Details
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                <?php
                                                    // Get client type from clients table
                                                    $soldTo = $ticket['ticket']['sold_to'];
                                                    $isAgencyClient = false; // Default to not agency client
                                                    
                                                    // Fix: We need to query the clients table using the client name from sold_to
                                                    $clientQuery = $conn->query("SELECT client_type FROM clients WHERE name = '$soldTo'");
                                                    if ($clientQuery && $clientQuery->num_rows > 0) {
                                                        $clientRow = $clientQuery->fetch_assoc();
                                                        // Only show payment status for agency clients
                                                        $isAgencyClient = ($clientRow['client_type'] === 'agency');
                                                    }
                                                    
                                                    // Only show payment status for agency clients
                                                    if ($isAgencyClient) {
                                                        // Calculate payment status
                                                        $transactionTotal = 0;
                                                        $soldAmount = floatval($ticket['ticket']['sold']);
                                                        
                                                        // Get ticket ID first
                                                        $ticketId = $ticket['ticket']['id'];
                                                        
                                                        // Get exchange rate from database
                                                        $exchangeRateQuery = $conn->query("SELECT exchange_rate FROM ticket_bookings WHERE id = '$ticketId' LIMIT 1");
                                                        $exchangeRate = 1; // Default value
                                                        if ($exchangeRateRow = $exchangeRateQuery->fetch_assoc()) {
                                                            $exchangeRate = floatval($exchangeRateRow['exchange_rate']);
                                                        }
                                                        
                                                        // Query transactions from account_transactions table
                                                        $transactionQuery = $conn->query("SELECT * FROM main_account_transactions WHERE 
                                                            transaction_of = 'ticket_sale' 
                                                            AND reference_id = '$ticketId'");
                                                        
                                                        if ($transactionQuery && $transactionQuery->num_rows > 0) {
                                                            while ($transaction = $transactionQuery->fetch_assoc()) {
                                                                if ($transaction['currency'] === 'USD') {
                                                                    // Convert USD to AFS
                                                                    $transactionTotal += floatval($transaction['amount']) * $exchangeRate;
                                                                } else {
                                                                    // Already in AFS
                                                                    $transactionTotal += floatval($transaction['amount']);
                                                                }
                                                            }
                                                        }
                                                        
                                                        // Status icon based on payment status
                                                        if ($transactionTotal <= 0) {
                                                            // No transactions
                                                            echo '<i class="fas fa-circle text-danger" title="No payment received"></i>';
                                                        } elseif ($transactionTotal < $soldAmount) {
                                                            // Partial payment
                                                            $percentage = round(($transactionTotal / $soldAmount) * 100);
                                                            echo '<i class="fas fa-circle text-warning" title="Partial payment: ' . number_format($transactionTotal, 2) . ' / ' . number_format($soldAmount, 2) . ' AFS (' . $percentage . '%)"></i>';
                                                        } else {
                                                            // Fully paid
                                                            echo '<i class="fas fa-circle text-success" title="Fully paid"></i>';
                                                        }
                                                    } else {
                                                        // Not an agency client - show neutral icon
                                                        echo '<i class="fas fa-minus text-muted" title="Not an agency client"></i>';
                                                    }
                                                ?>
                                                </td>
                                                <td><?= htmlspecialchars($ticket['ticket']['sold_to']) ?></td>
                                                <td><?= htmlspecialchars($ticket['ticket']['paid_to']) ?></td>
                                                <td><?= htmlspecialchars($ticket['ticket']['title']) ?></td>
                                                <td><?= htmlspecialchars($ticket['ticket']['passenger_name']) ?></td>
                                                <td class="pnr-field"><?= htmlspecialchars($ticket['ticket']['pnr']) ?></td>
                                                <td>
                                                    <?php if ($ticket['ticket']['trip_type'] === 'one_way'): ?>
                                                        <?= htmlspecialchars($ticket['ticket']['origin']) ?> - <?= htmlspecialchars($ticket['ticket']['destination']) ?>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($ticket['ticket']['origin']) ?> - <?= htmlspecialchars($ticket['ticket']['destination']) ?> - 
                                                        <?= htmlspecialchars($ticket['ticket']['return_destination']) ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($ticket['ticket']['airline']) ?></td>
                                                <td><?= htmlspecialchars($ticket['ticket']['issue_date']) ?></td>
                                                <td>
                                                <?php if ($ticket['ticket']['trip_type'] === 'one_way'): ?>
                                                    <?= htmlspecialchars($ticket['ticket']['departure_date']) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($ticket['ticket']['departure_date']) ?> - <?= htmlspecialchars($ticket['ticket']['return_date']) ?>
                                                <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($ticket['ticket']['sold']) ?></td>
                                                
                                            </tr>

                                           
                                            <?php endforeach; ?>
                                        </tbody>
                            </table>
                                   <!-- Ticket details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title">
                    <i class="feather icon-clipboard mr-2"></i>Ticket Details
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <!-- Top Summary Card -->
                <div class="bg-light p-4 border-bottom">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Sold Price</div>
                            <h4 class="mb-0 text-primary" id="sold-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Base Price</div>
                            <h4 class="mb-0 text-info" id="base-price">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Profit</div>
                            <h4 class="mb-0 text-success" id="profit">-</h4>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="small text-muted mb-1">Payment Amount</div>
                            <h4 class="mb-0 text-success" id="paymentAmount">-</h4>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-pills nav-fill p-3" id="detailsTab" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary" role="tab">
                            <i class="feather icon-info mr-2"></i>Summary
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="details-description-tab" data-toggle="tab" href="#details-description" role="tab">
                            <i class="feather icon-file-text mr-2"></i>Description
                        </a>
                    </li>
                    
                </ul>

                <!-- Tab Content -->
                <div class="tab-content p-4">
                    <!-- Summary Tab -->
                    <div class="tab-pane fade show active" id="details-summary" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Client Information</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Passenger Name</span>
                                            <strong id="passenger-name">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">PNR</span>
                                            <strong id="pnr">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Sold To</span>
                                            <strong id="sold-to">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Paid To</span>
                                            <strong id="paid-to">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-3 text-muted">Additional Details</h6>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Currency</span>
                                            <strong id="currency">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Payment Currency</span>
                                            <strong id="payment-currency">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Exchange Rate</span>
                                            <strong id="exchangeRate">-</strong>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Phone</span>
                                            <strong id="phone">-</strong>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Gender</span>
                                            <strong id="gender">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description Tab -->
                    <div class="tab-pane fade" id="details-description" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <p id="description" class="mb-0">-</p>
                            </div>
                        </div>
                    </div>

                   
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-2"></i>Close
                </button>
                
            </div>
        </div>
    </div>
</div>

<style>
            /* Modal Styles */
            .modal-content {
                border-radius: 0.5rem;
            }

            .modal-header {
                padding: 1.25rem;
            }

            .nav-pills .nav-link {
                border-radius: 0.25rem;
                transition: all 0.3s;
                color: #6c757d;
            }

            .nav-pills .nav-link.active {
                background-color: #4099ff;
                color: white;
            }

            .nav-pills .nav-link:hover:not(.active) {
                background-color: #e9ecef;
            }

            .card {
                transition: transform 0.2s;
            }


            .badge-pill {
                padding: 0.5em 1em;
            }

            /* Responsive Adjustments */
            @media (max-width: 768px) {
                .modal-dialog {
                    margin: 0.5rem;
                }
                
                .nav-pills {
                    flex-wrap: nowrap;
                    overflow-x: auto;
                    padding: 1rem;
                }
                
                .nav-pills .nav-link {
                    white-space: nowrap;
                }
            }
</style>



                                <!-- Book Ticket Modal -->
                                <div class="modal fade" id="bookTicketModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5>Reserve a Ticket</h5>
                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                            </div>
                                            <form id="bookTicketForm">
                                                <div class="modal-body">
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="supplier">Supplier</label>
                                                            <select class="form-control" id="supplier" name="supplier" required>
                                                                <option value="">Select Supplier</option>
                                                                <?php foreach ($suppliers as $supplier): ?>
                                                                <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                         
                                                        <div class="form-group col-md-3">
                                                            <label for="soldTo">Sold To</label>
                                                            <select class="form-control" id="soldTo" name="soldTo" required>
                                                                <option value="">Select Client</option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active'");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']} (USD: {$row['usd_balance']}, AFS: {$row['afs_balance']})
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="tripType">Trip Type</label>
                                                            <select class="form-control" id="tripType" name="tripType" required>
                                                                <option value="one_way">One Way</option>
                                                                <option value="round_trip">Round Trip</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="title">Title</label>
                                                            <select class="form-control" id="title" name="title" required>
                                                                <option value="Mr">Mr</option>
                                                                <option value="Mrs">Mrs</option>
                                                                <option value="Child">Child</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="gender">Gender</label>
                                                            <select class="form-control" id="gender" name="gender" required>
                                                                <option value="Male">Male</option>
                                                                <option value="Female">Female</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="passengerName">Passenger Name</label>
                                                            <input type="text" class="form-control" id="passengerName" name="passengerName" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="pnr">PNR</label>
                                                            <input type="text" class="form-control" id="pnr" name="pnr" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="phone">Phone</label>
                                                            <input type="text" class="form-control" id="phone" name="phone" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="origin">From</label>
                                                            <input type="text" class="form-control" id="origin" name="origin" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="destination">To</label>
                                                            <input type="text" class="form-control" id="destination" name="destination" required>
                                                        </div>
                                                        <div id="returnJourneyFields" class="form-group col-md-3" style="display: none;">
                                                            <label for="returnDestination">Return To</label>
                                                            <input type="text" class="form-control" id="returnDestination" name="returnDestination">
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="airline">Airline</label>
                                                            <select class="form-control" id="airline" name="airline" required>
                                                                <!-- Airline options go here -->
                                                                <option value="Ariana AFG">Ariana AFG</option>
                                                                <option value="Kam Air">Kam Air</option>
                                                                <option value="Turkish Airlines">Turkish Airlines</option>
                                                                <option value="Emirates">Emirates</option>
                                                                <option value="Qatar Airways">Qatar Airways</option>
                                                                <option value="Iran Air">Iran Air</option>
                                                                <option value="Flydubai">Flydubai</option>
                                                                <option value="Air India">Air India</option>
                                                                <option value="PIA">PIA</option>
                                                                <option value="Uzbekistan Airways">Uzbekistan Airways</option>
                                                                <option value="Air Arabia">Air Arabia</option>
                                                                <option value="Saudia">Saudia (Saudi Arabian Airlines)</option>
                                                                <option value="Etihad Airways">Etihad Airways</option>
                                                                <option value="Korean Air">Korean Air</option>
                                                                <option value="China Southern Airlines">China Southern Airlines</option>
                                                                <option value="China Eastern Airlines">China Eastern Airlines</option>
                                                                <option value="Lufthansa">Lufthansa</option>
                                                                <option value="British Airways">British Airways</option>
                                                                <option value="Singapore Airlines">Singapore Airlines</option>
                                                                <option value="Thai Airways">Thai Airways</option>
                                                                <option value="Air France">Air France</option>
                                                                <option value="KLM Royal Dutch Airlines">KLM Royal Dutch Airlines</option>
                                                                <option value="American Airlines">American Airlines</option>
                                                                <option value="United Airlines">United Airlines</option>
                                                                <option value="Delta Air Lines">Delta Air Lines</option>
                                                                <option value="Aeroflot">Aeroflot</option>
                                                                <option value="Japan Airlines">Japan Airlines (JAL)</option>
                                                                <option value="All Nippon Airways">All Nippon Airways (ANA)</option>
                                                                <option value="Malaysia Airlines">Malaysia Airlines</option>
                                                                <option value="IndiGo">IndiGo</option>
                                                                <option value="SpiceJet">SpiceJet</option>
                                                                <option value="SriLankan Airlines">SriLankan Airlines</option>
                                                                <option value="Oman Air">Oman Air</option>
                                                                <option value="Kuwait Airways">Kuwait Airways</option>
                                                                <option value="EgyptAir">EgyptAir</option>
                                                                <option value="Royal Jordanian">Royal Jordanian</option>
                                                                <option value="Thai Vietjet Air">Thai Vietjet Air</option>
                                                                <option value="Scoot">Scoot</option>
                                                                <option value="Norwegian Air Shuttle">Norwegian Air Shuttle</option>
                                                                <option value="Alitalia">Alitalia</option>
                                                                <option value="Iberia">Iberia</option>
                                                                <option value="Austrian Airlines">Austrian Airlines</option>
                                                                <option value="SWISS International Air Lines">SWISS International Air Lines</option>
                                                                <option value="Finnair">Finnair</option>
                                                                <option value="TAP Air Portugal">TAP Air Portugal</option>
                                                                <option value="Virgin Atlantic">Virgin Atlantic</option>
                                                                <option value="Cathay Pacific">Cathay Pacific</option>
                                                                <option value="Hainan Airlines">Hainan Airlines</option>
                                                                <option value="Garuda Indonesia">Garuda Indonesia</option>
                                                                <option value="Qantas Airways">Qantas Airways</option>
                                                                <option value="Aerolineas Argentinas">Aerolineas Argentinas</option>
                                                                <option value="GOL Linhas Aéreas">GOL Linhas Aéreas</option>
                                                                <option value="LATAM Airlines">LATAM Airlines</option>
                                                                <option value="Air Canada">Air Canada</option>
                                                                <option value="WestJet">WestJet</option>
                                                                <option value="Alaska Airlines">Alaska Airlines</option>
                                                                <option value="Spirit Airlines">Spirit Airlines</option>
                                                                <option value="Frontier Airlines">Frontier Airlines</option>
                                                                <option value="JetBlue Airways">JetBlue Airways</option>
                                                                <option value="AA">American Airlines (AA)</option>
                                                                <option value="AC">Air Canada (AC)</option>
                                                                <option value="AF">Air France (AF)</option>
                                                                <option value="AI">Air India (AI)</option>
                                                                <option value="AK">AirAsia (AK)</option>
                                                                <option value="AM">Aeroméxico (AM)</option>
                                                                <option value="AR">Aerolineas Argentinas (AR)</option>
                                                                <option value="AS">Alaska Airlines (AS)</option>
                                                                <option value="AY">Finnair (AY)</option>
                                                                <option value="AZ">ITA Airways (AZ)</option>
                                                                <option value="BA">British Airways (BA)</option>
                                                                <option value="BI">Royal Brunei Airlines (BI)</option>
                                                                <option value="BR">EVA Air (BR)</option>
                                                                <option value="CA">Air China (CA)</option>
                                                                <option value="CI">China Airlines (CI)</option>
                                                                <option value="CX">Cathay Pacific (CX)</option>
                                                                <option value="CZ">China Southern Airlines (CZ)</option>
                                                                <option value="DL">Delta Air Lines (DL)</option>
                                                                <option value="EK">Emirates (EK)</option>
                                                                <option value="ET">Ethiopian Airlines (ET)</option>
                                                                <option value="EY">Etihad Airways (EY)</option>
                                                                <option value="FI">Icelandair (FI)</option>
                                                                <option value="FJ">Fiji Airways (FJ)</option>
                                                                <option value="GA">Garuda Indonesia (GA)</option>
                                                                <option value="GF">Gulf Air (GF)</option>
                                                                <option value="HA">Hawaiian Airlines (HA)</option>
                                                                <option value="HU">Hainan Airlines (HU)</option>
                                                                <option value="IB">Iberia (IB)</option>
                                                                <option value="JL">Japan Airlines (JL)</option>
                                                                <option value="JJ">LATAM Airlines Brasil (JJ)</option>
                                                                <option value="KE">Korean Air (KE)</option>
                                                                <option value="KL">KLM Royal Dutch Airlines (KL)</option>
                                                                <option value="KM">Air Malta (KM)</option>
                                                                <option value="KQ">Kenya Airways (KQ)</option>
                                                                <option value="KU">Kuwait Airways (KU)</option>
                                                                <option value="LA">LATAM Airlines (LA)</option>
                                                                <option value="LH">Lufthansa (LH)</option>
                                                                <option value="LO">LOT Polish Airlines (LO)</option>
                                                                <option value="LX">SWISS International Air Lines (LX)</option>
                                                                <option value="LY">EL AL Israel Airlines (LY)</option>
                                                                <option value="MH">Malaysia Airlines (MH)</option>
                                                                <option value="MS">EgyptAir (MS)</option>
                                                                <option value="MU">China Eastern Airlines (MU)</option>
                                                                <option value="NH">All Nippon Airways (NH)</option>
                                                                <option value="NZ">Air New Zealand (NZ)</option>
                                                                <option value="OA">Olympic Air (OA)</option>
                                                                <option value="OK">Czech Airlines (OK)</option>
                                                                <option value="OS">Austrian Airlines (OS)</option>
                                                                <option value="OU">Croatia Airlines (OU)</option>
                                                                <option value="OZ">Asiana Airlines (OZ)</option>
                                                                <option value="PD">Porter Airlines (PD)</option>
                                                                <option value="PG">Bangkok Airways (PG)</option>
                                                                <option value="PR">Philippine Airlines (PR)</option>
                                                                <option value="QR">Qatar Airways (QR)</option>
                                                                <option value="QF">Qantas Airways (QF)</option>
                                                                <option value="RJ">Royal Jordanian (RJ)</option>
                                                                <option value="RO">TAROM (RO)</option>
                                                                <option value="SA">South African Airways (SA)</option>
                                                                <option value="SK">Scandinavian Airlines (SK)</option>
                                                                <option value="SN">Brussels Airlines (SN)</option>
                                                                <option value="SQ">Singapore Airlines (SQ)</option>
                                                                <option value="SU">Aeroflot (SU)</option>
                                                                <option value="SV">Saudia (SV)</option>
                                                                <option value="TA">TACA Airlines (TA)</option>
                                                                <option value="TG">Thai Airways (TG)</option>
                                                                <option value="TK">Turkish Airlines (TK)</option>
                                                                <option value="TP">TAP Air Portugal (TP)</option>
                                                                <option value="TU">Tunisair (TU)</option>
                                                                <option value="UA">United Airlines (UA)</option>
                                                                <option value="UL">SriLankan Airlines (UL)</option>
                                                                <option value="UX">Air Europa (UX)</option>
                                                                <option value="VA">Virgin Australia (VA)</option>
                                                                <option value="VN">Vietnam Airlines (VN)</option>
                                                                <option value="VS">Virgin Atlantic (VS)</option>
                                                                <option value="VT">Air Tahiti (VT)</option>
                                                                <option value="VY">Vueling Airlines (VY)</option>
                                                                <option value="WF">Widerøe (WF)</option>
                                                                <option value="WN">Southwest Airlines (WN)</option>
                                                                <option value="WS">WestJet (WS)</option>
                                                                <option value="WY">Oman Air (WY)</option>
                                                                <option value="X3">TUI fly Deutschland (X3)</option>
                                                                <option value="XQ">SunExpress (XQ)</option>
                                                                <option value="XY">flynas (XY)</option>
                                                                <option value="YV">Mesa Airlines (YV)</option>
                                                                <option value="ZB">Monarch Airlines (ZB)</option>
                                                                <option value="ZK">Great Lakes Airlines (ZK)</option>
                                                                <option value="ZL">Regional Express (ZL)</option>
                                                                <option value="ZP">Paranair (ZP)</option>
                                                                <option value="ZR">Aviacon Zitotrans (ZR)</option>
                                                                <option value="ZS">Sama Airlines (ZS)</option>
                                                                <option value="ZT">Titan Airways (ZT)</option>
                                                                <option value="ZU">Heli Air Monaco (ZU)</option>
                                                                <option value="ZV">Air Midwest (ZV)</option>
                                                                <option value="ZW">Air Wisconsin (ZW)</option>
                                                                <option value="ZX">Air Georgian (ZX)</option>
                                                                <option value="ZY">Sky Airlines (ZY)</option>
                                                                <option value="3K">Jetstar Asia Airways (3K)</option>
                                                                <option value="4O">Interjet (4O)</option>
                                                                <option value="5J">Cebu Pacific (5J)</option>
                                                                <option value="5Y">Atlas Air (5Y)</option>
                                                                <option value="6E">IndiGo (6E)</option>
                                                                <option value="7C">Jeju Air (7C)</option>
                                                                <option value="7F">First Air (7F)</option>
                                                                <option value="8U">Afriqiyah Airways (8U)</option>
                                                                <option value="9C">Spring Airlines (9C)</option>
                                                                <option value="9W">Jet Airways (9W)</option>
                                                                <option value="A3">Aegean Airlines (A3)</option>
                                                                <option value="A9">Georgian Airways (A9)</option>
                                                                <option value="AD">Azul Brazilian Airlines (AD)</option>
                                                                <option value="AK">AirAsia (AK)</option>
                                                                <option value="AV">Avianca (AV)</option>
                                                                <option value="AZ">ITA Airways (AZ)</option>
                                                                <option value="BE">Flybe (BE)</option>
                                                                <option value="BT">airBaltic (BT)</option>
                                                                <option value="BX">Air Busan (BX)</option>
                                                                <option value="CM">Copa Airlines (CM)</option>
                                                                <option value="DN">Norwegian Air Argentina (DN)</option>
                                                                <option value="DP">Pobeda Airlines (DP)</option>
                                                                <option value="DY">Norwegian Air Shuttle (DY)</option>
                                                                <option value="E9">Evelop Airlines (E9)</option>
                                                                <option value="EI">Aer Lingus (EI)</option>
                                                                <option value="EN">Air Dolomiti (EN)</option>
                                                                <option value="EW">Eurowings (EW)</option>
                                                                <option value="F9">Frontier Airlines (F9)</option>
                                                                <option value="G3">GOL Linhas Aéreas (G3)</option>
                                                                <option value="GF">Gulf Air (GF)</option>
                                                                <option value="G9">Air Arabia (G9)</option>
                                                                <option value="H1">Hahn Air (H1)</option>
                                                                <option value="HY">Uzbekistan Airways (HY)</option>
                                                                <option value="IR">Iran Air (IR)</option>
                                                                <option value="IZ">Arkia Israeli Airlines (IZ)</option>
                                                                <option value="J2">Azerbaijan Airlines (J2)</option>
                                                                <option value="JD">Beijing Capital Airlines (JD)</option>
                                                                <option value="JQ">Jetstar Airways (JQ)</option>
                                                                <option value="KY">Kunming Airlines (KY)</option>
                                                                <option value="LG">Luxair (LG)</option>
                                                                <option value="ME">Middle East Airlines (ME)</option>
                                                                <option value="MF">XiamenAir (MF)</option>
                                                                <option value="MM">Peach Aviation (MM)</option>
                                                                <option value="MN">Kulula (MN)</option>
                                                                <option value="NG">Lauda (NG)</option>
                                                                <option value="NK">Spirit Airlines (NK)</option>
                                                                <option value="NT">Binter Canarias (NT)</option>
                                                                <option value="OD">Batik Air Malaysia (OD)</option>
                                                                <option value="OG">Play (OG)</option>
                                                                <option value="PC">Pegasus Airlines (PC)</option>
                                                                <option value="PG">Bangkok Airways (PG)</option>
                                                                <option value="PN">West Air China (PN)</option>
                                                                <option value="PS">Ukraine International Airlines (PS)</option>
                                                                <option value="PZ">LATAM Paraguay (PZ)</option>
                                                                <option value="RA">Nepal Airlines (RA)</option>
                                                                <option value="RD">Ryan Air Dominicana (RD)</option>
                                                                <option value="RE">Stobart Air (RE)</option>
                                                                <option value="S7">S7 Airlines (S7)</option>
                                                                <option value="SG">SpiceJet (SG)</option>
                                                                <option value="SL">Thai Lion Air (SL)</option>
                                                                <option value="SM">Air Cairo (SM)</option>
                                                                <option value="TB">TUI fly Belgium (TB)</option>
                                                                <option value="TI">Tropic Air (TI)</option>
                                                                <option value="TO">Transavia France (TO)</option>
                                                                <option value="TR">Scoot (TR)</option>
                                                                <option value="TS">Air Transat (TS)</option>
                                                                <option value="TT">Tigerair Australia (TT)</option>
                                                                <option value="TZ">ZIPAIR Tokyo (TZ)</option>
                                                                <option value="U2">easyJet (U2)</option>
                                                                <option value="U6">Ural Airlines (U6)</option>
                                                                <option value="UT">UTair (UT)</option>
                                                                <option value="UX">Air Europa (UX)</option>
                                                                <option value="V7">Volotea (V7)</option>
                                                                <option value="VB">Viva Aerobus (VB)</option>
                                                                <option value="VE">EasyFly (VE)</option>
                                                                <option value="VG">VLM Airlines (VG)</option>
                                                                <option value="VO">Voepass Linhas Aéreas (VO)</option>
                                                                <option value="VQ">Novoair (VQ)</option>
                                                                <option value="VV">Viva Air Colombia (VV)</option>
                                                                <option value="W6">Wizz Air (W6)</option>
                                                                <option value="WF">Widerøe (WF)</option>
                                                                <option value="WN">Southwest Airlines (WN)</option>
                                                                <option value="Y4">Volaris (Y4)</option>
                                                                <option value="Z2">Philippines AirAsia (Z2)</option>
                                                                <option value="ZF">Azur Air (ZF)</option>
                                                                <option value="ZL">Regional Express (ZL)</option>
                                                                <option value="ZP">Paranair (ZP)</option>
                                                                <option value="ZR">Aviacon Zitotrans (ZR)</option>
                                                                <option value="ZS">Sama Airlines (ZS)</option>
                                                                <option value="ZT">Titan Airways (ZT)</option>
                                                                <option value="ZU">Heli Air Monaco (ZU)</option>
                                                                <option value="ZV">Air Midwest (ZV)</option>
                                                                
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="issueDate">Issue Date</label>
                                                            <input type="date" class="form-control" id="issueDate" name="issueDate" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="departureDate">Departure Date</label>
                                                            <input type="date" class="form-control" id="departureDate" name="departureDate" required>
                                                        </div>
                                                        <div id="returnDateField" class="form-group col-md-3" style="display: none;">
                                                            <label for="returnDate">Return Date</label>
                                                            <input type="date" class="form-control" id="returnDate" name="returnDate">
                                                        </div>
                                                        <div class="form-group col-md-3" id="baseFieldContainer">
                                                            <label for="base">Base</label>
                                                            <input type="number" class="form-control" id="base" name="base" step="any" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="sold">Sold</label>
                                                            <input type="number" class="form-control" id="sold" name="sold" step="any" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="profit">Profit</label>
                                                            <input type="number" class="form-control" id="pro" name="pro" step="any" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="curr">Currency</label>
                                                            <input class="form-control" id="curr" name="curr" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="description">Description</label>
                                                            <input type="text" class="form-control" id="description" name="description" required>
                                                        </div>
                                                    </div>
                                                    
                                                    
                                                
                                                    <div class="form-row">
                                                    <div class="form-group col-md-6">
                                                            <label for="exchangeRate">Exchange Rate</label>
                                                            <input type="number" class="form-control" id="exchangeRate" name="exchangeRate" step="any">
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label for="paidTo">Paid To</label>
                                                            <select class="form-control" id="paidTo" name="paidTo" required>
                                                                <option value="">Select Main Account</option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active'");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']} (USD: {$row['usd_balance']}, AFS: {$row['afs_balance']})
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Book</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                               <!-- Profile Modal -->
   <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i>User Profile
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Email</label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Phone</label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Join Date</label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">Address</label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3">Account Information</h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0">Account Created</p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                
            </div>
        </div>
    </div>
</div>

<style>
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #2ed8b6;
            border: 2px solid #fff;
        }

        .profile-status.online {
            background-color: #2ed8b6;
        }

        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item p {
            font-weight: 500;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #2196f3;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-footer {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        /* Updated Modal Styles */
        .modal-lg {
            max-width: 800px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-label input,
        .floating-label textarea {
            height: auto;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }

        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            transition: all 0.2s ease;
            color: #6c757d;
            margin: 0;
            padding: 0 0.2rem;
            background-color: #fff;
            font-size: 1rem;
        }

        .floating-label textarea ~ label {
            top: 1rem;
            transform: translateY(0);
        }

        /* Active state - when input has value or is focused */
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label,
        .floating-label textarea:focus ~ label,
        .floating-label textarea:not(:placeholder-shown) ~ label {
            top: 0;
            transform: translateY(-50%) scale(0.85);
            background-color: #fff;
            color: #4099ff;
            z-index: 1;
        }

        .floating-label input:focus,
        .floating-label textarea:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
            outline: none;
        }

        /* Ensure inputs have placeholder to trigger :not(:placeholder-shown) */
        .floating-label input,
        .floating-label textarea {
            placeholder: " ";
        }

        /* Rest of the styles remain the same */
        .profile-upload-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(64, 153, 255, 0.9);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            transform: scale(1.1);
            background: rgba(64, 153, 255, 1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }

            .profile-upload-preview {
                width: 120px;
                height: 120px;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .floating-label input,
            .floating-label textarea {
                padding: 0.6rem;
                font-size: 0.95rem;
            }

            .floating-label label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            .profile-upload-preview {
                width: 100px;
                height: 100px;
            }

            .upload-overlay {
                width: 30px;
                height: 30px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer button {
                width: 100%;
                margin: 0.25rem 0;
            }
        }
</style>

                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-settings mr-2"></i>Profile Settings
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2">Click to change profile picture</small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i>Personal Information
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName">Full Name</label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail">Email Address</label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone">Phone Number</label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress">Address</label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i>Change Password
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword">Current Password</label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword">New Password</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword">Confirm Password</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i>Cancel
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i>Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>


                           
                             
                                <!-- Edit ticket tab -->
                                    <div class="modal fade" id="editTicketModal" tabindex="-1" role="dialog">
                                        <div class="modal-dialog modal-lg" role="document">
                                            <div id="editLoader" style="display: none; text-align: center;">
                                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...
                                            </div>

                                            <form id="editTicketForm">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Edit Ticket</h5>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" id="editTicketId" name="id">
                                                       <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="supplier">Supplier</label>
                                                            <select class="form-control" id="editSupplier" name="supplier" required readonly>
                                                                <option value="">Select Supplier</option>
                                                                <?php foreach ($suppliers as $supplier): ?>
                                                                <option value="<?= $supplier['id'] ?>"><?= $supplier['name'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editSoldTo">Sold To</label>
                                                            <select class="form-control" id="editSoldTo" name="soldTo" required readonly>
                                                                <option value="">Select Client</option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM clients where status = 'active'");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']} (USD: {$row['usd_balance']}, AFS: {$row['afs_balance']})
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editTripType">Trip Type</label>
                                                            <select class="form-control" id="editTripType" name="tripType" required>
                                                                <option value="one_way">One Way</option>
                                                                <option value="round_trip">Round Trip</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editTitle">Title</label>
                                                            <select class="form-control" id="editTitle" name="title" required>
                                                                <option value="Mr">Mr</option>
                                                                <option value="Mrs">Mrs</option>
                                                                <option value="Child">Child</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editGender">Gender</label>
                                                            <select class="form-control" id="editGender" name="gender" required>
                                                                <option value="Male">Male</option>
                                                                <option value="Female">Female</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPassengerName">Passenger Name</label>
                                                            <input type="text" class="form-control" id="editPassengerName" name="passengerName" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPhone">Phone</label>
                                                            <input type="text" class="form-control" id="editPhone" name="phone" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPnr">PNR</label>
                                                            <input type="text" class="form-control" id="editPnr" name="pnr" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editOrigin">From</label>
                                                            <input type="text" class="form-control" id="editOrigin" name="origin" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editDestination">To</label>
                                                            <input type="text" class="form-control" id="editDestination" name="destination" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editAirline">Airline</label>
                                                            <select class="form-control" id="editAirline" name="airline" required>
                                                                <!-- Airline options go here -->
                                                                <option value="Ariana AFG">Ariana AFG</option>
                                                                <option value="Kam Air">Kam Air</option>
                                                                <option value="Turkish Airlines">Turkish Airlines</option>
                                                                    <option value="Emirates">Emirates</option>
                                                                    <option value="Qatar Airways">Qatar Airways</option>
                                                                    <option value="Iran Air">Iran Air</option>
                                                                    <option value="Flydubai">Flydubai</option>
                                                                    <option value="Air India">Air India</option>
                                                                    <option value="PIA">PIA</option>
                                                                    <option value="Uzbekistan Airways">Uzbekistan Airways</option>
                                                                    <option value="Air Arabia">Air Arabia</option>
                                                                    <option value="Saudia">Saudia (Saudi Arabian Airlines)</option>
                                                                    <option value="Etihad Airways">Etihad Airways</option>
                                                                    <option value="Korean Air">Korean Air</option>
                                                                    <option value="China Southern Airlines">China Southern Airlines</option>
                                                                    <option value="China Eastern Airlines">China Eastern Airlines</option>
                                                                    <option value="Lufthansa">Lufthansa</option>
                                                                    <option value="British Airways">British Airways</option>
                                                                    <option value="Singapore Airlines">Singapore Airlines</option>
                                                                    <option value="Thai Airways">Thai Airways</option>
                                                                    <option value="Air France">Air France</option>
                                                                    <option value="KLM Royal Dutch Airlines">KLM Royal Dutch Airlines</option>
                                                                    <option value="American Airlines">American Airlines</option>
                                                                    <option value="United Airlines">United Airlines</option>
                                                                    <option value="Delta Air Lines">Delta Air Lines</option>
                                                                    <option value="Aeroflot">Aeroflot</option>
                                                                    <option value="Japan Airlines">Japan Airlines (JAL)</option>
                                                                    <option value="All Nippon Airways">All Nippon Airways (ANA)</option>
                                                                    <option value="Malaysia Airlines">Malaysia Airlines</option>
                                                                    <option value="IndiGo">IndiGo</option>
                                                                    <option value="SpiceJet">SpiceJet</option>
                                                                    <option value="SriLankan Airlines">SriLankan Airlines</option>
                                                                    <option value="Oman Air">Oman Air</option>
                                                                    <option value="Kuwait Airways">Kuwait Airways</option>
                                                                    <option value="EgyptAir">EgyptAir</option>
                                                                    <option value="Royal Jordanian">Royal Jordanian</option>
                                                                    <option value="Thai Vietjet Air">Thai Vietjet Air</option>
                                                                    <option value="Scoot">Scoot</option>
                                                                    <option value="Norwegian Air Shuttle">Norwegian Air Shuttle</option>
                                                                    <option value="Alitalia">Alitalia</option>
                                                                    <option value="Iberia">Iberia</option>
                                                                    <option value="Austrian Airlines">Austrian Airlines</option>
                                                                    <option value="SWISS International Air Lines">SWISS International Air Lines</option>
                                                                    <option value="Finnair">Finnair</option>
                                                                    <option value="TAP Air Portugal">TAP Air Portugal</option>
                                                                    <option value="Virgin Atlantic">Virgin Atlantic</option>
                                                                    <option value="Cathay Pacific">Cathay Pacific</option>
                                                                    <option value="Hainan Airlines">Hainan Airlines</option>
                                                                    <option value="Garuda Indonesia">Garuda Indonesia</option>
                                                                    <option value="Qantas Airways">Qantas Airways</option>
                                                                    <option value="Aerolineas Argentinas">Aerolineas Argentinas</option>
                                                                    <option value="GOL Linhas Aéreas">GOL Linhas Aéreas</option>
                                                                    <option value="LATAM Airlines">LATAM Airlines</option>
                                                                    <option value="Air Canada">Air Canada</option>
                                                                    <option value="WestJet">WestJet</option>
                                                                    <option value="Alaska Airlines">Alaska Airlines</option>
                                                                    <option value="Spirit Airlines">Spirit Airlines</option>
                                                                    <option value="Frontier Airlines">Frontier Airlines</option>
                                                                    <option value="JetBlue Airways">JetBlue Airways</option>
                                                                    <option value="AA">American Airlines (AA)</option>
                                                                <option value="AC">Air Canada (AC)</option>
                                                                <option value="AF">Air France (AF)</option>
                                                                <option value="AI">Air India (AI)</option>
                                                                <option value="AK">AirAsia (AK)</option>
                                                                <option value="AM">Aeroméxico (AM)</option>
                                                                <option value="AR">Aerolineas Argentinas (AR)</option>
                                                                <option value="AS">Alaska Airlines (AS)</option>
                                                                <option value="AY">Finnair (AY)</option>
                                                                <option value="AZ">ITA Airways (AZ)</option>
                                                                <option value="BA">British Airways (BA)</option>
                                                                <option value="BI">Royal Brunei Airlines (BI)</option>
                                                                <option value="BR">EVA Air (BR)</option>
                                                                <option value="CA">Air China (CA)</option>
                                                                <option value="CI">China Airlines (CI)</option>
                                                                <option value="CX">Cathay Pacific (CX)</option>
                                                                <option value="CZ">China Southern Airlines (CZ)</option>
                                                                <option value="DL">Delta Air Lines (DL)</option>
                                                                <option value="EK">Emirates (EK)</option>
                                                                <option value="ET">Ethiopian Airlines (ET)</option>
                                                                <option value="EY">Etihad Airways (EY)</option>
                                                                <option value="FI">Icelandair (FI)</option>
                                                                <option value="FJ">Fiji Airways (FJ)</option>
                                                                <option value="GA">Garuda Indonesia (GA)</option>
                                                                <option value="GF">Gulf Air (GF)</option>
                                                                <option value="HA">Hawaiian Airlines (HA)</option>
                                                                <option value="HU">Hainan Airlines (HU)</option>
                                                                <option value="IB">Iberia (IB)</option>
                                                                <option value="JL">Japan Airlines (JL)</option>
                                                                <option value="JJ">LATAM Airlines Brasil (JJ)</option>
                                                                <option value="KE">Korean Air (KE)</option>
                                                                <option value="KL">KLM Royal Dutch Airlines (KL)</option>
                                                                <option value="KM">Air Malta (KM)</option>
                                                                <option value="KQ">Kenya Airways (KQ)</option>
                                                                <option value="KU">Kuwait Airways (KU)</option>
                                                                <option value="LA">LATAM Airlines (LA)</option>
                                                                <option value="LH">Lufthansa (LH)</option>
                                                                <option value="LO">LOT Polish Airlines (LO)</option>
                                                                <option value="LX">SWISS International Air Lines (LX)</option>
                                                                <option value="LY">EL AL Israel Airlines (LY)</option>
                                                                <option value="MH">Malaysia Airlines (MH)</option>
                                                                <option value="MS">EgyptAir (MS)</option>
                                                                <option value="MU">China Eastern Airlines (MU)</option>
                                                                <option value="NH">All Nippon Airways (NH)</option>
                                                                <option value="NZ">Air New Zealand (NZ)</option>
                                                                <option value="OA">Olympic Air (OA)</option>
                                                                <option value="OK">Czech Airlines (OK)</option>
                                                                <option value="OS">Austrian Airlines (OS)</option>
                                                                <option value="OU">Croatia Airlines (OU)</option>
                                                                <option value="OZ">Asiana Airlines (OZ)</option>
                                                                <option value="PD">Porter Airlines (PD)</option>
                                                                <option value="PG">Bangkok Airways (PG)</option>
                                                                <option value="PR">Philippine Airlines (PR)</option>
                                                                <option value="QR">Qatar Airways (QR)</option>
                                                                <option value="QF">Qantas Airways (QF)</option>
                                                                <option value="RJ">Royal Jordanian (RJ)</option>
                                                                <option value="RO">TAROM (RO)</option>
                                                                <option value="SA">South African Airways (SA)</option>
                                                                <option value="SK">Scandinavian Airlines (SK)</option>
                                                                <option value="SN">Brussels Airlines (SN)</option>
                                                                <option value="SQ">Singapore Airlines (SQ)</option>
                                                                <option value="SU">Aeroflot (SU)</option>
                                                                <option value="SV">Saudia (SV)</option>
                                                                <option value="TA">TACA Airlines (TA)</option>
                                                                <option value="TG">Thai Airways (TG)</option>
                                                                <option value="TK">Turkish Airlines (TK)</option>
                                                                <option value="TP">TAP Air Portugal (TP)</option>
                                                                <option value="TU">Tunisair (TU)</option>
                                                                <option value="UA">United Airlines (UA)</option>
                                                                <option value="UL">SriLankan Airlines (UL)</option>
                                                                <option value="UX">Air Europa (UX)</option>
                                                                <option value="VA">Virgin Australia (VA)</option>
                                                                <option value="VN">Vietnam Airlines (VN)</option>
                                                                <option value="VS">Virgin Atlantic (VS)</option>
                                                                <option value="VT">Air Tahiti (VT)</option>
                                                                <option value="VY">Vueling Airlines (VY)</option>
                                                                <option value="WF">Widerøe (WF)</option>
                                                                <option value="WN">Southwest Airlines (WN)</option>
                                                                <option value="WS">WestJet (WS)</option>
                                                                <option value="WY">Oman Air (WY)</option>
                                                                <option value="X3">TUI fly Deutschland (X3)</option>
                                                                <option value="XQ">SunExpress (XQ)</option>
                                                                <option value="XY">flynas (XY)</option>
                                                                <option value="YV">Mesa Airlines (YV)</option>
                                                                <option value="ZB">Monarch Airlines (ZB)</option>
                                                                <option value="ZK">Great Lakes Airlines (ZK)</option>
                                                                <option value="ZL">Regional Express (ZL)</option>
                                                                <option value="ZP">Paranair (ZP)</option>
                                                                <option value="ZR">Aviacon Zitotrans (ZR)</option>
                                                                <option value="ZS">Sama Airlines (ZS)</option>
                                                                <option value="ZT">Titan Airways (ZT)</option>
                                                                <option value="ZU">Heli Air Monaco (ZU)</option>
                                                                <option value="ZV">Air Midwest (ZV)</option>
                                                                <option value="ZW">Air Wisconsin (ZW)</option>
                                                                <option value="ZX">Air Georgian (ZX)</option>
                                                                <option value="ZY">Sky Airlines (ZY)</option>
                                                                <option value="3K">Jetstar Asia Airways (3K)</option>
                                                                <option value="4O">Interjet (4O)</option>
                                                                <option value="5J">Cebu Pacific (5J)</option>
                                                                <option value="5Y">Atlas Air (5Y)</option>
                                                                <option value="6E">IndiGo (6E)</option>
                                                                <option value="7C">Jeju Air (7C)</option>
                                                                <option value="7F">First Air (7F)</option>
                                                                <option value="8U">Afriqiyah Airways (8U)</option>
                                                                <option value="9C">Spring Airlines (9C)</option>
                                                                <option value="9W">Jet Airways (9W)</option>
                                                                <option value="A3">Aegean Airlines (A3)</option>
                                                                <option value="A9">Georgian Airways (A9)</option>
                                                                <option value="AD">Azul Brazilian Airlines (AD)</option>
                                                                <option value="AK">AirAsia (AK)</option>
                                                                <option value="AV">Avianca (AV)</option>
                                                                <option value="AZ">ITA Airways (AZ)</option>
                                                                <option value="BE">Flybe (BE)</option>
                                                                <option value="BT">airBaltic (BT)</option>
                                                                <option value="BX">Air Busan (BX)</option>
                                                                <option value="CM">Copa Airlines (CM)</option>
                                                                <option value="DN">Norwegian Air Argentina (DN)</option>
                                                                <option value="DP">Pobeda Airlines (DP)</option>
                                                                <option value="DY">Norwegian Air Shuttle (DY)</option>
                                                                <option value="E9">Evelop Airlines (E9)</option>
                                                                <option value="EI">Aer Lingus (EI)</option>
                                                                <option value="EN">Air Dolomiti (EN)</option>
                                                                <option value="EW">Eurowings (EW)</option>
                                                                <option value="F9">Frontier Airlines (F9)</option>
                                                                <option value="G3">GOL Linhas Aéreas (G3)</option>
                                                                <option value="GF">Gulf Air (GF)</option>
                                                                <option value="G9">Air Arabia (G9)</option>
                                                                <option value="H1">Hahn Air (H1)</option>
                                                                <option value="HY">Uzbekistan Airways (HY)</option>
                                                                <option value="IR">Iran Air (IR)</option>
                                                                <option value="IZ">Arkia Israeli Airlines (IZ)</option>
                                                                <option value="J2">Azerbaijan Airlines (J2)</option>
                                                                <option value="JD">Beijing Capital Airlines (JD)</option>
                                                                <option value="JQ">Jetstar Airways (JQ)</option>
                                                                <option value="KY">Kunming Airlines (KY)</option>
                                                                <option value="LG">Luxair (LG)</option>
                                                                <option value="ME">Middle East Airlines (ME)</option>
                                                                <option value="MF">XiamenAir (MF)</option>
                                                                <option value="MM">Peach Aviation (MM)</option>
                                                                <option value="MN">Kulula (MN)</option>
                                                                <option value="NG">Lauda (NG)</option>
                                                                <option value="NK">Spirit Airlines (NK)</option>
                                                                <option value="NT">Binter Canarias (NT)</option>
                                                                <option value="OD">Batik Air Malaysia (OD)</option>
                                                                <option value="OG">Play (OG)</option>
                                                                <option value="PC">Pegasus Airlines (PC)</option>
                                                                <option value="PG">Bangkok Airways (PG)</option>
                                                                <option value="PN">West Air China (PN)</option>
                                                                <option value="PS">Ukraine International Airlines (PS)</option>
                                                                <option value="PZ">LATAM Paraguay (PZ)</option>
                                                                <option value="RA">Nepal Airlines (RA)</option>
                                                                <option value="RD">Ryan Air Dominicana (RD)</option>
                                                                <option value="RE">Stobart Air (RE)</option>
                                                                <option value="S7">S7 Airlines (S7)</option>
                                                                <option value="SG">SpiceJet (SG)</option>
                                                                <option value="SL">Thai Lion Air (SL)</option>
                                                                <option value="SM">Air Cairo (SM)</option>
                                                                <option value="TB">TUI fly Belgium (TB)</option>
                                                                <option value="TI">Tropic Air (TI)</option>
                                                                <option value="TO">Transavia France (TO)</option>
                                                                <option value="TR">Scoot (TR)</option>
                                                                <option value="TS">Air Transat (TS)</option>
                                                                <option value="TT">Tigerair Australia (TT)</option>
                                                                <option value="TZ">ZIPAIR Tokyo (TZ)</option>
                                                                <option value="U2">easyJet (U2)</option>
                                                                <option value="U6">Ural Airlines (U6)</option>
                                                                <option value="UT">UTair (UT)</option>
                                                                <option value="UX">Air Europa (UX)</option>
                                                                <option value="V7">Volotea (V7)</option>
                                                                <option value="VB">Viva Aerobus (VB)</option>
                                                                <option value="VE">EasyFly (VE)</option>
                                                                <option value="VG">VLM Airlines (VG)</option>
                                                                <option value="VO">Voepass Linhas Aéreas (VO)</option>
                                                                <option value="VQ">Novoair (VQ)</option>
                                                                <option value="VV">Viva Air Colombia (VV)</option>
                                                                <option value="W6">Wizz Air (W6)</option>
                                                                <option value="WF">Widerøe (WF)</option>
                                                                <option value="WN">Southwest Airlines (WN)</option>
                                                                <option value="Y4">Volaris (Y4)</option>
                                                                <option value="Z2">Philippines AirAsia (Z2)</option>
                                                                <option value="ZF">Azur Air (ZF)</option>
                                                                <option value="ZL">Regional Express (ZL)</option>
                                                                <option value="ZP">Paranair (ZP)</option>
                                                                <option value="ZR">Aviacon Zitotrans (ZR)</option>
                                                                <option value="ZS">Sama Airlines (ZS)</option>
                                                                <option value="ZT">Titan Airways (ZT)</option>
                                                                <option value="ZU">Heli Air Monaco (ZU)</option>
                                                                <option value="ZV">Air Midwest (ZV)</option>
                                                                
                                                                </select>
                                                        </div>
                                                        <div id="editReturnJourneyFields" style="display: none;">
                                                            <div class="form-group col-md-8">
                                                                <label for="editReturnDestination">Return To</label>
                                                                <input type="text" class="form-control" id="editReturnDestination" name="returnDestination">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editIssueDate">Issue Date</label>
                                                            <input type="date" class="form-control" id="editIssueDate" name="issueDate" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editDepartureDate">Departure Date</label>
                                                            <input type="date" class="form-control" id="editDepartureDate" name="departureDate" required>
                                                        </div>
                                                        <div id="editReturnDateField" class="form-group col-md-3" style="display: none;">
                                                            <label for="editReturnDate">Return Date</label>
                                                            <input type="date" class="form-control" id="editReturnDate" name="returnDate">
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editBase">Base</label>
                                                            <input type="number" class="form-control" id="editBase" name="base" step="any" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-3">
                                                            <label for="editSold">Sold</label>
                                                            <input type="number" class="form-control" id="editSold" name="sold" step="any" required>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPro">Profit</label>
                                                            <input type="number" class="form-control" id="editPro" name="pro" step="any" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editCurr">Currency</label>
                                                            <input class="form-control" id="editCurr" name="curr" required readonly>
                                                        </div>
                                                        <div class="form-group col-md-3">
                                                            <label for="editPaidTo">Paid To</label>
                                                            <select class="form-control" id="editPaidTo" name="paidTo" required readonly>
                                                                <option value="">Select Main Account</option>
                                                                <?php 
                                                                if ($conn->connect_error) {
                                                                    echo "<option value=''>Database connection failed</option>";
                                                                } else {
                                                                    $result = $conn->query("SELECT id, name, usd_balance, afs_balance FROM main_account where status = 'active'");
                                                                    while ($row = $result->fetch_assoc()) {
                                                                        echo "<option value='{$row['id']}'>
                                                                                {$row['name']} (USD: {$row['usd_balance']}, AFS: {$row['afs_balance']})
                                                                              </option>";
                                                                    }
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        <div class="form-group col-md-12">
                                                            <label for="editDescription">Description</label>
                                                            <input type="text" class="form-control" id="editDescription" name="description" required>
                                                        </div>
                                                    </div>

                                                    <div class="form-row">
                                                        
                                                        <div class="form-group col-md-3">
                                                            <label for="editExchangeRate">Exchange Rate</label>
                                                            <input type="number" class="form-control" id="editExchangeRate" name="exchangeRate" step="any">
                                                        </div>
                                                        
                                                        
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>





                                  <!-- Required Js -->
                                    <script src="../assets/js/vendor-all.min.js"></script>
                                    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
                                    <script src="../assets/js/pcoded.min.js"></script>

                                    <!-- Add script for multiple ticket invoice functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Select2 for client dropdown
        if (typeof $.fn.select2 !== 'undefined') {
            $('#clientForInvoice1').select2({
                dropdownParent: $('#multiTicketInvoiceModal'),
                placeholder: "Search and select client...",
                allowClear: true
            });
        }
        
        // Launch multi-ticket invoice modal
        document.getElementById('launchMultiTicketInvoice').addEventListener('click', function() {
            loadTicketsForInvoice();
            $('#multiTicketInvoiceModal').modal('show');
        });
        
        // Handle "Select All" checkbox
        document.getElementById('selectAllTickets').addEventListener('change', function() {
            const isChecked = this.checked;
            const checkboxes = document.querySelectorAll('#ticketsForInvoiceBody input[type="checkbox"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            
            updateInvoiceTotal();
        });
        
        // Generate the combined invoice
        document.getElementById('generateCombinedInvoice').addEventListener('click', function() {
            const selectedTickets = getSelectedTickets();
            
            if (selectedTickets.length === 0) {
                alert('Please select at least one ticket for the invoice.');
                return;
            }
            
            const clientId = document.getElementById('clientForInvoice').value;
            if (!clientId) {
                alert('Please select a client for the invoice.');
                return;
            }
            
            const invoiceData = {
            
                comment: document.getElementById('invoiceComment').value,
                currency: document.getElementById('invoiceCurrency').value,
                clientName: document.getElementById('clientForInvoice').value,
                tickets: selectedTickets
            };
            
            // Send the data to a new page to generate the invoice
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generate_multi_ticket_reserve_invoice.php';
            form.target = '_blank';
            
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = 'invoiceData';
            hiddenField.value = JSON.stringify(invoiceData);
            
            form.appendChild(hiddenField);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            // Close the modal after generating the invoice
            $('#multiTicketInvoiceModal').modal('hide');
        });
        
        // Load tickets for the invoice table
        function loadTicketsForInvoice() {
            fetch('fetch_tickets_reserve_for_invoice.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        populateTicketTable(data.tickets);
                    } else {
                        console.error('Error loading tickets:', data.message);
                        alert('Failed to load tickets. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while loading tickets.');
                });
        }
        
        // Populate the ticket selection table
        function populateTicketTable(tickets) {
            const tableBody = document.getElementById('ticketsForInvoiceBody');
            tableBody.innerHTML = '';
            
            tickets.forEach((ticket, index) => {
                // Format the sector information
                let sector = `${ticket.origin} to ${ticket.destination}`;
                if (ticket.trip_type === 'round_trip' && ticket.return_destination) {
                    sector += `<br><small>Return: ${ticket.return_destination}</small>`;
                }
                
                // Format the flight information
                let flight = ticket.airline;
                
                // Format the date information
                let date = formatDate(ticket.departure_date);
                if (ticket.trip_type === 'round_trip' && ticket.return_date) {
                    date += `<br><small>Return: ${formatDate(ticket.return_date)}</small>`;
                }
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input ticket-checkbox" 
                                id="ticket-${ticket.id}" 
                                data-ticket-id="${ticket.id}"
                                data-amount="${ticket.sold}"
                                onchange="updateInvoiceTotal()">
                            <label class="custom-control-label" for="ticket-${ticket.id}"></label>
                        </div>
                    </td>
                    <td>${ticket.passenger_name}</td>
                    <td>${ticket.pnr}</td>
                    <td>${sector}</td>
                    <td>${flight}</td>
                    <td>${date}</td>
                    <td class="text-right">${parseFloat(ticket.sold).toFixed(2)}</td>
                `;
                
                tableBody.appendChild(row);
            });
            
            // Clear the total
            updateInvoiceTotal();
        }
        
        // Format date for display
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        // Get all selected tickets
        function getSelectedTickets() {
            const checkboxes = document.querySelectorAll('#ticketsForInvoiceBody input[type="checkbox"]:checked');
            const tickets = [];
            
            checkboxes.forEach(checkbox => {
                tickets.push(checkbox.dataset.ticketId);
            });
            
            return tickets;
        }
        
        // Update the invoice total
        window.updateInvoiceTotal = function() {
            const checkboxes = document.querySelectorAll('#ticketsForInvoiceBody input[type="checkbox"]:checked');
            let total = 0;
            
            checkboxes.forEach(checkbox => {
                total += parseFloat(checkbox.dataset.amount) || 0;
            });
            
            document.getElementById('invoiceTotal').textContent = total.toFixed(2);
        }
    });
</script>
                                     <!-- base, sold and profit calculation -->
                                      <script>
                                           document.addEventListener('DOMContentLoaded', () => {
                                            const baseInput = document.getElementById('base');
                                           const soldInput = document.getElementById('sold');
                                                          const proInput = document.getElementById('pro');

                                                                            // Function to calculate and update the profit field
                                                                            function calculatePro() {
                                                                                const base = parseFloat(baseInput.value) || 0; // Default to 0 if not valid
                                                                                const sold = parseFloat(soldInput.value) || 0; // Default to 0 if not valid
                                                                                const pro = sold - base; // Calculate profit

                                                                                console.log("Base: ", base);
                                                                                console.log("Sold: ", sold);
                                                                                console.log('Profit Calculated:', pro);

                                                                                // Update the profit field and make sure it's also visible
                                                                                proInput.value = pro.toFixed(2);  // Update to two decimal points
                                                                                console.log('Updated Profit Input Value: ', proInput.value); // Check updated value
                                                                            }

                                                                            // Add event listeners for real-time calculation
                                                                            baseInput.addEventListener('input', calculatePro);
                                                                            soldInput.addEventListener('input', calculatePro);
                                       });

                                          document.addEventListener('DOMContentLoaded', () => {
                                                                            const editBaseInput = document.getElementById('editBase');
                                                                            const editSoldInput = document.getElementById('editSold');
                                                                            const editProInput = document.getElementById('editPro');

                                                                            // Function to calculate and update the profit field
                                                                            function calculateEditPro() {
                                                                                const editBase = parseFloat(editBaseInput.value) || 0; // Default to 0 if not valid
                                                                                const editSold = parseFloat(editSoldInput.value) || 0; // Default to 0 if not valid
                                                                                const editPro = editSold - editBase; // Calculate profit

                                                                                console.log("editBase: ", editBase);
                                                                                console.log("editSold: ", editSold);
                                                                                console.log('Profit Calculated:', editPro);

                                                                                // Update the profit field and make sure it's also visible
                                                                                editProInput.value = editPro.toFixed(2);  // Update to two decimal points
                                                                                console.log('Updated Profit Input Value: ', editProInput.value); // Check updated value
                                                                            }

                                                                            // Add event listeners for real-time calculation
                                                                            editBaseInput.addEventListener('input', calculateEditPro);
                                                                            editSoldInput.addEventListener('input', calculateEditPro);
                                      });


                                      </script>

                                    <!-- view ticket details -->
                                <script>
                                  // Function to populate and display modal details
                                    $(document).on('click', '.view-details', function() {
                                        var ticketData = $(this).data('ticket');

                                        console.log(ticketData);  // Log ticket data for debugging
                                         if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
                                            alert('Ticket data or ID is missing!');
                                            return;
                                        }

                                        // Attach ticket data to the modal
                                        $('#detailsModal').data('ticket', ticketData); // Attach full ticket data
                                        $('#detailsModal').data('ticket-id', ticketData.ticket.id); // Attach ticket ID


                                        if (ticketData) {
                                            // Supplier, sold to, and paid to names should now be populated correctly
                                            $('#passenger-name').text(ticketData.ticket.passenger_name || 'N/A');
                                            $('#pnr').text(ticketData.ticket.pnr || 'N/A');
                                            $('#sold-to').text(ticketData.ticket.sold_to || 'N/A');
                                            $('#paid-to').text(ticketData.ticket.paid_to || 'N/A');
                                            
                                            // Populate other fields...
                                            $('#sold-price').text(ticketData.ticket.sold || 'N/A');
                                            $('#base-price').text(ticketData.ticket.price || 'N/A');
                                            $('#profit').text(ticketData.ticket.profit || 'N/A');
                                            $('#paymentAmount').text(ticketData.ticket.paymentAmount || 'N/A');
                                            $('#currency').text(ticketData.ticket.currency || 'N/A');
                                            $('#payment-currency').text(ticketData.ticket.paymentCurrency || 'N/A');
                                            $('#exchangeRate').text(ticketData.ticket.exchangeRate || 'N/A');
                                            $('#phone').text(ticketData.ticket.phone || 'N/A');
                                            $('#gender').text(ticketData.ticket.gender || 'N/A');
                                            $('#description').text(ticketData.ticket.description || 'N/A');
                                            
                                            // Handle refund data...
                                            if (ticketData.refund_data) {
                                                $('#refund-supplier-penalty').text(ticketData.refund_data.supplier_penalty || 'N/A');
                                                $('#refund-service-penalty').text(ticketData.refund_data.service_penalty || 'N/A');
                                                $('#refund-to-passenger').text(ticketData.refund_data.refund_to_passenger || 'N/A');
                                                $('#refund-status').text(ticketData.refund_data.status || 'N/A');
                                                $('#refund-remarks').text(ticketData.refund_data.remarks || 'N/A');
                                            }

                                            // Handle date change data...
                                            if (ticketData.date_change_data) {
                                                $('#date-change-departure-date').text(ticketData.date_change_data.departure_date || 'N/A');
                                                $('#date-change-currency').text(ticketData.date_change_data.currency || 'N/A');
                                                $('#date-change-supplier-penalty').text(ticketData.date_change_data.supplier_penalty || 'N/A');
                                                $('#date-change-service-penalty').text(ticketData.date_change_data.service_penalty || 'N/A');
                                                $('#date-change-status').text(ticketData.date_change_data.status || 'N/A');
                                                $('#date-change-remarks').text(ticketData.date_change_data.remarks || 'N/A');
                                            }

                                            $('#detailsModal').modal('show');  // Show the modal with details
                                        } else {
                                            alert('Ticket data not available!');
                                        }
                                    });
                                    // Date change function
                                $(document).ready(function () {
                                    // Open Date Change Modal
                                    $('#dateChangeBtn').click(function () {
                                       const ticketData = $('#detailsModal').data('ticket'); // Get ticket data
                                        if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
                                            alert('Ticket data or ID is missing!');
                                            return;
                                        }

                                        const ticketId = ticketData.ticket.id; // Extract the ticket ID

                                        // Pass the ticketId dynamically to the Date Change modal fields
                                        $('#dateChangeTicketId').val(ticketId);  // Set ticketId in the hidden field for the date change form

                                        // Populate fields (fetch dynamically or mock data)
                                        $('#dateChangeSold').val($('#sold-price').text());
                                        $('#dateChangeBase').val($('#base-price').text());
                                        $('#dateChangeDescription').val($('#description').text());
                                        $('#dateChangeDepartureDate').val('');  // Empty the departure date for the user to enter

                                        $('#dateChangeModal').modal('show');
                                    });

                                    // Open Refund Modal
                                    $('#refundBtn').click(function () {
                                        const ticketData = $('#detailsModal').data('ticket'); // Get ticket data
                                        if (!ticketData || !ticketData.ticket || !ticketData.ticket.id) {
                                            alert('Ticket data or ID is missing!');
                                            return;
                                        }

                                        const ticketId = ticketData.ticket.id; // Extract the ticket ID

                                        $('#refundTicketId').val(ticketId); // Set the hidden field for the refund form

                                        // Fetch client type and handle the refund modal
                                        $.ajax({
                                            type: 'POST',
                                            url: 'getClientType.php',
                                            data: { ticketId: ticketId }, // Send only the ticket ID
                                            success: function (response) {
                                                const data = JSON.parse(response);

                                                if (data.status === 'success') {
                                                    const clientType = data.client_type; // Client type: agency or regular
                                                    const basePrice = parseFloat($('#base-price').text()); // Base price
                                                    const soldPrice = parseFloat($('#sold-price').text()); // Sold price
                                                    
                                                    // Dynamically retrieve and display initial penalties
                                                    let supplierPenalty = parseFloat($('#supplierRefundPenalty').val()) || 0; 
                                                    let servicePenalty = parseFloat($('#serviceRefundPenalty').val()) || 0;

                                                    console.log(`Initial Values: Client Type = ${clientType}`);
                                                    console.log(`Sold Price = ${soldPrice}, Base Price = ${basePrice}`);
                                                    console.log(`Supplier Penalty = ${supplierPenalty}, Service Penalty = ${servicePenalty}`);

                                                    let refundAmount = 0;

                                                    if (clientType === 'agency') {
                                                        // Use Base Price for Agencies
                                                        refundAmount = basePrice - supplierPenalty - servicePenalty;
                                                    } else if (clientType === 'regular') {
                                                        // Use Sold Price for Regular Clients
                                                        refundAmount = soldPrice - supplierPenalty - servicePenalty;
                                                    }

                                                    console.log(`Initial Refund Amount = ${refundAmount.toFixed(2)}`);

                                                    // Populate initial modal fields
                                                    $('#refundBase').val(basePrice.toFixed(2));
                                                    $('#refundSold').val(soldPrice.toFixed(2));
                                                    $('#refundAmount').val(refundAmount.toFixed(2));

                                                    // On change of penalties, update the refund calculation
                                                    $('#supplierRefundPenalty, #serviceRefundPenalty').on('input', function () {
                                                        supplierPenalty = parseFloat($('#supplierRefundPenalty').val()) || 0;
                                                        servicePenalty = parseFloat($('#serviceRefundPenalty').val()) || 0;

                                                        if (clientType === 'agency') {
                                                            refundAmount = basePrice - supplierPenalty - servicePenalty;
                                                        } else if (clientType === 'regular') {
                                                            refundAmount = soldPrice - supplierPenalty - servicePenalty;
                                                        }

                                                        // Ensure refundAmount is non-negative
                                                        if (refundAmount < 0) refundAmount = 0;

                                                        console.log(`Updated Values: Supplier Penalty = ${supplierPenalty}, Service Penalty = ${servicePenalty}`);
                                                        console.log(`Updated Refund Amount = ${refundAmount.toFixed(2)}`);

                                                        $('#refundAmount').val(refundAmount.toFixed(2));
                                                    });

                                                    // Show the modal
                                                    $('#refundModal').modal('show');
                                                } else {
                                                    alert('Error: ' + data.message); // If there was an error fetching client type
                                                }
                                            },
                                            error: function () {
                                                alert('Error fetching client type.'); // AJAX error
                                            }
                                        });
                                    });
                                    // Submit Date Change Form
                                    $('#dateChangeForm').submit(function (e) {
                                        e.preventDefault();
                                        const formData = $(this).serialize();

                                        $.ajax({
                                            url: 'insert_ticket_record_dc.php',
                                            method: 'POST',
                                            data: formData,
                                            success: function (response) {
                                                console.log('Server Response:', response); // Log response for debugging
                                                if ($.trim(response) === 'success') { // Trim whitespace
                                                    alert('Date Change recorded successfully!');
                                                    $('#dateChangeModal').modal('hide');
                                                } else {
                                                    alert('Error recording Date Change: ' + response);
                                                }
                                            },
                                            error: function () {
                                                alert('An error occurred.');
                                            },
                                        });
                                    });
                                    // Submit Refund Form
                                    $('#refundForm').submit(function (e) {
                                        e.preventDefault();
                                        const formData = $(this).serialize();

                                        $.ajax({
                                            url: 'insert_ticket_record.php',
                                            method: 'POST',
                                            data: formData,
                                            success: function (response) {
                                                 console.log('Server Response:', response); // Log response for debugging
                                                if ($.trim(response) === 'success') {
                                                    alert('Refund recorded successfully!');
                                                    $('#refundModal').modal('hide');
                                                } else {
                                                    alert('Error recording Refund.');
                                                }
                                            },
                                            error: function () {
                                                alert('An error occurred.');
                                            },
                                        });
                                    });
                                });

                            </script>
                                <script>
                                   document.getElementById('bookTicketForm').addEventListener('submit', function (event) {
                                    event.preventDefault(); // Prevent default form submission
                                    const formData = new FormData(this); // Collect form data

                                    fetch('save_ticket_reserve.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json()) // Parse JSON response
                                    .then(data => {
                                        if (data.status === 'success') { // Check for status
                                            alert(data.message); // Show success message
                                            location.reload(); // Reload page
                                        } else {
                                            alert('Error: ' + data.message); // Display specific error message
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error); // Log error
                                        alert('An unexpected error occurred.');
                                    });
                                });

                                </script>

                               
                            <!-- Fetch supplier curency -->
                            <script>
                                document.getElementById('supplier').addEventListener('change', function () {
                                    const supplierId = this.value;

                                    console.log('Selected Supplier ID:', supplierId);

                                    if (supplierId) {
                                        fetch(`get_supplier_currency.php?supplier_id=${supplierId}`)
                                            .then(response => {
                                                console.log('Response status:', response.status); // Log status
                                                return response.json();
                                            })
                                            .then(data => {
                                                console.log('Response data:', data); // Log full response
                                                const currInput = document.getElementById('curr');
                                                if (data.currency) {
                                                    currInput.value = data.currency;

                                                    console.log('Currency input updated to:', data.currency);
                                                } else {
                                                    currInput.value = '';
                                                    console.warn('No currency found in response!');
                                                }
                                            })
                                            .catch(error => {
                                                console.error('Error fetching supplier currency:', error);
                                            });
                                    } else {
                                        console.log('No supplier selected, clearing input.');
                                        document.getElementById('curr').value = '';
                                    }
                                });


                                </script>

                                <script>
                                        function deleteTicket(id) {
                                            if (confirm('Are you sure you want to delete this Ticket?')) {
                                                fetch('delete_ticket_reserve.php', {
                                                    method: 'POST',
                                                    headers: { 'Content-Type': 'application/json' },
                                                    body: JSON.stringify({ id }),
                                                })
                                                .then(response => response.json())
                                                .then(data => {
                                                    if (data.success) {
                                                        alert('Ticket deleted successfully!');
                                                        location.reload();
                                                    } else {
                                                        alert('Error: ' + data.message);
                                                    }
                                                })
                                                .catch(error => console.error('Error deleting Ticket:', error));
                                            }
                                        }
                                        </script>

  
                                    </div>
                                </div>
                                    <!-- [ refund calculation ]-->
                        <script>
                                    // Refund calculation logic
                                    const supplierRefundPenaltyElement = document.getElementById('supplierRefundPenalty');
                                    const serviceRefundPenaltyElement = document.getElementById('serviceRefundPenalty');
                                    
                                    if (supplierRefundPenaltyElement) {
                                        supplierRefundPenaltyElement.addEventListener('input', updateRefundAmount);
                                    }
                                    
                                    if (serviceRefundPenaltyElement) {
                                        serviceRefundPenaltyElement.addEventListener('input', updateRefundAmount);
                                    }
                                    
                                    const refundBaseElement = document.getElementById('refundBase');
                                    if (refundBaseElement) {
                                        refundBaseElement.addEventListener('input', updateRefundAmount);
                                    }
                                    
                                    function updateRefundAmount() {
                                        const refundBaseEl = document.getElementById('refundBase');
                                        const supplierPenaltyEl = document.getElementById('supplierRefundPenalty');
                                        const servicePenaltyEl = document.getElementById('serviceRefundPenalty');
                                        const refundAmountEl = document.getElementById('refundAmount');
                                        
                                        if (!refundBaseEl || !supplierPenaltyEl || !servicePenaltyEl || !refundAmountEl) {
                                            console.error('Missing required elements for refund calculation');
                                            return;
                                        }
                                        
                                        const base = parseFloat(refundBaseEl.value) || 0;
                                        const supplierPenalty = parseFloat(supplierPenaltyEl.value) || 0;
                                        const servicePenalty = parseFloat(servicePenaltyEl.value) || 0;
                                        
                                        // Total penalty
                                        const totalPenalty = supplierPenalty + servicePenalty;
                                        
                                        // Refund amount calculation
                                        const refundAmount = base - supplierPenalty - servicePenalty;
                                        
                                        // Show refund amount in readonly input
                                        refundAmountEl.value = refundAmount > 0 ? refundAmount : 0;
                                    }
                                </script>
                                <script>
                                    $(document).on('click', '.generate-invoice', function () {
                                    const ticketId = $(this).data('ticket-id');
                                    if (!ticketId) {
                                        alert('Ticket ID is missing!');
                                        return;
                                    }
                                    window.location.href = `generateInvoice.php?ticketId=${ticketId}`;
                                });

                         </script>

 <script>
        document.getElementById('pnrFilter').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#ticketTable tr');

            rows.forEach(row => {
                let pnr = row.querySelector('.pnr-field').textContent.toLowerCase();
                row.style.display = pnr.includes(filter) ? '' : 'none';
            });
        });
</script>

<!-- Transaction Modal -->
<div class="modal fade" id="transactionsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-credit-card mr-2"></i>Manage Transactions
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                <!-- Hotel Info Card -->
                <div class="card mb-4 border-primary">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted mb-2">Ticket Booking Details</h6>
                                <p class="mb-1"><strong>Name:</strong> <span id="trans-guest-name"></span></p>
                                <p class="mb-1"><strong>PNR:</strong> <span id="trans-order-id"></span></p>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info mb-0">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Original Amount:</span>
                                        <strong id="totalAmount"></strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Exchange Rate:</span>
                                        <strong id="displayExchangeRate">-</strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Converted Amount:</span>
                                        <strong id="convertedAmount">-</strong>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <div id="usdSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Paid Amount (USD):</span>
                                            <strong id="paidAmountUSD" class="text-success">USD 0.00</strong>
                                        </div>
                                    </div>
                                    <div id="afsSection" style="display: none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Paid Amount (AFS):</span>
                                            <strong id="paidAmountAFS" class="text-success">AFS 0.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions Table -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Transaction History</h6>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="collapse" data-target="#addTransactionForm">
                            <i class="feather icon-plus"></i> New Transaction
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Payment</th>
                                        <th>Amount</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="transactionTableBody">
                                    <!-- Transactions will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Add Transaction Form -->
                <div id="addTransactionForm" class="collapse">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0">Add New Transaction</h6>
                        </div>
                        <div class="card-body">
                            <form id="hotelTransactionForm">
                                <input type="hidden" id="booking_id" name="booking_id">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentDate">
                                                <i class="feather icon-calendar mr-1"></i>Payment Date
                                            </label>
                                            <input type="date" class="form-control" id="paymentDate" name="payment_date" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="paymentTime">
                                                <i class="feather icon-clock mr-1"></i>Payment Time
                                            </label>
                                            <input type="time" class="form-control" id="paymentTime" name="payment_time" step="1" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="transactionPaymentAmount">
                                                <i class="feather icon-dollar-sign mr-1"></i>Amount
                                            </label>
                                            <input type="number" class="form-control" id="transactionPaymentAmount" 
                                                   name="payment_amount" step="0.01" min="0.01" required 
                                                   placeholder="Enter amount">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paymentCurrency">
                                            <i class="feather icon-dollar-sign mr-1"></i>Currency
                                        </label>
                                        <select class="form-control" id="paymentCurrency" name="payment_currency" required>
                                            <option value="USD">USD</option>
                                            <option value="AFS">AFS</option>
                                        </select>
                                    </div>
                                </div>
                                </div>

                                <div class="form-group">
                                    <label for="paymentDescription">
                                        <i class="feather icon-file-text mr-1"></i>Description
                                    </label>
                                    <textarea class="form-control" id="paymentDescription" 
                                              name="payment_description" rows="2" required
                                              placeholder="Enter payment description"></textarea>
                                </div>

                                <div class="text-right mt-3">
                                    <button type="button" class="btn btn-secondary" data-toggle="collapse" 
                                            data-target="#addTransactionForm">
                                        <i class="feather icon-x mr-1"></i>Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-check mr-1"></i>Add Transaction
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="feather icon-x mr-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Transaction Management
    const transactionManager = {
    // Initialize transaction modal and form handlers
    init: function() {
        this.bindEvents();
        this.setDefaultDateTime();
    },

    // Bind all event listeners
    bindEvents: function() {
        $('#hotelTransactionForm').on('submit', this.handleTransactionSubmit);
        $('#transaction_to').on('change', this.toggleReceiptField);
    },

    // Set today's date and current time as default
    setDefaultDateTime: function() {
        const now = new Date();
        const today = now.toISOString().split('T')[0];
        $('#paymentDate').val(today);
        
        // Format time as HH:MM:SS
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        $('#paymentTime').val(`${hours}:${minutes}:${seconds}`);
    },

    // Load and display transaction modal
    loadTransactionModal: function(ticketId) {
        if (!ticketId) {
            console.error('No ticket ID provided');
            return;
        }

        $.ajax({
            url: 'get_ticket_reservations.php',
            type: 'GET',
            data: { id: ticketId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const booking = response.booking;
                    
                    // Update booking details
                    $('#trans-guest-name').text(
                        `${booking.title} ${booking.passenger_name}`
                    );
                    $('#trans-order-id').text(booking.pnr);

                    // Display original amount and currency
                    const originalAmount = parseFloat(booking.sold) || 0;
                    const originalCurrency = booking.currency || 'USD';
                    $('#totalAmount').text(`${originalCurrency} ${originalAmount.toFixed(2)}`);
                    
                    // Display exchange rate
                    const exchangeRate = parseFloat(booking.exchange_rate) || 1;
                    $('#displayExchangeRate').text(exchangeRate.toFixed(4));
                    
                    // Calculate and display converted amount
                    const convertedAmount = originalAmount * exchangeRate;
                    const convertedCurrency = booking.payment_currency || originalCurrency;
                     // Determine the display currency based on exchange rate
                     let displayCurrency = booking.currency;
                    if (booking.currency === 'USD' && exchangeRate > 1) {
                        displayCurrency = 'AFS';
                    } else if (booking.currency === 'AFS' && exchangeRate < 1) {
                        displayCurrency = 'USD';
                    }
                    $('#convertedAmount').text(`${displayCurrency} ${convertedAmount.toFixed(2)}`);
                    
                    // Set booking ID in the form
                    $('#booking_id').val(ticketId);
                    
                    // Load transaction history
                    transactionManager.loadTransactionHistory(ticketId);
                    
                    // Show modal
                    $('#transactionsModal').modal('show');
                } else {
                    alert('Error fetching booking details: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error fetching booking details');
            }
        });
    },
    

    // Handle transaction form submission
    handleTransactionSubmit: function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const ticketId = $('#booking_id').val();
        
        if (!ticketId) {
            alert('Ticket ID is missing');
            return;
        }

        // Ensure booking_id is included in formData
        formData.set('ticket_id', ticketId);
        
        // Combine date and time into a single datetime value
        const date = formData.get('payment_date');
        const time = formData.get('payment_time') || '00:00:00';
        if (date) {
            formData.set('payment_date', `${date} ${time}`);
        }
        
        $.ajax({
            url: 'add_ticket_reserve_payment.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        alert('Transaction added successfully');
                        $('#addTransactionForm').collapse('hide');
                        $('#hotelTransactionForm')[0].reset();
                        transactionManager.setDefaultDateTime();
                        transactionManager.loadTransactionHistory(ticketId);
                    } else {
                        alert('Error adding transaction: ' + (result.message || 'Unknown error'));
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    alert('Error processing the request');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('Error adding transaction');
            }
        });
    },

    // Load transaction history
    loadTransactionHistory: function(ticketId) {
        const soldAmount = parseFloat($('#totalAmount').text().split(' ')[1]);
        
    $.ajax({
        url: 'get_ticket_reserve_transactions.php',
        type: 'GET',
        data: { ticket_id: ticketId },
        success: function(response) {
                try {
                    const transactions = typeof response === 'string' ? JSON.parse(response) : response;
                    const tbody = $('#transactionTableBody');
                    tbody.empty();

                    let totalPaidUSD = 0;
                    let totalPaidAFS = 0;
                    let hasUSDTransactions = false;
                    let hasAFSTransactions = false;
                    
                    
                      
                    transactions.forEach(transaction => {
                        const amount = parseFloat(transaction.amount);
                        if (transaction.currency === 'USD') {
                            totalPaidUSD += amount;
                            hasUSDTransactions = true;
                        } else if (transaction.currency === 'AFS') {
                            totalPaidAFS += amount;
                            hasAFSTransactions = true;
                        }
                        const row = `
                            <tr>
                                <td>${transactionManager.formatDate(transaction.created_at)}</td>
                                <td>${transaction.description || 'N/A'}</td>
                                <td>${transaction.type === 'credit' ? 'Received' : 'Paid'}</td>
                                <td>${transaction.currency} ${parseFloat(transaction.amount).toFixed(2)}</td>
                                <td class="text-center">
                                    <button class="btn btn-primary btn-sm mr-1" title="Edit Transaction"
                                            onclick="transactionManager.editTransaction(${transaction.id}, '${transaction.description}', ${transaction.amount}, '${transaction.created_at}')">
                                        <i class="feather icon-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" title="Delete Transaction"
                                            onclick="transactionManager.deleteTransaction(${transaction.id})">
                                        <i class="feather icon-trash-2"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.append(row);
                    });

                    // Get the total amount and its currency
                    const totalAmountText = $('#totalAmount').text();
                    const totalCurrency = totalAmountText.split(' ')[0];
                    const totalAmount = parseFloat(totalAmountText.split(' ')[1]) || 0;

                    // Show/hide currency sections based on transaction existence
                    $('#usdSection').toggle(hasUSDTransactions);
                    $('#afsSection').toggle(hasAFSTransactions);

                    // Update paid amounts if transactions exist
                    if (hasUSDTransactions) {
                        $('#paidAmountUSD').text(`USD ${totalPaidUSD.toFixed(2)}`);
                        if (totalCurrency === 'USD') {
                            const remainingUSD = totalAmount - totalPaidUSD;
                            $('#remainingAmountUSD').text(`USD ${remainingUSD.toFixed(2)}`);
                        }
                    }

                    if (hasAFSTransactions) {
                        $('#paidAmountAFS').text(`AFS ${totalPaidAFS.toFixed(2)}`);
                        if (totalCurrency === 'AFS') {
                            const remainingAFS = totalAmount - totalPaidAFS;
                            $('#remainingAmountAFS').text(`AFS ${remainingAFS.toFixed(2)}`);
                        }
                    }
            } catch (e) {
                console.error('Error parsing transactions:', e);
                    $('#transactionTableBody').html(
                        '<tr><td colspan="5" class="text-center">Error loading transactions</td></tr>'
                    );
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading transactions:', error);
                $('#transactionTableBody').html(
                    '<tr><td colspan="5" class="text-center">Error loading transactions</td></tr>'
                );
            }
        });
    },

    // Update format date function to handle SQL datetime
    formatDate: function(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    },

    // Add edit transaction function
    editTransaction: function(transactionId, description, amount, createdAt) {
        // Parse the datetime string
        const dateTime = new Date(createdAt);
        
        // Format date for input field (YYYY-MM-DD)
        const formattedDate = dateTime.toISOString().split('T')[0];
        
        // Format time for input field (HH:MM:SS)
        const hours = String(dateTime.getHours()).padStart(2, '0');
        const minutes = String(dateTime.getMinutes()).padStart(2, '0');
        const seconds = String(dateTime.getSeconds()).padStart(2, '0');
        const formattedTime = `${hours}:${minutes}:${seconds}`;
        
        // Get the current ticket ID from the booking_id field
        const ticketId = $('#booking_id').val();
        
        console.log('Current ticket ID:', ticketId); // Debug log
        
        // Create edit transaction modal if it doesn't exist
        if (!$('#editTransactionModal').length) {
            const modalHtml = `
                <div class="modal fade" id="editTransactionModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="feather icon-edit mr-2"></i>Edit Transaction
                                </h5>
                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                            </div>
                            <form id="editTransactionForm">
                                <div class="modal-body">
                                    <input type="hidden" id="editTransactionId" name="transaction_id">
                                    <input type="hidden" id="editTicketId" name="ticket_id">
                                    <input type="hidden" id="originalAmount" name="original_amount">
                                    
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="editPaymentDate">
                                                    <i class="feather icon-calendar mr-1"></i>Payment Date
                                                </label>
                                                <input type="date" class="form-control" id="editPaymentDate" name="payment_date" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="editPaymentTime">
                                                    <i class="feather icon-clock mr-1"></i>Payment Time
                                                </label>
                                                <input type="time" class="form-control" id="editPaymentTime" name="payment_time" step="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="editPaymentAmount">
                                                    <i class="feather icon-dollar-sign mr-1"></i>Amount
                                                </label>
                                                <input type="number" class="form-control" id="editPaymentAmount" 
                                                       name="payment_amount" step="0.01" min="0.01" required>
                                                <small class="form-text text-muted">
                                                    Changing this amount will update all subsequent balances.
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="editPaymentDescription">
                                                    <i class="feather icon-file-text mr-1"></i>Description
                                                </label>
                                                <textarea class="form-control" id="editPaymentDescription" 
                                                          name="payment_description" rows="2" required></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                        <i class="feather icon-x mr-1"></i>Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="feather icon-check mr-1"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);
            
            // Add submit handler for the edit form
            $('#editTransactionForm').on('submit', function(e) {
                e.preventDefault();
                
                // Create FormData from the form
                const formData = new FormData(this);
                
                // Explicitly set the ticket ID again to ensure it's included
                const currentTicketId = $('#booking_id').val();
                formData.set('ticket_id', currentTicketId);
                
                // Ensure transaction_id and ticket_id are set
                if (!formData.get('transaction_id')) {
                    alert('Error: Missing transaction ID');
                    return;
                }
                
                if (!formData.get('ticket_id')) {
                    alert('Error: Missing ticket ID');
                    return;
                }
                
                // Combine date and time into a datetime string in MySQL format
                const date = formData.get('payment_date');
                const time = formData.get('payment_time');
                if (date && time) {
                    formData.set('payment_date', `${date} ${time}`);
                }
                
                // Log the form data for debugging
                console.log('Submitting transaction update with data:');
                for (let pair of formData.entries()) {
                    console.log(pair[0] + ': ' + pair[1]);
                }
                
                $.ajax({
                    url: 'update_ticket_reserve.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            if (result.success) {
                                alert('Transaction updated successfully');
                                $('#editTransactionModal').modal('hide');
                                transactionManager.loadTransactionHistory(currentTicketId);
                            } else {
                                alert('Error updating transaction: ' + (result.message || 'Unknown error'));
                            }
                        } catch (e) {
                            console.error('Error parsing response:', e);
                            alert('Error processing the request');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                        console.error('Response:', xhr.responseText);
                        alert('Error updating transaction');
                    }
                });
            });
        }
        
        // Wait for modal to be added to DOM before setting values
        setTimeout(() => {
            // Populate the edit form with the current values
            const $editTransactionId = $('#editTransactionId');
            const $editTicketId = $('#editTicketId');
            const $originalAmount = $('#originalAmount');
            const $editPaymentDate = $('#editPaymentDate');
            const $editPaymentTime = $('#editPaymentTime');
            const $editPaymentAmount = $('#editPaymentAmount');
            const $editPaymentDescription = $('#editPaymentDescription');

            if ($editTransactionId.length) $editTransactionId.val(transactionId);
            if ($editTicketId.length) $editTicketId.val(ticketId);
            if ($originalAmount.length) $originalAmount.val(amount);
            if ($editPaymentDate.length) $editPaymentDate.val(formattedDate);
            if ($editPaymentTime.length) $editPaymentTime.val(formattedTime);
            if ($editPaymentAmount.length) $editPaymentAmount.val(parseFloat(amount).toFixed(2));
            if ($editPaymentDescription.length) $editPaymentDescription.val(description);
            
            // Log values for debugging
            console.log('Edit Transaction:', {
                transactionId: transactionId,
                ticketId: ticketId,
                amount: amount,
                date: formattedDate,
                time: formattedTime,
                description: description
            });
            
            // Show the modal
            $('#editTransactionModal').modal('show');
        }, 100);
    },

    // Update delete transaction function to match your endpoint
    deleteTransaction: function(transactionId) {
        if (!confirm('Are you sure you want to delete this transaction?')) {
            return;
        }

        const ticketId = $('#booking_id').val();
        const transactionRow = $(`button[onclick="transactionManager.deleteTransaction(${transactionId})"]`).closest('tr');
        const amountText = transactionRow.find('td:nth-child(4)').text().trim();
        const amount = parseFloat(amountText.split(' ')[1]);

        // Send as form data instead of JSON
        $.ajax({
            url: 'delete_ticket_reserve_payment.php',
            type: 'POST',
            data: {
                transaction_id: transactionId,
                ticket_id: ticketId,
                amount: amount
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        transactionManager.loadTransactionHistory(ticketId);
                        alert('Transaction deleted successfully');
        } else {
                        alert('Error deleting transaction: ' + (result.message || 'Unknown error'));
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    alert('Error processing the request');
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete Error Response:', {
                    status: xhr.status,
                    error: error,
                    response: xhr.responseText
                });
                alert('Error deleting transaction');
            }
        });
    }
    };

    // Initialize transaction manager when document is ready
    $(document).ready(function() {
        transactionManager.init();
    });

    // Global function to manage transactions (called from HTML)
    function manageTransactions(ticketId) {
        transactionManager.loadTransactionModal(ticketId);
    }
</script>

<script>
    // Trip type toggle for new booking form
    const tripTypeElement = document.getElementById('tripType');
    if (tripTypeElement) {
        tripTypeElement.addEventListener('change', function() {
            const tripType = this.value;
            const returnJourneyFields = document.getElementById('returnJourneyFields');
            const returnDateField = document.getElementById('returnDateField');
            
            if (tripType === 'round_trip') {
                if (returnJourneyFields) returnJourneyFields.style.display = 'block';
                if (returnDateField) returnDateField.style.display = 'block';
                // Make return fields required when visible
                const returnOrigin = document.getElementById('returnOrigin');
                const returnDate = document.getElementById('returnDate');
                if (returnOrigin) returnOrigin.required = true;
                if (returnDate) returnDate.required = true;
            } else {
                if (returnJourneyFields) returnJourneyFields.style.display = 'none';
                if (returnDateField) returnDateField.style.display = 'none';
                // Remove required attribute when hidden
                const returnOrigin = document.getElementById('returnOrigin');
                const returnDate = document.getElementById('returnDate');
                if (returnOrigin) returnOrigin.required = false;
                if (returnDate) returnDate.required = false;
            }
        });
    }
</script>

<script>
    // Payment currency handling for new booking form
    const paymentCurrencyElement = document.getElementById('paymentCurrency');
    if (paymentCurrencyElement) {
        paymentCurrencyElement.addEventListener('change', function() {
            const supplierCurrency = document.getElementById('curr')?.value || '';
            const paymentCurrency = this.value;
            
            const exchangeRateElement = document.getElementById('exchangeRate');
            const paymentAmountElement = document.getElementById('paymentAmount');
            const soldElement = document.getElementById('sold');
            
            if (supplierCurrency !== paymentCurrency) {
                if (exchangeRateElement) exchangeRateElement.required = true;
            } else {
                if (exchangeRateElement) exchangeRateElement.required = false;
                if (paymentAmountElement && soldElement) {
                    paymentAmountElement.value = soldElement.value;
                }
            }
        });
    }
    
    // Calculate payment amount when the calculate button is clicked
    const calculatePaymentElement = document.getElementById('calculatePayment');
    if (calculatePaymentElement) {
        calculatePaymentElement.addEventListener('click', function() {
            const currElement = document.getElementById('curr');
            const paymentCurrencyElement = document.getElementById('paymentCurrency');
            const soldElement = document.getElementById('sold');
            const exchangeRateElement = document.getElementById('exchangeRate');
            const paymentAmountElement = document.getElementById('paymentAmount');
            
            if (!currElement || !paymentCurrencyElement || !soldElement || !exchangeRateElement || !paymentAmountElement) {
                console.error('Missing required elements for payment calculation');
                return;
            }
            
            const supplierCurrency = currElement.value;
            const paymentCurrency = paymentCurrencyElement.value;
            const sold = parseFloat(soldElement.value) || 0;
            const exchangeRate = parseFloat(exchangeRateElement.value) || 1;
            let paymentAmount;
            
            if (supplierCurrency !== paymentCurrency) {
                paymentAmount = sold * exchangeRate;
            } else {
                paymentAmount = sold;
            }
            
            paymentAmountElement.value = paymentAmount.toFixed(2);
        });
    }
    
    // Set supplier currency when supplier changes
    const supplierElement = document.getElementById('supplier');
    if (supplierElement) {
        supplierElement.addEventListener('change', function() {
            // This function is already handled by the existing get_supplier_currency.php call
            // Additionally update payment calculation when supplier or currency changes
            setTimeout(() => {
                const paymentCurrencyElement = document.getElementById('paymentCurrency');
                const currElement = document.getElementById('curr');
                const soldElement = document.getElementById('sold');
                const paymentAmountElement = document.getElementById('paymentAmount');
                
                if (!paymentCurrencyElement || !currElement || !paymentAmountElement) {
                    return;
                }
                
                const paymentCurrency = paymentCurrencyElement.value;
                const supplierCurrency = currElement.value;
                
                if (paymentCurrency === supplierCurrency) {
                    if (soldElement) {
                        paymentAmountElement.value = soldElement.value;
                    }
                } else {
                    // Clear payment amount to require recalculation
                    paymentAmountElement.value = '';
                }
            }, 500); // Small timeout to wait for the supplier currency to be set
        });
    }
    
    // Update payment amount when sold amount changes
    const soldElement = document.getElementById('sold');
    if (soldElement) {
        soldElement.addEventListener('input', function() {
            const paymentCurrencyElement = document.getElementById('paymentCurrency');
            const currElement = document.getElementById('curr');
            const paymentAmountElement = document.getElementById('paymentAmount');
            const calculatePaymentElement = document.getElementById('calculatePayment');
            
            if (!paymentCurrencyElement || !currElement || !paymentAmountElement) {
                return;
            }
            
            const paymentCurrency = paymentCurrencyElement.value;
            const supplierCurrency = currElement.value;
            
            if (paymentCurrency === supplierCurrency) {
                paymentAmountElement.value = this.value;
            } else {
                // If currencies differ, don't auto-update but indicate recalculation is needed
                const currentPaymentAmount = paymentAmountElement.value;
                if (currentPaymentAmount && calculatePaymentElement) {
                    // Trigger calculation if there was already a value
                    calculatePaymentElement.click();
                }
            }
        });
    }
    
    // Trip type toggle for edit form
    const editTripTypeEl = document.getElementById('editTripType');
    if (editTripTypeEl) {
        editTripTypeEl.addEventListener('change', function() {
            const tripType = this.value;
            const returnJourneyFields = document.getElementById('editReturnJourneyFields');
            const returnDateField = document.getElementById('editReturnDateField');
            
            if (tripType === 'round_trip') {
                if (returnJourneyFields) returnJourneyFields.style.display = 'block';
                if (returnDateField) returnDateField.style.display = 'block';
                // Make return fields required when visible
                const editReturnOrigin = document.getElementById('editReturnOrigin');
                const editReturnDate = document.getElementById('editReturnDate');
                if (editReturnOrigin) editReturnOrigin.required = true;
                if (editReturnDate) editReturnDate.required = true;
            } else {
                if (returnJourneyFields) returnJourneyFields.style.display = 'none';
                if (returnDateField) returnDateField.style.display = 'none';
                // Remove required attribute when hidden
                const editReturnOrigin = document.getElementById('editReturnOrigin');
                const editReturnDate = document.getElementById('editReturnDate');
                if (editReturnOrigin) editReturnOrigin.required = false;
                if (editReturnDate) editReturnDate.required = false;
            }
        });
    }
</script>

<script>
    // Function to handle edit ticket button click
    function editTicket(ticketId) {
        // Show loader
        document.getElementById('editLoader').style.display = 'block';
        
        // Function to populate edit form with ticket data
        window.populateEditForm = function(ticketData) {
            try {
                // Set ticket ID
                document.getElementById('editTicketId').value = ticketData.id;
                
                // Set basic ticket information
                document.getElementById('editSupplier').value = ticketData.supplier;
                document.getElementById('editSoldTo').value = ticketData.sold_to;
                document.getElementById('editTripType').value = ticketData.trip_type;
                document.getElementById('editTitle').value = ticketData.title;
                document.getElementById('editGender').value = ticketData.gender;
                document.getElementById('editPassengerName').value = ticketData.passenger_name;
                document.getElementById('editPnr').value = ticketData.pnr;
                document.getElementById('editPhone').value = ticketData.phone;
                
                // Set journey details
                document.getElementById('editOrigin').value = ticketData.origin;
                document.getElementById('editDestination').value = ticketData.destination;
                document.getElementById('editAirline').value = ticketData.airline;
                document.getElementById('editIssueDate').value = ticketData.issue_date;
                document.getElementById('editDepartureDate').value = ticketData.departure_date;
                
                // Set return journey details if applicable
                if (ticketData.trip_type === 'round_trip') {
                    document.getElementById('editReturnDestination').value = ticketData.return_destination || '';
                    document.getElementById('editReturnDate').value = ticketData.return_date || '';
                }
                
                // Set financial details
                document.getElementById('editBase').value = ticketData.price;
                document.getElementById('editSold').value = ticketData.sold;
                document.getElementById('editPro').value = ticketData.profit;
                document.getElementById('editExchangeRate').value = ticketData.exchange_rate;
                
                document.getElementById('editCurr').value = ticketData.currency;
                document.getElementById('editDescription').value = ticketData.description || '';
                document.getElementById('editPaidTo').value = ticketData.paid_to || '';
                
                // Handle return fields visibility directly
                const isRoundTrip = ticketData.trip_type === 'round_trip';
                const returnJourneyFields = document.getElementById('editReturnJourneyFields');
                const returnDateField = document.getElementById('editReturnDateField');
                
                if (returnJourneyFields) {
                    returnJourneyFields.style.display = isRoundTrip ? 'block' : 'none';
                }
                if (returnDateField) {
                    returnDateField.style.display = isRoundTrip ? 'block' : 'none';
                }
                
                // Update required status of return fields
                const returnFields = ['editReturnOrigin', 'editReturnDestination', 'editReturnDate'];
                returnFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.required = isRoundTrip;
                    }
                });
                
                console.log('Form populated successfully with ticket data');
            } catch (error) {
                console.error('Error populating form:', error);
                alert('Error loading form data. Please check the console for details.');
            }
        };
        
        // Fetch ticket data
        fetch(`fetch_ticket_reserve_by_id.php?id=${ticketId}`)
            .then(response => response.json())
            .then(data => {
                // Hide loader
                document.getElementById('editLoader').style.display = 'none';
                
                if (data.success) {
                    // Populate the form with ticket data
                    window.populateEditForm(data.ticket);
                    
                    // Show the modal
                    $('#editTicketModal').modal('show');
                } else {
                    alert('Error loading ticket data: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error fetching ticket data:', error);
                document.getElementById('editLoader').style.display = 'none';
                alert('An error occurred while loading ticket data. Please try again.');
            });
    }

    // Add event listeners to update balances in real-time when editing base and sold prices
    document.addEventListener('DOMContentLoaded', function() {
        const editBaseInput = document.getElementById('editBase');
        const editSoldInput = document.getElementById('editSold');
        const editTripTypeSelect = document.getElementById('editTripType');
        const editDiscountInput = document.getElementById('editDiscount');
        const editProInput = document.getElementById('editPro');
        const editTicketModal = document.getElementById('editTicketModal');
        
        // Skip initialization if elements are not present on the page
        if (!editBaseInput || !editSoldInput || !editTripTypeSelect) {
            console.log('Edit form elements not found, skipping initialization');
            return;
        }
        
        // Store original values when the modal opens
        let originalBase = 0;
        let originalSold = 0;
        
        // When the edit modal is shown, store the original values
        if (editTicketModal) {
            $(editTicketModal).on('shown.bs.modal', function() {
                originalBase = parseFloat(editBaseInput.value) || 0;
                originalSold = parseFloat(editSoldInput.value) || 0;
                
                console.log('Original values stored - Base:', originalBase, 'Sold:', originalSold);
                
                // Show/hide return fields based on trip type
                toggleReturnFields();
            });
        }
        
        // Toggle return fields visibility based on trip type
        if (editTripTypeSelect) {
            editTripTypeSelect.addEventListener('change', toggleReturnFields);
        }
        
        function toggleReturnFields() {
            if (!editTripTypeSelect) return;
            
            const isRoundTrip = editTripTypeSelect.value === 'round_trip';
            const returnJourneyFields = document.getElementById('editReturnJourneyFields');
            const returnDateField = document.getElementById('editReturnDateField');
            
            if (returnJourneyFields) returnJourneyFields.style.display = isRoundTrip ? 'block' : 'none';
            if (returnDateField) returnDateField.style.display = isRoundTrip ? 'block' : 'none';
            
            // Make return fields required if round trip is selected
            const returnFields = ['editReturnOrigin', 'editReturnDestination', 'editReturnDate'];
            returnFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.required = isRoundTrip;
                }
            });
        }
        
        // Calculate profit automatically
        function calculateProfit() {
            if (!editBaseInput || !editSoldInput || !editDiscountInput || !editProInput) return;
            
            const base = parseFloat(editBaseInput.value) || 0;
            const sold = parseFloat(editSoldInput.value) || 0;
            const discount = parseFloat(editDiscountInput.value) || 0;
            const profit = sold - discount - base;
            editProInput.value = profit.toFixed(2);
        }
        
        // Recalculate profit when base or sold changes
        if (editBaseInput) editBaseInput.addEventListener('input', calculateProfit);
        if (editSoldInput) editSoldInput.addEventListener('input', calculateProfit);
        if (editDiscountInput) editDiscountInput.addEventListener('input', calculateProfit);
        
        // Update supplier balance when base price changes
        if (editBaseInput) {
            editBaseInput.addEventListener('input', function() {
                const editSupplierEl = document.getElementById('editSupplier');
                if (!editSupplierEl) return;
                
                const supplierId = editSupplierEl.value;
                if (!supplierId) return;
                
                const newBase = parseFloat(this.value) || 0;
                const baseDifference = originalBase - newBase; // Positive if base decreased, negative if increased
                
                // Only proceed if there's an actual change
                if (baseDifference !== 0) {
                    updateSupplierBalance(supplierId, baseDifference);
                }
            });
        }
        
        // Update client balance when sold price changes
        if (editSoldInput) {
            editSoldInput.addEventListener('input', function() {
                const editSoldToEl = document.getElementById('editSoldTo');
                if (!editSoldToEl) return;
                
                const clientId = editSoldToEl.value;
                if (!clientId) return;
                
                const newSold = parseFloat(this.value) || 0;
                const soldDifference = originalSold - newSold; // Positive if sold decreased, negative if increased
                
                // Only proceed if there's an actual change
                if (soldDifference !== 0) {
                    updateClientBalance(clientId, soldDifference);
                }
            });
        }
        
        // Function to update supplier balance preview
        function updateSupplierBalance(supplierId, difference) {
            // Get the currency
            const currencyElement = document.getElementById('editCurr');
            const currency = currencyElement ? currencyElement.value : 'USD';
            
            // Make AJAX call to get current supplier balance
            fetch(`get_supplier_balance.php?supplier_id=${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Only update preview if supplier is External
                        if (data.is_external) {
                            // Calculate new balance
                            const currentBalance = parseFloat(data.balance) || 0;
                            const newBalance = currentBalance + difference;
                            
                            console.log(`Supplier balance update preview: ${currentBalance} + ${difference} = ${newBalance}`);
                            
                            // Update the supplier dropdown to show the new balance preview
                            const supplierSelect = document.getElementById('editSupplier');
                            if (supplierSelect) {
                                const selectedOption = supplierSelect.options[supplierSelect.selectedIndex];
                                if (selectedOption) {
                                    // Update the option text with the new balance preview
                                    selectedOption.text = `${data.supplier_name} (Balance: ${newBalance.toFixed(2)})`;
                                }
                            }
                        } else {
                            console.log('Supplier is not External, no balance update needed');
                        }
                    } else {
                        console.error('Error fetching supplier balance:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error in supplier balance update:', error);
                });
        }
        
        // Function to update client balance preview
        function updateClientBalance(clientId, difference) {
            // Get the currency
            const currencyElement = document.getElementById('editCurr');
            const currency = currencyElement ? currencyElement.value : 'USD';
            
            // Make AJAX call to get current client balance
            fetch(`get_client_balance.php?client_id=${clientId}&currency=${currency}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Only update preview if client is Regular
                        if (data.is_regular) {
                            // Calculate new balance
                            const currentBalance = parseFloat(data.balance) || 0;
                            const newBalance = currentBalance + difference;
                            
                            console.log(`Client balance update preview: ${currentBalance} + ${difference} = ${newBalance} ${currency}`);
                            
                            // Update the client dropdown to show the new balance preview
                            const clientSelect = document.getElementById('editSoldTo');
                            if (clientSelect) {
                                const selectedOption = clientSelect.options[clientSelect.selectedIndex];
                                if (selectedOption) {
                                    // Update the option text with the new balance preview
                                    selectedOption.text = `${data.client_name} (${currency}: ${newBalance.toFixed(2)})`;
                                }
                            }
                        } else {
                            console.log('Client is not Regular, no balance update needed');
                        }
                    } else {
                        console.error('Error fetching client balance:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error in client balance update:', error);
                });
        }
        
        // Update the form submission to include all fields and balance changes
        const editTicketForm = document.getElementById('editTicketForm');
        if (editTicketForm) {
            editTicketForm.addEventListener('submit', function(event) {
                event.preventDefault();
                
                // Show loader
                const editLoader = document.getElementById('editLoader');
                if (editLoader) {
                    editLoader.style.display = 'block';
                }
                
                const formData = new FormData(this);
                
                // Add the original values to the form data for server-side comparison
                formData.append('originalBase', originalBase);
                formData.append('originalSold', originalSold);
                
                // Validate required fields for round trip
                if (editTripTypeSelect && editTripTypeSelect.value === 'round_trip') {
                    const returnFields = ['editReturnOrigin', 'editReturnDestination', 'editReturnDate'];
                    for (const fieldId of returnFields) {
                        const field = document.getElementById(fieldId);
                        if (field && !field.value) {
                            const label = field.previousElementSibling ? field.previousElementSibling.textContent : 'Required';
                            alert(`Please fill in the ${label} field.`);
                            if (editLoader) {
                                editLoader.style.display = 'none';
                            }
                            return;
                        }
                    }
                }
                
                fetch('update_ticket_reserve.php', {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    // Hide loader
                    if (editLoader) {
                        editLoader.style.display = 'none';
                    }
                    
                    if (data.success) {
                        alert('Ticket updated successfully!');
                        $('#editTicketModal').modal('hide');
                        location.reload(); // Refresh to see updated balances
                    } else {
                        alert('Error updating ticket: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error updating ticket:', error);
                    if (editLoader) {
                        editLoader.style.display = 'none';
                    }
                    alert('An error occurred while updating the ticket. Please try again.');
                });
            });
        }
    });
</script><script>
  $(document).ready(function() {
    $('.select2-airlines').select2({
      placeholder: "Select an airline",
      allowClear: true
    });
  });
</script>


                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  
  
<!-- Multiple Ticket Invoice Modal -->
<div class="modal fade" id="multiTicketInvoiceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="feather icon-file-text mr-2"></i>Generate Combined Invoice
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3">
                    <i class="feather icon-info mr-2"></i>Select multiple tickets to generate a combined invoice.
                </div>
                
                <form id="multiTicketInvoiceForm">
                    <div class="form-group">
                        <label for="clientForInvoice">Client</label>
                        
                        <input type="text" class="form-control" id="clientForInvoice" name="clientForInvoice" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="invoiceComment">Comments/Notes</label>
                        <textarea class="form-control" id="invoiceComment" name="invoiceComment" rows="2"></textarea>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="ticketSelectionTable">
                            <thead class="thead-light">
                                <tr>
                                    <th width="40">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="selectAllTickets">
                                            <label class="custom-control-label" for="selectAllTickets"></label>
                                        </div>
                                    </th>
                                    <th>Passenger</th>
                                    <th>PNR</th>
                                    <th>Sector</th>
                                    <th>Flight</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="ticketsForInvoiceBody">
                                <!-- Tickets will be loaded here dynamically -->
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <td colspan="6" class="text-right font-weight-bold">Total:</td>
                                    <td id="invoiceTotal" class="font-weight-bold">0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label for="invoiceCurrency">Currency</label>
                        <select class="form-control" id="invoiceCurrency" name="invoiceCurrency" required>
                            <option value="USD">USD</option>
                    -footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="generateCombinedInvoice">
                    <i class="feather icon-file-text mr-2"></i>Generate Invoice
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add a floating action button for launching the multi-ticket invoice modal -->
<div class="position-fixed" style="bottom: 20px; left: 280px; z-index: 1050;">
    <button type="button" class="btn btn-primary btn-lg shadow" id="launchMultiTicketInvoice" title="Generate Multi-Ticket Invoice">
        <i class="feather icon-file-text"></i>
    </button>
</div>
<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>