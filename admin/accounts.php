<?php
// Include security module
require_once 'security.php';

// Include secure headers helper
require_once 'includes/set_secure_headers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

// Check if user is admin - for accessing main accounts
$isAdmin = $_SESSION['role'] === 'admin';

// Make admin flag available to JavaScript
echo '<script>const isUserAdmin = ' . ($isAdmin ? 'true' : 'false') . ';</script>';

// Fetch main account balances (only for admin)
$mainAccounts = [];
$mainAccountQuery = "SELECT * FROM main_account WHERE tenant_id = ? And branch_id = ?";
$stmt = $pdo->prepare($mainAccountQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$mainAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch client accounts balances
$clientAccountQuery = "SELECT * FROM clients where status = 'active' AND tenant_id = ? And branch_id = ?";
$stmt = $pdo->prepare($clientAccountQuery);
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$clientAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch supplier accounts with their balances
$supplierQuery = "
    SELECT sa.id, sa.name AS supplier_name, sa.currency, sa.balance, sa.updated_at, sa.status, sa.supplier_type
    FROM suppliers sa where status = 'active' AND tenant_id = ? And branch_id = ?";
$supplierStmt = $pdo->prepare($supplierQuery);
$supplierStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$supplierStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$supplierStmt->execute();
$supplier = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch client accounts with their balances
$clientQuery = "
SELECT cl.id, cl.name, cl.usd_balance, cl.afs_balance, cl.updated_at, cl.status, cl.client_type
FROM clients cl where status = 'active' AND tenant_id = ? And branch_id = ?";
$clientStmt = $pdo->prepare($clientQuery);
$clientStmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$clientStmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$clientStmt->execute();
$clientAccounts = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

// Include dashboard handler for low balance alerts
require_once '../api/dashboard/supplier_notification.php';
require_once '../api/dashboard/client_notification.php';
?>

<?php include '../includes/header.php'; ?>
<meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
<link href="../css/account/styles.css" rel="stylesheet">
<link href="../assets/css/transaction-account.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="../assets/plugins/daterangepicker/daterangepicker.css" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
/* ============================================================
   DESIGN TOKENS
   ============================================================ */
:root {
  --ac-bg:          #f0f2f5;
  --ac-surface:     #ffffff;
  --ac-surface-2:   #f7f8fa;
  --ac-border:      #e2e5ea;
  --ac-text-1:      #1a1d23;
  --ac-text-2:      #5a6072;
  --ac-text-3:      #9ba3b5;
  --ac-blue:        #4099ff;
  --ac-blue-soft:   #f0f7ff;
  --ac-blue-mid:    #7fb3ff;
  --ac-green:       #16a34a;
  --ac-green-soft:  #f0fdf4;
  --ac-teal:        #0891b2;
  --ac-teal-soft:   #ecfeff;
  --ac-amber:       #d97706;
  --ac-amber-soft:  #fffbeb;
  --ac-red:         #dc2626;
  --ac-red-soft:    #fef2f2;
  --ac-radius:      10px;
  --ac-radius-lg:   16px;
  --ac-shadow-sm:   0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  --ac-shadow:      0 4px 16px rgba(0,0,0,.07), 0 1px 4px rgba(0,0,0,.04);
  --ac-shadow-lg:   0 12px 40px rgba(0,0,0,.10), 0 4px 12px rgba(0,0,0,.05);
  --ac-transition:  all .22s cubic-bezier(.4,0,.2,1);
}

/* ============================================================
    STICKY QUICK-STATS BAR
    ============================================================ */
.ac-stats-bar {
  position: sticky;
  top: 0;
  z-index: 99;
  background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
  border-bottom: 1px solid rgba(255,255,255,.07);
  display: flex;
  align-items: center;
  min-height: 50px;
  padding: 10px 16px;
  box-shadow: 0 2px 20px rgba(0,0,0,.28);
  overflow-x: auto;
  flex-wrap: wrap;
}
.ac-stats-bar::-webkit-scrollbar { display: none; }
.ac-hstat {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; min-height: 34px;
  border-right: 1px solid rgba(255,255,255,.07);
  flex-shrink: 0; cursor: default;
  margin: 2px 0;
}
.ac-hstat:last-child { border-right: none; }
.ac-hstat-label { font-size: 9px; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .4px; font-weight: 500; white-space: nowrap; }
.ac-hstat-value { font-family: 'DM Mono', monospace; font-size: 12px; color: #fff; font-weight: 500; white-space: nowrap; }
.ac-hstat-value.pos { color: #4ade80; }
.ac-hstat-value.neg { color: #f87171; }
.ac-stats-bar-spacer { flex: 1; }

/* Mobile responsive adjustments */
@media (max-width: 640px) {
  .ac-stats-bar {
    padding: 8px 12px;
    min-height: auto;
    flex-wrap: wrap;
    gap: 0;
  }
  .ac-hstat {
    padding: 6px 10px;
    border-right: none;
    border-bottom: 1px solid rgba(255,255,255,.07);
    flex: 1 1 50%;
    min-height: 32px;
    margin: 0;
  }
  .ac-hstat:nth-child(odd) {
    border-right: 1px solid rgba(255,255,255,.07);
  }
  .ac-hstat-label { font-size: 8px; letter-spacing: .3px; }
  .ac-hstat-value { font-size: 11px; }
}

/* ============================================================
   ALERTS
   ============================================================ */
.ac-alert {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 13px 16px; border-radius: var(--ac-radius);
  margin-bottom: 14px; border: 1px solid;
  animation: acFadeUp .35s ease both;
}
.ac-alert.warning { background: var(--ac-amber-soft); border-color: #fcd34d; }
.ac-alert.danger  { background: var(--ac-red-soft);   border-color: #fca5a5; }
.ac-alert-icon { font-size: 16px; margin-top: 1px; flex-shrink: 0; }
.ac-alert.warning .ac-alert-icon { color: var(--ac-amber); }
.ac-alert.danger  .ac-alert-icon { color: var(--ac-red); }
.ac-alert-title { font-weight: 600; font-size: 13px; margin-bottom: 7px; }
.ac-alert.warning .ac-alert-title { color: #92400e; }
.ac-alert.danger  .ac-alert-title { color: #991b1b; }
.ac-alert-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.ac-chip { font-size: 11.5px; font-weight: 500; padding: 3px 10px; border-radius: 20px; }
.ac-alert.warning .ac-chip { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
.ac-alert.danger  .ac-chip { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.ac-alert-close { margin-left: auto; background: none; border: none; cursor: pointer; color: var(--ac-text-3); font-size: 16px; padding: 0 0 0 10px; flex-shrink: 0; transition: var(--ac-transition); }
.ac-alert-close:hover { color: var(--ac-text-1); }

/* ============================================================
   FILTER BAR
   ============================================================ */
.ac-filter-bar {
  background: var(--ac-surface); border: 1px solid var(--ac-border);
  border-radius: var(--ac-radius); padding: 12px 16px;
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 22px; box-shadow: var(--ac-shadow-sm);
  flex-wrap: wrap; animation: acFadeUp .38s ease both;
}
.ac-search-wrap { flex: 1; min-width: 200px; position: relative; }
.ac-search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--ac-text-3); font-size: 12px; }
.ac-search-input {
  width: 100%; padding: 8px 12px 8px 30px;
  border: 1px solid var(--ac-border); border-radius: 7px;
  font-size: 13px; background: var(--ac-surface-2); color: var(--ac-text-1);
  transition: var(--ac-transition); outline: none;
}
.ac-search-input:focus { border-color: var(--ac-blue-mid); background: #fff; box-shadow: 0 0 0 3px var(--ac-blue-soft); }
.ac-filter-select {
  padding: 8px 12px; border: 1px solid var(--ac-border); border-radius: 7px;
  font-size: 13px; background: var(--ac-surface-2); color: var(--ac-text-1);
  cursor: pointer; outline: none; transition: var(--ac-transition);
}
.ac-filter-select:focus { border-color: var(--ac-blue-mid); box-shadow: 0 0 0 3px var(--ac-blue-soft); }

/* ============================================================
   SECTION
   ============================================================ */
.ac-section { margin-bottom: 26px; animation: acFadeUp .42s ease both; }
.ac-section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; padding: 0 2px; }
.ac-section-title-group { display: flex; align-items: center; gap: 9px; }
.ac-section-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
.ac-section-title { font-size: 15px; font-weight: 600; color: var(--ac-text-1); letter-spacing: -.2px; }
.ac-section-count { font-size: 11.5px; background: var(--ac-surface-2); border: 1px solid var(--ac-border); color: var(--ac-text-2); padding: 2px 9px; border-radius: 20px; font-weight: 500; }
.ac-section-actions { display: flex; gap: 7px; align-items: center; }
.ac-collapse-btn { width: 28px; height: 28px; border-radius: 6px; background: var(--ac-surface); border: 1px solid var(--ac-border); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--ac-text-2); transition: var(--ac-transition); font-size: 11px; }
.ac-collapse-btn:hover { background: var(--ac-surface-2); color: var(--ac-text-1); }
.ac-collapse-btn i { transition: transform .22s ease; }
.ac-collapse-btn.collapsed i { transform: rotate(-90deg); }
.ac-section-body.collapsed { display: none; }

/* ============================================================
   STAT STRIP
   ============================================================ */
.ac-stat-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 14px; }
.ac-stat-tile { background: var(--ac-surface); border: 1px solid var(--ac-border); border-radius: var(--ac-radius); padding: 13px 15px; box-shadow: var(--ac-shadow-sm); transition: var(--ac-transition); position: relative; overflow: hidden; }
.ac-stat-tile::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.ac-stat-tile.blue::before  { background: var(--ac-blue); }
.ac-stat-tile.green::before { background: var(--ac-green); }
.ac-stat-tile.teal::before  { background: var(--ac-teal); }
.ac-stat-tile.red::before   { background: var(--ac-red); }
.ac-stat-tile:hover { box-shadow: var(--ac-shadow); transform: translateY(-1px); }
.ac-stat-label { font-size: 11px; color: var(--ac-text-3); text-transform: uppercase; letter-spacing: .4px; font-weight: 500; margin-bottom: 5px; }
.ac-stat-value { font-family: 'DM Mono', monospace; font-size: 15px; font-weight: 500; color: var(--ac-text-1); }
.ac-stat-value.green { color: var(--ac-green); }
.ac-stat-value.teal  { color: var(--ac-teal); }
.ac-stat-value.red   { color: var(--ac-red); }

/* ============================================================
   PILL FILTERS
   ============================================================ */
.ac-pill-row { display: flex; gap: 7px; margin-bottom: 13px; flex-wrap: wrap; align-items: center; }
.ac-pill-label { font-size: 11.5px; color: var(--ac-text-3); font-weight: 500; }
.ac-pill { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; cursor: pointer; border: 1px solid var(--ac-border); background: var(--ac-surface); color: var(--ac-text-2); transition: var(--ac-transition); user-select: none; }
.ac-pill:hover, .ac-pill.active { background: var(--ac-blue); border-color: var(--ac-blue); color: #fff; }
.ac-pill-search { flex: 0 0 200px; position: relative; }
.ac-pill-search i { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); color: var(--ac-text-3); font-size: 11px; }
.ac-pill-search input { width: 100%; padding: 5px 10px 5px 28px; border: 1px solid var(--ac-border); border-radius: 20px; font-size: 12px; background: var(--ac-surface-2); color: var(--ac-text-1); outline: none; transition: var(--ac-transition); }
.ac-pill-search input:focus { border-color: var(--ac-blue-mid); background: #fff; }

/* ============================================================
   MAIN ACCOUNT CARDS (grid)
   ============================================================ */
.ac-main-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.ac-main-card { background: var(--ac-surface); border: 1px solid var(--ac-border); border-radius: var(--ac-radius-lg); overflow: visible; box-shadow: var(--ac-shadow-sm); transition: var(--ac-transition); animation: acFadeUp .4s ease both; }
.ac-main-card:hover { box-shadow: var(--ac-shadow); transform: translateY(-2px); }
.ac-main-card.inactive { opacity: .72; }
.ac-mc-head { padding: 15px 16px 11px; border-bottom: 1px solid var(--ac-border); display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.ac-mc-head-left { display: flex; gap: 10px; align-items: flex-start; flex: 1; min-width: 0; }
.ac-mc-icon { width: 34px; height: 34px; border-radius: 9px; background: var(--ac-blue-soft); color: var(--ac-blue); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ac-mc-name { font-weight: 600; font-size: 13.5px; color: var(--ac-text-1); }
.ac-mc-meta { font-size: 11px; color: var(--ac-text-3); margin-top: 2px; }
.ac-mc-badge { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 20px; flex-shrink: 0; white-space: nowrap; }
.ac-mc-badge.active   { background: var(--ac-green-soft); color: var(--ac-green); }
.ac-mc-badge.inactive { background: var(--ac-red-soft);   color: var(--ac-red); }
.ac-mc-balances { display: grid; grid-template-columns: 1fr 1fr; gap: 1px; background: var(--ac-border); border-bottom: 1px solid var(--ac-border); }
.ac-mc-bal-cell { background: var(--ac-surface); padding: 11px 14px; }
.ac-mc-bal-cur { font-size: 10.5px; color: var(--ac-text-3); text-transform: uppercase; letter-spacing: .4px; font-weight: 500; margin-bottom: 3px; }
.ac-mc-bal-val { font-family: 'DM Mono', monospace; font-size: 13.5px; font-weight: 500; }
.ac-mc-bal-val.usd { color: var(--ac-green); }
.ac-mc-bal-val.afs { color: var(--ac-teal); }
.ac-mc-bal-val.eur { color: var(--ac-blue); }
.ac-mc-bal-val.aed { color: var(--ac-amber); }
.ac-mc-gauge { display: flex; align-items: center; gap: 10px; padding: 9px 14px; background: var(--ac-surface-2); border-bottom: 1px solid var(--ac-border); }
.ac-mc-gauge-label { font-size: 11px; color: var(--ac-text-3); font-weight: 500; flex-shrink: 0; }
.ac-mc-gauge-track { flex: 1; height: 5px; background: var(--ac-border); border-radius: 99px; overflow: visible; }
.ac-mc-gauge-fill { height: 100%; border-radius: 99px; transition: width .7s cubic-bezier(.4,0,.2,1); }
.ac-mc-gauge-pct { font-size: 11px; font-family: 'DM Mono', monospace; color: var(--ac-text-2); font-weight: 500; flex-shrink: 0; }
.ac-mc-fund { padding: 12px 14px; display: flex; gap: 7px; align-items: center; border-bottom: 1px solid var(--ac-border); }
.ac-mc-fund select, .ac-mc-fund input { border: 1px solid var(--ac-border); border-radius: 7px; padding: 6px 10px; font-size: 12.5px; color: var(--ac-text-1); background: var(--ac-surface-2); outline: none; transition: var(--ac-transition); }
.ac-mc-fund select:focus, .ac-mc-fund input:focus { border-color: var(--ac-blue-mid); background: #fff; }
.ac-mc-fund select { width: 80px; flex-shrink: 0; }
.ac-mc-fund input { flex: 1; min-width: 0; }
.ac-mc-last-updated { padding: 6px 14px 8px; font-size: 11px; color: var(--ac-text-3); }
.ac-mc-footer { padding: 10px 14px 13px; display: flex; flex-direction: column; gap: 8px; background: var(--ac-surface-2); }
.ac-mc-footer .view-transactions-btn { width: 100%; justify-content: center; font-size: 12px; }
.ac-mc-footer-row { display: flex; gap: 6px; }
.ac-mc-footer-row .btn { flex: 1; min-width: 0; justify-content: center; font-size: 12px; }

/* ============================================================
    HORIZONTAL LIST CARD
    ============================================================ */
.ac-list-card { background: var(--ac-surface); border: 1px solid var(--ac-border); border-radius: var(--ac-radius); overflow: visible; transition: var(--ac-transition); box-shadow: var(--ac-shadow-sm); margin-bottom: 7px; animation: acFadeUp .4s ease both; position: relative; z-index: 1; }
.ac-list-card:nth-child(1) { animation-delay:.04s; }
.ac-list-card:nth-child(2) { animation-delay:.08s; }
.ac-list-card:nth-child(3) { animation-delay:.12s; }
.ac-list-card:nth-child(4) { animation-delay:.16s; }
.ac-list-card:nth-child(5) { animation-delay:.20s; }
.ac-list-card:nth-child(6) { animation-delay:.24s; }
.ac-list-card:hover { box-shadow: var(--ac-shadow); border-color: var(--ac-blue-mid); transform: translateX(3px); }
.ac-list-card.inactive { opacity: .72; }
.ac-list-card.dropdown-open { box-shadow: var(--ac-shadow-lg); border-color: var(--ac-blue-mid); padding-bottom: 12px; }
.ac-lc-inner { display: flex; align-items: center; padding: 13px 16px; gap: 14px; flex-wrap: wrap; }
.ac-lc-avatar { width: 36px; height: 36px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ac-lc-avatar.supplier { background: var(--ac-teal-soft); color: var(--ac-teal); }
.ac-lc-avatar.client   { background: var(--ac-green-soft); color: var(--ac-green); }
.ac-lc-avatar.inactive { background: #f1f5f9; color: var(--ac-text-3); }
.ac-lc-name-group { flex: 1.4; min-width: 110px; }
.ac-lc-name { font-weight: 600; font-size: 13px; color: var(--ac-text-1); }
.ac-lc-sub  { font-size: 11px; color: var(--ac-text-3); margin-top: 2px; }
.ac-lc-badges { display: flex; gap: 5px; flex-wrap: wrap; min-width: 70px; }
.ac-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px; white-space: nowrap; }
.ac-badge.usd     { background: var(--ac-green-soft); color: var(--ac-green); }
.ac-badge.afs     { background: var(--ac-teal-soft);  color: var(--ac-teal); }
.ac-badge.active  { background: var(--ac-green-soft); color: var(--ac-green); }
.ac-badge.inactive{ background: var(--ac-red-soft);   color: var(--ac-red); }
.ac-lc-balances { display: flex; gap: 18px; flex: 2; min-width: 0; }
.ac-lc-bal { min-width: 90px; }
.ac-lc-bal-label { font-size: 10.5px; color: var(--ac-text-3); text-transform: uppercase; letter-spacing: .4px; font-weight: 500; }
.ac-lc-bal-value { font-family: 'DM Mono', monospace; font-size: 13px; font-weight: 500; margin-top: 2px; }
.ac-lc-bal-value.pos-usd { color: var(--ac-green); }
.ac-lc-bal-value.pos-afs { color: var(--ac-teal); }
.ac-lc-bal-value.neg     { color: var(--ac-red); }
.ac-lc-bal-value.zero    { color: var(--ac-text-3); }
.ac-lc-bar { flex: 1; min-width: 80px; max-width: 130px; }
.ac-lc-bar-label { font-size: 10.5px; color: var(--ac-text-3); margin-bottom: 4px; font-weight: 500; }
.ac-lc-bar-bg  { height: 5px; background: var(--ac-border); border-radius: 99px; overflow: hidden; }
.ac-lc-bar-fill { height: 100%; border-radius: 99px; transition: width .6s cubic-bezier(.4,0,.2,1); }
.ac-lc-bar-fill.pos { background: var(--ac-green); }
.ac-lc-bar-fill.neg { background: var(--ac-red); }
.ac-lc-bar-pct { font-size: 10px; color: var(--ac-text-3); margin-top: 3px; font-family: 'DM Mono', monospace; }
.ac-lc-date { font-size: 11px; color: var(--ac-text-3); white-space: nowrap; min-width: 75px; text-align: right; }
.ac-lc-actions { display: flex; gap: 6px; flex-shrink: 0; align-items: center; }
.ac-lc-actions .btn { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
.ac-lc-actions .btn-sm { padding: 0.375rem 0.75rem; font-size: 12px; }
.ac-lc-actions-expanded { gap: 4px; }
.ac-lc-actions-expanded .btn-sm { 
  padding: 0.4rem 0.55rem; 
  min-width: 36px; 
  height: 36px;
  justify-content: center; 
  align-items: center;
  border-radius: 7px;
  font-size: 13px;
  transition: all 0.2s ease;
  box-shadow: 0 1px 3px rgba(0,0,0,.08);
}
.ac-lc-actions-expanded .btn-sm:hover {
  transform: translateY(-2px);
  box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.ac-lc-actions-expanded .btn-sm:active {
  transform: translateY(0);
}
.ac-lc-actions-expanded .btn-primary { background: #2563eb; border-color: #2563eb; color: white; }
.ac-lc-actions-expanded .btn-primary:hover { background: #1d4ed8; border-color: #1d4ed8; }
.ac-lc-actions-expanded .btn-info { background: #0891b2; border-color: #0891b2; color: white; }
.ac-lc-actions-expanded .btn-info:hover { background: #0e7490; border-color: #0e7490; }
.ac-lc-actions-expanded .btn-outline-primary { border: 1.5px solid #2563eb; color: #2563eb; background: transparent; }
.ac-lc-actions-expanded .btn-outline-primary:hover { background: #eff4ff; }
.ac-lc-actions-expanded .btn-outline-secondary { border: 1.5px solid #6b7280; color: #374151; background: transparent; }
.ac-lc-actions-expanded .btn-outline-secondary:hover { background: #f3f4f6; }
.ac-lc-actions-expanded .btn-outline-danger { border: 1.5px solid #dc2626; color: #dc2626; background: transparent; }
.ac-lc-actions-expanded .btn-outline-danger:hover { background: #fef2f2; }
.ac-lc-actions-expanded .btn-outline-success { border: 1.5px solid #16a34a; color: #16a34a; background: transparent; }
.ac-lc-actions-expanded .btn-outline-success:hover { background: #f0fdf4; }

/* Dropdown menu styling */
.dropdown-menu { 
  max-width: 200px;
  z-index: 1000;
  position: absolute;
  top: 100%;
  right: 0;
}
.ac-list-card .dropdown { position: relative; }
.ac-list-card .dropdown-menu { 
  margin-top: 4px;
  box-shadow: 0 4px 12px rgba(0,0,0,.15);
}
.dropdown-item { white-space: normal; word-wrap: break-word; padding: 0.5rem 1rem; font-size: 13px; }
.dropdown-item i { margin-right: 6px; }

/* ============================================================
    EMPTY STATE
    ============================================================ */
.ac-empty { padding: 36px 20px; text-align: center; color: var(--ac-text-3); }
.ac-empty i { font-size: 28px; margin-bottom: 9px; display: block; }
.ac-empty p { font-size: 13px; }

/* ============================================================
    ANIMATIONS
    ============================================================ */
@keyframes acFadeUp {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ============================================================
    RESPONSIVE
    ============================================================ */
@media (max-width: 991px) {
  .ac-lc-bar, .ac-lc-date { display: none; }
  .ac-lc-balances { gap: 12px; }
}

@media (max-width: 768px) {
  .ac-main-grid { grid-template-columns: 1fr; }
  .ac-lc-inner { padding: 10px 12px; gap: 10px; }
  .ac-lc-name-group { flex: 1; }
  .ac-lc-balances { flex: 1 1 100%; gap: 12px; margin-top: 8px; }
  .ac-lc-bar { display: none; }
  .ac-lc-date { display: none; }
  .ac-lc-actions { flex: 1 1 100%; margin-top: 6px; flex-wrap: wrap; }
  .ac-lc-actions .btn { flex: 1; min-width: 100px; }
  .ac-pill-row { flex-wrap: wrap; }
  .ac-pill-search { flex: 0 0 100%; margin-top: 8px; }
}

@media (max-width: 640px) {
  .ac-lc-badges { display: none; }
  .ac-stat-strip { grid-template-columns: 1fr 1fr; }
  .ac-main-grid { grid-template-columns: 1fr; }
  .ac-lc-inner { flex-direction: column; align-items: flex-start; }
  .ac-lc-avatar { margin-bottom: 4px; }
  .ac-lc-name-group { flex: 1 1 100%; }
  .ac-lc-balances { flex: 1 1 100%; flex-direction: column; gap: 8px; margin-top: 8px; }
  .ac-lc-bal { min-width: auto; }
  .ac-lc-actions { flex: 1 1 100%; margin-top: 8px; }
  .ac-lc-actions .btn { width: 100%; max-width: none; white-space: normal; }
  .ac-lc-actions .dropdown { width: 100%; }
  .dropdown-menu { position: fixed !important; max-width: calc(100vw - 16px); left: 8px !important; right: 8px !important; }
  .mc-fund { flex-direction: column; }
  .ac-mc-fund select, .ac-mc-fund input { width: 100%; }
  .ac-mc-footer { flex-direction: column; }
  .ac-mc-footer .btn { width: 100%; }
}
</style>

<?php
// Pre-compute totals for the stats bar
$totalMainUSD = array_sum(array_column($mainAccounts, 'usd_balance'));
$totalMainAFS = array_sum(array_column($mainAccounts, 'afs_balance'));

$totalSupUSD = 0; $totalSupAFS = 0; $totalSupDueUSD = 0; $totalSupDueAFS = 0;
foreach ($supplier as $s) {
    if ($s['currency'] === 'USD' && $s['balance'] > 0) $totalSupUSD    += $s['balance'];
    if ($s['currency'] === 'AFS' && $s['balance'] > 0) $totalSupAFS    += $s['balance'];
    if ($s['currency'] === 'USD' && $s['balance'] < 0) $totalSupDueUSD += $s['balance'];
    if ($s['currency'] === 'AFS' && $s['balance'] < 0) $totalSupDueAFS += $s['balance'];
}
$totalClientDueUSD = 0; $totalClientDueAFS = 0;
foreach ($clientAccounts as $c) {
    if ($c['usd_balance'] < 0) $totalClientDueUSD += $c['usd_balance'];
    if ($c['afs_balance'] < 0) $totalClientDueAFS += $c['afs_balance'];
}
$activeCount = count($mainAccounts) + count($supplier) + count($clientAccounts);
?>



<!-- PCODED WRAPPER — exact original structure -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <!-- STICKY QUICK-STATS BAR -->
        <div class="ac-stats-bar">
            <?php if ($isAdmin): ?>
            <div class="ac-hstat">
                <span class="ac-hstat-label">Main USD</span>
                <span class="ac-hstat-value pos">$<?= number_format($totalMainUSD, 2) ?></span>
            </div>
            <div class="ac-hstat">
                <span class="ac-hstat-label">Main AFS</span>
                <span class="ac-hstat-value pos">؋<?= number_format($totalMainAFS, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="ac-hstat">
                <span class="ac-hstat-label">Supplier USD</span>
                <span class="ac-hstat-value pos">$<?= number_format($totalSupUSD, 2) ?></span>
            </div>
            <div class="ac-hstat">
                <span class="ac-hstat-label">Supplier AFS</span>
                <span class="ac-hstat-value pos">؋<?= number_format($totalSupAFS, 2) ?></span>
            </div>
            <div class="ac-hstat">
                <span class="ac-hstat-label">Client Due USD</span>
                <span class="ac-hstat-value <?= $totalClientDueUSD < 0 ? 'neg' : 'pos' ?>">$<?= number_format($totalClientDueUSD, 2) ?></span>
            </div>
            <div class="ac-hstat">
                <span class="ac-hstat-label">Client Due AFS</span>
                <span class="ac-hstat-value <?= $totalClientDueAFS < 0 ? 'neg' : 'pos' ?>">؋<?= number_format($totalClientDueAFS, 2) ?></span>
            </div>
            <div class="ac-hstat">
                <span class="ac-hstat-label">Active Accounts</span>
                <span class="ac-hstat-value"><?= $activeCount ?></span>
            </div>
            <div class="ac-stats-bar-spacer"></div>
        </div>
        <div class="pcoded-content">   
            <div class="pcoded-inner-content">
                
                <!-- breadcrumb — untouched -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-header-title">
                                    <h5 class="m-b-10"><?= __('accounts_management') ?></h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><?= __('accounts') ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-info" type="button" id="watchTutorialsBtn" data-toggle="modal" data-target="#accountsTutorialsModal">
                                    <i class="feather icon-play-circle mr-1"></i>Watch Tutorials
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- breadcrumb end -->

                <div class="main-body">
                    <div class="page-wrapper">

                        <!-- ALERTS -->
                        <?php if (!empty($suppliersWithLowBalance)): ?>
                        <div class="ac-alert warning" id="acAlertSupplier">
                            <div class="ac-alert-icon"><i class="feather icon-alert-triangle"></i></div>
                            <div style="flex:1">
                                <div class="ac-alert-title">Low Supplier Balance Alert</div>
                                <div class="ac-alert-chips">
                                    <?php foreach ($suppliersWithLowBalance as $lowBalanceSupplier):
                                        $cs  = ($lowBalanceSupplier['currency'] === 'USD') ? '$' : '؋';
                                        $thr = ($lowBalanceSupplier['currency'] === 'USD') ? '$500' : '؋20,000';
                                    ?>
                                    <span class="ac-chip">
                                        <?= htmlspecialchars($lowBalanceSupplier['name']) ?> —
                                        <?= $lowBalanceSupplier['currency'] ?>: <?= $cs . number_format($lowBalanceSupplier['balance'], 2) ?>
                                        (Threshold: <?= $thr ?>)
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button class="ac-alert-close" onclick="this.closest('.ac-alert').remove()"><i class="feather icon-x"></i></button>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($clientsWithLowBalance)): ?>
                        <div class="ac-alert danger" id="acAlertClient">
                            <div class="ac-alert-icon"><i class="feather icon-alert-circle"></i></div>
                            <div style="flex:1">
                                <div class="ac-alert-title">Client Balance Due Alert</div>
                                <div class="ac-alert-chips">
                                    <?php foreach ($clientsWithLowBalance as $lbc):
                                        $items = [];
                                        if ($lbc['usd_balance'] < -1000)  $items[] = 'USD: $' . number_format($lbc['usd_balance'], 2) . ' (Threshold: $-1,000)';
                                        if ($lbc['afs_balance'] < -20000) $items[] = 'AFS: ؋' . number_format($lbc['afs_balance'], 2) . ' (Threshold: ؋-20,000)';
                                    ?>
                                    <span class="ac-chip">
                                        <?= htmlspecialchars($lbc['name']) ?> — <?= implode(' | ', $items) ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button class="ac-alert-close" onclick="this.closest('.ac-alert').remove()"><i class="feather icon-x"></i></button>
                        </div>
                        <?php endif; ?>

                        <!-- GLOBAL FILTER BAR -->
                        <div class="ac-filter-bar">
                            <div class="ac-search-wrap">
                                <i class="feather icon-search"></i>
                                <input type="text" id="accountSearchInput" class="ac-search-input" placeholder="<?= __('search_accounts') ?>…">
                            </div>
                            <select class="ac-filter-select" id="accountTypeFilter">
                                <option value="all"><?= __('all_account_types') ?></option>
                                <option value="main"><?= __('main_accounts') ?></option>
                                <option value="supplier"><?= __('supplier_accounts') ?></option>
                                <option value="client"><?= __('client_accounts') ?></option>
                            </select>
                            <select class="ac-filter-select" id="statusFilter">
                                <option value="all"><?= __('all_statuses') ?></option>
                                <option value="active"><?= __('active') ?></option>
                                <option value="inactive"><?= __('inactive') ?></option>
                            </select>
                        </div>

                        <!-- ==========================================
                             INTERNAL / MAIN ACCOUNTS  (admin only)
                             ========================================== -->
                        <?php if ($isAdmin): ?>
                        <div class="ac-section" id="acMainSection">
                            <div class="ac-section-header">
                                <div class="ac-section-title-group">
                                    <div class="ac-section-dot" style="background:var(--ac-blue)"></div>
                                    <div class="ac-section-title"><?= __('internal_accounts') ?></div>
                                    <div class="ac-section-count"><?= count($mainAccounts) ?> <?= __('accounts') ?></div>
                                </div>
                                <div class="ac-section-actions">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#transferModal">
                                        <i class="feather icon-exchange"></i> <?= __('transfer_balance') ?>
                                    </button>
                                    <button id="addMainAccountBtn" class="btn btn-primary btn-sm">
                                        <i class="feather icon-plus"></i> <?= __('add_account') ?>
                                    </button>
                                    <button class="ac-collapse-btn" id="acMainCollapseBtn" onclick="acToggleSection('acMainBody','acMainCollapseBtn')">
                                        <i class="feather icon-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="ac-section-body" id="acMainBody">
                                <div class="ac-main-grid">
                                    <?php foreach ($mainAccounts as $account):
                                        $isInactive = isset($account['status']) && $account['status'] === 'inactive';
                                        $healthPct  = min(100, max(0, round(($account['usd_balance'] / 200000) * 100)));
                                        $healthColor = $healthPct >= 60 ? 'var(--ac-green)' : ($healthPct >= 30 ? 'var(--ac-amber)' : 'var(--ac-red)');
                                    ?>
                                    <div class="ac-main-card <?= $isInactive ? 'inactive' : '' ?>">

                                        <div class="ac-mc-head">
                                            <div class="ac-mc-head-left">
                                                <div class="ac-mc-icon" <?= (!isset($account['account_type']) || $account['account_type'] !== 'bank') ? 'style="background:var(--ac-green-soft);color:var(--ac-green)"' : '' ?>>
                                                    <i class="feather <?= isset($account['account_type']) && $account['account_type'] === 'bank' ? 'icon-credit-card' : 'icon-briefcase' ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="ac-mc-name"><?= htmlspecialchars($account['name']) ?></div>
                                                    <div class="ac-mc-meta">
                                                        <?php if (isset($account['account_type'])): ?>
                                                            <?= ucfirst(htmlspecialchars($account['account_type'])) ?> Account
                                                        <?php endif; ?>
                                                        <?php if (!empty($account['bank_account_number'])): ?>
                                                            &nbsp;·&nbsp;USD #<?= htmlspecialchars($account['bank_account_number']) ?>
                                                        <?php endif; ?>
                                                        <?php if (!empty($account['bank_account_afs_number'])): ?>
                                                            &nbsp;·&nbsp;AFS #<?= htmlspecialchars($account['bank_account_afs_number']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <span class="ac-mc-badge <?= $isInactive ? 'inactive' : 'active' ?>">
                                                <?= isset($account['status']) ? ucfirst($account['status']) : 'Active' ?>
                                            </span>
                                        </div>

                                        <div class="ac-mc-balances">
                                            <div class="ac-mc-bal-cell">
                                                <div class="ac-mc-bal-cur"><?= __('usd_balance') ?></div>
                                                <div class="ac-mc-bal-val usd">$<?= number_format($account['usd_balance'], 2) ?></div>
                                            </div>
                                            <div class="ac-mc-bal-cell">
                                                <div class="ac-mc-bal-cur"><?= __('afs_balance') ?></div>
                                                <div class="ac-mc-bal-val afs">؋<?= number_format($account['afs_balance'], 2) ?></div>
                                            </div>
                                            <div class="ac-mc-bal-cell">
                                                <div class="ac-mc-bal-cur"><?= __('euro_balance') ?></div>
                                                <div class="ac-mc-bal-val eur">€<?= number_format($account['euro_balance'], 2) ?></div>
                                            </div>
                                            <div class="ac-mc-bal-cell">
                                                <div class="ac-mc-bal-cur"><?= __('aed_balance') ?></div>
                                                <div class="ac-mc-bal-val aed">AED <?= number_format($account['darham_balance'], 2) ?></div>
                                            </div>
                                        </div>

                                        <div class="ac-mc-fund">
                                            <select id="currency-<?= $account['id'] ?>">
                                                <option value="USD"><?= __('usd') ?></option>
                                                <option value="AFS"><?= __('afs') ?></option>
                                                <option value="EUR"><?= __('eur') ?></option>
                                                <option value="DARHAM"><?= __('darham') ?></option>
                                            </select>
                                            <input type="number" id="amount-<?= $account['id'] ?>" placeholder="Enter amount" <?= $isInactive ? 'disabled' : '' ?>>
                                            <button class="btn btn-primary btn-sm fund-account-btn" data-account-id="<?= $account['id'] ?>" <?= $isInactive ? 'disabled style="opacity:.5"' : '' ?>>
                                                <i class="feather icon-plus-circle"></i> <?= __('fund') ?>
                                            </button>
                                        </div>

                                        <div class="ac-mc-last-updated">
                                            <?= __('last_updated') ?>: <?= htmlspecialchars($account['last_updated']) ?>
                                        </div>

                                        <div class="ac-mc-footer">
                                             <button class="btn btn-outline-primary btn-sm action-btn view-transactions-btn"
                                                     data-account-id="<?= $account['id'] ?>"
                                                     data-account-name="<?= htmlspecialchars($account['name']) ?>">
                                                 <i class="feather icon-list mr-1"></i> <?= __('view_transactions') ?>
                                             </button>
                                             <div class="ac-mc-footer-row">
                                                 <button class="btn btn-outline-info btn-sm action-btn edit-main-account-btn"
                                                         data-account-id="<?= $account['id'] ?>"
                                                         data-account-name="<?= htmlspecialchars($account['name']) ?>">
                                                     <i class="feather icon-edit mr-1"></i> <?= __('edit_account') ?>
                                                 </button>
                                                 <button class="btn btn-outline-<?= $isInactive ? 'success' : 'danger' ?> btn-sm action-btn toggle-status-btn"
                                                         data-account-id="<?= $account['id'] ?>"
                                                         data-current-status="<?= isset($account['status']) ? $account['status'] : 'active' ?>">
                                                     <i class="feather icon-<?= $isInactive ? 'check-circle' : 'power' ?> mr-1"></i>
                                                     <?= $isInactive ? __('activate') : __('deactivate') ?> <?= __('account') ?>
                                                 </button>
                                             </div>
                                         </div>

                                    </div><!-- /ac-main-card -->
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- ==========================================
                             SUPPLIER ACCOUNTS
                             ========================================== -->
                        <div class="ac-section" id="acSupplierSection">
                            <div class="ac-section-header">
                                <div class="ac-section-title-group">
                                    <div class="ac-section-dot" style="background:var(--ac-teal)"></div>
                                    <div class="ac-section-title"><?= __('supplier_accounts') ?></div>
                                    <div class="ac-section-count"><?= count($supplier) ?> <?= __('accounts') ?></div>
                                </div>
                                <div class="ac-section-actions">
                                    <button class="ac-collapse-btn" id="acSupplierCollapseBtn" onclick="acToggleSection('acSupplierBody','acSupplierCollapseBtn')">
                                        <i class="feather icon-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="ac-section-body" id="acSupplierBody">

                                <div class="ac-stat-strip">
                                    <div class="ac-stat-tile teal">
                                        <div class="ac-stat-label"><?= __('total_usd') ?></div>
                                        <div class="ac-stat-value green">$<?= number_format($totalSupUSD, 2) ?></div>
                                    </div>
                                    <div class="ac-stat-tile teal">
                                        <div class="ac-stat-label"><?= __('total_afs') ?></div>
                                        <div class="ac-stat-value teal">؋<?= number_format($totalSupAFS, 2) ?></div>
                                    </div>
                                    <div class="ac-stat-tile red">
                                        <div class="ac-stat-label"><?= __('total_usd_due') ?></div>
                                        <div class="ac-stat-value red">$<?= number_format($totalSupDueUSD, 2) ?></div>
                                    </div>
                                    <div class="ac-stat-tile red">
                                        <div class="ac-stat-label"><?= __('total_afs_due') ?></div>
                                        <div class="ac-stat-value red">؋<?= number_format($totalSupDueAFS, 2) ?></div>
                                    </div>
                                </div>

                                <div class="ac-pill-row">
                                    <span class="ac-pill-label">Filter:</span>
                                    <span class="ac-pill active" onclick="acPill(this,'acSupplierList','currency','all')">All</span>
                                    <span class="ac-pill" onclick="acPill(this,'acSupplierList','currency','USD')">USD</span>
                                    <span class="ac-pill" onclick="acPill(this,'acSupplierList','currency','AFS')">AFS</span>
                                    <span class="ac-pill" onclick="acPill(this,'acSupplierList','balance','positive')"><?= __('positive_balance') ?></span>
                                    <span class="ac-pill" onclick="acPill(this,'acSupplierList','balance','negative')"><?= __('negative_balance') ?></span>
                                    <div class="ac-pill-search">
                                        <i class="feather icon-search"></i>
                                        <input type="text" id="supplierSearchInput" placeholder="<?= __('search_suppliers') ?>…"
                                               oninput="acSearchList(this.value,'acSupplierList','supplier-name')">
                                    </div>
                                </div>

                                <div id="acSupplierList">
                                    <?php if (empty($supplier)): ?>
                                    <div class="ac-empty">
                                        <i class="feather icon-users"></i>
                                        <p><?= __('no_supplier_accounts_found') ?></p>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($supplier as $row):
                                        $isInactive = isset($row['status']) && $row['status'] === 'inactive';
                                        $sym        = $row['currency'] === 'USD' ? '$' : '؋';
                                        $balClass   = $row['balance'] > 0 ? ($row['currency'] === 'USD' ? 'pos-usd' : 'pos-afs') : ($row['balance'] < 0 ? 'neg' : 'zero');
                                        $barPct     = $row['balance'] >= 0
                                                      ? min(100, round(($row['balance'] / ($row['currency']==='USD' ? 50000 : 500000)) * 100))
                                                      : min(100, round((abs($row['balance']) / ($row['currency']==='USD' ? 5000 : 50000)) * 100));
                                        $barClass   = $row['balance'] >= 0 ? 'pos' : 'neg';
                                    ?>
                                    <div class="ac-list-card <?= $isInactive ? 'inactive' : '' ?>"
                                         data-supplier-name="<?= htmlspecialchars($row['supplier_name']) ?>"
                                         data-currency="<?= htmlspecialchars($row['currency']) ?>"
                                         data-balance="<?= $row['balance'] ?>"
                                         data-status="<?= isset($row['status']) ? $row['status'] : 'active' ?>">
                                        <div class="ac-lc-inner">
                                            <div class="ac-lc-avatar <?= $isInactive ? 'inactive' : 'supplier' ?>">
                                                <i class="feather icon-user-check"></i>
                                            </div>
                                            <div class="ac-lc-name-group">
                                                <div class="ac-lc-name"><?= htmlspecialchars($row['supplier_name']) ?></div>
                                                <div class="ac-lc-sub"><?= date('M d, Y H:i', strtotime($row['updated_at'])) ?></div>
                                            </div>
                                            <div class="ac-lc-badges">
                                                <span class="ac-badge <?= strtolower($row['currency']) ?>"><?= htmlspecialchars($row['currency']) ?></span>
                                                <span class="ac-badge <?= $isInactive ? 'inactive' : 'active' ?>"><?= $isInactive ? 'Inactive' : 'Active' ?></span>
                                            </div>
                                            <div class="ac-lc-balances">
                                                <div class="ac-lc-bal">
                                                    <div class="ac-lc-bal-label"><?= __('balance') ?></div>
                                                    <div class="ac-lc-bal-value <?= $balClass ?>"><?= $sym . number_format($row['balance'], 2) ?></div>
                                                </div>
                                            </div>
                                            <div class="ac-lc-date"><?= date('M d, Y', strtotime($row['updated_at'])) ?></div>
                                            <div class="ac-lc-actions ac-lc-actions-expanded">
                                                <?php $isInternalSupplier = isset($row['supplier_type']) && $row['supplier_type'] === 'Internal'; ?>
                                                <?php if (!$isInternalSupplier): ?>
                                                <button class="btn btn-sm btn-primary" onclick="setupFundingModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['supplier_name']) ?>', '<?= htmlspecialchars($row['currency']) ?>')" title="<?= __('fund') ?>" data-toggle="tooltip" data-placement="top">
                                                    <i class="feather icon-credit-card"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="setupBonusModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['supplier_name']) ?>', '<?= htmlspecialchars($row['currency']) ?>')" title="<?= __('bonus') ?>" data-toggle="tooltip" data-placement="top">
                                                    <i class="fas fa-gift"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-primary" onclick="setupWithdrawModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['supplier_name']) ?>', '<?= htmlspecialchars($row['currency']) ?>')" title="<?= __('withdraw') ?>" data-toggle="tooltip" data-placement="top">
                                                    <i class="feather icon-arrow-down"></i>
                                                </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-secondary view-supplier-transactions-btn" data-supplier-id="<?= $row['id'] ?>" data-supplier-name="<?= htmlspecialchars($row['supplier_name']) ?>" title="<?= __('transactions') ?>" data-toggle="tooltip" data-placement="top">
                                                    <i class="feather icon-list"></i>
                                                </button>
                                                <?php if ($isAdmin): ?>
                                                <button class="btn btn-sm btn-outline-<?= !$isInactive ? 'danger' : 'success' ?> toggle-supplier-status-btn" data-supplier-id="<?= $row['id'] ?>" data-current-status="<?= isset($row['status']) ? $row['status'] : 'active' ?>" title="<?= !$isInactive ? __('deactivate') : __('activate') ?>" data-toggle="tooltip" data-placement="top">
                                                    <i class="feather icon-<?= !$isInactive ? 'power' : 'check-circle' ?>"></i>
                                                </button>
                                                <?php endif; ?>
                                             </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div><!-- /acSupplierList -->
                            </div>
                        </div>

                        <!-- ==========================================
                             CLIENT ACCOUNTS
                             ========================================== -->
                        <div class="ac-section" id="acClientSection">
                            <div class="ac-section-header">
                                <div class="ac-section-title-group">
                                    <div class="ac-section-dot" style="background:var(--ac-green)"></div>
                                    <div class="ac-section-title"><?= __('client_accounts') ?></div>
                                    <div class="ac-section-count"><?= count($clientAccounts) ?> <?= __('accounts') ?></div>
                                </div>
                                <div class="ac-section-actions">
                                    <button class="ac-collapse-btn" id="acClientCollapseBtn" onclick="acToggleSection('acClientBody','acClientCollapseBtn')">
                                        <i class="feather icon-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="ac-section-body" id="acClientBody">

                                <div class="ac-pill-row">
                                    <span class="ac-pill-label">Balance:</span>
                                    <span class="ac-pill active" onclick="acPill(this,'acClientList','balance','all')">All</span>
                                    <span class="ac-pill" onclick="acPill(this,'acClientList','balance','positive')"><?= __('positive_balance') ?></span>
                                    <span class="ac-pill" onclick="acPill(this,'acClientList','balance','negative')"><?= __('negative_balance') ?></span>
                                    <span class="ac-pill" onclick="acPill(this,'acClientList','balance','zero')"><?= __('zero_balance') ?></span>
                                    <div class="ac-pill-search">
                                        <i class="feather icon-search"></i>
                                        <input type="text" id="clientSearchInput" placeholder="<?= __('search_clients') ?>…"
                                               oninput="acSearchList(this.value,'acClientList','client-name')">
                                    </div>
                                </div>

                                <div id="acClientList">
                                    <?php foreach ($clientAccounts as $client):
                                        $isInactive  = isset($client['status']) && $client['status'] === 'inactive';
                                        $usdClass    = $client['usd_balance'] > 0 ? 'pos-usd' : ($client['usd_balance'] < 0 ? 'neg' : 'zero');
                                        $afsClass    = $client['afs_balance'] > 0 ? 'pos-afs' : ($client['afs_balance'] < 0 ? 'neg' : 'zero');
                                        $usdBarPct   = $client['usd_balance'] >= 0
                                                       ? min(100, round(($client['usd_balance'] / 20000) * 100))
                                                       : min(100, round((abs($client['usd_balance']) / 2000) * 100));
                                        $usdBarClass = $client['usd_balance'] >= 0 ? 'pos' : 'neg';
                                    ?>
                                    <div class="ac-list-card <?= $isInactive ? 'inactive' : '' ?>"
                                         data-client-name="<?= htmlspecialchars($client['name']) ?>"
                                         data-usd-balance="<?= $client['usd_balance'] ?>"
                                         data-afs-balance="<?= $client['afs_balance'] ?>"
                                         data-status="<?= isset($client['status']) ? $client['status'] : 'active' ?>">
                                        <div class="ac-lc-inner">
                                            <div class="ac-lc-avatar <?= $isInactive ? 'inactive' : 'client' ?>">
                                                <i class="feather icon-user"></i>
                                            </div>
                                            <div class="ac-lc-name-group">
                                                <div class="ac-lc-name"><?= htmlspecialchars($client['name']) ?></div>
                                                <div class="ac-lc-sub"><?= date('M d, Y H:i', strtotime($client['updated_at'])) ?></div>
                                            </div>
                                            <div class="ac-lc-badges">
                                                <span class="ac-badge <?= $isInactive ? 'inactive' : 'active' ?>"><?= $isInactive ? 'Inactive' : 'Active' ?></span>
                                            </div>
                                            <div class="ac-lc-balances">
                                                <div class="ac-lc-bal">
                                                    <div class="ac-lc-bal-label"><?= __('usd_balance') ?></div>
                                                    <div class="ac-lc-bal-value <?= $usdClass ?>">$<?= number_format($client['usd_balance'], 2) ?></div>
                                                </div>
                                                <div class="ac-lc-bal">
                                                    <div class="ac-lc-bal-label"><?= __('afs_balance') ?></div>
                                                    <div class="ac-lc-bal-value <?= $afsClass ?>">؋<?= number_format($client['afs_balance'], 2) ?></div>
                                                </div>
                                            </div>
                                            <div class="ac-lc-date"><?= date('M d, Y', strtotime($client['updated_at'])) ?></div>
                                             <div class="ac-lc-actions ac-lc-actions-expanded">
                                                 <?php $isAgencyClient = isset($client['client_type']) && $client['client_type'] === 'agency'; ?>
                                                 <?php if (!$isAgencyClient): ?>
                                                 <button class="btn btn-primary btn-sm make-payment-btn"
                                                         data-client-id="<?= $client['id'] ?>"
                                                         data-client-name="<?= htmlspecialchars($client['name']) ?>"
                                                         data-usd-balance="<?= $client['usd_balance'] ?>"
                                                         data-afs-balance="<?= $client['afs_balance'] ?>"
                                                         title="<?= __('make_payment') ?>" data-toggle="tooltip" data-placement="top">
                                                     <i class="feather icon-credit-card"></i>
                                                 </button>
                                                 <?php endif; ?>
                                                 <button class="btn btn-outline-secondary view-client-transactions-btn btn-sm"
                                                         data-client-id="<?= $client['id'] ?>"
                                                         data-client-name="<?= htmlspecialchars($client['name']) ?>"
                                                         title="<?= __('view_transactions') ?>" data-toggle="tooltip" data-placement="top">
                                                     <i class="feather icon-list"></i>
                                                 </button>
                                                 <?php if ($isAdmin): ?>
                                                 <button class="btn btn-outline-<?= !$isInactive ? 'danger' : 'success' ?> toggle-client-status-btn btn-sm"
                                                         data-client-id="<?= $client['id'] ?>"
                                                         data-current-status="<?= isset($client['status']) ? $client['status'] : 'active' ?>"
                                                         title="<?= !$isInactive ? __('deactivate') : __('activate') ?>" data-toggle="tooltip" data-placement="top">
                                                     <i class="feather icon-<?= !$isInactive ? 'power' : 'check-circle' ?>"></i>
                                                 </button>
                                                 <?php endif; ?>
                                             </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div><!-- /acClientList -->

                                <!-- original id kept for client-search.js -->
                                <div id="noClientsMessage" class="ac-empty d-none">
                                    <i class="feather icon-users"></i>
                                    <p><?= __('no_clients_match_your_criteria') ?></p>
                                </div>
                            </div>
                        </div>

                    </div><!-- /page-wrapper -->
                </div><!-- /main-body -->
            </div>
        </div>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>
<!-- ALL MODALS — completely untouched -->
<?php include '../modals/accounts/fund_supplier_modal.php'; ?>
<?php include '../modals/accounts/withdraw_supplier_modal.php'; ?>
<?php include '../modals/accounts/bonus_supplier_modal.php'; ?>
<?php include '../modals/accounts/client_payment_modal.php'; ?>
<?php include '../modals/accounts/client_transaction_history_modal.php'; ?>
<?php include '../modals/accounts/supplier_transaction_history_modal.php'; ?>
<?php include '../modals/accounts/transfer_modal.php'; ?>
<?php include '../modals/accounts/main_account_transaction_history_modal.php'; ?>
<?php include '../modals/accounts/remarks_modal.php'; ?>
<?php include '../modals/accounts/edit_transaction_modal.php'; ?>
<?php include '../modals/accounts/edit_receipt_modal.php'; ?>
<?php include '../modals/accounts/edit_main_account_modal.php'; ?>
<?php include '../modals/accounts/add_main_account_modal.php'; ?>

<!-- Hidden form for transaction deletion — untouched -->
<form id="deleteTransactionForm" class="d-none">
    <input type="hidden" id="deleteTransactionId" name="transaction_id">
    <input type="hidden" id="deleteTransactionType" name="transaction_type">
</form>

<style>
.transfer-separator { text-align: center; }
.transfer-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 50%; position: absolute;
    top: 50%; left: 50%; transform: translate(-50%, -50%);
}
</style>

<!-- Toast Container -->
<div class="toast-container"></div>

<!-- ALL ORIGINAL JS INCLUDES — untouched order -->
<script src="../js/accounts/button_protection.js"></script>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../assets/js/client-search.js"></script>
<script type="text/javascript" src="../assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="../assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="../js/accounts/filters.js"></script>
<script src="../js/accounts/toast-notifications.js"></script>
<script src="../js/accounts/printing.js"></script>
<script src="../js/accounts/account-management.js"></script>
<script src="../js/accounts/account-funding.js"></script>
<script src="../js/accounts/account-withdrawal.js"></script>
<script src="../js/accounts/transaction-management.js"></script>
<script src="../js/accounts/status-management.js?v=1.1"></script>

<!-- NEW UI SCRIPTS: collapse, pill filters, bar animations -->
<script>
/* ---- collapse sections ---- */
function acToggleSection(bodyId, btnId) {
    document.getElementById(bodyId).classList.toggle('collapsed');
    document.getElementById(btnId).classList.toggle('collapsed');
}

/* ---- pill filters ---- */
function acPill(el, listId, attr, val) {
    el.closest('.ac-pill-row').querySelectorAll('.ac-pill').forEach(function(p) {
        if ((p.getAttribute('onclick') || '').indexOf("'" + attr + "'") > -1) {
            p.classList.remove('active');
        }
    });
    el.classList.add('active');
    _acApplyPills(listId);
}
function _acApplyPills(listId) {
    var list  = document.getElementById(listId);
    var pills = list.closest('.ac-section-body').querySelectorAll('.ac-pill.active');
    var currency = 'all', balance = 'all';
    pills.forEach(function(p) {
        var fn = p.getAttribute('onclick') || '';
        var m  = fn.match(/'([^']+)'\s*\)\s*$/);
        if (!m) return;
        if (fn.indexOf("'currency'") > -1) currency = m[1];
        if (fn.indexOf("'balance'")  > -1) balance  = m[1];
    });
    list.querySelectorAll('.ac-list-card').forEach(function(card) {
        var show   = true;
        var cur    = card.dataset.currency || '';
        var usd    = parseFloat(card.dataset.usdBalance !== undefined ? card.dataset.usdBalance : (card.dataset.balance || 0));
        if (currency !== 'all' && cur !== currency) show = false;
        if (balance === 'positive' && usd <= 0) show = false;
        if (balance === 'negative' && usd >= 0) show = false;
        if (balance === 'zero'     && usd !== 0) show = false;
        card.style.display = show ? '' : 'none';
    });
}

/* ---- inline search ---- */
function acSearchList(q, listId, attr) {
    var lq  = q.toLowerCase();
    var key = attr.replace(/-([a-z])/g, function(_, c) { return c.toUpperCase(); });
    document.querySelectorAll('#' + listId + ' .ac-list-card').forEach(function(card) {
        var name = (card.dataset[key] || '').toLowerCase();
        card.style.display = name.indexOf(lq) > -1 ? '' : 'none';
    });
}

/* ---- global filter bar ---- */
document.getElementById('accountSearchInput').addEventListener('input', _acGlobal);
document.getElementById('accountTypeFilter').addEventListener('change', _acGlobal);
document.getElementById('statusFilter').addEventListener('change', _acGlobal);
function _acGlobal() {
    var q      = document.getElementById('accountSearchInput').value.toLowerCase();
    var type   = document.getElementById('accountTypeFilter').value;
    var status = document.getElementById('statusFilter').value;
    var map    = { main: 'acMainSection', supplier: 'acSupplierSection', client: 'acClientSection' };
    Object.keys(map).forEach(function(key) {
        var sec = document.getElementById(map[key]);
        if (!sec) return;
        sec.style.display = (type !== 'all' && type !== key) ? 'none' : '';
        sec.querySelectorAll('.ac-list-card, .ac-main-card').forEach(function(card) {
            var nameEl = card.querySelector('.ac-lc-name, .ac-mc-name');
            var name   = (card.dataset.supplierName || card.dataset.clientName || (nameEl ? nameEl.textContent : '') || '').toLowerCase();
            var st     = card.dataset.status || 'active';
            card.style.display = (name.indexOf(q) > -1 && (status === 'all' || st === status)) ? '' : 'none';
        });
    });
}

/* ---- animate health bars on load ---- */
window.addEventListener('load', function() {
    setTimeout(function() {
        document.querySelectorAll('.ac-gauge-anim').forEach(function(el) {
            el.style.width = el.dataset.target || '0%';
        });
    }, 350);
});
</script>

<!-- TUTORIALS MODAL — untouched -->
<div class="modal fade" id="accountsTutorialsModal" tabindex="-1" role="dialog" aria-labelledby="accountsTutorialsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="accountsTutorialsModalLabel">
                    <i class="feather icon-play-circle mr-2"></i>Accounts Management Tutorials
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="row h-100 no-gutters">
                    <div class="col-md-8">
                        <div style="background: #000; position: relative;">
                            <iframe id="tutorialVideoPlayer" style="width: 100%; height: 500px; border: none;"
                                    src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen></iframe>
                        </div>
                        <div class="p-3">
                            <h6 id="tutorialTitle" class="mb-2">Select a tutorial to watch</h6>
                            <p id="tutorialDescription" class="text-muted small mb-0"></p>
                            <div class="mt-2">
                                <small class="badge-primary" id="tutorialLevel">Beginner</small>
                                <small class="badge-secondary" id="tutorialDuration">5:00</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4" style="max-height: 600px; overflow-y: auto; border-left: 1px solid #e9ecef;">
                        <div class="p-3">
                            <h6 class="mb-3">Available Tutorials</h6>
                            <div id="tutorialsListContainer">
                                <p class="text-muted text-center py-3">Loading tutorials...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
var accountsTutorials = [
    {id:1,title:'View Account Balances',description:'Viewing comprehensive account overview, understanding main account balances, supplier account balances, and client account balances with USD and AFS currency support.',duration:'4:30',level:'Beginner',vimeo_id:''},
    {id:2,title:'Search & Filter Accounts',description:'Using search functionality to find accounts, filtering by account type (main, supplier, client), filtering by status (active, inactive), managing large account lists.',duration:'5:00',level:'Beginner',vimeo_id:''},
    {id:3,title:'Add Account Funds',description:'Adding funds to account balances, selecting currency (USD/AFS), entering amount, recording transactions, understanding debit and credit operations, updating account status.',duration:'6:00',level:'Intermediate',vimeo_id:''},
    {id:4,title:'Withdraw Account Funds',description:'Withdrawing funds from accounts, verifying withdrawal requests, processing withdrawals with proper documentation, managing withdrawal transactions, updating balances.',duration:'6:00',level:'Intermediate',vimeo_id:''},
    {id:5,title:'View Transaction History',description:'Accessing account transaction history, reviewing all account transactions, understanding transaction types and amounts, tracking account changes over time, exporting transaction records.',duration:'5:30',level:'Intermediate',vimeo_id:''},
    {id:6,title:'Manage Account Status',description:'Changing account status (active/inactive), understanding status impact on operations, deactivating accounts, reactivating accounts, managing account lifecycle.',duration:'4:30',level:'Advanced',vimeo_id:''}
];
$(document).ready(function() {
    function loadTutorialsInModal() {
        var html = '';
        accountsTutorials.forEach(function(t) {
            html += '<div class="tutorial-item p-2 mb-2 border rounded tutorial-selectable" data-tutorial-id="' + t.id + '" style="background:#f8f9fa;cursor:pointer;transition:all .3s">' +
                '<div class="d-flex align-items-start">' +
                    '<div class="flex-grow-1">' +
                        '<h6 class="mb-1 small font-weight-bold">' + t.title + '</h6>' +
                        '<small class="text-muted d-block mb-1">' + t.duration + '</small>' +
                        '<small class="badge-light">' + t.level + '</small>' +
                    '</div>' +
                    '<i class="feather icon-play text-muted" style="margin-top:2px"></i>' +
                '</div></div>';
        });
        $('#tutorialsListContainer').html(html);
        $('.tutorial-selectable').click(function() {
            var t = accountsTutorials.find(function(x) { return x.id == $(this).data('tutorial-id'); }.bind(this));
            if (!t) return;
            $('.tutorial-selectable').css('background', '#f8f9fa');
            $(this).css({'background': '#e7f3ff', 'border-color': '#4099ff'});
            $('#tutorialVideoPlayer').attr('src', t.vimeo_id ? 'https://player.vimeo.com/video/' + t.vimeo_id : '');
            $('#tutorialTitle').text(t.title);
            $('#tutorialDescription').text(t.description);
            $('#tutorialLevel').text(t.level);
            $('#tutorialDuration').text(t.duration);
        });
    }
    $('#accountsTutorialsModal').on('show.bs.modal', function() {
        loadTutorialsInModal();
        $('.tutorial-selectable').first().click();
    });
    $('<style>').text('.cursor-pointer { cursor: pointer; }').appendTo('head');
});
</script>


</body>
</html>