<?php
require_once '../includes/conn.php';
require_once '../includes/db.php';
require_once 'security.php';
require_once '../includes/language_helpers.php';


// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch customers with their total balances
$stmt = $conn->prepare("
    SELECT 
        c.*,
        COALESCE(SUM(w.balance), 0) as current_balance,
        w.currency
    FROM customers c
    LEFT JOIN customer_wallets w ON c.id = w.customer_id
    WHERE c.status = 'active' AND c.tenant_id = ?
    GROUP BY c.id, w.currency
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
$customers = [];

// Organize customer data
while ($row = $result->fetch_assoc()) {
    $customerId = $row['id'];
    if (!isset($customers[$customerId])) {
        $customers[$customerId] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'created_at' => $row['created_at'],
            'balances' => []
        ];
    }
    if ($row['currency']) {
        $customers[$customerId]['balances'][$row['currency']] = $row['current_balance'];
    }
}

?>


    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4099ff 0%, #73b4ff 100%);
            --success-gradient: linear-gradient(135deg, #2ed8b6 0%, #59e0c5 100%);
            --warning-gradient: linear-gradient(135deg, #FFB64D 0%, #ffcb80 100%);
            --danger-gradient: linear-gradient(135deg, #FF5370 0%, #ff869a 100%);
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        .card {
            border: none;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            border-radius: 10px;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .card-header {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 1.25rem;
        }

        .btn {
            border-radius: 50px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-success {
            background: var(--success-gradient);
            border: none;
        }

        .btn-info {
            background: var(--primary-gradient);
            border: none;
        }

        .btn-warning {
            background: var(--warning-gradient);
            border: none;
            color: white;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            padding: 1.25rem 1rem;
            background: #f8f9fa;
            white-space: nowrap;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f1f1f1;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
        }

        .badge-light {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #eee;
        }

        .customer-name {
            font-weight: 600;
            color: #333;
            transition: var(--transition);
        }

        .customer-name:hover {
            color: #4099ff;
        }

        .customer-contact {
            line-height: 1.4;
        }

        .customer-contact small {
            opacity: 0.7;
        }

        .btn-group-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-group-actions .btn {
            padding: 0.4rem 1rem;
            font-size: 0.875rem;
        }

        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: var(--success-gradient);
            color: white;
        }

        .alert-danger {
            background: var(--danger-gradient);
            color: white;
        }

        @media (max-width: 768px) {
            .btn-group-actions {
                flex-direction: column;
            }
            
            .btn-group-actions .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .table-responsive {
                border-radius: 10px;
                box-shadow: var(--card-shadow);
            }
        }
    </style>
<style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>
    <?php if (in_array($_SESSION['lang'] ?? 'en', ['fa', 'ps'])): ?>
    <style>
        .card-header {
            flex-direction: row-reverse !important;
        }
        .card-header .title-section {
            margin-right: 0;
            margin-left: auto;
        }
        .card-header .button-section {
            margin-left: 0;
            margin-right: auto;
        }
        .feather {
            margin-left: 8px;
            margin-right: 0;
        }
    </style>
    <?php endif; ?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="css/modal-styles.css">

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <!-- Customer Stats Cards -->
                                <div class="col-xl-3 col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <i class="feather icon-users f-30 text-primary"></i>
                                                <div class="ml-3">
                                                    <h6 class="mb-1"><?= __('total_customers') ?></h6>
                                                    <h3 class="mb-0"><?= count($customers) ?></h3>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div class="title-section d-flex align-items-center">
                                                <i class="feather icon-users mr-2"></i>
                                                <h5 class="mb-0"><?= __('customer_management') ?></h5>
                                            </div>
                                            <div class="button-section">
                                                <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#customerModal">
                                                    <i class="feather icon-user-plus"></i> <?= __('new_customer') ?>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <!-- Success/Error Messages -->
                                            <?php if (isset($success_message)): ?>
                                                <div class="alert alert-success alert-dismissible fade show">
                                                    <i class="feather icon-check-circle mr-2"></i>
                                                    <?php echo htmlspecialchars($success_message); ?>
                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($error_message)): ?>
                                                <div class="alert alert-danger alert-dismissible fade show">
                                                    <i class="feather icon-alert-circle mr-2"></i>
                                                    <?php echo htmlspecialchars($error_message); ?>
                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Search and Filter Section -->
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text bg-primary border-primary text-white">
                                                                <i class="feather icon-search"></i>
                                                            </span>
                                                        </div>
                                                        <input type="text" class="form-control" id="customerSearch" 
                                                               placeholder="<?= __('search_customers') ?>">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Customers Table -->
                                            <div class="table-responsive">
                                                <table class="table table-hover" id="customersTable">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('customer_name') ?></th>
                                                            <th><?= __('customer_contact') ?></th>
                                                            <th><?= __('customer_current_balance') ?></th>
                                                            <th><?= __('customer_created') ?></th>
                                                            <th><?= __('customer_actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($customers as $customer): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="customer-name">
                                                                    <?= htmlspecialchars($customer['name']) ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="customer-contact">
                                                                    <div><i class="feather icon-phone mr-1"></i><?= htmlspecialchars($customer['phone']) ?></div>
                                                                    <?php if ($customer['email']): ?>
                                                                    <small><i class="feather icon-mail mr-1"></i><?= htmlspecialchars($customer['email']) ?></small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                if (!empty($customer['balances'])) {
                                                                    foreach ($customer['balances'] as $currency => $balance) {
                                                                        if (floatval($balance) != 0) {
                                                                            $badgeClass = floatval($balance) > 0 ? 'badge-success' : 'badge-danger';
                                                                            echo "<div class='badge {$badgeClass} mr-1'>" . 
                                                                                htmlspecialchars(number_format($balance, 2) . " " . $currency) . 
                                                                                "</div>";
                                                                        }
                                                                    }
                                                                } else {
                                                                    echo "<span class='text-muted'>" . __('no_balance') . "</span>";
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <i class="feather icon-calendar mr-1 text-muted"></i>
                                                                    <?= date('Y-m-d', strtotime($customer['created_at'])) ?>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group-actions">
                                                                    <a href="customer_detail.php?id=<?= $customer['id'] ?>" 
                                                                       class="btn btn-info btn-sm">
                                                                        <i class="feather icon-eye"></i> <?= __('view_customer') ?>
                                                                    </a>
                                                                    <button class="btn btn-warning btn-sm" 
                                                                            onclick="editCustomer(<?= $customer['id'] ?>)">
                                                                        <i class="feather icon-edit"></i> <?= __('edit_customer') ?>
                                                                    </button>
                                                                    <a href="print_statement.php?id=<?= $customer['id'] ?>" 
                                                                       class="btn btn-primary btn-sm" target="_blank">
                                                                        <i class="feather icon-printer"></i> <?= __('print_statement') ?>
                                                                    </a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
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

    <!-- Include Customer Modal -->
    <?php include 'includes/sarafi_modals.php'; ?>

    <!-- Edit Customer Modal -->
    <div class="modal fade" id="editCustomerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('edit_customer') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?= __('close') ?>">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editCustomerForm" method="POST" action="handlers/edit_customer.php">
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" id="edit_customer_id">
                        <div class="form-group">
                            <label for="edit_name"><?= __('customer_name') ?></label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_phone"><?= __('customer_phone') ?></label>
                            <input type="text" class="form-control" id="edit_phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_email"><?= __('customer_email') ?></label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="form-group">
                            <label for="edit_address"><?= __('customer_address') ?></label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                        <button type="submit" class="btn btn-primary"><?= __('update_customer') ?></button>
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
                    <i class="feather icon-user mr-2"></i><?= __('user_profile') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="<?= __('close') ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="<?= __('user_profile') ?>">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : __('guest') ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : __('user') ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('customer_email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : __('not_set') ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('customer_phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : __('not_set') ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : __('not_set') ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('customer_address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : __('not_set') ?></p>
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
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : __('not_available') ?></small>
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
                                                            <img src="<?= $imagePath ?>" alt="<?= __('user_profile') ?>" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2"><?= __('change_profile_picture') ?></small>
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
                                                                <label for="updateAddress"><?= __('customer_address') ?></label>
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
                            <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <script>
        // Initialize tooltips
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });

        // Customer search functionality
        document.getElementById('customerSearch').addEventListener('keyup', function(e) {
            const searchValue = e.target.value.toLowerCase();
            const tableRows = document.querySelectorAll('#customersTable tbody tr');
            
            tableRows.forEach(row => {
                const name = row.querySelector('.customer-name').textContent.toLowerCase();
                const contact = row.querySelector('.customer-contact').textContent.toLowerCase();
                
                if (name.includes(searchValue) || contact.includes(searchValue)) {
                    row.style.display = '';
                    // Highlight matching text
                    if (searchValue) {
                        highlightText(row, searchValue);
                    } else {
                        removeHighlight(row);
                    }
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Text highlighting functions
        function highlightText(element, searchText) {
            const nodes = element.querySelectorAll('.customer-name, .customer-contact');
            nodes.forEach(node => {
                const text = node.textContent;
                const highlightedText = text.replace(
                    new RegExp(searchText, 'gi'),
                    match => `<span class="highlight">${match}</span>`
                );
                if (text !== highlightedText) {
                    node.innerHTML = highlightedText;
                }
            });
        }

        function removeHighlight(element) {
            const highlights = element.querySelectorAll('.highlight');
            highlights.forEach(highlight => {
                const text = highlight.textContent;
                highlight.replaceWith(text);
            });
        }

        // Add loading state to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function() {
                if (!this.classList.contains('disabled')) {
                    const originalHtml = this.innerHTML;
                    this.setAttribute('data-original-html', originalHtml);
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
                    this.classList.add('disabled');
                }
            });
        });

        // Customer edit functionality
        function editCustomer(customerId) {
            const button = event.target.closest('button');
            const originalHtml = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
            button.classList.add('disabled');

            fetch(`handlers/get_customer.php?id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_customer_id').value = data.customer.id;
                        document.getElementById('edit_name').value = data.customer.name;
                        document.getElementById('edit_phone').value = data.customer.phone;
                        document.getElementById('edit_email').value = data.customer.email || '';
                        document.getElementById('edit_address').value = data.customer.address || '';
                        
                        $('#editCustomerModal').modal('show');
                    } else {
                        showToast('error', '<?= __('error_fetching_customer') ?>');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', '<?= __('error_fetching_customer') ?>');
                })
                .finally(() => {
                    button.innerHTML = originalHtml;
                    button.classList.remove('disabled');
                });
        }

        // Toast notification function
        function showToast(type, message) {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="feather icon-${type === 'success' ? 'check-circle' : 'alert-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }, 100);
        }

        // Form submission handling
        document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            submitBtn.classList.add('disabled');
            
            fetch('handlers/edit_customer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                showToast(data.success ? 'success' : 'error', data.message);

                if (data.success) {
                    $('#editCustomerModal').modal('hide');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', '<?= __('error_updating_customer') ?>');
            })
            .finally(() => {
                submitBtn.innerHTML = originalHtml;
                submitBtn.classList.remove('disabled');
            });
        });

        // Add these styles for toast notifications
        const style = document.createElement('style');
        style.textContent = `
            .toast {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem;
                border-radius: 8px;
                background: white;
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                z-index: 9999;
                transform: translateX(120%);
                transition: transform 0.3s ease;
            }

            .toast.show {
                transform: translateX(0);
            }

            .toast-content {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .toast-success {
                background: var(--success-gradient);
                color: white;
            }

            .toast-error {
                background: var(--danger-gradient);
                color: white;
            }

            .highlight {
                background: #fff3cd;
                padding: 2px;
                border-radius: 3px;
            }

            .btn.disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            .fa-spin {
                animation: spin 1s linear infinite;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html> 