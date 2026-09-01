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

enforce_auth();
require_permission('umrah.finance_view');

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
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
<style>
.finance-title-row{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:28px}
.finance-title-row h1{font-size:25px;margin:0 0 9px;font-weight:800}
.finance-subtitle{color:#71809a;font-size:15px}
.finance-refresh{background:#fff;border:1px solid #dce4ed;border-radius:8px;padding:10px 19px;color:#263953;font-weight:600;box-shadow:0 2px 5px rgba(17,37,65,.04);cursor:pointer;transition:all .2s}
.finance-refresh:hover{transform:translateY(-1px);box-shadow:0 4px 8px rgba(17,37,65,.08)}

.finance-tabs{
  display:flex;
  align-items:center;
  gap:8px;
  margin-bottom:29px;
  padding:6px;
  background:#eef3f9;
  border:1px solid #e3eaf2;
  border-radius:12px;
  width:max-content;
  box-shadow:inset 0 1px 2px rgba(20,45,75,.035)
}
.finance-tab{
  position:relative;
  min-width:142px;
  height:48px;
  padding:0 21px;
  border-radius:9px;
  color:#66768d;
  font-size:14px;
  font-weight:650;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  cursor:pointer;
  transition:all .2s ease;
  border:1px solid transparent;
  text-decoration:none
}
.finance-tab .tab-icon{
  width:28px;
  height:28px;
  display:grid;
  place-items:center;
  border-radius:8px;
  background:#fff;
  color:#71829a;
  font-size:15px;
  box-shadow:0 1px 3px rgba(30,55,85,.06)
}
.finance-tab:hover{
  color:#1e67d2;
  background:rgba(255,255,255,.72);
  transform:translateY(-1px)
}
.finance-tab:hover .tab-icon{color:#2377ee}
.finance-tab.active{
  background:#fff;
  color:#1769df;
  font-weight:800;
  border-color:#d9e5f4;
  box-shadow:0 4px 12px rgba(28,72,122,.10)
}
.finance-tab.active .tab-icon{
  background:#e8f1ff;
  color:#2377ee
}
.finance-tab.active:after{
  content:"";
  position:absolute;
  left:22px;
  right:22px;
  bottom:-7px;
  height:3px;
  border-radius:3px;
  background:linear-gradient(90deg,#2377ee,#22c7c1)
}
.finance-tab-badge{
  min-width:20px;
  height:20px;
  padding:0 6px;
  border-radius:10px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  background:#e8edf4;
  color:#64758d;
  font-size:10px;
  font-weight:800
}
.finance-tab.active .finance-tab-badge{
  background:#e7f0ff;
  color:#2377ee
}

.finance-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:13px;margin-bottom:27px}
.finance-kpi{background:#fff;border:1px solid #edf1f6;border-radius:14px;min-height:145px;padding:24px 17px;display:flex;align-items:center;gap:16px;box-shadow:0 7px 22px rgba(33,57,87,.07)}
.finance-kpi-icon{width:54px;height:54px;flex:0 0 54px;border-radius:50%;display:grid;place-items:center;font-size:24px;font-weight:800}
.finance-i-blue{background:#e5f0ff;color:#1872e9}
.finance-i-green{background:#e2f8ee;color:#0aa64a}
.finance-i-red{background:#ffe9eb;color:#e53943}
.finance-i-orange{background:#fff0dc;color:#f08b00}
.finance-i-purple{background:#f0e6ff;color:#7935dc}
.finance-i-cyan{background:#e2f8fa;color:#0b8da2}
.finance-kpi-label{font-size:12px;color:#728099;text-transform:uppercase;margin-bottom:7px}
.finance-kpi-value{font-size:20px;font-weight:800;line-height:1.25}
.finance-kpi-unit{font-size:13px;font-weight:700;margin-top:3px}

.finance-panel{background:#fff;border-radius:14px;box-shadow:0 7px 22px rgba(33,57,87,.07);overflow:hidden;margin-bottom:24px}
.finance-panel-head{height:59px;background:linear-gradient(90deg,#2d91ef,#28cbb7);color:#fff;display:flex;align-items:center;padding:0 27px;font-weight:800;font-size:16px;gap:12px}
.finance-panel-body{padding:40px 28px 26px}

.finance-filters{
  display:grid;
  grid-template-columns:minmax(220px,1.5fr) minmax(200px,1fr) auto 132px;
  gap:18px;
  align-items:end;
  width:100%;
}
.input-group{position:relative}
.input-group .input-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#71809a;pointer-events:none;font-size:16px;z-index:1}
.finance-field label{
  display:block;
  font-size:13px;
  font-weight:700;
  color:#263951;
  margin:0 0 8px;
  line-height:1.2;
}
.finance-input,.finance-select{
  display:block;
  height:48px;
  width:100%;
  min-width:0;
  border:1px solid #dce4ee;
  background:#fff;
  border-radius:8px;
  padding:0 14px;
  color:#65748b;
  outline:none;
  transition:border-color .2s,box-shadow .2s;
  font:inherit
}
.finance-input:focus,.finance-select:focus{
  border-color:#7eb1f5;
  box-shadow:0 0 0 3px rgba(35,119,238,.09);
}
.finance-select{appearance:auto;cursor:pointer}
#financeTabContent>.tab-pane{display:none}
#financeTabContent>.tab-pane.show.active{display:block}
.group-dropdown{position:relative}
.group-dropdown-btn{
  display:flex;align-items:center;justify-content:space-between;
  height:48px;width:100%;min-width:0;
  border:1px solid #dce4ee;background:#fff;border-radius:8px;
  padding:0 14px;color:#65748b;font:inherit;cursor:pointer;text-align:left;
  transition:border-color .2s,box-shadow .2s;
}
.group-dropdown-btn:focus,.group-dropdown-btn.open{border-color:#7eb1f5;box-shadow:0 0 0 3px rgba(35,119,238,.09)}
.group-dropdown-btn .arrow{font-size:10px;color:#999;margin-left:8px;transition:transform .2s}
.group-dropdown-btn.open .arrow{transform:rotate(180deg)}
.group-dropdown-menu{
  display:none;position:absolute;top:100%;left:0;right:0;z-index:100;
  margin-top:4px;background:#fff;border:1px solid #dce4ee;border-radius:8px;
  box-shadow:0 8px 24px rgba(0,0,0,.12);max-height:260px;overflow-y:auto;
}
.group-dropdown-menu.open{display:block}
.group-dropdown-item{
  display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;
  transition:background .15s;font-size:14px;color:#65748b;
}
.group-dropdown-item:hover{background:#f0f5ff}
.group-dropdown-item input[type="checkbox"]{
  width:16px;height:16px;border-radius:4px;border:1.5px solid #c4cdd5;
  accent-color:#2377ee;cursor:pointer;flex-shrink:0;
}
.group-dropdown-item label{cursor:pointer;flex:1;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.group-dropdown-search{
  padding:8px 10px;border-bottom:1px solid #eef1f6;
}
.group-dropdown-search input{
  width:100%;height:34px;border:1px solid #dce4ee;border-radius:6px;
  padding:0 10px;font-size:13px;outline:none;
}
.group-dropdown-search input:focus{border-color:#7eb1f5}
.finance-generate{
  height:48px;
  border:0;
  border-radius:8px;
  background:#2377ee;
  color:#fff;
  padding:0 23px;
  font-weight:800;
  white-space:nowrap;
  box-shadow:0 5px 12px rgba(35,119,238,.2);
  cursor:pointer;
  transition:transform .2s,box-shadow .2s;
  font:inherit
}
.finance-generate:hover{transform:translateY(-1px);box-shadow:0 7px 15px rgba(35,119,238,.25)}
.finance-language-field{min-width:132px}
.finance-actions{
  display:flex;
  align-items:center;
  gap:12px;
  margin:24px 0 23px;
  flex-wrap:wrap;
}
.finance-outline{
  height:47px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  background:#fff;
  border:1px solid #dbe3ed;
  border-radius:8px;
  padding:0 20px;
  font-weight:700;
  color:#30445e;
  cursor:pointer;
  transition:all .2s;
  font:inherit
}
.finance-outline:hover{
  border-color:#b8cce6;
  background:#f8fbff;
  transform:translateY(-1px);
}

.finance-table-wrap{border:1px solid #e2e8f0;border-radius:9px;overflow:auto}
.finance-table{width:100%;border-collapse:collapse;min-width:950px}
.finance-table th{background:#f7f9fc;color:#263951;font-size:13px;text-transform:uppercase;padding:17px 15px;text-align:center;white-space:nowrap}
.finance-table td{padding:19px 15px;border-top:1px solid #e9eef4;text-align:center;font-size:14px;white-space:nowrap}
.finance-table td:first-child,.finance-table th:first-child{text-align:center}
.finance-group{color:#156be0;font-weight:800}
.finance-profit{color:#0aa64a;font-weight:800}
.finance-due{color:#ef333d}
.finance-muted{color:#697a91}

.svc-type-badge { border: 1px solid #d1d5db !important; background: #fff !important; color: #6b7280 !important; font-weight: 500; transition: all 0.15s; outline: none !important; }
.svc-type-badge.active, .svc-type-badge.active:focus, .svc-type-badge.active:active { background: #0e7490 !important; color: #fff !important; border-color: #0e7490 !important; }
.svc-type-badge:hover { box-shadow: 0 1px 3px rgba(0,0,0,0.12); }

@media(max-width:1250px){
  .finance-kpis{grid-template-columns:repeat(3,1fr)}
}
@media(max-width:900px){
  .finance-kpis{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:650px){
  .finance-kpis{grid-template-columns:1fr}
  .finance-title-row{gap:15px}
  .finance-tabs{overflow:auto}
  .finance-filters{grid-template-columns:1fr}
}
@media(max-width:1200px){
  .finance-filters{
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px;
  }
  .finance-filters .group-field{grid-column:auto}
  .finance-filters .finance-generate{width:100%}
  .finance-filters .finance-language-field{width:100%}
}
@media(max-width:700px){
  .finance-panel-body{padding:25px 18px 20px}
  .finance-filters{grid-template-columns:1fr;gap:15px}
  .finance-filters .group-field{grid-column:auto}
  .finance-generate{width:100%;height:50px}
  .finance-language-field{width:100%}
  .finance-actions{margin-top:19px}
  .finance-outline{flex:1;min-width:145px}
}
@media(max-width:420px){
  .finance-actions{display:grid;grid-template-columns:1fr}
  .finance-outline{width:100%}
}
</style>
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="main-body">
      <div class="page-wrapper">
        <div class="content container-fluid">
          <div class="finance-title-row">
            <div>
              <h1>▥ &nbsp;<?= __('finance_management') ?></h1>
              <div class="finance-subtitle"><?= __('finance_management_hint') ?></div>
            </div>
            <button class="finance-refresh" onclick="location.reload()">⟳ &nbsp; <?= __('refresh') ?></button>
          </div>

          <div class="finance-tabs" role="tablist">
            <a class="finance-tab active" role="tab" aria-selected="true" id="tab-finance" href="#pane-finance" data-pane="pane-finance">
              <span class="tab-icon">▣</span>
              <span><?= __('finance') ?></span>
            </a>
            <a class="finance-tab" role="tab" aria-selected="false" id="tab-payables" href="#pane-payables" data-pane="pane-payables">
              <span class="tab-icon">⌁</span>
              <span><?= __('payables') ?></span>
              <span class="finance-tab-badge">3</span>
            </a>
            <a class="finance-tab" role="tab" aria-selected="false" id="tab-reports" href="#pane-reports" data-pane="pane-reports">
              <span class="tab-icon">◷</span>
              <span><?= __('reports') ?></span>
            </a>
            <a class="finance-tab" role="tab" aria-selected="false" id="tab-service-report" href="#pane-service-report" data-pane="pane-service-report">
              <span class="tab-icon">⊞</span>
              <span><?= __('service_report') ?></span>
            </a>
          </div>

          <div class="tab-content" id="financeTabContent">

            <!-- ── Finance ───────────────────────────────────────────────── -->
            <div class="tab-pane fade show active" id="pane-finance" role="tabpanel">
              <div class="row" id="financeStats"></div>
              <div class="finance-panel">
                <div class="finance-panel-head">♧ &nbsp; <?= __('group_profitability') ?></div>
                <div class="finance-panel-body">
                  <div class="finance-filters">
                    <div class="finance-field">
                      <label for="profitDateRange"><?= __('date_range') ?></label>
                      <div class="input-group">
                        <input type="text" id="profitDateRange" class="finance-input" readonly placeholder="<?= __('select_date_range') ?>" style="cursor:pointer">
                        <input type="hidden" id="profitDateFrom">
                        <input type="hidden" id="profitDateTo">
                        <span class="input-icon"><i class="feather icon-calendar"></i></span>
                      </div>
                    </div>
                    <div class="finance-field group-field">
                      <label for="profitGroupSelect"><?= __('group_name') ?></label>
                      <div class="group-dropdown" id="profitGroupDropdown">
                        <button type="button" class="group-dropdown-btn" id="profitGroupBtn">
                          <span><?= __('all_groups') ?></span><span class="arrow">▾</span>
                        </button>
                        <div class="group-dropdown-menu" id="profitGroupMenu">
                          <div class="group-dropdown-search"><input type="text" id="profitGroupSearch" placeholder="<?= __('group_name') ?>..."></div>
                          <div class="group-dropdown-item" style="border-bottom:1px solid #eef1f6">
                            <input type="checkbox" id="grpAll" checked>
                            <label for="grpAll"><b><?= __('all_groups') ?></b></label>
                          </div>
                          <div id="profitGroupList"></div>
                        </div>
                        <select id="profitGroupSelect" style="display:none" multiple></select>
                      </div>
                    </div>
                    <button type="button" class="finance-generate" id="btnLoadProfitDetail">
                      ⌕ &nbsp; <?= __('generate') ?>
                    </button>
                    <div class="finance-field finance-language-field">
                      <label for="profitLangSelect"><?= __('language') ?></label>
                      <select id="profitLangSelect" class="finance-select">
                        <option value="en">English</option>
                        <option value="dari">دری</option>
                        <option value="ps">پښتو</option>
                      </select>
                    </div>
                  </div>
                  <div class="finance-actions">
                    <button type="button" class="finance-outline" id="btnProfitExcel">▣ &nbsp; <?= __('excel') ?></button>
                    <button type="button" class="finance-outline" id="btnProfitPrint">▤ &nbsp; <?= __('print') ?></button>
                  </div>
                  <div class="finance-table-wrap" id="memberProfitTable">
                    <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                  </div>
                </div>
              </div>
              <div class="finance-panel">
                <div class="finance-panel-head">♧ &nbsp; <?= __('service_profitability') ?></div>
                <div class="finance-panel-body">
                  <div class="finance-table-wrap" id="serviceProfitTable">
                    <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── Payables ──────────────────────────────────────────────── -->
            <div class="tab-pane fade" id="pane-payables" role="tabpanel">
              <div class="row" id="payablesStats"></div>
              <div class="finance-panel">
                <div class="finance-panel-head">♧ &nbsp; <?= __('supplier_payables') ?></div>
                <div class="finance-panel-body">
                  <div class="finance-table-wrap" id="supplierPayableTable">
                    <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── Reports ───────────────────────────────────────────────── -->
            <div class="tab-pane fade" id="pane-reports" role="tabpanel">
              <div class="finance-panel">
                <div class="finance-panel-head">♧ &nbsp; <?= __('hotel_report') ?></div>
                <div class="finance-panel-body">
                  <div class="finance-table-wrap" id="hotelReportTable">
                    <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                  </div>
                </div>
              </div>
              <div class="finance-panel">
                <div class="finance-panel-head">♧ &nbsp; <?= __('outstanding_payments') ?></div>
                <div class="finance-panel-body">
                  <div class="finance-table-wrap" id="outstandingTable">
                    <div class="text-muted py-4 text-center"><?= __('loading') ?>...</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── Service Report (by fulfillment date) ──────────────────── -->
            <div class="tab-pane fade" id="pane-service-report" role="tabpanel">
              <div class="finance-panel">
                <div class="finance-panel-head">♧ &nbsp; <?= __('service_profitability') ?></div>
                <div class="finance-panel-body">
                  <div class="finance-filters">
                    <div class="finance-field">
                      <label for="svcDateRange"><?= __('date_range') ?></label>
                      <div class="input-group">
                        <input type="text" id="svcDateRange" class="finance-input" readonly placeholder="<?= __('select_date_range') ?>" style="cursor:pointer">
                        <input type="hidden" id="svcDateFrom">
                        <input type="hidden" id="svcDateTo">
                        <span class="input-icon"><i class="feather icon-calendar"></i></span>
                      </div>
                    </div>
                    <div class="finance-field" id="svcTypeFilterWrap">
                      <label for="svcTypeBadges"><?= __('service_type') ?></label>
                      <div class="d-flex flex-wrap" style="gap:4px;" id="svcTypeBadges">
                        <button type="button" class="btn btn-sm svc-type-badge" data-svc="visa" style="font-size:0.78rem;"><?= __('visa') ?></button>
                        <button type="button" class="btn btn-sm svc-type-badge" data-svc="hotel" style="font-size:0.78rem;"><?= __('hotel') ?></button>
                        <button type="button" class="btn btn-sm svc-type-badge" data-svc="transport" style="font-size:0.78rem;"><?= __('transport') ?></button>
                        <button type="button" class="btn btn-sm svc-type-badge" data-svc="flight" style="font-size:0.78rem;"><?= __('flight') ?></button>
                        <button type="button" class="btn btn-sm svc-type-badge" data-svc="meal" style="font-size:0.78rem;"><?= __('meal') ?></button>
                        <button type="button" class="btn btn-sm svc-type-badge" data-svc="ziyarat" style="font-size:0.78rem;"><?= __('ziyarat') ?></button>
                      </div>
                    </div>
                    <button type="button" class="finance-generate" id="btnLoadServiceReport">
                      ⌕ &nbsp; <?= __('generate') ?>
                    </button>
                  </div>
                  <div class="finance-actions">
                    <button type="button" class="finance-outline" id="btnExportServiceExcel" disabled>▣ &nbsp; <?= __('excel') ?></button>
                    <button type="button" class="finance-outline" id="btnPrintServiceReport" disabled>▤ &nbsp; <?= __('print') ?></button>
                  </div>
                  <div class="row" id="serviceReportStats"></div>
                  <div class="finance-table-wrap" id="serviceReportTable">
                    <div class="text-muted py-4 text-center"><?= __('select_date_range_and_generate') ?></div>
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
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="../js/umrah/bundle.php?v=<?= $umrahJsVersion ?>"></script>
<script>
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
    window.UMRAH_CAN_FINANCE = true;
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
        'grand_total' => __('grand_total'),
        'group_name' => __('group_name'),
        'family' => __('family'),
        'total_members' => __('total_members'),
        'name' => __('name'),
        'col_fname' => __('father'),
        'col_passport' => __('passport_number'),
        'client' => __('client'),
        'all' => __('all'),
        'all_groups' => __('all_groups'),
    ]) ?>;
</script>
<script>
    $(function(){
    // Manual tab switching (avoids Bootstrap tab plugin conflicts)
    $('.finance-tab').on('click',function(e){
      e.preventDefault();
      var pane=$(this).data('pane');
      if(!pane)return;
      $('.finance-tab').removeClass('active').attr('aria-selected','false');
      $(this).addClass('active').attr('aria-selected','true');
      $('#financeTabContent .tab-pane').removeClass('show active');
      $('#'+pane).addClass('show active');
    });

    // Group checkbox dropdown logic
      var $btn=$('#profitGroupBtn'),$menu=$('#profitGroupMenu'),$list=$('#profitGroupList'),
          $search=$('#profitGroupSearch'),$sel=$('#profitGroupSelect'),$all=$('#grpAll');

      $btn.on('click',function(e){e.stopPropagation();$menu.toggleClass('open');$btn.toggleClass('open')});
      $(document).on('click',function(){$menu.removeClass('open');$btn.removeClass('open')});
      $menu.on('click',function(e){e.stopPropagation()});

      $search.on('input',function(){
        var q=$(this).val().toLowerCase();
        $list.find('.group-dropdown-item').each(function(){$(this).toggle($(this).text().toLowerCase().indexOf(q)>=0)});
      });

      $all.on('change',function(){
        var checked=$all.is(':checked');
        $list.find('input[type="checkbox"]').prop('checked',checked);
        syncSelect();updateBtnText();
      });

      $list.on('change','input[type="checkbox"]',function(){
        var total=$list.find('input[type="checkbox"]').length;
        var checked=$list.find('input[type="checkbox"]:checked').length;
        $all.prop('checked',checked===total);
        syncSelect();updateBtnText();
      });

      function syncSelect(){
        $sel.empty();
        if($all.is(':checked')){updateBtnText();return}
        $list.find('input[type="checkbox"]:checked').each(function(){
          $sel.append('<option value="'+$(this).val()+'" selected>'+$(this).next('label').text()+'</option>');
        });
        updateBtnText();
      }

      function updateBtnText(){
        if($all.is(':checked')){$btn.find('span:first').text(financeLabels.all_groups||'All Groups');return}
        var checked=[];
        $list.find('input[type="checkbox"]:checked').each(function(){checked.push($(this).next('label').text())});
        $btn.find('span:first').text(checked.length?checked.join(', '):(financeLabels.all_groups||'All Groups'));
      }

      window.getSelectedGroupIds=function(){
        if($all.is(':checked'))return[];
        var ids=[];
        $list.find('input[type="checkbox"]:checked').each(function(){ids.push($(this).val())});
        return ids;
      };

      window.populateGroupDropdown=function(groups){
        var prevIds=[];
        $list.find('input[type="checkbox"]:checked').each(function(){prevIds.push($(this).val())});
        var html='';
        groups.forEach(function(g){
          var id=String(g.group_id);
          var chk=prevIds.indexOf(id)>=0?' checked':'';
          html+='<div class="group-dropdown-item"><input type="checkbox" id="grp_'+id+'" value="'+id+'"'+chk+'><label for="grp_'+id+'">#'+g.group_number+' — '+g.group_name+'</label></div>';
        });
        $list.html(html);
        var total=$list.find('input[type="checkbox"]').length;
        var checked=$list.find('input[type="checkbox"]:checked').length;
        $all.prop('checked',checked===total);
        syncSelect();
      };

      // Initialize date range pickers (matching report.php style)
      function initDateRangePickers(){
        if(typeof $.fn.daterangepicker==='undefined')return;

        var ranges={
          'Today':[moment(),moment()],
          'Yesterday':[moment().subtract(1,'days'),moment().subtract(1,'days')],
          'Last 7 Days':[moment().subtract(6,'days'),moment()],
          'Last 30 Days':[moment().subtract(29,'days'),moment()],
          'This Month':[moment().startOf('month'),moment().endOf('month')],
          'Last Month':[moment().subtract(1,'month').startOf('month'),moment().subtract(1,'month').endOf('month')],
          'This Year':[moment().startOf('year'),moment().endOf('year')],
          'Last Year':[moment().subtract(1,'year').startOf('year'),moment().subtract(1,'year').endOf('year')]
        };

        $('#profitDateRange').daterangepicker({
          startDate:moment().startOf('month'),
          endDate:moment().endOf('month'),
          ranges:ranges,
          locale:{format:'DD MMM YYYY'}
        },function(start,end){
          $('#profitDateFrom').val(start.format('YYYY-MM-DD')).trigger('change');
          $('#profitDateTo').val(end.format('YYYY-MM-DD')).trigger('change');
        });

        $('#svcDateRange').daterangepicker({
          startDate:moment().startOf('month'),
          endDate:moment().endOf('month'),
          ranges:ranges,
          locale:{format:'DD MMM YYYY'}
        },function(start,end){
          $('#svcDateFrom').val(start.format('YYYY-MM-DD')).trigger('change');
          $('#svcDateTo').val(end.format('YYYY-MM-DD')).trigger('change');
        });

        // Set initial hidden values
        $('#profitDateFrom').val(moment().startOf('month').format('YYYY-MM-DD'));
        $('#profitDateTo').val(moment().endOf('month').format('YYYY-MM-DD'));
        $('#svcDateFrom').val(moment().startOf('month').format('YYYY-MM-DD'));
        $('#svcDateTo').val(moment().endOf('month').format('YYYY-MM-DD'));
      }

      initDateRangePickers();
    });
</script>
<?php include '../includes/admin_footer.php'; ?>