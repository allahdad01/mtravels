<?php
require_once '../includes/session_check.php';
require_once '../includes/db.php';

// Load my tenant and get available branches
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) { 
    header('Location: ../login.php'); 
    exit; 
}

$uStmt = secure_query($pdo, 'SELECT tenant_id, branch_id FROM users WHERE id = ?', [$uid]);
$u = $uStmt ? $uStmt->fetch(PDO::FETCH_ASSOC) : null;
if (!$u) { 
    die('User not found'); 
}
$tenantId = (int)$u['tenant_id'];
$userBranchId = (int)$u['branch_id'];

// Get list of all branches for this tenant
$branchesStmt = secure_query($pdo, 'SELECT id, name FROM branches WHERE tenant_id = ? AND status = "active" ORDER BY name', [$tenantId]);
$branches = $branchesStmt ? $branchesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Determine which branch we're editing (default to current user's branch)
$selectedBranch = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : $userBranchId;

// Verify the selected branch belongs to this tenant
$branchExists = false;
foreach ($branches as $branch) {
    if ((int)$branch['id'] === $selectedBranch) {
        $branchExists = true;
        break;
    }
}

if (!$branchExists && !empty($branches)) {
    $selectedBranch = (int)$branches[0]['id'];
    $branchExists = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$branchExists) {
        die('Invalid branch selected');
    }

    $max = isset($_POST['max_file_bytes']) ? max(1048576, (int)$_POST['max_file_bytes']) : 26214400;
    $pref = isset($_POST['allowed_mime_prefixes']) ? trim($_POST['allowed_mime_prefixes']) : 'image/,video/,audio/,application/pdf,text/';
    $auto = isset($_POST['default_auto_download']) ? 1 : 0;

    // Check if settings exist for this branch
    $checkStmt = secure_query($pdo, 'SELECT id FROM branch_chat_settings WHERE tenant_id = ? AND branch_id = ?', [$tenantId, $selectedBranch]);
    $exists = $checkStmt && $checkStmt->fetch();

    if ($exists) {
        // Update existing
        secure_query($pdo,
            'UPDATE branch_chat_settings 
             SET chat_max_file_bytes = ?, chat_allowed_mime_prefixes = ?, chat_default_auto_download = ? 
             WHERE tenant_id = ? AND branch_id = ?',
            [$max, $pref, $auto, $tenantId, $selectedBranch]
        );
    } else {
        // Insert new
        secure_query($pdo,
            'INSERT INTO branch_chat_settings (tenant_id, branch_id, chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download)
             VALUES (?, ?, ?, ?, ?)',
            [$tenantId, $selectedBranch, $max, $pref, $auto]
        );
    }

    header('Location: chat_settings.php?branch_id=' . $selectedBranch . '&ok=1');
    exit;
}

// Load current settings for selected branch
$sStmt = secure_query($pdo,
    'SELECT chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download 
     FROM branch_chat_settings 
     WHERE tenant_id = ? AND branch_id = ?',
    [$tenantId, $selectedBranch]
);
$s = $sStmt ? $sStmt->fetch(PDO::FETCH_ASSOC) : null;

// Fallback to tenant settings if branch settings don't exist
if (!$s) {
    $fallbackStmt = secure_query($pdo,
        'SELECT chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download 
         FROM tenants 
         WHERE id = ?',
        [$tenantId]
    );
    $s = $fallbackStmt ? $fallbackStmt->fetch(PDO::FETCH_ASSOC) : null;
}

$chatSettings = [
    'chat_max_file_bytes'        => $s['chat_max_file_bytes'] ?? 26214400,
    'chat_allowed_mime_prefixes' => $s['chat_allowed_mime_prefixes'] ?? 'image/,video/,audio/,application/pdf,text/',
    'chat_default_auto_download' => $s['chat_default_auto_download'] ?? 0,
];

