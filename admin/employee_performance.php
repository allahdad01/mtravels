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

// Build query for employees with performance reviews
$query = "
    SELECT u.*, sm.base_salary, sm.currency as salary_currency,
           pr.id as review_id, pr.overall_rating, pr.review_date, pr.status as review_status,
           pr.period_start, pr.period_end
    FROM users u
    LEFT JOIN salary_management sm ON u.id = sm.user_id AND sm.tenant_id = u.tenant_id AND sm.branch_id = u.branch_id
    LEFT JOIN performance_reviews pr ON u.id = pr.user_id AND pr.tenant_id = u.tenant_id AND pr.branch_id = u.branch_id
        AND pr.id = (SELECT MAX(id) FROM performance_reviews WHERE user_id = u.id AND tenant_id = u.tenant_id AND branch_id = u.branch_id)
    WHERE u.tenant_id = ? AND u.branch_id = ? AND u.role != 'super_admin'
";

$params = [$tenant_id, $branch_id];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== 'all') {
    if ($status_filter === 'active') {
        $query .= " AND u.fired = 0";
    } elseif ($status_filter === 'terminated') {
        $query .= " AND u.fired = 1";
    }
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = __('performance_reviews');
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
                                            <h5 class="mb-0"><i class="feather icon-trending-up mr-2"></i><?php echo __('performance_reviews'); ?></h5>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <a href="employee_management.php" class="btn btn-outline-secondary btn-sm">
                                                <i class="feather icon-arrow-left mr-1"></i><?php echo __('back'); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Filters and Search -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <form method="GET" class="row g-3">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" name="search" placeholder="<?php echo __('search_employees'); ?>"
                                                    value="<?php echo htmlspecialchars($search); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-control" name="status">
                                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>><?php echo __('all_statuses'); ?></option>
                                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                                                    <option value="terminated" <?php echo $status_filter === 'terminated' ? 'selected' : ''; ?>><?php echo __('terminated'); ?></option>
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

                                <!-- Performance Overview -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h4 class="text-primary"><?php echo count(array_filter($employees, function($e) { return $e['review_id'] && $e['review_status'] == 'approved'; })); ?></h4>
                                                <p class="text-muted mb-0"><?php echo __('evaluated_employees'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <?php
                                                $ratings = array_filter(array_column($employees, 'overall_rating'));
                                                $avg_rating = count($ratings) > 0 ? array_sum($ratings) / count($ratings) : 0;
                                                ?>
                                                <h4 class="text-success"><?php echo number_format($avg_rating, 1); ?>/5.0</h4>
                                                <p class="text-muted mb-0"><?php echo __('average_rating'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h4 class="text-warning"><?php echo count(array_filter($employees, function($e) { return !$e['review_id']; })); ?></h4>
                                                <p class="text-muted mb-0"><?php echo __('pending_reviews'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card">
                                            <div class="card-body text-center">
                                                <h4 class="text-info"><?php echo count($employees); ?></h4>
                                                <p class="text-muted mb-0"><?php echo __('total_employees'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Employees Table -->
                                <div class="card">
                                    <div class="card-header">
                                        <h5><?php echo __('employees'); ?> (<?php echo count($employees); ?>)</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($employees)): ?>
                                            <div class="text-center py-5">
                                                <i class="feather icon-users text-muted" style="font-size: 48px;"></i>
                                                <h5 class="mt-3"><?php echo __('no_employees_found'); ?></h5>
                                                <p class="text-muted"><?php echo __('try_adjusting_filters'); ?></p>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th><?php echo __('employee'); ?></th>
                                                            <th><?php echo __('role'); ?></th>
                                                            <th><?php echo __('hire_date'); ?></th>
                                                            <th><?php echo __('status'); ?></th>
                                                            <th><?php echo __('performance_status'); ?></th>
                                                            <th><?php echo __('actions'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($employees as $employee): ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <img src="../assets/images/user/<?= htmlspecialchars(basename($employee['profile_pic'] ?: 'avatar-1.jpg')); ?>"
                                                                            class="rounded-circle mr-3" style="width: 40px; height: 40px; object-fit: cover;">
                                                                        <div>
                                                                            <h6 class="mb-0"><?php echo htmlspecialchars($employee['name']); ?></h6>
                                                                            <small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <span class="badge-primary"><?php echo htmlspecialchars(ucfirst($employee['role'] ?: 'N/A')); ?></span>
                                                                </td>
                                                                <td>
                                                                    <?php echo $employee['hire_date'] ? date('M d, Y', strtotime($employee['hire_date'])) : '-'; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($employee['fired']): ?>
                                                                        <span class="badge-danger"><?php echo __('terminated'); ?></span>
                                                                    <?php else: ?>
                                                                        <span class="badge-success"><?php echo __('active'); ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($employee['review_id'] && $employee['review_status'] == 'approved'): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <span class="badge-success mr-2">
                                                                                <?php echo $employee['overall_rating'] ? $employee['overall_rating'] . '/5' : __('evaluated'); ?>
                                                                            </span>
                                                                            <small class="text-muted">
                                                                                <?php echo $employee['review_date'] ? date('M d, Y', strtotime($employee['review_date'])) : ''; ?>
                                                                            </small>
                                                                        </div>
                                                                    <?php elseif ($employee['review_id'] && $employee['review_status'] == 'draft'): ?>
                                                                        <span class="badge-warning"><?php echo __('draft'); ?></span>
                                                                    <?php elseif ($employee['review_id'] && $employee['review_status'] == 'submitted'): ?>
                                                                        <span class="badge-info"><?php echo __('submitted'); ?></span>
                                                                    <?php else: ?>
                                                                        <span class="badge-secondary"><?php echo __('not_evaluated'); ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <div class="btn-group">
                                                                        <?php if ($employee['review_id']): ?>
                                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewReview(<?php echo $employee['review_id']; ?>)">
                                                                                <i class="feather icon-eye"></i> <?php echo __('view_review'); ?>
                                                                            </button>
                                                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editReview(<?php echo $employee['review_id']; ?>)">
                                                                                <i class="feather icon-edit"></i> <?php echo __('edit_review'); ?>
                                                                            </button>
                                                                        <?php else: ?>
                                                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="addReview(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>')">
                                                                                <i class="feather icon-plus"></i> <?php echo __('add_review'); ?>
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
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
    <!-- Performance Review Modals -->
    <div class="modal fade" id="viewReviewModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('performance_review_details'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="reviewDetails">
                    <!-- Review details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addReviewModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('add_performance_review'); ?></h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="performanceReviewForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('employee'); ?></label>
                                    <input type="text" class="form-control" id="employeeName" readonly>
                                    <input type="hidden" id="userId" name="user_id">
                                    <input type="hidden" id="reviewId" name="review_id">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('review_date'); ?></label>
                                    <input type="date" class="form-control" id="reviewDate" name="review_date" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('period_start'); ?></label>
                                    <input type="date" class="form-control" id="periodStart" name="period_start" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('period_end'); ?></label>
                                    <input type="date" class="form-control" id="periodEnd" name="period_end" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('overall_rating'); ?> (1-5)</label>
                                    <input type="number" class="form-control" id="overallRating" name="overall_rating" min="1" max="5" step="0.1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo __('reviewer'); ?></label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('comments'); ?></label>
                            <textarea class="form-control" id="comments" name="comments" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('achievements'); ?></label>
                            <textarea class="form-control" id="achievements" name="achievements" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('areas_for_improvement'); ?></label>
                            <textarea class="form-control" id="areasForImprovement" name="areas_for_improvement" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('goals'); ?></label>
                            <textarea class="form-control" id="goals" name="goals" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('recommendations'); ?></label>
                            <textarea class="form-control" id="recommendations" name="recommendations" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label><?php echo __('status'); ?></label>
                            <select class="form-control" id="reviewStatus" name="status">
                                <option value="draft"><?php echo __('draft'); ?></option>
                                <option value="submitted"><?php echo __('submitted'); ?></option>
                                <option value="approved"><?php echo __('approved'); ?></option>
                                <option value="rejected"><?php echo __('rejected'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo __('cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo __('save_review'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Required Js -->
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>

    <script>
    function viewReview(reviewId) {
        $.ajax({
            url: 'ajax/get_performance_review.php',
            type: 'GET',
            data: { review_id: reviewId },
            success: function(response) {
                $('#reviewDetails').html(response);
                $('#viewReviewModal').modal('show');
            },
            error: function() {
                alert('<?php echo __('error_loading_review'); ?>');
            }
        });
    }

    function addReview(userId, employeeName) {
        $('#performanceReviewForm')[0].reset();
        $('#userId').val(userId);
        $('#employeeName').val(employeeName);
        $('#reviewId').val('');
        $('#reviewDate').val(new Date().toISOString().split('T')[0]);
        $('#addReviewModal .modal-title').text('<?php echo __('add_performance_review'); ?>');
        $('#addReviewModal').modal('show');
    }

    function editReview(reviewId) {
        $.ajax({
            url: 'ajax/get_performance_review.php',
            type: 'GET',
            data: { review_id: reviewId, edit: 1 },
            success: function(response) {
                var data = JSON.parse(response);
                $('#userId').val(data.user_id);
                $('#employeeName').val(data.employee_name);
                $('#reviewId').val(data.id);
                $('#reviewDate').val(data.review_date);
                $('#periodStart').val(data.period_start);
                $('#periodEnd').val(data.period_end);
                $('#overallRating').val(data.overall_rating);
                $('#comments').val(data.comments);
                $('#achievements').val(data.achievements);
                $('#areasForImprovement').val(data.areas_for_improvement);
                $('#goals').val(data.goals);
                $('#recommendations').val(data.recommendations);
                $('#reviewStatus').val(data.status);
                $('#addReviewModal .modal-title').text('<?php echo __('edit_performance_review'); ?>');
                $('#addReviewModal').modal('show');
            },
            error: function() {
                alert('<?php echo __('error_loading_review'); ?>');
            }
        });
    }

    $('#performanceReviewForm').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);

        $.ajax({
            url: 'ajax/save_performance_review.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    $('#addReviewModal').modal('hide');
                    location.reload();
                } else {
                    alert(result.message || '<?php echo __('error_saving_review'); ?>');
                }
            },
            error: function() {
                alert('<?php echo __('error_saving_review'); ?>');
            }
        });
    });
    </script>
<?php include '../includes/admin_footer.php'; ?>