<?php
session_start();

require_once '../config.php';
require_once 'security.php';
enforce_auth();

$role = $_SESSION['role'] ?? '';
require_permission('finance.cash_settlement');

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
$current_user = (int) ($_SESSION['user_id'] ?? 0);
$csrf_token = $_SESSION['csrf_token'] ?? '';
$canApprove = user_can('finance.cash_settlement_approve');
include '../includes/header.php';
?>
<style>
    :root {
        --cs-surface:   #ffffff;
        --cs-muted:     #f0f2f5;
        --cs-border:    rgba(0,0,0,0.08);
        --cs-border-md: rgba(0,0,0,0.13);
        --cs-text:      #0f172a;
        --cs-text-sub:  #64748b;
        --cs-text-hint: #94a3b8;
        --cs-blue:      #2563eb;
        --cs-blue-bg:   #eff6ff;
        --cs-blue-tx:   #1d4ed8;
        --cs-green:     #16a34a;
        --cs-green-bg:  #f0fdf4;
        --cs-green-tx:  #15803d;
        --cs-red:       #dc2626;
        --cs-red-bg:    #fef2f2;
        --cs-red-tx:    #b91c1c;
        --cs-amber:     #d97706;
        --cs-amber-bg:  #fffbeb;
        --cs-amber-tx:  #b45309;
        --cs-r-sm:      6px;
        --cs-r-md:      8px;
        --cs-r-lg:      12px;
        --cs-sh:        0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    }
    .cs-wrap { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; color: var(--cs-text); font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
    .cs-wrap *, .cs-wrap *::before, .cs-wrap *::after { box-sizing: border-box; }
    .cs-page { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

    .cs-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px; }
    .cs-topbar-title { font-size: 18px; font-weight: 600; color: var(--cs-text); }
    .cs-topbar-sub { font-size: 13px; color: var(--cs-text-sub); margin-top: 1px; }
    .cs-role-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; background: var(--cs-blue-bg); color: var(--cs-blue-tx); border: 1px solid #bfdbfe; letter-spacing: 0.2px; }

    .cs-alert-bar { display: none; padding: 10px 14px; border-radius: var(--cs-r-md); font-size: 13px; font-weight: 500; margin-bottom: 1.25rem; border-left: 3px solid transparent; }
    .cs-alert-bar.show { display: block; }
    .cs-alert-bar.success { background: var(--cs-green-bg); color: var(--cs-green-tx); border-left-color: var(--cs-green); }
    .cs-alert-bar.danger  { background: var(--cs-red-bg);   color: var(--cs-red-tx);   border-left-color: var(--cs-red); }

    .cs-section-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--cs-text-sub); margin: 1.5rem 0 0.75rem; }

    .cs-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 0.5rem; }
    .cs-card { background: var(--cs-surface); border-radius: var(--cs-r-lg); border: 1px solid var(--cs-border); padding: 0.9rem 1rem; box-shadow: var(--cs-sh); position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 6px; }
    .cs-card::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 3px; border-radius: var(--cs-r-lg) 0 0 var(--cs-r-lg); }
    .cs-card.cs-blue::before { background: var(--cs-blue); } .cs-card.cs-green::before { background: var(--cs-green); }
    .cs-card.cs-amber::before { background: var(--cs-amber); } .cs-card.cs-red::before { background: var(--cs-red); }
    .cs-card-value { font-size: 20px; font-weight: 700; line-height: 1.1; }
    .cs-card-label { font-size: 11px; font-weight: 600; color: var(--cs-text-sub); text-transform: uppercase; letter-spacing: 0.3px; }
    .cs-card-sub { font-size: 12px; color: var(--cs-text-sub); }
    .cs-card .cs-btn { margin-top: 6px; }
    .cs-card-actions { display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
    .cs-card-actions .cs-btn { margin-top: 0; }

    .cs-block { background: var(--cs-surface); border: 1px solid var(--cs-border); border-radius: var(--cs-r-lg); padding: 1.25rem; margin-bottom: 1.25rem; box-shadow: var(--cs-sh); }
    .cs-block-title { font-size: 15px; font-weight: 700; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 8px; }

    .cs-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 8px 15px; border-radius: var(--cs-r-md); border: 1px solid var(--cs-border-md); background: var(--cs-surface); color: var(--cs-text); cursor: pointer; transition: background 0.15s, box-shadow 0.15s; white-space: nowrap; line-height: 1; box-shadow: var(--cs-sh); }
    .cs-btn:hover { background: var(--cs-muted); }
    .cs-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .cs-btn-green { background: var(--cs-green); color: #fff; border-color: var(--cs-green); } .cs-btn-green:hover { background: #15803d; }
    .cs-btn-red   { background: var(--cs-red);   color: #fff; border-color: var(--cs-red);   } .cs-btn-red:hover   { background: #b91c1c; }
    .cs-btn-blue  { background: var(--cs-blue);  color: #fff; border-color: var(--cs-blue);  } .cs-btn-blue:hover  { background: #1d4ed8; }
    .cs-btn-ghost { background: transparent; color: var(--cs-text-sub); border-color: var(--cs-border); }
    .cs-btn-sm { padding: 6px 10px; font-size: 12px; }

    .cs-table-wrap { background: var(--cs-surface); border: 1px solid var(--cs-border); border-radius: var(--cs-r-lg); box-shadow: var(--cs-sh); overflow: hidden; }
    .cs-t-head, .cs-t-row { display: grid; grid-template-columns: 110px 120px 110px 1fr 110px 210px; gap: 12px; padding: 10px 16px; align-items: center; }
    .cs-t-head { background: var(--cs-muted); border-bottom: 1px solid var(--cs-border); }
    .cs-t-head span { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; color: var(--cs-text-sub); }
    .cs-t-row { border-bottom: 1px solid var(--cs-border); transition: background 0.1s; }
    .cs-t-row:last-child { border-bottom: none; }
    .cs-t-row:hover { background: var(--cs-muted); }
    .cs-t-dim { font-size: 12.5px; color: var(--cs-text-sub); }
    .cs-t-note { font-size: 12.5px; color: var(--cs-text-sub); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .cs-pill { display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; letter-spacing: 0.2px; text-transform: capitalize; }
    .cs-pill.cs-pending   { background: var(--cs-amber-bg); color: var(--cs-amber-tx); }
    .cs-pill.cs-confirmed { background: var(--cs-green-bg); color: var(--cs-green-tx); }
    .cs-pill.cs-rejected  { background: var(--cs-red-bg);   color: var(--cs-red-tx); }

    .cs-empty-state { padding: 3rem 1rem; text-align: center; font-size: 13px; color: var(--cs-text-hint); }

    .cs-overlay { display: none !important; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15,23,42,0.45); backdrop-filter: blur(2px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto; }
    .cs-overlay.open { display: flex !important; }
    .cs-modal { background: var(--cs-surface); border: 1px solid var(--cs-border); border-radius: var(--cs-r-lg); padding: 1.5rem; width: 440px; max-width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,0.18); animation: csModalIn 0.2s ease-out; position: relative; z-index: 100000; margin: auto; flex-shrink: 0; }
    .cs-modal-lg { width: 760px; }
    .cs-tx-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .cs-tx-table th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; color: var(--cs-text-sub); padding: 8px 10px; border-bottom: 1px solid var(--cs-border); background: var(--cs-muted); }
    .cs-tx-table td { padding: 8px 10px; border-bottom: 1px solid var(--cs-border); vertical-align: top; }
    .cs-tx-table tr:last-child td { border-bottom: none; }
    .cs-tx-pos { color: var(--cs-green); font-weight: 600; }
    .cs-tx-neg { color: var(--cs-red); font-weight: 600; }
    .cs-tx-section { margin-bottom: 1rem; }
    .cs-tx-section-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--cs-text-sub); margin-bottom: 0.5rem; }
    @keyframes csModalIn { from { opacity: 0; transform: translateY(10px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .cs-modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--cs-border); }
    .cs-modal-head h2 { font-size: 15px; font-weight: 600; margin: 0; }
    .cs-modal-close { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: var(--cs-r-sm); background: none; border: none; cursor: pointer; font-size: 18px; line-height: 1; color: var(--cs-text-sub); }
    .cs-modal-close:hover { background: var(--cs-muted); }
    .cs-field { margin-bottom: 1rem; }
    .cs-field label { display: block; font-size: 12px; font-weight: 600; color: var(--cs-text-sub); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.3px; }
    .cs-field input, .cs-field select, .cs-field textarea { width: 100%; padding: 8px 10px; font-size: 13px; border: 1px solid var(--cs-border-md); border-radius: var(--cs-r-md); background: var(--cs-surface); color: var(--cs-text); font-family: inherit; }
    .cs-field input:focus, .cs-field select:focus, .cs-field textarea:focus { outline: none; border-color: var(--cs-blue); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
    .cs-field textarea { resize: vertical; }
    .cs-modal-footer { display: flex; gap: 8px; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--cs-border); }
    .cs-modal-footer .cs-btn { flex: 1; justify-content: center; }
    .cs-warn { background: var(--cs-red-bg); color: var(--cs-red-tx); font-size: 12.5px; padding: 8px 10px; border-radius: var(--cs-r-sm); margin-bottom: 0.5rem; }
</style>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="main-body">
      <div class="page-wrapper">
        <div class="cs-wrap">
<div class="cs-page">

    <div class="cs-topbar">
        <div>
            <div class="cs-topbar-title"><?php echo $canApprove ? 'Cash Settlements' : 'Cash Settlement'; ?></div>
            <div class="cs-topbar-sub">Finance hands over collected cash to admin; admin approval reduces the counter.</div>
        </div>
        <span class="cs-role-badge"><?php echo ucfirst(htmlspecialchars($role)); ?></span>
    </div>

    <div class="cs-alert-bar" id="alertBar"></div>

    <?php if (!$canApprove): ?>
        <!-- ── Finance view ── -->
        <div class="cs-section-label">Available balance by currency (auto)</div>
        <div class="cs-cards" id="summaryCards"></div>

        <div class="cs-section-label">My settlement history</div>
        <div class="cs-table-wrap">
            <div class="cs-t-head">
                <span>Date</span><span>Currency</span><span>Amount</span><span>Note</span><span>Status</span><span>Confirmed By</span>
            </div>
            <div id="financeList"><div class="cs-empty-state">Loading…</div></div>
        </div>
    <?php else: ?>
        <!-- ── Admin view ── -->
        <div class="cs-section-label">My collected cash (auto) — available to settle</div>
        <div class="cs-cards" id="adminSummaryCards"></div>

        <div class="cs-block">
            <div class="cs-block-title">Pending settlements</div>
            <div class="cs-table-wrap">
                <div class="cs-t-head">
                    <span>Date</span><span>User</span><span>Currency</span><span>Amount</span><span>Note</span><span>Actions</span>
                </div>
                <div id="pendingList"><div class="cs-empty-state">Loading…</div></div>
            </div>
        </div>

        <div class="cs-section-label">All settlement history</div>
        <div class="cs-table-wrap">
            <div class="cs-t-head">
                <span>Date</span><span>User</span><span>Currency</span><span>Amount</span><span>Note</span><span>Status</span>
            </div>
            <div id="adminList"><div class="cs-empty-state">Loading…</div></div>
        </div>
    <?php endif; ?>

</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Transactions breakdown modal -->
<div class="cs-overlay" id="txOverlay">
    <div class="cs-modal cs-modal-lg">
        <div class="cs-modal-head">
            <h2 id="txModalTitle">Transactions</h2>
            <button class="cs-modal-close" data-close="tx">&times;</button>
        </div>
        <div id="txContent"><div class="cs-empty-state">Loading…</div></div>
    </div>
</div>

<!-- Submit modal -->
<div class="cs-overlay" id="submitOverlay">
    <div class="cs-modal">
        <div class="cs-modal-head">
            <h2 id="submitModalTitle">Submit to Admin</h2>
            <button class="cs-modal-close" data-close="submit">&times;</button>
        </div>
        <form id="submitForm">
            <div class="cs-field">
                <label>Currency</label>
                <select id="submitCurrency"></select>
            </div>
            <div class="cs-field">
                <label>Amount</label>
                <input type="number" id="submitAmount" step="0.01" min="0" required>
            </div>
            <div class="cs-field">
                <label>Note</label>
                <textarea id="submitNote" rows="3" placeholder="Optional note"></textarea>
            </div>
            <div class="cs-warn" id="submitWarn" style="display:none;"></div>
            <div class="cs-modal-footer">
                <button type="button" class="cs-btn" data-close="submit">Cancel</button>
                <button type="submit" class="cs-btn cs-btn-green">Submit for Approval</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject modal -->
<div class="cs-overlay" id="rejectOverlay">
    <div class="cs-modal">
        <div class="cs-modal-head">
            <h2>Reject Settlement</h2>
            <button class="cs-modal-close" data-close="reject">&times;</button>
        </div>
        <form id="rejectForm">
            <div class="cs-field">
                <label>Reason <span style="text-transform:none;">(required)</span></label>
                <textarea id="rejectReason" rows="3" required></textarea>
            </div>
            <div class="cs-modal-footer">
                <button type="button" class="cs-btn" data-close="reject">Cancel</button>
                <button type="submit" class="cs-btn cs-btn-red">Reject</button>
            </div>
        </form>
    </div>
</div>

<!-- Sign & confirm modal -->
<div class="cs-overlay" id="sigOverlay">
    <div class="cs-modal cs-modal-lg">
        <div class="cs-modal-head">
            <h2>Confirm &amp; Sign</h2>
            <button class="cs-modal-close" data-close="sig">&times;</button>
        </div>
        <div id="sigSummary" class="cs-tx-section"></div>
        <div class="cs-warn">Confirming reduces the finance counter by this amount. Your signature below is the official proof of cash handover and will appear on the settlement receipt.</div>
        <div class="cs-field">
            <label>Admin Signature</label>
            <div style="border:1px solid var(--cs-border-md); border-radius: var(--cs-r-md); overflow:hidden; background:#fff;">
                <canvas id="sigCanvas" width="700" height="180" style="display:block; width:100%; cursor:crosshair; touch-action:none;"></canvas>
            </div>
            <div style="display:flex; gap:8px; margin-top:8px;">
                <button type="button" class="cs-btn cs-btn-sm" id="sigClearBtn">Clear</button>
            </div>
        </div>
        <div class="cs-modal-footer">
            <button type="button" class="cs-btn" data-close="sig">Cancel</button>
            <button type="button" class="cs-btn cs-btn-green" id="sigConfirmBtn">Confirm &amp; Sign</button>
        </div>
    </div>
</div>

<!-- Income items breakdown modal -->
<div class="cs-overlay" id="itemsOverlay">
    <div class="cs-modal cs-modal-lg">
        <div class="cs-modal-head">
            <h2 id="itemsModalTitle">Income Items</h2>
            <button class="cs-modal-close" data-close="items">&times;</button>
        </div>
        <div id="itemsNote" class="cs-t-dim" style="margin-bottom:0.75rem;"></div>
        <div id="itemsBody"></div>
        <div class="cs-modal-footer" style="justify-content:flex-end; border-top:none; padding-top:0;">
            <button type="button" class="cs-btn" data-close="items" style="flex:0 0 auto;">Close</button>
        </div>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
const CSRF = <?php echo json_encode($csrf_token); ?>;
const CAN_APPROVE = <?php echo $canApprove ? 'true' : 'false'; ?>;
const CURRENT_USER = <?php echo (int) $current_user; ?>;

const qs  = (s, c) => (c || document).querySelector(s);
const qsa = (s, c) => Array.from((c || document).querySelectorAll(s));

function esc(s) { const d = document.createElement('div'); d.textContent = (s === null || s === undefined) ? '' : String(s); return d.innerHTML; }
function money(n) { return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function dateTime(s) { if (!s) return '—'; return new Date(s).toLocaleString(); }

function showAlert(msg, type) {
    const bar = qs('#alertBar');
    bar.textContent = msg;
    bar.className = 'cs-alert-bar show ' + type;
    setTimeout(() => { bar.className = 'cs-alert-bar'; }, 5000);
}

function post(action, data, onOk) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', CSRF);
    Object.keys(data || {}).forEach(k => {
        if (data[k] !== null && data[k] !== undefined) fd.append(k, data[k]);
    });
    fetch('../api/finance/cash_settlements.php', { method: 'POST', credentials: 'include', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message || 'Request failed');
            showAlert(res.message || 'Done', 'success');
            onOk && onOk(res);
        })
        .catch(err => showAlert(err.message, 'danger'));
}

