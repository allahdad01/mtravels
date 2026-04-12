<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance', 'sales', 'umrah'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
?>

<?php include '../includes/header.php'; ?>
<script src="../assets/plugins/jquery/js/jquery.min.js"></script>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/umrah/umrah-enhanced.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Status badges - specific to date changes */
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
                <!-- Enhanced Page Header -->
                <div class="enhanced-page-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-title-wrapper">
                                    <i class="fas fa-calendar-check page-icon"></i>
                                    <div>
                                        <h2 class="page-title"><?= __('umrah_date_changes') ?></h2>
                                        <p class="page-subtitle"><?= __('manage_date_change_requests') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-gradient-primary" onclick="location.reload()">
                                    <i class="fas fa-sync mr-2"></i><?= __('refresh') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Filters and Search -->
                <div class="container-fluid px-4 mb-4">
                    <div class="filters-wrapper">
                        <!-- Filter Pills -->
                        <div class="filter-pills">
                            <a href="javascript:void(0)" onclick="filterByStatus('all')" class="filter-pill active" id="filter-all">
                                <i class="fas fa-layer-group"></i>
                                <span><?= __('all') ?></span>
                                <span class="pill-badge" id="all-count">0</span>
                            </a>
                            <a href="javascript:void(0)" onclick="filterByStatus('Pending')" class="filter-pill" id="filter-pending">
                                <i class="fas fa-clock"></i>
                                <span><?= __('pending') ?></span>
                                <span class="pill-badge" id="pending-count">0</span>
                            </a>
                            <a href="javascript:void(0)" onclick="filterByStatus('Approved')" class="filter-pill" id="filter-approved">
                                <i class="fas fa-check-circle"></i>
                                <span><?= __('approved') ?></span>
                                <span class="pill-badge" id="approved-count">0</span>
                            </a>
                            <a href="javascript:void(0)" onclick="filterByStatus('Rejected')" class="filter-pill" id="filter-rejected">
                                <i class="fas fa-times-circle"></i>
                                <span><?= __('rejected') ?></span>
                                <span class="pill-badge" id="rejected-count">0</span>
                            </a>
                            <a href="javascript:void(0)" onclick="filterByStatus('Completed')" class="filter-pill" id="filter-completed">
                                <i class="fas fa-check"></i>
                                <span><?= __('completed') ?></span>
                                <span class="pill-badge" id="completed-count">0</span>
                            </a>
                        </div>

                        <!-- Enhanced Search -->
                        <div class="search-wrapper">
                            <div class="search-form">
                                <div class="search-input-group">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="search" 
                                           id="searchInput"
                                           placeholder="<?= __('search_by_passenger_or_family') ?>"
                                           class="search-input">
                                    <button type="button" class="search-button" onclick="searchDateChanges()">
                                        <?= __('search') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Date Change Requests Table -->
                <div class="container-fluid px-4">
                    <!-- Requests Table -->
                    <div class="table-responsive card">
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
                        <i class="fas fa-spinner fa-spin"></i> <?= __('loading') ?>...
                    </div>

                    <!-- No data message -->
                    <div id="noDataMessage" class="text-center py-4 d-none empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <h3 class="text-muted mt-3"><?= __('no_date_change_requests') ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>


<?php include '../modals/umrah_date_change/date_change_modal.php'; ?>
<?php include '../modals/umrah_date_change/penalty_modal.php'; ?>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<script src="../js/umrah_date_change/date_change.js"></script>

<!-- Custom Scripts -->
<script>
    // Set CSRF token
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

    // Toast notification
    function showToast(type, message) {
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        
        Toast.fire({
            icon: type,
            title: message
        });
    }
</script>
</body>
</html>