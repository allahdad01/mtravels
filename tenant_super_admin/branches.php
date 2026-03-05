<?php
include 'header.php';
require_once '../includes/BranchAddonManager.php';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];

// Initialize branch addon manager
$addon_manager = new BranchAddonManager($pdo, $tenant_id);

// Get current branch limits and status for display
$plan_info = $addon_manager->getTenantPlanInfo($tenant_id);
$current_branches = $addon_manager->getCurrentBranchCount($tenant_id);
$max_allowed_branches = $addon_manager->getMaxAllowedBranches($tenant_id);
$additional_branches = $addon_manager->getTotalAdditionalBranches($tenant_id);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for all POST requests
    if (!CsrfProtection::validateToken($_POST['csrf_token'] ?? null)) {
        $message = 'Security token validation failed. Please try again.';
        $messageType = 'danger';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                // Create new branch
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;

                if (empty($name) || empty($code)) {
                    $message = 'Branch name and code are required.';
                    $messageType = 'danger';
                } elseif (!$addon_manager->canAddMoreBranches()) {
                    // Check if tenant has reached branch limit
                    $message = "You have reached the maximum number of branches (" . $max_allowed_branches . "). ";
                    $message .= ($additional_branches > 0) ? 
                        "To create more branches, please contact support or request additional branches." :
                        "Please <a href='../admin/request_branch_addon.php' style='font-weight: bold; text-decoration: underline;'>request additional branches</a> to exceed your plan limit.";
                    $messageType = 'danger';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO branches (tenant_id, name, code, address, phone, email, manager_id, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$tenant_id, $name, $code, $address, $phone, $email, $manager_id, $_SESSION['user_id']]);

                        // Log activity
                        logActivity($pdo, $tenant_id, $_SESSION['user_id'], 'create', 'branches', $pdo->lastInsertId(), null, json_encode([
                            'name' => $name,
                            'code' => $code,
                            'address' => $address,
                            'phone' => $phone,
                            'email' => $email,
                            'manager_id' => $manager_id
                        ]));

                        $message = 'Branch created successfully.';
                        $messageType = 'success';
                        
                        // Refresh branch limits after successful creation
                        $current_branches = $addon_manager->getCurrentBranchCount($tenant_id);
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Duplicate entry
                            $message = 'Branch code already exists.';
                        } else {
                            $message = 'Error creating branch: ' . $e->getMessage();
                        }
                        $messageType = 'danger';
                    }
                }
                break;

            case 'update':
                // Update branch
                $branch_id = $_POST['branch_id'] ?? 0;
                $name = trim($_POST['name'] ?? '');
                $code = trim($_POST['code'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
                $status = $_POST['status'] ?? 'active';

                if (empty($name) || empty($code) || !$branch_id) {
                    $message = 'Branch name, code, and ID are required.';
                    $messageType = 'danger';
                } else {
                    try {
                        // Get old values for logging
                        $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? AND tenant_id = ?");
                        $stmt->execute([$branch_id, $tenant_id]);
                        $oldBranch = $stmt->fetch(PDO::FETCH_ASSOC);

                        $stmt = $pdo->prepare("
                            UPDATE branches
                            SET name = ?, code = ?, address = ?, phone = ?, email = ?, manager_id = ?, status = ?, updated_at = NOW()
                            WHERE id = ? AND tenant_id = ?
                        ");
                        $stmt->execute([$name, $code, $address, $phone, $email, $manager_id, $status, $branch_id, $tenant_id]);

                        // Log activity
                        logActivity($pdo, $tenant_id, $_SESSION['user_id'], 'update', 'branches', $branch_id,
                            json_encode($oldBranch),
                            json_encode([
                                'name' => $name,
                                'code' => $code,
                                'address' => $address,
                                'phone' => $phone,
                                'email' => $email,
                                'manager_id' => $manager_id,
                                'status' => $status
                            ])
                        );

                        $message = 'Branch updated successfully.';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Duplicate entry
                            $message = 'Branch code already exists.';
                        } else {
                            $message = 'Error updating branch: ' . $e->getMessage();
                        }
                        $messageType = 'danger';
                    }
                }
                break;

            case 'delete':
                // Delete branch
                $branch_id = $_POST['branch_id'] ?? 0;

                if (!$branch_id) {
                    $message = 'Branch ID is required.';
                    $messageType = 'danger';
                } else {
                    try {
                        // Check if branch has users
                        $stmt = $pdo->prepare("SELECT COUNT(*) as user_count FROM users WHERE branch_id = ? AND tenant_id = ?");
                        $stmt->execute([$branch_id, $tenant_id]);
                        $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['user_count'];

                        if ($userCount > 0) {
                            $message = 'Cannot delete branch with existing users. Please reassign users first.';
                            $messageType = 'danger';
                        } else {
                            // Get branch data for logging
                            $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ? AND tenant_id = ?");
                            $stmt->execute([$branch_id, $tenant_id]);
                            $branchData = $stmt->fetch(PDO::FETCH_ASSOC);

                            $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ? AND tenant_id = ?");
                            $stmt->execute([$branch_id, $tenant_id]);

                            // Log activity
                            logActivity($pdo, $tenant_id, $_SESSION['user_id'], 'delete', 'branches', $branch_id,
                                json_encode($branchData), null);

                            $message = 'Branch deleted successfully.';
                            $messageType = 'success';
                        }
                    } catch (PDOException $e) {
                        $message = 'Error deleting branch: ' . $e->getMessage();
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Fetch branches
try {
    $stmt = $pdo->prepare("
        SELECT b.*, u.name as manager_name,
               (SELECT COUNT(*) FROM users WHERE branch_id = b.id AND tenant_id = b.tenant_id) as user_count
        FROM branches b
        LEFT JOIN users u ON b.manager_id = u.id
        WHERE b.tenant_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$tenant_id]);
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $branches = [];
}

// Fetch available managers (admin users who can be branch managers)
try {
    $stmt = $pdo->prepare("
        SELECT id, name, email
        FROM users
        WHERE tenant_id = ? AND role IN ('admin', 'sales', 'finance', 'umrah', 'visa')
        ORDER BY name
    ");
    $stmt->execute([$tenant_id]);
    $availableManagers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $availableManagers = [];
}

// Helper function to log activity
function logActivity($pdo, $tenant_id, $user_id, $action, $table_name, $record_id, $old_values, $new_values) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (tenant_id, user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenant_id,
            $user_id,
            $action,
            $table_name,
            $record_id,
            $old_values,
            $new_values,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}
?>

<!-- [ Main Content ] start -->
<style>
/* Enhanced custom styles for better layout and design */
.page-header-title h5 {
    color: #007bff;
    font-weight: 600;
}
.card {
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}
.card.border-left-primary {
    border-left: 4px solid #007bff !important;
}
.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px 10px 0 0;
    padding: 1rem 1.5rem;
    border: none;
}
.card-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
}
.badge {
    font-size: 0.85em;
    padding: 0.5em 0.75em;
    border-radius: 20px;
    font-weight: 500;
}
.badge-success {
    background-color: #28a745;
}
.badge-info {
    background-color: #17a2b8;
}
.badge-secondary {
    background-color: #6c757d;
}
.table-responsive {
    border-radius: 10px;
    
}
.table {
    margin-bottom: 0;
}
.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    padding: 1rem;
}
.table tbody tr:hover {
    background-color: #f1f3f4;
}
.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}
.btn {
    border-radius: 25px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    border: none;
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,123,255,0.3);
}
.btn-outline-primary {
    border-color: #007bff;
    color: #007bff;
}
.btn-outline-primary:hover {
    background-color: #007bff;
    border-color: #007bff;
}
.btn-outline-danger {
    border-color: #dc3545;
    color: #dc3545;
}
.btn-outline-danger:hover {
    background-color: #dc3545;
    border-color: #dc3545;
}
.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}
.alert {
    border-radius: 10px;
    border: none;
    padding: 1rem 1.5rem;
}
.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
}
.alert-danger {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
}
.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
}
.modal-content {
    border-radius: 15px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px 15px 0 0;
    border: none;
}
.modal-header .close {
    color: white;
    opacity: 0.8;
}
.modal-header .close:hover {
    opacity: 1;
}
.form-control {
    border-radius: 8px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    padding: 0.75rem;
}
.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
}
.btn-group .btn {
    border-radius: 50% !important;
    margin: 0 2px;
}
.text-primary {
    color: #007bff !important;
}
.text-muted {
    color: #6c757d !important;
}
</style>
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><i class="feather icon-git-branch mr-2"></i>Branch Management</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="javascript:void(0)">Branch Management</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->

        <!-- Alert Messages -->
        <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <i class="feather icon-<?= $messageType === 'success' ? 'check-circle' : 'alert-circle' ?>"></i>
            <?php if ($messageType === 'danger' && strpos($message, 'request additional branches') !== false): ?>
                <?= $message ?>
            <?php else: ?>
                <?= htmlspecialchars($message) ?>
            <?php endif; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <!-- Branch Limits Info Card -->
        <?php if ($plan_info): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-left-primary shadow">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <div class="mb-3">
                                    <i class="feather icon-package text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="text-muted mb-2">Current Plan</h6>
                                <h5 class="mb-0 font-weight-bold text-primary"><?= htmlspecialchars($plan_info['name']) ?></h5>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="mb-3">
                                    <i class="feather icon-git-branch text-info" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="text-muted mb-2">Branches</h6>
                                <h5 class="mb-0">
                                    <span class="text-primary font-weight-bold h3"><?= $current_branches ?></span>
                                    <small class="text-muted">/ <?= $max_allowed_branches ?></small>
                                </h5>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="mb-3">
                                    <i class="feather icon-check text-success" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="text-muted mb-2">Included</h6>
                                <h5 class="mb-0 font-weight-bold text-success"><?= htmlspecialchars($plan_info['max_branches']) ?></h5>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="mb-3">
                                    <i class="feather icon-plus-circle text-warning" style="font-size: 2rem;"></i>
                                </div>
                                <h6 class="text-muted mb-2">Add-ons</h6>
                                <h5 class="mb-0 font-weight-bold text-warning"><?= $additional_branches > 0 ? '+' . $additional_branches : '-' ?></h5>
                            </div>
                        </div>
                        <?php if ($max_allowed_branches > $plan_info['max_branches']): ?>
                        <hr class="my-4">
                        <div class="alert alert-info mb-0 border-0 shadow-sm">
                            <i class="feather icon-info mr-2"></i>
                            You have purchased <strong class="text-primary"><?= $additional_branches ?> additional branch(es)</strong> for this billing period.
                            <?php if (!$addon_manager->canAddMoreBranches()): ?>
                            <br><strong class="text-danger">Maximum capacity reached.</strong> To add more branches, contact support.
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <button type="button" class="btn btn-primary btn-lg <?= !$addon_manager->canAddMoreBranches() ? 'disabled' : '' ?>"
                        data-toggle="modal"
                        data-target="#createBranchModal"
                        <?= !$addon_manager->canAddMoreBranches() ? 'disabled' : '' ?>>
                    <i class="feather icon-plus mr-2"></i> Create New Branch
                </button>
                <?php if ($current_branches < $max_allowed_branches && $additional_branches == 0): ?>
                <div class="alert alert-light border mt-3 shadow-sm">
                    <i class="feather icon-info mr-2 text-info"></i>
                    <strong class="text-info">Available:</strong> <?= $max_allowed_branches - $current_branches ?> more branch(es) for your plan
                </div>
                <?php elseif (!$addon_manager->canAddMoreBranches()): ?>
                <div class="alert alert-warning border mt-3 shadow-sm">
                    <i class="feather icon-alert-circle mr-2"></i>
                    <strong>You have reached your maximum branches.</strong> <a href="request_branch_addon.php" class="alert-link font-weight-bold">Request more branches</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Branches Table -->
        <div class="row">
            <div class="col-xl-12 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="feather icon-list mr-2"></i>All Branches</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="feather icon-hash mr-1"></i>Code</th>
                                        <th><i class="feather icon-tag mr-1"></i>Name</th>
                                        <th><i class="feather icon-user mr-1"></i>Manager</th>
                                        <th><i class="feather icon-users mr-1"></i>Users</th>
                                        <th><i class="feather icon-activity mr-1"></i>Status</th>
                                        <th><i class="feather icon-phone mr-1"></i>Contact</th>
                                        <th><i class="feather icon-calendar mr-1"></i>Created</th>
                                        <th><i class="feather icon-settings mr-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($branches as $branch): ?>
                                    <tr>
                                        <td><strong class="text-primary font-weight-bold"><?= htmlspecialchars($branch['code']) ?></strong></td>
                                        <td class="font-weight-bold text-dark"><?= htmlspecialchars($branch['name']) ?></td>
                                        <td>
                                            <?php if ($branch['manager_name']): ?>
                                                <span class="text-success font-weight-bold"><i class="feather icon-user-check mr-1"></i><?= htmlspecialchars($branch['manager_name']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted"><i class="feather icon-user-x mr-1"></i>Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-info badge-pill px-3 py-2 font-weight-bold">
                                                <i class="feather icon-users mr-1"></i><?= $branch['user_count'] ?> users
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $branch['status'] === 'active' ? 'success' : 'secondary' ?> badge-pill px-3 py-1 font-weight-bold">
                                                <i class="feather icon-<?= $branch['status'] === 'active' ? 'check-circle' : 'x-circle' ?> mr-1"></i>
                                                <?= ucfirst($branch['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($branch['phone'] || $branch['email']): ?>
                                                <div class="small">
                                                    <?php if ($branch['phone']): ?>
                                                        <div class="mb-1"><i class="feather icon-phone text-primary mr-1"></i><span class="text-dark"><?= htmlspecialchars($branch['phone']) ?></span></div>
                                                    <?php endif; ?>
                                                    <?php if ($branch['email']): ?>
                                                        <div><i class="feather icon-mail text-info mr-1"></i><span class="text-dark"><?= htmlspecialchars($branch['email']) ?></span></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted">
                                            <small class="font-weight-bold"><?= date('M d, Y', strtotime($branch['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editBranch(<?= $branch['id'] ?>)" title="Edit Branch">
                                                    <i class="feather icon-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteBranch(<?= $branch['id'] ?>, '<?= htmlspecialchars($branch['name']) ?>')" title="Delete Branch">
                                                    <i class="feather icon-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($branches)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <div class="mb-4">
                                                <i class="feather icon-git-branch text-muted" style="font-size: 4rem;"></i>
                                            </div>
                                            <h5 class="text-muted font-weight-bold mb-2">No branches found</h5>
                                            <p class="text-muted mb-4">Create your first branch to get started with branch management.</p>
                                            <button type="button" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#createBranchModal">
                                                <i class="feather icon-plus mr-2"></i>Create Your First Branch
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Branch Modal -->
<div class="modal fade" id="createBranchModal" tabindex="-1" role="dialog" aria-labelledby="createBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createBranchModalLabel"><i class="feather icon-plus-circle mr-2"></i>Create New Branch</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="branchName">Branch Name *</label>
                                <input type="text" class="form-control" id="branchName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="branchCode">Branch Code *</label>
                                <input type="text" class="form-control" id="branchCode" name="code" required>
                                <small class="form-text text-muted">Unique identifier for the branch</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="branchPhone">Phone</label>
                                <input type="tel" class="form-control" id="branchPhone" name="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="branchEmail">Email</label>
                                <input type="email" class="form-control" id="branchEmail" name="email">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="branchAddress">Address</label>
                        <textarea class="form-control" id="branchAddress" name="address" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="branchManager">Branch Manager</label>
                        <select class="form-control" id="branchManager" name="manager_id">
                            <option value="">Select Manager (Optional)</option>
                            <?php foreach ($availableManagers as $manager): ?>
                            <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['name']) ?> (<?= htmlspecialchars($manager['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1" role="dialog" aria-labelledby="editBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBranchModalLabel"><i class="feather icon-edit mr-2"></i>Edit Branch</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editBranchForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="branch_id" id="editBranchId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editBranchName">Branch Name *</label>
                                <input type="text" class="form-control" id="editBranchName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editBranchCode">Branch Code *</label>
                                <input type="text" class="form-control" id="editBranchCode" name="code" required>
                                <small class="form-text text-muted">Unique identifier for the branch</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editBranchPhone">Phone</label>
                                <input type="tel" class="form-control" id="editBranchPhone" name="phone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editBranchEmail">Email</label>
                                <input type="email" class="form-control" id="editBranchEmail" name="email">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editBranchAddress">Address</label>
                        <textarea class="form-control" id="editBranchAddress" name="address" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="editBranchManager">Branch Manager</label>
                        <select class="form-control" id="editBranchManager" name="manager_id">
                            <option value="">Select Manager (Optional)</option>
                            <?php foreach ($availableManagers as $manager): ?>
                            <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['name']) ?> (<?= htmlspecialchars($manager['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editBranchStatus">Status</label>
                        <select class="form-control" id="editBranchStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Branch Modal -->
<div class="modal fade" id="deleteBranchModal" tabindex="-1" role="dialog" aria-labelledby="deleteBranchModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteBranchModalLabel"><i class="feather icon-trash-2 mr-2"></i>Delete Branch</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="deleteBranchForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="branch_id" id="deleteBranchId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body">
                    <p>Are you sure you want to delete the branch "<strong id="deleteBranchName"></strong>"?</p>
                    <div class="alert alert-warning">
                        <i class="feather icon-alert-triangle"></i>
                        <strong>Warning:</strong> This action cannot be undone. All branch data will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit branch function
function editBranch(branchId) {
    // Fetch branch data via AJAX
    $.ajax({
        url: 'get_branch.php',
        type: 'GET',
        data: { id: branchId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const branch = response.branch;
                $('#editBranchId').val(branch.id);
                $('#editBranchName').val(branch.name);
                $('#editBranchCode').val(branch.code);
                $('#editBranchPhone').val(branch.phone || '');
                $('#editBranchEmail').val(branch.email || '');
                $('#editBranchAddress').val(branch.address || '');
                $('#editBranchManager').val(branch.manager_id || '');
                $('#editBranchStatus').val(branch.status);
                $('#editBranchModal').modal('show');
            } else {
                alert('Error loading branch data: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            alert('An error occurred while loading branch data: ' + error);
        }
    });
}

// Delete branch function
function deleteBranch(branchId, branchName) {
    $('#deleteBranchId').val(branchId);
    $('#deleteBranchName').text(branchName);
    $('#deleteBranchModal').modal('show');
}

// Form validation
$('#createBranchModal form').on('submit', function(e) {
    const name = $('#branchName').val().trim();
    const code = $('#branchCode').val().trim();

    if (!name || !code) {
        e.preventDefault();
        alert('Branch name and code are required.');
        return false;
    }
});

$('#editBranchForm').on('submit', function(e) {
    const name = $('#editBranchName').val().trim();
    const code = $('#editBranchCode').val().trim();

    if (!name || !code) {
        e.preventDefault();
        alert('Branch name and code are required.');
        return false;
    }
});
</script>

<?php include 'footer.php'; ?>