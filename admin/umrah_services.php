<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';
// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];


// Check if user is logged in
if (!isset($_SESSION['user_id'])  || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="css/modal-styles.css">
<link rel="stylesheet" href="css/ticket-form.css">
<link rel="stylesheet" href="css/umrah-management.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- [ Main Content ] start -->
    <div class="pcoded-main-container">
        <div class="pcoded-wrapper">
            <div class="pcoded-content">
                <div class="pcoded-inner-content">
                    <!-- [ breadcrumb ] start -->
                    <div class="page-header">
                        <div class="page-block">
                            <div class="row align-items-center">
                                <div class="col-md-12">
                                    <div class="page-header-title">
                                        <h5 class="m-b-10"><?= __('umrah_services') ?></h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('umrah') ?></a></li>
                                        <li class="breadcrumb-item"><a href="javascript:"><?= __('services') ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ breadcrumb ] end -->
                    <div class="main-body">
                        <div class="page-wrapper">
                            <!-- [ Main Content ] start -->
                            <div class="row">
                                <!-- body -->
                                <?php
                                    // Search and Pagination setup
                                    $resultsPerPage = 10; // Number of services per page
                                    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                                    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                                    $serviceType = isset($_GET['service_type']) ? trim($_GET['service_type']) : '';
                                    $offset = ($page - 1) * $resultsPerPage;

                                    // ---------- COUNT QUERY ----------
                                    $countSql = "SELECT COUNT(*) as total
                                                FROM umrah_booking_services ubs
                                                LEFT JOIN umrah_bookings ub ON ubs.booking_id = ub.booking_id
                                                LEFT JOIN families f ON ub.family_id = f.family_id
                                                LEFT JOIN suppliers s ON ubs.supplier_id = s.id
                                                WHERE f.tenant_id = ? AND f.branch_id = ?
                                                AND ub.tenant_id = ? AND ub.branch_id = ?
                                                AND ubs.tenant_id = ? AND ubs.branch_id = ?";

                                    $countParams = [$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id];
                                    $countTypes = "iiiiii";

                                    // Add filters for count
                                    if (!empty($serviceType)) {
                                        $countSql .= " AND ubs.service_type = ?";
                                        $countParams[] = $serviceType;
                                        $countTypes .= "s";
                                    }

                                    if (!empty($search)) {
                                        $countSql .= " AND (
                                            ub.name LIKE ? OR
                                            f.head_of_family LIKE ? OR
                                            s.name LIKE ?
                                        )";
                                        $searchTerm = "%$search%";
                                        $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm]);
                                        $countTypes .= "sss";
                                    }

                                    $countStmt = $pdo->prepare($countSql);
                                    $countStmt->execute($countParams);
                                    $totalServices = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                                    $totalPages = ceil($totalServices / $resultsPerPage);

                                    // ---------- MAIN QUERY ----------
                                    $sqlServices = "SELECT ubs.*, ub.name as member_name, f.head_of_family, f.package_type,
                                                    s.name as supplier_name, c.name as client_name
                                                FROM umrah_booking_services ubs
                                                LEFT JOIN umrah_bookings ub ON ubs.booking_id = ub.booking_id
                                                LEFT JOIN families f ON ub.family_id = f.family_id
                                                LEFT JOIN suppliers s ON ubs.supplier_id = s.id
                                                LEFT JOIN clients c ON ub.sold_to = c.id
                                                WHERE f.tenant_id = ? AND f.branch_id = ?
                                                AND ub.tenant_id = ? AND ub.branch_id = ?
                                                AND ubs.tenant_id = ? AND ubs.branch_id = ?";

                                    $servicesParams = [$tenant_id, $branch_id, $tenant_id, $branch_id, $tenant_id, $branch_id];
                                    $servicesTypes = "iiiiii";

                                    // Add filters for main query
                                    if (!empty($serviceType)) {
                                        $sqlServices .= " AND ubs.service_type = ?";
                                        $servicesParams[] = $serviceType;
                                        $servicesTypes .= "s";
                                    }

                                    if (!empty($search)) {
                                        $sqlServices .= " AND (
                                            ub.name LIKE ? OR
                                            f.head_of_family LIKE ? OR
                                            s.name LIKE ?
                                        )";
                                        $searchTerm = "%$search%";
                                        $servicesParams = array_merge($servicesParams, [$searchTerm, $searchTerm, $searchTerm]);
                                        $servicesTypes .= "sss";
                                    }

                                    $sqlServices .= " ORDER BY ubs.created_at DESC LIMIT ? OFFSET ?";
                                    $servicesParams[] = $resultsPerPage;
                                    $servicesParams[] = $offset;
                                    $servicesTypes .= "ii";

                                    $servicesStmt = $pdo->prepare($sqlServices);
                                    $servicesStmt->execute($servicesParams);
                                    $resultServices = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <!-- Display Services -->
                                <div class="container-fluid px-4">
                                    <div class="card umrah-card shadow-lg border-0 mb-4">
                                        <div class="card-header bg-primary text-white py-3">
                                            <div class="container-fluid px-0">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h4 class="mb-0 font-weight-bold"><?= __('booked_services') ?></h4>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <!-- Filters and Search Section -->
                                            <div class="p-3 border-bottom">
                                                <div class="container-fluid px-0">
                                                    <div class="row align-items-center">
                                                        <!-- Filter Tabs -->
                                                        <div class="col-md-6 mb-3 mb-md-0">
                                                            <div class="bg-light rounded-pill p-1">
                                                                <ul class="nav nav-pills nav-fill">
                                                                    <li class="nav-item">
                                                                        <a class="nav-link py-1 px-3<?= empty($serviceType) ? ' active' : '' ?>"
                                                                           href="?service_type="
                                                                           style="border-radius: 50px;">
                                                                            <?= __('all') ?>
                                                                        </a>
                                                                    </li>
                                                                    <li class="nav-item">
                                                                        <a class="nav-link py-1 px-3<?= $serviceType === 'ticket' ? ' active' : '' ?>"
                                                                           href="?service_type=ticket"
                                                                           style="border-radius: 50px;">
                                                                            <?= __('ticket') ?>
                                                                        </a>
                                                                    </li>
                                                                    <li class="nav-item">
                                                                        <a class="nav-link py-1 px-3<?= $serviceType === 'visa' ? ' active' : '' ?>"
                                                                           href="?service_type=visa"
                                                                           style="border-radius: 50px;">
                                                                            <?= __('visa') ?>
                                                                        </a>
                                                                    </li>
                                                                    <li class="nav-item">
                                                                        <a class="nav-link py-1 px-3<?= $serviceType === 'hotel' ? ' active' : '' ?>"
                                                                           href="?service_type=hotel"
                                                                           style="border-radius: 50px;">
                                                                            <?= __('hotel') ?>
                                                                        </a>
                                                                    </li>
                                                                    <li class="nav-item">
                                                                        <a class="nav-link py-1 px-3<?= $serviceType === 'transport' ? ' active' : '' ?>"
                                                                           href="?service_type=transport"
                                                                           style="border-radius: 50px;">
                                                                            <?= __('transport') ?>
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>

                                                        <!-- Search -->
                                                        <div class="col-md-6">
                                                            <div class="d-flex align-items-center justify-content-end">
                                                                <form id="serviceSearchForm" method="GET" class="d-flex">
                                                                    <div class="input-group input-group-sm">
                                                                        <input type="search"
                                                                               class="form-control form-control-sm"
                                                                               placeholder="<?= __('search_services') ?>"
                                                                               name="search"
                                                                               value="<?= htmlspecialchars($search) ?>"
                                                                               aria-label="Search services">
                                                                        <div class="input-group-append">
                                                                            <button class="btn btn-outline-secondary" type="submit">
                                                                                <i class="feather icon-search"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover umrah-table mb-0" id="serviceTable">
                                                    <thead class="thead-light">
                                                        <tr>
                                                            <th class="text-left pl-4">
                                                                <?= __('service_details') ?>
                                                            </th>
                                                            <th>
                                                                <?= __('member_info') ?>
                                                            </th>
                                                            <th>
                                                                <?= __('supplier') ?>
                                                            </th>
                                                            <th>
                                                                <?= __('pricing') ?>
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php if (!empty($resultServices)) {
                                                            foreach ($resultServices as $row) { ?>
                                                                <tr>
                                                                    <td class="pl-4">
                                                                        <div class="d-flex align-items-center">
                                                                            <div>
                                                                                <h6 class="mb-1 font-weight-bold">
                                                                                    <?php
                                                                                    $serviceTypeDisplay = $row['service_type'];
                                                                                    if ($row['service_type'] == 'all') {
                                                                                        $serviceTypeDisplay = 'All Services';
                                                                                    }
                                                                                    echo htmlspecialchars($serviceTypeDisplay);
                                                                                    ?>
                                                                                </h6>
                                                                                <div class="text-muted small">
                                                                                    <i class="feather icon-calendar mr-1"></i>Booked: <?= htmlspecialchars($row['created_at']) ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-user mr-1"></i><strong>Member:</strong> <?= htmlspecialchars($row['member_name']) ?>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-users mr-1"></i><strong>Family:</strong> <?= htmlspecialchars($row['head_of_family']) ?>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-package mr-1"></i><strong>Package:</strong> <?= htmlspecialchars($row['package_type']) ?>
                                                                        </div>
                                                                        <div class="text-muted small">
                                                                            <i class="feather icon-user-check mr-1"></i><strong>Client:</strong> <?= htmlspecialchars($row['client_name']) ?>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge badge-soft-info mb-2"><?= htmlspecialchars($row['supplier_name']) ?></span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="financial-summary">
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-muted">Base:</span>
                                                                                <strong><?= htmlspecialchars($row['base_price'] . ' ' . $row['currency']) ?></strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between mb-1">
                                                                                <span class="text-success">Sold:</span>
                                                                                <strong class="text-success"><?= htmlspecialchars($row['sold_price'] . ' ' . $row['currency']) ?></strong>
                                                                            </div>
                                                                            <div class="d-flex justify-content-between">
                                                                                <span class="text-primary">Profit:</span>
                                                                                <strong class="text-primary"><?= htmlspecialchars($row['profit'] . ' ' . $row['currency']) ?></strong>
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php }
                                                        } else { ?>
                                                            <tr>
                                                                <td colspan="4" class="text-center py-5">
                                                                    <div class="d-flex flex-column align-items-center">
                                                                        <i class="feather icon-search text-muted" style="font-size: 4rem;"></i>
                                                                        <h5 class="text-muted mt-3">
                                                                            <?= !empty($search)
                                                                                ? sprintf(__('no_services_found_for_search'), htmlspecialchars($search))
                                                                                : __('no_services_available')
                                                                            ?>
                                                                        </h5>
                                                                        <?php if (!empty($search)): ?>
                                                                            <a href="umrah_services.php" class="btn btn-primary mt-3">
                                                                                <i class="feather icon-x-circle mr-2"></i><?= __('clear_search') ?>
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Pagination -->
                                            <nav aria-label="Services pagination" class="p-3">
                                                <ul class="pagination justify-content-center mb-0">
                                                    <?php
                                                    // Preserve search and filter parameters in pagination links
                                                    $searchParam = !empty($search) ? "&search=" . urlencode($search) : "";
                                                    $serviceTypeParam = !empty($serviceType) ? "&service_type=" . urlencode($serviceType) : "";

                                                    if ($page > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $page - 1 . $searchParam . $serviceTypeParam ?>" aria-label="Previous">
                                                                <span aria-hidden="true">&laquo;</span>
                                                                <span class="sr-only"><?= __('previous') ?></span>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php
                                                    // Show page numbers
                                                    $startPage = max(1, $page - 2);
                                                    $endPage = min($totalPages, $page + 2);

                                                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                            <a class="page-link" href="?page=<?= $i . $searchParam . $serviceTypeParam ?>"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>

                                                    <?php if ($page < $totalPages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?page=<?= $page + 1 . $searchParam . $serviceTypeParam ?>" aria-label="Next">
                                                                <span aria-hidden="true">&raquo;</span>
                                                                <span class="sr-only"><?= __('next') ?></span>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                                <div class="text-center text-muted mt-2">
                                                    <?= sprintf(__('showing_page_x_of_y'), $page, $totalPages) ?>
                                                    <span class="ml-2"><?= sprintf(__('total_services_x'), $totalServices) ?></span>
                                                </div>
                                            </nav>
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

    <!-- Required Js -->
    <script src="../assets/plugins/jquery/js/jquery.min.js"></script>
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <!-- Custom Scripts -->
    <script>
        // Toast notification function
        function showToast(type, message) {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });

            Toast.fire({
                icon: type,
                title: message
            });
        }
    </script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>