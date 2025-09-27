<?php
	require_once dirname(__FILE__) . '/../includes/session_check.php';
	require_once dirname(__FILE__) . '/../includes/db.php';

	// Determine current tenant id
	$currentTenantId = 0;
	if (isset($_SESSION['user_id'])) {
		$uStmt = secure_query($pdo, 'SELECT tenant_id FROM users WHERE id = ?', [$_SESSION['user_id']]);
		$u = $uStmt ? $uStmt->fetch(PDO::FETCH_ASSOC) : null;
		$currentTenantId = $u ? (int)$u['tenant_id'] : 0;
	}


	// Handle create/update peering
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
		$tenant_id = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : 0;
		$peer_tenant_id = isset($_POST['peer_tenant_id']) ? (int)$_POST['peer_tenant_id'] : 0;
		// New requests default to pending
		$status = isset($_POST['status']) ? $_POST['status'] : 'pending';
		if ($tenant_id > 0 && $peer_tenant_id > 0 && $tenant_id !== $peer_tenant_id && in_array($status, ['approved','pending','blocked'], true)) {
			$sql = 'INSERT INTO tenant_peering (tenant_id, peer_tenant_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)';
			secure_query($pdo, $sql, [$tenant_id, $peer_tenant_id, $status]);
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

	// Load peerings with names (only those involving my tenant)
	$peeringsSql = 'SELECT tp.id, tp.tenant_id, tp.peer_tenant_id, tp.status,
		(SELECT name FROM tenants t WHERE t.id = tp.tenant_id) AS tenant_name,
		(SELECT name FROM tenants t2 WHERE t2.id = tp.peer_tenant_id) AS peer_name
		FROM tenant_peering tp
		WHERE tp.tenant_id = ? OR tp.peer_tenant_id = ?
		ORDER BY tp.id DESC';
	$peeringsStmt = secure_query($pdo, $peeringsSql, [$currentTenantId, $currentTenantId]);
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
										<h5><i class="feather icon-users mr-2"></i>Tenant Peering</h5>
									</div>
									<div class="card-body">
										<p class="text-muted mb-4">Manage which tenants can exchange messages with your organization.</p>

                                        <div class="card mb-4">
                                            <div class="card-header">Request Peering</div>
                                            <div class="card-body">
                                                <form method="post">
                                                    <input type="hidden" name="action" value="add" />
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <label class="form-label">Your Tenant</label>
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($currentTenant['name'] ?? ('#'.$currentTenantId)) ?>" disabled />
                                                            <input type="hidden" name="tenant_id" value="<?= (int)$currentTenantId ?>" />
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Peer Tenant</label>
                                                            <select name="peer_tenant_id" class="form-control" required>
                                                                <option value="">Select peer</option>
                                                                <?php foreach ($tenants as $t): ?>
                                                                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Status</label>
                                                            <input type="text" class="form-control" value="pending" disabled />
                                                            <input type="hidden" name="status" value="pending" />
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 d-flex gap-2">
                                                        <button type="submit" class="btn btn-primary">Save Peering</button>
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
                                                                <th>Peer Tenant</th>
                                                                <th>Status</th>
                                                                <th style="width:140px;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (empty($peerings)): ?>
                                                            <tr><td colspan="5" class="text-center p-3">No peerings</td></tr>
                                                            <?php else: foreach ($peerings as $p): ?>
                                                            <tr>
                                                                <td><?= (int)$p['id'] ?></td>
                                                                <td><?= htmlspecialchars($p['tenant_name'] ?: ('#'.$p['tenant_id'])) ?></td>
                                                                <td><?= htmlspecialchars($p['peer_name'] ?: ('#'.$p['peer_tenant_id'])) ?></td>
                                                                <td>
                                                                    <?php $st = htmlspecialchars($p['status']); $cls = $st === 'approved' ? 'bg-success' : ($st === 'pending' ? 'bg-warning text-dark' : 'bg-danger'); ?>
                                                                    <span class="badge badge-status <?= $cls ?>"><?= $st ?></span>
                                                                </td>
                                                                <td>
                                                                    <?php if ((int)$p['peer_tenant_id'] === $currentTenantId && $p['status'] === 'pending'): ?>
                                                                    <form method="post" style="display:inline-block; margin-right:6px;">
                                                                        <input type="hidden" name="action" value="set_status" />
                                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                                        <input type="hidden" name="status" value="approved" />
                                                                        <button class="btn btn-sm btn-success">Approve</button>
                                                                    </form>
                                                                    <form method="post" style="display:inline-block; margin-right:6px;">
                                                                        <input type="hidden" name="action" value="set_status" />
                                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                                        <input type="hidden" name="status" value="blocked" />
                                                                        <button class="btn btn-sm btn-outline-danger">Block</button>
                                                                    </form>
                                                                    <?php endif; ?>
                                                                    <form method="post" onsubmit="return confirm('Delete this peering?');" style="display:inline-block;">
                                                                        <input type="hidden" name="action" value="delete" />
                                                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>" />
                                                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
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
  <!-- Profile Modal -->
  <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="profileModalLabel">
                    <i class="feather icon-user mr-2"></i> <?= __('user_profile') ?>
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    
                    <div class="position-relative d-inline-block">
                        <img src="<?= $imagePath ?>" 
                             class="rounded-circle profile-image" 
                             alt="User Profile Image">
                        <div class="profile-status online"></div>
                    </div>
                    <h5 class="mt-3 mb-1"><?= !empty($user['name']) ? htmlspecialchars($user['name']) : 'Guest' ?></h5>
                    <p class="text-muted mb-0"><?= !empty($user['role']) ? htmlspecialchars($user['role']) : 'User' ?></p>
                </div>

                <div class="profile-info">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('email') ?></label>
                                <p class="mb-0"><?= !empty($user['email']) ? htmlspecialchars($user['email']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('phone') ?></label>
                                <p class="mb-0"><?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('join_date') ?></label>
                                <p class="mb-0"><?= !empty($user['hire_date']) ? date('M d, Y', strtotime($user['hire_date'])) : 'Not Set' ?></p>
                            </div>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <div class="info-item">
                                <label class="text-muted mb-1"><?= __('address') ?></label>
                                <p class="mb-0"><?= !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not Set' ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <h6 class="mb-3"><?= __('account_information') ?></h6>
                        <div class="activity-timeline">
                            <div class="timeline-item">
                                <i class="activity-icon fas fa-calendar-alt bg-primary"></i>
                                <div class="timeline-content">
                                    <p class="mb-0"><?= __('account_created') ?></p>
                                    <small class="text-muted"><?= !empty($user['created_at']) ? date('M d, Y H:i A', strtotime($user['created_at'])) : 'Not Available' ?></small>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"><?= __('close') ?></button>
                
            </div>
        </div>
    </div>
</div>

<style>
        .profile-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .profile-status {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #2ed8b6;
            border: 2px solid #fff;
        }

        .profile-status.online {
            background-color: #2ed8b6;
        }

        .info-item label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item p {
            font-weight: 500;
        }

        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 15px;
        }

        .activity-icon {
            position: absolute;
            left: -30px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e3f2fd;
            color: #2196f3;
            text-align: center;
            line-height: 24px;
            font-size: 12px;
        }

        .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .modal-header {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .modal-footer {
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 100px;
                height: 100px;
            }
            
            .modal-dialog {
                margin: 0.5rem;
            }
        }
        /* Updated Modal Styles */
        .modal-lg {
            max-width: 800px;
        }

        .floating-label {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .floating-label input,
        .floating-label textarea {
            height: auto;
            padding: 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }

        .floating-label label {
            position: absolute;
            top: 50%;
            left: 0.75rem;
            transform: translateY(-50%);
            pointer-events: none;
            transition: all 0.2s ease;
            color: #6c757d;
            margin: 0;
            padding: 0 0.2rem;
            background-color: #fff;
            font-size: 1rem;
        }

        .floating-label textarea ~ label {
            top: 1rem;
            transform: translateY(0);
        }

        /* Active state - when input has value or is focused */
        .floating-label input:focus ~ label,
        .floating-label input:not(:placeholder-shown) ~ label,
        .floating-label textarea:focus ~ label,
        .floating-label textarea:not(:placeholder-shown) ~ label {
            top: 0;
            transform: translateY(-50%) scale(0.85);
            background-color: #fff;
            color: #4099ff;
            z-index: 1;
        }

        .floating-label input:focus,
        .floating-label textarea:focus {
            border-color: #4099ff;
            box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
            outline: none;
        }

        /* Ensure inputs have placeholder to trigger :not(:placeholder-shown) */
        .floating-label input,
        .floating-label textarea {
            placeholder: " ";
        }

        /* Rest of the styles remain the same */
        .profile-upload-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: rgba(64, 153, 255, 0.9);
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            transform: scale(1.1);
            background: rgba(64, 153, 255, 1);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }

            .profile-upload-preview {
                width: 120px;
                height: 120px;
            }

            .modal-body {
                padding: 1rem !important;
            }

            .floating-label input,
            .floating-label textarea {
                padding: 0.6rem;
                font-size: 0.95rem;
            }

            .floating-label label {
                font-size: 0.95rem;
            }
        }

        @media (max-width: 576px) {
            .profile-upload-preview {
                width: 100px;
                height: 100px;
            }

            .upload-overlay {
                width: 30px;
                height: 30px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer button {
                width: 100%;
                margin: 0.25rem 0;
            }
        }
</style>

                            <!-- Settings Modal -->
                            <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <form id="updateProfileForm" enctype="multipart/form-data">
                                        <div class="modal-content shadow-lg border-0">
                                            <div class="modal-header bg-primary text-white border-0">
                                                <h5 class="modal-title">
                                                        <i class="feather icon-settings mr-2"></i><?= __('profile_settings') ?>
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="row">
                                                    <!-- Left Column - Profile Picture -->
                                                    <div class="col-md-4 text-center mb-4">
                                                        <div class="position-relative d-inline-block">
                                                            <img src="<?= $imagePath ?>" alt="Profile Picture" 
                                                                 class="profile-upload-preview rounded-circle border shadow-sm"
                                                                 id="profilePreview">
                                                            <label for="profileImage" class="upload-overlay">
                                                                <i class="feather icon-camera"></i>
                                                            </label>
                                                            <input type="file" class="d-none" id="profileImage" name="image" 
                                                                   accept="image/*" onchange="previewImage(this)">
                                                        </div>
                                                        <small class="text-muted d-block mt-2"><?= __('click_to_change_profile_picture') ?></small>
                                                    </div>

                                                    <!-- Right Column - Form Fields -->
                                                    <div class="col-md-8">
                                                        <!-- Personal Info Section -->
                                                        <div class="settings-section active" id="personalInfo">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-user mr-2"></i><?= __('personal_information') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="text" class="form-control" id="updateName" name="name" 
                                                                       value="<?= htmlspecialchars($user['name']) ?>" required>
                                                                <label for="updateName"><?= __('full_name') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="email" class="form-control" id="updateEmail" name="email" 
                                                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                                                                <label for="updateEmail"><?= __('email_address') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <input type="tel" class="form-control" id="updatePhone" name="phone" 
                                                                       value="<?= htmlspecialchars($user['phone']) ?>">
                                                                <label for="updatePhone"><?= __('phone_number') ?></label>
                                                            </div>
                                                            <div class="form-group floating-label">
                                                                <textarea class="form-control" id="updateAddress" name="address" 
                                                                          rows="3"><?= htmlspecialchars($user['address']) ?></textarea>
                                                                <label for="updateAddress"><?= __('address') ?></label>
                                                            </div>
                                                        </div>

                                                        <!-- Password Section -->
                                                        <div class="settings-section mt-4">
                                                            <h6 class="text-primary mb-3">
                                                                <i class="feather icon-lock mr-2"></i><?= __('change_password') ?>
                                                            </h6>
                                                            <div class="form-group floating-label">
                                                                <input type="password" class="form-control" id="currentPassword" 
                                                                       name="current_password">
                                                                <label for="currentPassword"><?= __('current_password') ?></label>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="newPassword" 
                                                                               name="new_password">
                                                                        <label for="newPassword"><?= __('new_password') ?></label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-group floating-label">
                                                                        <input type="password" class="form-control" id="confirmPassword" 
                                                                               name="confirm_password">
                                                                        <label for="confirmPassword"><?= __('confirm_password') ?></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 bg-light">
                                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                                    <i class="feather icon-x mr-2"></i><?= __('cancel') ?>
                                                </button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-2"></i><?= __('save_changes') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>




    <!-- Required Js -->

    
    <!-- Custom scripts -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/ripple.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>

