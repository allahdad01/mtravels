<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'client')) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
require_once('../includes/conn.php');
// Fetch user data with proper error handling
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Log the error
        error_log("User not found: " . $_SESSION['user_id']);
        
        // For debugging
        echo "<!-- Debug: User ID = " . $_SESSION['user_id'] . " -->";
        echo "<!-- Debug: SQL = SELECT * FROM users WHERE id = " . $_SESSION['user_id'] . " -->";
        
        // Redirect to login if user not found
        session_destroy();
        header('Location: login.php');
        exit();
    }

    // For debugging - remove in production
    echo "<!-- Debug: User Data = " . json_encode($user) . " -->";

} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    // For debugging
    echo "<!-- Debug: Database Error = " . $e->getMessage() . " -->";
    
    $user = null;
}

// Verify the data is being fetched correctly
if ($user) {
    // Log successful fetch
    error_log("User data fetched successfully for ID: " . $_SESSION['user_id']);
} else {
    // Log fetch failure
    error_log("Failed to fetch user data for ID: " . $_SESSION['user_id']);
}


// Fetch settings data
try {
    $settingStmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Settings Error: " . $e->getMessage());
    $settings = ['agency_name' => 'Default Name'];
}

// Fetch Tickets
$visaQuery = "SELECT * FROM visa_applications WHERE sold_to = " . $_SESSION['user_id'];
$visaResult = $conn->query($visaQuery);
$visas = $visaResult->fetch_all(MYSQLI_ASSOC);

// Fetch Suppliers
$suppliersQuery = "SELECT id, name FROM suppliers";
$suppliersResult = $conn->query($suppliersQuery);
$suppliers = $suppliersResult->fetch_all(MYSQLI_ASSOC);


// Fetch Clients
$clientsQuery = "SELECT id, name FROM clients";
$clientsResult = $conn->query($clientsQuery);
$clients = $clientsResult->fetch_all(MYSQLI_ASSOC);

// Fetch internal
$internalQuery = "SELECT id, name FROM main_account";
$internalResult = $conn->query($internalQuery);
$internal = $internalResult->fetch_all(MYSQLI_ASSOC);

// Create an associative array of supplier id to supplier name for easy lookup
$supplier_names = [];
foreach ($suppliers as $supplier) {
    $supplier_names[$supplier['id']] = $supplier['name'];
}

// Create an associative array of client id to client name for easy lookup
$client_names = [];
foreach ($clients as $client) {
    $client_names[$client['id']] = $client['name'];
}

// Create an associative array of internal id to internal name for easy lookup
$internal_names = [];
foreach ($internal as $int) {
    $internal_names[$int['id']] = $int['name'];
}

