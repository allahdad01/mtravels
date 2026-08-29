<?php
require_once 'security.php';
enforce_auth();
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
require_permission('finance.owner_funds');
require_once('../includes/db.php');

$tenant_id  = $_SESSION['tenant_id'];
$branch_id  = $_SESSION['branch_id'];
$csrf_token = $_SESSION['csrf_token'];
$current_user = (int) ($_SESSION['user_id'] ?? 0);

// Fetch active main accounts for this branch
$accountsStmt = $pdo->prepare("SELECT id, name, usd_balance, afs_balance, euro_balance, darham_balance, sar_balance FROM main_account WHERE status = 'active' AND tenant_id = ? AND branch_id = ?");
$accountsStmt->execute([$tenant_id, $branch_id]);
$mainAccounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch owners (tenant_super_admin users)
$ownersStmt = $pdo->prepare("SELECT id, name FROM users WHERE tenant_id = ? AND role = 'tenant_super_admin' AND deleted_at IS NULL");
$ownersStmt->execute([$tenant_id]);
$owners = $ownersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique custom owner names from existing transactions
$customOwnersStmt = $pdo->prepare("SELECT DISTINCT SUBSTRING_INDEX(SUBSTRING_INDEX(description, '] ', 1), '[Owner: ', -1) AS custom_name
    FROM main_account_transactions
    WHERE tenant_id = ? AND branch_id = ? AND transaction_of = 'owner_fund'
      AND description LIKE '%[Owner:%]%'
      AND reference_id IS NULL
    ORDER BY custom_name ASC");
$customOwnersStmt->execute([$tenant_id, $branch_id]);
$customOwners = $customOwnersStmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<style>
    :root {
        --of-surface: #ffffff;
        --of-muted: #f0f2f5;
        --of-border: rgba(0,0,0,0.08);
        --of-border-md: rgba(0,0,0,0.13);
        --of-text: #0f172a;
        --of-text-sub: #64748b;
        --of-text-hint: #94a3b8;
        --of-blue: #2563eb;
        --of-blue-bg: #eff6ff;
        --of-blue-tx: #1d4ed8;
        --of-green: #16a34a;
        --of-green-bg: #f0fdf4;
        --of-green-tx: #15803d;
        --of-red: #dc2626;
        --of-red-bg: #fef2f2;
        --of-red-tx: #b91c1c;
        --of-amber: #d97706;
        --of-amber-bg: #fffbeb;
        --of-amber-tx: #b45309;
        --of-r-sm: 6px;
        --of-r-md: 8px;
        --of-r-lg: 12px;
        --of-sh: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    }
    .of-wrap { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; color: var(--of-text); font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
    .of-wrap *, .of-wrap *::before, .of-wrap *::after { box-sizing: border-box; }
    .of-page { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

    .of-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px; }
    .of-topbar-title { font-size: 18px; font-weight: 600; color: var(--of-text); }
    .of-topbar-sub { font-size: 13px; color: var(--of-text-sub); margin-top: 1px; }

    .of-alert { display: none; padding: 10px 14px; border-radius: var(--of-r-md); font-size: 13px; font-weight: 500; margin-bottom: 1.25rem; border-left: 3px solid transparent; }
    .of-alert.show { display: block; }
    .of-alert.success { background: var(--of-green-bg); color: var(--of-green-tx); border-left-color: var(--of-green); }
    .of-alert.danger { background: var(--of-red-bg); color: var(--of-red-tx); border-left-color: var(--of-red); }

    .of-section { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--of-text-sub); margin: 1.5rem 0 0.75rem; }

    .of-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 0.5rem; }
    .of-card { background: var(--of-surface); border-radius: var(--of-r-lg); border: 1px solid var(--of-border); padding: 0.9rem 1rem; box-shadow: var(--of-sh); position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 6px; }
    .of-card::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 3px; border-radius: var(--of-r-lg) 0 0 var(--of-r-lg); }
    .of-card.of-blue::before { background: var(--of-blue); }
    .of-card.of-green::before { background: var(--of-green); }
    .of-card-value { font-size: 20px; font-weight: 700; line-height: 1.1; }
    .of-card-label { font-size: 11px; font-weight: 600; color: var(--of-text-sub); text-transform: uppercase; letter-spacing: 0.3px; }
    .of-card-sub { font-size: 12px; color: var(--of-text-sub); }

    .of-block { background: var(--of-surface); border: 1px solid var(--of-border); border-radius: var(--of-r-lg); padding: 1.25rem; margin-bottom: 1.25rem; box-shadow: var(--of-sh); }

    .of-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 8px 15px; border-radius: var(--of-r-md); border: 1px solid var(--of-border-md); background: var(--of-surface); color: var(--of-text); cursor: pointer; transition: background 0.15s; white-space: nowrap; line-height: 1; box-shadow: var(--of-sh); }
    .of-btn:hover { background: var(--of-muted); }
    .of-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .of-btn-green { background: var(--of-green); color: #fff; border-color: var(--of-green); }
    .of-btn-green:hover { background: #15803d; }
    .of-btn-red { background: var(--of-red); color: #fff; border-color: var(--of-red); }
    .of-btn-red:hover { background: #b91c1c; }
    .of-btn-blue { background: var(--of-blue); color: #fff; border-color: var(--of-blue); }
    .of-btn-blue:hover { background: #1d4ed8; }
    .of-btn-sm { padding: 6px 10px; font-size: 12px; }

    /* Filter */
    .of-filter { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; padding: 1rem 1.25rem; background: var(--of-surface); border: 1px solid var(--of-border); border-radius: var(--of-r-lg); margin-bottom: 1.25rem; box-shadow: var(--of-sh); }
    .of-filter .of-field { display: flex; flex-direction: column; gap: 4px; }
    .of-filter .of-field label { font-size: 11px; font-weight: 600; color: var(--of-text-sub); text-transform: uppercase; letter-spacing: 0.3px; }
    .of-filter .of-field input, .of-filter .of-field select { padding: 7px 10px; font-size: 13px; border: 1px solid var(--of-border-md); border-radius: var(--of-r-md); background: var(--of-surface); color: var(--of-text); }
    .of-filter .of-field input:focus, .of-filter .of-field select:focus { outline: none; border-color: var(--of-blue); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .of-filter-actions { display: flex; gap: 8px; align-items: flex-end; }

    /* Table */
    .of-table-wrap { background: var(--of-surface); border: 1px solid var(--of-border); border-radius: var(--of-r-lg); box-shadow: var(--of-sh); overflow: hidden; }
    .of-t-head, .of-t-row { display: grid; grid-template-columns: 130px 1fr 120px 130px 1fr 150px; gap: 12px; padding: 10px 16px; align-items: center; }
    .of-t-head { background: var(--of-muted); border-bottom: 1px solid var(--of-border); }
    .of-t-head span { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; color: var(--of-text-sub); }
    .of-t-row { border-bottom: 1px solid var(--of-border); transition: background 0.1s; }
    .of-t-row:last-child { border-bottom: none; }
    .of-t-row:hover { background: var(--of-muted); }
    .of-t-dim { font-size: 12.5px; color: var(--of-text-sub); }
    .of-t-note { font-size: 12.5px; color: var(--of-text-sub); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .of-empty { padding: 3rem 1rem; text-align: center; font-size: 13px; color: var(--of-text-hint); }
    .of-actions { display: flex; gap: 6px; flex-wrap: wrap; }

    /* Pagination */
    .of-pagination { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-top: 1px solid var(--of-border); font-size: 13px; color: var(--of-text-sub); }
    .of-pagination-btns { display: flex; gap: 6px; }

    /* Modal */
    .of-overlay { display: none !important; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15,23,42,0.45); backdrop-filter: blur(2px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto; }
    .of-overlay.open { display: flex !important; }
    .of-modal { background: var(--of-surface); border: 1px solid var(--of-border); border-radius: var(--of-r-lg); padding: 1.5rem; width: 500px; max-width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.18); animation: ofModalIn 0.2s ease-out; position: relative; z-index: 100000; margin: auto; flex-shrink: 0; }
    @keyframes ofModalIn { from { opacity: 0; transform: translateY(10px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .of-modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--of-border); }
    .of-modal-head h2 { font-size: 15px; font-weight: 600; margin: 0; }
    .of-modal-close { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: var(--of-r-sm); background: none; border: none; cursor: pointer; font-size: 18px; line-height: 1; color: var(--of-text-sub); }
    .of-modal-close:hover { background: var(--of-muted); }
    .of-field { margin-bottom: 1rem; }
    .of-field label { display: block; font-size: 12px; font-weight: 600; color: var(--of-text-sub); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.3px; }
    .of-field input, .of-field select, .of-field textarea { width: 100%; padding: 8px 10px; font-size: 13px; border: 1px solid var(--of-border-md); border-radius: var(--of-r-md); background: var(--of-surface); color: var(--of-text); font-family: inherit; }
    .of-field input:focus, .of-field select:focus, .of-field textarea:focus { outline: none; border-color: var(--of-blue); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .of-field textarea { resize: vertical; }
    .of-modal-footer { display: flex; gap: 8px; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--of-border); }
    .of-modal-footer .of-btn { flex: 1; justify-content: center; }
    .of-bal-hint { font-size: 11px; color: var(--of-text-hint); margin-top: 4px; }

    @media (max-width: 900px) {
        .of-t-head { display: none; }
        .of-t-row { grid-template-columns: 1fr; gap: 6px; padding: 12px 16px; }
        .of-t-row::before { content: attr(data-label); font-size: 11px; font-weight: 600; color: var(--of-text-sub); text-transform: uppercase; }
    }
</style>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="main-body">
      <div class="page-wrapper">
        <div class="of-wrap">
<div class="of-page">

    <div class="of-topbar">
        <div>
            <div class="of-topbar-title">Owner Payments</div>
            <div class="of-topbar-sub">Track money paid from admin to owner (tenant_super_admin)</div>
        </div>
        <div style="display:flex; gap:8px;">
            <button class="of-btn of-btn-blue" onclick="openModal('statementOverlay')">
                <i class="fas fa-file-invoice"></i> Print Statement
            </button>
            <button class="of-btn of-btn-green" onclick="openModal('addOverlay')">
                <i class="fas fa-plus"></i> Record Payment
            </button>
        </div>
    </div>

    <div class="of-alert" id="alertBar"></div>

    <!-- Summary Cards -->
    <div class="of-section">Payment Summary</div>
    <div class="of-cards" id="summaryCards"><div class="of-empty">Loading...</div></div>

    <!-- Filters -->
    <div class="of-filter">
        <div class="of-field">
            <label>From</label>
            <input type="date" id="filterFrom" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
        </div>
        <div class="of-field">
            <label>To</label>
            <input type="date" id="filterTo" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
        </div>
        <div class="of-field">
            <label>Currency</label>
            <select id="filterCurrency">
                <option value="">All</option>
                <option value="USD" <?= ($_GET['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD</option>
                <option value="AFS" <?= ($_GET['currency'] ?? '') === 'AFS' ? 'selected' : '' ?>>AFS</option>
                <option value="EUR" <?= ($_GET['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR</option>
                <option value="DARHAM" <?= ($_GET['currency'] ?? '') === 'DARHAM' ? 'selected' : '' ?>>DARHAM</option>
                <option value="SAR" <?= ($_GET['currency'] ?? '') === 'SAR' ? 'selected' : '' ?>>SAR</option>
            </select>
        </div>
        <div class="of-field">
            <label>Search</label>
            <input type="text" id="filterSearch" placeholder="Owner, purpose, receipt..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="width:180px;">
        </div>
        <div class="of-filter-actions">
            <button class="of-btn of-btn-blue of-btn-sm" onclick="applyFilters()"><i class="fas fa-search"></i> Filter</button>
            <button class="of-btn of-btn-sm" onclick="resetFilters()"><i class="fas fa-times"></i> Reset</button>
        </div>
    </div>

    <!-- Table -->
    <div class="of-table-wrap">
        <div class="of-t-head">
            <span>Date</span><span>Owner</span><span>Amount</span><span>Account</span><span>Purpose</span><span>Actions</span>
        </div>
        <div id="tableBody"><div class="of-empty">Loading...</div></div>
        <div class="of-pagination" id="paginationBar" style="display:none;">
            <span id="paginationInfo"></span>
            <div class="of-pagination-btns" id="paginationBtns"></div>
        </div>
    </div>

</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Payment Modal -->
<div class="of-overlay" id="addOverlay">
    <div class="of-modal">
        <div class="of-modal-head">
            <h2>Record Owner Payment</h2>
            <button class="of-modal-close" onclick="closeModal('addOverlay')">&times;</button>
        </div>
        <form id="addForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

            <div class="of-field">
                <label>Main Account *</label>
                <select name="main_account_id" id="formAccount" required>
                    <option value="">Select account...</option>
                    <?php foreach ($mainAccounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" 
                            data-usd="<?= $acc['usd_balance'] ?>" 
                            data-afs="<?= $acc['afs_balance'] ?>"
                            data-eur="<?= $acc['euro_balance'] ?>"
                            data-darham="<?= $acc['darham_balance'] ?>"
                            data-sar="<?= $acc['sar_balance'] ?>">
                        <?= h($acc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="of-bal-hint" id="balanceHint"></div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="of-field">
                    <label>Amount *</label>
                    <input type="number" name="amount" id="formAmount" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="of-field">
                    <label>Currency *</label>
                    <select name="currency" id="formCurrency" required>
                        <option value="USD">USD</option>
                        <option value="AFS">AFS</option>
                        <option value="EUR">EUR</option>
                        <option value="DARHAM">DARHAM</option>
                        <option value="SAR">SAR</option>
                    </select>
                </div>
            </div>

            <div class="of-field">
                <label>Owner (Received By) *</label>
                <select name="owner_id" id="formOwner" required>
                    <option value="">Select owner...</option>
                    <?php foreach ($owners as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= h($o['name']) ?></option>
                    <?php endforeach; ?>
                    <?php foreach ($customOwners as $co): ?>
                    <option value="custom:<?= h($co['custom_name']) ?>"><?= h($co['custom_name']) ?></option>
                    <?php endforeach; ?>
                    <option value="custom">Other (Custom Name)</option>
                </select>
            </div>

            <div class="of-field" id="customNameWrap" style="display:none;">
                <label>Owner Name *</label>
                <input type="text" name="owner_name" id="formOwnerName" placeholder="Enter owner name">
            </div>

            <div class="of-field">
                <label>Purpose / Reason *</label>
                <textarea name="description" id="formDesc" rows="3" required placeholder="Reason for payment..."></textarea>
            </div>

            <div class="of-field">
                <label>Receipt Number (optional)</label>
                <input type="text" name="receipt_number" id="formReceipt" placeholder="Receipt #">
            </div>

            <div class="of-modal-footer">
                <button type="button" class="of-btn" onclick="closeModal('addOverlay')">Cancel</button>
                <button type="submit" class="of-btn of-btn-green" id="submitBtn">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Payment Modal -->
<div class="of-overlay" id="editOverlay">
    <div class="of-modal">
        <div class="of-modal-head">
            <h2>Edit Owner Payment</h2>
            <button class="of-modal-close" onclick="closeModal('editOverlay')">&times;</button>
        </div>
        <form id="editForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="id" id="editId">

            <div class="of-field">
                <label>Main Account *</label>
                <select name="main_account_id" id="editAccount" required>
                    <option value="">Select account...</option>
                    <?php foreach ($mainAccounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>"
                            data-usd="<?= $acc['usd_balance'] ?>"
                            data-afs="<?= $acc['afs_balance'] ?>"
                            data-eur="<?= $acc['euro_balance'] ?>"
                            data-darham="<?= $acc['darham_balance'] ?>"
                            data-sar="<?= $acc['sar_balance'] ?>">
                        <?= h($acc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="of-bal-hint" id="editBalanceHint"></div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="of-field">
                    <label>Amount *</label>
                    <input type="number" name="amount" id="editAmount" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="of-field">
                    <label>Currency *</label>
                    <select name="currency" id="editCurrency" required>
                        <option value="USD">USD</option>
                        <option value="AFS">AFS</option>
                        <option value="EUR">EUR</option>
                        <option value="DARHAM">DARHAM</option>
                        <option value="SAR">SAR</option>
                    </select>
                </div>
            </div>

            <div class="of-field">
                <label>Owner (Received By) *</label>
                <select name="owner_id" id="editOwner" required>
                    <option value="">Select owner...</option>
                    <?php foreach ($owners as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= h($o['name']) ?></option>
                    <?php endforeach; ?>
                    <?php foreach ($customOwners as $co): ?>
                    <option value="custom:<?= h($co['custom_name']) ?>"><?= h($co['custom_name']) ?></option>
                    <?php endforeach; ?>
                    <option value="custom">Other (Custom Name)</option>
                </select>
            </div>

            <div class="of-field" id="editCustomNameWrap" style="display:none;">
                <label>Owner Name *</label>
                <input type="text" name="owner_name" id="editOwnerName" placeholder="Enter owner name">
            </div>

            <div class="of-field">
                <label>Purpose / Reason *</label>
                <textarea name="description" id="editDesc" rows="3" required placeholder="Reason for payment..."></textarea>
            </div>

            <div class="of-field">
                <label>Receipt Number (optional)</label>
                <input type="text" name="receipt_number" id="editReceipt" placeholder="Receipt #">
            </div>

            <div class="of-modal-footer">
                <button type="button" class="of-btn" onclick="closeModal('editOverlay')">Cancel</button>
                <button type="submit" class="of-btn of-btn-blue" id="editSubmitBtn">Update Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Print Statement Modal -->
<div class="of-overlay" id="statementOverlay">
    <div class="of-modal">
        <div class="of-modal-head">
            <h2>Print Owner Statement</h2>
            <button class="of-modal-close" onclick="closeModal('statementOverlay')">&times;</button>
        </div>
        <form id="statementForm" onsubmit="return openStatement()">
            <div class="of-field">
                <label>Owner *</label>
                <select id="stmtOwner" required>
                    <option value="">Select owner...</option>
                    <?php foreach ($owners as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= h($o['name']) ?></option>
                    <?php endforeach; ?>
                    <?php foreach ($customOwners as $co): ?>
                    <option value="custom:<?= h($co['custom_name']) ?>"><?= h($co['custom_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="of-field">
                    <label>Date From (optional)</label>
                    <input type="date" id="stmtDateFrom">
                </div>
                <div class="of-field">
                    <label>Date To (optional)</label>
                    <input type="date" id="stmtDateTo">
                </div>
            </div>
            <div class="of-modal-footer">
                <button type="button" class="of-btn" onclick="closeModal('statementOverlay')">Cancel</button>
                <button type="submit" class="of-btn of-btn-blue"><i class="fas fa-print"></i> Generate Statement</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
const CSRF = <?= json_encode($csrf_token) ?>;
const API  = '../api/finance/owner_funds.php';

function esc(s) { const d = document.createElement('div'); d.textContent = (s == null) ? '' : String(s); return d.innerHTML; }
function money(n) { return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function dateTime(s) { if (!s) return '—'; return new Date(s).toLocaleString(); }

function showAlert(msg, type) {
    const bar = document.getElementById('alertBar');
    bar.textContent = msg;
    bar.className = 'of-alert show ' + type;
    setTimeout(() => { bar.className = 'of-alert'; }, 5000);
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/* ── Summary ── */
function loadSummary() {
    fetch(API + '?action=summary', { credentials: 'include' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            const wrap = document.getElementById('summaryCards');
            const order = ['USD','AFS','EUR','DARHAM','SAR'];
            const items = order.filter(c => res.summary[c]).map(c => {
                const d = res.summary[c];
                return '<div class="of-card of-blue">'
                    + '<div class="of-card-label">' + esc(c) + ' Total Paid</div>'
                    + '<div class="of-card-value">' + money(d.total_amount) + '</div>'
                    + '<div class="of-card-sub">' + d.total_count + ' payment(s)</div>'
                    + '</div>';
            }).join('');
            wrap.innerHTML = items || '<div class="of-card"><div class="of-card-sub">No payments recorded yet</div></div>';
        })
        .catch(e => showAlert(e.message, 'danger'));
}

/* ── List ── */
let currentPage = 1;

function loadList(page) {
    currentPage = page || 1;
    const params = new URLSearchParams();
    params.set('action', 'list');
    params.set('page', currentPage);
    const fv = id => document.getElementById(id).value.trim();
    if (fv('filterFrom'))     params.set('start_date', fv('filterFrom'));
    if (fv('filterTo'))       params.set('end_date', fv('filterTo'));
    if (fv('filterCurrency')) params.set('currency', fv('filterCurrency'));
    if (fv('filterSearch'))   params.set('search', fv('filterSearch'));

    fetch(API + '?' + params.toString(), { credentials: 'include' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            renderTable(res.transactions);
            renderPagination(res.pagination);
        })
        .catch(e => showAlert(e.message, 'danger'));
}

function renderTable(list) {
    const wrap = document.getElementById('tableBody');
    if (!list.length) { wrap.innerHTML = '<div class="of-empty">No owner payments found.</div>'; return; }
    wrap.innerHTML = list.map(t => `
        <div class="of-t-row">
            <span class="of-t-dim">${dateTime(t.created_at)}</span>
            <span style="font-weight:500;">${esc(t.owner_name) || '—'}</span>
            <span style="font-weight:700; color:var(--of-red);">${esc(t.currency)} ${money(t.amount)}</span>
            <span class="of-t-dim">${esc(t.account_name) || '—'}</span>
            <span class="of-t-note" title="${esc(t.purpose || t.description)}">${esc(t.purpose || t.description) || '—'}</span>
            <span class="of-actions">
                <button class="of-btn of-btn-sm" onclick="printReceipt(${t.id})" title="Print receipt"><i class="fas fa-print"></i></button>
                <button class="of-btn of-btn-sm" onclick="openEdit(${t.id})" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="of-btn of-btn-red of-btn-sm" onclick="deletePayment(${t.id}, '${esc(t.currency)}', ${t.amount})" title="Delete"><i class="fas fa-trash"></i></button>
            </span>
        </div>`).join('');
}

function renderPagination(p) {
    const bar = document.getElementById('paginationBar');
    const info = document.getElementById('paginationInfo');
    const btns = document.getElementById('paginationBtns');
    if (p.total_pages <= 1) { bar.style.display = 'none'; return; }
    bar.style.display = 'flex';
    info.textContent = `Page ${p.current_page} of ${p.total_pages} (${p.total_records} records)`;
    let html = '';
    if (p.current_page > 1) html += `<button class="of-btn of-btn-sm" onclick="loadList(${p.current_page - 1})">Prev</button>`;
    if (p.current_page < p.total_pages) html += `<button class="of-btn of-btn-sm" onclick="loadList(${p.current_page + 1})">Next</button>`;
    btns.innerHTML = html;
}

function applyFilters() { loadList(1); }
function resetFilters() {
    document.getElementById('filterFrom').value = '';
    document.getElementById('filterTo').value = '';
    document.getElementById('filterCurrency').value = '';
    document.getElementById('filterSearch').value = '';
    loadList(1);
}

/* ── Balance hint ── */
function updateBalanceHint() {
    const sel = document.getElementById('formAccount');
    const cur = document.getElementById('formCurrency').value;
    const opt = sel.options[sel.selectedIndex];
    const hint = document.getElementById('balanceHint');
    if (!opt || !opt.value) { hint.textContent = ''; return; }
    const map = { USD: 'usd', AFS: 'afs', EUR: 'eur', DARHAM: 'darham', SAR: 'sar' };
    const bal = parseFloat(opt.getAttribute('data-' + (map[cur] || 'usd')) || 0);
    hint.textContent = 'Available: ' + money(bal) + ' ' + cur;
}
document.getElementById('formAccount').addEventListener('change', updateBalanceHint);
document.getElementById('formCurrency').addEventListener('change', updateBalanceHint);

/* ── Owner dropdown toggle ── */
document.getElementById('formOwner').addEventListener('change', function() {
    const wrap = document.getElementById('customNameWrap');
    const nameInput = document.getElementById('formOwnerName');
    const val = this.value;
    if (val === 'custom') {
        wrap.style.display = 'block';
        nameInput.required = true;
    } else if (val.startsWith('custom:')) {
        wrap.style.display = 'none';
        nameInput.required = false;
        nameInput.value = val.substring(7);
    } else {
        wrap.style.display = 'none';
        nameInput.required = false;
        nameInput.value = '';
    }
});

/* ── Submit form ── */
document.getElementById('addForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-loader spinner"></i> Processing...';

    const formData = new FormData(this);
    // Handle custom:Name format
    const ownerVal = formData.get('owner_id');
    if (ownerVal && ownerVal.startsWith('custom:')) {
        formData.set('owner_id', 'custom');
        formData.set('owner_name', ownerVal.substring(7));
    }
    formData.append('action', 'create');
    fetch(API, { method: 'POST', credentials: 'include', body: formData })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            showAlert(res.message, 'success');
            closeModal('addOverlay');
            this.reset();
            document.getElementById('balanceHint').textContent = '';
            document.getElementById('customNameWrap').style.display = 'none';
            document.getElementById('formOwnerName').required = false;
            loadSummary();
            loadList(1);
        })
        .catch(err => showAlert(err.message, 'danger'))
        .finally(() => { btn.disabled = false; btn.innerHTML = 'Record Payment'; });
});

/* ── Delete ── */
function deletePayment(id, currency, amount) {
    if (!confirm('Delete this ' + currency + ' ' + money(amount) + ' payment? The amount will be restored to the account.')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    fetch(API, { method: 'POST', credentials: 'include', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            showAlert(res.message, 'success');
            loadSummary();
            loadList(currentPage);
        })
        .catch(err => showAlert(err.message, 'danger'));
}

/* ── Edit ── */
function openEdit(id) {
    fetch(API + '?action=get&id=' + id, { credentials: 'include' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            const t = res.transaction;
            document.getElementById('editId').value = t.id;
            document.getElementById('editAccount').value = t.main_account_id;
            document.getElementById('editAmount').value = t.amount;
            document.getElementById('editCurrency').value = t.currency;
            document.getElementById('editDesc').value = t.purpose || t.description;
            document.getElementById('editReceipt').value = t.receipt || '';

            // Set owner dropdown
            const ownerSel = document.getElementById('editOwner');
            const customWrap = document.getElementById('editCustomNameWrap');
            const nameInput = document.getElementById('editOwnerName');
            if (t.reference_id) {
                ownerSel.value = t.reference_id;
                customWrap.style.display = 'none';
                nameInput.required = false;
                nameInput.value = '';
            } else {
                // Try matching custom:Name option, else fall back to custom
                const customVal = 'custom:' + (t.owner_name || '');
                const hasCustomOpt = Array.from(ownerSel.options).some(o => o.value === customVal);
                ownerSel.value = hasCustomOpt ? customVal : 'custom';
                customWrap.style.display = hasCustomOpt ? 'none' : 'block';
                nameInput.required = !hasCustomOpt;
                nameInput.value = t.owner_name || '';
            }

            // Update balance hint
            updateEditBalanceHint();
            openModal('editOverlay');
        })
        .catch(err => showAlert(err.message, 'danger'));
}

function updateEditBalanceHint() {
    const sel = document.getElementById('editAccount');
    const cur = document.getElementById('editCurrency').value;
    const opt = sel.options[sel.selectedIndex];
    const hint = document.getElementById('editBalanceHint');
    if (!opt || !opt.value) { hint.textContent = ''; return; }
    const map = { USD: 'usd', AFS: 'afs', EUR: 'eur', DARHAM: 'darham', SAR: 'sar' };
    const bal = parseFloat(opt.getAttribute('data-' + (map[cur] || 'usd')) || 0);
    hint.textContent = 'Available: ' + money(bal) + ' ' + cur;
}
document.getElementById('editAccount').addEventListener('change', updateEditBalanceHint);
document.getElementById('editCurrency').addEventListener('change', updateEditBalanceHint);

/* ── Edit owner toggle ── */
document.getElementById('editOwner').addEventListener('change', function() {
    const wrap = document.getElementById('editCustomNameWrap');
    const nameInput = document.getElementById('editOwnerName');
    const val = this.value;
    if (val === 'custom') {
        wrap.style.display = 'block';
        nameInput.required = true;
    } else if (val.startsWith('custom:')) {
        wrap.style.display = 'none';
        nameInput.required = false;
        nameInput.value = val.substring(7);
    } else {
        wrap.style.display = 'none';
        nameInput.required = false;
        nameInput.value = '';
    }
});

/* ── Edit form submit ── */
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('editSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-loader spinner"></i> Processing...';

    const formData = new FormData(this);
    // Handle custom:Name format
    const ownerVal = formData.get('owner_id');
    if (ownerVal && ownerVal.startsWith('custom:')) {
        formData.set('owner_id', 'custom');
        formData.set('owner_name', ownerVal.substring(7));
    }
    formData.append('action', 'update');
    fetch(API, { method: 'POST', credentials: 'include', body: formData })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            showAlert(res.message, 'success');
            closeModal('editOverlay');
            loadSummary();
            loadList(currentPage);
        })
        .catch(err => showAlert(err.message, 'danger'))
        .finally(() => { btn.disabled = false; btn.innerHTML = 'Update Payment'; });
});

/* ── Print receipt ── */
function printReceipt(id) {
    window.open('../api/finance/print_owner_fund_receipt.php?id=' + id, '_blank');
}

/* ── Print owner statement ── */
function openStatement() {
    const sel = document.getElementById('stmtOwner');
    const val = sel.value;
    if (!val) { showAlert('Please select an owner', 'danger'); return false; }
    const dateFrom = document.getElementById('stmtDateFrom').value;
    const dateTo = document.getElementById('stmtDateTo').value;
    let url = '../api/finance/print_owner_statement.php';
    if (val.startsWith('custom:')) {
        url += '?owner_id=0&custom_name=' + encodeURIComponent(val.substring(7));
    } else {
        url += '?owner_id=' + val;
    }
    if (dateFrom) url += '&date_from=' + dateFrom;
    if (dateTo) url += '&date_to=' + dateTo;
    window.open(url, '_blank');
    closeModal('statementOverlay');
    return false;
}

/* ── Init ── */
loadSummary();
loadList(1);
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
