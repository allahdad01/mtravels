<?php
/**
 * Hotel Management — Phase 24-25 (umrah_plan.md)
 * Dedicated hotel area: dashboard overview (occupancy by room type),
 * hotel master CRUD, rooms (types + rooms), contracts (inventory + rates),
 * and the room × date calendar (A/R/O/B).
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
$canManageHotels = umrah_can('hotel_manage');

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
    .uh-badge--available { color: #059669; border-color: #a7f3d0; background: #ecfdf5; }
    .uh-badge--reserved { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
    .uh-badge--occupied { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
    .uh-badge--blocked  { color: #64748b; border-color: #cbd5e1; background: #f1f5f9; }
    .uh-badge--green  { color: #059669; border-color: #a7f3d0; background: #ecfdf5; }
    .uh-badge--blue   { color: #2563eb; border-color: #bfdbfe; background: #eff6ff; }
    .uh-badge--red    { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
    .uh-badge--amber  { color: #d97706; border-color: #fde68a; background: #fffbeb; }
    .uh-badge--slate  { color: #64748b; border-color: #cbd5e1; background: #f1f5f9; }
    .uh-badge--light  { color: #94a3b8; border-color: #e2e8f0; background: #f8fafc; }
    .uh-cell-fill {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        padding: 2px 0;
        width: 100%;
    }
    .uh-cell-name {
        font-size: .62rem;
        line-height: 1.15;
        color: #334155;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 84px;
    }
    .uh-cell-bar {
        height: 7px;
        width: 100%;
        overflow: hidden;
    }
    .uh-cell-bar-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #fca5a5, #dc2626);
    }
    .uh-cell-occ {
        border-left: 0 !important;
        border-right: 0 !important;
        background: rgba(254, 242, 242, .35);
    }
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
                    <i class="feather icon-home mr-2" style="color: #0e7490;"></i><?= __('hotel_management') ?>
                </h5>
                <p class="text-muted mb-0" style="font-size: 0.85rem;"><?= __('hotel_management_hint') ?></p>
            </div>
            <div class="ml-auto">
                <button class="btn btn-sm btn-outline-secondary" type="button" id="btnRefreshHotels">
                    <i class="feather icon-refresh-cw mr-1"></i><?= __('refresh') ?>
                </button>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" id="hotelTabs" role="tablist">
            <li class="nav-item"><a class="nav-link active" id="tab-overview" data-toggle="tab" href="#pane-overview" role="tab"><i class="feather icon-layout mr-1"></i><?= __('overview') ?></a></li>
            <li class="nav-item"><a class="nav-link" id="tab-hotels" data-toggle="tab" href="#pane-hotels" role="tab"><i class="feather icon-home mr-1"></i><?= __('hotels') ?></a></li>
            <li class="nav-item"><a class="nav-link" id="tab-rooms" data-toggle="tab" href="#pane-rooms" role="tab"><i class="feather icon-grid mr-1"></i><?= __('rooms') ?></a></li>
            <li class="nav-item"><a class="nav-link" id="tab-contracts" data-toggle="tab" href="#pane-contracts" role="tab"><i class="feather icon-file-text mr-1"></i><?= __('contracts') ?></a></li>
            <li class="nav-item"><a class="nav-link" id="tab-calendar" data-toggle="tab" href="#pane-calendar" role="tab"><i class="feather icon-calendar mr-1"></i><?= __('calendar') ?></a></li>
        </ul>

        <div class="tab-content" id="hotelTabContent">

            <!-- ── Overview ─────────────────────────────────────────────── -->
            <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
                <div class="row" id="overviewStats"></div>
                <div class="card">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="feather icon-grid mr-2" style="color: #0e7490;"></i><?= __('occupancy_today') ?></h6>
                    </div>
                    <div class="card-body" id="overviewOccupancy">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
                <div class="card mt-3">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="feather icon-truck mr-2" style="color: #0e7490;"></i><?= __('recent_hotel_stays') ?></h6>
                    </div>
                    <div class="card-body" id="overviewStays">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
            </div>

            <!-- ── Hotels ───────────────────────────────────────────────── -->
            <div class="tab-pane fade" id="pane-hotels" role="tabpanel">
                <div class="d-flex justify-content-end mb-2">
                    <?php if ($canManageHotels): ?>
                    <button class="btn btn-sm btn-primary" type="button" onclick="openHotelForm(0)">
                        <i class="feather icon-plus mr-1"></i><?= __('add_hotel') ?>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card">
                    <div class="card-body p-0" id="hotelsTableWrap">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
            </div>

            <!-- ── Rooms (room types + rooms) ───────────────────────────── -->
            <div class="tab-pane fade" id="pane-rooms" role="tabpanel">
                <div class="row mb-2">
                    <div class="col-md-4">
                        <select class="form-control form-control-sm" id="roomsHotelFilter">
                            <option value=""><?= __('all_hotels') ?></option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex justify-content-end">
                        <?php if ($canManageHotels): ?>
                        <button class="btn btn-sm btn-outline-primary mr-1" type="button" onclick="openRoomTypeForm(0)">
                            <i class="feather icon-plus mr-1"></i><?= __('add_room_type') ?>
                        </button>
                        <button class="btn btn-sm btn-primary" type="button" onclick="openRoomForm(0)">
                            <i class="feather icon-plus mr-1"></i><?= __('add_room') ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header py-2 bg-white"><strong><?= __('room_types') ?></strong></div>
                    <div class="card-body p-0" id="roomTypesTableWrap">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header py-2 bg-white"><strong><?= __('rooms') ?></strong></div>
                    <div class="card-body p-0" id="roomsTableWrap">
                        <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                    </div>
                </div>
            </div>

            <!-- ── Contracts ────────────────────────────────────────────── -->
            <div class="tab-pane fade" id="pane-contracts" role="tabpanel">
                <div class="d-flex justify-content-end mb-2">
                    <?php if ($canManageHotels): ?>
                    <button class="btn btn-sm btn-primary" type="button" onclick="openContractForm(0)">
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

            <!-- ── Calendar ─────────────────────────────────────────────── -->
            <div class="tab-pane fade" id="pane-calendar" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-white">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <select class="form-control form-control-sm" id="calendarHotelFilter">
                                    <option value=""><?= __('select_hotel') ?></option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control form-control-sm" id="calendarRoomTypeFilter">
                                    <option value=""><?= __('all_room_types') ?></option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control form-control-sm" id="calendarFrom">
                            </div>
                            <div class="col-md-2">
                                <input type="date" class="form-control form-control-sm" id="calendarTo">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-sm btn-primary w-100" type="button" id="btnLoadCalendar">
                                    <i class="feather icon-search mr-1"></i><?= __('load') ?>
                                </button>
                            </div>
                        </div>
                        <div class="mt-2" style="font-size: 0.8rem;">
                            <span class="uh-badge uh-badge--available mr-2">A = <?= __('available') ?></span>
                            <span class="uh-badge uh-badge--reserved mr-2">R = <?= __('reserved') ?></span>
                            <span class="uh-badge uh-badge--occupied mr-2">O = <?= __('occupied') ?></span>
                            <span class="uh-badge uh-badge--blocked">B = <?= __('blocked') ?></span>
                        </div>
                    </div>
                    <div class="card-body p-0" id="calendarWrap" style="overflow-x: auto;">
                        <div class="text-muted py-4 text-center"><?= __('select_hotel') ?></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</div>
</div>
</div>
<?php include '../modals/umrah/hotel_form_modal.php'; ?>
<?php include '../modals/umrah/contract_form_modal.php'; ?>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script src="../js/umrah/bundle.php?v=<?= $umrahJsVersion ?>"></script>
<script>
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    window.canManageHotels = <?= $canManageHotels ? 'true' : 'false' ?>;
    window.hotelLabels = <?= json_encode([
        'active' => __('active'), 'active_contracts' => __('active_contracts'),
        'add_rate' => __('add_rate'), 'all_hotels' => __('all_hotels'),
        'all_room_types' => __('all_room_types'), 'available' => __('available'),
        'available_today' => __('available_today'), 'bed_type' => __('bed_type'),
        'city' => __('city'), 'confirm_delete' => __('confirm_delete'),
        'contract_amount' => __('contract_amount'), 'contract_number' => __('contract_number'),
        'contract_type' => __('contract_type'),
        'contract_type_period' => __('contract_type_period'),
        'contract_type_per_trip' => __('contract_type_per_trip'),
        'contract_type_period_help' => __('contract_type_period_help'),
        'contract_type_per_trip_help' => __('contract_type_per_trip_help'),
        'cost_price' => __('cost_price'),
        'count' => __('count'), 'currency' => __('currency'),
        'delete' => __('delete'), 'deleted' => __('deleted'), 'deleting' => __('deleting'),
        'expired' => __('expired'), 'hotel' => __('hotel'), 'inactive' => __('inactive'),
        'invalid_date_range' => __('invalid_date_range'), 'inventory' => __('inventory'),
        'inventory_hint' => __('inventory_hint'), 'load_failed' => __('load_failed'),
        'loading' => __('loading'), 'maintenance' => __('maintenance'),
        'max_occupancy' => __('max_occupancy'), 'member' => __('member'),
        'name' => __('name'), 'nightly_rate' => __('nightly_rate'),
        'no_contracts' => __('no_contracts'), 'no_hotels' => __('no_hotels'),
        'no_occupancy_data' => __('no_occupancy_data'), 'no_rates' => __('no_rates'),
        'no_rooms' => __('no_rooms'), 'no_room_types' => __('no_room_types'), 'no_stays' => __('no_stays'),
        'none' => __('none'), 'occupied' => __('occupied'),
        'occupied_today' => __('occupied_today'), 'per_trip' => __('per_trip'),
        'period' => __('period'), 'rates' => __('rates'),
        'reserved' => __('reserved'), 'reserved_today' => __('reserved_today'),
        'room' => __('room'), 'room_number' => __('room_number'), 'room_type' => __('room_type'),
        'room_type_name' => __('room_type_name'), 'room_types' => __('room_types'),
        'rooms' => __('rooms'), 'save_failed' => __('save_failed'),
        'saved' => __('saved'), 'saving' => __('saving'), 'scope' => __('scope'),
        'select_hotel' => __('select_hotel'), 'select_room_type' => __('select_room_type'),
        'star_rating' => __('star_rating'), 'status' => __('status'),
        'stay_period' => __('stay_period'), 'supplier' => __('supplier'),
        'toggle' => __('toggle'), 'total' => __('total'),
        'total_hotels' => __('total_hotels'), 'total_rooms' => __('total_rooms'),
        'validity' => __('validity'),
    ]) ?>;
</script>
<?php include '../includes/admin_footer.php'; ?>
