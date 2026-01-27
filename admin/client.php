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
                                <!-- Modern Page Header -->
                                <div class="page-header-modern">
                                    <div class="header-content">
                                        <div class="header-left">
                                            <div class="icon-wrapper">
                                                <i class="feather icon-users"></i>
                                            </div>
                                            <div>
                                                <h1><?php echo __('client_management'); ?></h1>
                                                <p><?php echo __('manage_clients_here'); ?></p>
                                            </div>
                                        </div>
                                        <div class="header-right">
                                            <button class="btn-modern btn-primary" data-toggle="modal" data-target="#addClientModal">
                                                <i class="fas fa-plus"></i>
                                                <span><?php echo __('add_new_client'); ?></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Statistics Grid -->
                                <div class="stats-grid">
                                    <div class="stat-card-modern">
                                        <div class="stat-header">
                                            <div class="stat-icon-modern blue">
                                                <i class="fas fa-users"></i>
                                            </div>
                                            <span class="stat-label"><?= __('total_clients') ?></span>
                                        </div>
                                        <div class="stat-value" id="totalClients">0</div>
                                        <div class="stat-trend positive">
                                        </div>
                                    </div>

                                    <div class="stat-card-modern">
                                        <div class="stat-header">
                                            <div class="stat-icon-modern green">
                                                <i class="fas fa-building"></i>
                                            </div>
                                            <span class="stat-label"><?= __('agencies') ?></span>
                                        </div>
                                        <div class="stat-value" id="totalAgencies">0</div>
                                        <div class="stat-trend positive">
                                        </div>
                                    </div>

                                    <div class="stat-card-modern">
                                        <div class="stat-header">
                                            <div class="stat-icon-modern purple">
                                                <i class="fas fa-dollar-sign"></i>
                                            </div>
                                            <span class="stat-label"><?= __('total_usd_balance') ?></span>
                                        </div>
                                        <div class="stat-value" id="totalBalance">$0</div>
                                        <div class="stat-trend positive">
                                        </div>
                                    </div>

                                    <div class="stat-card-modern">
                                        <div class="stat-header">
                                            <div class="stat-icon-modern orange">
                                                <i class="fas fa-coins"></i>
                                            </div>
                                            <span class="stat-label"><?= __('total_afs_balance') ?></span>
                                        </div>
                                        <div class="stat-value" id="totalAfs">0 AFN</div>
                                        <div class="stat-trend positive">
                                        </div>
                                    </div>
                                </div>

                                <!-- Client Management Card -->
                                <div class="card-modern">
                                    <div class="card-modern-header">
                                        <h2><?php echo __('client_management'); ?></h2>
                                    </div>
                                    <div class="card-modern-body">
                                        <!-- Search and Filter -->
                                        <div class="controls-row">
                                            <div class="search-wrapper">
                                                <i class="fas fa-search"></i>
                                                <input type="text" class="input-modern" id="searchClient" 
                                                   placeholder="<?= __('search_clients') ?>">
                                            </div>
                                            <div class="filter-wrapper">
                                                <select class="select-modern" id="filterType">
                                                    <option value=""><?= __('all_types') ?></option>
                                                    <option value="regular"><?= __('regular') ?></option>
                                                    <option value="agency"><?= __('agency') ?></option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Clients Tabs -->
                                        <div class="tabs-modern">
                                            <button class="tab-modern active" data-tab="activeClients">
                                                <?= __('active_clients') ?>
                                            </button>
                                            <button class="tab-modern" data-tab="inactiveClients">
                                                <?= __('inactive_clients') ?>
                                            </button>
                                        </div>

                                        <!-- Active Clients Table -->
                                        <div class="tab-content-modern active" id="activeClients">
                                            <div class="table-wrapper">
                                                <table class="table-modern">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('client') ?></th>
                                                            <th><?= __('type') ?></th>
                                                            <th><?= __('email') ?></th>
                                                            <th><?= __('phone') ?></th>
                                                            <th class="text-right"><?= __('usd_balance') ?></th>
                                                            <th class="text-right"><?= __('afs_balance') ?></th>
                                                            <th><?= __('status') ?></th>
                                                            <th class="text-right"><?= __('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="activeClientsTableBody">
                                                        <!-- Rows will be dynamically added -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Inactive Clients Table -->
                                        <div class="tab-content-modern" id="inactiveClients">
                                            <div class="table-wrapper">
                                                <table class="table-modern">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('client') ?></th>
                                                            <th><?= __('type') ?></th>
                                                            <th><?= __('email') ?></th>
                                                            <th><?= __('phone') ?></th>
                                                            <th class="text-right"><?= __('usd_balance') ?></th>
                                                            <th class="text-right"><?= __('afs_balance') ?></th>
                                                            <th><?= __('status') ?></th>
                                                            <th class="text-right"><?= __('actions') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="inactiveClientsTableBody">
                                                        <!-- Rows will be dynamically added -->
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
    /* Modern Design System */
    :root {
        --primary: #3B82F6;
        --primary-hover: #2563EB;
        --success: #10B981;
        --warning: #F59E0B;
        --danger: #EF4444;
        --gray-50: #F9FAFB;
        --gray-100: #F3F4F6;
        --gray-200: #E5E7EB;
        --gray-300: #D1D5DB;
        --gray-400: #9CA3AF;
        --gray-500: #6B7280;
        --gray-600: #4B5563;
        --gray-700: #374151;
        --gray-800: #1F2937;
        --gray-900: #111827;
        --border-radius: 12px;
        --border-radius-sm: 8px;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
        box-sizing: border-box;
    }

    body {
        background-color: var(--gray-50);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        color: var(--gray-900);
        line-height: 1.6;
    }

    /* Page Header */
    .page-header-modern {
        background: white;
        border-radius: var(--border-radius);
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: var(--shadow);
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .icon-wrapper {
        width: 48px;
        height: 48px;
        background: linear-gradient(135deg, var(--primary) 0%, #8B5CF6 100%);
        border-radius: var(--border-radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }

    .header-left h1 {
        font-size: 24px;
        font-weight: 600;
        color: var(--gray-900);
        margin: 0;
        line-height: 1.2;
    }

    .header-left p {
        font-size: 14px;
        color: var(--gray-600);
        margin: 4px 0 0 0;
    }

    /* Modern Button */
    .btn-modern {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border: none;
        border-radius: var(--border-radius-sm);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        white-space: nowrap;
    }

    .btn-primary {
        background: var(--primary);
        color: white;
    }

    .btn-primary:hover {
        background: var(--primary-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    /* Statistics Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }

    .stat-card-modern {
        background: white;
        border-radius: var(--border-radius);
        padding: 20px;
        box-shadow: var(--shadow);
        transition: var(--transition);
    }

    .stat-card-modern:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .stat-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .stat-icon-modern {
        width: 40px;
        height: 40px;
        border-radius: var(--border-radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .stat-icon-modern.blue {
        background: #EFF6FF;
        color: var(--primary);
    }

    .stat-icon-modern.green {
        background: #F0FDF4;
        color: var(--success);
    }

    .stat-icon-modern.purple {
        background: #FAF5FF;
        color: #8B5CF6;
    }

    .stat-icon-modern.orange {
        background: #FFF7ED;
        color: var(--warning);
    }

    .stat-label {
        font-size: 13px;
        font-weight: 500;
        color: var(--gray-600);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 8px;
        line-height: 1;
    }

    .stat-trend {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 500;
    }

    .stat-trend.positive {
        color: var(--success);
    }

    .stat-trend i {
        font-size: 12px;
    }

    /* Modern Card */
    .card-modern {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .card-modern-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--gray-200);
    }

    .card-modern-header h2 {
        font-size: 18px;
        font-weight: 600;
        color: var(--gray-900);
        margin: 0;
    }

    .card-modern-body {
        padding: 24px;
    }

    /* Controls Row */
    .controls-row {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
    }

    .search-wrapper {
        flex: 1;
        position: relative;
    }

    .search-wrapper i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        font-size: 14px;
    }

    .input-modern {
        width: 100%;
        padding: 10px 14px 10px 40px;
        border: 1px solid var(--gray-300);
        border-radius: var(--border-radius-sm);
        font-size: 14px;
        transition: var(--transition);
        background: white;
    }

    .input-modern:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .filter-wrapper {
        min-width: 200px;
    }

    .select-modern {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid var(--gray-300);
        border-radius: var(--border-radius-sm);
        font-size: 14px;
        background: white;
        cursor: pointer;
        transition: var(--transition);
    }

    .select-modern:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Tabs */
    .tabs-modern {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--gray-200);
    }

    .tab-modern {
        padding: 12px 20px;
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        color: var(--gray-600);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
    }

    .tab-modern:hover {
        color: var(--gray-900);
    }

    .tab-modern.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    .tab-content-modern {
        display: none;
    }

    .tab-content-modern.active {
        display: block;
    }

    /* Modern Table */
    .table-wrapper {
        overflow-x: auto;
        border-radius: var(--border-radius-sm);
        border: 1px solid var(--gray-200);
    }

    .table-modern {
        width: 100%;
        border-collapse: collapse;
    }

    .table-modern thead {
        background: var(--gray-50);
    }

    .table-modern th {
        padding: 12px 16px;
        text-align: left;
        font-size: 12px;
        font-weight: 600;
        color: var(--gray-700);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--gray-200);
    }

    .table-modern td {
        padding: 16px;
        font-size: 14px;
        color: var(--gray-900);
        border-bottom: 1px solid var(--gray-200);
    }

    .table-modern tbody tr {
        transition: var(--transition);
    }

    .table-modern tbody tr:hover {
        background: var(--gray-50);
    }

    .table-modern tbody tr:last-child td {
        border-bottom: none;
    }

    .text-right {
        text-align: right;
    }

    /* Badges */
    .badge-modern {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 500;
    }

    .badge-regular {
        background: #EFF6FF;
        color: var(--primary);
    }

    .badge-agency {
        background: #FDF2F8;
        color: #DB2777;
    }

    .badge-active {
        background: #F0FDF4;
        color: var(--success);
    }

    .badge-inactive {
        background: var(--gray-100);
        color: var(--gray-600);
    }

    /* Action Buttons */
    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        border: none;
        border-radius: var(--border-radius-sm);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
        margin: 0 2px;
    }

    .btn-action.btn-view {
        background: #EFF6FF;
        color: var(--primary);
    }

    .btn-action.btn-view:hover {
        background: var(--primary);
        color: white;
    }

    .btn-action.btn-edit {
        background: #FFF7ED;
        color: var(--warning);
    }

    .btn-action.btn-edit:hover {
        background: var(--warning);
        color: white;
    }

    .btn-action.btn-delete {
        background: #FEF2F2;
        color: var(--danger);
    }

    .btn-action.btn-delete:hover {
        background: var(--danger);
        color: white;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .header-content {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-right {
            width: 100%;
        }

        .btn-modern {
            width: 100%;
            justify-content: center;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .controls-row {
            flex-direction: column;
        }

        .filter-wrapper {
            min-width: auto;
        }

        .stat-value {
            font-size: 28px;
        }
    }

    /* Utilities */
    .mb-0 { margin-bottom: 0; }
    .mt-1 { margin-top: 0.25rem; }
    .mr-2 { margin-right: 0.5rem; }
</style>

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/client/client_management.js"></script>

<script>
// Client type translations
const clientTypeTranslations = {
    'regular': '<?= __("regular") ?>',
    'agency': '<?= __("agency") ?>'
};

// Modern tab switching
document.querySelectorAll('.tab-modern').forEach(tab => {
    tab.addEventListener('click', function() {
        const targetId = this.dataset.tab;

        // Update tabs
        document.querySelectorAll('.tab-modern').forEach(t => t.classList.remove('active'));
        this.classList.add('active');

        // Update content
        document.querySelectorAll('.tab-content-modern').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(targetId).classList.add('active');
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>