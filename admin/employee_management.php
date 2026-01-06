<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once 'security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

// Build query
$query = "
    SELECT u.*, sm.base_salary, sm.currency as salary_currency, sm.status as salary_status
    FROM users u
    LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id AND sm.branch_id = u.branch_id
    WHERE u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin'
";

$params = [$tenant_id, $branch_id];
$types = "ii";

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($status_filter !== 'all') {
    if ($status_filter === 'active') {
        $query .= " AND u.fired = 0";
    } elseif ($status_filter === 'terminated') {
        $query .= " AND u.fired = 1";
    }
}

if ($role_filter !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if ($user_id) {
    $query .= " AND u.id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate active and fired employees
$active_employees = array_filter($employees, function($emp) {
    return !$emp['fired'];
});

$fired_employees = array_filter($employees, function($emp) {
    return $emp['fired'];
});

// Get roles for filter dropdown
$roles_query = "SELECT DISTINCT role FROM users WHERE tenant_id = ? AND branch_id = ? AND role IS NOT NULL AND role != 'super_admin' ORDER BY role";
$stmt = $pdo->prepare($roles_query);
$stmt->execute([$tenant_id, $branch_id]);
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = $user_id ? __('manage_employee') : __('employee_management');
include '../includes/header.php';
?>
<style>
    .page-header.card {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: #ffffff;
        border: none;
        margin-bottom: 20px;
        padding: 20px !important;
    }

    .page-header.card .row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .page-header.card h5 {
        color: #ffffff;
        margin: 0;
    }

    .page-header.card .text-end {
        text-align: right;
    }

    .page-header.card .btn {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.3);
    }

    .page-header.card .btn:hover {
        background: rgba(255,255,255,0.3);
        border-color: rgba(255,255,255,0.5);
    }
</style>




    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
                                <div class="page-header card">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h5 class="mb-0"><i class="feather icon-users mr-2"></i><?php echo $user_id ? __('manage_employee') : __('employee_management'); ?></h5>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <?php if (!$user_id): ?>
                                                <a href="add_employee.php" class="btn btn-primary btn-sm">
                                                    <i class="feather icon-user-plus mr-1"></i><?php echo __('add_employee'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!$user_id): ?>
                                <!-- Filters and Search -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <form method="GET" class="row g-3">
                                            <div class="col-md-4">
                                                <input type="text" class="form-control" name="search" placeholder="<?php echo __('search_employees'); ?>"
                                                    value="<?php echo htmlspecialchars($search); ?>">
                                            </div>
                                            <div class="col-md-3">
                                                <select class="form-control" name="status">
                                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo __('all_statuses'); ?></option>
                                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                                                    <option value="terminated" <?php echo $status_filter === 'terminated' ? 'selected' : ''; ?>><?php echo __('terminated'); ?></option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <select class="form-control" name="role">
                                                    <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>><?php echo __('all_roles'); ?></option>
                                                    <?php foreach ($roles as $role): ?>
                                                        <option value="<?php echo htmlspecialchars($role); ?>" <?php echo $role_filter === $role ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(ucfirst($role)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="submit" class="btn btn-primary btn-block">
                                                    <i class="feather icon-search mr-1"></i><?php echo __('search'); ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Employees Table with Tabs -->
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5><?php echo __('employees'); ?> (<?php echo count($employees); ?>)</h5>
                                        <div class="d-flex align-items-center gap-3">
                                            <button class="btn btn-primary" onclick="showAddEmployeeModal()">
                                                <i class="feather icon-plus"></i> <?php echo __('add_employee'); ?>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body table-border-style">
                                        <!-- Nav tabs -->
                                        <ul class="nav nav-tabs mb-3" role="tablist">
                                            <li class="nav-item">
                                                <a class="nav-link active" data-toggle="tab" href="#active-employees" role="tab">
                                                    <i class="feather icon-user mr-2"></i><?php echo __('active_employees'); ?> (<?php echo count($active_employees); ?>)
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link" data-toggle="tab" href="#fired-employees" role="tab">
                                                    <i class="feather icon-user-x mr-2"></i><?php echo __('fired_employees'); ?> (<?php echo count($fired_employees); ?>)
                                                </a>
                                            </li>
                                        </ul>

                                        <!-- Tab panes -->
                                        <div class="tab-content">
                                            <!-- Active Employees Tab -->
                                            <div class="tab-pane active" id="active-employees" role="tabpanel">
                                                <div class="table-responsive">
                                                    <table id="active-employees-table" class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th><?php echo __('profile'); ?></th>
                                                                <th><?php echo __('name'); ?></th>
                                                                <th><?php echo __('email'); ?></th>
                                                                <th><?php echo __('role'); ?></th>
                                                                <th><?php echo __('phone'); ?></th>
                                                                <th><?php echo __('join_date'); ?></th>
                                                                <th><?php echo __('status'); ?></th>
                                                                <th><?php echo __('actions'); ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($active_employees as $employee): ?>
                                                                <tr>
                                                                    <td>
                                                                        <img src="../assets/images/user/<?= htmlspecialchars($employee['profile_pic'] ?? 'default-avatar.jpg') ?>"
                                                                              class="user-avatar" style="width: 40px !important; height: 40px !important;" alt="User Avatar">
                                                                    </td>
                                                                    <td>
                                                                        <div class="font-weight-bold"><?php echo htmlspecialchars($employee['name']); ?></div>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                                                    <td>
                                                                        <span class="badge-role badge-<?php echo strtolower($employee['role']); ?>">
                                                                            <?php echo ucfirst(htmlspecialchars($employee['role'])); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></td>
                                                                    <td><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                                                                    <td>
                                                                        <span class="badge-success">
                                                                            <?php echo __('active'); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="btn-group">
                                                                            <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                                <i class="feather icon-more-vertical"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                                <!-- View Details -->
                                                                                <a class="dropdown-item" href="employee_details.php?id=<?php echo $employee['id']; ?>">
                                                                                    <i class="feather icon-eye mr-2"></i><?php echo __('view_details'); ?>
                                                                                </a>
                                                                                <!-- Edit -->
                                                                                <a class="dropdown-item" href="edit_employee.php?id=<?php echo $employee['id']; ?>">
                                                                                    <i class="feather icon-edit mr-2"></i><?php echo __('edit'); ?>
                                                                                </a>
                                                                                <!-- Terminate -->
                                                                                <a class="dropdown-item text-danger" href="#" onclick="terminateEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>')">
                                                                                    <i class="feather icon-user-x mr-2"></i><?php echo __('terminate'); ?>
                                                                                </a>
                                                                                <div class="dropdown-divider"></div>
                                                                                <!-- HR Documents -->
                                                                                <a class="dropdown-item" href="#" onclick="showLanguageModal(<?php echo $employee['id']; ?>)">
                                                                                    <i class="feather icon-file-plus mr-2"></i><?php echo __('employment_agreement'); ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="#" onclick="showGuarantorLanguageModal(<?php echo $employee['id']; ?>)">
                                                                                    <i class="feather icon-user-check mr-2"></i><?php echo __('guarantor_letter'); ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="#" onclick="showTawseahModal(<?php echo $employee['id']; ?>)">
                                                                                    <i class="feather icon-alert-circle mr-2"></i><?php echo __('tawseah'); ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="#" onclick="showIkhtarModal(<?php echo $employee['id']; ?>)">
                                                                                    <i class="feather icon-alert-triangle mr-2"></i><?php echo __('official_warning'); ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="#" onclick="showFineLetterModal(<?php echo $employee['id']; ?>)">
                                                                                    <i class="fa fa-money-bill-alt mr-2"></i><?php echo __('fine_letter'); ?>
                                                                                </a>
                                                                                <a class="dropdown-item" href="#" onclick="showTerminationLetterModal(<?php echo $employee['id']; ?>)">
                                                                                    <i class="feather icon-user-x mr-2"></i><?php echo __('termination_letter'); ?>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Fired Employees Tab -->
                                            <div class="tab-pane" id="fired-employees" role="tabpanel">
                                                <div class="table-responsive">
                                                    <table id="fired-employees-table" class="table table-hover">
                                                        <thead>
                                                            <tr>
                                                                <th><?php echo __('profile'); ?></th>
                                                                <th><?php echo __('name'); ?></th>
                                                                <th><?php echo __('email'); ?></th>
                                                                <th><?php echo __('role'); ?></th>
                                                                <th><?php echo __('phone'); ?></th>
                                                                <th><?php echo __('join_date'); ?></th>
                                                                <th><?php echo __('status'); ?></th>
                                                                <th><?php echo __('actions'); ?></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($fired_employees as $employee): ?>
                                                                <tr class="table-danger">
                                                                    <td>
                                                                        <img src="../assets/images/user/<?= htmlspecialchars($employee['profile_pic'] ?? 'default-avatar.jpg') ?>"
                                                                              class="user-avatar" style="width: 40px !important; height: 40px !important;" alt="User Avatar">
                                                                    </td>
                                                                    <td>
                                                                        <div class="font-weight-bold"><?php echo htmlspecialchars($employee['name']); ?></div>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                                                    <td>
                                                                        <span class="badge-role badge-<?php echo strtolower($employee['role']); ?>">
                                                                            <?php echo ucfirst(htmlspecialchars($employee['role'])); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></td>
                                                                    <td><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                                                                    <td>
                                                                        <span class="badge-danger">
                                                                            <?php echo __('fired'); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="btn-group">
                                                                            <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                                <i class="feather icon-more-vertical"></i>
                                                                            </button>
                                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                                <!-- View Details -->
                                                                                <a class="dropdown-item" href="employee_details.php?id=<?php echo $employee['id']; ?>">
                                                                                    <i class="feather icon-eye mr-2"></i><?php echo __('view_details'); ?>
                                                                                </a>
                                                                                <!-- Edit -->
                                                                                <a class="dropdown-item" href="edit_employee.php?id=<?php echo $employee['id']; ?>">
                                                                                    <i class="feather icon-edit mr-2"></i><?php echo __('edit'); ?>
                                                                                </a>
                                                                                <!-- Reinstate -->
                                                                                <a class="dropdown-item text-success" href="#" onclick="reinstateEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>')">
                                                                                    <i class="feather icon-user-check mr-2"></i><?php echo __('reinstate'); ?>
                                                                                </a>
                                                                                <div class="dropdown-divider"></div>
                                                                                <!-- Termination Letter -->
                                                                                <a class="dropdown-item" href="#" onclick="showTerminationLetterModal(<?php echo $employee['id']; ?>)">
                                                                                    <i class="feather icon-user-x mr-2"></i><?php echo __('termination_letter'); ?>
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Termination Modal -->
                            <div class="modal fade" id="terminationModal" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?php echo __('terminate_employee'); ?></h5>
                                            <button type="button" class="close" data-dismiss="modal">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <form id="terminationForm">
                                            <div class="modal-body">
                                                <input type="hidden" id="terminateEmployeeId" name="employee_id">
                                                <div class="form-group">
                                                    <label><?php echo __('employee_name'); ?></label>
                                                    <p id="terminateEmployeeName" class="font-weight-bold"></p>
                                                </div>
                                                <div class="form-group">
                                                    <label for="termination_reason"><?php echo __('termination_reason'); ?></label>
                                                    <textarea class="form-control" id="termination_reason" name="reason" rows="3" required></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __('cancel'); ?></button>
                                                <button type="submit" class="btn btn-danger"><?php echo __('terminate_employee'); ?></button>
                                            </div>
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


    <?php include '../modals/employee/language_selection_modal.php'; ?>
    <?php include '../modals/employee/gurantor_modal.php'; ?>
    <?php include '../modals/employee/tawseah_modal.php'; ?>
    <?php include '../modals/employee/fine_modal.php'; ?>
    <?php include '../modals/employee/ikhtar_modal.php'; ?>
    <?php include '../modals/employee/termination_modal.php'; ?>
        <!-- Required Js -->
        <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
    <!-- Employee Management JS -->
    <script src="../js/employee/employee_management.js"></script>

<?php include '../includes/admin_footer.php'; ?>