function getSummary() {
    return fetch('../api/finance/cash_settlements.php?action=summary', { credentials: 'include' })
        .then(r => r.json())
        .then(res => { if (!res.success) throw new Error(res.message); return res; });
}

function statusPill(s) {
    return '<span class="cs-pill cs-' + esc(s) + '">' + esc(s) + '</span>';
}
const renderPill = statusPill;

/* ── Finance view ── */
function renderFinanceSummary(currencies, wrapId) {
    const wrap = qs(wrapId || '#summaryCards');
    if (!wrap) return;
    const order = ['USD','AFS','EUR','DARHAM','SAR'];
    const items = order.filter(c => currencies[c]).map(c => {
        const d = currencies[c];
        return `
            <div class="cs-card cs-blue">
                <div class="cs-card-label">${esc(c)} · Remaining</div>
                <div class="cs-card-value">${money(d.remaining)}</div>
                <div class="cs-card-sub">Credit ${money(d.credit)} / Debit ${money(d.debit)}</div>
                <div class="cs-card-sub">Handed over: ${money(d.confirmed)} · Pending: ${money(d.pending)}</div>
                <div class="cs-card-actions">
                    <button class="cs-btn cs-btn-sm" onclick="openTransactions('${esc(c)}')">View transactions</button>
                    <button class="cs-btn cs-btn-green cs-btn-sm" onclick="openSubmit('${esc(c)}', ${Number(d.available).toFixed(2)}, ${Number(d.remaining).toFixed(2)})">${CAN_APPROVE ? 'Settle my cash' : 'Submit to Admin'}</button>
                </div>
            </div>`;
    }).join('');
    wrap.innerHTML = items || '<div class="cs-card"><div class="cs-card-sub">No data</div></div>';
}

