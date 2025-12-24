<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';
// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

/* Status badges */
.status-badge {
    font-size: 0.85em;
    padding: 0.25em 0.5em;
}

.status-Pending {
    background-color: #fff3cd;
    color: #856404;
}

.status-Approved {
    background-color: #d1ecf1;
    color: #0c5460;
}

.status-Rejected {
    background-color: #f8d7da;
    color: #721c24;
}

.status-Completed {
    background-color: #d4edda;
    color: #155724;
}

/* Fix SweetAlert2 z-index to appear above Bootstrap modals */
.swal2-container {
    z-index: 1200 !important;
}



/* Ensure SweetAlert2 inputs are focusable and interactive */
.swal2-container input,
.swal2-container textarea,
.swal2-container select {
    pointer-events: auto !important;
    z-index: 1201 !important;
}

.swal2-container .form-group {
    margin-bottom: 1rem;
}

.swal2-container label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #495057;
}
</style>

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
                                    <h5 class="m-b-10"><?= __('umrah_date_changes') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="umrah.php"><i class="feather icon-users"></i></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:"><?= __('date_changes') ?></a></li>
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
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="feather icon-calendar mr-2"></i><?= __('date_change_requests') ?></h5>
                                        <div class="card-header-right">
                                            <button class="btn btn-sm btn-light" onclick="location.reload()">
                                                <i class="feather icon-refresh-cw"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <!-- Status Filter Tabs -->
                                        <div class="row mb-4">
                                            <div class="col-md-12">
                                                <ul class="nav nav-tabs" id="statusTabs" role="tablist">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" id="all-tab" data-toggle="tab" href="#all" role="tab">
                                                            <?= __('all') ?> <span class="badge-light" id="all-count">0</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="pending-tab" data-toggle="tab" href="#pending" role="tab">
                                                            <?= __('pending') ?> <span class="badge-warning" id="pending-count">0</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="approved-tab" data-toggle="tab" href="#approved" role="tab">
                                                            <?= __('approved') ?> <span class="badge-info" id="approved-count">0</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a class="nav-link" id="completed-tab" data-toggle="tab" href="#completed" role="tab">
                                                            <?= __('completed') ?> <span class="badge-success" id="completed-count">0</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Requests Table -->
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="dateChangesTable">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th><?= __('request_id') ?></th>
                                                        <th><?= __('passenger_name') ?></th>
                                                        <th><?= __('family') ?></th>
                                                        <th><?= __('current_dates') ?></th>
                                                        <th><?= __('requested_dates') ?></th>
                                                        <th><?= __('price_change') ?></th>
                                                        <th><?= __('status') ?></th>
                                                        <th><?= __('requested_on') ?></th>
                                                        <th><?= __('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="dateChangesTableBody">
                                                    <!-- Data will be loaded here -->
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Loading indicator -->
                                        <div id="loadingIndicator" class="text-center py-4">
                                            <i class="feather icon-loader spinning"></i> <?= __('loading') ?>...
                                        </div>

                                        <!-- No data message -->
                                        <div id="noDataMessage" class="text-center py-4 d-none">
                                            <i class="feather icon-calendar text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="text-muted mt-3"><?= __('no_date_change_requests') ?></h5>
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






<?php include '../modals/umrah_date_change/date_change_modal.php'; ?>
<?php include '../modals/umrah_date_change/penalty_modal.php'; ?>


<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>


<script src="../js/umrah_date_change/date_change.js"></script>
<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>