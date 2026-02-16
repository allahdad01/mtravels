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

	// Handle status change (approve/block/pending)
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_status') {
		$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		$newStatus = isset($_POST['status']) ? $_POST['status'] : '';
		if ($id > 0 && in_array($newStatus, ['approved','pending','blocked'], true)) {
			// Verify the peering exists and current tenant is either the creator or the receiver
			$peeringStmt = secure_query($pdo, 'SELECT id, tenant_id, peer_tenant_id FROM branch_peering WHERE id = ?', [$id]);
			$peering = $peeringStmt ? $peeringStmt->fetch(PDO::FETCH_ASSOC) : null;
			
			if ($peering) {
				$isCreator = ((int)$peering['tenant_id'] === $currentTenantId);
				$isReceiver = ((int)$peering['peer_tenant_id'] === $currentTenantId);
				
				// Creator can delete or update status if still pending
				// Receiver can approve or block pending requests
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
			$delStmt = secure_query($pdo, 'DELETE FROM branch_peering WHERE id = ? AND tenant_id = ?', [$id, $currentTenantId]);
		}
		header('Location: branch_peering.php');
		exit;
	}

	// Get current tenant info
	$curTenantStmt = secure_query($pdo, "SELECT id, name, identifier, status FROM tenants WHERE id = ? AND status <> 'deleted'", [$currentTenantId]);
	$currentTenant = $curTenantStmt ? $curTenantStmt->fetch(PDO::FETCH_ASSOC) : null;
	
	// Get other tenants for peering
	$tenantsStmt = secure_query($pdo, "SELECT id, name, identifier, status FROM tenants WHERE status <> 'deleted' AND id <> ? ORDER BY name ASC", [$currentTenantId]);
	$tenants = $tenantsStmt ? $tenantsStmt->fetchAll() : [];

	// Get all branches for current tenant
	$branchesStmt = secure_query($pdo, 'SELECT id, name FROM branches WHERE tenant_id = ? AND status = "active" ORDER BY name', [$currentTenantId]);
	$myBranches = $branchesStmt ? $branchesStmt->fetchAll() : [];

	// Get branch peerings with names
	// Show both: peerings created by this tenant AND peerings sent to this tenant for approval
	$peeringsSql = 'SELECT bp.id, bp.tenant_id, bp.branch_id, bp.peer_tenant_id, bp.peer_branch_id, bp.status,
		(SELECT name FROM tenants t WHERE t.id = bp.tenant_id) AS requester_tenant_name,
		(SELECT name FROM branches b WHERE b.id = bp.branch_id) AS branch_name,
		(SELECT name FROM tenants t WHERE t.id = bp.peer_tenant_id) AS peer_tenant_name,
		(SELECT name FROM branches b WHERE b.id = bp.peer_branch_id) AS peer_branch_name,
		CASE 
			WHEN bp.tenant_id = ? THEN "created_by_us"
			WHEN bp.peer_tenant_id = ? THEN "sent_to_us"
			ELSE "other"
		END AS request_type
		FROM branch_peering bp
		WHERE bp.tenant_id = ? OR bp.peer_tenant_id = ?
		ORDER BY bp.id DESC';
	$peeringsStmt = secure_query($pdo, $peeringsSql, [$currentTenantId, $currentTenantId, $currentTenantId, $currentTenantId]);
	$peerings = $peeringsStmt ? $peeringsStmt->fetchAll() : [];
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
										<h5><i class="feather icon-share-2 mr-2"></i>Branch Peering</h5>
									</div>
									<div class="card-body">
										<p class="text-muted mb-4">Manage branch-specific peering with other organizations. Each branch can have independent peering relationships.</p>

                                        <div class="card mb-4">
                                            <div class="card-header">Create Branch Peering Request</div>
                                            <div class="card-body">
                                                <div class="alert alert-info" role="alert">
                                                    <i class="feather icon-info mr-2"></i>
                                                    <strong>Branch-Level Peering:</strong> This allows specific branches to communicate with specific branches in other organizations. For example, your Sales branch can communicate with another organization's Support branch, while your Finance branch remains isolated.
                                                </div>
                                                <form method="post">
                                                    <input type="hidden" name="action" value="add" />
                                                    <div class="row g-3">
                                                        <div class="col-md-2">
                                                            <label class="form-label">Your Branch</label>
                                                            <select name="branch_id" class="form-control" required>
                                                                <option value="">Select branch</option>
                                                                <?php foreach ($myBranches as $b): ?>
                                                                <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Peer Organization</label>
                                                            <select name="peer_tenant_id" class="form-control" required onchange="loadPeerBranches()">
                                                                <option value="">Select organization</option>
                                                                <?php foreach ($tenants as $t): ?>
                                                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Peer Branch</label>
                                                            <select name="peer_branch_id" class="form-control" id="peer_branch_select" required>
                                                                <option value="">Select peer branch</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label">Status</label>
                                                            <input type="text" class="form-control" value="pending" disabled />
                                                            <input type="hidden" name="status" value="pending" />
                                                        </div>
                                                        <div class="col-md-2 d-flex align-items-end">
                                                            <button type="submit" class="btn btn-primary w-100">Create Request</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="card">
                                            <div class="card-header">Peering Requests Sent By Us</div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-striped mb-0 align-middle">
                                                        <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Our Branch</th>
                                                                <th>Peer Organization</th>
                                                                <th>Peer Branch</th>
                                                                <th>Status</th>
                                                                <th style="width:160px;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $sentRequests = array_filter($peerings, function($p) { return $p['request_type'] === 'created_by_us'; });
                                                            if (empty($sentRequests)): 
                                                            ?>
                                                            <tr><td colspan="6" class="text-center p-3 text-muted">No outgoing requests</td></tr>
                                                            <?php else: foreach ($sentRequests as $p): ?>
                                                            <tr>
                                                                <td><?= (int)$p['id'] ?></td>
                                                                <td><?= htmlspecialchars($p['branch_name'] ?: ('#'.$p['branch_id'])) ?></td>
                                                                <td><?= htmlspecialchars($p['peer_tenant_name'] ?: ('#'.$p['peer_tenant_id'])) ?></td>
                                                                <td><?= htmlspecialchars($p['peer_branch_name'] ?: ('#'.$p['peer_branch_id'])) ?></td>
                                                                <td>
                                                                    <?php $st = htmlspecialchars($p['status']); $cls = $st === 'approved' ? 'bg-success' : ($st === 'pending' ? 'bg-warning text-dark' : 'bg-danger'); ?>
                                                                    <span class="badge-status <?= $cls ?>"><?= $st ?></span>
                                                                </td>
                                                                <td>
                                                                    <form method="post" onsubmit="return confirm('Delete this request?');" style="display:inline-block;">
                                                                        <input type="hidden" name="action" value="delete" />
                                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                                        <button class="btn btn-sm btn-outline-danger" title="Delete this request">
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

                                        <div class="card">
                                            <div class="card-header">Peering Requests Sent To Us</div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-striped mb-0 align-middle">
                                                        <thead>
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>From Organization</th>
                                                                <th>From Branch</th>
                                                                <th>To Our Branch</th>
                                                                <th>Status</th>
                                                                <th style="width:200px;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php 
                                                            $receivedRequests = array_filter($peerings, function($p) { return $p['request_type'] === 'sent_to_us'; });
                                                            if (empty($receivedRequests)): 
                                                            ?>
                                                            <tr><td colspan="6" class="text-center p-3 text-muted">No incoming requests</td></tr>
                                                            <?php else: foreach ($receivedRequests as $p): ?>
                                                            <tr>
                                                                <td><?= (int)$p['id'] ?></td>
                                                                <td><?= htmlspecialchars($p['requester_tenant_name'] ?: ('#'.$p['tenant_id'])) ?></td>
                                                                <td><?= htmlspecialchars($p['branch_name'] ?: ('#'.$p['branch_id'])) ?></td>
                                                                <td><?= htmlspecialchars($p['peer_branch_name'] ?: ('#'.$p['peer_branch_id'])) ?></td>
                                                                <td>
                                                                    <?php $st = htmlspecialchars($p['status']); $cls = $st === 'approved' ? 'bg-success' : ($st === 'pending' ? 'bg-warning text-dark' : 'bg-danger'); ?>
                                                                    <span class="badge-status <?= $cls ?>"><?= $st ?></span>
                                                                </td>
                                                                <td>
                                                                    <?php if ($p['status'] === 'pending'): ?>
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

<script>
// Load peer branches when peer organization is selected
function loadPeerBranches() {
    const peerTenantId = document.querySelector('[name="peer_tenant_id"]').value;
    const peerBranchSelect = document.getElementById('peer_branch_select');
    
    if (!peerTenantId) {
        peerBranchSelect.innerHTML = '<option value="">Select peer branch</option>';
        return;
    }
    
    // Fetch branches for the selected peer tenant
    fetch('../api/branches.php?tenant_id=' + encodeURIComponent(peerTenantId))
        .then(response => response.json())
        .then(data => {
            peerBranchSelect.innerHTML = '<option value="">Select peer branch</option>';
            if (data.branches && Array.isArray(data.branches)) {
                data.branches.forEach(branch => {
                    const option = document.createElement('option');
                    option.value = branch.id;
                    option.textContent = branch.name;
                    peerBranchSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading branches:', error);
            peerBranchSelect.innerHTML = '<option value="">Error loading branches</option>';
        });
}
</script>

    <!-- Custom scripts -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>