function loadFinance() {
    getSummary().then(res => renderFinanceSummary(res.currencies)).catch(e => showAlert(e.message, 'danger'));
    fetch('../api/finance/cash_settlements.php?action=list', { credentials: 'include' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            renderFinanceList(res.settlements);
        })
        .catch(e => showAlert(e.message, 'danger'));
}

function renderFinanceList(list) {
    const wrap = qs('#financeList');
    if (!list.length) { wrap.innerHTML = '<div class="cs-empty-state">No settlements yet.</div>'; return; }
    wrap.innerHTML = list.map(s => `
        <div class="cs-t-row">
            <span class="cs-t-dim">${dateTime(s.created_at)}</span>
            <span>${esc(s.currency)}</span>
            <span style="font-weight:600;">${money(s.amount)}</span>
            <span class="cs-t-note" title="${esc(s.request_note)}">${esc(s.request_note) || '—'}</span>
            <span>${renderPill(s.status)}</span>
            <span class="cs-t-dim">${esc(s.confirmed_name) || '—'}
                <button class="cs-btn cs-btn-sm" style="margin-left:8px;" onclick="viewItems(${s.id})">Items</button>
                ${s.status === 'pending' ? `<button class="cs-btn cs-btn-red cs-btn-sm" style="margin-left:8px;" onclick="deleteSettlement(${s.id})">Delete</button>` : ''}
                ${s.status === 'confirmed' ? `<button class="cs-btn cs-btn-sm" style="margin-left:8px;" onclick="printSettlement(${s.id})">Print</button>` : ''}
            </span>
        </div>`).join('');
}

