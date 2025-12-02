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

<!-- Custom Styles -->
<style>
/* RTL Support */
[dir="rtl"] .modal-header .close {
    margin: -1rem auto -1rem -1rem;
    float: left;
}

[dir="rtl"] .btn-group > .btn:not(:last-child):not(.dropdown-toggle) {
    border-radius: 0 0.25rem 0.25rem 0;
}

[dir="rtl"] .btn-group > .btn:not(:first-child) {
    border-radius: 0.25rem 0 0 0.25rem;
}

/* Enhanced Table Styles */
.table td {
    vertical-align: middle;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

/* Role Badge Styles */
.badge-role {
    padding: 5px 12px;
    border-radius: 15px;
    font-weight: 500;
}

.badge-admin { background-color: #FF6B6B; color: white; }
.badge-finance { background-color: #4ECDC4; color: white; }
.badge-sales { background-color: #45B7D1; color: white; }
.badge-umrah { background-color: #96CEB4; color: white; }
.badge-staff { background-color: #6c757d; color: white; }

/* Modal Enhancements */
.modal-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-footer {
    background-color: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

/* Form Styling */
.form-control:focus {
    border-color: #4099ff;
    box-shadow: 0 0 0 0.2rem rgba(64, 153, 255, 0.25);
}

/* Avatar Upload */
.avatar-upload {
    position: relative;
    max-width: 120px;
    margin: 0 auto 1rem;
}

.avatar-upload img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.avatar-upload .upload-button {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #4099ff;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.avatar-upload .upload-button:hover {
    background-color: #2d7be3;
    transform: scale(1.1);
}

/* Search Box Enhancement */
.search-box {
    max-width: 300px;
}

.search-box .form-control {
    padding-left: 2.5rem;
}

.search-box .search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .btn-group {
        display: flex;
        flex-direction: column;
    }

    .btn-group .btn {
        margin-bottom: 0.25rem;
    }

    .card-header {
        flex-direction: column;
        gap: 1rem;
    }

    .search-box {
        max-width: 100%;
    }
}

/* Add default avatar fallback */
.user-avatar {
    background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="%23ccc" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>');
    background-size: cover;
    background-position: center;
}

/* Enhanced Tab Styles */
.nav-tabs {
    border-bottom: 2px solid #e9ecef;
}

.nav-tabs .nav-item {
    margin-bottom: -2px;
}

.nav-tabs .nav-link {
    border: none;
    border-bottom: 2px solid transparent;
    color: #6c757d;
    padding: 0.75rem 1rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
    border-color: transparent;
    color: #4099ff;
}

.nav-tabs .nav-link.active {
    color: #4099ff;
    background: transparent;
    border-bottom: 2px solid #4099ff;
}

.nav-tabs .nav-link i {
    margin-right: 5px;
}

/* Tab Content Animation */
.tab-content > .tab-pane {
    transition: all 0.3s ease-in-out;
}

.tab-content > .active {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Fired User Styles */
.table-danger {
    background-color: #f8d7da !important;
}

.table-danger td {
    color: #721c24 !important;
}

.table-danger .user-avatar {
    opacity: 0.6;
    filter: grayscale(100%);
}

.badge-danger {
    background-color: #dc3545 !important;
    color: white !important;
}
</style>

<!-- Apply gradient background to card headers matching the sidebar -->
<style>
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

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="main-content">
                                <div class="page-header">
                                    <div class="page-header-content">
                                        <h1><i class="feather icon-users mr-2"></i><?php echo $user_id ? __('manage_employee') : __('employee_management'); ?></h1>
                                        <p><?php echo $user_id ? __('manage_specific_employee_information') : __('manage_employee_records_and_information'); ?></p>
                                    </div>
                                    <div class="page-header-actions">
                                        <?php if (!$user_id): ?>
                                            <a href="add_employee.php" class="btn btn-primary">
                                                <i class="feather icon-user-plus mr-1"></i><?php echo __('add_employee'); ?>
                                            </a>
                                        <?php endif; ?>
                                        
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
                                                                              class="user-avatar" alt="User Avatar">
                                                                    </td>
                                                                    <td>
                                                                        <div class="font-weight-bold"><?php echo htmlspecialchars($employee['name']); ?></div>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                                                    <td>
                                                                        <span class="badge badge-role badge-<?php echo strtolower($employee['role']); ?>">
                                                                            <?php echo ucfirst(htmlspecialchars($employee['role'])); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></td>
                                                                    <td><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                                                                    <td>
                                                                        <span class="badge badge-success">
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
                                                                              class="user-avatar" alt="User Avatar">
                                                                    </td>
                                                                    <td>
                                                                        <div class="font-weight-bold"><?php echo htmlspecialchars($employee['name']); ?></div>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                                                    <td>
                                                                        <span class="badge badge-role badge-<?php echo strtolower($employee['role']); ?>">
                                                                            <?php echo ucfirst(htmlspecialchars($employee['role'])); ?>
                                                                        </span>
                                                                    </td>
                                                                    <td><?php echo htmlspecialchars($employee['phone'] ?? 'N/A'); ?></td>
                                                                    <td><?php echo date('M d, Y', strtotime($employee['created_at'])); ?></td>
                                                                    <td>
                                                                        <span class="badge badge-danger">
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

    <!-- Language Selection Modal -->
    <div class="modal fade" id="languageSelectionModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('select_agreement_language'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                <div>
                    <form id="agreementForm" onsubmit="generateAgreement(event)">
                        <div class="form-group">
                            <label for="rule"><?php echo __('rule'); ?></label>
                            <textarea type="text" class="form-control" id="rule" placeholder="<?php echo __('rule'); ?>"></textarea>
                        </div>
                    </form>
                </div>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateAgreement('en')">
                            <i class="feather icon-globe mr-2"></i> English
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateAgreement('fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateAgreement('ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?php echo __('cancel'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Guarantor Letter Language Selection Modal -->
    <div class="modal fade" id="guarantorLanguageModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('select_guarantor_letter_language'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateGuarantorLetter('fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateGuarantorLetter('ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?php echo __('cancel'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tawseah Language Selection Modal -->
    <div class="modal fade" id="tawseahModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('select_tawseah_language'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="tawseahForm" onsubmit="generateTawseah(event)">
                    <div class="form-group">
                    <label for="job_title"><?php echo __('job_title'); ?></label>
                    <input type="text" class="form-control" id="job_title" placeholder="<?php echo __('job_title'); ?>">
                    </div>
                    <div class="form-group">
                    <label for="takhaluf"><?php echo __('takhaluf'); ?></label>
                    <input type="text" class="form-control" id="takhaluf" placeholder="<?php echo __('takhaluf'); ?>">
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTawseah(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTawseah(event, 'ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?php echo __('cancel'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Fine Letter Language Selection Modal -->

    <div class="modal fade" id="fineLetterModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('select_fine_letter_language'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="fineLetterForm" onsubmit="generateFineLetter(event)">
                    <div class="form-group">
                    <label for="job_title"><?php echo __('job_title'); ?></label>
                    <input type="text" class="form-control" id="job_title_fine" placeholder="<?php echo __('job_title'); ?>">
                    </div>
                    <div class="form-group">
                    <label for="takhaluf"><?php echo __('takhaluf'); ?></label>
                    <input type="text" class="form-control" id="takhaluf_fine" placeholder="<?php echo __('takhaluf'); ?>">
                    </div>
                    <div class="form-group">
                    <label for="fine_amount"><?php echo __('fine_amount'); ?></label>
                    <input type="text" class="form-control" id="fine_amount" placeholder="<?php echo __('fine_amount'); ?>">
                    </div>
                    <div class="form-group">
                    <label for="currency"><?php echo __('currency'); ?></label>
                    <select class="form-control" id="currency">
                            <option value="AFS">AFS</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateFineLetter(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateFineLetter(event, 'ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?php echo __('cancel'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ikhtar Language Selection Modal -->
    <div class="modal fade" id="ikhtarModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('select_official_warning_language'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="ikhtarForm" onsubmit="generateIkhtar(event)">
                    <div class="form-group">
                    <label for="job_title"><?php echo __('job_title'); ?></label>
                    <input type="text" class="form-control" id="job_title_ikhtar" placeholder="<?php echo __('job_title'); ?>">
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateIkhtar(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateIkhtar(event, 'ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?php echo __('cancel'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Termination Letter Language Selection Modal -->
    <div class="modal fade" id="terminationLetterModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('select_termination_letter_language'); ?></h5>
                </div>
                <div class="modal-body">
                    <form id="terminationLetterForm" onsubmit="generateTerminationLetter(event)">
                    <div class="form-group">
                    <label for="job_title"><?php echo __('job_title'); ?></label>
                    <input type="text" class="form-control" id="job_title_termination" placeholder="<?php echo __('job_title'); ?>">
                    </div>
                    <div class="form-group">
                    <label for="termination_date"><?php echo __('termination_date'); ?></label>
                    <input type="date" class="form-control" id="termination_date" placeholder="<?php echo __('termination_date'); ?>">
                    </div>
                    </form>
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTerminationLetter(event, 'fa')">
                            <i class="feather icon-globe mr-2"></i> Dari
                        </a>
                        <a href="#" class="list-group-item list-group-item-action" onclick="generateTerminationLetter(event, 'ps')">
                            <i class="feather icon-globe mr-2"></i> Pashto
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <?php echo __('cancel'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

        <!-- Required Js -->
        <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
<script>
// Global toast function
function createToast(message, type = 'success') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.position = 'fixed';
        toastContainer.style.top = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '99999';
        document.body.appendChild(toastContainer);
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <div class="toast-message">${message}</div>
            <button type="button" class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;

    // Add to container
    toastContainer.appendChild(toast);

    // Force reflow to trigger animation
    toast.offsetHeight;

    // Show toast
    toast.classList.add('show');

    // Auto remove after delay
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);

    return toast;
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTables for both active and fired employees
    const activeEmployeesTable = $('#active-employees-table').DataTable({
        responsive: true,
        language: {
            url: '../assets/plugins/datatables/i18n/' + document.documentElement.lang + '.json'
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    const firedEmployeesTable = $('#fired-employees-table').DataTable({
        responsive: true,
        language: {
            url: '../assets/plugins/datatables/i18n/' + document.documentElement.lang + '.json'
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });

    // Initialize modals with proper backdrop handling
    $('.modal').on('show.bs.modal', function() {
        const zIndex = 1040 + (10 * $('.modal:visible').length);
        $(this).css('z-index', zIndex);
        setTimeout(() => {
            $('.modal-backdrop').not('.modal-stack')
                .css('z-index', zIndex - 1)
                .addClass('modal-stack');
        });
    });

    // Handle modal close
    $('.modal').on('hidden.bs.modal', function() {
        if ($('.modal:visible').length) {
            $('body').addClass('modal-open');
        }
    });
});

// Global variable to store user ID for language selection
let selectedUserId = null;
let selectedGuarantorUserId = null;
let selectedTawseahUserId = null;
let selectedIkhtarUserId = null;
let selectedFineLetterUserId = null;
let selectedTerminationLetterUserId = null;

// Function to show language selection modal
function showLanguageModal(userId) {
    selectedUserId = userId;
    $('#languageSelectionModal').modal('show');
}

// Function to show guarantor letter language selection modal
function showGuarantorLanguageModal(userId) {
    selectedGuarantorUserId = userId;
    $('#guarantorLanguageModal').modal('show');
}

// Function to show tawseah language selection modal
function showTawseahModal(userId) {
    selectedTawseahUserId = userId;
    $('#tawseahModal').modal('show');
}

// Function to show ikhtar language selection modal
function showIkhtarModal(userId) {
    selectedIkhtarUserId = userId;
    $('#ikhtarModal').modal('show');
}

// Function to show fine letter language selection modal
function showFineLetterModal(userId) {
    selectedFineLetterUserId = userId;
    $('#fineLetterModal').modal('show');
}

// Function to show termination letter language selection modal
function showTerminationLetterModal(userId) {
    selectedTerminationLetterUserId = userId;
    $('#terminationLetterModal').modal('show');
}

// Function to generate agreement based on selected language
function generateAgreement(language) {
    if (!selectedUserId) {
        createToast('<?php echo __('error_no_user_selected'); ?>', 'danger');
        return;
    }

    // Get the rule input value at the time of click
    const ruleValue = document.getElementById('rule').value;

    // Close the language selection modal
    $('#languageSelectionModal').modal('hide');

    // Determine the correct agreement generation URL based on language
    let agreementUrl = '';
    switch(language) {
        case 'en':
            agreementUrl = 'generate_user_agreement.php';
            break;
        case 'fa':
            agreementUrl = 'generate_user_dari_agreement.php';
            break;
        case 'ps':
            agreementUrl = 'generate_user_pashto_agreement.php';
            break;
        default:
            createToast('<?php echo __('error_invalid_language'); ?>', 'danger');
            return;
    }

    // Open the agreement in a new tab
    window.open(`${agreementUrl}?user_id=${selectedUserId}&rule=${encodeURIComponent(ruleValue)}`, '_blank');
}

function generateGuarantorLetter(language) {
    if (!selectedGuarantorUserId) {
        createToast('<?php echo __('error_no_user_selected'); ?>', 'danger');
        return;
    }

    // Close the language selection modal
    $('#guarantorLanguageModal').modal('hide');

    // Determine the correct guarantor letter generation URL based on language
    let guarantorLetterUrl = '';
    switch(language) {
        case 'fa':
            guarantorLetterUrl = 'generate_guarantor_letter.php';
            break;
        case 'ps':
            guarantorLetterUrl = 'generate_guarantor_pashto_letter.php';
            break;
        default:
            createToast('<?php echo __('error_invalid_language'); ?>', 'danger');
            return;
    }

    // Open the guarantor letter in a new tab
    window.open(`${guarantorLetterUrl}?user_id=${selectedGuarantorUserId}`, '_blank');
}

function generateTawseah(event, language) {
    event.preventDefault();

    if (!selectedTawseahUserId) {
        createToast('<?php echo __('error_no_user_selected'); ?>', 'danger');
        return;
    }

    // Get the takhaluf input value safely
    const takhalufValue = document.getElementById('takhaluf').value;
    const jobtitleValue = document.getElementById('job_title').value;

    // Close the modal
    $('#tawseahModal').modal('hide');

    // Determine URL
    let tawseahUrl = '';
    switch(language) {
        case 'fa':
            tawseahUrl = 'generate_tawseah.php';
            break;
        case 'ps':
            tawseahUrl = 'generate_tawseah_pashto.php';
            break;
        default:
            createToast('<?php echo __('error_invalid_language'); ?>', 'danger');
            return;
    }

    // Open in new tab with encoded value
    window.open(`${tawseahUrl}?user_id=${selectedTawseahUserId}&language=${language}&takhaluf=${encodeURIComponent(takhalufValue)}&job_title=${encodeURIComponent(jobtitleValue)}`, '_blank');
}

function generateIkhtar(event, language) {
    event.preventDefault();

    if (!selectedIkhtarUserId) {
        createToast('<?php echo __('error_no_user_selected'); ?>', 'danger');
        return;
    }

    const jobTitleInput = document.getElementById('job_title_ikhtar');
    if (!jobTitleInput) {
        createToast('Job title field not found.', 'danger');
        return;
    }

    const jobtitleValue = jobTitleInput.value.trim();
    if (!jobtitleValue) {
        createToast('<?php echo __('error_job_title_required'); ?>', 'warning');
        return;
    }

    $('#ikhtarModal').modal('hide');

    let ikhtarUrl = '';
    switch(language) {
        case 'fa':
            ikhtarUrl = 'generate_ikhtar.php';
            break;
        case 'ps':
            ikhtarUrl = 'generate_ikhtar_pashto.php';
            break;
        default:
            createToast('<?php echo __('error_invalid_language'); ?>', 'danger');
            return;
    }

    const finalUrl = `${ikhtarUrl}?user_id=${selectedIkhtarUserId}&language=${language}&job_title=${encodeURIComponent(jobtitleValue)}`;
    console.log('Opening:', finalUrl);
    window.open(finalUrl, '_blank');
}

function generateFineLetter(event, language) {
    event.preventDefault();

    if (!selectedFineLetterUserId) {
        createToast('<?php echo __('error_no_user_selected'); ?>', 'danger');
        return;
    }

    const jobTitleInput = document.getElementById('job_title_fine');
    if (!jobTitleInput) {
        createToast('Job title field not found.', 'danger');
        return;
    }
    const takhalufInput = document.getElementById('takhaluf_fine');
    if (!takhalufInput) {
        createToast('Takhaluf field not found.', 'danger');
        return;
    }

    const jobtitleValue = jobTitleInput.value.trim();
    if (!jobtitleValue) {
        createToast('<?php echo __('error_job_title_required'); ?>', 'warning');
        return;
    }

    const takhalufValue = takhalufInput.value.trim();
    if (!takhalufValue) {
        createToast('<?php echo __('error_takhaluf_required'); ?>', 'warning');
        return;
    }

    const fineAmountInput = document.getElementById('fine_amount');
    if (!fineAmountInput) {
        createToast('Fine amount field not found.', 'danger');
        return;
    }

    const fineAmountValue = fineAmountInput.value.trim();
    if (!fineAmountValue) {
        createToast('<?php echo __('error_fine_amount_required'); ?>', 'warning');
        return;
    }

    const currencyInput = document.getElementById('currency');
    if (!currencyInput) {
        createToast('Currency field not found.', 'danger');
        return;
    }

    const currencyValue = currencyInput.value.trim();
    if (!currencyValue) {
        createToast('<?php echo __('error_currency_required'); ?>', 'warning');
        return;
    }

    $('#fineLetterModal').modal('hide');

    let fineLetterUrl = '';
    switch(language) {
        case 'fa':
            fineLetterUrl = 'generate_fine.php';
            break;
        case 'ps':
            fineLetterUrl = 'generate_fine_pashto.php';
            break;
        default:
            createToast('<?php echo __('error_invalid_language'); ?>', 'danger');
            return;
    }

    const finalUrl = `${fineLetterUrl}?user_id=${selectedFineLetterUserId}&language=${language}&job_title=${encodeURIComponent(jobtitleValue)}&takhaluf=${encodeURIComponent(takhalufValue)}&fine_amount=${encodeURIComponent(fineAmountValue)}&currency=${encodeURIComponent(currencyValue)}`;
    console.log('Opening:', finalUrl);
    window.open(finalUrl, '_blank');
}

function generateTerminationLetter(event, language) {
    event.preventDefault();

    if (!selectedTerminationLetterUserId) {
        createToast('<?php echo __('error_no_user_selected'); ?>', 'danger');
        return;
    }

    const jobTitleInput = document.getElementById('job_title_termination');
    if (!jobTitleInput) {
        createToast('Job title field not found.', 'danger');
        return;
    }

    const jobtitleValue = jobTitleInput.value.trim();
    if (!jobtitleValue) {
        createToast('<?php echo __('error_job_title_required'); ?>', 'warning');
        return;
    }

    const terminationDateInput = document.getElementById('termination_date');
    if (!terminationDateInput) {
        createToast('Termination date field not found.', 'danger');
        return;
    }

    const terminationDateValue = terminationDateInput.value.trim();
    if (!terminationDateValue) {
        createToast('<?php echo __('error_termination_date_required'); ?>', 'warning');
        return;
    }

    $('#terminationLetterModal').modal('hide');

    let terminationLetterUrl = '';
    switch(language) {
        case 'fa':
            terminationLetterUrl = 'generate_termination.php';
            break;
        case 'ps':
            terminationLetterUrl = 'generate_termination_pashto.php';
            break;
        default:
            createToast('<?php echo __('error_invalid_language'); ?>', 'danger');
            return;
    }

    const finalUrl = `${terminationLetterUrl}?user_id=${selectedTerminationLetterUserId}&language=${language}&job_title=${encodeURIComponent(jobtitleValue)}&termination_date=${encodeURIComponent(terminationDateValue)}`;
    console.log('Opening:', finalUrl);
    window.open(finalUrl, '_blank');
}

function terminateEmployee(employeeId, employeeName) {
    $('#terminateEmployeeId').val(employeeId);
    $('#terminateEmployeeName').text(employeeName);
    $('#terminationModal').modal('show');
}

function reinstateEmployee(employeeId, employeeName) {
    if (confirm('<?php echo __('confirm_reinstate_employee'); ?>'.replace('{name}', employeeName))) {
        $.post('terminate_employee.php', {
            employee_id: employeeId,
            action: 'reinstate',
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                createToast(response.message || '<?php echo __('error_occurred'); ?>', 'danger');
            }
        })
        .fail(function() {
            createToast('<?php echo __('error_occurred'); ?>', 'danger');
        });
    }
}

$('#terminationForm').on('submit', function(e) {
    e.preventDefault();

    $.post('terminate_employee.php', {
        employee_id: $('#terminateEmployeeId').val(),
        reason: $('#termination_reason').val(),
        action: 'terminate',
        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
    })
    .done(function(response) {
        if (response.success) {
            $('#terminationModal').modal('hide');
            location.reload();
        } else {
            createToast(response.message || '<?php echo __('error_occurred'); ?>', 'danger');
        }
    })
    .fail(function() {
        createToast('<?php echo __('error_occurred'); ?>', 'danger');
    });
});

function showAddEmployeeModal() {
    // Redirect to add employee page
    window.location.href = 'add_employee.php';
}

// Add CSS to head
const style = document.createElement('style');
style.textContent = `
    #toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 99999;
    }
    .toast-notification {
        min-width: 300px;
        margin-bottom: 10px;
        padding: 15px;
        border-radius: 4px;
        font-size: 14px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease-in-out;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .toast-notification.show {
        opacity: 1;
        transform: translateX(0);
    }
    .toast-notification.success {
        background-color: #d4edda;
        border-color: #c3e6cb;
        color: #155724;
    }
    .toast-notification.danger {
        background-color: #f8d7da;
        border-color: #f5c6cb;
        color: #721c24;
    }
    .toast-notification.warning {
        background-color: #fff3cd;
        border-color: #ffeaa7;
        color: #856404;
    }
    .toast-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .toast-message {
        flex-grow: 1;
        margin-right: 10px;
    }
    .toast-close {
        background: none;
        border: none;
        font-size: 20px;
        font-weight: bold;
        color: inherit;
        cursor: pointer;
        padding: 0 5px;
    }
    .toast-close:hover {
        opacity: 0.7;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/admin_footer.php'; ?>