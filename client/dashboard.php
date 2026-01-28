<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
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
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to client dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
require_once('../includes/db.php');
include '../includes/conn.php';
?>

<?php include '../includes/header_client.php'; ?>

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
                            
                            <!-- Logo Section End -->
                            
                            
                            
                            <!-- Messages Section -->
<div class="col-xl-12 col-md-6">
    <div class="card Recent-Users">
        <div class="card-header">
            <h5><?= __('messages') ?></h5>
            <ul class="nav nav-pills nav-fill" id="messageTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="unread-messages-tab" data-toggle="tab" href="#unread-messages" role="tab">
                        <i class="feather icon-mail mr-1"></i><?= __('unread') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="read-messages-tab" data-toggle="tab" href="#read-messages" role="tab">
                        <i class="feather icon-check-circle mr-1"></i><?= __('read') ?>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="tab-content" id="messageTabContent">
                <!-- Unread Messages Tab -->
                <div class="tab-pane fade show active" id="unread-messages" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <tbody>
                                <?php
                                try {
                                    // Query to fetch unread messages
                                    $query = "SELECT m.*, u.name as sender_name
                                             FROM messages m
                                             JOIN users u ON m.sender_id = u.id
                                             WHERE (m.recipient_type = 'all'
                                             OR m.recipient_type = 'clients'
                                             OR (m.recipient_type = 'individual'
                                             AND m.recipient_id = ?
                                             AND m.recipient_table = 'clients'))
                                             AND m.status = 'unread'
                                             ORDER BY m.created_at DESC";
                                    
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$_SESSION['user_id']]);
                                    displayMessages($stmt, 'unread');
                                } catch (PDOException $e) {
                                    error_log("Error fetching unread messages: " . $e->getMessage());
                                    echo '<tr><td colspan="4">'.__('error_loading_messages').'</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Read Messages Tab -->
                <div class="tab-pane fade" id="read-messages" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <tbody>
                                <?php
                                try {
                                    // Query to fetch read messages
                                    $query = "SELECT m.*, u.name as sender_name
                                             FROM messages m
                                             JOIN users u ON m.sender_id = u.id
                                             WHERE (m.recipient_type = 'all'
                                             OR m.recipient_type = 'clients'
                                             OR (m.recipient_type = 'individual'
                                             AND m.recipient_id = ?
                                             AND m.recipient_table = 'clients'))
                                             AND m.status = 'read'
                                             ORDER BY m.created_at DESC";
                                     
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$_SESSION['user_id']]);
                                    displayMessages($stmt, 'read');
                                } catch (PDOException $e) {
                                    error_log("Error fetching read messages: " . $e->getMessage());
                                    echo '<tr><td colspan="4">'.__('error_loading_messages').'</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

                            
                          
                  
<?php
require_once '../includes/conn.php';

// Query for tickets booked today with supplier name and transaction status
$today_query = "SELECT ticket_bookings.*, suppliers.name AS supplier_name FROM ticket_bookings LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id WHERE DATE(ticket_bookings.created_at) = CURDATE() AND ticket_bookings.sold_to = ?";
try {
    $today_stmt = $pdo->prepare($today_query);
    $today_stmt->execute([$_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log("Error fetching today's tickets: " . $e->getMessage());
    $today_stmt = null;
}

// Fetch this week's tickets
$this_week_query = "SELECT ticket_bookings.*, suppliers.name AS supplier_name FROM ticket_bookings LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id WHERE YEARWEEK(ticket_bookings.created_at, 1) = YEARWEEK(CURDATE(), 1) AND ticket_bookings.sold_to = ?";
try {
    $this_week_stmt = $pdo->prepare($this_week_query);
    $this_week_stmt->execute([$_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log("Error fetching this week's tickets: " . $e->getMessage());
    $this_week_stmt = null;
}

// Fetch this month's tickets
$this_month_query = "SELECT ticket_bookings.*, suppliers.name AS supplier_name FROM ticket_bookings LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id WHERE YEAR(ticket_bookings.created_at) = YEAR(CURDATE()) AND MONTH(ticket_bookings.created_at) = MONTH(CURDATE()) AND ticket_bookings.sold_to = ?";
try {
    $this_month_stmt = $pdo->prepare($this_month_query);
    $this_month_stmt->execute([$_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log("Error fetching this month's tickets: " . $e->getMessage());
    $this_month_stmt = null;
}


?>



                                
 <div class="col-xl-12 col-md-6">
    <div class="card">
        <div class="card-header">
            <h5><?= __('ticket_bookings_overview') ?></h5>
            <ul class="nav nav-pills nav-fill" id="ticketTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="today-tab" data-toggle="tab" href="#today" role="tab">
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
                <!-- Today's Tickets -->
                <div class="tab-pane fade show active" id="today" role="tabpanel">
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
                             


                            </div>
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
  
    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
	<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="debtors-modal-fix.js"></script>
   <!-- JavaScript to Handle Modal and AJAX Request -->


<?php
// Function to display messages
function displayMessages($stmt, $status) {
    if ($stmt->rowCount() > 0) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $message_id = htmlspecialchars($row['id']);
            $subject = htmlspecialchars($row['subject']);
            $message = htmlspecialchars($row['message']);
            $sender_name = htmlspecialchars($row['sender_name']);
            $created_at = htmlspecialchars($row['created_at']);
            ?>
            <tr class="<?php echo $status; ?>">
                <td width="50" class="align-middle">
                    <img class="rounded-circle" style="width:40px;" src="../assets/images/user/avatar-1.jpg" alt="sender-avatar">
                </td>
                <td width="100" class="align-middle">
                    <?php if ($status === 'unread') { ?>
                        <button class="btn btn-info btn-sm read-button" 
                                data-id="<?php echo $message_id; ?>">
                            <?= __('mark_as_read') ?>
                        </button>
                    <?php } else { ?>
                        <button class="btn btn-secondary btn-sm" disabled>
                            <?= __('read') ?>
                        </button>
                    <?php } ?>
                </td>
                <td class="message-content">
                    <div class="message-wrapper">
                        <h6 class="message-text"><?php echo $subject; ?></h6>
                        <p class="message-details"><?php echo $message; ?></p>
                    </div>
                </td>
                <td width="150" class="align-middle">
                    <h6 class="text-muted">
                        <i class="fas fa-circle text-c-green f-10 m-r-15"></i>
                        <?php echo date('M d, Y', strtotime($created_at)); ?>
                    </h6>
                    <small class="text-muted"><?= __('from') ?>: <?php echo $sender_name; ?></small>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="4">'.__('no_messages_available').'</td></tr>';
    }
}
?>

<style>
    /* Add some styling for the message tabs */
    .nav-pills .nav-link {
        color: #6c757d;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        transition: all 0.3s ease;
    }

    .nav-pills .nav-link.active {
        background-color: #4099ff;
        color: #fff;
    }

    .nav-pills .nav-link i {
        margin-right: 5px;
    }

    /* Style for read messages */
    tr.read {
        opacity: 0.8;
        background-color: #f8f9fa;
    }

    tr.read .message-text {
        color: #6c757d;
    }

    tr.read .message-details {
        color: #adb5bd;
    }

    /* Message content styling */
    .message-content {
        max-width: 400px;
    }

    .message-wrapper {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .message-text {
        margin: 0;
        font-size: 0.95rem;
        line-height: 1.4;
        word-wrap: break-word;
        white-space: pre-line;
        color: #333;
    }

    .message-details {
        margin: 0;
        font-size: 0.85rem;
        color: #6c757d;
        word-wrap: break-word;
    }

    /* Button styling */
    .read-button {
        white-space: nowrap;
        transition: all 0.3s ease;
    }

    .read-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .message-content {
            max-width: 200px;
        }
        
        .message-text {
            font-size: 0.9rem;
        }
        
        .message-details {
            font-size: 0.8rem;
        }
    }
</style>


                           
                            <!-- [ Main Content ] end -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- [ Main Content ] end -->

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>


</body>
</html>