function deleteSettlement(id) {
    if (!confirm('Delete this pending submission? The amount will be added back to your available balance.')) return;
    post('delete', { id }, () => loadFinance());
}

/* ── Admin view ── */
function loadAdmin() {
    getSummary().then(res => renderFinanceSummary(res.currencies, '#adminSummaryCards')).catch(e => showAlert(e.message, 'danger'));
    fetch('../api/finance/cash_settlements.php?action=list&status=pending', { credentials: 'include' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            renderPending(res.settlements);
        })
        .catch(e => showAlert(e.message, 'danger'));

    fetch('../api/finance/cash_settlements.php?action=list', { credentials: 'include' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            renderAdminList(res.settlements);
        })
        .catch(e => showAlert(e.message, 'danger'));
}

function renderPending(list) {
    const wrap = qs('#pendingList');
    if (!list.length) { wrap.innerHTML = '<div class="cs-empty-state">No pending settlements.</div>'; return; }
    list.forEach(s => { pendingRows[s.id] = s; });
    wrap.innerHTML = list.map(s => `
        <div class="cs-t-row">
            <span class="cs-t-dim">${dateTime(s.created_at)}</span>
            <span>${esc(s.user_name)}</span>
            <span>${esc(s.currency)}</span>
            <span style="font-weight:600;">${money(s.amount)}</span>
            <span class="cs-t-note" title="${esc(s.request_note)}">${esc(s.request_note) || '—'}</span>
            <span>
                <button class="cs-btn cs-btn-green cs-btn-sm" onclick="openConfirm(${s.id})">Confirm &amp; Sign</button>
                <button class="cs-btn cs-btn-red cs-btn-sm" onclick="openReject(${s.id})">Reject</button>
                <button class="cs-btn cs-btn-sm" onclick="viewItems(${s.id})" title="View income items">Items</button>
            </span>
        </div>`).join('');
}

