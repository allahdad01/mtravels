<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// First, define pagination variables
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Initialize variables
$bookings = [];


// Fetch bookings data with all necessary fields
try {
    $stmt = $pdo->prepare("
        SELECT 
            hb.id,
            CONCAT(hb.title, ' ', hb.first_name, ' ', hb.last_name) as guest_name,
            hb.gender,
            hb.order_id,
            hb.check_in_date,
            hb.check_out_date,
            hb.accommodation_details,
            hb.issue_date,
            hb.supplier_id,
            hb.contact_no,
            hb.base_amount,
            hb.sold_amount,
            hb.profit,
            hb.currency,
            hb.remarks,
            hb.receipt,
            s.name as supplier_name,
            c.name as client_name,
            ma.name as paid_to_name
        FROM hotel_bookings hb
        LEFT JOIN suppliers s ON hb.supplier_id = s.id
        LEFT JOIN clients c ON hb.sold_to = c.id
        LEFT JOIN main_account ma ON hb.paid_to = ma.id
        WHERE hb.sold_to = " . $_SESSION['user_id'] . "
        ORDER BY hb.id DESC
        LIMIT :offset, :itemsPerPage
    ");
    
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':itemsPerPage', $itemsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For debugging
    error_log("Bookings fetched successfully: " . count($bookings) . " records");
} catch (PDOException $e) {
    error_log("Error fetching bookings: " . $e->getMessage());
    $bookings = [];
}

// Get total number of records for pagination
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM hotel_bookings WHERE sold_to = " . $_SESSION['user_id']);
    $totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $itemsPerPage);
    
    // Calculate start and end record numbers
    $startRecord = $offset + 1;
    $endRecord = min($offset + $itemsPerPage, $totalRecords);

} catch (PDOException $e) {
    error_log("Error fetching pagination data: " . $e->getMessage());
    $totalRecords = 0;
    $totalPages = 1;
    $startRecord = 0;
    $endRecord = 0;
}

// Validate current page
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
    // Recalculate offset
    $offset = ($currentPage - 1) * $itemsPerPage;
}

// Ensure all variables have default values
$totalPages = $totalPages ?? 1;
$startRecord = $startRecord ?? 0;
$endRecord = $endRecord ?? 0;

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
        echo "<!-- Debug: SQL = SELECT * FROM clients WHERE id = " . $_SESSION['user_id'] . " -->";
        
        // Redirect to login if user not found
        session_destroy();
        header('Location: ../login.php');
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

// Add this function definition after your database queries and before the HTML
/**
 * Safely get array value with default
 * @param array $array The array to get value from
 * @param string $key The key to look for
 * @param mixed $default Default value if key doesn't exist
 * @return mixed The value or default
 */
function getValue($array, $key, $default = 'N/A') {
    if (!is_array($array)) {
        return $default;
    }
    
    if (!isset($array[$key])) {
        return $default;
    }
    
    if (empty($array[$key]) && $array[$key] !== 0 && $array[$key] !== '0') {
        return $default;
    }
    
    return htmlspecialchars($array[$key]);
}

/**
 * Get status badge class
 * @param string $status The status value
 * @return string The corresponding CSS class
 */

/**
 * Format date in a consistent way
 * @param string $date The date string
 * @param string $format The desired format
 * @return string Formatted date or N/A
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) {
        return 'N/A';
    }
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return 'N/A';
    }
}

/**
 * Format currency amount
 * @param float $amount The amount to format
 * @param string $currency The currency code
 * @return string Formatted amount with currency
 */
function formatAmount($amount, $currency = 'USD') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Generate pagination HTML
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $urlPattern URL pattern for pagination links
 * @return string Generated pagination HTML
 */
