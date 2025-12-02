<?php
include 'header.php';

// Get tenant ID from session
$tenant_id = $_SESSION['tenant_id'];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
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
<div class="pcoded-main-container">
    <div class="pcoded-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Branch Management</h5>
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
            <?= htmlspecialchars($message) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createBranchModal">
                    <i class="feather icon-plus"></i> Create New Branch
                </button>
            </div>
        </div>

        <!-- Branches Table -->
        <div class="row">
            <div class="col-xl-12 col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>All Branches</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Manager</th>
                                        <th>Users</th>
                                        <th>Status</th>
                                        <th>Contact</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($branches as $branch): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($branch['code']) ?></strong></td>
                                        <td><?= htmlspecialchars($branch['name']) ?></td>
                                        <td>
                                            <?php if ($branch['manager_name']): ?>
                                                <?= htmlspecialchars($branch['manager_name']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?= $branch['user_count'] ?> users</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $branch['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst($branch['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($branch['phone'] || $branch['email']): ?>
                                                <small>
                                                    <?php if ($branch['phone']): ?>
                                                        <i class="feather icon-phone"></i> <?= htmlspecialchars($branch['phone']) ?><br>
                                                    <?php endif; ?>
                                                    <?php if ($branch['email']): ?>
                                                        <i class="feather icon-mail"></i> <?= htmlspecialchars($branch['email']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('M d, Y', strtotime($branch['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editBranch(<?= $branch['id'] ?>)">
                                                    <i class="feather icon-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteBranch(<?= $branch['id'] ?>, '<?= htmlspecialchars($branch['name']) ?>')">
                                                    <i class="feather icon-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($branches)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="feather icon-git-branch text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="text-muted mt-2">No branches found</h5>
                                            <p class="text-muted">Create your first branch to get started.</p>
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
                <h5 class="modal-title" id="createBranchModalLabel">Create New Branch</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
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
                <h5 class="modal-title" id="editBranchModalLabel">Edit Branch</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="editBranchForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="branch_id" id="editBranchId">
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
                <h5 class="modal-title" id="deleteBranchModalLabel">Delete Branch</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST" id="deleteBranchForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="branch_id" id="deleteBranchId">
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