function renderAdminList(list) {
    const wrap = qs('#adminList');
    if (!list.length) { wrap.innerHTML = '<div class="cs-empty-state">No settlements.</div>'; return; }
    wrap.innerHTML = list.map(s => `
        <div class="cs-t-row">
            <span class="cs-t-dim">${dateTime(s.created_at)}</span>
            <span>${esc(s.user_name)}</span>
            <span>${esc(s.currency)}</span>
            <span style="font-weight:600;">${money(s.amount)}</span>
            <span class="cs-t-note" title="${esc(s.request_note)}">${esc(s.request_note) || '—'}</span>
            <span>${renderPill(s.status)}
                ${s.status === 'rejected' && s.reject_reason ? '<div class="cs-t-dim">' + esc(s.reject_reason) + '</div>' : ''}
                <div style="margin-top:4px; display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                    <button class="cs-btn cs-btn-sm" onclick="viewItems(${s.id})" title="View income items">Items</button>
                    ${s.status === 'confirmed' ? `<button class="cs-btn cs-btn-sm" onclick="printSettlement(${s.id})">Print receipt</button>` : ''}
                </div>
            </span>
        </div>`).join('');
}

function confirmSettlement(id) {
    if (!confirm('Confirm this settlement? The finance counter will be reduced by this amount.')) return;
    post('confirm', { id }, () => loadAdmin());
}