// Get current branch name
$currentBranchName = null;
foreach ($branches as $branch) {
    if ((int)$branch['id'] === $selectedBranch) {
        $currentBranchName = $branch['name'];
        break;
    }
}
?>
<?php include '../includes/header.php'; ?>

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
										<h5><i class="feather icon-message-circle mr-2"></i>Chat Settings</h5>
										<p class="text-muted mb-0"><small>Configure per-branch chat settings</small></p>
									</div>
									<div class="card-body">
										<?php if (!empty($_GET['ok'])): ?>
											<div class="alert alert-success mb-3">
												<i class="feather icon-check-circle mr-2"></i>Settings saved successfully for <strong><?= htmlspecialchars($currentBranchName) ?></strong>
											</div>
										<?php endif; ?>

										<div class="alert alert-info mb-4">
											<i class="feather icon-info mr-2"></i>
											<strong>Per-Branch Settings:</strong> These settings apply only to <strong><?= htmlspecialchars($currentBranchName) ?></strong>. Each branch can have different file size limits and MIME type restrictions.
										</div>

										<!-- Branch Selector -->
										<div class="card mb-4">
											<div class="card-header bg-light">
												<h6 class="mb-0">Select Branch</h6>
											</div>
											<div class="card-body">
												<div class="row">
													<div class="col-md-6">
														<form method="get" class="form-inline">
															<label class="mr-2">Branch:</label>
															<select name="branch_id" class="form-control mr-2" onchange="this.form.submit();" style="width: auto;">
																<?php foreach ($branches as $branch): ?>
																	<option value="<?= (int)$branch['id'] ?>" <?= ((int)$branch['id'] === $selectedBranch) ? 'selected' : '' ?>>
																		<?= htmlspecialchars($branch['name']) ?>
																	</option>
																<?php endforeach; ?>
															</select>
														</form>
													</div>
												</div>
											</div>
										</div>

										<!-- Settings Form -->
										<div class="card">
											<div class="card-header bg-light">
												<h6 class="mb-0">Chat Configuration for <?= htmlspecialchars($currentBranchName) ?></h6>
											</div>
											<div class="card-body">
												<form method="post">
													<div class="row">
														<div class="col-md-6">
															<div class="form-group">
																<label><strong>Max file size (bytes)</strong></label>
																<input type="number" name="max_file_bytes" class="form-control" value="<?= (int)$chatSettings['chat_max_file_bytes'] ?>" min="1048576" step="1024" required />
																<small class="form-text text-muted">
																	Minimum: 1 MB (1,048,576 bytes)<br>
																	Current: <?= number_format((int)$chatSettings['chat_max_file_bytes'] / 1048576, 1) ?> MB<br>
																	Applies to all message content in this branch
																</small>
															</div>
														</div>
														<div class="col-md-6">
															<div class="form-group">
																<label><strong>Allowed MIME prefixes (comma-separated)</strong></label>
																<input type="text" name="allowed_mime_prefixes" class="form-control" value="<?= htmlspecialchars($chatSettings['chat_allowed_mime_prefixes']) ?>" required />
																<small class="form-text text-muted">
																	Examples: image/, video/, audio/, application/pdf, text/
																</small>
															</div>
														</div>
													</div>

													<div class="row">
														<div class="col-md-12">
															<div class="form-group form-check">
																<input class="form-check-input" type="checkbox" name="default_auto_download" id="default_auto_download" <?= ((int)$chatSettings['chat_default_auto_download']) ? 'checked' : '' ?> />
																<label class="form-check-label" for="default_auto_download">
																	Enable auto-download by default for this branch
																</label>
																<small class="form-text text-muted d-block mt-1">
																	Users can still override this setting individually
																</small>
															</div>
														</div>
													</div>

													<div class="form-group mt-4">
														<button class="btn btn-primary" type="submit">
															<i class="feather icon-save mr-2"></i>Save Settings for <?= htmlspecialchars($currentBranchName) ?>
														</button>
														<button class="btn btn-light ml-2" type="button" onclick="resetDefaults()">
															<i class="feather icon-refresh-ccw mr-2"></i>Reset to Recommended
														</button>
													</div>
												</form>
											</div>
										</div>

										<!-- Settings Summary Table -->
										<div class="card mt-4">
											<div class="card-header bg-light">
												<h6 class="mb-0">All Branch Settings Summary</h6>
											</div>
											<div class="card-body p-0">
												<div class="table-responsive">
													<table class="table table-sm table-hover mb-0">
														<thead class="thead-light">
															<tr>
																<th>Branch</th>
																<th>Max File Size</th>
																<th>Auto-Download</th>
																<th>Action</th>
															</tr>
														</thead>
														<tbody>
															<?php
																foreach ($branches as $branch) {
																	$branchId = (int)$branch['id'];
																	$branchStmt = secure_query($pdo, 'SELECT chat_max_file_bytes, chat_default_auto_download FROM branch_chat_settings WHERE tenant_id = ? AND branch_id = ?', [$tenantId, $branchId]);
																	$branchSettings = $branchStmt ? $branchStmt->fetch(PDO::FETCH_ASSOC) : null;
																	
																	$maxSize = $branchSettings ? number_format((int)$branchSettings['chat_max_file_bytes'] / 1048576, 1) : '25.0';
																	$autoDownload = $branchSettings ? ((int)$branchSettings['chat_default_auto_download'] ? 'Yes' : 'No') : 'No';
															?>
																<tr>
																	<td><strong><?= htmlspecialchars($branch['name']) ?></strong></td>
																	<td><?= $maxSize ?> MB</td>
																	<td><span class="badge <?= $autoDownload === 'Yes' ? 'badge-success' : 'badge-secondary' ?>"><?= $autoDownload ?></span></td>
																	<td>
																		<a href="?branch_id=<?= $branchId ?>" class="btn btn-sm btn-outline-primary">
																			<i class="feather icon-edit-2"></i> Edit
																		</a>
																	</td>
																</tr>
															<?php } ?>
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
<script src="../assets/js/ripple.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
function resetDefaults() {
	var max = document.querySelector('input[name="max_file_bytes"]');
	var mime = document.querySelector('input[name="allowed_mime_prefixes"]');
	var auto = document.getElementById('default_auto_download');
	if (max) max.value = 26214400;
	if (mime) mime.value = 'image/,video/,audio/,application/pdf,text/';
	if (auto) auto.checked = false;
}
</script>

<?php include '../includes/admin_footer.php'; ?>
