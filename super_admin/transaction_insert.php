<?php
require_once 'security.php';
require_once '../includes/db.php';

enforce_auth();

include '../includes/header_super_admin.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
  --ink: #0D0F12; --ink-2: #3A3F4A; --ink-3: #6C737F; --ink-4: #9CA3AF;
  --line: #E8EAED; --surface: #FFFFFF; --surface-2: #F8F9FA;
  --green: #059669; --rose: #E11D48; --radius-lg: 16px;
  --shadow-md: 0 2px 8px rgba(0,0,0,.06), 0 8px 24px rgba(0,0,0,.06);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--surface-2); font-family: 'Sora', sans-serif; color: var(--ink); }
.shell { max-width: 720px; margin: 0 auto; padding: 32px 24px; }
.page-title { font-size: 1.4rem; font-weight: 700; margin-bottom: 4px; }
.page-subtitle { font-size: .82rem; color: var(--ink-3); margin-bottom: 28px; }

.ti-card { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); overflow: hidden; }
.ti-card-header { display: flex; align-items: center; gap: .75rem; padding: 1rem 1.25rem; background: #0f172a; color: #fff; }
.ti-card-header-icon { width: 38px; height: 38px; background: rgba(255,255,255,.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff; }
.ti-card-title { font-size: .95rem; font-weight: 600; color: #fff; margin: 0; }
.ti-card-subtitle { font-size: .75rem; color: #94a3b8; margin-top: 1px; }

.ti-entity-bar { display: flex; align-items: center; gap: 1rem; padding: .7rem 1.25rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; }
.ti-entity-bar-item { display: flex; flex-direction: column; gap: 1px; }
.ti-entity-bar-label { font-size: .62rem; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; font-weight: 600; }
.ti-entity-bar-value { font-size: .88rem; font-weight: 700; }
.ti-entity-bar-value.usd { color: #059669; }
.ti-entity-bar-value.afs { color: #2563EB; }
.ti-entity-bar-divider { width: 1px; height: 24px; background: #e2e8f0; }

.ti-body { padding: 0; }
.ti-field { padding: .85rem 1.25rem; border-bottom: 1px solid #f1f5f9; }
.ti-field:last-child { border-bottom: none; }
.ti-hidden { display: none !important; }
.ti-reveal { animation: tiIn .2s ease both; }
@keyframes tiIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

.ti-label { display: flex; align-items: center; gap: .45rem; font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; margin-bottom: .55rem; }
.ti-step { width: 18px; height: 18px; background: #0f172a; color: #fff; border-radius: 50%; font-size: .58rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.ti-label-hint, .ti-optional { font-weight: 400; text-transform: none; letter-spacing: 0; color: #94a3b8; font-size: .68rem; }
.ti-label-hint { margin-left: auto; }

.ti-toggle-group { display: flex; gap: .5rem; flex-wrap: wrap; }
.ti-toggle { flex: 1; min-width: 80px; padding: .5rem .75rem; border: 1.5px solid #e2e8f0; border-radius: 8px; background: #fff; color: #475569; font-size: .82rem; font-weight: 500; cursor: pointer; transition: all .15s; display: flex; align-items: center; justify-content: center; gap: .4rem; }
.ti-toggle:hover { border-color: #94a3b8; background: #f8fafc; }
.ti-toggle.ti-active { border-color: #059669 !important; background: #059669 !important; color: #fff !important; box-shadow: 0 0 0 3px rgba(5,150,105,.15) !important; }
.ti-toggle-credit.ti-active { border-color: #059669 !important; background: #059669 !important; color: #fff !important; }
.ti-toggle-debit.ti-active { border-color: #E11D48 !important; background: #E11D48 !important; color: #fff !important; }

.ti-input-wrap { display: flex; align-items: center; border: 1.5px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s; }
.ti-input-wrap:focus-within { border-color: #0f172a; box-shadow: 0 0 0 3px rgba(15,23,42,.07); }
.ti-input-pre { padding: .5rem .7rem; background: #f8fafc; color: #64748b; font-size: .8rem; font-weight: 500; border-right: 1px solid #e2e8f0; white-space: nowrap; flex-shrink: 0; }
.ti-input { flex: 1; border: none; outline: none; padding: .5rem .75rem; font-size: .88rem; color: #0f172a; background: transparent; min-width: 0; width: 100%; }
.ti-select { cursor: pointer; }
.ti-textarea-wrap { align-items: flex-start; }
.ti-textarea-wrap .ti-input-pre { padding-top: .55rem; }
.ti-textarea { resize: none; }

.ti-footer { display: flex; align-items: center; justify-content: flex-end; gap: .6rem; padding: .85rem 1.25rem; background: #f8fafc; border-top: 1px solid #e2e8f0; }
.ti-btn-cancel { padding: .48rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 8px; background: #fff; color: #64748b; font-size: .83rem; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: .35rem; transition: all .15s; }
.ti-btn-cancel:hover { border-color: #94a3b8; color: #0f172a; }
.ti-btn-submit { padding: .48rem 1.2rem; border: none; border-radius: 8px; background: #0f172a; color: #fff; font-size: .83rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: .4rem; transition: background .15s; }
.ti-btn-submit:hover:not(:disabled) { background: #1e293b; }
.ti-btn-submit:disabled { background: #cbd5e1; color: #94a3b8; cursor: not-allowed; }

.ti-recent { padding: 1rem 1.25rem; }
.ti-recent-title { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #64748b; margin-bottom: .5rem; }
.ti-recent-table { width: 100%; border-collapse: collapse; font-size: .78rem; }
.ti-recent-table th { text-align: left; padding: .4rem .5rem; color: #94a3b8; font-weight: 600; border-bottom: 1px solid #e2e8f0; font-size: .68rem; text-transform: uppercase; }
.ti-recent-table td { padding: .4rem .5rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
.ti-recent-table tr:last-child td { border-bottom: none; }
.ti-badge-credit { display: inline-block; padding: 1px 6px; border-radius: 4px; background: #ecfdf5; color: #059669; font-size: .68rem; font-weight: 600; }
.ti-badge-debit { display: inline-block; padding: 1px 6px; border-radius: 4px; background: #fff1f2; color: #E11D48; font-size: .68rem; font-weight: 600; }

.ti-toast { position: fixed; bottom: 24px; right: 24px; z-index: 9999; padding: .75rem 1.25rem; border-radius: 8px; font-size: .85rem; font-weight: 500; color: #fff; box-shadow: 0 8px 24px rgba(0,0,0,.15); transform: translateY(100px); opacity: 0; transition: all .3s ease; }
.ti-toast.show { transform: translateY(0); opacity: 1; }
.ti-toast.success { background: #059669; }
.ti-toast.error { background: #E11D48; }
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="shell">
            <div class="page-title">Insert Transaction</div>
            <div class="page-subtitle">Select tenant, branch, then account to insert a credit or debit transaction</div>

            <div class="ti-card">
                <div class="ti-card-header">
                    <div class="ti-card-header-icon"><i class="feather icon-credit-card"></i></div>
                    <div>
                        <div class="ti-card-title">New Transaction</div>
                        <div class="ti-card-subtitle" id="tiEntityDisplay">Start by selecting a tenant</div>
                    </div>
                </div>

                <div class="ti-entity-bar ti-hidden" id="tiEntityBar"></div>

                <form id="tiForm" class="ti-body">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" id="tiTenantId" name="tenant_id">
                    <input type="hidden" id="tiBranchId" name="branch_id">
                    <input type="hidden" id="tiEntityType" name="entity_type">
                    <input type="hidden" id="tiEntityId" name="entity_id">
                    <input type="hidden" id="tiTransactionType" name="transaction_type">
                    <input type="hidden" id="tiCurrency" name="currency">

                    <!-- Step 1: Select Tenant -->
                    <div class="ti-field" id="tiFieldTenant">
                        <label class="ti-label">
                            <span class="ti-step">1</span>
                            Select Tenant
                        </label>
                        <div class="ti-input-wrap">
                            <span class="ti-input-pre"><i class="fas fa-building"></i></span>
                            <select class="ti-input ti-select" id="tiTenantSelect" name="tenant_select">
                                <option value="">Loading tenants...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 2: Select Branch -->
                    <div class="ti-field ti-hidden" id="tiFieldBranch">
                        <label class="ti-label">
                            <span class="ti-step">2</span>
                            Select Branch
                        </label>
                        <div class="ti-input-wrap">
                            <span class="ti-input-pre"><i class="fas fa-code-branch"></i></span>
                            <select class="ti-input ti-select" id="tiBranchSelect" name="branch_select">
                                <option value="">Loading branches...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 3: Entity Type -->
                    <div class="ti-field ti-hidden" id="tiFieldType">
                        <label class="ti-label">
                            <span class="ti-step">3</span>
                            Account type
                        </label>
                        <div class="ti-toggle-group">
                            <button type="button" class="ti-toggle" data-value="client" data-target="tiEntityType">
                                <i class="feather icon-user"></i> Client
                            </button>
                            <button type="button" class="ti-toggle" data-value="supplier" data-target="tiEntityType">
                                <i class="fas fa-truck"></i> Supplier
                            </button>
                            <button type="button" class="ti-toggle" data-value="main_account" data-target="tiEntityType">
                                <i class="fas fa-university"></i> Main Account
                            </button>
                        </div>
                    </div>

                    <!-- Step 4: Select Entity -->
                    <div class="ti-field ti-hidden" id="tiFieldEntity">
                        <label class="ti-label">
                            <span class="ti-step">4</span>
                            <span id="tiEntityLabel">Select account</span>
                        </label>
                        <div class="ti-input-wrap">
                            <span class="ti-input-pre"><i class="feather icon-search"></i></span>
                            <select class="ti-input ti-select" id="tiEntitySelect" name="entity_select">
                                <option value="">Loading...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 5: Credit/Debit -->
                    <div class="ti-field ti-hidden" id="tiFieldTxnType">
                        <label class="ti-label">
                            <span class="ti-step">5</span>
                            Transaction direction
                        </label>
                        <div class="ti-toggle-group">
                            <button type="button" class="ti-toggle ti-toggle-credit" data-value="credit" data-target="tiTransactionType">
                                <i class="fas fa-arrow-up"></i> Credit (+)
                            </button>
                            <button type="button" class="ti-toggle ti-toggle-debit" data-value="debit" data-target="tiTransactionType">
                                <i class="fas fa-arrow-down"></i> Debit (-)
                            </button>
                        </div>
                    </div>

                    <!-- Step 6: Currency -->
                    <div class="ti-field ti-hidden" id="tiFieldCurrency">
                        <label class="ti-label">
                            <span class="ti-step">6</span>
                            Currency
                        </label>
                        <div class="ti-toggle-group" id="tiCurrencyToggles"></div>
                    </div>

                    <!-- Step 7: Amount -->
                    <div class="ti-field ti-hidden" id="tiFieldAmount">
                        <label class="ti-label">
                            <span class="ti-step">7</span>
                            Amount
                            <span class="ti-label-hint" id="tiAmountHint">in USD</span>
                        </label>
                        <div class="ti-input-wrap">
                            <span class="ti-input-pre" id="tiAmountSymbol">$</span>
                            <input type="number" class="ti-input" id="tiAmount" name="amount" step="0.01" min="0.01" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Step 8: Transaction ID -->
                    <div class="ti-field ti-hidden" id="tiFieldRefId">
                        <label class="ti-label">
                            <span class="ti-step">8</span>
                            Transaction ID
                            <span class="ti-optional">optional</span>
                        </label>
                        <div class="ti-input-wrap">
                            <span class="ti-input-pre"><i class="fas fa-link"></i></span>
                            <input type="number" class="ti-input" id="tiRefTransactionId" name="reference_transaction_id" placeholder="Enter free earlier ID to fill gap">
                        </div>
                        <div id="tiFreeIdHint" style="margin-top:.4rem;display:none;">
                            <small style="font-size:.72rem;color:#059669;background:#ecfdf5;padding:4px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;">
                                <i class="fas fa-magic"></i>
                                <span id="tiFreeIdText">Next free ID: —</span>
                            </small>
                        </div>
                        <div id="tiExistingIdsWrap" style="margin-top:.3rem;display:none;">
                            <small style="font-size:.68rem;color:#94a3b8;">Existing IDs: <span id="tiExistingIds"></span></small>
                        </div>
                    </div>

                    <!-- Step 9: Receipt -->
                    <div class="ti-field ti-hidden" id="tiFieldReceipt">
                        <label class="ti-label">
                            <span class="ti-step">9</span>
                            Receipt Number
                            <span class="ti-optional">optional</span>
                        </label>
                        <div class="ti-input-wrap">
                            <span class="ti-input-pre"><i class="feather icon-hash"></i></span>
                            <input type="text" class="ti-input" id="tiReceipt" name="receipt" placeholder="Enter receipt number">
                        </div>
                    </div>

                    <!-- Step 10: Remarks -->
                    <div class="ti-field ti-hidden" id="tiFieldRemarks">
                        <label class="ti-label">
                            <span class="ti-step">10</span>
                            Remarks
                            <span class="ti-optional">optional</span>
                        </label>
                        <div class="ti-input-wrap ti-textarea-wrap">
                            <span class="ti-input-pre"><i class="feather icon-message-square"></i></span>
                            <textarea class="ti-input ti-textarea" id="tiRemarks" name="remarks" rows="2" placeholder="Enter transaction details..."></textarea>
                        </div>
                    </div>
                </form>

                <div class="ti-recent ti-hidden" id="tiRecentSection">
                    <div class="ti-recent-title">Last 5 transactions</div>
                    <table class="ti-recent-table">
                        <thead><tr><th>ID</th><th>Type</th><th>Amount</th><th>Date</th></tr></thead>
                        <tbody id="tiRecentBody"></tbody>
                    </table>
                </div>

                <div class="ti-footer">
                    <button type="button" class="ti-btn-cancel" onclick="location.reload()"><i class="feather icon-x"></i> Reset</button>
                    <button type="button" class="ti-btn-submit" id="tiSubmitBtn" disabled><i class="feather icon-check-circle"></i> Insert Transaction</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ti-toast" id="tiToast"></div>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
(function () {
    const $ = id => document.getElementById(id);
    const s = { tenantId: null, branchId: null, entityType: null, entityId: null, txnType: null, currency: null };

    let fields = {};
    function initFields() {
        fields = {
            branch:   $('tiFieldBranch'),
            type:     $('tiFieldType'),
            entity:   $('tiFieldEntity'),
            txnType:  $('tiFieldTxnType'),
            currency: $('tiFieldCurrency'),
            amount:   $('tiFieldAmount'),
            refId:    $('tiFieldRefId'),
            receipt:  $('tiFieldReceipt'),
            remarks:  $('tiFieldRemarks'),
        };
    }

    function reveal(el) { el.classList.remove('ti-hidden'); el.classList.add('ti-reveal'); }
    function hide(el) { el.classList.add('ti-hidden'); el.classList.remove('ti-reveal'); }
    function clearField(el) {
        el.querySelectorAll('input[type=text], input[type=number], select, textarea').forEach(i => { i.value = ''; });
        el.querySelectorAll('.ti-toggle').forEach(b => b.classList.remove('ti-active'));
    }

    const fieldOrder = ['branch','type','entity','txnType','currency','amount','refId','receipt','remarks'];
    function resetFrom(key) {
        const idx = fieldOrder.indexOf(key);
        fieldOrder.slice(idx).forEach(k => { if(fields[k]) { hide(fields[k]); clearField(fields[k]); } });
        $('tiSubmitBtn').disabled = true;
    }

    function showToast(msg, type) {
        const t = $('tiToast');
        t.textContent = msg;
        t.className = 'ti-toast ' + type + ' show';
        setTimeout(() => { t.classList.remove('show'); }, 3500);
    }

    const CUR_SYM = { USD: '$', AFS: '؋', EUR: '€', DARHAM: 'د.إ', SAR: '﷼' };
    const CUR_NAME = { USD: 'USD', AFS: 'AFS', EUR: 'EUR', DARHAM: 'AED', SAR: 'SAR' };

    // ── Load Tenants ────────────────────────────────────────────────
    fetch('api_get_tenants.php')
        .then(r => r.json())
        .then(data => {
            const sel = $('tiTenantSelect');
            sel.innerHTML = '<option value="">Select tenant...</option>';
            (data.tenants || []).forEach(t => {
                sel.innerHTML += '<option value="'+t.id+'" data-name="'+t.name+'">'+t.name+' ('+t.identifier+')</option>';
            });
        })
        .catch(() => { $('tiTenantSelect').innerHTML = '<option value="">Failed to load</option>'; });

    // ── Tenant Change ──────────────────────────────────────────────
    $('tiTenantSelect').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!this.value) return;
        s.tenantId = this.value;
        $('tiTenantId').value = this.value;
        $('tiEntityDisplay').textContent = 'Tenant: ' + (opt.dataset.name || opt.text);
        resetFrom('branch');

        fetch('api_get_branches.php?tenant_id=' + s.tenantId)
            .then(r => r.json())
            .then(data => {
                const sel = $('tiBranchSelect');
                sel.innerHTML = '<option value="">Select branch...</option>';
                (data.branches || []).forEach(b => {
                    sel.innerHTML += '<option value="'+b.id+'" data-name="'+b.name+'">'+b.name+(b.code ? ' ('+b.code+')' : '')+'</option>';
                });
                reveal(fields.branch);
            })
            .catch(() => { $('tiBranchSelect').innerHTML = '<option value="">Failed to load</option>'; });
    });

    // ── Branch Change ──────────────────────────────────────────────
    $('tiBranchSelect').addEventListener('change', function () {
        if (!this.value) return;
        s.branchId = this.value;
        $('tiBranchId').value = this.value;
        resetFrom('type');
        reveal(fields.type);
    });

    // ── Toggle Handler ─────────────────────────────────────────────
    function handleToggle() {
        const target = this.dataset.target;
        const value = this.dataset.value;
        document.querySelectorAll('.ti-toggle[data-target="'+target+'"]').forEach(b => b.classList.remove('ti-active'));
        this.classList.add('ti-active');
        $(target).value = value;

        if (target === 'tiEntityType') {
            s.entityType = value;
            resetFrom('entity');
            $('tiEntityLabel').textContent = value === 'client' ? 'Select client' : value === 'supplier' ? 'Select supplier' : 'Select main account';
            loadEntities(value);
            reveal(fields.entity);
        } else if (target === 'tiTransactionType') {
            s.txnType = value;
            resetFrom('currency');
            setupCurrencyToggles();
            reveal(fields.currency);
        } else if (target === 'tiCurrency') {
            s.currency = value;
            resetFrom('amount');
            $('tiAmountSymbol').textContent = CUR_SYM[value] || value;
            $('tiAmountHint').textContent = 'in ' + (CUR_NAME[value] || value);
            reveal(fields.amount);
            loadRecentTransactions();
            loadFreeIds();
        }
    }
    document.querySelectorAll('.ti-toggle').forEach(btn => { btn.addEventListener('click', handleToggle); });

    // ── Load Entities ──────────────────────────────────────────────
    function loadEntities(type) {
        const sel = $('tiEntitySelect');
        sel.innerHTML = '<option value="">Loading...</option>';
        const params = 'tenant_id=' + s.tenantId + '&branch_id=' + s.branchId;
        let url = '';
        if (type === 'client') url = 'api_get_clients.php?' + params;
        else if (type === 'supplier') url = 'api_get_suppliers.php?' + params;
        else if (type === 'main_account') url = 'api_get_main_accounts.php?' + params;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                let items = [];
                if (type === 'client') {
                    items = Array.isArray(data) ? data : [];
                    sel.innerHTML = '<option value="">Select client...</option>';
                    items.forEach(c => {
                        sel.innerHTML += '<option value="'+c.id+'" data-usd="'+(c.usd_balance||0)+'" data-afs="'+(c.afs_balance||0)+'" data-name="'+c.name+'">'+c.name+'</option>';
                    });
                } else if (type === 'supplier') {
                    items = data.suppliers || [];
                    sel.innerHTML = '<option value="">Select supplier...</option>';
                    items.forEach(c => {
                        sel.innerHTML += '<option value="'+c.id+'" data-balance="'+(c.balance||0)+'" data-currency="'+(c.currency||'USD')+'" data-name="'+c.name+'">'+c.name+'</option>';
                    });
                } else if (type === 'main_account') {
                    items = data.accounts || [];
                    sel.innerHTML = '<option value="">Select main account...</option>';
                    items.forEach(a => {
                        sel.innerHTML += '<option value="'+a.id+'" data-usd="'+(a.usd_balance||0)+'" data-afs="'+(a.afs_balance||0)+'" data-bal_usd="'+(a.usd_balance||0)+'" data-bal_afs="'+(a.afs_balance||0)+'" data-bal_eur="'+(a.euro_balance||0)+'" data-bal_darham="'+(a.darham_balance||0)+'" data-bal_sar="'+(a.sar_balance||0)+'" data-name="'+a.name+'">'+a.name+'</option>';
                    });
                }
            })
            .catch(() => { sel.innerHTML = '<option value="">Failed to load</option>'; });
    }

    // ── Entity Select Change ───────────────────────────────────────
    $('tiEntitySelect').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (!this.value) return;
        s.entityId = this.value;
        $('tiEntityId').value = this.value;
        $('tiEntityDisplay').textContent = s.entityType.replace('_', ' ') + ': ' + (opt.dataset.name || opt.text);

        const bar = $('tiEntityBar');
        bar.innerHTML = '';
        if (s.entityType === 'client') {
            const usd = parseFloat(opt.dataset.usd || 0);
            const afs = parseFloat(opt.dataset.afs || 0);
            bar.innerHTML = '<div class="ti-entity-bar-item"><span class="ti-entity-bar-label">USD</span><span class="ti-entity-bar-value usd">$'+usd.toFixed(2)+'</span></div><div class="ti-entity-bar-divider"></div><div class="ti-entity-bar-item"><span class="ti-entity-bar-label">AFS</span><span class="ti-entity-bar-value afs">'+afs.toFixed(2)+'</span></div>';
        } else if (s.entityType === 'main_account') {
            const currencies = ['usd','afs','eur','darham','sar'];
            const labels = ['USD','AFS','EUR','AED','SAR'];
            const colors = ['#059669','#2563EB','#8b5cf6','#d97706','#dc2626'];
            let html = '';
            currencies.forEach((c, i) => {
                const val = parseFloat(opt.dataset['bal_'+c] || 0);
                if (val !== 0) {
                    if (html) html += '<div class="ti-entity-bar-divider"></div>';
                    html += '<div class="ti-entity-bar-item"><span class="ti-entity-bar-label">'+labels[i]+'</span><span class="ti-entity-bar-value" style="color:'+colors[i]+'">'+(CUR_SYM[labels[i]]||'')+val.toFixed(2)+'</span></div>';
                }
            });
            bar.innerHTML = html || '<div class="ti-entity-bar-item"><span class="ti-entity-bar-label">No balances</span></div>';
        } else if (s.entityType === 'supplier') {
            const bal = parseFloat(opt.dataset.balance || 0);
            const cur = opt.dataset.currency || 'USD';
            bar.innerHTML = '<div class="ti-entity-bar-item"><span class="ti-entity-bar-label">Balance ('+cur+')</span><span class="ti-entity-bar-value">'+(CUR_SYM[cur]||cur)+bal.toFixed(2)+'</span></div>';
        }
        reveal(bar);
        resetFrom('txnType');
        reveal(fields.txnType);
        loadRecentTransactions();
    });

    // ── Currency Toggles ───────────────────────────────────────────
    function setupCurrencyToggles() {
        const wrap = $('tiCurrencyToggles');
        wrap.innerHTML = '';
        ['USD','AFS','EUR','DARHAM','SAR'].forEach(c => {
            const label = c === 'DARHAM' ? 'AED' : c;
            const icon = c === 'USD' ? 'dollar-sign' : c === 'EUR' ? 'euro-sign' : 'money-bill-wave';
            wrap.innerHTML += '<button type="button" class="ti-toggle" data-value="'+c+'" data-target="tiCurrency"><i class="fas fa-'+icon+'"></i> '+label+'</button>';
        });
        wrap.querySelectorAll('.ti-toggle').forEach(btn => { btn.addEventListener('click', handleToggle); });
    }

    // ── Recent Transactions ────────────────────────────────────────
    function loadRecentTransactions() {
        if (!s.entityType || !s.entityId) return;
        let url = '';
        const base = '../api/accounts/';
        const curParam = s.currency ? '&currency=' + s.currency : '';
        if (s.entityType === 'client') url = base+'get_client_transactions.php?client_id='+s.entityId+'&per_page=5'+curParam;
        else if (s.entityType === 'supplier') url = base+'get_supplier_transactions_main.php?supplier_id='+s.entityId+'&per_page=5';
        else if (s.entityType === 'main_account') url = base+'get_main_account_transactions.php?account_id='+s.entityId+'&per_page=5'+curParam;

        fetch(url).then(r => r.json()).then(data => {
            const txns = data.data || data.transactions || [];
            const tbody = $('tiRecentBody');
            tbody.innerHTML = '';
            if (txns.length === 0) { tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#94a3b8;">No transactions'+(s.currency ? ' in '+s.currency : '')+'</td></div>'; return; }
            txns.slice(0, 5).forEach(t => {
                const type = (t.type || t.transaction_type || '').toLowerCase();
                const badge = type === 'credit' ? '<span class="ti-badge-credit">Credit</span>' : '<span class="ti-badge-debit">Debit</span>';
                const amt = parseFloat(t.amount || 0);
                const cur = t.currency || 'USD';
                const date = t.created_at ? new Date(t.created_at).toLocaleDateString() : '-';
                tbody.innerHTML += '<tr><td>'+(t.id||'-')+'</td><td>'+badge+'</td><td>'+(CUR_SYM[cur]||cur)+amt.toFixed(2)+'</td><td>'+date+'</td></tr>';
            });
            reveal($('tiRecentSection'));
        }).catch(() => {});
    }

    // ── Free Transaction IDs ──────────────────────────────────────
    function loadFreeIds() {
        if (!s.entityType || !s.entityId || !s.currency) return;
        var hint = $('tiFreeIdHint');
        var idsWrap = $('tiExistingIdsWrap');
        hint.style.display = 'none';
        idsWrap.style.display = 'none';

        fetch('api_get_free_ids.php?account_id='+s.entityId+'&currency='+s.currency+'&entity_type='+s.entityType)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                $('tiFreeIdText').textContent = 'Suggested next free ID: ' + data.next_free_id;
                hint.style.display = 'block';
                if (data.existing_ids && data.existing_ids.length > 0) {
                    $('tiExistingIds').textContent = data.existing_ids.slice(0, 8).join(', ') + (data.existing_ids.length >= 8 ? '...' : '');
                    idsWrap.style.display = 'block';
                }
            }).catch(() => {});
    }

    // ── Amount Input ───────────────────────────────────────────────
    $('tiAmount').addEventListener('input', function () {
        if (parseFloat(this.value) > 0) {
            if (fields.refId && fields.refId.classList.contains('ti-hidden')) reveal(fields.refId);
            if (fields.receipt && fields.receipt.classList.contains('ti-hidden')) reveal(fields.receipt);
            if (fields.remarks && fields.remarks.classList.contains('ti-hidden')) reveal(fields.remarks);
        }
        checkSubmit();
    });
    $('tiRefTransactionId').addEventListener('input', checkSubmit);
    $('tiReceipt').addEventListener('input', checkSubmit);
    $('tiRemarks').addEventListener('input', checkSubmit);

    function checkSubmit() {
        const ok = s.tenantId && s.branchId && s.entityType && s.entityId && s.txnType && s.currency && parseFloat($('tiAmount').value) > 0;
        $('tiSubmitBtn').disabled = !ok;
    }

    // ── Submit ─────────────────────────────────────────────────────
    $('tiSubmitBtn').addEventListener('click', function () {
        const form = $('tiForm');
        if (!form) return;
        const btn = this;
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processing...';

        fetch('../api/accounts/insert_transaction.php', { method: 'POST', body: new FormData(form) })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast('Transaction inserted successfully!', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(data.message || 'Failed', 'error');
                    btn.disabled = false; btn.innerHTML = origHtml;
                }
            })
            .catch(() => { showToast('Network error', 'error'); btn.disabled = false; btn.innerHTML = origHtml; });
    });

    initFields();
})();
</script>
