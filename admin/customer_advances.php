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

// Fetch main accounts for payment forms
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
.ag-card.blue::before{background:#2563eb}.ag-card.green::before{background:#16a34a}.ag-card.amber::before{background:#d97706}.ag-card.red::before{background:#dc2626}.ag-card.purple::before{background:#7c3aed}
.ag-card-value{font-size:20px;font-weight:700;line-height:1.1}
.ag-card-label{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.3px}
.ag-card-sub{font-size:12px;color:#64748b}
.ag-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;font-size:13px;font-weight:500;padding:8px 15px;border-radius:8px;border:1px solid rgba(0,0,0,.13);background:#fff;color:#0f172a;cursor:pointer;transition:background .15s;white-space:nowrap;line-height:1}
.ag-btn:hover{background:#f0f2f5}.ag-btn:disabled{opacity:.6;cursor:not-allowed}
.ag-btn-green{background:#16a34a;color:#fff;border-color:#16a34a}.ag-btn-green:hover{background:#15803d}
.ag-btn-red{background:#dc2626;color:#fff;border-color:#dc2626}.ag-btn-red:hover{background:#b91c1c}
.ag-btn-blue{background:#2563eb;color:#fff;border-color:#2563eb}.ag-btn-blue:hover{background:#1d4ed8}
.ag-btn-amber{background:#d97706;color:#fff;border-color:#d97706}.ag-btn-amber:hover{background:#b45309}
.ag-btn-sm{padding:6px 10px;font-size:12px}
.ag-section{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin:1.5rem 0 .75rem}
.ag-block{background:#fff;border:1px solid #eef1f5;border-radius:12px;padding:1.25rem;margin-bottom:1.25rem;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.ag-block-title{font-size:15px;font-weight:700;margin-bottom:.75rem;display:flex;align-items:center;gap:8px}
.ag-table-wrap{background:#fff;border:1px solid #eef1f5;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.04);overflow:hidden}
.ag-t-head,.ag-t-row{display:grid;grid-template-columns:160px 120px 1fr 110px 100px 100px 140px;gap:12px;padding:10px 16px;align-items:center}
.ag-t-head{background:#f4f6fa;border-bottom:1px solid #eef1f5}
.ag-t-head span{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#64748b}
.ag-t-row{border-bottom:1px solid #eef1f5;transition:background .1s}
.ag-t-row:last-child{border-bottom:none}.ag-t-row:hover{background:#f4f6fa}
.ag-t-dim{font-size:12.5px;color:#64748b}
.ag-t-note{font-size:12.5px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ag-pill{display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;letter-spacing:.2px;text-transform:capitalize}
.ag-pill.pending{background:#fffbeb;color:#b45309}.ag-pill.completed{background:#f0fdf4;color:#15803d}.ag-pill.paid_by_agency{background:#eff6ff;color:#1d4ed8}
.ag-empty{padding:3rem 1rem;text-align:center;font-size:13px;color:#94a3b8}
.ag-overlay{display:none!important;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(15,23,42,.45);backdrop-filter:blur(2px);z-index:99999;align-items:center;justify-content:center;padding:1rem;overflow-y:auto}
.ag-overlay.open{display:flex!important}
.ag-modal{background:#fff;border:1px solid #eef1f5;border-radius:12px;padding:1.5rem;width:520px;max-width:100%;box-shadow:0 20px 60px rgba(0,0,0,.18);animation:agIn .2s ease-out;position:relative;z-index:100000;margin:auto;flex-shrink:0}
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
.ag-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
.ag-form-grid .ag-field-full{grid-column:1/-1}
.ag-filter-bar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.25rem;padding:1rem;background:#fff;border:1px solid #eef1f5;border-radius:12px}
.ag-filter-bar .ag-field{margin-bottom:0;min-width:160px}
.ag-settled-table{width:100%;border-collapse:collapse;font-size:13px}
.ag-settled-table th{text-align:left;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#64748b;padding:8px 10px;border-bottom:1px solid #eef1f5;background:#f4f6fa}
.ag-settled-table td{padding:8px 10px;border-bottom:1px solid #eef1f5;vertical-align:top}
.ag-settled-table tr:last-child td{border-bottom:none}
.ag-settled-table .pos{color:#16a34a;font-weight:600}
.ag-settled-table .neg{color:#dc2626;font-weight:600}
</style>

<div class="pcoded-main-container">
<div class="pcoded-wrapper">
<div class="main-body">
<div class="page-wrapper">
<div class="ag-wrap">

    <div class="ag-topbar">
        <div>
            <div class="ag-topbar-title">Umrah Hawala</div>
            <div class="ag-topbar-sub">Track when suppliers give money to customers on our behalf</div>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="ag-btn ag-btn-blue" onclick="openRecordAdvance()">
                <i class="feather icon-plus"></i> Record Umrah Hawala
            </button>
            <button class="ag-btn ag-btn-green" onclick="openRecordPayment()">
                <i class="feather icon-dollar-sign"></i> Record Payment
            </button>
            <button class="ag-btn" onclick="exportPrint()">
                <i class="feather icon-printer"></i> Print
            </button>
            <button class="ag-btn" onclick="exportExcel()">
                <i class="feather icon-download"></i> Excel
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="ag-block" style="margin-bottom:1rem;">
        <div style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;">
            <div class="ag-field" style="margin-bottom:0;">
                <label>From Date</label>
                <input type="date" id="reportStartDate" style="padding:7px 10px;font-size:13px;border:1px solid rgba(0,0,0,.13);border-radius:8px;">
            </div>
            <div class="ag-field" style="margin-bottom:0;">
                <label>To Date</label>
                <input type="date" id="reportEndDate" style="padding:7px 10px;font-size:13px;border:1px solid rgba(0,0,0,.13);border-radius:8px;">
            </div>
            <button class="ag-btn ag-btn-blue ag-btn-sm" onclick="applyReportFilter()">
                <i class="feather icon-filter"></i> Apply
            </button>
            <button class="ag-btn ag-btn-sm" onclick="resetReportFilter()">Reset</button>
            <div style="display:flex;gap:4px;margin-left:8px;">
                <button class="ag-btn ag-btn-sm" onclick="setReportRange('today')">Today</button>
                <button class="ag-btn ag-btn-sm" onclick="setReportRange('week')">This Week</button>
                <button class="ag-btn ag-btn-sm" onclick="setReportRange('month')">This Month</button>
                <button class="ag-btn ag-btn-sm" onclick="setReportRange('quarter')">This Quarter</button>
                <button class="ag-btn ag-btn-sm" onclick="setReportRange('year')">This Year</button>
            </div>
        </div>
    </div>

    <div class="ag-alert" id="alertBar"></div>

    <!-- Summary Cards -->
    <div class="ag-section">Overview</div>
    <div class="ag-cards" id="summaryCards">
        <div class="ag-card blue"><div class="ag-card-sub">Loading...</div></div>
    </div>

    <!-- Customer List -->
    <div class="ag-section">Customers</div>
    <div class="ag-filter-bar">
        <div class="ag-field">
            <label>Search</label>
            <input type="text" id="searchInput" placeholder="Customer name or phone" onkeyup="debounceSearch()">
        </div>
        <div class="ag-field">
            <label>Status</label>
            <select id="statusFilter" onchange="loadCustomers()">
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="paid_by_agency">Paid to Supplier</option>
                <option value="completed">Completed</option>
            </select>
        </div>
    </div>
    <div class="ag-table-wrap">
        <div class="ag-t-head">
            <span>Customer</span><span>Phone</span><span>Supplier</span><span>Amount</span><span>Pending</span><span>Status</span><span>Actions</span>
        </div>
        <div id="customerList"><div class="ag-empty">Loading...</div></div>
    </div>

    <!-- Selected Customer Details -->
    <div id="customerDetails" style="display:none;">
        <div class="ag-section">Umrah Hawala for <span id="selectedCustomerName"></span></div>

        <div class="ag-filter-bar">
            <div class="ag-field">
                <label>From Date</label>
                <input type="date" id="filterStartDate">
            </div>
            <div class="ag-field">
                <label>To Date</label>
                <input type="date" id="filterEndDate">
            </div>
            <button class="ag-btn ag-btn-blue ag-btn-sm" onclick="loadAdvances()">
                <i class="feather icon-search"></i> Filter
            </button>
            <button class="ag-btn ag-btn-sm" onclick="clearFilter()">Reset</button>
        </div>

        <div class="ag-block">
            <div class="ag-block-title">
                <i class="feather icon-file-text"></i> Umrah Hawala
            </div>
            <div class="ag-table-wrap">
                <div id="advancesList"><div class="ag-empty">Select a customer to view details</div></div>
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

<!-- Record Advance Modal -->
<div class="ag-overlay" id="advanceOverlay">
    <div class="ag-modal">
        <div class="ag-modal-head">
            <h2>Record Umrah Hawala</h2>
            <button class="ag-modal-close" onclick="closeAll()">&times;</button>
        </div>
        <form id="advanceForm" novalidate>
            <div class="ag-form-grid">
                <div class="ag-field">
                    <label>Customer Name *</label>
                    <select id="advCustomerSelect" onchange="onAdvCustomerChange()">
                        <option value="">Select Customer</option>
                    </select>
                </div>
                <div class="ag-field" id="advNewCustomerGroup" style="display:none;">
                    <label>New Customer Name *</label>
                    <input type="text" id="advCustomerName" placeholder="Enter new customer name" maxlength="255">
                </div>
                <div class="ag-field" id="advPhoneGroup" style="display:none;">
                    <label>Customer Phone</label>
                    <input type="text" id="advCustomerPhone" placeholder="Phone number" maxlength="50">
                </div>
                <div class="ag-field">
                    <label>Supplier Name *</label>
                    <select id="advSupplierSelect" onchange="onAdvSupplierChange()">
                        <option value="">Select Supplier</option>
                    </select>
                </div>
                <div class="ag-field" id="advNewSupplierGroup" style="display:none;">
                    <label>New Supplier Name *</label>
                    <input type="text" id="advSupplierName" placeholder="Enter new supplier name" maxlength="255">
                </div>
                <div class="ag-field">
                    <label>Amount *</label>
                    <input type="number" step="0.01" min="0.01" id="advAmount" required placeholder="0.00">
                </div>
                <div class="ag-field">
                    <label>Currency *</label>
                    <select id="advCurrency" required>
                        <option value="USD">USD</option>
                        <option value="AFS">AFS</option>
                        <option value="EUR">EUR</option>
                        <option value="DARHAM">DARHAM</option>
                        <option value="SAR">SAR</option>
                    </select>
                </div>
                <div class="ag-field">
                    <label>Date *</label>
                    <input type="date" id="advDate" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="ag-field ag-field-full">
                    <label>Reason</label>
                    <textarea id="advReason" rows="2" placeholder="Why customer needs money"></textarea>
                </div>
            </div>
            <div class="ag-modal-footer">
                <button type="button" class="ag-btn" onclick="closeAll()">Cancel</button>
                <button type="submit" class="ag-btn ag-btn-green">Record Umrah Hawala</button>
            </div>
        </form>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="ag-overlay" id="paymentOverlay">
    <div class="ag-modal">
        <div class="ag-modal-head">
            <h2>Record Payment</h2>
            <button class="ag-modal-close" onclick="closeAll()">&times;</button>
        </div>
        <form id="paymentForm" novalidate>
            <div class="ag-form-grid">
                <div class="ag-field">
                    <label>Supplier *</label>
                    <select id="paySupplier" required onchange="onPaymentSupplierChange()">
                        <option value="">Select Supplier</option>
                    </select>
                </div>
                <div class="ag-field">
                    <label>Customer *</label>
                    <select id="payCustomer" required onchange="onPaymentCustomerChange()">
                        <option value="">Select supplier first</option>
                    </select>
                </div>
                <div class="ag-field">
                    <label>Payment Type *</label>
                    <select id="payType" required>
                        <option value="">Select Type</option>
                        <option value="incoming">Customer Pays Us (Incoming)</option>
                        <option value="outgoing">We Pay Supplier (Outgoing)</option>
                    </select>
                </div>
                <div class="ag-field">
                    <label>Amount *</label>
                    <input type="number" step="0.01" min="0.01" id="payAmount" required placeholder="0.00">
                </div>
                <div class="ag-field">
                    <label>Currency *</label>
                    <select id="payCurrency" required>
                        <option value="USD">USD</option>
                        <option value="AFS">AFS</option>
                        <option value="EUR">EUR</option>
                        <option value="DARHAM">DARHAM</option>
                        <option value="SAR">SAR</option>
                    </select>
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
                    <label>Payment Date *</label>
                    <input type="date" id="payDate" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="ag-field" id="exchangeRateGroup" style="display:none;">
                    <label>Exchange Rate *</label>
                    <input type="number" step="0.000001" min="0" id="payExchangeRate" value="1" placeholder="1.000000">
                    <small style="color:#64748b;font-size:11px;" id="exchangeRateHint"></small>
                </div>
                <div class="ag-field" id="convertedAmountGroup" style="display:none;">
                    <label>Converted Amount</label>
                    <input type="text" id="payConvertedAmount" readonly placeholder="Auto-calculated" style="background:#f8f9fa;">
                    <small style="color:#64748b;font-size:11px;" id="convertedAmountHint"></small>
                </div>
                <div class="ag-field">
                    <label>Reference Number</label>
                    <input type="text" id="payReference" placeholder="Cheque/Transfer ID">
                </div>
                <div class="ag-field ag-field-full">
                    <label>Description</label>
                    <input type="text" id="payDescription" placeholder="Payment note">
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
const API = '../api/customer_advance/customer_advance_actions.php';

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

function pill(status) { return '<span class="ag-pill ' + esc(status) + '">' + esc(status).replace(/_/g,' ') + '</span>'; }

let selectedCustomerName = null;
let searchTimeout = null;

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadCustomers, 300);
}

function loadSummary() {
    var rd = getReportDates();
    let params = 'action=summary';
    if (rd.start) params += '&start_date=' + encodeURIComponent(rd.start);
    if (rd.end) params += '&end_date=' + encodeURIComponent(rd.end);
    get(params).then(res => {
        if (!res.success) throw new Error(res.message);
        const cards = document.getElementById('summaryCards');
        const s = res.summary || {};
        const owedAmount = Number(s.total_owed_to_suppliers) || 0;
        const paidAmount = Number(s.total_paid_to_suppliers) || 0;
        const completedAmount = Number(s.total_completed) || 0;
        const owedCount = Number(s.owed_count) || 0;
        const paidCount = Number(s.paid_count) || 0;
        const completedCount = Number(s.completed_count) || 0;

        let incomingHtml = '';
        (s.incoming || []).forEach(r => {
            incomingHtml += `<div class="ag-card green">
                <div class="ag-card-label">Received (${esc(r.currency)})</div>
                <div class="ag-card-value">${money(r.total_incoming)}</div>
                <div class="ag-card-sub">Customer payments</div>
            </div>`;
        });

        let outgoingHtml = '';
        (s.outgoing || []).forEach(r => {
            outgoingHtml += `<div class="ag-card red">
                <div class="ag-card-label">Paid to Suppliers (${esc(r.currency)})</div>
                <div class="ag-card-value">${money(r.total_outgoing)}</div>
                <div class="ag-card-sub">Supplier payments</div>
            </div>`;
        });

        let html = `
            <div class="ag-card amber">
                <div class="ag-card-label">Owed to Suppliers</div>
                <div class="ag-card-value">${money(owedAmount)}</div>
                <div class="ag-card-sub">${owedCount} hawala(s) - we need to pay</div>
            </div>
            <div class="ag-card blue">
                <div class="ag-card-label">Paid to Suppliers</div>
                <div class="ag-card-value">${money(paidAmount)}</div>
                <div class="ag-card-sub">${paidCount} hawala(s) - waiting for customer</div>
            </div>
            <div class="ag-card green">
                <div class="ag-card-label">Completed</div>
                <div class="ag-card-value">${money(completedAmount)}</div>
                <div class="ag-card-sub">${completedCount} hawala(s) - fully settled</div>
            </div>
            ${incomingHtml}${outgoingHtml}`;
        cards.innerHTML = html;
    }).catch(e => showAlert(e.message, 'danger'));
}

function loadCustomers() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    var rd = getReportDates();
    let params = 'action=get_customers';
    if (search) params += '&search=' + encodeURIComponent(search);
    if (status) params += '&status=' + encodeURIComponent(status);
    if (rd.start) params += '&start_date=' + encodeURIComponent(rd.start);
    if (rd.end) params += '&end_date=' + encodeURIComponent(rd.end);

    get(params).then(res => {
        if (!res.success) throw new Error(res.message);
        const wrap = document.getElementById('customerList');
        if (!res.customers.length) {
            wrap.innerHTML = '<div class="ag-empty">No umrah hawala records found. Click "Record Umrah Hawala" to get started.</div>';
            return;
        }
        wrap.innerHTML = res.customers.map(c => {
            const phone = c.customer_phone || '—';
            return `<div class="ag-t-row" style="cursor:pointer;" onclick="selectCustomer('${esc(c.customer_name)}')">
                <span style="font-weight:600;">${esc(c.customer_name)}</span>
                <span class="ag-t-dim">${esc(phone)}</span>
                <span class="ag-t-dim">${esc(c.total_advances)} hawala(s)</span>
                <span style="font-weight:600;">${money(c.total_amount)} ${esc(c.currency)}</span>
                <span class="${c.pending_amount > 0 ? 'neg' : ''}">${money(c.pending_amount)}</span>
                <span>${c.pending_count > 0 ? pill('pending') : (c.owed_count > 0 ? pill('paid_by_agency') : pill('completed'))}</span>
                <span>
                    <button class="ag-btn ag-btn-blue ag-btn-sm" onclick="event.stopPropagation(); selectCustomer('${esc(c.customer_name)}')">View</button>
                </span>
            </div>`;
        }).join('');
    }).catch(e => showAlert(e.message, 'danger'));
}

function selectCustomer(name) {
    selectedCustomerName = name;
    document.getElementById('selectedCustomerName').textContent = name;
    document.getElementById('customerDetails').style.display = 'block';
    loadAdvances();
    loadPayments();
    populateAdvanceDropdown(name);
}

function loadAdvances() {
    if (!selectedCustomerName) return;
    const sd = document.getElementById('filterStartDate').value;
    const ed = document.getElementById('filterEndDate').value;
    let params = 'action=get_advances&customer_name=' + encodeURIComponent(selectedCustomerName);

    get(params).then(res => {
        if (!res.success) throw new Error(res.message);
        const wrap = document.getElementById('advancesList');
        let advances = res.advances || [];

        // Client-side date filter
        if (sd && ed) {
            advances = advances.filter(a => a.advance_date >= sd && a.advance_date <= ed);
        }

        if (!advances.length) {
            wrap.innerHTML = '<div class="ag-empty">No umrah hawala found for this customer</div>';
            return;
        }

        let totalAmount = 0;
        wrap.innerHTML = '<table class="ag-settled-table"><thead><tr><th>Date</th><th>Supplier</th><th>Amount</th><th>Currency</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead><tbody>'
        + advances.map(a => {
            totalAmount += Number(a.amount);
            const canDelete = a.payments.length === 0;
            const canMarkPaid = a.status === 'pending';
            return `<tr>
                <td class="ag-t-dim">${dateStr(a.advance_date)}</td>
                <td style="font-weight:600;">${esc(a.supplier_name)}</td>
                <td style="font-weight:600;">${money(a.amount)}</td>
                <td>${esc(a.currency)}</td>
                <td class="ag-t-note" style="max-width:180px;" title="${esc(a.reason)}">${esc(a.reason) || '—'}</td>
                <td>${pill(a.status)}</td>
                <td>
                    ${canMarkPaid ? `<button class="ag-btn ag-btn-amber ag-btn-sm" onclick="markSupplierPaid(${a.id})">Mark Paid</button>` : ''}
                    ${canDelete ? `<button class="ag-btn ag-btn-red ag-btn-sm" onclick="deleteAdvance(${a.id})">Delete</button>` : ''}
                </td>
            </tr>`;
        }).join('')
        + '</tbody></table>'
        + `<div style="padding:10px 16px;background:#f4f6fa;font-weight:700;display:flex;justify-content:space-between;">
            <span>Total:</span><span>${money(totalAmount)}</span></div>`;
    }).catch(e => showAlert(e.message, 'danger'));
}

function loadPayments() {
    if (!selectedCustomerName) return;
    let params = 'action=get_payments&customer_name=' + encodeURIComponent(selectedCustomerName);
    get(params).then(res => {
        if (!res.success) throw new Error(res.message);
        const wrap = document.getElementById('paymentsList');
        if (!res.payments.length) {
            wrap.innerHTML = '<div class="ag-empty">No payments recorded for this customer</div>';
            return;
        }
        wrap.innerHTML = '<table class="ag-settled-table"><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Currency</th><th>Rate</th><th>Converted</th><th>Account</th><th>Description</th><th>Actions</th></tr></thead><tbody>'
        + res.payments.map(p => {
            var rateDisplay = (p.exchange_rate && p.exchange_rate != 1) ? parseFloat(p.exchange_rate).toFixed(6) : '—';
            var convertedDisplay = (p.converted_amount && p.converted_amount != p.amount) ? money(p.converted_amount) : '—';
            return `<tr>
            <td class="ag-t-dim">${dateStr(p.payment_date)}</td>
            <td>${p.type === 'incoming' ? '<span style="color:#16a34a;font-weight:600;">Incoming</span>' : '<span style="color:#dc2626;font-weight:600;">Outgoing</span>'}</td>
            <td class="${p.type === 'incoming' ? 'pos' : 'neg'}">${p.type === 'incoming' ? '+' : '-'}${money(p.amount)}</td>
            <td>${esc(p.currency)}</td>
            <td class="ag-t-dim">${rateDisplay}</td>
            <td>${convertedDisplay}</td>
            <td class="ag-t-dim">${esc(p.main_account_name) || '—'}</td>
            <td class="ag-t-note" style="max-width:140px;" title="${esc(p.description)}">${esc(p.description) || '—'}</td>
            <td>
                <button class="ag-btn ag-btn-red ag-btn-sm" onclick="deletePayment(${p.id})">Delete</button>
            </td>
        </tr>`;
        }).join('')
        + '</tbody></table>';
    }).catch(e => showAlert(e.message, 'danger'));
}

function loadAdvanceSuppliers() {
    return get('action=get_suppliers').then(res => {
        const sel = document.getElementById('advSupplierSelect');
        sel.innerHTML = '<option value="">Select Supplier</option>';
        if (!res.success || !res.suppliers) return;
        res.suppliers.forEach(s => {
            sel.innerHTML += `<option value="${esc(s.supplier_name)}">${esc(s.supplier_name)} (${s.total_advances} hawala(s))</option>`;
        });
        sel.innerHTML += '<option value="__new__">+ Add New Supplier</option>';
    });
}

function onAdvSupplierChange() {
    var val = document.getElementById('advSupplierSelect').value;
    var newGroup = document.getElementById('advNewSupplierGroup');
    var nameInput = document.getElementById('advSupplierName');
    if (val === '__new__') {
        newGroup.style.display = 'block';
        nameInput.value = '';
    } else {
        newGroup.style.display = 'none';
        nameInput.value = val;
    }
}

function loadAdvanceCustomers() {
    return get('action=get_customer_names').then(res => {
        const sel = document.getElementById('advCustomerSelect');
        sel.innerHTML = '<option value="">Select Customer</option>';
        if (!res.success || !res.customers) return;
        res.customers.forEach(c => {
            const phone = c.customer_phone ? ' (' + esc(c.customer_phone) + ')' : '';
            sel.innerHTML += `<option value="${esc(c.customer_name)}" data-phone="${esc(c.customer_phone || '')}">${esc(c.customer_name)}${phone}</option>`;
        });
        sel.innerHTML += '<option value="__new__">+ Add New Customer</option>';
    });
}

function onAdvCustomerChange() {
    var val = document.getElementById('advCustomerSelect').value;
    var newGroup = document.getElementById('advNewCustomerGroup');
    var phoneGroup = document.getElementById('advPhoneGroup');
    var nameInput = document.getElementById('advCustomerName');
    var phoneInput = document.getElementById('advCustomerPhone');
    if (val === '__new__') {
        newGroup.style.display = 'block';
        phoneGroup.style.display = 'block';
        nameInput.value = '';
        phoneInput.value = '';
    } else if (val) {
        newGroup.style.display = 'none';
        phoneGroup.style.display = 'none';
        nameInput.value = val;
        var opt = document.getElementById('advCustomerSelect').selectedOptions[0];
        phoneInput.value = opt ? opt.dataset.phone || '' : '';
    } else {
        newGroup.style.display = 'none';
        phoneGroup.style.display = 'none';
        nameInput.value = '';
        phoneInput.value = '';
    }
}

function openRecordAdvance() {
    document.getElementById('advCustomerSelect').value = '';
    document.getElementById('advCustomerName').value = '';
    document.getElementById('advCustomerPhone').value = '';
    document.getElementById('advNewCustomerGroup').style.display = 'none';
    document.getElementById('advPhoneGroup').style.display = 'none';
    document.getElementById('advSupplierSelect').value = '';
    document.getElementById('advSupplierName').value = '';
    document.getElementById('advNewSupplierGroup').style.display = 'none';
    document.getElementById('advAmount').value = '';
    document.getElementById('advDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('advReason').value = '';
    loadAdvanceCustomers();
    loadAdvanceSuppliers();
    document.getElementById('advanceOverlay').classList.add('open');
}

function loadPaymentSuppliers() {
    return get('action=get_suppliers').then(res => {
        const sel = document.getElementById('paySupplier');
        sel.innerHTML = '<option value="">Select Supplier</option>';
        if (!res.success || !res.suppliers) return;
        res.suppliers.forEach(s => {
            sel.innerHTML += `<option value="${esc(s.supplier_name)}">${esc(s.supplier_name)} (${s.total_advances} hawala(s), ${money(s.total_amount)})</option>`;
        });
    });
}

let advanceCurrencyCache = {};

function onPaymentSupplierChange() {
    var supplierName = document.getElementById('paySupplier').value;
    var custSel = document.getElementById('payCustomer');
    if (!supplierName) {
        custSel.innerHTML = '<option value="">Select supplier first</option>';
        return;
    }
    get('action=get_customers_by_supplier&supplier_name=' + encodeURIComponent(supplierName)).then(res => {
        custSel.innerHTML = '<option value="">Select Customer</option>';
        if (!res.success || !res.customers) return;
        res.customers.forEach(c => {
            advanceCurrencyCache[c.customer_name] = c.currency;
            const label = esc(c.customer_name) + (c.customer_phone ? ' (' + esc(c.customer_phone) + ')' : '') + ' - ' + money(c.pending_amount) + ' ' + esc(c.currency) + ' pending';
            custSel.innerHTML += `<option value="${esc(c.customer_name)}">${label}</option>`;
        });
    });
}

function openRecordPayment() {
    document.getElementById('payType').value = '';
    document.getElementById('payAmount').value = '';
    document.getElementById('payDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('payDescription').value = '';
    document.getElementById('payReference').value = '';
    document.getElementById('payMainAccount').value = '';
    document.getElementById('payExchangeRate').value = '1';
    document.getElementById('payConvertedAmount').value = '';
    document.getElementById('exchangeRateGroup').style.display = 'none';
    document.getElementById('convertedAmountGroup').style.display = 'none';
    document.getElementById('paySupplier').innerHTML = '<option value="">Loading...</option>';
    document.getElementById('payCustomer').innerHTML = '<option value="">Select supplier first</option>';
    document.getElementById('paymentOverlay').classList.add('open');

    if (selectedCustomerName) {
        let params = 'action=get_advances&customer_name=' + encodeURIComponent(selectedCustomerName);
        get(params).then(res => {
            if (res.success && res.advances && res.advances.length > 0) {
                var supplier = res.advances[0].supplier_name;
                var advCurrency = res.advances[0].currency;
                advanceCurrencyCache[selectedCustomerName] = advCurrency;
                loadPaymentSuppliers().then(function() {
                    document.getElementById('paySupplier').value = supplier;
                    onPaymentSupplierChange();
                    setTimeout(function() {
                        document.getElementById('payCustomer').value = selectedCustomerName;
                        onPaymentCustomerChange();
                    }, 200);
                });
            } else {
                loadPaymentSuppliers();
            }
        });
    } else {
        loadPaymentSuppliers();
    }
}

function onPaymentCustomerChange() {
    var custName = document.getElementById('payCustomer').value;
    if (!custName) {
        document.getElementById('exchangeRateGroup').style.display = 'none';
        document.getElementById('convertedAmountGroup').style.display = 'none';
        return;
    }
    var advCurrency = advanceCurrencyCache[custName] || 'USD';
    var payCurrency = document.getElementById('payCurrency').value;
    checkShowExchangeRate(advCurrency, payCurrency);
}

function checkShowExchangeRate(advCurrency, payCurrency) {
    var rateGroup = document.getElementById('exchangeRateGroup');
    var convGroup = document.getElementById('convertedAmountGroup');
    if (advCurrency !== payCurrency) {
        rateGroup.style.display = 'block';
        convGroup.style.display = 'block';
        document.getElementById('payExchangeRate').value = '';
        document.getElementById('exchangeRateHint').textContent = 'Enter rate: 1 ' + advCurrency + ' = ? ' + payCurrency;
        document.getElementById('payConvertedAmount').value = '';
    } else {
        rateGroup.style.display = 'none';
        convGroup.style.display = 'none';
        document.getElementById('payExchangeRate').value = '1';
        document.getElementById('payConvertedAmount').value = '';
    }
}

function fetchExchangeRate(fromCurrency, toCurrency) {
}

function updateConvertedAmount() {
    var amount = parseFloat(document.getElementById('payAmount').value) || 0;
    var rate = parseFloat(document.getElementById('payExchangeRate').value) || 0;
    var custName = document.getElementById('payCustomer').value;
    var advCurrency = advanceCurrencyCache[custName] || 'USD';
    var payCurrency = document.getElementById('payCurrency').value;
    var rateGroup = document.getElementById('exchangeRateGroup');
    if (rateGroup.style.display === 'none') {
        document.getElementById('payConvertedAmount').value = '';
        return;
    }
    if (amount > 0 && rate > 0) {
        var converted = amount * rate;
        document.getElementById('payConvertedAmount').value = converted.toFixed(2);
        document.getElementById('convertedAmountHint').textContent = amount + ' ' + payCurrency + ' = ' + converted.toFixed(2) + ' ' + advCurrency;
    } else {
        document.getElementById('payConvertedAmount').value = '';
    }
}

document.getElementById('payAmount').addEventListener('input', updateConvertedAmount);
document.getElementById('payExchangeRate').addEventListener('input', updateConvertedAmount);
document.getElementById('payCurrency').addEventListener('change', function() {
    var custName = document.getElementById('payCustomer').value;
    if (!custName) return;
    var advCurrency = advanceCurrencyCache[custName] || 'USD';
    checkShowExchangeRate(advCurrency, this.value);
});

// Advance form submit
document.getElementById('advanceForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var customerSelect = document.getElementById('advCustomerSelect');
    var customerInput = document.getElementById('advCustomerName');
    var supplierSelect = document.getElementById('advSupplierSelect');
    var supplierInput = document.getElementById('advSupplierName');
    var amount = document.getElementById('advAmount');
    var valid = true;

    var customerName = '';
    if (customerSelect.value === '__new__') {
        customerName = customerInput.value.trim();
        if (!customerName) { customerInput.style.borderColor = '#dc2626'; valid = false; }
    } else {
        customerName = customerSelect.value;
    }

    var supplierName = '';
    if (supplierSelect.value === '__new__') {
        supplierName = supplierInput.value.trim();
        if (!supplierName) { supplierInput.style.borderColor = '#dc2626'; valid = false; }
    } else {
        supplierName = supplierSelect.value;
    }

    [customerSelect, supplierSelect, amount].forEach(function(el) { el.style.borderColor = ''; });
    customerInput.style.borderColor = '';
    supplierInput.style.borderColor = '';
    if (!customerName) { customerSelect.style.borderColor = '#dc2626'; valid = false; }
    if (!supplierName) { supplierSelect.style.borderColor = '#dc2626'; valid = false; }
    if (!amount.value || parseFloat(amount.value) <= 0) { amount.style.borderColor = '#dc2626'; valid = false; }
    if (!valid) { alert('Please fill in all required fields'); return; }

    post({
        action: 'record_advance',
        customer_name: customerName,
        customer_phone: document.getElementById('advCustomerPhone').value.trim(),
        supplier_name: supplierName,
        amount: amount.value,
        currency: document.getElementById('advCurrency').value,
        advance_date: document.getElementById('advDate').value,
        reason: document.getElementById('advReason').value.trim()
    }).then(res => {
        if (!res.success) throw new Error(res.message);
        showAlert(res.message, 'success');
        closeAll();
        loadSummary();
        loadCustomers();
        if (selectedCustomerName) {
            loadAdvances();
            loadPayments();
        }
    }).catch(e => showAlert(e.message, 'danger'));
});

// Payment form submit
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var supplier = document.getElementById('paySupplier');
    var customer = document.getElementById('payCustomer');
    var type = document.getElementById('payType');
    var amount = document.getElementById('payAmount');
    var mainAccount = document.getElementById('payMainAccount');
    var valid = true;
    [supplier, customer, type, amount, mainAccount].forEach(function(el) { el.style.borderColor = ''; });
    if (!supplier.value) { supplier.style.borderColor = '#dc2626'; valid = false; }
    if (!customer.value) { customer.style.borderColor = '#dc2626'; valid = false; }
    if (!type.value) { type.style.borderColor = '#dc2626'; valid = false; }
    if (!amount.value || parseFloat(amount.value) <= 0) { amount.style.borderColor = '#dc2626'; valid = false; }
    if (!mainAccount.value) { mainAccount.style.borderColor = '#dc2626'; valid = false; }
    if (!valid) { alert('Please fill in all required fields'); return; }

    post({
        action: 'record_payment',
        customer_name: customer.value.trim(),
        supplier_name: supplier.value.trim(),
        type: type.value,
        amount: amount.value,
        currency: document.getElementById('payCurrency').value,
        exchange_rate: document.getElementById('payExchangeRate').value,
        main_account_id: mainAccount.value,
        payment_date: document.getElementById('payDate').value,
        reference_number: document.getElementById('payReference').value.trim(),
        description: document.getElementById('payDescription').value.trim()
    }).then(res => {
        if (!res.success) throw new Error(res.message);
        showAlert(res.message, 'success');
        closeAll();
        loadSummary();
        loadCustomers();
        if (selectedCustomerName) {
            loadAdvances();
            loadPayments();
            onPaymentCustomerChange();
        }
    }).catch(e => showAlert(e.message, 'danger'));
});

function markSupplierPaid(id) {
    if (!confirm('Mark this hawala as paid to supplier?')) return;
    post({ action: 'mark_supplier_paid', advance_id: id }).then(res => {
        if (!res.success) throw new Error(res.message);
        showAlert(res.message, 'success');
        loadSummary();
        loadCustomers();
        if (selectedCustomerName) {
            loadAdvances();
            onPaymentCustomerChange();
        }
    }).catch(e => showAlert(e.message, 'danger'));
}

function deleteAdvance(id) {
    if (!confirm('Delete this hawala? This cannot be undone.')) return;
    post({ action: 'delete_advance', advance_id: id }).then(res => {
        if (!res.success) throw new Error(res.message);
        showAlert(res.message, 'success');
        loadSummary();
        loadCustomers();
        if (selectedCustomerName) {
            loadAdvances();
            onPaymentCustomerChange();
        }
    }).catch(e => showAlert(e.message, 'danger'));
}

function deletePayment(id) {
    if (!confirm('Delete this payment? The balance will be reversed.')) return;
    post({ action: 'delete_payment', payment_id: id }).then(res => {
        if (!res.success) throw new Error(res.message);
        showAlert(res.message, 'success');
        loadSummary();
        loadCustomers();
        if (selectedCustomerName) {
            loadAdvances();
            loadPayments();
            onPaymentCustomerChange();
        }
    }).catch(e => showAlert(e.message, 'danger'));
}

function clearFilter() {
    document.getElementById('filterStartDate').value = '';
    document.getElementById('filterEndDate').value = '';
    if (selectedCustomerName) loadAdvances();
}

// Date Range Report Functions
function getReportDates() {
    var sd = document.getElementById('reportStartDate').value;
    var ed = document.getElementById('reportEndDate').value;
    return { start: sd, end: ed };
}

function setReportRange(range) {
    var today = new Date();
    var sd, ed;
    switch(range) {
        case 'today':
            sd = ed = today.toISOString().split('T')[0];
            break;
        case 'week':
            var day = today.getDay() || 7;
            sd = new Date(today); sd.setDate(today.getDate() - day + 1);
            ed = new Date(today);
            sd = sd.toISOString().split('T')[0]; ed = ed.toISOString().split('T')[0];
            break;
        case 'month':
            sd = new Date(today.getFullYear(), today.getMonth(), 1);
            ed = new Date(today);
            sd = sd.toISOString().split('T')[0]; ed = ed.toISOString().split('T')[0];
            break;
        case 'quarter':
            var qm = Math.floor(today.getMonth() / 3) * 3;
            sd = new Date(today.getFullYear(), qm, 1);
            ed = new Date(today);
            sd = sd.toISOString().split('T')[0]; ed = ed.toISOString().split('T')[0];
            break;
        case 'year':
            sd = new Date(today.getFullYear(), 0, 1);
            ed = new Date(today);
            sd = sd.toISOString().split('T')[0]; ed = ed.toISOString().split('T')[0];
            break;
    }
    document.getElementById('reportStartDate').value = sd;
    document.getElementById('reportEndDate').value = ed;
    applyReportFilter();
}

function applyReportFilter() {
    loadSummary();
    loadCustomers();
    if (selectedCustomerName) {
        loadAdvances();
        loadPayments();
    }
}

function resetReportFilter() {
    document.getElementById('reportStartDate').value = '';
    document.getElementById('reportEndDate').value = '';
    applyReportFilter();
}

function exportPrint() {
    var sd = document.getElementById('reportStartDate').value;
    var ed = document.getElementById('reportEndDate').value;
    var url = '../api/customer_advance/export_customer_advances.php?action=print';
    if (sd) url += '&start_date=' + encodeURIComponent(sd);
    if (ed) url += '&end_date=' + encodeURIComponent(ed);
    window.open(url, '_blank');
}

function exportExcel() {
    var sd = document.getElementById('reportStartDate').value;
    var ed = document.getElementById('reportEndDate').value;
    var url = '../api/customer_advance/export_customer_advances.php?action=excel';
    if (sd) url += '&start_date=' + encodeURIComponent(sd);
    if (ed) url += '&end_date=' + encodeURIComponent(ed);
    window.location.href = url;
}

document.querySelectorAll('.ag-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) closeAll(); });
});

loadSummary();
loadCustomers();
</script>
<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