/* ── Transactions breakdown ── */
function openTransactions(currency) {
    qs('#txModalTitle').textContent = currency + ' — Transactions';
    qs('#txContent').innerHTML = '<div class="cs-empty-state">Loading…</div>';
    openModal('txOverlay');
    fetch('../api/finance/cash_settlements.php?action=transactions&currency=' + encodeURIComponent(currency), { credentials: 'include' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            renderTransactions(res);
        })
        .catch(e => { qs('#txContent').innerHTML = '<div class="cs-empty-state">' + esc(e.message) + '</div>'; });
}

function renderTransactions(res) {
    const tx = res.transactions || [];
    const st = res.settlements || [];
    if (!tx.length && !st.length) {
        qs('#txContent').innerHTML = '<div class="cs-empty-state">No transactions for this currency yet.</div>';
        return;
    }

    let html = '';
    if (tx.length) {
        html += '<div class="cs-tx-section"><div class="cs-tx-section-title">Ledger transactions</div>'
            + '<table class="cs-tx-table"><thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Module</th><th>Amount</th></tr></thead><tbody>';
        tx.forEach(t => {
            const sign = t.type === 'credit' ? '+' : '−';
            const cls = t.type === 'credit' ? 'cs-tx-pos' : 'cs-tx-neg';
            const mod = (t.transaction_of || '').replace(/_/g, ' ');
            html += '<tr>'
                + '<td class="cs-t-dim">' + dateTime(t.created_at) + '</td>'
                + '<td>' + esc(t.type) + '</td>'
                + '<td class="cs-t-note" style="max-width:260px;" title="' + esc(t.description) + '">' + (esc(t.description) || '—') + '</td>'
                + '<td class="cs-t-dim">' + esc(mod) + '</td>'
                + '<td class="' + cls + '">' + sign + money(t.amount) + '</td>'
                + '</tr>';
        });
        html += '</tbody></table></div>';
    }
    if (st.length) {
        html += '<div class="cs-tx-section"><div class="cs-tx-section-title">Settlement history</div>'
            + '<table class="cs-tx-table"><thead><tr><th>Date</th><th>Amount</th><th>Status</th><th>Note</th></tr></thead><tbody>';
        st.forEach(s => {
            html += '<tr>'
                + '<td class="cs-t-dim">' + dateTime(s.created_at) + '</td>'
                + '<td class="cs-tx-neg">−' + money(s.amount) + '</td>'
                + '<td>' + renderPill(s.status) + '</td>'
                + '<td class="cs-t-note" style="max-width:240px;" title="' + esc(s.request_note) + '">' + (esc(s.request_note) || '—') + '</td>'
                + '</tr>';
        });
        html += '</tbody></table></div>';
    }
    qs('#txContent').innerHTML = html;
}

/* ── Submit modal ── */
let currentAvailable = 0;
function openSubmit(currency, available, remaining) {
    currentAvailable = available;
    qs('#submitCurrency').value = currency;
    qs('#submitAmount').value = available > 0 ? available : '';
    qs('#submitNote').value = '';
    qs('#submitWarn').style.display = 'none';
    qs('#submitModalTitle').textContent = (CAN_APPROVE ? 'Settle ' : 'Submit ') + currency + (CAN_APPROVE ? ' (my collected cash)' : ' to Admin');
    fillCurrencies(currency);
    openModal('submitOverlay');
}
function fillCurrencies(selected) {
    const sel = qs('#submitCurrency');
    sel.innerHTML = '';
    ['USD','AFS','EUR','DARHAM','SAR'].forEach(c => {
        const o = document.createElement('option');
        o.value = c; o.textContent = c;
        sel.appendChild(o);
    });
    sel.value = selected;
}
qs('#submitAmount').addEventListener('input', () => {
    const v = parseFloat(qs('#submitAmount').value || 0);
    const warn = qs('#submitWarn');
    if (v > currentAvailable) {
        warn.style.display = 'block';
        warn.textContent = 'Exceeds available (' + money(currentAvailable) + ').';
    } else {
        warn.style.display = 'none';
    }
});
qs('#submitForm').addEventListener('submit', e => {
    e.preventDefault();
    const currency = qs('#submitCurrency').value;
    const amount = parseFloat(qs('#submitAmount').value);
    const note = qs('#submitNote').value.trim();
    if (!amount || amount <= 0) { showAlert('Enter a valid amount', 'danger'); return; }
    if (amount > currentAvailable) { showAlert('Amount exceeds available balance', 'danger'); return; }
    post('create', { currency, amount, note }, () => { closeAll(); CAN_APPROVE ? loadAdmin() : loadFinance(); });
});

