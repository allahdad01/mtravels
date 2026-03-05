<?php
	require_once dirname(__FILE__) . '/../includes/session_check.php';
	require_once dirname(__FILE__) . '/../includes/db.php';

	// Determine current tenant id and branch id
	$currentTenantId = 0;
	$currentBranchId = 0;
	if (isset($_SESSION['user_id'])) {
		$uStmt = secure_query($pdo, 'SELECT tenant_id, branch_id FROM users WHERE id = ?', [$_SESSION['user_id']]);
		$u = $uStmt ? $uStmt->fetch(PDO::FETCH_ASSOC) : null;
		$currentTenantId = $u ? (int)$u['tenant_id'] : 0;
		$currentBranchId = $u ? (int)$u['branch_id'] : 0;
	}

	// Handle create/update peering
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
		$tenant_id = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
		$peer_tenant_id = isset($_POST['peer_tenant_id']) ? (int)$_POST['peer_tenant_id'] : 0;
		$status = isset($_POST['status']) ? $_POST['status'] : 'pending';
		if ($tenant_id > 0 && $peer_tenant_id > 0 && $tenant_id !== $peer_tenant_id && in_array($status, ['approved','pending','blocked'], true)) {
			$sql = 'INSERT INTO tenant_peering (tenant_id, peer_tenant_id, status, branch_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)';
			secure_query($pdo, $sql, [$tenant_id, $peer_tenant_id, $status, $currentBranchId]);
		}
		header('Location: tenant_peering.php');
		exit;
	}

	// Handle status change
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_status') {
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$newStatus = isset($_POST['status']) ? $_POST['status'] : '';
		if ($id > 0 && in_array($newStatus, ['approved','pending','blocked'], true)) {
			$ownStmt = secure_query($pdo, 'SELECT peer_tenant_id FROM tenant_peering WHERE id = ?', [$id]);
			$row = $ownStmt ? $ownStmt->fetch(PDO::FETCH_ASSOC) : null;
			if ($row && (int)$row['peer_tenant_id'] === $currentTenantId) {
				secure_query($pdo, 'UPDATE tenant_peering SET status = ? WHERE id = ?', [$newStatus, $id]);
			}
		}
		header('Location: tenant_peering.php');
		exit;
	}

	// Handle delete peering
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		if ($id > 0) {
			secure_query($pdo, 'DELETE FROM tenant_peering WHERE id = ?', [$id]);
		}
		header('Location: tenant_peering.php');
		exit;
	}

	// Load current tenant and other tenants
	$curTenantStmt = secure_query($pdo, "SELECT id, name, identifier, status FROM tenants WHERE id = ? AND status <> 'deleted'", [$currentTenantId]);
	$currentTenant = $curTenantStmt ? $curTenantStmt->fetch(PDO::FETCH_ASSOC) : null;
	$tenantsStmt = secure_query($pdo, "SELECT id, name, identifier, status FROM tenants WHERE status <> 'deleted' AND id <> ? ORDER BY name ASC", [$currentTenantId]);
	$tenants = $tenantsStmt ? $tenantsStmt->fetchAll() : [];

	// Load peerings
	$peeringsSql = 'SELECT tp.id, tp.tenant_id, tp.peer_tenant_id, tp.status, tp.branch_id,
		(SELECT name FROM tenants t WHERE t.id = tp.tenant_id) AS tenant_name,
		(SELECT name FROM tenants t2 WHERE t2.id = tp.peer_tenant_id) AS peer_name,
		(SELECT name FROM branches b WHERE b.id = tp.branch_id) AS branch_name
		FROM tenant_peering tp
		WHERE (tp.tenant_id = ? OR tp.peer_tenant_id = ?)
		ORDER BY tp.id DESC';
	$peeringsStmt = secure_query($pdo, $peeringsSql, [$currentTenantId, $currentTenantId]);
	$peerings = $peeringsStmt ? $peeringsStmt->fetchAll() : [];

	// Get all branches for current tenant
	$branchesStmt = secure_query($pdo, 'SELECT id, name FROM branches WHERE tenant_id = ? AND status = "active" ORDER BY name', [$currentTenantId]);
	$myBranches = $branchesStmt ? $branchesStmt->fetchAll() : [];

	// Resolve current branch name
	$currentBranchName = 'N/A';
	foreach ($myBranches as $branch) {
		if ((int)$branch['id'] === $currentBranchId) {
			$currentBranchName = $branch['name'];
			break;
		}
	}
