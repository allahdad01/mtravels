<?php
/**
 * Finance, Payables & Reports — Phases 26-28 (umrah_plan.md)
 * Tab 1 Finance   : member profitability (selling - supplier cost = gross profit,
 *                   paid/due) + service profitability per service type.
 * Tab 2 Payables  : supplier payables derived from actual fulfilled services.
 * Tab 3 Reports   : hotel report (rooms, reservations, occupancy, contract
 *                   utilization) + outstanding payments.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/language_helpers.php';

enforce_auth(['admin', 'finance', 'umrah', 'sales', 'staff', 'operations', 'hotel_manager', 'viewer']);
umrah_require('finance_view', 'page');

require_once '../includes/db.php';

// Cache-busting versions for the combined CSS/JS bundles
$umrahCssVersion = 0;
$umrahJsVersion = 0;
foreach (require '../css/bundle_files.php' as $bundleFile) {
    $bundleMtime = @filemtime('../css/' . $bundleFile);
    if ($bundleMtime > $umrahCssVersion) { $umrahCssVersion = $bundleMtime; }
}
foreach (require '../js/umrah/bundle_files.php' as $bundleFile) {
    $bundleMtime = @filemtime('../js/' . $bundleFile);
    if ($bundleMtime > $umrahJsVersion) { $umrahJsVersion = $bundleMtime; }
}
?>
<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="../css/bundle.php?v=<?= $umrahCssVersion ?>">
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="main-body">
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center mb-3">
            <div>
                <h5 class="mb-1" style="font-weight: 700;">
                    <i class="feather icon-bar-chart-2 mr-2" style="color: #0e7490;"></i><?= __('finance_management') ?>
                </h5>
                <p class="text-muted mb-0" style="font-size: 0.85rem;"><?= __('finance_management_hint') ?></p>
            </div>
            <div class="ml-auto">
                <button class="btn btn-sm btn-outline-secondary" type="button" id="btnRefreshFinance">
                    <i class="feather icon-refresh-cw mr-1"></i><?= __('refresh') ?>
                </button>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" id="financeTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" id="tab-finance" data-toggle="tab" href="#pane-finance" role="tab"><i class="feather icon-dollar-sign mr-1"></i><?= __('finance') ?></a></li>
            <li class="nav-item"><a class="nav-link" id="tab-payables" data-toggle="tab" href="#pane-payables" role="tab"><i class="feather icon-truck mr-1"></i><?= __('payables') ?></a></li>
            <li class="nav-item"><a class="nav-link" id="tab-reports" data-toggle="tab" href="#pane-reports" role="tab"><i class="feather icon-pie-chart mr-1"></i><?= __('reports') ?></a></li>
        </ul>

        <div class="tab-content" id="financeTabContent">

            <!-- ── Finance ───────────────────────────────────────────────── -->
            <div class="tab-pane fade show active" id="pane-finance" role="tabpanel">
                <div class="row" id="financeStats"></div>
                <div class="card mt-2">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="feather icon-users mr-2" style="color: #0e7490;"></i><?= __('member_profitability') ?></h6>
                    </div>
                    <div class="card-body p-0" id="memberProfitTable">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="feather icon-grid mr-2" style="color: #0e7490;"></i><?= __('service_profitability') ?></h6>
                    </div>
                    <div class="card-body p-0" id="serviceProfitTable">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
            </div>

            <!-- ── Payables ──────────────────────────────────────────────── -->
            <div class="tab-pane fade" id="pane-payables" role="tabpanel">
                <div class="row" id="payablesStats"></div>
                <div class="card mt-2">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="feather icon-truck mr-2" style="color: #0e7490;"></i><?= __('supplier_payables') ?></h6>
                    </div>
                    <div class="card-body p-0" id="supplierPayableTable">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
            </div>

            <!-- ── Reports ───────────────────────────────────────────────── -->
            <div class="tab-pane fade" id="pane-reports" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="feather icon-home mr-2" style="color: #0e7490;"></i><?= __('hotel_report') ?></h6>
                    </div>
                    <div class="card-body p-0" id="hotelReportTable">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="feather icon-alert-circle mr-2" style="color: #0e7490;"></i><?= __('outstanding_payments') ?></h6>
                    </div>
                    <div class="card-body p-0" id="outstandingTable">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</div>
</div>
</div>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="../js/umrah/bundle.php?v=<?= $umrahJsVersion ?>"></script>
<script>
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    window.financeLabels = <?= json_encode([
        'loading' => __('loading'),
        'load_failed' => __('load_failed'),
        'no_data' => __('no_data'),
        'total_selling' => __('total_selling'),
        'total_cost' => __('total_cost'),
        'gross_profit' => __('gross_profit'),
        'margin' => __('margin'),
        'total_paid' => __('total_paid'),
        'total_due' => __('total_due'),
        'member' => __('member'),
        'flight_date' => __('flight_date'),
        'currency' => __('currency'),
        'selling' => __('selling'),
        'cost' => __('cost'),
        'profit' => __('profit'),
        'paid' => __('paid'),
        'due' => __('due'),
        'service_type' => __('service_type'),
        'services_count' => __('services_count'),
        'revenue' => __('revenue'),
        'suppliers' => __('suppliers'),
        'fulfilled_services' => __('fulfilled_services'),
        'total_payable' => __('total_payable'),
        'supplier' => __('supplier'),
        'pay_flight' => __('pay_flight'),
        'pay_hotel' => __('pay_hotel'),
        'pay_visa' => __('pay_visa'),
        'pay_transport' => __('pay_transport'),
        'pay_meal' => __('pay_meal'),
        'pay_ziyarat' => __('pay_ziyarat'),
        'no_payables' => __('no_payables'),
        'hotel' => __('hotel'),
        'city' => __('city'),
        'total_rooms' => __('total_rooms'),
        'reservations' => __('reservations'),
        'occupied_today' => __('occupied_today'),
        'occupancy' => __('occupancy'),
        'contracts' => __('contracts'),
        'inventory_rooms' => __('inventory_rooms'),
        'utilization' => __('utilization'),
        'outstanding_payments' => __('outstanding_payments'),
        'no_outstanding' => __('no_outstanding'),
    ]) ?>;
</script>
<?php include '../includes/admin_footer.php'; ?>
