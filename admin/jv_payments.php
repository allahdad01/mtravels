<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include the security module
require_once('security.php');
$tenant_id = $_SESSION['tenant_id'];
// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication for this page
enforce_auth(['admin', 'finance']);



// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/conn.php';
require_once '../includes/db.php';

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Build redirect URL with current query parameters
$redirect_url = $_SERVER['PHP_SELF'];
if (!empty($_GET)) {
    $redirect_url .= '?' . http_build_query($_GET);
}

// Get all clients
$clientsQuery = "SELECT id, name, usd_balance, afs_balance FROM clients WHERE status = 'active' AND tenant_id = ? ORDER BY name";
$clientsStmt = $pdo->prepare($clientsQuery);
$clientsStmt->execute([$tenant_id]);
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all suppliers
$suppliersQuery = "SELECT id, name, balance, currency FROM suppliers WHERE status = 'active' AND tenant_id = ? ORDER BY name";
$suppliersStmt = $pdo->prepare($suppliersQuery);
$suppliersStmt->execute([$tenant_id]);
$suppliers = $suppliersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all JV payments
$jvPaymentsQuery = "SELECT jp.*, u.name as created_by_name 
                    FROM jv_payments jp 
                    LEFT JOIN users u ON jp.created_by = u.id 
                    WHERE jp.tenant_id = ?
                    ORDER BY jp.created_at DESC";
try {
    $jvPaymentsStmt = $pdo->prepare($jvPaymentsQuery);
    $jvPaymentsStmt->execute([$tenant_id]);
    $jvPayments = $jvPaymentsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching JV payments: " . $e->getMessage());
    $jvPayments = [];
}
    
