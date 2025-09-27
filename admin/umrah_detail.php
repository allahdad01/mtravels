<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Initialize variables
$umrahId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$umrahData = null;
$clientTransactions = [];
$supplierTransactions = [];
$mainAccountTransactions = [];
$error = null;

// Check if ID is provided
if (!$umrahId) {
    $error = "No Umrah booking ID provided";
} else {
    // Get Umrah booking details with related info
    $umrahQuery = "SELECT 
            ub.*,
            c.name AS client_name,
            c.email AS client_email,
            c.phone AS client_phone,
            s.name AS supplier_name,
            s.email AS supplier_email,
            s.phone AS supplier_phone,
            f.head_of_family AS family_name
        FROM umrah_bookings ub
        LEFT JOIN clients c ON ub.sold_to = c.id
        LEFT JOIN suppliers s ON ub.supplier = s.id
        LEFT JOIN families f ON ub.family_id = f.family_id
        WHERE ub.booking_id = ? AND ub.tenant_id = ?";
        
    $stmt = $pdo->prepare($umrahQuery);
    $stmt->execute([$umrahId, $tenant_id]);
    $umrahData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$umrahData) {
        $error = "Umrah booking not found";
    } else {
        // Get client transactions related to this booking
        $clientTransQuery = "SELECT 
                'Client' AS transaction_type,
                ct.id,
                ct.type,
                ct.amount,
                ct.currency,
                ct.description,
                ct.transaction_of,
                ct.created_at AS transaction_date
            FROM client_transactions ct
            WHERE ct.reference_id = ? AND ct.transaction_of = 'umrah' AND ct.tenant_id = ?
            ORDER BY ct.created_at DESC";
            
        $stmt = $pdo->prepare($clientTransQuery);
        $stmt->execute([$umrahId, $tenant_id]);
        $clientTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get supplier transactions related to this booking
        $supplierTransQuery = "SELECT 
                'Supplier' AS transaction_type,
                st.id,
                st.transaction_type as type,
                st.amount,
                null as currency,
                st.remarks as description,
                st.transaction_of,
                st.transaction_date
            FROM supplier_transactions st
            WHERE st.reference_id = ? AND st.transaction_of = 'umrah' AND st.tenant_id = ?
            ORDER BY st.transaction_date DESC";
            
        $stmt = $pdo->prepare($supplierTransQuery);
        $stmt->execute([$umrahId, $tenant_id]);
        $supplierTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get main account transactions related to this booking
        $mainAccountTransQuery = "SELECT 
                'Main Account' AS transaction_type,
                mat.id,
                mat.type,
                mat.amount,
                mat.currency,
                mat.description,
                mat.transaction_of,
                mat.created_at AS transaction_date
            FROM main_account_transactions mat
            WHERE mat.reference_id = ? AND mat.transaction_of = 'umrah' AND mat.tenant_id = ?
            ORDER BY mat.created_at DESC";
            
        $stmt = $pdo->prepare($mainAccountTransQuery);
        $stmt->execute([$umrahId, $tenant_id]);
        $mainAccountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
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
                            <h5 class="m-b-10"><?= __('umrah_booking_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="search.php"><?= __('search') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('umrah_booking_details') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                    <a href="search.php" class="btn btn-primary"><?= __('back_to_search') ?></a>
                <?php else: ?>
                    <!-- Umrah Booking Information Card -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="feather icon-user mr-2"></i>
                                <?= __('umrah_booking_information') ?>
                                <span class="float-right">
                                    
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th><?= __('pilgrim_name') ?></th>
                                            <td><?php echo htmlspecialchars($umrahData['name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('passport_number') ?></th>
                                            <td><?php echo htmlspecialchars($umrahData['passport_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('date_of_birth') ?></th>
                                            <td><?php echo htmlspecialchars($umrahData['dob']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('family_name') ?></th>
                                            <td><?php echo htmlspecialchars($umrahData['family_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('flight_date') ?></th>
                                            <td><?php echo date('Y-m-d', strtotime($umrahData['flight_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('return_date') ?></th>
                                            <td><?php echo date('Y-m-d', strtotime($umrahData['return_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('duration') ?></th>
                                            <td><?php echo htmlspecialchars($umrahData['duration']); ?> days</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th><?= __('room_type') ?></th>
                                            <td><?php echo htmlspecialchars($umrahData['room_type']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('cost_price') ?></th>
                                            <td><?php echo htmlspecialchars($umrahData['currency']) . ' ' . htmlspecialchars($umrahData['price']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('sold_price') ?></th>
                                            <td><strong><?php echo htmlspecialchars($umrahData['currency']) . ' ' . htmlspecialchars($umrahData['sold_price']); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('profit') ?></th>
                                            <td class="<?php echo ($umrahData['profit'] > 0) ? 'text-success' : 'text-danger'; ?>">
                                                <strong><?php echo htmlspecialchars($umrahData['currency']) . ' ' . htmlspecialchars($umrahData['profit']); ?></strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('bank_payment_received') ?></th>
                                            <td>
                                                <?php if ($umrahData['received_bank_payment']): ?>
                                                    <span class="text-success"><?= __('yes') ?></span>
                                                    <?php if (!empty($umrahData['bank_receipt_number'])): ?>
                                                        (<?= __('receipt') ?>: <?php echo htmlspecialchars($umrahData['bank_receipt_number']); ?>)
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-danger"><?= __('no') ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?= __('amount_paid') ?></th>
                                            <td><?php echo htmlspecialchars($umrahData['currency']) . ' ' . htmlspecialchars($umrahData['paid']); ?></td>
                                        </tr>
                                        <tr>
                                            <th><?= __('amount_due') ?></th>
                                            <td>
                                                <span class="<?php echo ($umrahData['due'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo htmlspecialchars($umrahData['currency']) . ' ' . htmlspecialchars($umrahData['due']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Client & Supplier Information -->
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                                <h6 class="card-title"><i class="feather icon-user mr-2"></i><?= __('client_information') ?></h6>
                                            <?php if (!empty($umrahData['sold_to'])): ?>
                                                <p><strong><?= __('name') ?>:</strong> <?php echo htmlspecialchars($umrahData['client_name']); ?></p>
                                                <p><strong><?= __('email') ?>:</strong> <?php echo htmlspecialchars($umrahData['client_email']); ?></p>
                                                <p><strong><?= __('phone') ?>:</strong> <?php echo htmlspecialchars($umrahData['client_phone']); ?></p>
                                                <a href="client_detail.php?id=<?php echo h($umrahData['sold_to']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="feather icon-external-link mr-1"></i> <?= __('view_client_details') ?>
                                                </a>
                                            <?php else: ?>
                                                <p class="text-muted"><?= __('no_client_associated_with_this_booking') ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="feather icon-briefcase mr-2"></i><?= __('supplier_information') ?></h6>
                                            <?php if (!empty($umrahData['supplier'])): ?>
                                                <p><strong><?= __('name') ?>:</strong> <?php echo htmlspecialchars($umrahData['supplier_name']); ?></p>
                                                <p><strong><?= __('email') ?>:</strong> <?php echo htmlspecialchars($umrahData['supplier_email']); ?></p>
                                                <p><strong><?= __('phone') ?>:</strong> <?php echo htmlspecialchars($umrahData['supplier_phone']); ?></p>
                                                <a href="supplier_detail.php?id=<?php echo h($umrahData['supplier']); ?>" class="btn btn-sm btn-primary">
                                                    <i class="feather icon-external-link mr-1"></i> <?= __('view_supplier_details') ?>
                                                </a>
                                            <?php else: ?>
                                                <p class="text-muted"><?= __('no_supplier_associated_with_this_booking') ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($umrahData['entry_date'])): ?>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="feather icon-calendar mr-2"></i><?= __('entry_date') ?></h6>
                                            <p class="card-text"><?php echo date('Y-m-d', strtotime($umrahData['entry_date'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Transactions History -->
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="feather icon-activity mr-2"></i><?= __('transaction_history') ?></h5>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs mb-3" id="transactionTab" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="client-tab" data-toggle="tab" href="#client" role="tab"><?= __('client_transactions') ?></a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="supplier-tab" data-toggle="tab" href="#supplier" role="tab"><?= __('supplier_transactions') ?></a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="main-account-tab" data-toggle="tab" href="#main-account" role="tab"><?= __('main_account_transactions') ?></a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="all-tab" data-toggle="tab" href="#all" role="tab"><?= __('all_transactions') ?></a>
                                </li>
                            </ul>
                            <div class="tab-content" id="transactionTabContent">
                                <!-- Client Transactions -->
                                <div class="tab-pane fade show active" id="client" role="tabpanel">
                                    <?php if (!empty($clientTransactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= __('date') ?></th>
                                                    <th><?= __('transaction_type') ?></th>
                                                    <th><?= __('type') ?></th>
                                                    <th><?= __('amount') ?></th>
                                                    <th><?= __('description') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($clientTransactions as $transaction): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-info"><?= __('umrah') ?></span>
                                                    </td>
                                                    <td><?php echo ucfirst(strtolower($transaction['type'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo (strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars($transaction['amount']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_client_transactions_found_for_this_booking') ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Supplier Transactions -->
                                <div class="tab-pane fade" id="supplier" role="tabpanel">
                                    <?php if (!empty($supplierTransactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= __('date') ?></th>
                                                    <th><?= __('transaction_type') ?></th>
                                                    <th><?= __('type') ?></th>
                                                    <th><?= __('amount') ?></th>
                                                    <th><?= __('description') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($supplierTransactions as $transaction): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-info"><?= __('umrah') ?></span>
                                                    </td>
                                                    <td><?php echo ucfirst(strtolower($transaction['type'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo (strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo htmlspecialchars($transaction['amount']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_supplier_transactions_found_for_this_booking') ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Main Account Transactions -->
                                <div class="tab-pane fade" id="main-account" role="tabpanel">
                                    <?php if (!empty($mainAccountTransactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= __('date') ?></th>
                                                    <th><?= __('transaction_type') ?></th>
                                                    <th><?= __('type') ?></th>
                                                    <th><?= __('amount') ?></th>
                                                    <th><?= __('description') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($mainAccountTransactions as $transaction): ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-info"><?= __('umrah') ?></span>
                                                    </td>
                                                    <td><?php echo ucfirst(strtolower($transaction['type'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo (strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo htmlspecialchars($transaction['currency']) . ' ' . htmlspecialchars($transaction['amount']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_main_account_transactions_found_for_this_booking') ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- All Transactions -->
                                <div class="tab-pane fade" id="all" role="tabpanel">
                                    <?php if (!empty($clientTransactions) || !empty($supplierTransactions) || !empty($mainAccountTransactions)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= __('date') ?></th>
                                                    <th><?= __('type') ?></th>
                                                    <th><?= __('party') ?></th>
                                                    <th><?= __('transaction') ?></th>
                                                    <th><?= __('amount') ?></th>
                                                    <th><?= __('description') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $allTransactions = array_merge($clientTransactions, $supplierTransactions, $mainAccountTransactions);
                                                
                                                // Sort by date, most recent first
                                                usort($allTransactions, function($a, $b) {
                                                    return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
                                                });
                                                
                                                foreach ($allTransactions as $transaction): 
                                                ?>
                                                <tr>
                                                    <td><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-info">Umrah</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?php 
                                                            if ($transaction['transaction_type'] == 'Client') echo 'primary';
                                                            elseif ($transaction['transaction_type'] == 'Supplier') echo 'warning';
                                                            elseif ($transaction['transaction_type'] == 'Main Account') echo 'info';
                                                            else echo 'secondary';
                                                        ?>">
                                                            <?php echo h($transaction['transaction_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo ucfirst(strtolower($transaction['type'])); ?></td>
                                                    <td>
                                                        <span class="<?php echo (strtolower($transaction['type']) == 'debit') ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo isset($transaction['currency']) ? htmlspecialchars($transaction['currency']) . ' ' : ''; ?>
                                                            <?php echo htmlspecialchars($transaction['amount']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info"><?= __('no_transactions_found_for_this_booking') ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <a href="search.php" class="btn btn-secondary">
                                <i class="feather icon-arrow-left mr-1"></i> <?= __('back_to_search') ?>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Bootstrap Tab Functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check preloader status and force hide if needed
    console.log('Checking preloader status...');
    var preloader = document.querySelector('.loader-bg');
    if (preloader) {
        console.log('Preloader found, forcing hide...');
        preloader.style.display = 'none';
    } else {
        console.log('Preloader not found in DOM');
    }
    
    // Initialize tab functionality
    var tabLinks = document.querySelectorAll('.nav-tabs .nav-link');
    
    tabLinks.forEach(function(tabLink) {
        tabLink.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs and panes
            document.querySelectorAll('.nav-tabs .nav-link').forEach(function(link) {
                link.classList.remove('active');
            });
            document.querySelectorAll('.tab-pane').forEach(function(pane) {
                pane.classList.remove('show', 'active');
            });
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Get the target pane id from href attribute
            var targetId = this.getAttribute('href').substring(1);
            var targetPane = document.getElementById(targetId);
            
            // Show the target pane
            if (targetPane) {
                targetPane.classList.add('show', 'active');
            }
        });
    });
    
    // Activate the first tab by default
    if (tabLinks.length > 0) {
        tabLinks[0].classList.add('active');
        var firstPaneId = tabLinks[0].getAttribute('href').substring(1);
        var firstPane = document.getElementById(firstPaneId);
        if (firstPane) {
            firstPane.classList.add('show', 'active');
        }
    }
    
    // Fix sidebar scrolling
    if (typeof jQuery !== 'undefined' && jQuery.fn.slimScroll) {
        // Reinitialize sidebar scrolling
        if (!$(".pcoded-navbar").hasClass("theme-horizontal")) {
            var windowWidth = $(window)[0].innerWidth;
            if (windowWidth < 992 || $(".pcoded-navbar").hasClass("menupos-static")) {
                $(".navbar-content").slimScroll({
                    setTop: "1px",
                    size: "5px",
                    wheelStep: 10,
                    touchScrollStep: 90,
                    alwaysVisible: false,
                    allowPageScroll: true,
                    color: "rgba(0,0,0,0.5)",
                    height: "calc(100% - 70px)",
                    width: "100%"
                });
            } else {
                $(".navbar-content").slimScroll({
                    setTop: "1px",
                    size: "5px",
                    wheelStep: 10,
                    touchScrollStep: 90,
                    alwaysVisible: false,
                    allowPageScroll: true,
                    color: "rgba(0,0,0,0.5)",
                    height: "calc(100vh - 70px)",
                    width: "100%"
                });
            }
        }
    }
});
</script>

<?php
include '../includes/admin_footer.php';
?> 

