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
$allowed_roles = ['admin', 'finance', 'sales'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);

// Pagination and search setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['page'])   ? intval($_GET['page']) : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Build search + tenant condition
$searchCondition = " WHERE va.tenant_id = ? AND va.branch_id = ?";
$params = [$tenant_id, $branch_id];
$types  = "ii";

if (!empty($search)) {
    $searchCondition .= " AND (
        va.applicant_name LIKE ? OR
        va.passport_number LIKE ? OR
        va.title LIKE ? OR
        va.country LIKE ? OR
        va.visa_type LIKE ?
    )";
    for ($i = 0; $i < 5; $i++) {
        $params[] = "%$search%";
        $types   .= "s";
    }
}

/* COUNT */
$totalRecordsQuery = "SELECT COUNT(*) as total FROM visa_applications va $searchCondition";
$stmt = $pdo->prepare($totalRecordsQuery);
foreach ($params as $index => $param) {
    $stmt->bindParam($index + 1, $params[$index], is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages   = ceil($totalRecords / $recordsPerPage);

/* MAIN VISA QUERY */
$visaQuery = "SELECT va.*, u.name as created_by
              FROM visa_applications va
              LEFT JOIN users u ON va.created_by = u.id
              $searchCondition
              AND (u.id IS NULL OR u.branch_id = ?)
              ORDER BY va.id DESC
              LIMIT ? OFFSET ?";

$paramsWithLimit   = $params;
$paramsWithLimit[] = $branch_id;
$paramsWithLimit[] = $recordsPerPage;
$paramsWithLimit[] = $offset;

$stmt = $pdo->prepare($visaQuery);
foreach ($paramsWithLimit as $index => $param) {
    $stmt->bindParam($index + 1, $paramsWithLimit[$index], is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$visas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Fetch Suppliers */
$stmt = $pdo->prepare("SELECT id, name FROM suppliers WHERE status = 'active' AND tenant_id = ? AND branch_id = ? AND category IN ('visa', 'all')");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Fetch Clients */
$stmt = $pdo->prepare("SELECT id, name, client_type FROM clients WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Fetch Internal Accounts */
$stmt = $pdo->prepare("SELECT id, name FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$internal = $stmt->fetchAll(PDO::FETCH_ASSOC);

$supplier_names = array_column($suppliers, 'name', 'id');
$client_names   = array_column($clients,   'name', 'id');
$client_types   = array_column($clients,   'client_type', 'name');
$internal_names = array_column($internal,  'name', 'id');

foreach ($visas as $key => $visa) {
    $visas[$key]['supplier_name'] = $supplier_names[$visa['supplier']] ?? 'Unknown';
    $visas[$key]['sold_name']     = $client_names[$visa['sold_to']]   ?? 'Unknown';
    $visas[$key]['paid_name']     = $internal_names[$visa['paid_to'] ?? null] ?? 'Unknown';
}

/* ── HELPERS ── */
function getStripeClass($status) {
    switch (strtolower($status)) {
        case 'approved': case 'issued':   return 'stripe--approved';
        case 'pending':                   return 'stripe--pending';
        case 'rejected': case 'cancelled':
        case 'refunded': case 'withdrawn':return 'stripe--rejected';
        default:                          return 'stripe--default';
    }
}

function getBadgeClass($status) {
    switch (strtolower($status)) {
        case 'approved': case 'issued':   return 'badge--approved';
        case 'pending':                   return 'badge--pending';
        case 'rejected': case 'cancelled':
        case 'refunded': case 'withdrawn':return 'badge--rejected';
        default:                          return 'badge--default';
    }
}

function getBadgeDotColor($status) {
    switch (strtolower($status)) {
        case 'approved': case 'issued':                             return 'var(--green)';
        case 'pending':                                             return 'var(--amber)';
        case 'rejected': case 'cancelled': case 'refunded':
        case 'withdrawn':                                           return 'var(--red)';
        default:                                                    return 'var(--text-3)';
    }
}

function isApprovedOrIssued($status) {
    return in_array(strtolower($status), ['approved', 'issued']);
}

function isCancelledEtc($status) {
    return in_array(strtolower($status), ['cancelled', 'rejected', 'withdrawn']);
}
?>

<link rel="stylesheet" href="../css/ticket/ticket_styles.css">
<link rel="stylesheet" href="../css/ticket/ticket-components.css">
<link rel="stylesheet" href="../css/ticket/ticket-form.css">
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/visa/visa.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">

<?php include '../includes/header.php'; ?>

<style>
/* ── CSS VARIABLES ─────────────────────────────────────── */
:root {
    --bg:        #f0f2f7;
    --surface:   #ffffff;
    --border:    #e4e8f0;
    --text-1:    #111827;
    --text-2:    #4b5563;
    --text-3:    #9ca3af;
    --blue:      #3b82f6;
    --blue-lt:   #eff6ff;
    --green:     #10b981;
    --green-lt:  #ecfdf5;
    --amber:     #f59e0b;
    --amber-lt:  #fffbeb;
    --red:       #ef4444;
    --red-lt:    #fef2f2;
    --grad:      linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);
    --shadow-sm: 0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08),0 2px 6px rgba(0,0,0,.05);
    --r:         14px;
    --r-sm:      8px;
}

/* ── PAGE HEADER ───────────────────────────────────────── */
.visa-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 14px;
}

.visa-page-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.visa-page-title-icon {
    width: 44px; height: 44px;
    background: var(--grad);
    border-radius: var(--r-sm);
    display: grid; place-items: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(64,153,255,.35);
}

.visa-page-title h1 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-1);
    line-height: 1.2;
    margin: 0;
}

.visa-page-title p {
    font-size: 13px;
    color: var(--text-3);
    margin: 2px 0 0;
}

.visa-header-actions { display: flex; gap: 10px; align-items: center; }

/* ── SEARCH BAR ────────────────────────────────────────── */
.visa-search-bar {
    background: var(--surface);
    border-radius: var(--r);
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.visa-search-input-wrap {
    position: relative;
    flex: 1;
    min-width: 220px;
}

.visa-search-input-wrap i {
    position: absolute;
    left: 13px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-3);
    font-size: 13px;
    pointer-events: none;
}

.visa-search-input {
    width: 100%;
    padding: 9px 13px 9px 36px;
    border: 1.5px solid var(--border);
    border-radius: var(--r-sm);
    font-size: 14px;
    color: var(--text-1);
    background: var(--bg);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    font-family: inherit;
}

.visa-search-input:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    background: var(--surface);
}