/* ── Reject modal ── */
let rejectingId = 0;
function openReject(id) { rejectingId = id; qs('#rejectReason').value = ''; openModal('rejectOverlay'); }
qs('#rejectForm').addEventListener('submit', e => {
    e.preventDefault();
    const reason = qs('#rejectReason').value.trim();
    if (!reason) { showAlert('Reason is required', 'danger'); return; }
    post('reject', { id: rejectingId, reason }, () => { closeAll(); loadAdmin(); });
});

/* ── Overlay helpers ── */
function openModal(id) { qs('#' + id).classList.add('open'); }
function closeAll() { qsa('.cs-overlay').forEach(o => o.classList.remove('open')); }
qsa('[data-close]').forEach(b => b.addEventListener('click', closeAll));
qsa('.cs-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) closeAll(); }));

/* ── Sign & confirm ── */
const pendingRows = {};
let sigConfirmingId = 0;

function openConfirm(id) {
    const s = pendingRows[id];
    if (!s) { showAlert('Settlement data not found', 'danger'); return; }
    sigConfirmingId = id;
    qs('#sigSummary').innerHTML =
        '<div class="cs-tx-section-title">Handover summary</div>'
        + '<table class="cs-tx-table"><tbody>'
        + '<tr><td class="cs-t-dim">Finance user</td><td>' + esc(s.user_name) + '</td></tr>'
        + '<tr><td class="cs-t-dim">Currency</td><td>' + esc(s.currency) + '</td></tr>'
        + '<tr><td class="cs-t-dim">Amount</td><td style="font-weight:700;">' + money(s.amount) + '</td></tr>'
        + '<tr><td class="cs-t-dim">Note</td><td>' + (esc(s.request_note) || '—') + '</td></tr>'
        + '<tr><td class="cs-t-dim">Submitted</td><td>' + dateTime(s.created_at) + '</td></tr>'
        + '</tbody></table>';
    resetSigPad();
    openModal('sigOverlay');
}

const sigCanvas = qs('#sigCanvas');
const sigCtx = sigCanvas.getContext('2d');
let sigDrawing = false;
let sigLast = null;

function sigPos(e) {
    const rect = sigCanvas.getBoundingClientRect();
    const sx = sigCanvas.width / rect.width;
    const sy = sigCanvas.height / rect.height;
    const c = e.touches ? e.touches[0] : e;
    return { x: (c.clientX - rect.left) * sx, y: (c.clientY - rect.top) * sy };
}
function sigDown(e) {
    e.preventDefault();
    sigDrawing = true;
    const p = sigPos(e);
    sigLast = p;
    sigCtx.beginPath();
    sigCtx.moveTo(p.x, p.y);
}
function sigMove(e) {
    if (!sigDrawing) return;
    e.preventDefault();
    const p = sigPos(e);
    sigCtx.lineWidth = 2.5;
    sigCtx.lineCap = 'round';
    sigCtx.lineJoin = 'round';
    sigCtx.strokeStyle = '#0f172a';
    sigCtx.lineTo(p.x, p.y);
    sigCtx.stroke();
    sigLast = p;
}
function sigUp() { sigDrawing = false; }
function resetSigPad() {
    sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
    sigDrawing = false;
    sigLast = null;
}
function sigIsEmpty() {
    const d = sigCtx.getImageData(0, 0, sigCanvas.width, sigCanvas.height).data;
    for (let i = 3; i < d.length; i += 4) {
        if (d[i] > 40 && (d[i - 3] < 150 || d[i - 2] < 150 || d[i - 1] < 150)) return false;
    }
    return true;
}
sigCanvas.addEventListener('mousedown', sigDown);
sigCanvas.addEventListener('mousemove', sigMove);
window.addEventListener('mouseup', sigUp);
sigCanvas.addEventListener('touchstart', sigDown, { passive: false });
sigCanvas.addEventListener('touchmove', sigMove, { passive: false });
sigCanvas.addEventListener('touchend', sigUp);
qs('#sigClearBtn').addEventListener('click', resetSigPad);

qs('#sigConfirmBtn').addEventListener('click', () => {
    if (sigIsEmpty()) { showAlert('Please draw your signature before confirming', 'danger'); return; }
    const id = sigConfirmingId;
    const signature = sigCanvas.toDataURL('image/png');
    closeAll();
    const fd = new FormData();
    fd.append('action', 'confirm');
    fd.append('csrf_token', CSRF);
    fd.append('id', id);
    fd.append('signature', signature);
    fetch('../api/finance/cash_settlements.php', { method: 'POST', credentials: 'include', body: fd })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            showAlert(res.message, 'success');
            loadAdmin();
        })
        .catch(err => showAlert(err.message, 'danger'));
});