function generatePagination($currentPage, $totalPages, $urlPattern = '?page=') {
    if ($totalPages <= 1) {
        return '';
    }

    $html = '<ul class="pagination pagination-sm mb-0">';

    // Previous button
    $prevDisabled = ($currentPage <= 1) ? ' disabled' : '';
    $html .= '<li class="page-item' . $prevDisabled . '">
                <a class="page-link" href="' . $urlPattern . ($currentPage - 1) . '" tabindex="-1">
                    <i class="feather icon-chevron-left"></i>
                </a>
              </li>';

    // Page numbers
    $maxPages = 5; // Maximum number of page links to show
    $startPage = max(1, min($currentPage - floor($maxPages / 2), $totalPages - $maxPages + 1));
    $endPage = min($startPage + $maxPages - 1, $totalPages);

    // First page
    if ($startPage > 1) {
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $urlPattern . '1">1</a>
                  </li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>';
        }
    }

    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = ($i == $currentPage) ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">
                    <a class="page-link" href="' . $urlPattern . $i . '">' . $i . '</a>
                  </li>';
    }

    // Last page
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled">
                        <span class="page-link">...</span>
                      </li>';
        }
        $html .= '<li class="page-item">
                    <a class="page-link" href="' . $urlPattern . $totalPages . '">' . $totalPages . '</a>
                  </li>';
    }

    // Next button
    $nextDisabled = ($currentPage >= $totalPages) ? ' disabled' : '';
    $html .= '<li class="page-item' . $nextDisabled . '">
                <a class="page-link" href="' . $urlPattern . ($currentPage + 1) . '">
                    <i class="feather icon-chevron-right"></i>
                </a>
              </li>';

    $html .= '</ul>';

    return $html;
}

/**
 * Generate page info text
 * @param int $currentPage Current page number
 * @param int $itemsPerPage Items per page
 * @param int $totalItems Total number of items
 * @return string Page info text
 */