?>
<?php include '../includes/header.php'; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap');

:root {
    --brand-start: #4099ff;
    --brand-end: #2ed8b6;
    --brand-mid: #38b2e8;
    --surface: #f7f9fc;
    --surface-raised: #ffffff;
    --border: #e4eaf3;
    --border-strong: #ccd6e8;
    --text-primary: #1a2540;
    --text-secondary: #5a6a85;
    --text-muted: #96a4b8;
    --approved: #10b981;
    --approved-bg: #ecfdf5;
    --pending: #f59e0b;
    --pending-bg: #fffbeb;
    --blocked: #ef4444;
    --blocked-bg: #fef2f2;
    --radius: 12px;
    --radius-sm: 8px;
    --shadow-sm: 0 1px 3px rgba(30,50,100,.07), 0 1px 2px rgba(30,50,100,.04);
    --shadow: 0 4px 16px rgba(30,50,100,.08), 0 1px 4px rgba(30,50,100,.04);
    --shadow-lg: 0 12px 40px rgba(30,50,100,.12), 0 4px 12px rgba(30,50,100,.06);
}

.pcoded-main-container {
    font-family: 'DM Sans', sans-serif;
    background: var(--surface);
}

/* ── Page header ─────────────────────────────── */
.tp-page-header {
    background: linear-gradient(135deg, var(--brand-start) 0%, var(--brand-end) 100%);
    border-radius: var(--radius);
    padding: 28px 32px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow-lg);
    position: relative;
    overflow: hidden;
}

.tp-page-header::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
    pointer-events: none;
}

.tp-page-header::after {
    content: '';
    position: absolute;
    bottom: -60px; right: 80px;
    width: 130px; height: 130px;
    border-radius: 50%;
    background: rgba(255,255,255,.05);
    pointer-events: none;
}

.tp-page-header h4 {
    color: #fff;
    font-weight: 600;
    font-size: 1.3rem;
    margin: 0 0 4px;
    letter-spacing: -.2px;
}

.tp-page-header p {
    color: rgba(255,255,255,.75);
    margin: 0;
    font-size: .875rem;
}

.tp-header-icon {
    width: 48px; height: 48px;
    border-radius: 14px;
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
    margin-right: 18px;
    flex-shrink: 0;
}

.tp-header-icon i { font-size: 22px; color: #fff; }

/* ── Context strip ───────────────────────────── */
.tp-context-strip {
    display: flex;
    gap: 14px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.tp-ctx-chip {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--surface-raised);
    border: 1px solid var(--border);
    border-radius: 40px;
    padding: 8px 16px 8px 10px;
    box-shadow: var(--shadow-sm);
}

.tp-ctx-chip-icon {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--brand-start), var(--brand-end));
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}

.tp-ctx-chip-icon i { font-size: 13px; color: #fff; }

.tp-ctx-chip-label {
    font-size: .72rem;
    font-weight: 500;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    line-height: 1;
}

.tp-ctx-chip-value {
    font-size: .88rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1;
    margin-top: 2px;
    font-family: 'DM Mono', monospace;
}

/* ── Cards ───────────────────────────────────── */
.tp-card {
    background: var(--surface-raised);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
    overflow: hidden;
    animation: fadeUp .35s ease both;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0); }
}

.tp-card:nth-child(2) { animation-delay: .07s; }
.tp-card:nth-child(3) { animation-delay: .14s; }

.tp-card-head {
    padding: 18px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.tp-card-head-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--brand-start), var(--brand-end));
    flex-shrink: 0;
}