/* ── Print receipt ── */
function printSettlement(id) {
    window.open('../api/finance/print_cash_settlement_receipt.php?id=' + id, '_blank');
}

/* ── Income items breakdown ── */
function viewItems(id) {
    openModal('itemsOverlay');
    qs('#itemsModalTitle').textContent = 'Income Items';
    qs('#itemsNote').textContent = 'Loading…';
    qs('#itemsBody').innerHTML = '';
    fetch('../api/finance/cash_settlements.php?action=breakdown&id=' + id, { credentials: 'include' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            renderItemsModal(res);
        })
        .catch(e => { qs('#itemsNote').textContent = e.message; });
}

function renderItemsModal(res) {
    const st = res.settlement;
    qs('#itemsModalTitle').textContent = 'Settlement #' + st.id;
    qs('#itemsNote').textContent = res.note || '';
    const body = qs('#itemsBody');
    if (!res.items.length) {
        body.innerHTML = '<div class="cs-empty-state">No items found.</div>';
        return;
    }

    const thead = '<thead><tr><th>#</th><th>Date</th><th>Item / Description</th><th>Source</th><th>Ref</th><th style="text-align:right;">Amount</th></tr></thead>';
    const rowHtml = (it, idx) => `
        <tr>
            <td class="cs-t-dim">${idx + 1}</td>
            <td class="cs-t-dim">${dateTime(it.created_at)}</td>
            <td class="cs-t-note" style="max-width:240px;" title="${esc(it.description)}">${esc(it.description) || '—'}
                ${it.partial ? '<span class="cs-pill cs-pending">partial</span>' : ''}
            </td>
            <td class="cs-t-dim">${esc(it.source)}</td>
            <td class="cs-t-dim">${esc(it.reference_id)}</td>
            <td style="font-weight:600; text-align:right;">${money(it.covered)}</td>
        </tr>`;

    const detailRow = (label, value) =>
        '<tr><td class="cs-t-dim">' + label + '</td><td style="font-weight:600; text-align:right;">' + value + '</td></tr>';

    const details =
        '<div style="flex:0 0 215px; min-width:195px;">'
        + '<table class="cs-tx-table"><tbody>'
        + detailRow('Finance', esc(st.user_name))
        + detailRow('Currency', esc(st.currency))
        + detailRow('Amount', money(st.amount))
        + detailRow('Status', renderPill(st.status))
        + detailRow('Submitted', dateTime(st.created_at))
        + (st.status === 'confirmed'
            ? detailRow('Confirmed By', esc(st.confirmed_name) || '—')
                + detailRow('Confirmed At', dateTime(st.confirmed_at))
            : '')
        + (st.request_note ? detailRow('Note', esc(st.request_note)) : '')
        + '</tbody></table></div>';

    body.innerHTML =
        '<div style="display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap;">'
        + details
        + '<div style="flex:1 1 330px; min-width:0;">'
        + '<div style="overflow-x:auto; max-width:100%;">'
        + '<table class="cs-tx-table">' + thead + '<tbody>' + res.items.map(rowHtml).join('') + '</tbody></table>'
        + '</div>'
        + '<table class="cs-tx-table" style="margin-top:12px;"><tbody>'
        + '<tr style="background:var(--cs-muted);">'
        + '<td style="font-weight:700;">Total handed over</td>'
        + '<td style="font-weight:700; text-align:right;">' + money(res.total) + '</td>'
        + '</tr></tbody></table>'
        + '</div></div>';
}

/* ── Init ── */
(function() {
    if (CAN_APPROVE) loadAdmin();
    else loadFinance();
})();
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
