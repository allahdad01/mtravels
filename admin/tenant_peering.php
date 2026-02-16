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
		// New requests default to pending
		$status = isset($_POST['status']) ? $_POST['status'] : 'pending';
		if ($tenant_id > 0 && $peer_tenant_id > 0 && $tenant_id !== $peer_tenant_id && in_array($status, ['approved','pending','blocked'], true)) {
			$sql = 'INSERT INTO tenant_peering (tenant_id, peer_tenant_id, status, branch_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)';
			secure_query($pdo, $sql, [$tenant_id, $peer_tenant_id, $status, $currentBranchId]);
		}
		header('Location: tenant_peering.php');
		exit;
	}

	// Handle status change (approve/block/pending)
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_status') {
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$newStatus = isset($_POST['status']) ? $_POST['status'] : '';
		if ($id > 0 && in_array($newStatus, ['approved','pending','blocked'], true)) {
			// Ensure current tenant is the receiver for security when approving/blocking
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

	// Load current tenant (display only) and other tenants for peering target
	$curTenantStmt = secure_query($pdo, "SELECT id, name, identifier, status FROM tenants WHERE id = ? AND status <> 'deleted'", [$currentTenantId]);
	$currentTenant = $curTenantStmt ? $curTenantStmt->fetch(PDO::FETCH_ASSOC) : null;
	$tenantsStmt = secure_query($pdo, "SELECT id, name, identifier, status FROM tenants WHERE status <> 'deleted' AND id <> ? ORDER BY name ASC", [$currentTenantId]);
	$tenants = $tenantsStmt ? $tenantsStmt->fetchAll() : [];

	// Load peerings with names (showing all peerings involving my tenant)
	// Note: Current implementation is tenant-wide, branch_id is for informational purposes
	$peeringsSql = 'SELECT tp.id, tp.tenant_id, tp.peer_tenant_id, tp.status, tp.branch_id,
		(SELECT name FROM tenants t WHERE t.id = tp.tenant_id) AS tenant_name,
		(SELECT name FROM tenants t2 WHERE t2.id = tp.peer_tenant_id) AS peer_name,
		(SELECT name FROM branches b WHERE b.id = tp.branch_id) AS branch_name
		FROM tenant_peering tp
		WHERE (tp.tenant_id = ? OR tp.peer_tenant_id = ?)
		ORDER BY tp.id DESC';
	$peeringsStmt = secure_query($pdo, $peeringsSql, [$currentTenantId, $currentTenantId]);
	$peerings = $peeringsStmt ? $peeringsStmt->fetchAll() : [];
	
	// Get all branches for current tenant for the form
	$branchesStmt = secure_query($pdo, 'SELECT id, name FROM branches WHERE tenant_id = ? AND status = "active" ORDER BY name', [$currentTenantId]);
	$myBranches = $branchesStmt ? $branchesStmt->fetchAll() : [];
?>
<?php include '../includes/header.php'; ?>
<style>
/* Apply gradient background to card headers matching the sidebar */
.card-header {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
    color: #ffffff !important;
    border-bottom: none !important;
}

.card-header h5 {
    color: #ffffff !important;
    margin-bottom: 0 !important;
}

.card-header .card-header-right {
    color: #ffffff !important;
}

.card-header .card-header-right .btn {
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
}

.card-header .card-header-right .btn:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
}
</style>
<div class="pcoded-main-container">
	<div class="pcoded-wrapper">
		<div class="pcoded-content">
			<div class="pcoded-inner-content">
				<div class="main-body">
					<div class="page-wrapper">
						<div class="row">
							<div class="col-sm-12">
								<div class="card">
									<div class="card-header">
										<h5><i class="feather icon-users mr-2"></i>Tenant Peering</h5>
									</div>
									<div class="card-body">
										<p class="text-muted mb-4">Manage which tenants can exchange messages with your organization.</p>

                                        <div class="card mb-4">
                                            <div class="card-header">Request Peering</div>
                                            <div class="card-body">
                                                <div class="alert alert-warning" role="alert">
                                                    <i class="feather icon-alert-circle mr-2"></i>
                                                    <strong>Note:</strong> Tenant peering is currently configured at the <strong>tenant level</strong>, meaning if approved, <strong>all branches</strong> of your tenant can communicate with all branches of the peer tenant. For branch-specific peering control, please contact support.
                                                </div>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="add" />
                                                    <div class="row g-3">
                                                        <div class="col-md-3">
                                                            <label class="form-label">Your Tenant</label>
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentTenant['name'] ?? ('#'.$currentTenantId)) ?>" disabled />
                                                            <input type="hidden" name="tenant_id" value="<?= (int)$currentTenantId ?>" />
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Your Branch (for reference)</label>
                                                            <input type="text" class="form-control" value="<?php 
                                                                $branchName = 'N/A';
                                                                foreach ($myBranches as $branch) {
                                                                    if ((int)$branch['id'] === $currentBranchId) {
                                                                        $branchName = $branch['name'];
                                                                        break;
                                                                    }
                                                                }
                                                                echo htmlspecialchars($branchName);
                                                            ?>" disabled />
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Peer Tenant</label>
                                                            <select name="peer_tenant_id" class="form-control" required>
                                                                <option value="">Select peer</option>
                                                                <?php foreach ($tenants as $t): ?>
                                                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Status</label>
                                                            <input type="text" class="form-control" value="pending" disabled />
                                                            <input type="hidden" name="status" value="pending" />
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 d-flex gap-2">
                                                        <button type="submit" class="btn btn-primary">Send Peering Request</button>
                                                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="card">
                                            <div class="card-header">Existing Peerings</div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-striped mb-0 align-middle">
                                                        <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Tenant</th>
                                                                <th>Branch</th>
                                                                <th>Peer Tenant</th>
                                                                <th>Status</th>
                                                                <th style="width:160px;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($peerings)): ?>
                                                            <tr><td colspan="6" class="text-center p-3">No peerings</td></tr>
                                                            <?php else: foreach ($peerings as $p): ?>
                                                            <tr>
                                                                <td><?= (int)$p['id'] ?></td>
                                                                <td><?= htmlspecialchars($p['tenant_name'] ?: ('#'.$p['tenant_id'])) ?></td>
                                                                <td><small class="text-muted"><?= htmlspecialchars($p['branch_name'] ?? 'N/A') ?></small></td>
                                                                <td><?= htmlspecialchars($p['peer_name'] ?: ('#'.$p['peer_tenant_id'])) ?></td>
                                                                <td>
                                                                    <?php $st = htmlspecialchars($p['status']); $cls = $st === 'approved' ? 'bg-success' : ($st === 'pending' ? 'bg-warning text-dark' : 'bg-danger'); ?>
                                                                    <span class="badge-status <?= $cls ?>"><?= $st ?></span>
                                                                </td>
                                                                <td>
                                                                    <?php if ((int)$p['peer_tenant_id'] === $currentTenantId && $p['status'] === 'pending'): ?>
                                                                    <form method="post" style="display:inline-block; margin-right:3px;">
                                                                        <input type="hidden" name="action" value="set_status" />
                                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                                        <input type="hidden" name="status" value="approved" />
                                                                        <button class="btn btn-sm btn-success" title="Approve this peering request">
                                                                            <i class="feather icon-check"></i>
                                                                        </button>
                                                                    </form>
                                                                    <form method="post" style="display:inline-block; margin-right:3px;">
                                                                        <input type="hidden" name="action" value="set_status" />
                                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                                        <input type="hidden" name="status" value="blocked" />
                                                                        <button class="btn btn-sm btn-outline-danger" title="Block this peering request">
                                                                            <i class="feather icon-x"></i>
                                                                        </button>
                                                                    </form>
                                                                    <?php endif; ?>
                                                                    <form method="post" onsubmit="return confirm('Delete this peering?');" style="display:inline-block;">
                                                                        <input type="hidden" name="action" value="delete" />
                                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                                        <button class="btn btn-sm btn-outline-danger" title="Delete this peering">
                                                                            <i class="feather icon-trash-2"></i>
                                                                        </button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>    
                            </div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>



    
    <!-- Custom scripts -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>

