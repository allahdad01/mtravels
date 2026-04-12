<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Check session timeout
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    header('Location: ../login.php');
    exit();
}

// Create CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../includes/db.php';

// Fetch all categories
$stmt = $pdo->query("
    SELECT sec.id, sec.name, sec.description, COUNT(se.id) as expense_count
    FROM system_expense_categories sec
    LEFT JOIN system_expenses se ON se.category_id = sec.id
    GROUP BY sec.id, sec.name, sec.description
    ORDER BY sec.name ASC
");
$categories = $stmt->fetchAll();
?>

<?php include '../includes/header_super_admin.php'; ?>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- Breadcrumb -->
                <div class="page-header">
                    <div class="page-block">
                        <div class="row align-items-center">
                            <div class="col-md-12">
                                <div class="page-header-title">
                                    <h5 class="m-b-10">Expense Categories</h5>
                                </div>
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="#!">Financial</a></li>
                                    <li class="breadcrumb-item active"><a href="#!">Expense Categories</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="row">
                            <div class="col-xl-12">
                                <?php if (isset($_GET['success'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="feather icon-check-circle mr-2"></i>
                                    <?= htmlspecialchars($_GET['success']) ?>
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                </div>
                                <?php endif; ?>

                                <?php if (isset($_GET['error'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="feather icon-alert-circle mr-2"></i>
                                    <?= htmlspecialchars($_GET['error']) ?>
                                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                                </div>
                                <?php endif; ?>

                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5>System Expense Categories</h5>
                                        <button class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal">
                                            <i class="feather icon-plus mr-1"></i>Add Category
                                        </button>
                                    </div>

                                    <div class="card-body">
                                        <div class="row">
                                            <?php if (!empty($categories)): ?>
                                                <?php foreach ($categories as $cat): ?>
                                                <div class="col-md-6 col-lg-4 mb-3">
                                                    <div class="card border-left-primary shadow h-100">
                                                        <div class="card-body">
                                                            <div class="d-flex align-items-center">
                                                                <div class="flex-grow-1">
                                                                    <h6 class="text-primary font-weight-bold mb-2">
                                                                        <?= htmlspecialchars($cat['name']) ?>
                                                                    </h6>
                                                                    <p class="text-muted mb-2 small">
                                                                        <?= htmlspecialchars($cat['description'] ?? 'No description') ?>
                                                                    </p>
                                                                    <span class="badge badge-info">
                                                                        <?= $cat['expense_count'] ?> expenses
                                                                    </span>
                                                                </div>
                                                                <div class="ml-2">
                                                                    <button class="btn btn-sm btn-info" data-toggle="modal" 
                                                                            data-target="#editCategoryModal" 
                                                                            onclick="editCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name']) ?>', '<?= htmlspecialchars($cat['description'] ?? '') ?>')">
                                                                        <i class="feather icon-edit"></i>
                                                                    </button>
                                                                    <?php if ($cat['expense_count'] == 0): ?>
                                                                    <a href="delete_expense_category.php?id=<?= $cat['id'] ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" 
                                                                       class="btn btn-sm btn-danger" 
                                                                       onclick="return confirm('Delete this category?')">
                                                                        <i class="feather icon-trash-2"></i>
                                                                    </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                            <div class="col-12">
                                                <p class="text-center text-muted">No expense categories found</p>
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
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add Expense Category</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form action="create_expense_category.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Category Name *</label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="e.g., Server & Hosting">
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Describe what this category is for"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Edit Expense Category</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <form action="update_expense_category.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="id" id="edit_category_id">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Category Name *</label>
                            <input type="text" name="name" class="form-control" id="edit_category_name" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control" id="edit_category_desc" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
function editCategory(id, name, desc) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_category_name').value = name;
    document.getElementById('edit_category_desc').value = desc;
}
</script>

</body>
</html>
