<?php
session_start();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once 'security.php';
require_once '../includes/db.php';

// Enforce authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_SESSION['tenant_id'])) {
    header('Location: ../access_denied.php');
    exit();
}

include '../includes/header.php';
?>
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <div class="main-content">
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0"><i class="feather icon-users mr-2"></i><?php echo __('client_management'); ?></h5>
                                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;"><?php echo __('manage_clients_here'); ?></p>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <button class="btn btn-primary" data-toggle="modal" data-target="#addClientModal">
                                                <i class="fas fa-plus mr-2"></i><?php echo __('add_new_client'); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>

        <div class="row">
            <div class="col-sm-12">
                <!-- Statistics Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="feather icon-bar-chart-2 mr-2"></i><?php echo __('client_statistics'); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <h3 class="mb-1" id="totalClients">0</h3>
                                        <p class="text-muted mb-0"><?= __('total_clients') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <h3 class="mb-1" id="totalAgencies">0</h3>
                                        <p class="text-muted mb-0"><?= __('agencies') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                        <h3 class="mb-1" id="totalBalance">$0</h3>
                                        <p class="text-muted mb-0"><?= __('total_usd_balance') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                            <i class="fas fa-coins"></i>
                                        </div>
                                        <h3 class="mb-1" id="totalAfs">₳0</h3>
                                        <p class="text-muted mb-0"><?= __('total_afs_balance') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Client Management Card -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-users mr-2"></i><?php echo __('client_management'); ?></h5>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="search-box">
                            <div class="input-group">
                                <span class="input-group-text border-0 bg-transparent">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-0" id="searchClient" 
                                   placeholder="<?= __('search_clients') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-control" id="filterType">
                            <option value=""><?= __('all_types') ?></option>
                            <option value="regular"><?= __('regular') ?></option>
                            <option value="agency"><?= __('agency') ?></option>
                        </select>
                    </div>
                </div>

                <!-- Clients Tabs -->
                <ul class="nav nav-tabs mb-3" id="clientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="activeClients-tab" data-toggle="tab" href="#activeClients" role="tab">
                            <?= __('active_clients') ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="inactiveClients-tab" data-toggle="tab" href="#inactiveClients" role="tab">
                            <?= __('inactive_clients') ?>
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="clientTabContent">
                    <div class="tab-pane fade show active" id="activeClients" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="activeClientsTable">
                                <thead>
                                    <tr>
                                        <th><?= __('client') ?></th>
                                        <th><?= __('type') ?></th>
                                        <th><?= __('email') ?></th>
                                        <th><?= __('phone') ?></th>
                                        <th><?= __('usd_balance') ?></th>
                                        <th><?= __('afs_balance') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th class="text-end"><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="activeClientsTableBody">
                                    <!-- Active Client rows will be dynamically added here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="inactiveClients" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="inactiveClientsTable">
                                <thead>
                                    <tr>
                                        <th><?= __('client') ?></th>
                                        <th><?= __('type') ?></th>
                                        <th><?= __('email') ?></th>
                                        <th><?= __('phone') ?></th>
                                        <th><?= __('usd_balance') ?></th>
                                        <th><?= __('afs_balance') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th class="text-end"><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="inactiveClientsTableBody">
                                    <!-- Inactive Client rows will be dynamically added here -->
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

    <?php include '../modals/client/add_client.php'; ?>
    <?php include '../modals/client/edit_client.php'; ?>


    <style>
    /* Enhanced custom styles for better layout and design */
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 20px;
        padding: 20px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 10px;
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
        font-weight: 600;
    }

    .page-header.card .text-end {
        text-align: right;
    }

    .page-header.card .btn {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 25px;
        transition: all 0.3s ease;
    }

    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
        transform: translateY(-1px);
    }

    .card {
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px 10px 0 0;
        padding: 1rem 1.5rem;
        border: none;
    }

    .card-header h5 {
        margin: 0;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .progress {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
    }

    .progress-bar {
        transition: width 0.6s ease;
    }

    .badge {
        font-size: 0.85em;
        padding: 0.5em 0.75em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-success {
        background-color: #28a745;
    }

    .badge-warning {
        background-color: #ffc107;
        color: #212529;
    }

    .badge-info {
        background-color: #17a2b8;
    }

    .table-responsive {
        border-radius: 10px;
        overflow: hidden;
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        color: #495057;
        padding: 1rem;
    }

    .table tbody tr:hover {
        background-color: #f1f3f4;
    }

    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
    }

    .form-control {
        border-radius: 8px;
        border: 1px solid #ced4da;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        padding: 0.75rem;
    }

    .form-control:focus {
        border-color: #4099ff;
        box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
    }

    .btn-primary {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        border: none;
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(64, 153, 255, 0.3);
    }

    .btn-secondary {
        border-radius: 25px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
    }

    .alert-info {
        background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
        color: #0c5460;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
    }

    .alert-danger {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
    }

    #estimated_cost {
        color: #28a745;
        font-weight: bold;
    }

    .h2 {
        font-size: 2.5rem;
    }

    .h4 {
        font-size: 1.5rem;
    }

    .h5 {
        font-size: 1.25rem;
    }

    .h6 {
        font-size: 1rem;
    }

    /* Preloader Styles */
    .loader-bg {
        position: fixed;
        z-index: 999999;
        background: #fff;
        width: 100%;
        height: 100%;
        transition: opacity 0.3s ease-out, visibility 0.3s ease-out;
    }

    .loader-bg.fade-out {
        opacity: 0;
        visibility: hidden;
    }

    /* SweetAlert2 Custom Styles */
    .colored-toast.swal2-icon-success {
        background-color: #a5dc86 !important;
    }

    .colored-toast .swal2-title,
    .colored-toast .swal2-content {
        color: #fff !important;
    }

    .colored-toast .swal2-success {
        border-color: #fff !important;
    }

    .colored-toast .swal2-success [class^='swal2-success-line'] {
        background-color: #fff !important;
    }

    .colored-toast .swal2-success-ring {
        border-color: #fff !important;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: transform 0.3s ease;
        border: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .client-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
    }

    .search-box {
        background: white;
        border-radius: 10px;
        padding: 0.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
    }

    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        margin: 0 0.2rem;
        transition: all 0.3s ease;
    }

    .btn-action:hover {
        transform: translateY(-2px);
    }

    .badge-regular {
        background-color: #e3f2fd;
        color: #1976d2;
    }

    .badge-agency {
        background-color: #fce4ec;
        color: #c2185b;
    }

    /* Bootstrap 4 Utility Classes */
    .mr-2 {
        margin-right: 0.5rem !important;
    }

    .mb-3 {
        margin-bottom: 1rem !important;
    }

    .text-white {
        color: #fff !important;
    }

    @media (max-width: 768px) {
        .stat-card {
            margin-bottom: 1rem;
        }

        .btn-action {
            width: 28px;
            height: 28px;
        }
    }
    </style>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/client/client_management.js"></script>

<?php include '../includes/admin_footer.php'; ?>