.tp-card-head h6 {
    font-size: .9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.tp-card-body { padding: 24px; }

/* ── Info callout ────────────────────────────── */
.tp-callout {
    display: flex;
    gap: 12px;
    background: #eef6ff;
    border: 1px solid #bfdbfe;
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-bottom: 22px;
}

.tp-callout i { color: #3b82f6; font-size: 16px; flex-shrink: 0; margin-top: 1px; }

.tp-callout p {
    font-size: .82rem;
    color: #1e40af;
    margin: 0;
    line-height: 1.5;
}

/* ── Form ────────────────────────────────────── */
.tp-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

@media (max-width: 600px) {
    .tp-form-row { grid-template-columns: 1fr; }
}

.tp-field label {
    display: block;
    font-size: .78rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 6px;
}

.tp-field .form-control,
.tp-field select.form-control {
    border: 1.5px solid var(--border-strong);
    border-radius: var(--radius-sm);
    padding: 9px 14px;
    font-size: .9rem;
    font-family: 'DM Sans', sans-serif;
    color: var(--text-primary);
    background: #fff;
    transition: border-color .2s, box-shadow .2s;
    height: auto;
}

.tp-field .form-control:focus,
.tp-field select.form-control:focus {
    border-color: var(--brand-start);
    box-shadow: 0 0 0 3px rgba(64,153,255,.15);
    outline: none;
}

.tp-field .form-control[disabled] {
    background: var(--surface);
    color: var(--text-secondary);
    cursor: default;
}

.tp-form-actions {
    display: flex;
    gap: 10px;
    padding-top: 4px;
}

.btn-tp-primary {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 20px;
    border-radius: var(--radius-sm);
    border: none;
    background: linear-gradient(135deg, var(--brand-start) 0%, var(--brand-end) 100%);
    color: #fff;
    font-size: .875rem;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: opacity .2s, transform .15s, box-shadow .2s;
    box-shadow: 0 2px 10px rgba(64,153,255,.35);
}

.btn-tp-primary:hover {
    opacity: .92;
    transform: translateY(-1px);
    box-shadow: 0 4px 18px rgba(64,153,255,.4);
}

.btn-tp-secondary {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: var(--radius-sm);
    border: 1.5px solid var(--border-strong);
    background: #fff;
    color: var(--text-secondary);
    font-size: .875rem;
    font-weight: 500;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: border-color .2s, color .2s;
}

.btn-tp-secondary:hover {
    border-color: var(--brand-start);
    color: var(--brand-start);
}

/* ── Table ───────────────────────────────────── */
.tp-table-wrap { overflow-x: auto; }

.tp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .875rem;
}

.tp-table thead tr {
    background: var(--surface);
    border-bottom: 2px solid var(--border);
}

.tp-table thead th {
    padding: 11px 16px;
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--text-muted);
    white-space: nowrap;
    text-align: left;
}

.tp-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
}

.tp-table tbody tr:last-child { border-bottom: none; }
.tp-table tbody tr:hover { background: #f8faff; }

.tp-table tbody td {
    padding: 13px 16px;
    color: var(--text-primary);
    vertical-align: middle;
}

.tp-tenant-cell { font-weight: 600; }

.tp-id-badge {
    display: inline-block;
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text-muted);
    border-radius: 6px;
    padding: 2px 8px;
    font-family: 'DM Mono', monospace;
    font-size: .78rem;
}

/* ── Status badges ───────────────────────────── */
.tp-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 600;
    letter-spacing: .2px;
}

.tp-status::before {
    content: '';
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.tp-status.approved  { background: var(--approved-bg); color: var(--approved);  }
.tp-status.approved::before  { background: var(--approved); }
.tp-status.pending   { background: var(--pending-bg);  color: var(--pending);   }
.tp-status.pending::before   { background: var(--pending); }
.tp-status.blocked   { background: var(--blocked-bg);  color: var(--blocked);   }
.tp-status.blocked::before   { background: var(--blocked); }

/* ── Action buttons in table ─────────────────── */
.tp-actions { display: flex; align-items: center; gap: 6px; }

.tp-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px; height: 32px;
    border-radius: var(--radius-sm);
    border: 1.5px solid transparent;
    background: transparent;
    cursor: pointer;
    transition: background .15s, border-color .15s, transform .1s;
    padding: 0;
}

.tp-action-btn:hover { transform: translateY(-1px); }

