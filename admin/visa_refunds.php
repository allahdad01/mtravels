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
$allowed_roles = ['admin', 'finance', 'sales'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    error_log("Unauthorized access attempt to dashboard: " . ($_SESSION['user_id'] ?? 'unknown') . " - Role: " . ($_SESSION['role'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Database connection
require_once('../includes/db.php');

$canEdit = in_array($_SESSION['role'], ['admin', 'finance']);

// Pagination and search setup
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page   = isset($_GET['page'])   ? intval($_GET['page']) : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

// Build search + tenant condition
$searchCondition = " WHERE r.tenant_id = ? AND r.branch_id = ?";
$params = [$tenant_id, $branch_id];
$types  = "ii";

if (!empty($search)) {
    $searchCondition .= " AND (
        v.applicant_name LIKE ? OR
        v.passport_number LIKE ? OR
        v.country LIKE ?
    )";
    for ($i = 0; $i < 3; $i++) {
        $params[] = "%$search%";
        $types   .= "s";
    }
}

/* COUNT */
$totalRecordsQuery = "SELECT COUNT(*) as total FROM visa_refunds r LEFT JOIN visa_applications v ON r.visa_id = v.id $searchCondition";
$stmt = $pdo->prepare($totalRecordsQuery);
foreach ($params as $index => $param) {
    $stmt->bindParam($index + 1, $params[$index], is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$totalRecords = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages   = ceil($totalRecords / $recordsPerPage);

/* MAIN REFUNDS QUERY */
$refundsQuery = "SELECT r.*, v.applicant_name, v.passport_number, v.country, v.currency, v.sold_to, c.client_type, u.name as created_by, m.name as account_name
                FROM visa_refunds r
                LEFT JOIN visa_applications v ON r.visa_id = v.id
                LEFT JOIN clients c ON v.sold_to = c.id
                LEFT JOIN users u ON r.processed_by = u.id
                LEFT JOIN main_account_transactions t ON r.transaction_id = t.id
                LEFT JOIN main_account m ON t.main_account_id = m.id
                $searchCondition
                AND (v.id IS NULL OR v.branch_id = ?)
                AND (c.id IS NULL OR c.branch_id = ?)
                AND (u.id IS NULL OR u.branch_id = ?)
                AND (t.id IS NULL OR t.branch_id = ?)
                AND (m.id IS NULL OR m.branch_id = ?)
                ORDER BY r.refund_date DESC
                LIMIT ? OFFSET ?";

$paramsWithLimit   = $params;
$paramsWithLimit[] = $branch_id;
$paramsWithLimit[] = $branch_id; // clients branch_id
$paramsWithLimit[] = $branch_id;
$paramsWithLimit[] = $branch_id;
$paramsWithLimit[] = $branch_id;
$paramsWithLimit[] = $recordsPerPage;
$paramsWithLimit[] = $offset;

$stmt = $pdo->prepare($refundsQuery);
foreach ($paramsWithLimit as $index => $param) {
    $stmt->bindParam($index + 1, $paramsWithLimit[$index], is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── HELPERS ── */
function getRefundStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'full': return 'badge--rejected';
        case 'partial': return 'badge--pending';
        default: return 'badge--default';
    }
}

function getRefundStatusDotColor($status) {
    switch (strtolower($status)) {
        case 'full': return 'var(--red)';
        case 'partial': return 'var(--amber)';
        default: return 'var(--text-3)';
    }
}
?>

<link rel="stylesheet" href="../css/ticket/ticket_styles.css">
<link rel="stylesheet" href="../css/ticket/ticket-components.css">
<link rel="stylesheet" href="../css/ticket/ticket-form.css">
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/visa/visa.css">

<?php include '../includes/header.php'; ?>

<style>
/* ── CSS VARIABLES ─────────────────────────────────────── */
:root {
    --bg:        #f0f2f7;
    --surface:   #ffffff;
    --border:    #e4e8f0;
    --text-1:    #111827;
    --text-2:    #4b5563;
    --text-3:    #9ca3af;
    --blue:      #3b82f6;
    --blue-lt:   #eff6ff;
    --green:     #10b981;
    --green-lt:  #ecfdf5;
    --amber:     #f59e0b;
    --amber-lt:  #fffbeb;
    --red:       #ef4444;
    --red-lt:    #fef2f2;
    --grad:      linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);
    --shadow-sm: 0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 16px rgba(0,0,0,.08),0 2px 6px rgba(0,0,0,.05);
    --r:         14px;
    --r-sm:      8px;
}

/* ── PAGE HEADER ───────────────────────────────────────── */
.visa-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 14px;
}

.visa-page-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.visa-page-title-icon {
    width: 44px; height: 44px;
    background: var(--grad);
    border-radius: var(--r-sm);
    display: grid; place-items: center;
    color: #fff;
    font-size: 18px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(64,153,255,.35);
}

.visa-page-title h1 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-1);
    line-height: 1.2;
    margin: 0;
}

.visa-page-title p {
    font-size: 13px;
    color: var(--text-3);
    margin: 2px 0 0;
}

.visa-header-actions { display: flex; gap: 10px; align-items: center; }

/* ── SEARCH BAR ────────────────────────────────────────── */
.visa-search-bar {
    background: var(--surface);
    border-radius: var(--r);
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    margin-bottom: 18px;
    flex-wrap: wrap;
}

.visa-search-input-wrap {
    position: relative;
    flex: 1;
    min-width: 220px;
}

.visa-search-input-wrap i {
    position: absolute;
    left: 10px; top: 50%;
    transform: translateY(-50%);
    color: var(--text-3);
    font-size: 14px;
}

#searchInput {
    width: 100%;
    padding: 8px 8px 8px 36px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    color: var(--text-1);
    background: var(--surface);
}

