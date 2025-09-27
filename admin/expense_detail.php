<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database security module for input validation
require_once 'includes/db_security.php';
$tenant_id = $_SESSION['tenant_id'];
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Initialize variables
$expense = null;
$transactions = [];
$errorMessage = '';

// Get expense ID from URL
if (isset($_GET['id'])) {
    $expenseId = DbSecurity::validateInput($_GET['id'], 'int');
    
    if (!$expenseId) {
        $errorMessage = "Invalid expense ID.";
    } else {
        // Get expense details
        $query = "SELECT e.*, ec.name as category_name, ma.name as account_name 
                  FROM expenses e 
                  LEFT JOIN expense_categories ec ON e.category_id = ec.id
                  LEFT JOIN main_account ma ON e.main_account_id = ma.id
                  WHERE e.id = ? AND e.tenant_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$expenseId, $tenant_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$expense) {
            $errorMessage = "Expense not found.";
        } else {
            // Get transactions related to this expense
            $transactionQuery = "SELECT 
                'Main Account' AS transaction_type,
                mat.id,
                mat.type,
                mat.amount,
                mat.balance,
                mat.currency,
                mat.description,
                mat.transaction_of,
                mat.receipt,
                mat.created_at AS transaction_date
                FROM main_account_transactions mat
                WHERE mat.reference_id = ? AND mat.transaction_of = 'expense' AND mat.tenant_id = ?";
            $stmt = $pdo->prepare($transactionQuery);
            $stmt->execute([$expenseId, $tenant_id]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    $errorMessage = "Expense ID is required.";
}

// Include the header
include '../includes/header.php';
?>
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
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><?= __('expense_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="expenses.php"><?= __('expenses') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('expense_details') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger"><?php echo h($errorMessage); ?></div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header">
                            <h5><?= __('expense_information') ?></h5>
                            <span class="text-muted">Details about expense #<?php echo h($expense['id']); ?></span>
                            <div class="card-header-right">
                                <a href="expense_management.php" class="btn btn-sm btn-secondary">
                                    <i class="feather icon-arrow-left"></i> <?= __('back_to_expenses') ?>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered table-striped">
                                        <tr>
                                            <th width="30%"><?= __('id') ?></th>
                                            <td><strong><?php echo h($expense['id']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('category') ?></th>
                                            <td><?php echo h($expense['category_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('description') ?></th>
                                            <td><?php echo h($expense['description']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('amount') ?></th>
                                            <td>
                                                <span class="text-danger font-weight-bold">
                                                    <?php echo h($expense['currency']) . ' ' . h(number_format($expense['amount'], 2)); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered table-striped">
                                        <tr>
                                            <th width="30%"><?= __('date') ?></th>
                                            <td><?php echo h(date('Y-m-d', strtotime($expense['date']))); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('account') ?></th>
                                            <td><?php echo h($expense['account_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('created_at') ?></th>
                                            <td><?php echo h(date('Y-m-d H:i:s', strtotime($expense['created_at']))); ?></td>
                                        </tr>
                                        <?php if (isset($expense['allocation_id']) && $expense['allocation_id']): ?>
                                        <tr>
                                            <th><?= __('budget_allocation') ?></th>
                                            <td>
                                                <a href="budget_allocation_detail.php?id=<?php echo h($expense['allocation_id']); ?>">
                                                    <?= __('view_allocation') ?> #<?php echo h($expense['allocation_id']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transaction History -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="feather icon-activity mr-1"></i> <?= __('transaction_history') ?></h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($transactions)): ?>
                                <div class="alert alert-info"><?= __('no_transactions_found_for_this_expense') ?></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><?= __('id') ?></th>
                                                <th><?= __('type') ?></th>
                                                <th><?= __('amount') ?></th>
                                                <th><?= __('balance') ?></th>
                                                <th><?= __('description') ?></th>
                                                <th><?= __('transaction_date') ?></th>
                                               
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $transaction): ?>
                                                <tr>
                                                    <td><?php echo h($transaction['id']); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo strtolower($transaction['type']) == 'debit' ? 'danger' : 'success'; ?>">
                                                            <?php echo h($transaction['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo h($transaction['currency']) . ' ' . h(number_format($transaction['amount'], 2)); ?></td>
                                                    <td><?php echo h($transaction['currency']) . ' ' . h(number_format($transaction['balance'], 2)); ?></td>
                                                    <td><?php echo h($transaction['description']); ?></td>
                                                    <td><?php echo h(date('Y-m-d H:i', strtotime($transaction['transaction_date']))); ?></td>
                                                    
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Actions Section -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="feather icon-tool mr-1"></i> <?= __('actions') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    
                                    <a href="print_expense.php?id=<?php echo h($expenseId); ?>" class="btn btn-secondary ml-2" target="_blank">
                                        <i class="feather icon-printer"></i> <?= __('print_details') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="receiptModalLabel"><?= __('receipt') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="receiptImage" src="" alt="Receipt" class="img-fluid">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('close') ?></button>
                <a id="downloadReceipt" href="" class="btn btn-primary" download><?= __('download') ?></a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel"><?= __('confirm_delete') ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= __('are_you_sure_you_want_to_delete_this_expense') ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                <a href="#" id="deleteLink" class="btn btn-danger"><?= __('delete') ?></a>
            </div>
        </div>
    </div>
</div>

<script>
    function showReceipt(receipt) {
        const baseUrl = '../uploads/receipts/';
        const receiptUrl = baseUrl + receipt;
        
        document.getElementById('receiptImage').src = receiptUrl;
        document.getElementById('downloadReceipt').href = receiptUrl;
        
        $('#receiptModal').modal('show');
    }
    
    function confirmDelete(id) {
        document.getElementById('deleteLink').href = 'delete_expense.php?id=' + id;
        $('#deleteModal').modal('show');
    }
</script>

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

<?php
// Include footer
include '../includes/admin_footer.php';
?> 