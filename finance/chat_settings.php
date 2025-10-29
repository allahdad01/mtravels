<?php
require_once '../includes/session_check.php';
require_once '../includes/db.php';

// Load my tenant
$uid = $_SESSION['user_id'] ?? null;
if (!$uid) { 
    header('Location: ../login.php'); 
    exit; 
}

$uStmt = secure_query($pdo, 'SELECT tenant_id FROM users WHERE id = ?', [$uid]);
$u = $uStmt ? $uStmt->fetch(PDO::FETCH_ASSOC) : null;
if (!$u) { 
    die('User not found'); 
}
$tenantId = (int)$u['tenant_id'];


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $max  = isset($_POST['max_file_bytes']) ? max(1048576, (int)$_POST['max_file_bytes']) : 26214400;
    $pref = isset($_POST['allowed_mime_prefixes']) ? trim($_POST['allowed_mime_prefixes']) : 'image/,video/,audio/,application/pdf,text/';
    $auto = isset($_POST['default_auto_download']) ? 1 : 0;

    secure_query(
        $pdo,
        'UPDATE tenants 
         SET chat_max_file_bytes = ?, chat_allowed_mime_prefixes = ?, chat_default_auto_download = ? 
         WHERE id = ?',
        [$max, $pref, $auto, $tenantId]
    );

    header('Location: chat_settings.php?ok=1');
    exit;
}

// Load current settings (or defaults)
$sStmt = secure_query(
    $pdo,
    'SELECT chat_max_file_bytes, chat_allowed_mime_prefixes, chat_default_auto_download 
     FROM tenants 
     WHERE id = ?',
    [$tenantId]
);
$s = $sStmt ? $sStmt->fetch(PDO::FETCH_ASSOC) : null;

$chatSettings = [
    'chat_max_file_bytes'       => $s['chat_max_file_bytes'] ?? 26214400,
    'chat_allowed_mime_prefixes'=> $s['chat_allowed_mime_prefixes'] ?? 'image/,video/,audio/,application/pdf,text/',
    'chat_default_auto_download'=> $s['chat_default_auto_download'] ?? 0,
];
?>
<?php include '../includes/header_finance.php'; ?>

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
									</div>
									<div class="card-body">
										<?php if (!empty($_GET['ok'])): ?>
											<div class="alert alert-success mb-3">Settings saved successfully.</div>
										<?php endif; ?>
										<p class="text-muted mb-4">Configure limits and defaults for inter-tenant chat. Changes apply to your tenant.</p>
										<form method="post">
											<div class="row">
												<div class="col-md-6">
													<div class="form-group">
														<label>Max file size (bytes)</label>
														<input type="number" name="max_file_bytes" class="form-control" value="<?= (int)$chatSettings['chat_max_file_bytes'] ?>" min="1048576" step="1024" required />
														<small class="form-text text-muted">Minimum: 1 MB (1048576 bytes). Applies to all uploads in chat.</small>
													</div>
												</div>
												<div class="col-md-6">
													<div class="form-group">
														<label>Allowed MIME prefixes (comma-separated)</label>
														<input type="text" name="allowed_mime_prefixes" class="form-control" value="<?= htmlspecialchars($chatSettings['chat_allowed_mime_prefixes']) ?>" required />
														<small class="form-text text-muted">Examples: image/, video/, audio/, application/pdf, text/</small>
													</div>
												</div>
											</div>
											<div class="form-group form-check">
												<input class="form-check-input" type="checkbox" name="default_auto_download" id="default_auto_download" <?= ((int)$chatSettings['chat_default_auto_download']) ? 'checked' : '' ?> />
												<label class="form-check-label" for="default_auto_download">Enable auto-download by default</label>
											</div>
											<button class="btn btn-primary" type="submit"><i class="feather icon-save mr-2"></i>Save</button>
											<button class="btn btn-light ml-2" type="button" onclick="resetDefaults()"><i class="feather icon-refresh-ccw mr-2"></i>Reset to recommended</button>
										</form>
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