function generatePageInfo($currentPage, $itemsPerPage, $totalItems) {
    $startItem = (($currentPage - 1) * $itemsPerPage) + 1;
    $endItem = min($startItem + $itemsPerPage - 1, $totalItems);
    
    return "Showing {$startItem} to {$endItem} of {$totalItems} entries";
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                $bookingPages = ['ticket.php', 'refund_ticket.php', 'date_change.php', 'hotel.php'];
                $isBookingActive = in_array(basename($_SERVER['PHP_SELF']), $bookingPages);
                ?>
                <li data-username="bookings" class="nav-item pcoded-hasmenu <?php echo $isBookingActive ? 'active pcoded-trigger' : ''; ?>">
                    <a href="javascript:" class="nav-link">
                        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
                        <span class="pcoded-mtext">Bookings</span>
                    </a>
                    <ul class="pcoded-submenu">
                        <?php 
                        $ticketPages = ['ticket.php', 'refund_ticket.php', 'date_change.php'];
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
<!-- [ Header ] end -->

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">

                    <div class="main-body">
                        <div class="page-wrapper">

                            <!-- Main Card -->
                            <div class="card shadow-sm">
                                <!-- Card Header with Actions -->
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="feather icon-list mr-2"></i>Hotel Bookings</h5>
                                </div>

                                <!-- Table Container -->
                                        <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="bookingsTable">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="border-0">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="selectAll">
                                                        <label class="custom-control-label" for="selectAll"></label>
                                                    </div>
                                                </th>
                                                <th class="border-0">Booking ID</th>
                                                <th class="border-0">Guest</th>
                                                <th class="border-0">Check In/Out</th>
                                                <th class="border-0">Room Details</th>
                                                <th class="border-0">Amount</th>
                                                <th class="border-0">Status</th>
                                                <th class="border-0 text-center">Actions</th>
                                                    </tr>
                                                </thead>
                                        <tbody id="bookingsTableBody">
                                            <?php if (!empty($bookings)): ?>
                                                <?php foreach ($bookings as $booking): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" 
                                                                       id="booking<?= getValue($booking, 'id') ?>">
                                                                <label class="custom-control-label" 
                                                                       for="booking<?= getValue($booking, 'id') ?>"></label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="font-weight-bold">#<?= getValue($booking, 'order_id') ?></span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="avatar avatar-sm bg-light text-primary mr-2">
                                                                    <?= strtoupper(substr(getValue($booking, 'first_name'), 0, 1)) ?>
                                                                </div>
                                                                <div>
                                                                    <span class="d-block"><?= getValue($booking, 'guest_name') ?></span>
                                                                    <small class="text-muted"><?= getValue($booking, 'contact_no') ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span><?= getValue($booking, 'check_in_date') ? date('M d, Y', strtotime($booking['check_in_date'])) : 'N/A' ?></span>
                                                                <small class="text-muted"><?= getValue($booking, 'check_in_date') ? date('D', strtotime($booking['check_in_date'])) : '' ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span><?= getValue($booking, 'check_out_date') ? date('M d, Y', strtotime($booking['check_out_date'])) : 'N/A' ?></span>
                                                                <small class="text-muted"><?= getValue($booking, 'check_out_date') ? date('D', strtotime($booking['check_out_date'])) : '' ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <h6 class="mb-1 text-primary">
                                                                    <?= getValue($booking, 'currency') ?> <?= number_format(getValue($booking, 'sold_amount', 0), 2) ?>
                                                                </h6>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span>Sold to: <?= getValue($booking, 'client_name') ?></span>
                                                                <small class="text-muted">Paid to: <?= getValue($booking, 'paid_to_name') ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex justify-content-center">
                                                                <button type="button" class="btn btn-icon btn-sm btn-info mr-2" 
                                                                        onclick="viewBooking(<?= $booking['id'] ?>)" 
                                                                        title="View Details">
                                                                    <i class="feather icon-eye"></i>
                                                                    </button>
                                                            </div>
                                                                </td>
                                                            </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">No bookings found</td>
                                                </tr>
                                            <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                <!-- Empty State -->
                                <?php if (empty($bookings)): ?>
                                <div class="text-center py-5">
                                    <img src="../assets/images/empty-bookings.svg" alt="No Bookings" 
                                         class="mb-4" style="width: 200px;">
                                    <h5 class="text-muted mb-2">No Bookings Found</h5>
                                    <p class="text-muted mb-3">Start by adding your first hotel booking</p>
                                    <button class="btn btn-primary" data-toggle="modal" data-target="#addBookingModal">
                                        <i class="feather icon-plus mr-1"></i>Add New Booking
                                                        </button>
                                                    </div>
                                <?php endif; ?>

                                <!-- Pagination -->
                                <?php if (!empty($bookings)): ?>
                                <div class="card-footer bg-white">
                                    <div class="row align-items-center">
                                        <div class="col">
                                            <p class="small text-muted mb-0">
                                                <?= generatePageInfo($currentPage, $itemsPerPage, $totalRecords) ?>
                                            </p>
                                                            </div>
                                        <div class="col-auto">
                                            <nav>
                                                <?= generatePagination($currentPage, $totalPages) ?>
                                            </nav>
                                                            </div>
                                                        </div>
                                                            </div>
                                <?php endif; ?>
                                                            </div>
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


<!-- View Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booking Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="bookingDetails">
                    <!-- Details will be populated dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Place this script section right after your jQuery and Bootstrap includes, but before your table HTML -->
<script type="text/javascript">
// Define all functions in the global scope
window.viewBooking = function(id) {
    if (!id) {
        console.error('No booking ID provided');
        return;
    }

    console.log('Viewing booking:', id);

    $.ajax({
        url: 'get_hotel_bookings.php',
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(bookings) {
            console.log('Response:', bookings);
            
            if (bookings && bookings.length > 0) {
                const booking = bookings[0];
                
                $('#bookingDetails').html(`
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Guest Name:</strong> ${booking.title} ${booking.first_name} ${booking.last_name}</p>
                            <p><strong>Order ID:</strong> ${booking.order_id || 'N/A'}</p>
                            <p><strong>Contact:</strong> ${booking.contact_no || 'N/A'}</p>
                            <p><strong>Check In:</strong> ${booking.check_in_date}</p>
                            <p><strong>Check Out:</strong> ${booking.check_out_date}</p>
                            <p><strong>Issue Date:</strong> ${booking.issue_date}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Client:</strong> ${booking.client_name || 'N/A'}</p>
                            <p><strong>Paid To:</strong> ${booking.paid_to_name || 'N/A'}</p>
                            <p><strong>Sold Amount:</strong> ${booking.currency} ${parseFloat(booking.sold_amount).toFixed(2)}</p>
                            </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong>Accommodation Details:</strong></p>
                            <p>${booking.accommodation_details || 'N/A'}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong>Remarks:</strong></p>
                            <p>${booking.remarks || 'No remarks'}</p>
                        </div>
                    </div>
                `);

                window.currentBookingId = id;
                $('#detailsModal').modal('show');
            } else {
                alert('Booking not found');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.log('Response:', xhr.responseText);
            alert('Error fetching booking details');
        }
    });
};

window.editBooking = function(id) {
    // Your existing editBooking function code here
    // Make sure to keep the same structure as viewBooking
    // First load the booking data
    $.ajax({
        url: 'get_hotel_booking.php',
        type: 'GET',
        data: { id: id },
        success: function(response) {
            try {
                const booking = JSON.parse(response);
                console.log('Booking data:', booking);

                // Load dropdowns and then populate form
                $.ajax({
                    url: 'fetch_suppliers.php',
                    type: 'GET',
                    dataType: 'json', // Add this line
                    success: function(suppliersResponse) {
                        // Populate supplier dropdown
                        let supplierOptions = '<option value="">Select Supplier</option>';
                        suppliersResponse.forEach(supplier => {
                            supplierOptions += `<option value="${supplier.id}">${supplier.name}</option>`;
                        });
                        $('#editBookingForm #supplier_id').html(supplierOptions);
                        $('#editBookingForm #supplier_id').val(booking.supplier_id);

                        // Load clients
                        $.ajax({
                            url: 'fetch_clients.php',
                            type: 'GET',
                            dataType: 'json', // Add this line
                            success: function(clientsResponse) {
                                // Populate client dropdown
                                let clientOptions = '<option value="">Select Client</option>';
                                clientsResponse.forEach(client => {
                                    clientOptions += `<option value="${client.id}">${client.name}</option>`;
                                });
                                $('#editBookingForm #sold_to').html(clientOptions);
                                $('#editBookingForm #sold_to').val(booking.sold_to);

                                // Load main accounts
    $.ajax({
                                    url: 'fetch_main_accounts.php',
                                    type: 'GET',
                                    dataType: 'json', // Add this line
                                    success: function(accountsResponse) {
                                        // Populate paid to dropdown
                                        let accountOptions = '<option value="">Select Account</option>';
                                        accountsResponse.forEach(account => {
                                            accountOptions += `<option value="${account.id}">${account.name}</option>`;
                                        });
                                        $('#editBookingForm #paid_to').html(accountOptions);
                                        $('#editBookingForm #paid_to').val(booking.paid_to);

                                        // Populate all other form fields
                                        $('#editBookingForm #booking_id').val(booking.id);
                                        $('#editBookingForm #title').val(booking.title);
                                        $('#editBookingForm #first_name').val(booking.first_name);
                                        $('#editBookingForm #last_name').val(booking.last_name);
                                        $('#editBookingForm #gender').val(booking.gender);
                                        $('#editBookingForm #order_id').val(booking.order_id);
                                        $('#editBookingForm #check_in_date').val(booking.check_in_date);
                                        $('#editBookingForm #check_out_date').val(booking.check_out_date);
                                        $('#editBookingForm #accommodation_details').val(booking.accommodation_details);
                                        $('#editBookingForm #issue_date').val(booking.issue_date);
                                        $('#editBookingForm #contact_no').val(booking.contact_no);
                                        $('#editBookingForm #base_amount').val(booking.base_amount);
                                        $('#editBookingForm #sold_amount').val(booking.sold_amount);
                                        $('#editBookingForm #profit').val(booking.profit);
                                        $('#editBookingForm #currency').val(booking.currency);
                                        $('#editBookingForm #remarks').val(booking.remarks);

                                        // Show the modal after everything is populated
                                        $('#editBookingModal').modal('show');
                                    },
                                    error: function(xhr, status, error) {
                                        console.error('Error loading main accounts:', error);
                                        alert('Error loading account data');
                                    }
                                });
                            },
                            error: function(xhr, status, error) {
                                console.error('Error loading clients:', error);
                                alert('Error loading client data');
                            }
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading suppliers:', error);
                        alert('Error loading supplier data');
                    }
                });

            } catch (e) {
                console.error('Error parsing booking data:', e);
                alert('Error loading booking details');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('Error fetching booking details');
        }
    });

    // Add event listeners for amount calculations
    $('#editBookingForm #base_amount, #editBookingForm #sold_amount').on('input', function() {
        const baseAmount = parseFloat($('#editBookingForm #base_amount').val()) || 0;
        const soldAmount = parseFloat($('#editBookingForm #sold_amount').val()) || 0;
        $('#editBookingForm #profit').val((soldAmount - baseAmount).toFixed(2));
    });
};


// Document ready handler
$(document).ready(function() {
    // Your existing document.ready code here
});
</script>

<!-- Rest of your HTML code -->
</body>
</html>