?>

    <style>
        .card {
            border-radius: 15px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .card-header {
            border-bottom: 2px solid #007BFF;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px;
        }
        .btn-primary {
            border-radius: 8px;
            padding: 10px 20px;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        /* Payment Details Modal Styles */
        .payment-details .payment-header {
            border-radius: 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .payment-details .icon-box {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .payment-details .card {
            transition: transform 0.2s;
        }
        
        .payment-details .card:hover {
            transform: translateY(-3px);
        }
        
        .payment-details .card-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
        }
        
        #viewCsModal .modal-content {
            border: none;
            overflow: hidden;
        }
        
        #viewCsModal .modal-body {
            padding: 0;
        }
        
        @media (max-width: 767px) {
            .payment-details .payment-header {
                text-align: center;
            }
            
            .payment-details .text-md-right {
                text-align: center !important;
            }
            
            /* Mobile-friendly table styles */
            #clientSupplierTable tbody tr {
                cursor: pointer;
                transition: background-color 0.2s;
            }
            
            #clientSupplierTable tbody tr:hover {
                background-color: rgba(0, 123, 255, 0.05);
            }
            
            /* Make action buttons more touch-friendly */
            #clientSupplierTable .btn-sm {
                padding: 0.375rem 0.5rem;
                margin: 0.15rem;
            }
            
            /* Add visual indicator that rows are clickable */
            #clientSupplierTable tbody tr td:first-child::before {
                content: "";
                display: inline-block;
                width: 8px;
                height: 8px;
                margin-right: 8px;
                background-color: #4099ff;
                border-radius: 50%;
                opacity: 0.5;
            }
        }
    </style>


    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="css/modal-styles.css">
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
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><?= __('jv_payments_management') ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($success_message): ?>
                                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                    <?php echo $success_message; ?>
                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($error_message): ?>
                                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                    <?php echo $error_message; ?>
                                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Heading for Client to Supplier Payments -->
                                            <div class="d-flex justify-content-between align-items-center mb-4">
                                                <h5><?= __('client_to_supplier_payment_management') ?></h5>
                                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addClientSupplierModal">
                                                    <i class="feather icon-plus-circle mr-1"></i> <?= __('add_new_payment') ?>
                                                </button>
                                            </div>
                                            
                                            <!-- Client to Supplier Payment Content -->
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5><?= __('client_supplier_jv_guide') ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="alert alert-info">
                                                        <h6><?= __('client_to_supplier_jv_payment') ?></h6>
                                                        <p><?= __('this_process_allows_clients_to_pay_suppliers_directly_from_their_account_balance_without_using_a_main_account') ?></p>
                                                        <ul>
                                                            <li><?= __('client_balance_will_be_reduced_by_the_specified_amount') ?></li>
                                                            <li><?= __('supplier_balance_will_be_increased_by_the_equivalent_amount') ?></li>
                                                            <li><?= __('if_currencies_differ_the_exchange_rate_will_be_used_for_conversion') ?></li>
                                                            <li><?= __('transactions_will_be_recorded_for_both_client_and_supplier') ?></li>
                                                        </ul>
                                                    </div>
                                                    <div class="alert alert-warning">
                                                        <h6><?= __('important_notes') ?></h6>
                                                        <ul>
                                                            <li><?= __('the_exchange_rate_is_critical_when_the_client_and_supplier_use_different_currencies') ?></li>
                                                            <li><?= __('always_verify_client_and_supplier_details_before_confirming_the_transaction') ?></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Client-Supplier Payments Table -->
                                            <div class="card mt-4">
                                                <div class="card-header">
                                                    <h5><?= __('payment_history') ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-hover" id="clientSupplierTable">
                                                            <thead>
                                                                <tr>
                                                                    <th><?= __('date') ?></th>
                                                                    <th><?= __('jv_name') ?></th>
                                                                    <th><?= __('client') ?></th>
                                                                    <th><?= __('supplier') ?></th>
                                                                    <th><?= __('amount') ?></th>
                                                                    <th><?= __('currency') ?></th>
                                                                    <th><?= __('receipt') ?></th>
                                                                    <th><?= __('actions') ?></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php 
                                                                // Get client-supplier JV payments
                                                                $csQuery = "SELECT jp.*, c.name as client_name, s.name as supplier_name 
                                                                            FROM jv_payments jp 
                                                                            LEFT JOIN clients c ON jp.client_id = c.id 
                                                                            LEFT JOIN suppliers s ON jp.supplier_id = s.id 
                                                                            WHERE jp.tenant_id = ?
                                                                            ORDER BY jp.created_at DESC";
                                                                $csStmt = $pdo->prepare($csQuery);
                                                                $csStmt->execute([$tenant_id]);
                                                                $csPayments = $csStmt->fetchAll(PDO::FETCH_ASSOC);
                                                                
                                                                foreach ($csPayments as $payment): ?>
                                                                    <tr>
                                                                        <td><?= date('Y-m-d H:i', strtotime($payment['created_at'])) ?></td>
                                                                        <td><?= htmlspecialchars($payment['jv_name']) ?></td>
                                                                        <td><?= htmlspecialchars($payment['client_name']) ?></td>
                                                                        <td><?= htmlspecialchars($payment['supplier_name']) ?></td>
                                                                        <td><?= htmlspecialchars($payment['total_amount']) ?></td>
                                                                        <td><?= htmlspecialchars($payment['currency']) ?></td>
                                                                        <td><?= htmlspecialchars($payment['receipt']) ?></td>
                                                                        <td>
                                                                            <button type="button" class="btn btn-info btn-sm view-cs-btn" data-id="<?= htmlspecialchars($payment['id']) ?>">
                                                                                <i class="feather icon-eye"></i>
                                                                            </button>
                                                                            <button type="button" class="btn btn-warning btn-sm edit-cs-btn" data-id="<?= htmlspecialchars($payment['id']) ?>">
                                                                                <i class="feather icon-edit-2"></i>
                                                                            </button>
                                                                            <button type="button" class="btn btn-danger btn-sm delete-cs-btn" data-id="<?= htmlspecialchars($payment['id']) ?>">
                                                                                <i class="feather icon-trash-2"></i>
                                                                            </button>
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
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete JV Payment Modal -->
    <div class="modal fade" id="deleteJvModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('delete_jv_payment') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="<?= $redirect_url ?>">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <p><?= __('are_you_sure_you_want_to_delete_this_jv_payment') ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-danger"><?= __('delete') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <!-- Add Client-Supplier Payment Modal -->
    <div class="modal fade" id="addClientSupplierModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('add_client_to_supplier_payment') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="process_client_supplier_jv.php" id="clientSupplierForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="jv_name"><?= __('jv_name') ?></label>
                            <input type="text" class="form-control" id="jv_name" name="jv_name" value="Client-Supplier Payment" required>
                        </div>
                        <div class="form-group">
                            <label for="client_id"><?= __('client') ?></label>
                            <select class="form-control" id="client_id" name="client_id" required>
                                <option value=""><?= __('select_client') ?></option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>">
                                        <?= htmlspecialchars($client['name']) ?> 
                                        (USD: <?= number_format($client['usd_balance'], 2) ?>, 
                                        AFS: <?= number_format($client['afs_balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="supplier_id"><?= __('supplier') ?></label>
                            <select class="form-control" id="supplier_id" name="supplier_id" required>
                                <option value=""><?= __('select_supplier') ?></option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>" data-currency="<?= $supplier['currency'] ?>">
                                        <?= htmlspecialchars($supplier['name']) ?> 
                                        (<?= number_format($supplier['balance'], 2) ?> <?= $supplier['currency'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="currency"><?= __('currency') ?></label>
                            <select class="form-control" id="currency" name="currency" required>
                                <option value="USD"><?= __('usd') ?></option>
                                <option value="AFS"><?= __('afs') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="total_amount"><?= __('amount') ?></label>
                            <input type="number" step="0.01" class="form-control" id="total_amount" name="total_amount" required>
                        </div>
                        <div class="form-group">
                            <label for="exchange_rate"><?= __('exchange_rate') ?></label>
                            <input type="number" step="0.00001" class="form-control" id="exchange_rate" name="exchange_rate">
                        </div>
                        <div class="form-group">
                            <label for="receipt"><?= __('receipt_number') ?></label>
                            <input type="text" class="form-control" id="receipt" name="receipt" required>
                        </div>
                        <div class="form-group">
                            <label for="remarks"><?= __('remarks') ?></label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= __('process_payment') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Client-Supplier Payment Modal -->
    <div class="modal fade" id="deleteCsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('delete_client_supplier_payment') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="process_client_supplier_jv_delete.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_cs_id">
                        <p><?= __('are_you_sure_you_want_to_delete_this_payment') ?> <?= __('this_action_will') ?>:</p>
                        <ul>
                            <li><?= __('return_funds_to_the_client_account') ?></li>
                            <li><?= __('deduct_the_amount_from_the_supplier_balance') ?></li>
                            <li><?= __('delete_all_associated_transaction_records') ?></li>
                        </ul>
                        <p class="text-danger"><strong><?= __('this_action_cannot_be_undone') ?></strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-danger"><?= __('delete') ?></button>
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
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>

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
    <script>
    $(document).ready(function() {
        // Initialize DataTable
        if ($.fn.DataTable) {
            $('#clientSupplierTable').DataTable({
                responsive: {
                    details: {
                        type: 'none', // Disable the built-in responsive details display
                        renderer: function() { return ''; } // Empty renderer
                    }
                },
                order: [[0, 'desc']],
                autoWidth: false,
                scrollX: true,
                columnDefs: [
                    { responsivePriority: 1, targets: [1, 2, 3] }, // JV Name, Client, Supplier
                    { responsivePriority: 2, targets: [4, 7] },    // Amount, Actions
                    { responsivePriority: 3, targets: [0, 5, 6] }  // Date, Currency, Receipt
                ]
            });
            
            // Add click handler for table rows on small screens
            $('#clientSupplierTable tbody').on('click', 'tr', function(e) {
                // Only trigger if we're in responsive mode and not clicking on a button
                if ($(window).width() < 768 && !$(e.target).closest('button').length) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Find the ID from the row's view button
                    const viewBtn = $(this).find('.view-cs-btn');
                    if (viewBtn.length) {
                        const paymentId = viewBtn.data('id');
                        // Trigger the view button click
                        $('.view-cs-btn[data-id="' + paymentId + '"]').trigger('click');
                    }
                }
            });
        }

        // Enhance View Client-Supplier Payment
        $('.view-cs-btn').off('click').on('click', function() {
            const jvId = $(this).data('id');
            // Show modal with loading spinner
            $('#viewCsModal').modal('show');

            $.ajax({
                url: 'get_jv_payment.php',
                type: 'GET',
                data: { id: jvId, type: 'client_supplier' },
                dataType: 'json',
                success: function(response) {
                    if (!response.success) {
                        $('#viewCsModal .modal-body').html(
                            '<div class="alert alert-danger m-3">' +
                            '<i class="feather icon-alert-triangle mr-2"></i>' +
                            'Error: ' + response.message +
                            '</div>'
                        );
                        return;
                    }
                    
                    const p = response.payment;
                    
                    // Format numbers and dates
                    const formattedAmount = parseFloat(p.total_amount).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    
                    const createdDate = new Date(p.created_at);
                    const formattedDate = createdDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    // Calculate time elapsed
                    const now = new Date();
                    const diffTime = Math.abs(now - createdDate);
                    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                    const timeElapsed = diffDays === 0 ? 'Today' : 
                                        diffDays === 1 ? 'Yesterday' : 
                                        diffDays + ' days ago';
                    
                    // Build new clean modal content
                    let html = `
                    <div class="payment-details">
                        <!-- Header with payment summary -->
                        <div class="payment-header bg-light p-4">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <span class="badge badge-primary mb-2">ID: ${p.id}</span>
                                    <h4 class="mb-1">${$('<div>').text(p.jv_name).html()}</h4>
                                    <div class="text-muted">
                                        <i class="feather icon-calendar mr-1"></i> ${formattedDate}
                                        <span class="badge badge-light ml-2">${timeElapsed}</span>
                                    </div>
                                </div>
                                <div class="col-md-5 text-md-right mt-3 mt-md-0">
                                    <h3 class="text-success mb-0">${formattedAmount} ${p.currency}</h3>
                                    <div class="text-muted small">
                                        <i class="feather icon-file-text mr-1"></i> ${p.receipt || 'No receipt'}
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Main content -->
                        <div class="p-4">
                            <!-- Transaction parties -->
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="icon-box bg-primary text-white rounded-circle p-2 mr-3">
                                                    <i class="feather icon-user"></i>
                                                </div>
                                                <h6 class="mb-0"><?= __('client') ?></h6>
                                            </div>
                                            <h5>${$('<div>').text(p.client_name || 'N/A').html()}</h5>
                                            <div class="text-muted small"><?= __('paid_from') ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="icon-box bg-success text-white rounded-circle p-2 mr-3">
                                                    <i class="feather icon-shopping-bag"></i>
                                                </div>
                                                <h6 class="mb-0"><?= __('supplier') ?></h6>
                                            </div>
                                            <h5>${$('<div>').text(p.supplier_name || 'N/A').html()}</h5>
                                            <div class="text-muted small"><?= __('paid_to') ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment details -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-light border-0">
                                    <h6 class="mb-0"><i class="feather icon-info mr-2"></i><?= __('payment_details') ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="text-muted small"><?= __('exchange_rate') ?></div>
                                            <div class="font-weight-bold">${p.exchange_rate || 'N/A'}</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-muted small"><?= __('created_by') ?></div>
                                            <div class="font-weight-bold">${p.created_by_name || 'System'}</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="text-muted small"><?= __('updated_at') ?></div>
                                            <div class="font-weight-bold">${p.updated_at ? new Date(p.updated_at).toLocaleString() : 'N/A'}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Remarks -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-light border-0">
                                    <h6 class="mb-0"><i class="feather icon-message-square mr-2"></i><?= __('remarks') ?></h6>
                                </div>
                                <div class="card-body">
                                    ${p.remarks ? 
                                      `<p class="mb-0">${$('<div>').text(p.remarks).html()}</p>` : 
                                      `<p class="text-muted font-italic mb-0"><?= __('no_remarks_provided') ?></p>`
                                    }
                                </div>
                            </div>
                        </div>
                    </div>`;
                    
                    // Update modal content
                    $('#viewCsModal .modal-body').html(html);
                    
                    // Add payment ID to modal title
                    $('#viewCsModal .modal-title').html(
                        `<i class="feather icon-credit-card mr-2"></i><?= __('payment_details') ?> <span class="badge badge-light ml-2">ID: ${p.id}</span>`
                    );
                    
                    // Show action buttons and set their click handlers
                    $('.edit-payment-btn')
                        .removeClass('d-none')
                        .off('click')
                        .on('click', function() {
                            $('#viewCsModal').modal('hide');
                            $('.edit-cs-btn[data-id="' + p.id + '"]').trigger('click');
                        });
                        
                    $('.delete-payment-btn')
                        .removeClass('d-none')
                        .off('click')
                        .on('click', function() {
                            $('#viewCsModal').modal('hide');
                            $('.delete-cs-btn[data-id="' + p.id + '"]').trigger('click');
                        });
                },
                error: function(xhr, status, error) {
                    $('#viewCsModal .modal-body').html(
                        '<div class="alert alert-danger m-3">' +
                        '<i class="feather icon-alert-triangle mr-2"></i>' +
                        '<?= __('failed_to_load_details') ?>: ' + $('<div>').text(error).html() +
                        '<br><small><?= __('please_try_again_or_contact_support_if_the_issue_persists') ?></small>' +
                        '</div>'
                    );
                }
            });
        });

        // Edit Client-Supplier Payment
        $('.edit-cs-btn').click(function() {
            const jvId = $(this).data('id');
            
            // AJAX call to get JV payment details
            $.ajax({
                url: 'get_jv_payment.php',
                type: 'GET',
                data: { id: jvId, type: 'client_supplier' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const payment = response.payment;
                        console.log("Payment data received:", payment); // Debug log
                        
                        // Check if this is a valid client-supplier payment
                        if (!payment.client_id || !payment.supplier_id) {
                            alert("<?= __('this_record_is_missing_client_or_supplier_information_and_cannot_be_edited_as_a_client_supplier_payment') ?>");
                            return;
                        }
                        
                        // Populate form fields for editing
                        $('#edit_id').val(payment.id);
                        $('#edit_jv_name').val(payment.jv_name);
                        
                        // Set client dropdown
                        if (payment.client_id) {
                            console.log("Client dropdown before:", $('#edit_client_id').val());
                            
                            // Check if the option exists, if not add it
                            if ($('#edit_client_id option[value="' + payment.client_id + '"]').length === 0) {
                                console.log("Adding client option for ID:", payment.client_id);
                                // Add the current client as an option if it doesn't exist
                                $('#edit_client_id').append(
                                    $('<option>', {
                                        value: payment.client_id,
                                        text: payment.client_name || '<?= __('client') ?> #' + payment.client_id
                                    })
                                );
                            }
                            
                            // Set the value after ensuring option exists
                            $('#edit_client_id').val(payment.client_id);
                            console.log("Client dropdown after:", $('#edit_client_id').val());
                        }
                        
                        // Set supplier dropdown
                        if (payment.supplier_id) {
                            console.log("Supplier dropdown before:", $('#edit_supplier_id').val());
                            
                            // Check if the option exists, if not add it
                            if ($('#edit_supplier_id option[value="' + payment.supplier_id + '"]').length === 0) {
                                console.log("Adding supplier option for ID:", payment.supplier_id);
                                // Add the current supplier as an option if it doesn't exist
                                $('#edit_supplier_id').append(
                                    $('<option>', {
                                        value: payment.supplier_id,
                                        text: payment.supplier_name || '<?= __('supplier') ?> #' + payment.supplier_id,
                                        'data-currency': payment.currency // Use payment currency as fallback
                                    })
                                );
                            }
                            
                            // Set the value after ensuring option exists
                            $('#edit_supplier_id').val(payment.supplier_id);
                            console.log("Supplier dropdown after:", $('#edit_supplier_id').val());
                        }
                        
                        // Set other fields
                        console.log("Setting currency to:", payment.currency);
                        $('#edit_currency').val(payment.currency);
                        
                        console.log("Setting total_amount to:", payment.total_amount);
                        $('#edit_total_amount').val(payment.total_amount);
                        
                        console.log("Setting exchange_rate to:", payment.exchange_rate);
                        $('#edit_exchange_rate').val(payment.exchange_rate);
                        
                        console.log("Setting receipt to:", payment.receipt);
                        $('#edit_receipt').val(payment.receipt);
                        
                        console.log("Setting remarks to:", payment.remarks);
                        $('#edit_remarks').val(payment.remarks);
                        
                        // Verify all fields are set
                        console.log("Final field values:");
                        console.log("- client_id:", $('#edit_client_id').val());
                        console.log("- supplier_id:", $('#edit_supplier_id').val());
                        console.log("- currency:", $('#edit_currency').val());
                        console.log("- total_amount:", $('#edit_total_amount').val());
                        console.log("- exchange_rate:", $('#edit_exchange_rate').val());
                        console.log("- receipt:", $('#edit_receipt').val());
                        console.log("- remarks:", $('#edit_remarks').val());
                        
                        // Force currency change event to ensure exchange rate visibility is correct
                        $('#edit_currency').trigger('change');
                        
                        // Update exchange rate visibility
                        updateEditExchangeRateVisibility();
                        
                        // Show the modal
                        $('#editClientSupplierModal').modal('show');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.error("Response:", xhr.responseText);
                    alert('<?= __('error') ?>: <?= __('failed_to_load_payment_details_for_editing') ?>');
                }
            });
        });

        // Delete Client-Supplier Payment
        $('.delete-cs-btn').click(function() {
            const jvId = $(this).data('id');
            $('#delete_cs_id').val(jvId);
            $('#deleteCsModal').modal('show');
        });

        // Client-Supplier form validation
        $('#clientSupplierForm').submit(function(e) {
            const clientId = $('#client_id').val();
            const supplierId = $('#supplier_id').val();
            const amount = parseFloat($('#total_amount').val());
            const currency = $('#currency').val();
            const exchangeRate = parseFloat($('#exchange_rate').val());
            
            if (!clientId || !supplierId) {
                alert('<?= __('please_select_both_client_and_supplier') ?>');
                e.preventDefault();
                return false;
            }
            
            if (isNaN(amount) || amount <= 0) {
                alert('<?= __('please_enter_a_valid_amount_greater_than_zero') ?>');
                e.preventDefault();
                return false;
            }
            
            // Check if exchange rate is needed and provided
            const selectedSupplier = $('#supplier_id').find('option:selected');
            const supplierCurrency = selectedSupplier.data('currency');
            
            if (supplierCurrency !== currency && (isNaN(exchangeRate) || exchangeRate <= 0)) {
                alert('<?= __('please_enter_a_valid_exchange_rate_for_currency_conversion') ?>');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Edit Client-Supplier form validation
        $('#editClientSupplierForm').submit(function(e) {
            const clientId = $('#edit_client_id').val();
            const supplierId = $('#edit_supplier_id').val();
            const amount = parseFloat($('#edit_total_amount').val());
            const currency = $('#edit_currency').val();
            const exchangeRate = parseFloat($('#edit_exchange_rate').val());
            
            console.log("Form validation checking:");
            console.log("- clientId:", clientId);
            console.log("- supplierId:", supplierId);
            console.log("- amount:", amount);
            console.log("- currency:", currency);
            console.log("- exchangeRate:", exchangeRate);
            
            if (!clientId || !supplierId) {
                alert('<?= __('please_select_both_client_and_supplier') ?>');
                e.preventDefault();
                return false;
            }
            
            if (isNaN(amount) || amount <= 0) {
                alert('<?= __('please_enter_a_valid_amount_greater_than_zero') ?>');
                e.preventDefault();
                return false;
            }
            
            // Check if exchange rate is needed and provided
            const selectedSupplier = $('#edit_supplier_id').find('option:selected');
            const supplierCurrency = selectedSupplier.data('currency');
            
            console.log("- supplierCurrency:", supplierCurrency);
            
            if (supplierCurrency && supplierCurrency !== currency && (isNaN(exchangeRate) || exchangeRate <= 0)) {
                alert('<?= __('please_enter_a_valid_exchange_rate_for_currency_conversion') ?>');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Handle supplier and currency change events
        $('#supplier_id').change(function() {
            const selectedOption = $(this).find('option:selected');
            const supplierCurrency = selectedOption.data('currency');
            
            // Display the supplier currency in the form
            $('#supplier_currency_info').text(supplierCurrency || '');
            
            updateExchangeRateVisibility();
        });
        
        $('#cs_currency').change(function() {
            updateExchangeRateVisibility();
        });
        
        function updateExchangeRateVisibility() {
            const selectedSupplier = $('#supplier_id').find('option:selected');
            const supplierCurrency = selectedSupplier.data('currency');
            const selectedCurrency = $('#cs_currency').val();
            
            // Show exchange rate field only if currencies are different
            if (supplierCurrency && selectedCurrency && supplierCurrency !== selectedCurrency) {
                $('#cs_exchange_rate').closest('.form-group').show();
            } else {
                $('#cs_exchange_rate').closest('.form-group').hide();
            }
        }
        
        // Handle edit form supplier and currency change events
        $('#edit_supplier_id').change(function() {
            const selectedOption = $(this).find('option:selected');
            const supplierCurrency = selectedOption.data('currency');
            
            updateEditExchangeRateVisibility();
        });
        
        $('#edit_currency').change(function() {
            updateEditExchangeRateVisibility();
        });
        
        function updateEditExchangeRateVisibility() {
            const selectedSupplier = $('#edit_supplier_id').find('option:selected');
            const supplierCurrency = selectedSupplier.data('currency');
            const selectedCurrency = $('#edit_currency').val();
            
            // Show exchange rate field only if currencies are different
            if (supplierCurrency && selectedCurrency && supplierCurrency !== selectedCurrency) {
                $('#edit_exchange_rate').closest('.form-group').show();
            } else {
                $('#edit_exchange_rate').closest('.form-group').hide();
            }
        }
    });
    </script>

    <!-- View Client-Supplier Payment Modal -->
    <div class="modal fade" id="viewCsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="feather icon-credit-card mr-2"></i><?= __('payment_details') ?></h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-0">
                    <!-- Content will be loaded dynamically via AJAX -->
                    <div class="text-center p-4">
                        <i class="fas fa-spinner fa-pulse fa-2x mb-2"></i>
                        <p><?= __('loading_details') ?>...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="feather icon-x mr-1"></i><?= __('close') ?>
                    </button>
                    <button type="button" class="btn btn-warning edit-payment-btn d-none">
                        <i class="feather icon-edit-2 mr-1"></i><?= __('edit') ?>
                    </button>
                    <button type="button" class="btn btn-danger delete-payment-btn d-none">
                        <i class="feather icon-trash-2 mr-1"></i><?= __('delete') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Client-Supplier Payment Modal -->
    <div class="modal fade" id="editClientSupplierModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('edit_client_supplier_payment') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="process_client_supplier_jv_update.php" id="editClientSupplierForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <input type="hidden" name="description" value="Client-Supplier Payment">
                        <div class="form-group">
                            <label for="edit_jv_name"><?= __('jv_name') ?></label>
                            <input type="text" class="form-control" id="edit_jv_name" name="jv_name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_client_id"><?= __('client') ?></label>
                            <select class="form-control" id="edit_client_id" name="client_id" required>
                                <option value=""><?= __('select_client') ?></option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>">
                                        <?= htmlspecialchars($client['name']) ?> 
                                        (USD: <?= number_format($client['usd_balance'], 2) ?>, 
                                        AFS: <?= number_format($client['afs_balance'], 2) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_supplier_id"><?= __('supplier') ?></label>
                            <select class="form-control" id="edit_supplier_id" name="supplier_id" required>
                                <option value=""><?= __('select_supplier') ?></option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>" data-currency="<?= $supplier['currency'] ?>">
                                        <?= htmlspecialchars($supplier['name']) ?> 
                                        (<?= number_format($supplier['balance'], 2) ?> <?= $supplier['currency'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_currency"><?= __('currency') ?></label>
                            <select class="form-control" id="edit_currency" name="currency" required>
                                <option value="USD"><?= __('usd') ?></option>
                                <option value="AFS"><?= __('afs') ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_total_amount"><?= __('amount') ?></label>
                            <input type="number" step="0.01" class="form-control" id="edit_total_amount" name="total_amount" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_exchange_rate"><?= __('exchange_rate') ?></label>
                            <input type="number" step="0.00001" class="form-control" id="edit_exchange_rate" name="exchange_rate">
                        </div>
                        <div class="form-group">
                            <label for="edit_receipt"><?= __('receipt_number') ?></label>
                            <input type="text" class="form-control" id="edit_receipt" name="receipt" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_remarks"><?= __('remarks') ?></label>
                            <textarea class="form-control" id="edit_remarks" name="remarks" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= __('cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= __('update_payment') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
</body>
    </html>     
    <!-- Delete Client-Supplier Payment Modal -->