.visa-search-input::placeholder { color: var(--text-3); }

.visa-filter-chips { display: flex; gap: 7px; flex-wrap: wrap; }

.visa-chip {
    padding: 6px 14px;
    border-radius: 99px;
    font-size: 13px;
    font-weight: 500;
    border: 1.5px solid var(--border);
    background: var(--surface);
    cursor: pointer;
    color: var(--text-2);
    transition: all .15s;
    white-space: nowrap;
    user-select: none;
}
.visa-chip:hover  { border-color: var(--blue); color: var(--blue); }
.visa-chip.active { background: var(--blue-lt); border-color: var(--blue); color: var(--blue); }

/* ── RESULTS BAR ───────────────────────────────────────── */
.visa-results-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    flex-wrap: wrap;
    gap: 8px;
}

.visa-results-count { font-size: 13px; color: var(--text-3); }
.visa-results-count strong { color: var(--text-2); font-weight: 600; }

.visa-refunds-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-2);
    text-decoration: none;
    padding: 6px 13px;
    border: 1.5px solid var(--border);
    border-radius: var(--r-sm);
    background: var(--surface);
    transition: all .15s;
    font-weight: 500;
}
.visa-refunds-link:hover { border-color: var(--blue); color: var(--blue); }

/* ── BUTTONS ───────────────────────────────────────────── */
.btn-visa-primary {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 18px;
    border-radius: var(--r-sm);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: var(--grad);
    color: #fff;
    box-shadow: 0 4px 12px rgba(64,153,255,.3);
    transition: all .18s;
    font-family: inherit;
    white-space: nowrap;
}
.btn-visa-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(64,153,255,.4); }

/* ── VISA CARD ─────────────────────────────────────────── */
.visa-card-list { display: flex; flex-direction: column; gap: 11px; }