.tp-action-btn.approve {
    border-color: #bbf7d0;
    color: var(--approved);
}
.tp-action-btn.approve:hover { background: var(--approved-bg); border-color: var(--approved); }

.tp-action-btn.block,
.tp-action-btn.delete {
    border-color: #fecaca;
    color: var(--blocked);
}
.tp-action-btn.block:hover,
.tp-action-btn.delete:hover { background: var(--blocked-bg); border-color: var(--blocked); }

.tp-action-btn i { font-size: 14px; }

/* ── Empty state ─────────────────────────────── */
.tp-empty {
    text-align: center;
    padding: 56px 24px;
}

.tp-empty-icon {
    width: 64px; height: 64px;
    border-radius: 18px;
    background: linear-gradient(135deg, #f0f7ff, #e8f8f5);
    border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}

.tp-empty-icon i { font-size: 28px; color: var(--brand-mid); }

.tp-empty h6 {
    font-size: .95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 6px;
}

.tp-empty p {
    font-size: .84rem;
    color: var(--text-muted);
    margin: 0;
    max-width: 300px;
    margin: 0 auto;
}

/* ── Direction indicator ─────────────────────── */
.tp-direction {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: .8rem;
    color: var(--text-muted);
}

.tp-direction .arrow {
    width: 18px; height: 1px;
    background: var(--border-strong);
    position: relative;
}

.tp-direction .arrow::after {
    content: '';
    position: absolute;
    right: 0; top: -3px;
    border: 4px solid transparent;
    border-left-color: var(--border-strong);
}

.tp-you-tag {
    display: inline-block;
    font-size: .68rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--brand-start), var(--brand-end));
    color: #fff;
    border-radius: 4px;
    padding: 1px 5px;
    margin-left: 5px;
    vertical-align: middle;
    letter-spacing: .3px;
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">

                        <!-- Page Header -->
                        <div class="tp-page-header">
                            <div style="display:flex; align-items:center;">
                                <div class="tp-header-icon">
                                    <i class="feather icon-link"></i>
                                </div>
                                <div>
                                    <h4>Tenant Peering</h4>
                                    <p>Manage cross-tenant communication permissions</p>
                                </div>
                            </div>
                            <div style="text-align:right; color:rgba(255,255,255,.7); font-size:.8rem; font-family:'DM Mono',monospace; display:none; display:block;">
                                <?= htmlspecialchars($currentTenant['identifier'] ?? '') ?>
                            </div>
                        </div>

                        <!-- Context strip: who you are -->
                        <div class="tp-context-strip">
                            <div class="tp-ctx-chip">
                                <div class="tp-ctx-chip-icon"><i class="feather icon-briefcase"></i></div>
                                <div>
                                    <div class="tp-ctx-chip-label">Your Tenant</div>
                                    <div class="tp-ctx-chip-value"><?= htmlspecialchars($currentTenant['name'] ?? ('#'.$currentTenantId)) ?></div>
                                </div>
                            </div>
                            <div class="tp-ctx-chip">
                                <div class="tp-ctx-chip-icon"><i class="feather icon-git-branch"></i></div>
                                <div>
                                    <div class="tp-ctx-chip-label">Your Branch</div>
                                    <div class="tp-ctx-chip-value"><?= htmlspecialchars($currentBranchName) ?></div>
                                </div>
                            </div>
                            <div class="tp-ctx-chip">
                                <div class="tp-ctx-chip-icon"><i class="feather icon-link-2"></i></div>
                                <div>
                                    <div class="tp-ctx-chip-label">Active Peerings</div>
                                    <div class="tp-ctx-chip-value"><?= count(array_filter($peerings, fn($p) => $p['status'] === 'approved')) ?></div>
                                </div>
                            </div>
                            <div class="tp-ctx-chip">
                                <div class="tp-ctx-chip-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);"><i class="feather icon-clock"></i></div>
                                <div>
                                    <div class="tp-ctx-chip-label">Pending</div>
                                    <div class="tp-ctx-chip-value"><?= count(array_filter($peerings, fn($p) => $p['status'] === 'pending')) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Request Peering Form -->
                        <div class="tp-card">
                            <div class="tp-card-head">
                                <div class="tp-card-head-dot"></div>
                                <h6>New Peering Request</h6>
                            </div>
                            <div class="tp-card-body">
                                <div class="tp-callout">
                                    <i class="feather icon-info"></i>
                                    <p>Peering is configured at the <strong>tenant level</strong> — once approved, all branches of both tenants can exchange messages. For branch-specific controls, contact support.</p>
                                </div>
                                <form method="post">
                                    <input type="hidden" name="action" value="add" />
                                    <input type="hidden" name="tenant_id" value="<?= (int)$currentTenantId ?>" />
                                    <input type="hidden" name="status" value="pending" />
                                    <div class="tp-form-row">
                                        <div class="tp-field">
                                            <label>Select Peer Tenant</label>
                                            <select name="peer_tenant_id" class="form-control" required>
                                                <option value="">— Choose a tenant —</option>
                                                <?php foreach ($tenants as $t): ?>
                                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="tp-field">
                                            <label>Request Status</label>
                                            <input type="text" class="form-control" value="Will be sent as: Pending" disabled />
                                        </div>
                                    </div>
                                    <div class="tp-form-actions">
                                        <button type="submit" class="btn-tp-primary">
                                            <i class="feather icon-send" style="font-size:13px;"></i>
                                            Send Request
                                        </button>
                                        <button type="reset" class="btn-tp-secondary">
                                            <i class="feather icon-rotate-ccw" style="font-size:13px;"></i>
                                            Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Existing Peerings Table -->
                        <div class="tp-card">
                            <div class="tp-card-head">
                                <div class="tp-card-head-dot"></div>
                                <h6>Existing Peerings</h6>
                            </div>
                            <div class="tp-table-wrap">
                                <?php if (empty($peerings)): ?>
                                <div class="tp-empty">
                                    <div class="tp-empty-icon">
                                        <i class="feather icon-link"></i>
                                    </div>
                                    <h6>No peerings yet</h6>
                                    <p>Send a peering request above to start exchanging messages with another tenant.</p>
                                </div>
                                <?php else: ?>
                                <table class="tp-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Branch</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($peerings as $p): ?>
                                        <?php $isReceiver = (int)$p['peer_tenant_id'] === $currentTenantId; ?>
                                        <tr>
                                            <td><span class="tp-id-badge"><?= (int)$p['id'] ?></span></td>
                                            <td class="tp-tenant-cell">
                                                <?= htmlspecialchars($p['tenant_name'] ?: ('#'.$p['tenant_id'])) ?>
                                                <?php if ((int)$p['tenant_id'] === $currentTenantId): ?>
                                                    <span class="tp-you-tag">you</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="tp-tenant-cell">
                                                <?= htmlspecialchars($p['peer_name'] ?: ('#'.$p['peer_tenant_id'])) ?>
                                                <?php if ($isReceiver): ?>
                                                    <span class="tp-you-tag">you</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="color:var(--text-muted); font-size:.82rem;">
                                                <?= htmlspecialchars($p['branch_name'] ?? '—') ?>
                                            </td>
                                            <td>
                                                <?php $st = $p['status']; ?>
                                                <span class="tp-status <?= htmlspecialchars($st) ?>">
                                                    <?= ucfirst(htmlspecialchars($st)) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="tp-actions">
                                                    <?php if ($isReceiver && $p['status'] === 'pending'): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="set_status" />
                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                        <input type="hidden" name="status" value="approved" />
                                                        <button type="submit" class="tp-action-btn approve" title="Approve peering">
                                                            <i class="feather icon-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="set_status" />
                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                        <input type="hidden" name="status" value="blocked" />
                                                        <button type="submit" class="tp-action-btn block" title="Block peering">
                                                            <i class="feather icon-slash"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <form method="post" onsubmit="return confirm('Delete this peering permanently?');">
                                                        <input type="hidden" name="action" value="delete" />
                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                        <button type="submit" class="tp-action-btn delete" title="Delete peering">
                                                            <i class="feather icon-trash-2"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /.page-wrapper -->
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>