#searchInput:focus {
    outline: none;
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(59,130,246,.1);
}

/* ── FILTER CHIPS ──────────────────────────────────────── */
.visa-filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
}

.visa-chip {
    padding: 8px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    background: var(--border);
    color: var(--text-2);
    transition: all .2s ease;
}

.visa-chip:hover {
    background: var(--blue-lt);
    color: var(--blue);
}

.visa-chip.active {
    background: var(--blue);
    color: white;
}

/* ── CARD LAYOUT ───────────────────────────────────────── */
.visa-card-list {
    display: grid;
    gap: 14px;
    margin-bottom: 22px;
}

.visa-card {
    background: var(--surface);
    border-radius: var(--r);
    padding: 18px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-sm);
    transition: all .2s ease;
    display: flex;
    gap: 18px;
}

.visa-card:hover {
    border-color: var(--blue);
    box-shadow: var(--shadow-md);
}

.visa-card__left { flex: 1; min-width: 0; }
.visa-card__right { display: flex; flex-direction: column; align-items: flex-end; gap: 12px; }

.visa-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 12px;
}

.vc-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-1);
    margin: 0;
}

.vc-subtitle {
    font-size: 13px;
    color: var(--text-3);
    margin: 4px 0 0;
}

.vc-status {
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.vc-status::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: block;
}

.badge--approved {
    background: var(--green-lt);
    color: var(--green);
}

.badge--approved::before {
    background: var(--green);
}

.badge--pending {
    background: var(--amber-lt);
    color: var(--amber);
}

.badge--pending::before {
    background: var(--amber);
}

.badge--rejected {
    background: var(--red-lt);
    color: var(--red);
}

.badge--rejected::before {
    background: var(--red);
}

.badge--default {
    background: #f3f4f6;
    color: var(--text-3);
}

.badge--default::before {
    background: var(--text-3);
}

.visa-card__meta {
    display: flex;
    gap: 12px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.vc-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #f3f4f6;
    border-radius: 6px;
    font-size: 12px;
    color: var(--text-2);
}

.vc-pill i {
    font-size: 12px;
    color: var(--text-3);
}

.visa-card__footer {
    display: flex;
    gap: 16px;
    margin-top: 12px;
    font-size: 13px;
    color: var(--text-3);
    flex-wrap: wrap;
}

.vc-date {
    display: flex;
    align-items: center;
    gap: 6px;
}

.vc-date i {
    font-size: 14px;
}

.vc-date__label { font-weight: 500; }

.vc-created {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--text-2);
}

.vc-created i {
    font-size: 14px;
}

.vc-amount {
    text-align: right;
}

.vc-amount__label {
    font-size: 12px;
    color: var(--text-3);
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.vc-amount__value {
    display: flex;
    align-items: baseline;
    justify-content: flex-end;
    gap: 4px;
    margin-top: 4px;
    font-size: 18px;
    font-weight: 700;
    color: var(--text-1);
}

.vc-amount__currency {
    font-size: 14px;
    color: var(--text-3);
}

.visa-card__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.vc-btn {
    padding: 7px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-2);
    cursor: pointer;
    transition: all .2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.vc-btn:hover {
    border-color: var(--blue);
    color: var(--blue);
    background: var(--blue-lt);
}

.vc-btn--success {
    border-color: var(--green);
    color: var(--green);
}

.vc-btn--success:hover {
    background: var(--green-lt);
}

.vc-btn--warn {
    border-color: var(--amber);
    color: var(--amber);
}

.vc-btn--warn:hover {
    background: var(--amber-lt);
}

.vc-btn--danger {
    border-color: var(--red);
    color: var(--red);
}

.vc-btn--danger:hover {
    background: var(--red-lt);
}

.vc-btn--disabled {
    opacity: .5;
    cursor: not-allowed;
}

.vc-btn--disabled:hover {
    border-color: var(--border);
    color: var(--text-3);
    background: var(--surface);
}

.vc-btn i {
    font-size: 13px;
}

/* ── EMPTY STATE ───────────────────────────────────────── */
.visa-empty-state {
    text-align: center;
    padding: 60px 20px;
}

.visa-empty-icon {
    font-size: 48px;
    color: var(--text-3);
    margin-bottom: 12px;
}

.visa-empty-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-1);
    margin: 0;
}

