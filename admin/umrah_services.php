<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance', 'sales', 'umrah'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');
?>

<?php include '../includes/header.php'; ?>
<script src="../assets/plugins/jquery/js/jquery.min.js"></script>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/umrah/umrah-enhanced.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- Enhanced Page Header -->
                <div class="enhanced-page-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="page-title-wrapper">
                                    <i class="fas fa-bell page-icon"></i>
                                    <div>
                                        <h2 class="page-title"><?= __('umrah_services') ?></h2>
                                        <p class="page-subtitle"><?= __('manage_booked_services') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-gradient-primary" onclick="location.reload()">
                                    <i class="fas fa-sync mr-2"></i><?= __('refresh') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
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
                                <!-- Filters and Search -->
                                <div class="container-fluid px-4 mb-4">
                                    <div class="filters-wrapper">
                                        <!-- Filter Pills -->
                                        <div class="filter-pills">
                                            <a href="?service_type=" class="filter-pill <?= empty($serviceType) ? 'active' : '' ?>">
                                                <i class="fas fa-layer-group"></i>
                                                <span><?= __('all') ?></span>
                                            </a>
                                            <a href="?service_type=ticket" class="filter-pill <?= $serviceType === 'ticket' ? 'active' : '' ?>">
                                                <i class="fas fa-ticket-alt"></i>
                                                <span><?= __('ticket') ?></span>
                                            </a>
                                            <a href="?service_type=visa" class="filter-pill <?= $serviceType === 'visa' ? 'active' : '' ?>">
                                                <i class="fas fa-passport"></i>
                                                <span><?= __('visa') ?></span>
                                            </a>
                                            <a href="?service_type=hotel" class="filter-pill <?= $serviceType === 'hotel' ? 'active' : '' ?>">
                                                <i class="fas fa-hotel"></i>
                                                <span><?= __('hotel') ?></span>
                                            </a>
                                            <a href="?service_type=transport" class="filter-pill <?= $serviceType === 'transport' ? 'active' : '' ?>">
                                                <i class="fas fa-bus"></i>
                                                <span><?= __('transport') ?></span>
                                            </a>
                                            <a href="?service_type=<?= urlencode('ticket+visa') ?>" class="filter-pill <?= $serviceType === 'ticket+visa' ? 'active' : '' ?>">
                                                <i class="fas fa-ticket-alt"></i>
                                                <span>Ticket + Visa</span>
                                            </a>
                                            <a href="?service_type=<?= urlencode('ticket+hotel') ?>" class="filter-pill <?= $serviceType === 'ticket+hotel' ? 'active' : '' ?>">
                                                <i class="fas fa-ticket-alt"></i>
                                                <span>Ticket + Hotel</span>
                                            </a>
                                            <a href="?service_type=<?= urlencode('ticket+transport') ?>" class="filter-pill <?= $serviceType === 'ticket+transport' ? 'active' : '' ?>">
                                                <i class="fas fa-ticket-alt"></i>
                                                <span>Ticket + Transport</span>
                                            </a>
                                            <a href="?service_type=<?= urlencode('visa+services') ?>" class="filter-pill <?= $serviceType === 'visa+services' ? 'active' : '' ?>">
                                                <i class="fas fa-passport"></i>
                                                <span>Visa + Services</span>
                                            </a>
                                            <a href="?service_type=<?= urlencode('visa+hotel') ?>" class="filter-pill <?= $serviceType === 'visa+hotel' ? 'active' : '' ?>">
                                                <i class="fas fa-passport"></i>
                                                <span>Visa + Hotel</span>
                                            </a>
                                            <a href="?service_type=<?= urlencode('visa+transport') ?>" class="filter-pill <?= $serviceType === 'visa+transport' ? 'active' : '' ?>">
                                                <i class="fas fa-passport"></i>
                                                <span>Visa + Transport</span>
                                            </a>
                                            <a href="?service_type=<?= urlencode('hotel+transport') ?>" class="filter-pill <?= $serviceType === 'hotel+transport' ? 'active' : '' ?>">
                                                <i class="fas fa-hotel"></i>
                                                <span>Hotel + Transport</span>
                                            </a>
                                        </div>

                                        <!-- Enhanced Search -->
                                        <div class="search-wrapper">
                                            <form id="serviceSearchForm" method="GET" class="search-form">
                                                <div class="search-input-group">
                                                    <i class="fas fa-search search-icon"></i>
                                                    <input type="search"
                                                           placeholder="<?= __('search_services') ?>"
                                                           name="search"
                                                           value="<?= htmlspecialchars($search) ?>"
                                                           class="search-input"
                                                           aria-label="Search services">
                                                    <?php if (!empty($search)): ?>
                                                        <a href="?" class="clear-search">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="submit" class="search-button">
                                                        <?= __('search') ?>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Services Table -->
                                <div class="container-fluid px-4">
                                    <div class="table-responsive card">
                                        <table class="table table-striped table-hover mb-0" id="serviceTable">
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
                                                                                    $serviceTypeLabels = [
                                                                                        'all' => 'All Services',
                                                                                        'ticket+visa' => 'Ticket + Visa',
                                                                                        'ticket+hotel' => 'Ticket + Hotel',
                                                                                        'ticket+transport' => 'Ticket + Transport',
                                                                                        'visa+services' => 'Visa + Services',
                                                                                        'visa+hotel' => 'Visa + Hotel',
                                                                                        'visa+transport' => 'Visa + Transport',
                                                                                        'hotel+transport' => 'Hotel + Transport',
                                                                                    ];
                                                                                    $serviceTypeDisplay = $row['service_type'];
                                                                                    if (isset($serviceTypeLabels[$serviceTypeDisplay])) {
                                                                                        $serviceTypeDisplay = $serviceTypeLabels[$serviceTypeDisplay];
                                                                                    } elseif (strpos($serviceTypeDisplay, '+') !== false) {
                                                                                        $serviceTypeDisplay = ucwords(str_replace('+', ' + ', $serviceTypeDisplay));
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
                                                                        <span class="mb-2"><?= htmlspecialchars($row['supplier_name']) ?></span>
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
                                                                    <div class="empty-state">
                                                                        <div class="empty-state-icon">
                                                                            <i class="fas fa-search"></i>
                                                                        </div>
                                                                        <h3 class="text-muted mt-3">
                                                                            <?= !empty($search)
                                                                                ? sprintf(__('no_services_found_for_search'), htmlspecialchars($search))
                                                                                : __('no_services_available')
                                                                            ?>
                                                                        </h3>
                                                                        <?php if (!empty($search)): ?>
                                                                            <a href="umrah_services.php" class="btn btn-primary mt-3">
                                                                                <i class="fas fa-times mr-2"></i><?= __('clear_search') ?>
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

<!-- Required Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

<!-- Custom Scripts -->
<script>
    // Set CSRF token
    window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

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
<?php include '../includes/admin_footer.php'; ?>

</body>
</html>