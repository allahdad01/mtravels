<?php
/**
 * Transport Management — Phase 24+ (umrah_plan.md)
 * Transport contracts with the same pricing scheme as hotel contracts
 * (contract_type 'period' | 'per_trip'), amount-based: the contracted
 * amount is divided among the trip's members at fulfillment time.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'security.php';
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/language_helpers.php';

enforce_auth(['admin', 'finance', 'umrah', 'sales', 'staff', 'operations', 'hotel_manager', 'viewer']);
umrah_require('view', 'page');
$canManageTransport = umrah_can('transport_manage');

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
<style>
    .uh-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 12px;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .02em;
        line-height: 1.5;
        border: 1px solid;
        background: #fff;
    }
    .uh-badge::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex: 0 0 8px;
        background: currentColor;
    }
    .uh-badge--green  { color: #059669; border-color: #a7f3d0; background: #ecfdf5; }
    .uh-badge--blue   { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
    .uh-badge--red    { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
    .uh-badge--amber  { color: #d97706; border-color: #fde68a; background: #fffbeb; }
    .uh-badge--slate  { color: #64748b; border-color: #cbd5e1; background: #f1f5f9; }
</style>
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="main-body">
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center mb-3">
            <div>
                <h5 class="mb-1" style="font-weight: 700;">
                    <i class="feather icon-truck mr-2" style="color: #0e7490;"></i><?= __('transport_management') ?>
                </h5>
                <p class="text-muted mb-0" style="font-size: 0.85rem;"><?= __('transport_management_hint') ?></p>
            </div>
            <div class="ml-auto">
                <button class="btn btn-sm btn-outline-secondary" type="button" id="btnRefreshTransport">
                    <i class="feather icon-refresh-cw mr-1"></i><?= __('refresh') ?>
                </button>
            </div>
        </div>

        <!-- ── Overview ─────────────────────────────────────────────── -->
        <div class="row" id="overviewStats"></div>

        <!-- ── Contracts ────────────────────────────────────────────── -->
        <div class="d-flex justify-content-end mb-2">
            <?php if ($canManageTransport): ?>
            <button class="btn btn-sm btn-primary" type="button" onclick="openTransportContractForm(0)">
                <i class="feather icon-plus mr-1"></i><?= __('add_contract') ?>
            </button>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-body p-0" id="contractsTableWrap">
                <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
</div>
<?php include '../modals/umrah/transport_contract_modal.php'; ?>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="../js/umrah/bundle.php?v=<?= $umrahJsVersion ?>"></script>
<script>
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    window.canManageTransport = <?= $canManageTransport ? 'true' : 'false' ?>;
    window.transportLabels = <?= json_encode([
        'active' => __('active'), 'add_contract' => __('add_contract'),
        'contract_amount' => __('contract_amount'), 'contract_number' => __('contract_number'),
        'contract_type' => __('contract_type'),
        'contract_type_period' => __('contract_type_period'),
        'contract_type_per_trip' => __('contract_type_per_trip'),
        'contract_type_period_help' => __('contract_type_period_help'),
        'contract_type_per_trip_help' => __('contract_type_per_trip_help'),
        'confirm_delete' => __('confirm_delete'), 'contracts' => __('contracts'),
        'currency' => __('currency'), 'delete' => __('delete'), 'deleted' => __('deleted'),
        'deleting' => __('deleting'), 'expired' => __('expired'), 'inactive' => __('inactive'),
        'load_failed' => __('load_failed'), 'loading' => __('loading'),
        'no_contracts' => __('no_contracts'), 'none' => __('none'),
        'payment_terms' => __('payment_terms'), 'per_trip' => __('per_trip'),
        'period' => __('period'), 'save_failed' => __('save_failed'),
        'saved' => __('saved'), 'saving' => __('saving'), 'status' => __('status'),
        'supplier' => __('supplier'), 'total' => __('total'),
        'validity' => __('validity'), 'valid_from' => __('valid_from'), 'valid_to' => __('valid_to'),
        'active_contracts' => __('active_contracts'), 'total_contracts' => __('total_contracts'),
        'total_amount' => __('total_amount'),
    ]) ?>;
</script>
<?php include '../includes/admin_footer.php'; ?>