.visa-empty-text {
    font-size: 14px;
    color: var(--text-3);
    margin: 8px 0 0;
}

/* ── PAGINATION ────────────────────────────────────────── */
.visa-pagination-bar {
    background: var(--surface);
    border-radius: var(--r);
    padding: 16px 18px;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
}

.visa-pagination-info {
    font-size: 13px;
    color: var(--text-3);
    margin: 0;
}

.visa-pagination {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    gap: 6px;
    align-items: center;
}

.visa-pagination li {
    display: flex;
}

.visa-pagination a,
.visa-pagination span {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text-2);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all .2s ease;
    cursor: pointer;
}

.visa-pagination a:hover {
    border-color: var(--blue);
    background: var(--blue-lt);
    color: var(--blue);
}

.visa-pagination li.active a {
    background: var(--blue);
    border-color: var(--blue);
    color: white;
}

.visa-pagination li.disabled a,
.visa-pagination li.disabled span {
    opacity: .5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .visa-card {
        flex-direction: column;
    }
    .visa-card__right {
        align-items: flex-start;
    }
    .visa-page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .vc-amount {
        text-align: left;
    }
    .vc-amount__value {
        justify-content: flex-start;
    }
}
</style>

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
                                <ul class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                                    <li class="breadcrumb-item"><a href="visa.php"><?= __('visa_management') ?></a></li>
                                    <li class="breadcrumb-item"><a href="javascript:"><?= __('visa_refunds') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- PAGE HEADER -->
                        <div class="visa-page-header">
                            <div class="visa-page-title">
                                <div class="visa-page-title-icon">
                                    <i class="feather icon-refresh-cw"></i>
                                </div>
                                <div>
                                    <h1><?= __('visa_refunds') ?></h1>
                                    <p><?= __('manage_and_track_visa_refunds') ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- SEARCH BAR -->
                        <div class="visa-search-bar">
                            <div class="visa-search-input-wrap">
                                <i class="feather icon-search"></i>
                                <input type="text" id="searchInput" placeholder="<?= __('search_by_applicant_passport_country') ?>..."
                                    value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>

                        <!-- REFUNDS LIST -->
                        <?php if (empty($refunds)): ?>
                        <div class="visa-empty-state">
                            <div class="visa-empty-icon">
                                <i class="feather icon-inbox"></i>
                            </div>
                            <h3 class="visa-empty-title"><?= __('no_visa_refunds_have_been_processed_yet') ?></h3>
                            <p class="visa-empty-text"><?= __('refunds_will_appear_here') ?></p>
                        </div>
                        <?php else: ?>
                        <div class="visa-card-list">
                        <?php
                        $counter = 1;
                        foreach ($refunds as $refund):
                            $status = $refund['refund_type'] ?? 'partial';
                            $isFullRefund = strtolower($status) === 'full';
                        ?>

                            <div class="visa-card" data-status="<?= htmlspecialchars($status) ?>">
                                <!-- LEFT -->
                                <div class="visa-card__left">
                                    <div class="visa-card__header">
                                        <div>
                                            <h3 class="vc-title">
                                                <i class="feather icon-passport"></i> <?= htmlspecialchars($refund['applicant_name'] ?? 'N/A') ?>
                                            </h3>
                                            <p class="vc-subtitle">Visa Refund #<?= $refund['id'] ?></p>
                                        </div>
                                        <span class="vc-status <?= getRefundStatusBadgeClass($status) ?>">
                                            <?= ucfirst($status) ?> <?= __('refund') ?>
                                        </span>
                                    </div>

                                    <!-- Row 2: Pills -->
                                    <div class="visa-card__meta">
                                        <span class="vc-pill">
                                            <i class="feather icon-passport"></i>
                                            <?= htmlspecialchars($refund['passport_number'] ?? 'N/A') ?>
                                        </span>
                                        <span class="vc-pill">
                                            <i class="feather icon-map-pin"></i>
                                            <?= htmlspecialchars($refund['country'] ?? 'N/A') ?>
                                        </span>
                                        <span class="vc-pill">
                                            <i class="feather icon-file-text"></i>
                                            <?= htmlspecialchars($refund['reason'] ?? 'N/A') ?>
                                        </span>
                                    </div>

                                    <!-- Row 3: dates + created-by -->
                                    <div class="visa-card__footer">
                                        <div class="vc-date">
                                            <i class="feather icon-calendar"></i>
                                            <span class="vc-date__label"><?= __('refund_date') ?></span>
                                            <span class="vc-date__val"><?= htmlspecialchars(date('M d, Y', strtotime($refund['refund_date']))) ?></span>
                                        </div>
                                        <?php if (!empty($refund['created_by'])): ?>
                                        <div class="vc-created">
                                            <i class="feather icon-user-check"></i>
                                            <?= __('by') ?> <strong><?= htmlspecialchars($refund['created_by']) ?></strong>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- RIGHT -->
                                <div class="visa-card__right">
                                    <div class="vc-amount">
                                        <div class="vc-amount__label"><?= __('refund_amount') ?></div>
                                        <div class="vc-amount__value">
                                            <span class="vc-amount__currency"><?= htmlspecialchars($refund['currency'] ?? 'N/A') ?></span>
                                            <?= number_format($refund['refund_amount'], 2) ?>
                                        </div>
                                    </div>

                                    <div class="visa-card__actions">
                                         <!-- Process -->
                                         <?php if ($refund['processed'] == 0 && $canEdit && strtolower($refund['client_type'] ?? 'regular') !== 'regular'): ?>
                                         <button class="vc-btn vc-btn--success" onclick="processRefundTransaction(<?= $refund['id'] ?>, '<?= htmlspecialchars($refund['applicant_name']) ?>')">
                                             <i class="feather icon-check"></i> <?= __('process') ?>
                                         </button>
                                         <?php endif; ?>

                                        <!-- Print Agreement -->
                                        <button class="vc-btn" onclick="printRefundAgreement(<?= $refund['id'] ?>)">
                                            <i class="feather icon-print"></i> <?= __('print') ?>
                                        </button>

                                        <!-- Delete -->
                                        <?php if ($canEdit): ?>
                                        <button class="vc-btn vc-btn--danger" onclick="deleteRefund(<?= $refund['id'] ?>, '<?= htmlspecialchars($refund['applicant_name']) ?>')">
                                            <i class="feather icon-trash-2"></i> <?= __('delete') ?>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        <?php
                            $counter++;
                        endforeach;
                        ?>
                        </div><!-- end .visa-card-list -->
                        <?php endif; ?>

                        <!-- PAGINATION -->
                        <div class="visa-pagination-bar">
                            <p class="visa-pagination-info">
                                <?= __('showing') ?>
                                <?= (($page - 1) * $recordsPerPage) + 1 ?>
                                <?= __('to') ?>
                                <?= min($page * $recordsPerPage, $totalRecords) ?>
                                <?= __('of') ?>
                                <?= $totalRecords ?> <?= __('entries') ?>
                            </p>
                            <ul class="visa-pagination">
                                <li class="<?= $page <= 1 ? 'disabled' : '' ?>">
                                    <?php if ($page <= 1): ?>
                                    <span>&laquo;</span>
                                    <?php else: ?>
                                    <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&laquo;</a>
                                    <?php endif; ?>
                                </li>

                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage   = min($totalPages, $page + 2);
                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                <li class="<?= $i == $page ? 'active' : '' ?>">
                                    <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>

                                <li class="<?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <?php if ($page >= $totalPages): ?>
                                    <span>&raquo;</span>
                                    <?php else: ?>
                                    <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&raquo;</a>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>

                    </div><!-- end page-wrapper -->
                </div><!-- end main-body -->

            </div>
        </div>
    </div>
</div>
<!-- [ Main Content ] end -->
<?php include '../includes/admin_footer.php'; ?>
<?php include '../modals/visa_refund/transaction_modal.php'; ?>
<?php include '../modals/visa_refund/edit_transaction_modal.php'; ?>

<!-- Scripts -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/visa_refund/transaction_manager.js"></script>
<script src="../js/visa_refund/refund_management.js"></script>
<script src="../js/visa_refund/button_protection.js"></script>
<script src="../js/visa_refund/visa_delete.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ── Search on Enter ──
document.getElementById('searchInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        const q = this.value.trim();
        window.location.href = 'visa_refunds.php?search=' + encodeURIComponent(q) + '&page=1';
    }
});
</script>

</body>
</html>