// Now, for each visa, add the supplier's name and other names based on their IDs
foreach ($visas as $key => $visa) {
    $supplier_id = $visa['supplier'];
    $client_id = $visa['sold_to'];
    $internal_id = $visa['paid_to'];
    
    // Add supplier name
    $visas[$key]['supplier_name'] = isset($supplier_names[$supplier_id]) ? $supplier_names[$supplier_id] : 'Unknown';
    
    // Add client name
    $visas[$key]['sold_name'] = isset($client_names[$client_id]) ? $client_names[$client_id] : 'Unknown';
    
    // Add paid_to name
    $visas[$key]['paid_name'] = isset($internal_names[$internal_id]) ? $internal_names[$internal_id] : 'Unknown';
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
                            <span class="text-muted">Client</span>
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
<!-- [ Header ] end --

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                
                                    
                                        <!-- body -->


                                            <!-- Visa Management Section -->
                <div class="container-fluid px-4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="feather icon-file-text mr-2"></i>Visa Applications</h5>
                                    <input type="text" id="searchInput" class="form-control" placeholder="Search Visa by Passport number ..." onkeyup="searchVisa()">
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover" id="visaTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th><i class="feather icon-hash mr-2"></i>#</th>
                                            <th><i class="feather icon-user mr-2"></i>Sold To</th>
                                            <th><i class="feather icon-user mr-2"></i>Passenger</th>
                                            <th><i class="feather icon-book mr-2"></i>Passport</th>
                                            <th><i class="feather icon-map mr-2"></i>Country</th>
                                            <th><i class="feather icon-dollar-sign mr-2"></i>Amount</th>
                                            <th><i class="feather icon-check-circle mr-2"></i>Status</th>
                                            <th><i class="feather icon-settings mr-2"></i>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($visas as $index => $visa): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="avatar avatar-sm bg-light text-primary mr-2">
                                                        <?= strtoupper(substr($visa['supplier_name'], 0, 1)) ?>
                                                    </span>
                                                    <?= htmlspecialchars($visa['supplier_name']) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($visa['sold_name']) ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="font-weight-bold"><?= htmlspecialchars($visa['title'] . ' ' . $visa['applicant_name']) ?></span>
                                                    <small class="text-muted"><?= htmlspecialchars($visa['gender']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-light">
                                                    <?= htmlspecialchars($visa['passport_number']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?= htmlspecialchars($visa['country']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="font-weight-bold text-primary">
                                                        <?= htmlspecialchars($visa['currency'] . ' ' . number_format($visa['sold'], 2)) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= getStatusBadgeClass($visa['status']) ?>">
                                                    <?= htmlspecialchars($visa['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                        <i class="feather icon-more-horizontal"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item view-details" href="javascript:void(0)" 
                                                        data-visa='<?= htmlspecialchars(json_encode($visa)) ?>'>
                                                            <i class="feather icon-eye text-info mr-2"></i>View Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                                <!-- Visa Details Modal -->
                                <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">
                                                    <i class="feather icon-file-text mr-2"></i>Visa Details
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                <ul class="nav nav-pills nav-fill mb-3" id="detailsTab" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" id="details-summary-tab" data-toggle="tab" href="#details-summary">
                                                            <i class="feather icon-info mr-1"></i>Summary
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="details-description-tab" data-toggle="tab" href="#details-description">
                                                            <i class="feather icon-file-text mr-1"></i>Description
                                                        </a>
                                                    </li>
                                                </ul>
                                                <div class="tab-content p-3 border rounded">
                                                    <div class="tab-pane fade show active" id="details-summary">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="card border-primary mb-3">
                                                                    <div class="card-header bg-primary text-white">
                                                                        <i class="feather icon-user mr-1"></i>Personal Details
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p class="mb-2"><strong>Paid To:</strong> <span id="paid-to"></span></p>
                                                                        <p class="mb-2"><strong>Country:</strong> <span id="country"></span></p>
                                                                        <p class="mb-2"><strong>Visa Type:</strong> <span id="visa-type"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="card border-success mb-3">
                                                                    <div class="card-header bg-success text-white">
                                                                        <i class="feather icon-dollar-sign mr-1"></i>Financial Details
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <p class="mb-2"><strong>Currency:</strong> <span id="currency"></span></p>
                                                                        <p class="mb-2"><strong>Sold Price:</strong> <span id="sold-price"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card border-info">
                                                            <div class="card-header bg-info text-white">
                                                                <i class="feather icon-calendar mr-1"></i>Dates
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong>Receive Date:</strong> <span id="receive-date"></span></p>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong>Applied Date:</strong> <span id="applied-date"></span></p>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <p class="mb-2"><strong>Issued Date:</strong> <span id="issued-date"></span></p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="tab-pane fade" id="details-description">
                                                        <div class="card">
                                                            <div class="card-body">
                                                                <p id="description" class="mb-0"></p>
                                                            </div>
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
                            </div>

                                <?php
                                function getStatusBadgeClass($status) {
                                    switch (strtolower($status)) {
                                        case 'approved':
                                            return 'success';
                                        case 'pending':
                                            return 'warning';
                                        case 'rejected':
                                            return 'danger';
                                        default:
                                            return 'secondary';
                                    }
                                }
                                ?>
                                        
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

                                                                               <!-- Profile Modal -->
   <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i>Client Profile
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
                             alt="Client Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['client_type']) ? htmlspecialchars($user['client_type']) : 'Client' ?></p>
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
                                <label class="text-muted mb-1">USD Balance</label>
                                <p class="mb-0">$<?= number_format($user['usd_balance'], 2) ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1">AFS Balance</label>
                                <p class="mb-0">؋<?= number_format($user['afs_balance'], 2) ?></p>
                            </div>
                        </div>
                        <div class="col-sm-12 mb-3">
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
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-sync bg-info"></i>
                                <div class="timeline-content">
                                    <p class="mb-0">Last Updated</p>
                                    <small class="text-muted"><?= !empty($user['updated_at']) ? date('M d, Y H:i A', strtotime($user['updated_at'])) : 'Not Available' ?></small>
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



    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <!-- update profile -->

    <script>
                                    
                                    document.querySelectorAll('.view-details').forEach(button => {
                                   button.addEventListener('click', function () {
                                       const visa = JSON.parse(this.getAttribute('data-visa'));
                                       
                                       // Update modal fields
                                       document.getElementById('country').textContent = visa.country;
                                       document.getElementById('paid-to').textContent = visa.paid_name || 'Not specified';
                                       document.getElementById('visa-type').textContent = visa.visa_type;
                                       document.getElementById('receive-date').textContent = visa.receive_date;
                                       document.getElementById('applied-date').textContent = visa.applied_date;
                                       document.getElementById('issued-date').textContent = visa.issued_date;
                                       document.getElementById('sold-price').textContent = visa.sold;
                                       document.getElementById('currency').textContent = visa.currency;

                                       document.getElementById('description').textContent = visa.description;
                                       $('#detailsModal').data('visa-id', visa.id);

                                       // Show the modal
                                       $('#detailsModal').modal('show');
                                   });
                               });

                                     function deleteVisa(id) {
                                           if (confirm('Are you sure you want to delete this Visa?')) {
                                               fetch('delete_visa.php', {
                                                   method: 'POST',
                                                   headers: { 'Content-Type': 'application/json' },
                                                   body: JSON.stringify({ id }),
                                               })
                                               .then(response => response.json())
                                               .then(data => {
                                                   if (data.success) {
                                                       alert('Visa deleted successfully!');
                                                       location.reload(); // Refresh table
                                                   } else {
                                                       alert('Error: ' + data.message);
                                                   }
                                               })
                                               .catch(error => console.error('Error deleting Visa:', error));
                                           }
                                       }
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
                                                const currencyInput = document.getElementById('curr');
                                                if (data.currency) {
                                                    currencyInput.value = data.currency;

                                                    console.log('Currency input updated to:', data.currency);
                                                } else {
                                                    currencyInput.value = '';
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
                            document.addEventListener('DOMContentLoaded', function() {
                                // Listen for form submission (using submit event)
                                document.getElementById('updateProfileForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    
                                    const newPassword = document.getElementById('newPassword').value;
                                    const confirmPassword = document.getElementById('confirmPassword').value;
                                    const currentPassword = document.getElementById('currentPassword').value;

                                    // If any password field is filled, all password fields must be filled
                                    if (newPassword || confirmPassword || currentPassword) {
                                        if (!currentPassword) {
                                            alert('Please enter your current password');
                                            return;
                                        }
                                        if (!newPassword) {
                                            alert('Please enter a new password');
                                            return;
                                        }
                                        if (!confirmPassword) {
                                            alert('Please confirm your new password');
                                            return;
                                        }
                                        if (newPassword !== confirmPassword) {
                                            alert('New passwords do not match');
                                            return;
                                        }
                                        if (newPassword.length < 6) {
                                            alert('New password must be at least 6 characters long');
                                            return;
                                        }
                                    }
                                    
                                    const formData = new FormData(this);
                                    
                                    fetch('update_client_profile.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert(data.message);
                                            // Clear password fields
                                            document.getElementById('currentPassword').value = '';
                                            document.getElementById('newPassword').value = '';
                                            document.getElementById('confirmPassword').value = '';
                                            location.reload();
                                        } else {
                                            alert(data.message || 'Failed to update profile');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('An error occurred while updating the profile');
                                    });
                                });
                            });
                            </script>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
                              
</body>
</html>

<!-- Add this script section after your table -->
<script>
function searchVisa() {
    // Get input value and convert to lowercase for case-insensitive search
    let input = document.getElementById('searchInput').value.toLowerCase();
    let table = document.querySelector('.table');
    let rows = table.getElementsByTagName('tr');

    // Loop through all table rows, starting from index 1 to skip header
    for (let i = 1; i < rows.length; i++) {
        let row = rows[i];
        let passportCell = row.getElementsByTagName('td')[6]; // Index 6 is the passport number column
        
        if (passportCell) {
            let passportNumber = passportCell.textContent || passportCell.innerText;
            
            // Show/hide row based on whether passport number contains the search input
            if (passportNumber.toLowerCase().indexOf(input) > -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }
}

// Add event listener for real-time search
document.getElementById('searchInput').addEventListener('input', function() {
    searchVisa();
});
</script>