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
		$branch_id = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
		$peer_tenant_id = isset($_POST['peer_tenant_id']) ? (int)$_POST['peer_tenant_id'] : 0;
		$peer_branch_id = isset($_POST['peer_branch_id']) ? (int)$_POST['peer_branch_id'] : 0;
		$status = isset($_POST['status']) ? $_POST['status'] : 'pending';
		if ($branch_id > 0 && $peer_tenant_id > 0 && $peer_branch_id > 0 && in_array($status, ['approved','pending','blocked'], true)) {
			$sql = 'INSERT INTO branch_peering (tenant_id, branch_id, peer_tenant_id, peer_branch_id, status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)';
			secure_query($pdo, $sql, [$currentTenantId, $branch_id, $peer_tenant_id, $peer_branch_id, $status]);
		}
		header('Location: branch_peering.php');
		exit;
	}

	// Handle status change
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_status') {
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$newStatus = isset($_POST['status']) ? $_POST['status'] : '';
		if ($id > 0 && in_array($newStatus, ['approved','pending','blocked'], true)) {
			$peeringStmt = secure_query($pdo, 'SELECT id, tenant_id, peer_tenant_id FROM branch_peering WHERE id = ?', [$id]);
			$peering = $peeringStmt ? $peeringStmt->fetch(PDO::FETCH_ASSOC) : null;
			if ($peering) {
				$isCreator  = ((int)$peering['tenant_id']      === $currentTenantId);
				$isReceiver = ((int)$peering['peer_tenant_id'] === $currentTenantId);
				if ($isCreator || $isReceiver) {
					secure_query($pdo, 'UPDATE branch_peering SET status = ? WHERE id = ?', [$newStatus, $id]);
				}
			}
		}
		header('Location: branch_peering.php');
		exit;
	}

	// Handle delete peering
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		if ($id > 0) {
			secure_query($pdo, 'DELETE FROM branch_peering WHERE id = ? AND tenant_id = ?', [$id, $currentTenantId]);
		}
		header('Location: branch_peering.php');
		exit;
	}

	// Get current tenant info
	$curTenantStmt = secure_query($pdo, "SELECT id, name, identifier, status FROM tenants WHERE id = ? AND status <> 'deleted'", [$currentTenantId]);
	$currentTenant = $curTenantStmt ? $curTenantStmt->fetch(PDO::FETCH_ASSOC) : null;

	// Get other tenants
	$tenantsStmt = secure_query($pdo, "SELECT id, name, identifier, status FROM tenants WHERE status <> 'deleted' AND id <> ? ORDER BY name ASC", [$currentTenantId]);
	$tenants = $tenantsStmt ? $tenantsStmt->fetchAll() : [];

	// Get branches for current tenant
	$branchesStmt = secure_query($pdo, 'SELECT id, name FROM branches WHERE tenant_id = ? AND status = "active" ORDER BY name', [$currentTenantId]);
	$myBranches = $branchesStmt ? $branchesStmt->fetchAll() : [];

	// Resolve current branch name
	$currentBranchName = 'N/A';
	foreach ($myBranches as $b) {
		if ((int)$b['id'] === $currentBranchId) { $currentBranchName = $b['name']; break; }
	}

	// Get branch peerings
	$peeringsSql = 'SELECT bp.id, bp.tenant_id, bp.branch_id, bp.peer_tenant_id, bp.peer_branch_id, bp.status,
		(SELECT name FROM tenants t  WHERE t.id = bp.tenant_id)       AS requester_tenant_name,
		(SELECT name FROM branches b WHERE b.id = bp.branch_id)       AS branch_name,
		(SELECT name FROM tenants t  WHERE t.id = bp.peer_tenant_id)  AS peer_tenant_name,
		(SELECT name FROM branches b WHERE b.id = bp.peer_branch_id)  AS peer_branch_name,
		CASE
			WHEN bp.tenant_id      = ? THEN "created_by_us"
			WHEN bp.peer_tenant_id = ? THEN "sent_to_us"
			ELSE "other"
		END AS request_type
		FROM branch_peering bp
		WHERE bp.tenant_id = ? OR bp.peer_tenant_id = ?
		ORDER BY bp.id DESC';
	$peeringsStmt = secure_query($pdo, $peeringsSql, [$currentTenantId, $currentTenantId, $currentTenantId, $currentTenantId]);
	$peerings = $peeringsStmt ? $peeringsStmt->fetchAll() : [];

	$sentRequests     = array_values(array_filter($peerings, fn($p) => $p['request_type'] === 'created_by_us'));
	$receivedRequests = array_values(array_filter($peerings, fn($p) => $p['request_type'] === 'sent_to_us'));
	$approvedCount    = count(array_filter($peerings, fn($p) => $p['status'] === 'approved'));
	$pendingCount     = count(array_filter($peerings, fn($p) => $p['status'] === 'pending'));