.visa-card {
    background: var(--surface);
    border-radius: var(--r);
    border: 1.5px solid var(--border);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    display: grid;
    grid-template-columns: 5px 1fr;
    transition: box-shadow .2s, transform .2s, border-color .2s;
    animation: visaFadeUp .3s ease both;
}

.visa-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); border-color: #d1d5e8; }

/* stripe */
.visa-card__stripe { width: 5px; }
.stripe--approved  { background: var(--green); }
.stripe--issued    { background: var(--green); }
.stripe--pending   { background: var(--amber); }
.stripe--rejected  { background: var(--red); }
.stripe--cancelled { background: var(--red); }
.stripe--refunded  { background: var(--red); }
.stripe--withdrawn { background: var(--red); }
.stripe--default   { background: var(--text-3); }

.visa-card__body {
    padding: 16px 18px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
    align-items: start;
}

/* LEFT */
.visa-card__left { min-width: 0; }

.visa-card__top {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 9px;
    flex-wrap: wrap;
}

.visa-card__counter {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-3);
    font-family: 'DM Mono', 'Courier New', monospace;
    letter-spacing: .5px;
    flex-shrink: 0;
}

.visa-card__name {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 280px;
}

.vc-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 11.5px;
    font-weight: 600;
    flex-shrink: 0;
}
.vc-badge-dot { width: 6px; height: 6px; border-radius: 50%; }
.badge--approved, .badge--issued    { background: var(--green-lt); color: var(--green); }
.badge--pending                     { background: var(--amber-lt); color: #b45309; }
.badge--rejected, .badge--cancelled,
.badge--refunded, .badge--withdrawn { background: var(--red-lt);   color: var(--red); }
.badge--default                     { background: #f1f5f9;          color: #64748b; }

/* payment bar */
.vc-pay-wrap { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.vc-pay-track {
    width: 58px; height: 5px;
    background: #e5e7eb;
    border-radius: 99px;
    overflow: hidden;
}
.vc-pay-fill {
    height: 100%;
    border-radius: 99px;
    transition: width .4s ease;
}
.pay--full    { background: var(--green); }
.pay--partial { background: var(--amber); }
.pay--none    { background: var(--red); }
.vc-pay-label { font-size: 11px; font-weight: 600; white-space: nowrap; }

/* meta pills */
.visa-card__meta {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-bottom: 9px;
    align-items: center;
}

.vc-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 12.5px;
    color: var(--text-2);
    font-weight: 500;
    white-space: nowrap;
}
.vc-pill i { font-size: 11px; color: var(--text-3); }
.vc-pill--blue   { background: var(--blue-lt); border-color: #bfdbfe; color: #1d4ed8; }
.vc-pill--blue i { color: #60a5fa; }

/* footer row */
.visa-card__footer {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
}

.vc-date {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
}
.vc-date i { font-size: 11px; color: var(--text-3); }
.vc-date__label {
    font-size: 10.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--text-3);
}
.vc-date__val { color: var(--text-2); font-weight: 500; }

.vc-created {
    margin-left: auto;
    font-size: 12px;
    color: var(--text-3);
    display: flex;
    align-items: center;
    gap: 5px;
}
.vc-created strong { color: var(--text-2); font-weight: 600; }

/* RIGHT */
.visa-card__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

.vc-amount { text-align: right; }
.vc-amount__label {
    font-size: 10.5px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--text-3);
}
.vc-amount__value {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-1);
    font-family: 'DM Mono', 'Courier New', monospace;
    letter-spacing: -.5px;
    line-height: 1.2;
}
.vc-amount__currency { font-size: 12px; color: var(--text-3); font-weight: 500; }

/* action buttons */
.visa-card__actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }

