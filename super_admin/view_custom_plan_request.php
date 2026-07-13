<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset(); session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';
require_once '../includes/feature-selector.php';

$request_id = intval($_GET['id'] ?? 0);
if (!$request_id) {
    header('Location: manage_custom_plan_requests.php?error=invalid_id');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM custom_plan_requests WHERE id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: manage_custom_plan_requests.php?error=not_found');
    exit();
}

$selected_features = json_decode($request['selected_features'], true) ?? [];
$categories = getCustomFeatureCategories();

require_once '../includes/header_super_admin.php';
?>

<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                Custom Plan Request #<?= $request['id'] ?>
                            </h5>
                            <p class="mb-0 mt-1" style="font-size: 14px; opacity: 0.9;">Submitted on <?= date('F j, Y \a\t g:i A', strtotime($request['created_at'])) ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="manage_custom_plan_requests.php" class="btn btn-light btn-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>Back to Requests
                            </a>
                        </div>
                    </div>
                </div>

                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="row">
                            <div class="col-xl-12">
                                <?php if (isset($_GET['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_GET['success']) ?>
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($_GET['error']) ?>
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                </div>
                                <?php endif; ?>

                                <!-- Status Update Form -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="feather icon-edit mr-2"></i>Update Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="update_custom_plan_request.php">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">

                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label class="font-weight-600">Status</label>
                                                        <select name="status" class="form-control">
                                                            <option value="pending" <?php echo $request['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="contacted" <?php echo $request['status'] === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                                            <option value="negotiating" <?php echo $request['status'] === 'negotiating' ? 'selected' : ''; ?>>Negotiating</option>
                                                            <option value="approved" <?php echo $request['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                            <option value="rejected" <?php echo $request['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label class="font-weight-600">Negotiated Price</label>
                                                        <div class="input-group">
                                                            <input type="number" step="0.01" min="0" name="negotiated_price" class="form-control" value="<?= htmlspecialchars($request['negotiated_price'] ?? '') ?>" placeholder="Leave empty if not set">
                                                            <select name="currency" class="form-control" style="max-width:90px;">
                                                                <option value="AFN" <?php echo ($request['currency'] ?? 'AFN') === 'AFN' ? 'selected' : ''; ?>>AFN</option>
                                                                <option value="USD" <?php echo ($request['currency'] ?? 'AFN') === 'USD' ? 'selected' : ''; ?>>USD</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="font-weight-600">Admin Notes</label>
                                                <textarea name="admin_notes" class="form-control" rows="3" placeholder="Add internal notes about this request..."><?= htmlspecialchars($request['admin_notes'] ?? '') ?></textarea>
                                            </div>

                                            <div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="feather icon-save mr-1"></i>Update Request
                                                </button>
                                                <?php if ($request['status'] === 'approved' || $request['status'] === 'negotiating'): ?>
                                                <a href="convert_custom_plan_request.php?id=<?= $request['id'] ?>" class="btn btn-success ml-2">
                                                    <i class="feather icon-layers mr-1"></i>Convert to Tenant
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Contact & Pricing Details -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-user mr-2"></i>Contact Information</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-borderless table-sm mb-0">
                                                    <tr>
                                                        <td class="font-weight-600" style="width:100px;">Name</td>
                                                        <td><?= htmlspecialchars($request['contact_name']) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="font-weight-600">Email</td>
                                                        <td><a href="mailto:<?= htmlspecialchars($request['contact_email']) ?>"><?= htmlspecialchars($request['contact_email']) ?></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="font-weight-600">Phone</td>
                                                        <td><a href="tel:<?= htmlspecialchars($request['contact_phone']) ?>"><?= htmlspecialchars($request['contact_phone']) ?></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="font-weight-600">Agency</td>
                                                        <td><?= htmlspecialchars($request['agency_name'] ?: '—') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="font-weight-600">Users</td>
                                                        <td><?= $request['max_users'] ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="font-weight-600">Status</td>
                                                        <td>
                                                            <?php
                                                            $badge = match($request['status']) {
                                                                'pending' => 'badge-warning',
                                                                'contacted' => 'badge-info',
                                                                'negotiating' => 'badge-primary',
                                                                'approved' => 'badge-success',
                                                                'rejected' => 'badge-danger',
                                                                'converted' => 'badge-success',
                                                                default => 'badge-secondary'
                                                            };
                                                            ?>
                                                            <span class="badge <?= $badge ?>"><?= $request['status'] ?></span>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5><i class="feather icon-dollar-sign mr-2"></i>Pricing</h5>
                                            </div>
                                            <div class="card-body">
                                                <table class="table table-borderless table-sm mb-0">
                                                    <tr>
                                                        <td class="font-weight-600" style="width:140px;">Suggested Price</td>
                                                        <td><?= $request['suggested_price'] ? htmlspecialchars($request['currency'] ?: 'AFN') . ' ' . number_format($request['suggested_price'], 2) : 'Not specified' ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="font-weight-600">Negotiated Price</td>
                                                        <td><?= $request['negotiated_price'] ? htmlspecialchars($request['currency'] ?: 'AFN') . ' ' . number_format($request['negotiated_price'], 2) : 'Not set' ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Applicant Notes -->
                                <?php if ($request['notes']): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h5><i class="feather icon-file-text mr-2"></i>Applicant Notes</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0" style="white-space:pre-wrap;"><?= htmlspecialchars($request['notes']) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Admin Notes Display -->
                                <?php if ($request['admin_notes']): ?>
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h5><i class="feather icon-edit-2 mr-2"></i>Admin Notes</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="p-3 rounded" style="background:#fff8e6;border:1px solid #fce4a0;">
                                            <?= nl2br(htmlspecialchars($request['admin_notes'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Selected Features -->
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h5><i class="feather icon-check-square mr-2"></i>Selected Features (<?= count($selected_features) ?>)</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($selected_features)): ?>
                                        <div class="row">
                                            <?php foreach ($categories as $catKey => $category): ?>
                                                <?php $catFeatures = array_intersect_key($category['features'], array_flip($selected_features)); ?>
                                                <?php if (!empty($catFeatures)): ?>
                                                <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                                                    <div class="p-3 rounded h-100" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                                        <h6 class="mb-2 font-weight-600" style="color:#4099ff;font-size:0.85rem;"><?= htmlspecialchars($category['title']) ?></h6>
                                                        <?php foreach ($catFeatures as $featKey => $featLabel): ?>
                                                        <div class="d-flex align-items-center mb-1" style="font-size:0.85rem;">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="mr-2 flex-shrink-0"><polyline points="20 6 9 17 4 12"/></svg>
                                                            <span><?= htmlspecialchars($featLabel) ?></span>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                        <p class="text-muted mb-0">No features selected.</p>
                                        <?php endif; ?>
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

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