?>
<?php include '../includes/header.php'; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap');

:root {
    --brand-start: #4099ff;
    --brand-end:   #2ed8b6;
    --brand-mid:   #38b2e8;
    --surface:        #f7f9fc;
    --surface-raised: #ffffff;
    --border:         #e4eaf3;
    --border-strong:  #ccd6e8;
    --text-primary:   #1a2540;
    --text-secondary: #5a6a85;
    --text-muted:     #96a4b8;
    --approved:    #10b981;  --approved-bg: #ecfdf5;
    --pending:     #f59e0b;  --pending-bg:  #fffbeb;
    --blocked:     #ef4444;  --blocked-bg:  #fef2f2;
    --radius:    12px;
    --radius-sm:  8px;
    --shadow-sm: 0 1px 3px rgba(30,50,100,.07), 0 1px 2px rgba(30,50,100,.04);
    --shadow:    0 4px 16px rgba(30,50,100,.08), 0 1px 4px rgba(30,50,100,.04);
    --shadow-lg: 0 12px 40px rgba(30,50,100,.12), 0 4px 12px rgba(30,50,100,.06);
}

.pcoded-main-container { font-family: 'DM Sans', sans-serif; background: var(--surface); }

/* ── Page header ──────────────────────────────────── */
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
    content:''; position:absolute; top:-40px; right:-40px;
    width:180px; height:180px; border-radius:50%;
    background:rgba(255,255,255,.08); pointer-events:none;
}
.tp-page-header::after {
    content:''; position:absolute; bottom:-60px; right:80px;
    width:130px; height:130px; border-radius:50%;
    background:rgba(255,255,255,.05); pointer-events:none;
}
.tp-page-header h4 { color:#fff; font-weight:600; font-size:1.3rem; margin:0 0 4px; letter-spacing:-.2px; }
.tp-page-header p  { color:rgba(255,255,255,.75); margin:0; font-size:.875rem; }

.tp-header-icon {
    width:48px; height:48px; border-radius:14px;
    background:rgba(255,255,255,.18);
    display:flex; align-items:center; justify-content:center;
    margin-right:18px; flex-shrink:0;
}
.tp-header-icon i { font-size:22px; color:#fff; }

/* ── Context strip ────────────────────────────────── */
.tp-context-strip { display:flex; gap:14px; margin-bottom:24px; flex-wrap:wrap; }

.tp-ctx-chip {
    display:flex; align-items:center; gap:10px;
    background:var(--surface-raised);
    border:1px solid var(--border);
    border-radius:40px;
    padding:8px 16px 8px 10px;
    box-shadow:var(--shadow-sm);
}
.tp-ctx-chip-icon {
    width:30px; height:30px; border-radius:50%;
    background:linear-gradient(135deg, var(--brand-start), var(--brand-end));
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.tp-ctx-chip-icon i { font-size:13px; color:#fff; }
.tp-ctx-chip-label { font-size:.72rem; font-weight:500; color:var(--text-muted); text-transform:uppercase; letter-spacing:.5px; line-height:1; }
.tp-ctx-chip-value { font-size:.88rem; font-weight:600; color:var(--text-primary); line-height:1; margin-top:2px; font-family:'DM Mono',monospace; }

/* ── Cards ────────────────────────────────────────── */
.tp-card {
    background:var(--surface-raised);
    border:1px solid var(--border);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    margin-bottom:24px;
    overflow:hidden;
    animation:fadeUp .35s ease both;
}
@keyframes fadeUp {
    from { opacity:0; transform:translateY(14px); }
    to   { opacity:1; transform:translateY(0); }
}
.tp-card:nth-child(2) { animation-delay:.07s; }
.tp-card:nth-child(3) { animation-delay:.14s; }
.tp-card:nth-child(4) { animation-delay:.21s; }

.tp-card-head {
    padding:18px 24px;
    border-bottom:1px solid var(--border);
    display:flex; align-items:center; gap:10px;
}
.tp-card-head-dot {
    width:8px; height:8px; border-radius:50%;
    background:linear-gradient(135deg, var(--brand-start), var(--brand-end));
    flex-shrink:0;
}
.tp-card-head h6 { font-size:.9rem; font-weight:600; color:var(--text-primary); margin:0; }
.tp-card-head .tp-badge-count {
    margin-left:auto;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:20px;
    padding:2px 10px;
    font-size:.75rem;
    font-weight:600;
    color:var(--text-secondary);
    font-family:'DM Mono',monospace;
}

.tp-card-body { padding:24px; }

/* ── Info callout ─────────────────────────────────── */
.tp-callout {
    display:flex; gap:12px;
    background:#eef6ff;
    border:1px solid #bfdbfe;
    border-radius:var(--radius-sm);
    padding:12px 16px;
    margin-bottom:22px;
}
.tp-callout i { color:#3b82f6; font-size:16px; flex-shrink:0; margin-top:1px; }
.tp-callout p { font-size:.82rem; color:#1e40af; margin:0; line-height:1.5; }

/* ── Form ─────────────────────────────────────────── */
.tp-form-grid {
    display:grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap:16px;
    margin-bottom:20px;
}
@media (max-width:768px) { .tp-form-grid { grid-template-columns:1fr 1fr; } }
@media (max-width:480px) { .tp-form-grid { grid-template-columns:1fr; } }

.tp-field label {
    display:block;
    font-size:.78rem; font-weight:600;
    color:var(--text-secondary);
    text-transform:uppercase; letter-spacing:.5px;
    margin-bottom:6px;
}
.tp-field .form-control,
.tp-field select.form-control {
    border:1.5px solid var(--border-strong);
    border-radius:var(--radius-sm);
    padding:9px 14px;
    font-size:.9rem;
    font-family:'DM Sans', sans-serif;
    color:var(--text-primary);
    background:#fff;
    transition:border-color .2s, box-shadow .2s;
    height:auto;
}
.tp-field .form-control:focus,
.tp-field select.form-control:focus {
    border-color:var(--brand-start);
    box-shadow:0 0 0 3px rgba(64,153,255,.15);
    outline:none;
}
.tp-field select.form-control:disabled,
.tp-field .form-control[disabled] {
    background:var(--surface); color:var(--text-secondary); cursor:default;
}

/* loading state on peer branch select */
.tp-field select.loading { opacity:.6; }

.tp-form-actions { display:flex; gap:10px; padding-top:4px; }

.btn-tp-primary {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 20px; border-radius:var(--radius-sm); border:none;
    background:linear-gradient(135deg, var(--brand-start) 0%, var(--brand-end) 100%);
    color:#fff; font-size:.875rem; font-weight:600;
    font-family:'DM Sans',sans-serif; cursor:pointer;
    transition:opacity .2s, transform .15s, box-shadow .2s;
    box-shadow:0 2px 10px rgba(64,153,255,.35);
}
.btn-tp-primary:hover { opacity:.92; transform:translateY(-1px); box-shadow:0 4px 18px rgba(64,153,255,.4); }

.btn-tp-secondary {
    display:inline-flex; align-items:center; gap:7px;
    padding:9px 18px; border-radius:var(--radius-sm);
    border:1.5px solid var(--border-strong);
    background:#fff; color:var(--text-secondary);
    font-size:.875rem; font-weight:500;
    font-family:'DM Sans',sans-serif; cursor:pointer;
    transition:border-color .2s, color .2s;
}
.btn-tp-secondary:hover { border-color:var(--brand-start); color:var(--brand-start); }

/* ── Table ────────────────────────────────────────── */
.tp-table-wrap { overflow-x:auto; }

.tp-table { width:100%; border-collapse:collapse; font-size:.875rem; }
.tp-table thead tr { background:var(--surface); border-bottom:2px solid var(--border); }
.tp-table thead th {
    padding:11px 16px;
    font-size:.72rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.6px;
    color:var(--text-muted); white-space:nowrap; text-align:left;
}
.tp-table tbody tr { border-bottom:1px solid var(--border); transition:background .15s; }
.tp-table tbody tr:last-child { border-bottom:none; }
.tp-table tbody tr:hover { background:#f8faff; }
.tp-table tbody td { padding:13px 16px; color:var(--text-primary); vertical-align:middle; }

.tp-id-badge {
    display:inline-block;
    background:var(--surface); border:1px solid var(--border);
    color:var(--text-muted); border-radius:6px;
    padding:2px 8px;
    font-family:'DM Mono',monospace; font-size:.78rem;
}

/* branch pill pair: org / branch */
.tp-branch-pair { display:flex; flex-direction:column; gap:2px; }
.tp-branch-pair .bp-org  { font-weight:600; color:var(--text-primary); font-size:.875rem; }
.tp-branch-pair .bp-name {
    display:inline-flex; align-items:center; gap:4px;
    font-size:.76rem; color:var(--text-secondary);
}
.tp-branch-pair .bp-name i { font-size:10px; color:var(--text-muted); }

/* ── Status badges ────────────────────────────────── */
.tp-status {
    display:inline-flex; align-items:center; gap:6px;
    padding:4px 10px; border-radius:20px;
    font-size:.78rem; font-weight:600; letter-spacing:.2px;
}
.tp-status::before { content:''; width:6px; height:6px; border-radius:50%; flex-shrink:0; }
.tp-status.approved { background:var(--approved-bg); color:var(--approved); }
.tp-status.approved::before { background:var(--approved); }
.tp-status.pending  { background:var(--pending-bg);  color:var(--pending);  }
.tp-status.pending::before  { background:var(--pending);  }
.tp-status.blocked  { background:var(--blocked-bg);  color:var(--blocked);  }
.tp-status.blocked::before  { background:var(--blocked);  }

/* ── Action buttons ───────────────────────────────── */
.tp-actions { display:flex; align-items:center; gap:6px; }

.tp-action-btn {
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; border-radius:var(--radius-sm);
    border:1.5px solid transparent;
    background:transparent; cursor:pointer;
    transition:background .15s, border-color .15s, transform .1s; padding:0;
}
.tp-action-btn:hover { transform:translateY(-1px); }
.tp-action-btn.approve { border-color:#bbf7d0; color:var(--approved); }
.tp-action-btn.approve:hover { background:var(--approved-bg); border-color:var(--approved); }
.tp-action-btn.block,
.tp-action-btn.delete { border-color:#fecaca; color:var(--blocked); }
.tp-action-btn.block:hover,
.tp-action-btn.delete:hover { background:var(--blocked-bg); border-color:var(--blocked); }
.tp-action-btn i { font-size:14px; }

/* ── Empty state ──────────────────────────────────── */
.tp-empty { text-align:center; padding:48px 24px; }
.tp-empty-icon {
    width:56px; height:56px; border-radius:16px;
    background:linear-gradient(135deg,#f0f7ff,#e8f8f5);
    border:1px solid var(--border);
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 14px;
}
.tp-empty-icon i { font-size:24px; color:var(--brand-mid); }
.tp-empty h6 { font-size:.9rem; font-weight:600; color:var(--text-primary); margin-bottom:4px; }
.tp-empty p  { font-size:.82rem; color:var(--text-muted); margin:0 auto; max-width:280px; }

/* ── Direction arrow between two branch pairs ─────── */
.tp-arrow-col { color:var(--text-muted); text-align:center; padding:0 4px; }
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
                                    <i class="feather icon-share-2"></i>
                                </div>
                                <div>
                                    <h4>Branch Peering</h4>
                                    <p>Fine-grained branch-to-branch communication control</p>
                                </div>
                            </div>
                        </div>

                        <!-- Context strip -->
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
                                    <div class="tp-ctx-chip-value"><?= $approvedCount ?></div>
                                </div>
                            </div>
                            <div class="tp-ctx-chip">
                                <div class="tp-ctx-chip-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24);"><i class="feather icon-clock"></i></div>
                                <div>
                                    <div class="tp-ctx-chip-label">Pending</div>
                                    <div class="tp-ctx-chip-value"><?= $pendingCount ?></div>
                                </div>
                            </div>
                            <div class="tp-ctx-chip">
                                <div class="tp-ctx-chip-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);"><i class="feather icon-arrow-up-right"></i></div>
                                <div>
                                    <div class="tp-ctx-chip-label">Sent by us</div>
                                    <div class="tp-ctx-chip-value"><?= count($sentRequests) ?></div>
                                </div>
                            </div>
                            <div class="tp-ctx-chip">
                                <div class="tp-ctx-chip-icon" style="background:linear-gradient(135deg,#ec4899,#f43f5e);"><i class="feather icon-arrow-down-left"></i></div>
                                <div>
                                    <div class="tp-ctx-chip-label">Received</div>
                                    <div class="tp-ctx-chip-value"><?= count($receivedRequests) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- New Peering Request Form -->
                        <div class="tp-card">
                            <div class="tp-card-head">
                                <div class="tp-card-head-dot"></div>
                                <h6>New Branch Peering Request</h6>
                            </div>
                            <div class="tp-card-body">
                                <div class="tp-callout">
                                    <i class="feather icon-info"></i>
                                    <p><strong>Branch-level peering</strong> lets specific branches exchange messages independently. For example, your <em>Sales</em> branch can communicate with another org's <em>Support</em> branch, while your <em>Finance</em> branch stays isolated.</p>
                                </div>
                                <form method="post" id="branchPeeringForm">
                                    <input type="hidden" name="action" value="add" />
                                    <input type="hidden" name="status" value="pending" />
                                    <div class="tp-form-grid">
                                        <div class="tp-field">
                                            <label>Your Branch</label>
                                            <select name="branch_id" class="form-control" required>
                                                <option value="">— Select your branch —</option>
                                                <?php foreach ($myBranches as $b): ?>
                                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="tp-field">
                                            <label>Peer Organization</label>
                                            <select name="peer_tenant_id" class="form-control" required id="peerTenantSelect" onchange="loadPeerBranches()">
                                                <option value="">— Select organization —</option>
                                                <?php foreach ($tenants as $t): ?>
                                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="tp-field">
                                            <label>Peer Branch</label>
                                            <select name="peer_branch_id" class="form-control" id="peer_branch_select" required>
                                                <option value="">— Select organization first —</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="tp-form-actions">
                                        <button type="submit" class="btn-tp-primary">
                                            <i class="feather icon-send" style="font-size:13px;"></i>
                                            Send Request
                                        </button>
                                        <button type="reset" class="btn-tp-secondary" onclick="resetPeerBranches()">
                                            <i class="feather icon-rotate-ccw" style="font-size:13px;"></i>
                                            Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Sent by us -->
                        <div class="tp-card">
                            <div class="tp-card-head">
                                <div class="tp-card-head-dot" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);"></div>
                                <h6>Requests Sent by Us</h6>
                                <span class="tp-badge-count"><?= count($sentRequests) ?></span>
                            </div>
                            <div class="tp-table-wrap">
                                <?php if (empty($sentRequests)): ?>
                                <div class="tp-empty">
                                    <div class="tp-empty-icon"><i class="feather icon-arrow-up-right"></i></div>
                                    <h6>No outgoing requests</h6>
                                    <p>Use the form above to initiate a branch peering request.</p>
                                </div>
                                <?php else: ?>
                                <table class="tp-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Our Branch</th>
                                            <th></th>
                                            <th>Peer Org / Branch</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sentRequests as $p): ?>
                                        <tr>
                                            <td><span class="tp-id-badge"><?= (int)$p['id'] ?></span></td>
                                            <td>
                                                <div class="tp-branch-pair">
                                                    <span class="bp-org"><?= htmlspecialchars($currentTenant['name'] ?? '') ?></span>
                                                    <span class="bp-name"><i class="feather icon-git-branch"></i><?= htmlspecialchars($p['branch_name'] ?: ('#'.$p['branch_id'])) ?></span>
                                                </div>
                                            </td>
                                            <td class="tp-arrow-col"><i class="feather icon-arrow-right" style="font-size:14px;"></i></td>
                                            <td>
                                                <div class="tp-branch-pair">
                                                    <span class="bp-org"><?= htmlspecialchars($p['peer_tenant_name'] ?: ('#'.$p['peer_tenant_id'])) ?></span>
                                                    <span class="bp-name"><i class="feather icon-git-branch"></i><?= htmlspecialchars($p['peer_branch_name'] ?: ('#'.$p['peer_branch_id'])) ?></span>
                                                </div>
                                            </td>
                                            <td><span class="tp-status <?= htmlspecialchars($p['status']) ?>"><?= ucfirst(htmlspecialchars($p['status'])) ?></span></td>
                                            <td>
                                                <div class="tp-actions">
                                                    <form method="post" onsubmit="return confirm('Delete this peering request?');">
                                                        <input type="hidden" name="action" value="delete" />
                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                        <button type="submit" class="tp-action-btn delete" title="Delete request">
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

                        <!-- Received requests -->
                        <div class="tp-card">
                            <div class="tp-card-head">
                                <div class="tp-card-head-dot" style="background:linear-gradient(135deg,#ec4899,#f43f5e);"></div>
                                <h6>Requests Received</h6>
                                <span class="tp-badge-count"><?= count($receivedRequests) ?></span>
                            </div>
                            <div class="tp-table-wrap">
                                <?php if (empty($receivedRequests)): ?>
                                <div class="tp-empty">
                                    <div class="tp-empty-icon"><i class="feather icon-arrow-down-left"></i></div>
                                    <h6>No incoming requests</h6>
                                    <p>Other organizations haven't sent you any branch peering requests yet.</p>
                                </div>
                                <?php else: ?>
                                <table class="tp-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>From Org / Branch</th>
                                            <th></th>
                                            <th>To Our Branch</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($receivedRequests as $p): ?>
                                        <tr>
                                            <td><span class="tp-id-badge"><?= (int)$p['id'] ?></span></td>
                                            <td>
                                                <div class="tp-branch-pair">
                                                    <span class="bp-org"><?= htmlspecialchars($p['requester_tenant_name'] ?: ('#'.$p['tenant_id'])) ?></span>
                                                    <span class="bp-name"><i class="feather icon-git-branch"></i><?= htmlspecialchars($p['branch_name'] ?: ('#'.$p['branch_id'])) ?></span>
                                                </div>
                                            </td>
                                            <td class="tp-arrow-col"><i class="feather icon-arrow-right" style="font-size:14px;"></i></td>
                                            <td>
                                                <div class="tp-branch-pair">
                                                    <span class="bp-org"><?= htmlspecialchars($currentTenant['name'] ?? '') ?></span>
                                                    <span class="bp-name"><i class="feather icon-git-branch"></i><?= htmlspecialchars($p['peer_branch_name'] ?: ('#'.$p['peer_branch_id'])) ?></span>
                                                </div>
                                            </td>
                                            <td><span class="tp-status <?= htmlspecialchars($p['status']) ?>"><?= ucfirst(htmlspecialchars($p['status'])) ?></span></td>
                                            <td>
                                                <div class="tp-actions">
                                                    <?php if ($p['status'] === 'pending'): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="set_status" />
                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                        <input type="hidden" name="status" value="approved" />
                                                        <button type="submit" class="tp-action-btn approve" title="Approve">
                                                            <i class="feather icon-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="set_status" />
                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                        <input type="hidden" name="status" value="blocked" />
                                                        <button type="submit" class="tp-action-btn block" title="Block">
                                                            <i class="feather icon-slash"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <form method="post" onsubmit="return confirm('Delete this peering?');">
                                                        <input type="hidden" name="action" value="delete" />
                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                        <button type="submit" class="tp-action-btn delete" title="Delete">
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

<script>
function loadPeerBranches() {
    const peerTenantId = document.getElementById('peerTenantSelect').value;
    const sel = document.getElementById('peer_branch_select');

    if (!peerTenantId) {
        sel.innerHTML = '<option value="">— Select organization first —</option>';
        return;
    }

    sel.innerHTML = '<option value="">Loading…</option>';
    sel.classList.add('loading');

    fetch('../api/branches.php?tenant_id=' + encodeURIComponent(peerTenantId))
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">— Select peer branch —</option>';
            if (data.branches && Array.isArray(data.branches)) {
                data.branches.forEach(b => {
                    const o = document.createElement('option');
                    o.value = b.id;
                    o.textContent = b.name;
                    sel.appendChild(o);
                });
            }
            if (!data.branches || data.branches.length === 0) {
                sel.innerHTML = '<option value="">No branches available</option>';
            }
        })
        .catch(() => {
            sel.innerHTML = '<option value="">Error loading branches</option>';
        })
        .finally(() => sel.classList.remove('loading'));
}

function resetPeerBranches() {
    document.getElementById('peer_branch_select').innerHTML = '<option value="">— Select organization first —</option>';
}
</script>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>