.vc-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 11px;
    border-radius: 7px;
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid var(--border);
    background: var(--surface);
    color: var(--text-2);
    transition: all .15s;
    white-space: nowrap;
}
.vc-btn:hover          { border-color: var(--blue);  color: var(--blue);  background: var(--blue-lt);  }
.vc-btn--warn:hover    { border-color: var(--amber); color: #b45309;      background: var(--amber-lt); }
.vc-btn--danger:hover  { border-color: var(--red);   color: var(--red);   background: var(--red-lt);   }
.vc-btn--success:hover { border-color: var(--green); color: var(--green); background: var(--green-lt); }
.vc-btn--disabled {
    opacity: .45;
    cursor: not-allowed;
    pointer-events: none;
}

/* ── PAGINATION ────────────────────────────────────────── */
.visa-pagination-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 22px;
    flex-wrap: wrap;
    gap: 12px;
}
.visa-pagination-info { font-size: 13px; color: var(--text-3); }
.visa-pagination { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; }
.visa-pagination li a,
.visa-pagination li span {
    display: grid; place-items: center;
    width: 36px; height: 36px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border: 1.5px solid var(--border);
    background: var(--surface);
    color: var(--text-2);
    transition: all .15s;
    cursor: pointer;
}
.visa-pagination li a:hover     { border-color: var(--blue); color: var(--blue); background: var(--blue-lt); }
.visa-pagination li.active a    { background: var(--grad); color: #fff; border-color: transparent; box-shadow: 0 3px 8px rgba(64,153,255,.3); }
.visa-pagination li.disabled span { color: var(--text-3); cursor: not-allowed; }

/* ── EMPTY STATE ───────────────────────────────────────── */
.visa-empty {
    background: var(--surface);
    border: 1.5px dashed var(--border);
    border-radius: var(--r);
    text-align: center;
    padding: 60px 20px;
    color: var(--text-3);
}
.visa-empty i { font-size: 40px; margin-bottom: 14px; opacity: .35; display: block; }
.visa-empty p { font-size: 14px; }

/* ── TOAST ─────────────────────────────────────────────── */
.toast-container {
    position: fixed; top: 20px; right: 20px;
    z-index: 9999; max-width: 350px;
}
.toast {
    background: #fff; border-radius: 8px;
    box-shadow: 0 8px 16px rgba(0,0,0,.15);
    margin-bottom: 10px; overflow: hidden;
    opacity: 0; transform: translateX(40px);
    transition: all .3s ease;
    border-left: 4px solid transparent;
    padding: 15px;
}
.toast-showing { opacity: 1; transform: translateX(0); }
.toast-removing { opacity: 0; transform: translateY(-20px); }
.toast-success { border-left-color: #10b981; }
.toast-error   { border-left-color: #ef4444; }
.toast-warning { border-left-color: #f59e0b; }
.toast-info    { border-left-color: #3b82f6; }
.toast-title   { display: flex; align-items: center; font-weight: 600; margin-bottom: 5px; }
.toast-message { word-break: break-word; line-height: 1.5; color: #64748b; }

/* ── CARD HEADER GRADIENT ──────────────────────────────── */
.card-header {
    background: var(--grad) !important;
    color: #fff !important;
    border-bottom: none !important;
}

/* ── ANIMATIONS ────────────────────────────────────────── */
@keyframes visaFadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
<?php for ($i = 1; $i <= 10; $i++): ?>
.visa-card:nth-child(<?= $i ?>) { animation-delay: <?= ($i * 0.035) ?>s; }
<?php endfor; ?>

/* ── RESPONSIVE ────────────────────────────────────────── */
@media (max-width: 640px) {
    .visa-card__body { grid-template-columns: 1fr; }
    .visa-card__right { align-items: flex-start; flex-direction: row; flex-wrap: wrap; }
    .vc-amount { text-align: left; }
    .vc-amount__value { font-size: 16px; }
    .visa-card__name { max-width: 200px; }
}
/* ─── FAB ──────────────────────────────────────────────── */
.pg-fab {
    position: fixed;
    bottom: 80px;
    z-index: 1050;
}

.pg-fab button {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #185FA5;
    border: none;
    color: #fff;
    font-size: 25px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(24,95,165,0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s;
}

.pg-fab button:hover {
    background: #0C447C;
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">


                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="toast-container"></div>

                        <!-- PAGE HEADER -->
                        <div class="visa-page-header">
                            <div class="visa-page-title">
                                <div class="visa-page-title-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <div>
                                    <h1><?= __('visa_applications') ?></h1>
                                    <p><?= __('manage_and_track_all_visa_applications') ?></p>
                                </div>
                            </div>
                            <div class="visa-header-actions">
                                <a href="visa_refunds.php" class="visa-refunds-link">
                                    <i class="feather icon-refresh-cw"></i> <?= __('visa_refunds') ?>
                                </a>
                                <button class="btn-visa-primary" data-toggle="modal" data-target="#addVisaModal">
                                    <i class="feather icon-plus-circle"></i> <?= __('new_visa_application') ?>
                                </button>
                            </div>
                        </div>

                        <!-- SEARCH BAR -->
                        <div class="visa-search-bar">
                            <div class="visa-search-input-wrap">
                                <i class="feather icon-search"></i>
                                <input type="text"
                                       id="searchInput"
                                       class="visa-search-input"
                                       placeholder="<?= __('search_by_passport_number_applicant_name_or_phone') ?>"
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="visa-filter-chips">
                                <span class="visa-chip <?= empty($search) ? 'active' : '' ?>" data-filter="all"><?= __('all') ?></span>
                                <span class="visa-chip" data-filter="pending"><?= __('pending') ?></span>
                                <span class="visa-chip" data-filter="approved"><?= __('approved') ?></span>
                                <span class="visa-chip" data-filter="issued"><?= __('issued') ?></span>
                                <span class="visa-chip" data-filter="rejected"><?= __('rejected') ?></span>
                                <span class="visa-chip" data-filter="cancelled"><?= __('cancelled') ?></span>
                            </div>
                        </div>

                        <!-- RESULTS BAR -->
                        <div class="visa-results-bar">
                            <p class="visa-results-count">
                                <?= __('showing') ?>
                                <strong><?= (($page - 1) * $recordsPerPage) + 1 ?>–<?= min($page * $recordsPerPage, $totalRecords) ?></strong>
                                <?= __('of') ?> <strong><?= $totalRecords ?></strong> <?= __('entries') ?>
                            </p>
                        </div>

                        <!-- VISA CARDS -->
                        <?php if (empty($visas)): ?>
                        <div class="visa-empty">
                            <i class="feather icon-inbox"></i>
                            <p><?= __('no_visa_applications_found') ?></p>
                        </div>
                        <?php else: ?>

                        <div class="visa-card-list" id="visaList">
                        <?php
                        $counter = 1;
                        foreach ($visas as $visa):
                            $status        = $visa['status'];
                            $statusLower   = strtolower($status);
                            $soldName      = $visa['sold_name'];
                            $isAgency      = ($client_types[$soldName] ?? '') === 'agency';
                            $stripeClass   = getStripeClass($status);
                            $badgeClass    = getBadgeClass($status);
                            $dotColor      = getBadgeDotColor($status);
                            $isApproved    = isApprovedOrIssued($status);
                            $isCancelled   = isCancelledEtc($status);

                            /* Payment calculation */
                            $payPercent = 0;
                            $payLabel   = '';
                            $payClass   = 'pay--none';
                            $payWidth   = 0;
                            $payColor   = 'var(--red)';

                            if ($isAgency) {
                                $baseCurrency  = $visa['currency'];
                                $soldAmount    = floatval($visa['sold']);
                                $totalPaid     = 0.0;

                                $tStmt = $pdo->prepare(
                                    "SELECT * FROM main_account_transactions
                                     WHERE transaction_of = 'visa_sale'
                                       AND reference_id = ?
                                       AND tenant_id = ?
                                       AND branch_id = ?"
                                );
                                $tStmt->bindParam(1, $visa['id'],   PDO::PARAM_INT);
                                $tStmt->bindParam(2, $tenant_id,    PDO::PARAM_INT);
                                $tStmt->bindParam(3, $branch_id,    PDO::PARAM_INT);
                                $tStmt->execute();
                                $transactions = $tStmt->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($transactions as $tx) {
                                    $amt  = floatval($tx['amount']);
                                    $tc   = $tx['currency'];
                                    $rate = (isset($tx['exchange_rate']) && $tx['exchange_rate'] > 0)
                                            ? floatval($tx['exchange_rate']) : 1.0;

                                    if ($tc === $baseCurrency) {
                                        $totalPaid += $amt;
                                    } elseif ($baseCurrency === 'AFS') {
                                        $totalPaid += $amt * $rate;
                                    } else {
                                        $totalPaid += $amt / $rate;
                                    }
                                }

                                if ($soldAmount > 0) {
                                    $payPercent = min(100, round(($totalPaid / $soldAmount) * 100));
                                }

                                if ($totalPaid <= 0) {
                                    $payClass = 'pay--none'; $payWidth = 0;
                                    $payLabel = __('unpaid'); $payColor = 'var(--red)';
                                } elseif ($totalPaid < $soldAmount - 0.01) {
                                    $payClass = 'pay--partial'; $payWidth = $payPercent;
                                    $payLabel = $payPercent . '%'; $payColor = 'var(--amber)';
                                } else {
                                    $payClass = 'pay--full'; $payWidth = 100;
                                    $payLabel = __('paid'); $payColor = 'var(--green)';
                                }
                            }
                        ?>

                        <div class="visa-card" data-status="<?= htmlspecialchars($statusLower) ?>">
                            <div class="visa-card__stripe <?= $stripeClass ?>"></div>
                            <div class="visa-card__body">

                                <!-- LEFT -->
                                <div class="visa-card__left">

                                    <!-- Row 1: counter · name · status badge · payment -->
                                    <div class="visa-card__top">
                                        <span class="visa-card__counter">#<?= str_pad($counter, 3, '0', STR_PAD_LEFT) ?></span>
                                        <span class="visa-card__name">
                                            <?= htmlspecialchars($visa['title']) ?> <?= htmlspecialchars($visa['applicant_name']) ?>
                                        </span>
                                        <span class="vc-badge <?= $badgeClass ?>">
                                            <span class="vc-badge-dot" style="background:<?= $dotColor ?>"></span>
                                            <?= htmlspecialchars($status) ?>
                                        </span>

                                        <?php if ($isAgency): ?>
                                        <div class="vc-pay-wrap" title="Payment: <?= $payPercent ?>%">
                                            <div class="vc-pay-track">
                                                <div class="vc-pay-fill <?= $payClass ?>" style="width:<?= $payWidth ?>%"></div>
                                            </div>
                                            <span class="vc-pay-label" style="color:<?= $payColor ?>"><?= $payLabel ?></span>
                                        </div>
                                        <?php else: ?>
                                        <span class="vc-pay-label" style="color:var(--text-3)">— <?= __('direct') ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Row 2: meta pills -->
                                    <div class="visa-card__meta">
                                        <span class="vc-pill">
                                            <i class="feather icon-credit-card"></i>
                                            <?= htmlspecialchars($visa['passport_number']) ?>
                                        </span>
                                        <span class="vc-pill vc-pill--blue">
                                            <i class="feather icon-globe"></i>
                                            <?= htmlspecialchars($visa['country']) ?>
                                        </span>
                                        <span class="vc-pill">
                                            <i class="feather icon-file-text"></i>
                                            <?= htmlspecialchars($visa['visa_type']) ?>
                                        </span>
                                        <span class="vc-pill">
                                            <i class="feather icon-briefcase"></i>
                                            <?= htmlspecialchars($visa['supplier_name']) ?>
                                        </span>
                                        <span class="vc-pill">
                                            <i class="feather icon-user"></i>
                                            <?= htmlspecialchars($soldName) ?>
                                        </span>
                                    </div>

                                    <!-- Row 3: dates + created-by -->
                                    <div class="visa-card__footer">
                                        <div class="vc-date">
                                            <i class="feather icon-calendar"></i>
                                            <span class="vc-date__label"><?= __('received') ?></span>
                                            <span class="vc-date__val"><?= htmlspecialchars($visa['receive_date']) ?></span>
                                        </div>
                                        <div class="vc-date">
                                            <i class="feather icon-send"></i>
                                            <span class="vc-date__label"><?= __('applied') ?></span>
                                            <span class="vc-date__val"><?= htmlspecialchars($visa['applied_date']) ?></span>
                                        </div>
                                        <?php if (!empty($visa['created_by'])): ?>
                                        <div class="vc-created">
                                            <i class="feather icon-user-check"></i>
                                            <?= __('by') ?> <strong><?= htmlspecialchars($visa['created_by']) ?></strong>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- RIGHT -->
                                <div class="visa-card__right">
                                    <div class="vc-amount">
                                        <div class="vc-amount__label"><?= __('sold') ?></div>
                                        <div class="vc-amount__value">
                                            <span class="vc-amount__currency"><?= htmlspecialchars($visa['currency']) ?></span>
                                            <?= number_format($visa['sold'], 2) ?>
                                        </div>
                                    </div>

                                    <div class="visa-card__actions">
                                        <!-- View -->
                                        <button class="vc-btn view-details"
                                                data-visa='<?= htmlspecialchars(json_encode($visa)) ?>'>
                                            <i class="feather icon-eye"></i> <?= __('view') ?>
                                        </button>

                                        <!-- Approve (Pending only) -->
                                        <?php if ($statusLower === 'pending' && $canEdit): ?>
                                        <button class="vc-btn vc-btn--success approve-visa"
                                                data-visa-id="<?= $visa['id'] ?>"
                                                title="<?= __('approve') ?>">
                                            <i class="feather icon-check"></i> <?= __('approve') ?>
                                        </button>
                                        <?php endif; ?>

                                        <!-- Edit -->
                                        <?php if ($canEdit): ?>
                                        <button class="vc-btn" onclick="editVisa(<?= $visa['id'] ?>)">
                                            <i class="feather icon-edit-2"></i> <?= __('edit') ?>
                                        </button>
                                        <?php endif; ?>

                                        <!-- Transactions (agency only) -->
                                        <?php if ($isAgency && $canEdit): ?>
                                        <button class="vc-btn vc-btn--success"
                                                onclick="openTransactionTab(<?= $visa['id'] ?>, <?= htmlspecialchars($visa['sold']) ?>, '<?= htmlspecialchars($visa['currency']) ?>')"
                                                title="<?= __('transactions') ?>">
                                            <i class="fas fa-dollar-sign"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- Refund -->
                                        <?php if ($isApproved): ?>
                                        <button class="vc-btn vc-btn--warn"
                                                onclick="openRefundModal(<?= $visa['id'] ?>, <?= htmlspecialchars($visa['sold']) ?>, <?= htmlspecialchars($visa['profit']) ?>, '<?= htmlspecialchars($visa['currency']) ?>')"
                                                title="<?= __('refund_visa') ?>">
                                            <i class="feather icon-refresh-cw"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="vc-btn vc-btn--warn vc-btn--disabled"
                                                disabled title="<?= __('cannot_refund_unapproved_visas') ?>">
                                            <i class="feather icon-refresh-cw"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- Cancel -->
                                        <?php if ($isApproved): ?>
                                        <button class="vc-btn vc-btn--danger vc-btn--disabled"
                                                disabled title="<?= __('cannot_cancel_approved_visas') ?>">
                                            <i class="feather icon-x-circle"></i>
                                        </button>
                                        <?php elseif (!$isCancelled): ?>
                                        <button class="vc-btn vc-btn--danger"
                                                onclick="openCancellationModal(<?= $visa['id'] ?>, '<?= htmlspecialchars($visa['applicant_name']) ?>', '<?= htmlspecialchars($status) ?>')"
                                                title="<?= __('cancel_visa') ?>">
                                            <i class="feather icon-x-circle"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- Re-apply (cancelled/rejected/withdrawn only) -->
                                        <?php if ($isCancelled): ?>
                                        <button class="vc-btn vc-btn--success"
                                                onclick="openReapplyModal(<?= $visa['id'] ?>, '<?= htmlspecialchars($visa['applicant_name']) ?>', <?= htmlspecialchars($visa['profit']) ?>, <?= htmlspecialchars($visa['base']) ?>, <?= htmlspecialchars($visa['sold']) ?>, '<?= htmlspecialchars($visa['currency']) ?>')">
                                            <i class="feather icon-rotate-ccw"></i> <?= __('re_apply') ?>
                                        </button>
                                        <?php endif; ?>

                                        <!-- Documents -->
                                        <button class="vc-btn"
                                                data-action="upload-docs"
                                                data-visa-id="<?= $visa['id'] ?>"
                                                title="<?= __('documents') ?>">
                                            <i class="feather icon-upload"></i>
                                        </button>

                                        <!-- Delete -->
                                        <?php if ($canEdit): ?>
                                        <button class="vc-btn vc-btn--danger"
                                                onclick="deleteVisa(<?= $visa['id'] ?>)"
                                                title="<?= __('delete') ?>">
                                            <i class="feather icon-trash-2"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <?php
                            $counter++;
                        endforeach;
                        ?>
                        </div><!-- end .visa-card-list -->
                        <?php endif; ?>

                        <!-- PAGINATION -->
                        <div class="visa-pagination-bar">
                            <p class="visa-pagination-info">
                                <?= __('showing') ?>
                                <?= (($page - 1) * $recordsPerPage) + 1 ?>
                                <?= __('to') ?>
                                <?= min($page * $recordsPerPage, $totalRecords) ?>
                                <?= __('of') ?>
                                <?= $totalRecords ?> <?= __('entries') ?>
                            </p>
                            <ul class="visa-pagination">
                                <li class="<?= $page <= 1 ? 'disabled' : '' ?>">
                                    <?php if ($page <= 1): ?>
                                    <span>&laquo;</span>
                                    <?php else: ?>
                                    <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&laquo;</a>
                                    <?php endif; ?>
                                </li>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage   = min($totalPages, $page + 2);
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                <li class="<?= $i == $page ? 'active' : '' ?>">
                                    <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>

                                <li class="<?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <?php if ($page >= $totalPages): ?>
                                    <span>&raquo;</span>
                                    <?php else: ?>
                                    <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&raquo;</a>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>

                    </div><!-- end page-wrapper -->
                </div><!-- end main-body -->

            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
<?php include '../modals/visa/documents_modal.php'; ?>
<?php include '../modals/visa/cancellation_modal.php'; ?>
<?php include '../modals/visa/details_modal.php'; ?>
<?php include '../modals/visa/add_visa_modal.php'; ?>

<?php include '../modals/visa/refund_modal.php'; ?>

<?php include '../modals/visa/reapply_modal.php'; ?>
<?php include '../modals/visa/multi_visa_modal.php'; ?>


<?php include '../modals/visa/edit_visa_modal.php'; ?>
<?php include '../modals/visa/transaction_modal.php'; ?>
<?php include '../modals/visa/edit_transaction_modal.php'; ?>
<?php
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'approved': case 'issued':   return 'success';
        case 'pending':                   return 'warning';
        case 'rejected': case 'refunded':
        case 'cancelled': case 'withdrawn': return 'danger';
        default:                          return 'secondary';
    }
}
?>

<!-- Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
<script src="../js/visa/select2.js"></script>
<script src="../js/visa/visa_details.js"></script>
<script src="../js/visa/supplier_currency.js"></script>
<script src="../js/visa/profit_calc.js"></script>
<script src="../js/visa/add_visa.js"></script>
<script src="../js/visa/edit_visa.js"></script>
<script src="../js/visa/invoice.js"></script>
<script src="../js/visa/visa_refund.js"></script>
<script src="../js/visa/transaction_manager.js"></script>
<script src="../js/visa/search.js"></script>
<script src="../js/visa/cancel_reapply.js"></script>
<script src="../js/visa/toast.js"></script>
<script src="../js/visa/document_manager.js"></script>

<script>
// ── Filter chips (client-side filter on current page) ──
document.querySelectorAll('.visa-chip').forEach(chip => {
    chip.addEventListener('click', function () {
        document.querySelectorAll('.visa-chip').forEach(c => c.classList.remove('active'));
        this.classList.add('active');

        const filter = this.dataset.filter;
        document.querySelectorAll('.visa-card').forEach(card => {
            if (filter === 'all' || card.dataset.status === filter) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// ── Search on Enter ──
document.getElementById('searchInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        const q = this.value.trim();
        window.location.href = 'visa.php?search=' + encodeURIComponent(q) + '&page=1';
    }
});
</script>

</body>
</html>