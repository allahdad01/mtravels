<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
    header('Location: ../login.php?timeout=1'); exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php'); exit();
}

if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, profile_pic FROM users WHERE id = ? AND role = 'super_admin'");
$stmt->execute([$user_id]);
$user = $stmt->fetch() ?: ['name' => 'Admin', 'profile_pic' => null];
$imagePath = !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : '../assets/images/user/avatar-2.jpg';

$tenants = $pdo->prepare("SELECT id, name FROM tenants WHERE status != 'deleted' ORDER BY name");
$tenants->execute();
$tenantList = $tenants->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Balances — Super Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #333; }
        .page-header { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); color: #fff; padding: 32px 40px; }
        .page-header h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: 6px; }
        .page-header p { opacity: .75; font-size: .9rem; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 28px; margin-bottom: 24px; }
        .card h2 { font-size: 1.15rem; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
        .card h2 i { color: #4099ff; }
        .form-row { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: .82rem; font-weight: 600; color: #555; }
        .form-group select, .form-group input {
            padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px;
            font-size: .9rem; min-width: 200px; background: #fafbfc;
        }
        .form-group select:focus, .form-group input:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 3px rgba(64,153,255,.15); }
        .entity-search-wrap { position: relative; min-width: 300px; }
        .entity-search-input { width: 100%; padding: 10px 14px; padding-right: 36px; border: 1px solid #ddd; border-radius: 8px; font-size: .9rem; background: #fafbfc; }
        .entity-search-input:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 3px rgba(64,153,255,.15); }
        .entity-dropdown { position: absolute; top: 100%; left: 0; right: 0; max-height: 320px; overflow-y: auto; background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); z-index: 1000; display: none; margin-top: 4px; }
        .entity-dropdown.open { display: block; }
        .entity-dropdown-item { padding: 10px 14px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: .85rem; border-bottom: 1px solid #f0f0f0; }
        .entity-dropdown-item:last-child { border-bottom: none; }
        .entity-dropdown-item:hover { background: #f0f7ff; }
        .entity-dropdown-item.selected { background: #e8f4fd; font-weight: 600; }
        .entity-dropdown-item .entity-badge { padding: 2px 8px; border-radius: 10px; font-size: .7rem; font-weight: 600; }
        .badge-client { background: #e8daef; color: #6c3483; }
        .badge-supplier { background: #d5f5e3; color: #1e8449; }
        .badge-main { background: #fdebd0; color: #ca6f1e; }
        .badge-all { background: #e2e3e5; color: #383d41; }
        .entity-search-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #999; font-size: .85rem; display: none; }
        .entity-search-clear.visible { display: block; }
        .entity-loading { padding: 14px; text-align: center; color: #999; font-size: .85rem; }
        .btn { padding: 10px 22px; border: none; border-radius: 8px; font-size: .9rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all .2s; }
        .btn-scan { background: #4099ff; color: #fff; }
        .btn-scan:hover { background: #3380e0; }
        .btn-apply { background: #28a745; color: #fff; }
        .btn-apply:hover { background: #218838; }
        .btn:disabled { opacity: .6; cursor: not-allowed; }
        .result-summary { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
        .summary-chip { padding: 10px 18px; border-radius: 8px; font-size: .85rem; font-weight: 600; }
        .chip-fixed { background: #d4edda; color: #155724; }
        .chip-unchanged { background: #e2e3e5; color: #383d41; }
        .chip-mode { background: #cce5ff; color: #004085; }
        .client-result { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 10px; padding: 16px; margin-bottom: 12px; }
        .client-result-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .client-name { font-weight: 600; font-size: .95rem; }
        .client-badge { padding: 3px 10px; border-radius: 12px; font-size: .75rem; font-weight: 600; }
        .badge-applied { background: #d4edda; color: #155724; }
        .badge-dry { background: #fff3cd; color: #856404; }
        .badge-error { background: #f8d7da; color: #721c24; }
        .badge-correct { background: #d4edda; color: #155724; }
        .balance-change { display: flex; gap: 24px; margin-bottom: 8px; font-size: .85rem; }
        .balance-change span { display: flex; align-items: center; gap: 4px; }
        .arrow { color: #999; margin: 0 4px; }
        .txn-table { width: 100%; border-collapse: collapse; font-size: .8rem; margin-top: 8px; }
        .txn-table th { text-align: left; padding: 6px 8px; background: #e9ecef; font-weight: 600; }
        .txn-table td { padding: 6px 8px; border-bottom: 1px solid #eee; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .empty-state i { font-size: 2.5rem; margin-bottom: 12px; display: block; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin .6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .warning-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; font-size: .85rem; color: #856404; display: flex; align-items: flex-start; gap: 10px; }
        .warning-box i { margin-top: 2px; }
        .status-correct td { background: #d4edda; }
        .status-wrong td { background: #f8d7da; }
    </style>
</head>
<body>
    <div class="page-header">
        <h1><i class="fas fa-wrench" style="margin-right:10px;"></i> Fix Account Balances</h1>
        <p>Recalculate running balances and master balance from transaction history</p>
    </div>

    <div class="container">
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Dangerous operation.</strong> Always run <em>Scan</em> first to preview changes. Only click <em>Apply</em> after reviewing the results.
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-cog"></i> Configuration</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Tenant</label>
                    <select id="tenantSelect">
                        <option value="">— Select Tenant —</option>
                        <?php foreach ($tenantList as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Type</label>
                    <select id="entityTypeSelect">
                        <option value="client">Client</option>
                        <option value="supplier">Supplier</option>
                        <option value="main_account">Main Account</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select id="currencyFilter">
                        <option value="">All Currencies</option>
                        <option value="USD">USD</option>
                        <option value="AFS">AFS</option>
                        <option value="EUR">EUR</option>
                        <option value="SAR">SAR</option>
                        <option value="DARHAM">DARHAM</option>
                    </select>
                </div>
                <div class="form-group">
                    <label id="entityLabel">Account</label>
                    <div class="entity-search-wrap" id="entitySearchWrap">
                        <input type="text" class="entity-search-input" id="entitySearchInput" placeholder="All accounts (leave empty) or type to search..." autocomplete="off" onfocus="openEntityDropdown()" oninput="filterEntities()">
                        <button class="entity-search-clear" id="entityClearBtn" onclick="clearEntity()">&times;</button>
                        <div class="entity-dropdown" id="entityDropdown"></div>
                    </div>
                    <input type="hidden" id="entityInput" value="">
                    <input type="hidden" id="entityCurrency" value="">
                </div>
                <div class="form-group" style="flex-direction:row; gap:10px; align-self:flex-end;">
                    <button class="btn btn-scan" id="scanBtn" onclick="runFix('scan')">
                        <i class="fas fa-search"></i> Scan
                    </button>
                    <button class="btn btn-apply" id="applyBtn" onclick="runFix('apply')" disabled>
                        <i class="fas fa-check"></i> Apply
                    </button>
                </div>
            </div>
        </div>

        <div class="card" id="resultsCard" style="display:none;">
            <h2><i class="fas fa-clipboard-list"></i> Results</h2>
            <div id="resultsContent"></div>
        </div>
    </div>

    <script>
    var csrfToken = '<?= $_SESSION['csrf_token'] ?>';
    var lastScanData = null;
    var allEntities = [];

    document.getElementById('tenantSelect').addEventListener('change', function() {
        clearEntity();
        loadEntities();
    });
    document.getElementById('entityTypeSelect').addEventListener('change', function() {
        clearEntity();
        updateCurrencyVisibility();
    updateEntityLabel();
        updateCurrencyVisibility();
        loadEntities();
    });

    function updateCurrencyVisibility() {
        var type = document.getElementById('entityTypeSelect').value;
        var currencyGroup = document.getElementById('currencyFilter').parentElement;
        currencyGroup.style.display = (type === 'supplier') ? 'none' : '';
    }

    document.addEventListener('click', function(e) {
        var wrap = document.getElementById('entitySearchWrap');
        if (wrap && !wrap.contains(e.target)) closeEntityDropdown();
    });

    function updateEntityLabel() {
        var type = document.getElementById('entityTypeSelect').value;
        var labels = { client: 'Client', supplier: 'Supplier', main_account: 'Main Account' };
        document.getElementById('entityLabel').textContent = labels[type] || 'Account';
    }

    function loadEntities() {
        var tenantId = document.getElementById('tenantSelect').value;
        var entityType = document.getElementById('entityTypeSelect').value;
        if (!tenantId) return;

        var dropdown = document.getElementById('entityDropdown');
        dropdown.innerHTML = '<div class="entity-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        dropdown.classList.add('open');

        fetch('handlers/get_clients_for_tenant.php?tenant_id=' + tenantId + '&entity_type=' + entityType)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.items) {
                    allEntities = data.items;
                    renderEntityList(allEntities);
                } else {
                    allEntities = [];
                    renderEntityList([]);
                }
            })
            .catch(function() {
                allEntities = [];
                renderEntityList([]);
            });
    }

    function renderEntityList(items) {
        var dropdown = document.getElementById('entityDropdown');
        var entityType = document.getElementById('entityTypeSelect').value;
        var html = '<div class="entity-dropdown-item selected" data-id="" data-currency="" onclick="selectEntity(this)"><span><i class="fas fa-layer-group" style="margin-right:6px;color:#999;"></i> All accounts</span><span class="entity-badge badge-all">all</span></div>';
        if (items.length === 0) {
            html += '<div class="entity-dropdown-item" style="cursor:default;color:#999;"><span>No accounts with transactions</span></div>';
        } else {
            items.forEach(function(item) {
                var badgeClass = entityType === 'client' ? 'badge-client' : (entityType === 'supplier' ? 'badge-supplier' : 'badge-main');
                var label = '#' + item.id + ' — ' + escapeHtml(item.name);
                var badge = item.txn_count + ' txns';
                if (entityType === 'client') {
                    badge += ' (' + item.client_type + ' ' + item.currency + ')';
                } else if (entityType === 'supplier') {
                    badge += ' (' + item.supplier_type + ' ' + item.currency + ')';
                } else if (entityType === 'main_account') {
                    badge += ' (' + item.account_type + ' ' + item.currency + ')';
                }
                html += '<div class="entity-dropdown-item" data-id="' + item.id + '" data-currency="' + (item.currency || '') + '" data-name="' + escapeAttr(item.name) + '" onclick="selectEntity(this)">';
                html += '<span>' + label + '</span>';
                html += '<span class="entity-badge ' + badgeClass + '">' + badge + '</span>';
                html += '</div>';
            });
        }
        dropdown.innerHTML = html;
    }

    function openEntityDropdown() {
        document.getElementById('entityDropdown').classList.add('open');
    }

    function closeEntityDropdown() {
        document.getElementById('entityDropdown').classList.remove('open');
    }

    function filterEntities() {
        var query = document.getElementById('entitySearchInput').value.toLowerCase().trim();
        var items = document.querySelectorAll('#entityDropdown .entity-dropdown-item');
        items.forEach(function(item) {
            if (item.dataset.id === '') { item.style.display = ''; return; }
            var name = (item.dataset.name || '').toLowerCase();
            var id = item.dataset.id || '';
            var match = !query || name.indexOf(query) !== -1 || id === query;
            item.style.display = match ? '' : 'none';
        });
        openEntityDropdown();
    }

    function selectEntity(el) {
        var id = el.dataset.id || '';
        var name = el.dataset.name || 'All accounts';
        var currency = el.dataset.currency || '';
        document.getElementById('entityInput').value = id;
        document.getElementById('entityCurrency').value = currency;
        document.getElementById('entitySearchInput').value = id ? ('#' + id + ' — ' + name) : '';
        document.getElementById('entityClearBtn').classList.toggle('visible', !!id);
        document.querySelectorAll('#entityDropdown .entity-dropdown-item').forEach(function(i) { i.classList.remove('selected'); });
        el.classList.add('selected');
        closeEntityDropdown();
    }

    function clearEntity() {
        document.getElementById('entityInput').value = '';
        document.getElementById('entityCurrency').value = '';
        document.getElementById('entitySearchInput').value = '';
        document.getElementById('entityClearBtn').classList.remove('visible');
        var first = document.querySelector('#entityDropdown .entity-dropdown-item');
        if (first) { first.classList.add('selected'); }
    }

    function runFix(mode) {
        var tenantId = document.getElementById('tenantSelect').value;
        var entityType = document.getElementById('entityTypeSelect').value;
        if (!tenantId) { alert('Please select a tenant.'); return; }
        if (mode === 'apply' && !confirm('Are you sure you want to apply these balance fixes? This will modify database records.')) return;

        var scanBtn = document.getElementById('scanBtn');
        var applyBtn = document.getElementById('applyBtn');
        scanBtn.disabled = true;
        applyBtn.disabled = true;
        scanBtn.innerHTML = '<span class="spinner"></span> Working...';

        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('tenant_id', tenantId);
        formData.append('entity_id', document.getElementById('entityInput').value);
        formData.append('entity_currency', document.getElementById('entityCurrency').value);
        formData.append('currency_filter', document.getElementById('currencyFilter').value);
        formData.append('entity_type', entityType);
        formData.append('mode', mode);

        fetch('handlers/recalc_balances.php?t=' + Date.now(), { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                scanBtn.disabled = false;
                scanBtn.innerHTML = '<i class="fas fa-search"></i> Scan';
                if (data.success) {
                    lastScanData = data;
                    if (mode === 'scan') applyBtn.disabled = false;
                    renderResults(data);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(function(err) {
                scanBtn.disabled = false;
                scanBtn.innerHTML = '<i class="fas fa-search"></i> Scan';
                alert('Request failed: ' + err.message);
            });
    }

    function renderResults(data) {
        var card = document.getElementById('resultsCard');
        var content = document.getElementById('resultsContent');
        card.style.display = 'block';

        var modeLabel = data.mode === 'apply' ? 'Applied' : 'Dry Run';
        var typeLabel = { client: 'Clients', supplier: 'Suppliers', main_account: 'Main Accounts' }[data.entity_type] || 'Accounts';
        var html = '<div class="result-summary">';
        html += '<span class="summary-chip chip-mode"><i class="fas fa-info-circle"></i> ' + modeLabel + '</span>';
        html += '<span class="summary-chip chip-fixed"><i class="fas fa-check-circle"></i> ' + data.fixed + ' ' + typeLabel + ' fixed</span>';
        html += '<span class="summary-chip chip-unchanged"><i class="fas fa-minus-circle"></i> ' + data.unchanged + ' unchanged</span>';
        html += '</div>';

        if (data.clients && data.clients.length > 0) {
            data.clients.forEach(function(c) {
                var badgeClass, badgeText;
                if (c.applied) {
                    badgeClass = 'badge-applied'; badgeText = 'Applied';
                } else if (c.error) {
                    badgeClass = 'badge-error'; badgeText = 'Error';
                } else if (c.txn_fixes && c.txn_fixes.length > 0) {
                    badgeClass = 'badge-dry'; badgeText = 'Needs Fix';
                } else {
                    badgeClass = 'badge-correct'; badgeText = 'All Correct';
                }

                var entityId = c.entity_id || c.client_id || '';
                var entityName = c.entity_name || c.client_name || '';

                html += '<div class="client-result">';
                html += '<div class="client-result-header">';
                html += '<span class="client-name">#' + entityId + ' — ' + escapeHtml(entityName) + ' [' + c.currency + ']</span>';
                html += '<span class="client-badge ' + badgeClass + '">' + badgeText + '</span>';
                html += '</div>';

                html += '<div class="balance-change">';
                html += '<span>Master: <strong>' + formatNum(c.old_master) + '</strong></span>';
                html += '<span class="arrow"><i class="fas fa-arrow-right"></i></span>';
                html += '<span><strong>' + formatNum(c.new_master) + '</strong></span>';
                html += '</div>';

                if (c.all_txns && c.all_txns.length > 0) {
                    html += '<table class="txn-table"><thead><tr><th>Txn ID</th><th>Type</th><th>Amount</th><th>Stored Balance</th><th>Calculated Balance</th><th>Status</th></tr></thead><tbody>';
                    c.all_txns.forEach(function(t) {
                        var statusClass = t.correct ? 'status-correct' : 'status-wrong';
                        var statusText = t.correct ? 'Correct' : 'Diff: ' + formatNum(t.diff);
                        html += '<tr class="' + statusClass + '">';
                        html += '<td>' + t.id + '</td>';
                        html += '<td>' + t.type + '</td>';
                        html += '<td>' + formatNum(t.amount) + '</td>';
                        html += '<td>' + formatNum(t.old_balance) + '</td>';
                        html += '<td>' + formatNum(t.new_balance) + '</td>';
                        html += '<td><strong>' + statusText + '</strong></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                }

                if (c.error) html += '<div style="color:#721c24;margin-top:8px;font-size:.82rem;"><i class="fas fa-exclamation-circle"></i> ' + escapeHtml(c.error) + '</div>';

                html += '</div>';
            });
        } else if (data.fixed === 0 && data.unchanged === 0) {
            html += '<div class="empty-state"><i class="fas fa-check-double"></i><p>No accounts with transactions found for this tenant.</p></div>';
        }

        content.innerHTML = html;
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function formatNum(n) { return parseFloat(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 3 }); }
    function escapeHtml(s) { if (!s) return ''; var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }
    function escapeAttr(s) { return String(s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
    </script>
</body>
</html>
