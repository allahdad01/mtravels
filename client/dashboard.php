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
                            
                            
                              <!-- Logo Section -->
                            <div class="row mb-4">
                                <div class="col-12 text-center">
                                    <img src="../assets/images/WhatsApp_Image_2024-11-23_at_12.05.34_PM-removebg-preview-removebg-preview.png" 
                                         alt="Al Moqadas Logo" 
                                         style="max-width: 400px; height: auto;"
                                         class="img-fluid">
                                </div>
                            </div>
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
$today_query = "SELECT ticket_bookings.*, 
                       suppliers.name AS supplier_name 
                       
                FROM ticket_bookings
                LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id
                
                WHERE DATE(ticket_bookings.created_at) = CURDATE() and ticket_bookings.sold_to = '".$_SESSION['user_id']."'";
try {
    $today_stmt = $pdo->query($today_query);
} catch (PDOException $e) {
    error_log("Error fetching today's tickets: " . $e->getMessage());
    $today_stmt = null;
}

// Fetch this week's tickets
$this_week_query = "SELECT ticket_bookings.*, 
                           suppliers.name AS supplier_name
                    FROM ticket_bookings
                    LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id
                    WHERE YEARWEEK(ticket_bookings.created_at, 1) = YEARWEEK(CURDATE(), 1) and ticket_bookings.sold_to = '".$_SESSION['user_id']."'";
try {
    $this_week_stmt = $pdo->query($this_week_query);
} catch (PDOException $e) {
    error_log("Error fetching this week's tickets: " . $e->getMessage());
    $this_week_stmt = null;
}

// Fetch this month's tickets
$this_month_query = "SELECT ticket_bookings.*, 
                            suppliers.name AS supplier_name
                     FROM ticket_bookings
                     LEFT JOIN suppliers ON ticket_bookings.supplier = suppliers.id
                     WHERE YEAR(ticket_bookings.created_at) = YEAR(CURDATE())
                       AND MONTH(ticket_bookings.created_at) = MONTH(CURDATE()) and ticket_bookings.sold_to = '".$_SESSION['user_id']."'";
try {
    $this_month_stmt = $pdo->query($this_month_query);
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
                             
                               <!-- Profile Modal -->
   <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
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
                                <label class="text-muted mb-1"><?= __('email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __('account_information') ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __('account_created') ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                
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
                                                    <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
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
                                                        <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __('full_name') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __('email_address') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __('phone_number') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __('address') ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __('current_password') ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __('new_password') ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
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
                                            alert('<?= __('please_enter_your_current_password') ?>');
                                            return;
                                        }
                                        if (!newPassword) {
                                            alert('<?= __('please_enter_a_new_password') ?>');
                                            return;
                                        }
                                        if (!confirmPassword) {
                                            alert('<?= __('please_confirm_your_new_password') ?>');
                                            return;
                                        }
                                        if (newPassword !== confirmPassword) {
                                            alert('<?= __('new_passwords_do_not_match') ?>');
                                            return;
                                        }
                                        if (newPassword.length < 6) {
                                            alert('<?= __('new_password_must_be_at_least_6_characters_long') ?>');
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
                                            alert(data.message || '<?= __('failed_to_update_profile') ?>');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('<?= __('an_error_occurred_while_updating_the_profile') ?>');
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

<style>
        /* Card styling */
        .card {
            border: none;
            box-shadow: 0 0 10px rgba(0,0,0,.1);
            border-radius: 8px;
        }
        
        /* Financial Chart styling */
        .wealth-distribution-summary {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            height: 100%;
            min-height: 300px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }
        
        .wealth-distribution-summary .font-weight-bold {
            font-size: 1.1rem;
        }
        
        .wealth-distribution-summary .d-flex {
            padding: 6px 0;
        }
        
        .wealth-distribution-summary hr {
            margin: 12px 0;
            border-color: rgba(0,0,0,0.1);
        }
        
        /* Chart container */
        #financeFlowChart {
            min-height: 400px;
            width: 100%;
        }
        
        .text-success {
            color: #2ed8b6 !important;
        }
        
        .text-danger {
            color: #ff5370 !important;
        }
        
        #financeChartPeriod, #financeChartCurrency {
            width: auto;
            display: inline-block;
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 1rem;
        }

        /* Responsive adjustments for chart area */
        @media (max-width: 992px) {
            #financeFlowChart {
                height: 350px;
                margin-bottom: 20px;
            }
            
            .wealth-distribution-summary {
                padding: 10px;
            }
        }

        /* Tab styling */
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

        /* Table styling */
        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
            padding: 1rem;
        }

        /* Badge styling */
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
        }

        .badge-success {
            background-color: #2ed8b6;
        }

        .badge-warning {
            background-color: #ffb64d;
        }

        .badge-danger {
            background-color: #ff5370;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table-responsive {
                border: 0;
            }
            
            .table td {
                padding: 0.75rem;
            }
            
            .d-flex.flex-column {
                font-size: 0.875rem;
            }
            
            .nav-pills .nav-link {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
        }

        /* Hover effects */
        .table-hover tbody tr:hover {
            background-color: rgba(64, 153, 255, 0.05);
            transition: all 0.3s ease;
        }

        /* Custom scrollbar */
        .table-responsive::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background-color: rgba(0,0,0,.2);
            border-radius: 3px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background-color: rgba(0,0,0,.05);
        }

        /* Notification table styles */
        .Recent-Users .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        /* Message content styling */
        .notification-content {
            max-width: 400px; /* Adjust based on your needs */
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
            white-space: pre-line; /* This will preserve line breaks */
            color: #333;
        }

        .transaction-details {
            margin: 0;
            font-size: 0.85rem;
            color: #6c757d;
            word-wrap: break-word;
        }

        .amount {
            font-weight: 600;
            color: #28a745;
        }

        /* Button styling */
        .approve-button {
            white-space: nowrap;
            transition: all 0.3s ease;
        }

        .approve-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .notification-content {
                max-width: 200px;
            }
            
            .message-text {
                font-size: 0.9rem;
            }
            
            .transaction-details {
                font-size: 0.8rem;
            }
        }

        /* Table cell alignment */
        .align-middle {
            vertical-align: middle !important;
        }

        /* Status indicators */
        .text-c-green {
            color: #2ed8b6;
        }

        /* Hover effect for rows */
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }
</style>

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