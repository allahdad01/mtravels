<?php
require_once 'security.php';
enforce_auth();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_permission('finance.expenses');
require_once('../includes/db.php');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Fetch branches that have settlements
$agenciesQuery = "SELECT br.id, br.name FROM branches br
    JOIN agency_expense_settlements aes ON aes.agency_branch_id = br.id
    WHERE aes.tenant_id = ? AND aes.branch_id = ?
    GROUP BY br.id, br.name ORDER BY br.name";
$agenciesStmt = $pdo->prepare($agenciesQuery);
$agenciesStmt->execute([$tenant_id, $branch_id]);
$agencies = $agenciesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active branches (excluding current) for the "record payment" flow
$allBranchesQuery = "SELECT id, name FROM branches WHERE tenant_id = ? AND status = 'active' AND id != ? ORDER BY name";
$allBranchesStmt = $pdo->prepare($allBranchesQuery);
$allBranchesStmt->execute([$tenant_id, $branch_id]);
$allBranches = $allBranchesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch main accounts for the payment form
$mainAccountsQuery = "SELECT id, name FROM main_account WHERE tenant_id = ? AND branch_id = ? AND status = 'active' ORDER BY name";
$mainAccountsStmt = $pdo->prepare($mainAccountsQuery);
$mainAccountsStmt->execute([$tenant_id, $branch_id]);
$mainAccounts = $mainAccountsStmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<meta name="csrf-token" content="<?= $csrf_token ?>">
<?php include '../includes/header.php'; ?>
<link href="../css/expenses/style.css" rel="stylesheet">
<style>
.ag-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;color:#0f172a;font-size:14px;line-height:1.5;max-width:1400px;margin:0 auto;padding:1.5rem}
.ag-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:10px}
.ag-topbar-title{font-size:18px;font-weight:600}
.ag-topbar-sub{font-size:13px;color:#64748b;margin-top:1px}
.ag-alert{display:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:500;margin-bottom:1.25rem;border-left:3px solid transparent}
.ag-alert.show{display:block}
.ag-alert.success{background:#f0fdf4;color:#15803d;border-left-color:#16a34a}
.ag-alert.danger{background:#fef2f2;color:#b91c1c;border-left-color:#dc2626}
.ag-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:1.25rem}
.ag-card{background:#fff;border-radius:12px;border:1px solid #eef1f5;padding:1rem;box-shadow:0 1px 3px rgba(0,0,0,.04);position:relative;overflow:hidden;display:flex;flex-direction:column;gap:6px}
.ag-card::before{content:'';position:absolute;inset:0 auto 0 0;width:3px;border-radius:12px 0 0 12px}
.ag-card.blue::before{background:#2563eb}.ag-card.green::before{background:#16a34a}.ag-card.amber::before{background:#d97706}.ag-card.red::before{background:#dc2626}
.ag-card-value{font-size:20px;font-weight:700;line-height:1.1}
.ag-card-label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.3px}
.ag-card-sub{font-size:12px;color:#64748b}
.ag-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:13px;font-weight:500;padding:8px 15px;border-radius:8px;border:1px solid rgba(0,0,0,.13);background:#fff;color:#0f172a;cursor:pointer;transition:background .15s;white-space:nowrap;line-height:1}
.ag-btn:hover{background:#f0f2f5}.ag-btn:disabled{opacity:.6;cursor:not-allowed}
.ag-btn-green{background:#16a34a;color:#fff;border-color:#16a34a}.ag-btn-green:hover{background:#15803d}
.ag-btn-red{background:#dc2626;color:#fff;border-color:#dc2626}.ag-btn-red:hover{background:#b91c1c}
.ag-btn-blue{background:#2563eb;color:#fff;border-color:#2563eb}.ag-btn-blue:hover{background:#1d4ed8}
.ag-btn-sm{padding:6px 10px;font-size:12px}
.ag-section{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin:1.5rem 0 .75rem}
.ag-block{background:#fff;border:1px solid #eef1f5;border-radius:12px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.ag-block-title{font-size:15px;font-weight:700;margin-bottom:.75rem;display:flex;align-items:center;gap:8px}
.ag-table-wrap{background:#fff;border:1px solid #eef1f5;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.04);overflow:hidden}
.ag-t-head,.ag-t-row{display:grid;grid-template-columns:110px 1fr 110px 100px 100px 140px;gap:12px;padding:10px 16px;align-items:center}
.ag-t-head{background:#f4f6fa;border-bottom:1px solid #eef1f5}
.ag-t-head span{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#64748b}
.ag-t-row{border-bottom:1px solid #eef1f5;transition:background .1s}
.ag-t-row:last-child{border-bottom:none}.ag-t-row:hover{background:#f4f6fa}
.ag-t-dim{font-size:12.5px;color:#64748b}
.ag-t-note{font-size:12.5px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ag-pill{display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;letter-spacing:.2px;text-transform:capitalize}
.ag-pill.pending{background:#fffbeb;color:#b45309}.ag-pill.settled{background:#f0fdf4;color:#15803d}.ag-pill.partial{background:#eff6ff;color:#1d4ed8}
.ag-empty{padding:3rem 1rem;text-align:center;font-size:13px;color:#94a3b8}
.ag-overlay{display:none!important;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(15,23,42,.45);backdrop-filter:blur(2px);z-index:99999;align-items:center;justify-content:center;padding:1rem;overflow-y:auto}
.ag-overlay.open{display:flex!important}
.ag-modal{background:#fff;border:1px solid #eef1f5;border-radius:12px;padding:1.5rem;width:480px;max-width:100%;box-shadow:0 20px 60px rgba(0,0,0,.18);animation:agIn .2s ease-out;position:relative;z-index:100000;margin:auto;flex-shrink:0}
.ag-modal-lg{width:700px}
@keyframes agIn{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
.ag-modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid #eef1f5}
.ag-modal-head h2{font-size:15px;font-weight:600;margin:0}
.ag-modal-close{width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;background:none;border:none;cursor:pointer;font-size:18px;line-height:1;color:#64748b}
.ag-modal-close:hover{background:#f4f6fa}
.ag-field{margin-bottom:1rem}
.ag-field label{display:block;font-size:12px;font-weight:600;color:#64748b;margin-bottom:5px;text-transform:uppercase;letter-spacing:.3px}
.ag-field input,.ag-field select,.ag-field textarea{width:100%;padding:8px 10px;font-size:13px;border:1px solid rgba(0,0,0,.13);border-radius:8px;background:#fff;color:#0f172a;font-family:inherit}
.ag-field input:focus,.ag-field select:focus,.ag-field textarea:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.ag-field textarea{resize:vertical}
.ag-modal-footer{display:flex;gap:8px;margin-top:1.25rem;padding-top:1rem;border-top:1px solid #eef1f5}
.ag-modal-footer .ag-btn{flex:1;justify-content:center}
.ag-filter-bar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.25rem;padding:1rem;background:#fff;border:1px solid #eef1f5;border-radius:12px}
.ag-filter-bar .ag-field{margin-bottom:0;min-width:160px}
.ag-settled-table{width:100%;border-collapse:collapse;font-size:13px}
.ag-settled-table th{text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#64748b;padding:8px 10px;border-bottom:1px solid #eef1f5;background:#f4f6fa}
.ag-settled-table td{padding:8px 10px;border-bottom:1px solid #eef1f5;vertical-align:top}
.ag-settled-table tr:last-child td{border-bottom:none}
.ag-settled-table .pos{color:#16a34a;font-weight:600}
.ag-settled-table .neg{color:#dc2626;font-weight:600}
.ag-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.ag-form-grid .ag-field-full{grid-column:1/-1}
</style>

<div class="pcoded-main-container">
<div class="pcoded-wrapper">
<div class="main-body">
<div class="page-wrapper">
<div class="ag-wrap">

    <div class="ag-topbar">
        <div>
            <div class="ag-topbar-title">Agency Settlements</div>
            <div class="ag-topbar-sub">Track expenses paid on behalf of other branches and record their payments</div>
        </div>
        <button class="ag-btn ag-btn-blue" onclick="openRecordPayment()">
            <i class="feather icon-plus"></i> Record Payment
        </button>
        <button class="ag-btn" style="background:#64748b;color:#fff;border-color:#64748b;" onclick="printSettlement()">
            <i class="feather icon-printer"></i> Print Report
        </button>
    </div>

    <div class="ag-alert" id="alertBar"></div>

    <!-- Summary Cards -->
    <div class="ag-section">Overview</div>
    <div class="ag-cards" id="summaryCards">
        <div class="ag-card blue"><div class="ag-card-sub">Loading...</div></div>
    </div>

    <!-- Branch List -->
    <div class="ag-section">Branches</div>
    <div class="ag-table-wrap">
        <div class="ag-t-head">
            <span>Branch</span><span>Status</span><span>Remaining</span><span>Pending</span><span>Settled</span><span>Actions</span>
        </div>
        <div id="agencyList"><div class="ag-empty">Loading...</div></div>
    </div>

    <!-- Selected Branch Details -->
    <div id="agencyDetails" style="display:none;">
        <div class="ag-section">Expenses for <span id="selectedAgencyName"></span></div>

        <div class="ag-filter-bar">
            <div class="ag-field">
                <label>From Date</label>
                <input type="date" id="filterStartDate">
            </div>
            <div class="ag-field">
                <label>To Date</label>
                <input type="date" id="filterEndDate">
            </div>
            <button class="ag-btn ag-btn-blue ag-btn-sm" onclick="loadSettlements()">
                <i class="feather icon-search"></i> Filter
            </button>
            <button class="ag-btn ag-btn-sm" onclick="clearFilter()">Reset</button>
        </div>

        <div class="ag-block">
            <div class="ag-block-title">
                <i class="feather icon-file-text"></i> Expense Settlements
            </div>
            <div class="ag-table-wrap">
                <div id="settlementsList"><div class="ag-empty">Select a branch to view details</div></div>
            </div>
        </div>

            <div class="ag-block">
            <div class="ag-block-title">
                <i class="feather icon-dollar-sign"></i> Payment History
            </div>
            <div class="ag-table-wrap">
                <div id="paymentsList"><div class="ag-empty">No payments recorded</div></div>
            </div>
        </div>
    </div>

</div>
</div>
</div>
</div>
</div>

<!-- Record Payment Modal -->
<div class="ag-overlay" id="paymentOverlay">
    <div class="ag-modal">
        <div class="ag-modal-head">
            <h2>Record Payment from Branch</h2>
            <button class="ag-modal-close" onclick="closeAll()">&times;</button>
        </div>
        <form id="paymentForm" novalidate>
            <div class="ag-form-grid">
                <div class="ag-field">
                    <label>Branch / Agency *</label>
                    <select id="payAgency" required>
                        <option value="">Select Branch / Agency</option>
                        <?php foreach ($allBranches as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                        <option value="__custom__">+ Enter Custom Name</option>
                    </select>
                </div>
                <div class="ag-field" id="payCustomNameGroup" style="display:none;">
                    <label>Agency / Client Name *</label>
                    <input type="text" id="payCustomName" placeholder="e.g. ABC Travel Agency" maxlength="255">
                </div>
                <div class="ag-field">
                    <label>Main Account *</label>
                    <select id="payMainAccount" required>
                        <option value="">Select Main Account</option>
                        <?php foreach ($mainAccounts as $ma): ?>
                        <option value="<?= $ma['id'] ?>"><?= htmlspecialchars($ma['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ag-field">
                    <label>Amount *</label>
                    <input type="number" step="0.01" min="0.01" id="payAmount" required placeholder="0.00">
                </div>
                <div class="ag-field">
                    <label>Currency *</label>
                    <select id="payCurrency" required>
                        <option value="">Select Currency</option>
                        <option value="USD">USD</option>
                        <option value="AFS">AFS</option>
                        <option value="EUR">EUR</option>
                        <option value="DARHAM">DARHAM</option>
                        <option value="SAR">SAR</option>
                    </select>
                </div>
                <div class="ag-field">
                    <label>Payment Date *</label>
                    <input type="date" id="payDate" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="ag-field">
                    <label>Reference Number</label>
                    <input type="text" id="payReference" placeholder="Cheque/Transfer ID">
                </div>
                <div class="ag-field ag-field-full">
                    <label>Description</label>
                    <input type="text" id="payDescription" placeholder="Payment reference or note">
                </div>
                <div class="ag-field ag-field-full" id="payExchangeRateField" style="display:none;">
                    <label id="payExchangeRateLabel"><i class="feather icon-refresh-cw" style="font-size:12px;"></i> Exchange Rate</label>
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        <input type="number" step="0.00001" id="payExchangeRate" placeholder="0.00" style="flex:0 0 140px;">
                        <div style="flex:1;">
                            <small style="color:#64748b;font-size:11px;" id="payExchangeRateInstruction"></small>
                            <small style="color:#94a3b8;font-size:11px;display:block;margin-top:2px;" id="payExchangeRateExample"></small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ag-modal-footer">
                <button type="button" class="ag-btn" onclick="closeAll()">Cancel</button>
                <button type="submit" class="ag-btn ag-btn-green">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
const CSRF = <?= json_encode($csrf_token) ?>;
const API = '../api/expense/agency_settlement_actions.php';

function esc(s) { const d = document.createElement('div'); d.textContent = (s===null||s===undefined)?'':String(s); return d.innerHTML; }
function money(n) { return Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
function dateStr(s) { if(!s) return '—'; return new Date(s).toLocaleDateString(); }
function showAlert(msg, type) {
    const bar = document.getElementById('alertBar');
    bar.textContent = msg;
    bar.className = 'ag-alert show ' + type;
    setTimeout(() => { bar.className = 'ag-alert'; }, 5000);
}
function closeAll() { document.querySelectorAll('.ag-overlay').forEach(o => o.classList.remove('open')); }

function post(data) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.keys(data).forEach(k => { if (data[k] !== null && data[k] !== undefined) fd.append(k, data[k]); });
    return fetch(API, { method: 'POST', credentials: 'include', body: fd }).then(r => r.json());
}
function get(params) {
    return fetch(API + '?' + params, { credentials: 'include' }).then(r => r.json());
}

function pill(status) { return '<span class="ag-pill ' + esc(status) + '">' + esc(status) + '</span>'; }

let selectedAgencyBranchId = null;
let selectedAgencyName = null;

function loadSummary() {
    get('action=summary').then(res => {
        if (!res.success) throw new Error(res.message);
        const cards = document.getElementById('summaryCards');
        const s = res.summary || [];
        if (!s.length) {
            cards.innerHTML = '<div class="ag-card blue"><div class="ag-card-label">No agency expenses</div><div class="ag-card-sub">Mark expenses as for 2nd agency from the expense page</div></div>';
            return;
        }
        // Group by currency
        const byCurrency = {};
        let totalSettled = 0;
        const branches = new Set();
        s.forEach(r => {
            const cur = r.currency;
            if (!byCurrency[cur]) byCurrency[cur] = 0;
            byCurrency[cur] += Number(r.remaining) || 0;
            totalSettled += Number(r.settled_count) || 0;
            branches.add(r.branch_name);
        });
        let html = '';
        Object.keys(byCurrency).forEach(cur => {
            html += `<div class="ag-card blue">
                <div class="ag-card-label">Remaining (${esc(cur)})</div>
                <div class="ag-card-value">${money(byCurrency[cur])}</div>
                <div class="ag-card-sub">${esc(cur)}</div>
            </div>`;
        });
        html += `<div class="ag-card green">
                <div class="ag-card-label">Fully Settled</div>
                <div class="ag-card-value">${totalSettled}</div>
                <div class="ag-card-sub">Expenses settled</div>
            </div>
            <div class="ag-card amber">
                <div class="ag-card-label">Branches</div>
                <div class="ag-card-value">${branches.size}</div>
                <div class="ag-card-sub">With pending settlements</div>
            </div>`;
        cards.innerHTML = html;
    }).catch(e => showAlert(e.message, 'danger'));
}

function loadAgencies() {
    get('action=get_agency_list').then(res => {
        if (!res.success) throw new Error(res.message);
        const wrap = document.getElementById('agencyList');
        if (!res.agencies.length) {
            wrap.innerHTML = '<div class="ag-empty">No branch settlements found. Go to Expense Management to mark expenses for a branch.</div>';
            return;
        }
        wrap.innerHTML = res.agencies.map(a => {
            const remaining = a.remaining_by_currency || [];
            const remainingText = remaining.map(r => money(r.remaining) + ' ' + esc(r.currency)).join(' | ') || '0.00';
            const isCustom = !a.agency_branch_id;
            const agencyName = esc(a.name);
            const idArg = isCustom ? 'null' : a.agency_branch_id;
            const nameArg = isCustom ? "'" + agencyName + "'" : "null";
            return `<div class="ag-t-row" style="cursor:pointer;" onclick="selectAgency(${idArg}, ${nameArg})">
                <span style="font-weight:600;">${agencyName}</span>
                <span>${a.pending_count > 0 ? pill('pending') : (a.partial_count > 0 ? pill('partial') : pill('settled'))}</span>
                <span style="font-weight:600;">${remainingText}</span>
                <span class="ag-t-dim">${a.pending_count + a.partial_count}</span>
                <span class="ag-t-dim">${a.settled_count}</span>
                <span>
                    <button class="ag-btn ag-btn-blue ag-btn-sm" onclick="event.stopPropagation(); selectAgency(${idArg}, ${nameArg})">View</button>
                </span>
            </div>`;
        }).join('');
    }).catch(e => showAlert(e.message, 'danger'));
}

function selectAgency(agencyBranchId, name) {
    selectedAgencyBranchId = agencyBranchId || null;
    selectedAgencyName = agencyBranchId ? null : name;
    document.getElementById('selectedAgencyName').textContent = name;
    document.getElementById('agencyDetails').style.display = 'block';
    loadSettlements();
    loadPayments();
}

function loadSettlements() {
    if (!selectedAgencyBranchId && !selectedAgencyName) return;
    const sd = document.getElementById('filterStartDate').value;
    const ed = document.getElementById('filterEndDate').value;
    let params = 'action=get_settlements';
    if (selectedAgencyBranchId) params += '&agency_branch_id=' + selectedAgencyBranchId;
    else params += '&agency_name=' + encodeURIComponent(selectedAgencyName);
    if (sd && ed) params += '&startDate=' + sd + '&endDate=' + ed;

    get(params).then(res => {
        if (!res.success) throw new Error(res.message);
        const wrap = document.getElementById('settlementsList');
        if (!res.settlements.length) {
            wrap.innerHTML = '<div class="ag-empty">No expenses found for this branch in the selected range</div>';
            return;
        }

        let totalRemaining = 0;
        wrap.innerHTML = '<table class="ag-settled-table"><thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Expense Amt</th><th>Remaining</th><th>Status</th></tr></thead><tbody>'
        + res.settlements.map(s => {
            totalRemaining += Number(s.amount_owed);
            return `<tr>
                <td class="ag-t-dim">${dateStr(s.expense_date)}</td>
                <td class="ag-t-note" style="max-width:200px;" title="${esc(s.expense_description)}">${esc(s.expense_description) || '—'}</td>
                <td class="ag-t-dim">${esc(s.category_name) || '—'}</td>
                <td style="font-weight:600;">${money(s.expense_amount)} ${esc(s.currency)}</td>
                <td class="${s.amount_owed > 0 ? 'neg' : 'pos'}">${money(s.amount_owed)}</td>
                <td>${pill(s.status)}</td>
            </tr>`;
        }).join('')
        + '</tbody></table>'
        + `<div style="padding:10px 16px;background:#f4f6fa;font-weight:700;display:flex;justify-content:space-between;">
            <span>Total Remaining:</span><span>${money(totalRemaining)}</span></div>`;
    }).catch(e => showAlert(e.message, 'danger'));
}

function loadPayments() {
    if (!selectedAgencyBranchId && !selectedAgencyName) return;
    let params = 'action=get_payments';
    if (selectedAgencyBranchId) params += '&agency_branch_id=' + selectedAgencyBranchId;
    else params += '&agency_name=' + encodeURIComponent(selectedAgencyName);
    get(params).then(res => {
        if (!res.success) throw new Error(res.message);
        const wrap = document.getElementById('paymentsList');
        if (!res.payments.length) {
            wrap.innerHTML = '<div class="ag-empty">No payments recorded for this branch</div>';
            return;
        }
        wrap.innerHTML = '<table class="ag-settled-table"><thead><tr><th>Date</th><th>Description</th><th>Amount</th><th>Currency</th><th>Account</th><th>Rate</th><th>Actions</th></tr></thead><tbody>'
        + res.payments.map(p => `<tr>
            <td class="ag-t-dim">${dateStr(p.payment_date)}</td>
            <td class="ag-t-note" style="max-width:180px;" title="${esc(p.description)}">${esc(p.description) || '—'}</td>
            <td class="pos">+${money(p.amount)}</td>
            <td>${esc(p.currency)}</td>
            <td class="ag-t-dim">${esc(p.main_account_name) || '—'}</td>
            <td class="ag-t-dim">${p.exchange_rate ? esc(p.exchange_rate) : '—'}</td>
            <td>
                <button class="ag-btn ag-btn-sm" style="background:#2563eb;color:#fff;border-color:#2563eb;" onclick="window.open('../api/expense/print_agency_payment.php?id=${p.id}', '_blank')">Print</button>
                <button class="ag-btn ag-btn-red ag-btn-sm" onclick="deletePayment(${p.id})">Delete</button>
            </td>
        </tr>`).join('')
        + '</tbody></table>';
    }).catch(e => showAlert(e.message, 'danger'));
}

function openRecordPayment() {
    if (selectedAgencyBranchId) {
        document.getElementById('payAgency').value = selectedAgencyBranchId;
    } else if (selectedAgencyName) {
        document.getElementById('payAgency').value = '__custom__';
        document.getElementById('payCustomName').value = selectedAgencyName;
        document.getElementById('payCustomNameGroup').style.display = 'block';
    }
    document.getElementById('payAmount').value = '';
    document.getElementById('payDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('payDescription').value = '';
    document.getElementById('payReference').value = '';
    document.getElementById('payMainAccount').value = '';
    document.getElementById('payExchangeRateField').style.display = 'none';
    document.getElementById('payExchangeRate').value = '';
    document.getElementById('payExchangeRate').removeAttribute('required');
    document.getElementById('paymentOverlay').classList.add('open');
}

document.getElementById('payAgency').addEventListener('change', function() {
    var isCustom = this.value === '__custom__';
    document.getElementById('payCustomNameGroup').style.display = isCustom ? 'block' : 'none';
    if (!isCustom) document.getElementById('payCustomName').value = '';
    togglePayExchangeRate();
});

document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var branch = document.getElementById('payAgency');
    var amount = document.getElementById('payAmount');
    var mainAccount = document.getElementById('payMainAccount');
    var customName = document.getElementById('payCustomName');
    var valid = true;
    [branch, amount, mainAccount].forEach(function(el) { el.style.borderColor = ''; });
    customName.style.borderColor = '';
    if (!branch.value) { branch.style.borderColor = '#dc2626'; valid = false; }
    if (!amount.value || parseFloat(amount.value) <= 0) { amount.style.borderColor = '#dc2626'; valid = false; }
    if (!mainAccount.value) { mainAccount.style.borderColor = '#dc2626'; valid = false; }
    if (branch.value === '__custom__' && !customName.value.trim()) { customName.style.borderColor = '#dc2626'; valid = false; }
    if (!valid) { alert('Please fill in all required fields'); return; }

    var payload = {
        action: 'record_payment',
        amount: amount.value,
        currency: document.getElementById('payCurrency').value,
        payment_date: document.getElementById('payDate').value,
        description: document.getElementById('payDescription').value,
        reference_number: document.getElementById('payReference').value,
        main_account_id: mainAccount.value
    };

    if (branch.value === '__custom__') {
        payload.agency_name = customName.value.trim();
    } else {
        payload.agency_branch_id = branch.value;
    }

    if (document.getElementById('payExchangeRateField').style.display !== 'none') {
        var rate = document.getElementById('payExchangeRate').value;
        if (rate) payload.exchange_rate = rate;
    }

    post(payload).then(res => {
        if (!res.success) throw new Error(res.message);
        showAlert(res.message, 'success');
        closeAll();
        loadSummary();
        loadAgencies();
        if (selectedAgencyBranchId || selectedAgencyName) {
            loadSettlements();
            loadPayments();
        }
    }).catch(e => showAlert(e.message, 'danger'));
});

function deletePayment(id) {
    if (!confirm('Delete this payment? Settlements will be reversed.')) return;
    post({ action: 'delete_payment', payment_id: id }).then(res => {
        if (!res.success) throw new Error(res.message);
        showAlert(res.message, 'success');
        loadSummary();
        loadAgencies();
        if (selectedAgencyBranchId || selectedAgencyName) {
            loadSettlements();
            loadPayments();
        }
    }).catch(e => showAlert(e.message, 'danger'));
}

function clearFilter() {
    document.getElementById('filterStartDate').value = '';
    document.getElementById('filterEndDate').value = '';
    if (selectedAgencyBranchId || selectedAgencyName) loadSettlements();
}

function printSettlement() {
    if (!selectedAgencyBranchId && !selectedAgencyName) {
        alert('Please select an agency first');
        return;
    }
    var sd = document.getElementById('filterStartDate').value;
    var ed = document.getElementById('filterEndDate').value;
    var url = '../api/expense/print_agency_settlement.php?';
    if (selectedAgencyBranchId) {
        url += 'branch_id=' + selectedAgencyBranchId;
    } else {
        url += 'agency_name=' + encodeURIComponent(selectedAgencyName);
    }
    if (sd && ed) url += '&start_date=' + sd + '&end_date=' + ed;
    window.open(url, '_blank');
}

function getCurrencyDisplay(code) {
    var map = { 'DARHAM': 'AED' };
    return map[code] || code;
}

function togglePayExchangeRate() {
    var paymentCurrency = document.getElementById('payCurrency').value;
    var selectedBranch = document.getElementById('payAgency').value;
    var customName = document.getElementById('payCustomName').value;
    var field = document.getElementById('payExchangeRateField');
    var rateInput = document.getElementById('payExchangeRate');

    if (!paymentCurrency || (!selectedBranch && !customName)) {
        field.style.display = 'none';
        rateInput.removeAttribute('required');
        rateInput.value = '';
        return;
    }

    var params = 'action=get_settlements';
    if (selectedBranch && selectedBranch !== '__custom__') {
        params += '&agency_branch_id=' + selectedBranch;
    } else if (customName) {
        params += '&agency_name=' + encodeURIComponent(customName);
    } else {
        field.style.display = 'none';
        rateInput.removeAttribute('required');
        rateInput.value = '';
        return;
    }

    // Get the settlement currency for this agency from the pending expenses
    get(params).then(function(res) {
        if (!res.success || !res.settlements || !res.settlements.length) {
            field.style.display = 'none';
            rateInput.removeAttribute('required');
            rateInput.value = '';
            return;
        }

        // Use the currency of the first pending settlement as the base
        var settlementCurrency = res.settlements[0].currency;
        if (paymentCurrency === settlementCurrency) {
            field.style.display = 'none';
            rateInput.removeAttribute('required');
            rateInput.value = '';
            return;
        }

        // Determine anchor currency
        var currencies = [paymentCurrency, settlementCurrency];
        var anchorCurrency = settlementCurrency;
        if (currencies.indexOf('USD') !== -1) anchorCurrency = 'USD';
        else if (currencies.indexOf('EUR') !== -1) anchorCurrency = 'EUR';
        else if (currencies.indexOf('AED') !== -1 || currencies.indexOf('DARHAM') !== -1) anchorCurrency = 'AED';
        else if (currencies.indexOf('AFS') !== -1) anchorCurrency = 'AFS';

        var anchorDisplay = getCurrencyDisplay(anchorCurrency);
        var otherCurrency = anchorCurrency === settlementCurrency ? paymentCurrency : settlementCurrency;
        var otherDisplay = getCurrencyDisplay(otherCurrency);

        var label = '<i class="feather icon-refresh-cw" style="font-size:12px;"></i> ' + anchorDisplay + ' to ' + otherDisplay + ' Exchange Rate';
        document.getElementById('payExchangeRateLabel').innerHTML = label;
        document.getElementById('payExchangeRateInstruction').textContent = 'Enter how many ' + otherDisplay + ' equals 1 ' + anchorDisplay;

        var examples = {
            'USD-AFS': 'Example: 1 USD = 88 AFS, enter 88',
            'USD-EUR': 'Example: 1 USD = 0.95 EUR, enter 0.95',
            'USD-AED': 'Example: 1 USD = 3.67 AED, enter 3.67',
            'AFS-USD': 'Example: 1 USD = 88 AFS, enter 88',
            'EUR-USD': 'Example: 1 USD = 0.95 EUR, enter 0.95',
            'AED-USD': 'Example: 1 USD = 3.67 AED, enter 3.67',
            'EUR-AFS': 'Example: 1 EUR = 92.5 AFS, enter 92.5',
            'AFS-EUR': 'Example: 1 EUR = 92.5 AFS, enter 92.5',
            'EUR-AED': 'Example: 1 EUR = 3.86 AED, enter 3.86',
            'AED-EUR': 'Example: 1 EUR = 3.86 AED, enter 3.86',
            'AED-AFS': 'Example: 1 AED = 23.99 AFS, enter 23.99',
            'AFS-AED': 'Example: 1 AED = 23.99 AFS, enter 23.99',
            'USD-SAR': 'Example: 1 USD = 3.75 SAR, enter 3.75',
            'SAR-USD': 'Example: 1 USD = 3.75 SAR, enter 3.75',
            'EUR-SAR': 'Example: 1 EUR = 4.07 SAR, enter 4.07',
            'SAR-EUR': 'Example: 1 EUR = 4.07 SAR, enter 4.07',
            'AED-SAR': 'Example: 1 AED = 1.02 SAR, enter 1.02',
            'SAR-AED': 'Example: 1 AED = 1.02 SAR, enter 1.02',
            'AFS-SAR': 'Example: 1 AFS = 18.67 SAR, enter 18.67',
            'SAR-AFS': 'Example: 1 AFS = 18.67 SAR, enter 18.67'
        };
        var key = getCurrencyDisplay(settlementCurrency) + '-' + getCurrencyDisplay(paymentCurrency);
        document.getElementById('payExchangeRateExample').textContent = examples[key] || 'Enter the exchange rate';

        field.style.display = 'block';
        rateInput.setAttribute('required', 'true');
        rateInput.value = '';
    });
}

document.getElementById('payCurrency').addEventListener('change', togglePayExchangeRate);
document.getElementById('payAgency').addEventListener('change', togglePayExchangeRate);

document.querySelectorAll('.ag-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) closeAll(); });
});

loadSummary();
loadAgencies();
</script>
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
