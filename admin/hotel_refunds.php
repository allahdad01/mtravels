<?php
// Include security module
require_once 'security.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in with proper role
require_permission('hotels.refund');
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
// Database connection
require_once('../includes/db.php');

// Include utility functions
require_once('../includes/utils.php');

// Check if user is admin or finance
$canEdit = user_can('hotels.refund') && in_array($_SESSION['role'] ?? '', ['admin', 'finance', 'tenant_super_admin', 'super_admin'], true);
    // Pagination setup
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $recordsPerPage = 10;
    $offset = ($page - 1) * $recordsPerPage;


    // Fetch refunds if table exists with pagination
    // Verify table existence safely
    $tableExists = false;
    try {
        $pdo->query("SELECT 1 FROM hotel_refunds LIMIT 1");
        $tableExists = true;
    } catch (PDOException $e) {
        $tableExists = false;
    }
    $refunds = [];
    $totalRefunds = 0;
    $totalPages = 0;

    if ($tableExists) {
        // First, count total refunds
        $countQuery = "
            SELECT COUNT(*) as total
            FROM hotel_refunds r
            LEFT JOIN hotel_bookings h ON r.booking_id = h.id
            WHERE r.tenant_id = ? AND r.branch_id = ?
        ";
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute([$tenant_id, $branch_id]);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
        $totalRefunds = $countRow ? (int)$countRow['total'] : 0;
        $totalPages = ceil($totalRefunds / $recordsPerPage);

        // Then fetch paginated refunds
         $refundsQuery = "
             SELECT r.*, h.title, h.first_name, h.last_name, h.check_in_date, h.check_out_date,
                    h.accommodation_details, h.currency as booking_currency,
                    u.name as processed_by_name, m.name as account_name,
                    s.name as supplier_name, c.name as client_name, c.client_type
             FROM hotel_refunds r
             LEFT JOIN hotel_bookings h ON r.booking_id = h.id
             LEFT JOIN users u ON r.processed_by = u.id
             LEFT JOIN main_account m ON h.paid_to = m.id
             LEFT JOIN suppliers s ON h.supplier_id = s.id
             LEFT JOIN clients c ON h.sold_to = c.id
             WHERE r.tenant_id = ? AND r.branch_id = ?
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?
         ";

        $stmt = $pdo->prepare($refundsQuery);
        $stmt->bindValue(1, (int)$tenant_id, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$branch_id, PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$recordsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(4, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../assets/plugins/sweetalert2/sweetalert2.min.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════
   Hotel Refunds — Row Card Design (matching hotel.php)
   Prefixed with hb- to avoid conflicts with existing CSS
   ═══════════════════════════════════════════════════════ */

:root {
    --hb-bg:         #f0f2f7;
    --hb-surface:    #ffffff;
    --hb-border:     #e4e8f0;
    --hb-text-1:     #111827;
    --hb-text-2:     #4b5563;
    --hb-text-3:     #9ca3af;
    --hb-accent:     #1a56db;
    --hb-accent-lt:  #eff3ff;
    --hb-green:      #059669;
    --hb-green-lt:   #ecfdf5;
    --hb-amber:      #d97706;
    --hb-amber-lt:   #fffbeb;
    --hb-red:        #dc2626;
    --hb-red-lt:     #fef2f2;
    --hb-radius:     14px;
    --hb-shadow:     0 1px 3px rgba(0,0,0,.07), 0 4px 12px rgba(0,0,0,.06);
    --hb-shadow-hover: 0 4px 8px rgba(0,0,0,.08), 0 14px 28px rgba(0,0,0,.1);
}

/* ── Page Header ─────────────────────────────────────────── */
.hb-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 14px;
}
.hb-page-header-left h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 1.65rem;
    font-weight: 600;
    color: var(--hb-text-1);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}
.hb-page-header-left h1 i {
    color: var(--hb-accent);
    font-size: 1.3rem;
}
.hb-page-header-left p {
    color: var(--hb-text-3);
    font-size: .85rem;
    margin: 3px 0 0;
}
.hb-page-header-right {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.hb-btn-back {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 18px;
    border-radius: 10px;
    border: 1px solid var(--hb-border);
    background: var(--hb-surface);
    color: var(--hb-text-2);
    font-size: .85rem;
    font-weight: 500;
    text-decoration: none;
    transition: all .2s;
}
.hb-btn-back:hover {
    border-color: var(--hb-accent);
    color: var(--hb-accent);
    text-decoration: none;
}

/* ── Column Headers ──────────────────────────────────────── */
.hb-list-header {
    display: grid;
    grid-template-columns: 6px 1fr 190px 150px 155px 140px;
    align-items: center;
    padding: 0 0 8px;
}
.hb-list-header > div {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--hb-text-3);
    padding: 0 12px;
}
.hb-list-header .lh-bar,
.hb-list-header .lh-icon { padding: 0; }
.hb-list-header .lh-guest { padding-left: 16px; }
.hb-list-header .lh-price { text-align: right; }
.hb-list-header .lh-actions { padding-right: 16px; }

/* ── List Wrapper ────────────────────────────────────────── */
.hb-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* ── Booking Row ─────────────────────────────────────────── */
.hb-row {
    display: grid;
    grid-template-columns: 6px 1fr 190px 150px 155px 140px;
    align-items: center;
    background: var(--hb-surface);
    border-radius: var(--hb-radius);
    border: 1px solid var(--hb-border);
    box-shadow: var(--hb-shadow);
    overflow: visible;
    transition: box-shadow .25s, transform .25s;
    animation: hbFadeUp .35s ease both;
    min-height: 88px;
    position: relative;
}
.hb-row:hover {
    box-shadow: var(--hb-shadow-hover);
    transform: translateY(-2px);
}

@keyframes hbFadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Accent Bar ──────────────────────────────────────────── */
.hb-accent-bar {
    height: 100%;
    min-height: 88px;
    border-radius: var(--hb-radius) 0 0 var(--hb-radius);
}

/* ── Guest Cell ──────────────────────────────────────────── */
.hb-guest-cell {
    padding: 14px 12px 14px 16px;
    min-width: 0;
}
.hb-guest-name {
    font-weight: 600;
    font-size: .9375rem;
    color: var(--hb-text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.hb-guest-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 4px;
    flex-wrap: wrap;
}
.hb-guest-meta span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .78rem;
    color: var(--hb-text-3);
    white-space: nowrap;
}
.hb-guest-meta span .feather { font-size: .72rem; }

/* ── Shared Cell Styles ──────────────────────────────────── */
.hb-room-cell,
.hb-dates-cell,
.hb-price-cell,
.hb-status-cell {
    padding: 14px 12px;
    border-left: 1px solid var(--hb-border);
    min-width: 0;
}
.hb-cell-label {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--hb-text-3);
    margin-bottom: 5px;
}

/* ── Room Cell ───────────────────────────────────────────── */
.hb-room-name {
    font-size: .875rem;
    font-weight: 500;
    color: var(--hb-text-1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Dates Cell ──────────────────────────────────────────── */
.hb-dates-track {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: nowrap;
    flex-direction: column;
    align-items: flex-start;
}
.hb-date-block { flex-shrink: 0; }

/* ── Price Cell ──────────────────────────────────────────── */
.hb-price-cell { text-align: right; }
.hb-price-amount {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--hb-text-1);
    letter-spacing: -.2px;
    white-space: nowrap;
}
.hb-currency {
    font-size: .72rem;
    font-weight: 500;
    color: var(--hb-text-3);
    margin-right: 2px;
}

/* ── Status Cell ─────────────────────────────────────────── */
.hb-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 600;
    white-space: nowrap;
}
.hb-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.hb-status-confirmed { background: var(--hb-green-lt); color: var(--hb-green); }
.hb-status-confirmed .hb-dot {
    background: var(--hb-green);
    box-shadow: 0 0 0 2px rgba(5,150,105,.2);
    animation: hbPulse 2s infinite;
}
.hb-status-pending { background: var(--hb-amber-lt); color: var(--hb-amber); }
.hb-status-pending .hb-dot { background: var(--hb-amber); }
.hb-status-cancelled { background: #f3f4f6; color: var(--hb-text-3); }
.hb-status-cancelled .hb-dot { background: var(--hb-text-3); }

@keyframes hbPulse {
    0%, 100% { box-shadow: 0 0 0 2px rgba(5,150,105,.2); }
    50%       { box-shadow: 0 0 0 4px rgba(5,150,105,.08); }
}

.hb-created-by {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: .78rem;
    color: var(--hb-text-3);
    margin-top: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.hb-created-by .feather { font-size: .7rem; flex-shrink: 0; }

/* ── Actions Cell ────────────────────────────────────────── */
.hb-actions-cell {
    padding: 14px 14px 14px 12px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    border-left: 1px solid var(--hb-border);
    flex-wrap: wrap;
    min-width: 0;
}
.hb-btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid var(--hb-border);
    background: var(--hb-surface);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: .8rem;
    cursor: pointer;
    transition: all .18s;
    color: var(--hb-text-2);
    position: relative;
    flex-shrink: 0;
}
.hb-btn-icon:hover { border-color: transparent; }
.hb-btn-icon.hb-view  { color: var(--hb-accent); }
.hb-btn-icon.hb-view:hover  { background: var(--hb-accent-lt); }
.hb-btn-icon.hb-edit  { color: var(--hb-amber); }
.hb-btn-icon.hb-edit:hover  { background: var(--hb-amber-lt); }
.hb-btn-icon.hb-trans  { color: var(--hb-green); }
.hb-btn-icon.hb-trans:hover { background: var(--hb-green-lt); }
.hb-btn-icon.hb-more:hover  { background: #f3f4f6; color: var(--hb-text-1); }

/* Tooltip */
.hb-btn-icon[data-tip]::after {
    content: attr(data-tip);
    position: absolute;
    bottom: calc(100% + 7px);
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: #fff;
    font-size: .68rem;
    padding: 3px 8px;
    border-radius: 5px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity .15s;
    z-index: 200;
}
.hb-btn-icon[data-tip]:hover::after { opacity: 1; }

/* ── Dropdown ────────────────────────────────────────────── */
.hb-dropdown-wrap { position: relative; }
.hb-dropdown-menu {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    background: var(--hb-surface);
    border: 1px solid var(--hb-border);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
    min-width: 168px;
    z-index: 150;
    display: none;
    overflow: hidden;
}
.hb-dropdown-menu.open { display: block; }
.hb-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    font-size: .8rem;
    color: var(--hb-text-2);
    cursor: pointer;
    transition: background .15s;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    font-family: inherit;
}
.hb-dropdown-item:hover { background: #f9fafb; }
.hb-dropdown-item.hb-danger { color: var(--hb-red); }
.hb-dropdown-item.hb-danger:hover { background: var(--hb-red-lt); }
.hb-dropdown-item .feather { width: 14px; font-size: .8rem; flex-shrink: 0; }

/* ── Empty State ─────────────────────────────────────────── */
.hb-empty {
    background: var(--hb-surface);
    border: 1px dashed var(--hb-border);
    border-radius: var(--hb-radius);
    padding: 64px 24px;
    text-align: center;
    color: var(--hb-text-3);
}
.hb-empty .feather { font-size: 2.5rem; display: block; margin: 0 auto 14px; }
.hb-empty h4 { font-size: 1rem; font-weight: 600; color: var(--hb-text-2); margin-bottom: 6px; }
.hb-empty p  { font-size: .875rem; }

/* ── Pagination ──────────────────────────────────────────── */
.hb-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.hb-pagination-info {
    font-size: .8rem;
    color: var(--hb-text-3);
}
.hb-pager {
    display: flex;
    gap: 5px;
    align-items: center;
}
.hb-page-btn {
    min-width: 34px;
    height: 34px;
    padding: 0 8px;
    border-radius: 8px;
    border: 1px solid var(--hb-border);
    background: var(--hb-surface);
    color: var(--hb-text-2);
    font-size: .82rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all .18s;
    cursor: pointer;
}
.hb-page-btn:hover:not(.disabled):not(.hb-page-active) {
    border-color: var(--hb-accent);
    color: var(--hb-accent);
    text-decoration: none;
}
.hb-page-btn.hb-page-active {
    background: var(--hb-accent);
    border-color: var(--hb-accent);
    color: #fff;
    box-shadow: 0 2px 8px rgba(26,86,219,.28);
}
.hb-page-btn.disabled {
    opacity: .4;
    pointer-events: none;
}
.hb-page-ellipsis {
    color: var(--hb-text-3);
    font-size: .8rem;
    padding: 0 4px;
    display: inline-flex;
    align-items: center;
}

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 1200px) {
    .hb-row,
    .hb-list-header {
        grid-template-columns: 6px 1fr 170px 140px auto;
    }
    .hb-status-cell,
    .hb-list-header .lh-status { display: none; }
}

@media (max-width: 960px) {
    .hb-row,
    .hb-list-header {
        grid-template-columns: 6px 1fr 140px auto;
    }
    .hb-price-cell,
    .hb-list-header .lh-price { display: none; }
}

@media (max-width: 700px) {
    .hb-row,
    .hb-list-header {
        grid-template-columns: 6px 1fr auto;
    }
    .hb-dates-cell,
    .hb-list-header .lh-dates { display: none; }
    .hb-list-header { display: none; }
    .hb-actions-cell { border-left: none; }
}

@media (max-width: 480px) {
    .hb-page-header { flex-direction: column; align-items: flex-start; }
    .hb-page-header-right { width: 100%; }
    .hb-btn-back { width: 100%; justify-content: center; }
}

/* ── Toast (keep existing) ───────────────────────────────── */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 350px;
}
</style>
<!-- Main Content -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">

                        <!-- Page Header -->
                        <div class="hb-page-header">
                            <div class="hb-page-header-left">
                                <h1><i class="fa-solid fa-credit-card"></i><?= __('hotel_refunds') ?></h1>
                                <p><?= __('manage_hotel_refunds_efficiently') ?></p>
                            </div>
                            <div class="hb-page-header-right">
                                <a href="hotel.php" class="hb-btn-back">
                                    <i class="feather icon-arrow-left"></i><?= __('back_to_bookings') ?>
                                </a>
                            </div>
                        </div>

                        <!-- Toast Container -->
                        <div class="toast-container"></div>

                        <!-- Column Headers -->
                        <div class="hb-list-header">
                            <div class="lh-bar"></div>
                            <div class="lh-guest">Guest</div>
                            <div class="lh-dates">Refund Info</div>
                            <div class="lh-price">Amount</div>
                            <div class="lh-status">Date</div>
                            <div class="lh-actions"></div>
                        </div>

                        <!-- Refunds List -->
                        <div class="hb-list" id="refundsContainer">
                            <?php if (!$tableExists || empty($refunds)): ?>
                                <div class="hb-empty">
                                    <i class="feather icon-inbox"></i>
                                    <h4><?= __('no_hotel_refunds_have_been_processed_yet') ?></h4>
                                    <p><?= __('when_refunds_are_processed_they_will_appear_here') ?></p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($refunds as $i => $refund): ?>
                                    <?php
                                    // Status styling
                                    $refundType = $refund['refund_type'] ?? 'full';
                                    $statusClass = ($refundType === 'full') ? 'hb-status-cancelled' : 'hb-status-pending';
                                    $statusLabel = ($refundType === 'full') ? 'Full Refund' : 'Partial Refund';
                                    $statusBar = ($refundType === 'full') ? '#d1d5db' : 'linear-gradient(180deg,#d97706 0%,#f59e0b 100%)';
                                    ?>
                                    <div class="hb-row" 
                                         data-refund-id="<?= $refund['id'] ?>"
                                         data-refund-type="<?= htmlspecialchars($refundType) ?>"
                                         style="animation-delay: <?= $i * 0.04 ?>s">

                                        <!-- Accent Bar -->
                                        <div class="hb-accent-bar" style="background: <?= $statusBar ?>;"></div>

                                        <!-- Guest -->
                                        <div class="hb-guest-cell">
                                            <div class="hb-guest-name"><?= htmlspecialchars(getValue($refund, 'title') . ' ' . getValue($refund, 'first_name') . ' ' . getValue($refund, 'last_name')) ?></div>
                                        </div>

                                        <!-- Refund Info -->
                                        <div class="hb-dates-cell">
                                            <div class="hb-cell-label">Refund</div>
                                            <div class="hb-dates-track">
                                                <span class="hb-status-pill <?= $statusClass ?>">
                                                    <span class="hb-dot"></span><?= $statusLabel ?>
                                                </span>
                                                <small style="display: block; margin-top: 4px; color: #9ca3af; font-size: .75rem;">
                                                    <?= htmlspecialchars($refund['reason'] ?? 'No reason provided') ?>
                                                </small>
                                            </div>
                                        </div>

                                        <!-- Amount -->
                                        <div class="hb-price-cell">
                                            <div class="hb-price-amount">
                                                <span class="hb-currency"><?= htmlspecialchars(getValue($refund, 'currency')) ?></span>
                                                <?= number_format(getValue($refund, 'refund_amount', 0), 2) ?>
                                            </div>
                                        </div>

                                        <!-- Date -->
                                        <div class="hb-status-cell">
                                            <div style="font-weight: 600; color: #111827;">
                                                <i class="feather icon-calendar"></i> <?= date('M d, Y', strtotime($refund['created_at'])) ?>
                                            </div>
                                            <?php if ($refund['processed_by_name']): ?>
                                            <div class="hb-created-by">
                                                <i class="feather icon-user-check"></i>
                                                <?= __('by') ?> <?= htmlspecialchars($refund['processed_by_name']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Actions -->
                                        <div class="hb-actions-cell">
                                            <?php if ($canEdit && strtolower($refund['client_type'] ?? 'regular') !== 'regular'): ?>
                                            <button class="hb-btn-icon hb-edit"
                                                    data-tip="<?= __('manage_transactions') ?>"
                                                    onclick="processRefundTransaction(<?= $refund['id'] ?>)">
                                                <i class="feather icon-credit-card"></i>
                                            </button>
                                            <?php endif; ?>

                                            <button class="hb-btn-icon hb-view"
                                                    data-tip="<?= __('print_agreement') ?>"
                                                    onclick="printRefundAgreement(<?= $refund['id'] ?>)">
                                                <i class="feather icon-printer"></i>
                                            </button>

                                            <?php if ($canEdit): ?>
                                            <div class="hb-dropdown-wrap">
                                                <button class="hb-btn-icon hb-more"
                                                        data-tip="More"
                                                        onclick="toggleHbDropdown(this)">
                                                    <i class="feather icon-more-vertical"></i>
                                                </button>
                                                <div class="hb-dropdown-menu">
                                                    <button class="hb-dropdown-item"
                                                            onclick="editHotelRefund(<?= $refund['id'] ?>)">
                                                        <i class="feather icon-edit"></i><?= __('edit') ?>
                                                    </button>
                                                    <button class="hb-dropdown-item hb-danger"
                                                            onclick="deleteRefund(<?= $refund['id'] ?>)">
                                                        <i class="feather icon-trash-2"></i><?= __('delete_refund') ?>
                                                    </button>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                    </div><!-- /.hb-row -->
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if (!empty($refunds) && isset($totalPages) && $totalPages > 1): ?>
                        <div class="hb-pagination">
                            <span class="hb-pagination-info">
                                <?php
                                if (isset($page, $recordsPerPage, $totalRefunds)) {
                                    $startRecord = (($page - 1) * $recordsPerPage) + 1;
                                    $endRecord   = min($page * $recordsPerPage, $totalRefunds);
                                    echo sprintf('Showing %d–%d of %d entries', $startRecord, $endRecord, $totalRefunds);
                                }
                                ?>
                            </span>
                            <nav class="hb-pager">
                                <?php
                                $prevDisabled = ($page <= 1) ? 'disabled' : '';
                                echo '<a class="hb-page-btn ' . $prevDisabled . '" href="' . ($prevDisabled ? '#' : '?page=' . ($page - 1)) . '"><i class="feather icon-chevron-left"></i></a>';

                                $maxPages  = 5;
                                $startPage = max(1, min($page - floor($maxPages / 2), $totalPages - $maxPages + 1));
                                $endPage   = min($startPage + $maxPages - 1, $totalPages);

                                if ($startPage > 1) {
                                    echo '<a class="hb-page-btn" href="?page=1">1</a>';
                                    if ($startPage > 2) echo '<span class="hb-page-ellipsis">…</span>';
                                }

                                for ($p = $startPage; $p <= $endPage; $p++) {
                                    $activeClass = ($p == $page) ? 'hb-page-active' : '';
                                    echo '<a class="hb-page-btn ' . $activeClass . '" href="?page=' . $p . '">' . $p . '</a>';
                                }

                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) echo '<span class="hb-page-ellipsis">…</span>';
                                    echo '<a class="hb-page-btn" href="?page=' . $totalPages . '">' . $totalPages . '</a>';
                                }

                                $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
                                echo '<a class="hb-page-btn ' . $nextDisabled . '" href="' . ($nextDisabled ? '#' : '?page=' . ($page + 1)) . '"><i class="feather icon-chevron-right"></i></a>';
                                ?>
                            </nav>
                        </div>
                        <?php endif; ?>

                    </div><!-- /.page-wrapper -->
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../modals/hotel_refund/transaction_modal.php'; ?>
<?php include '../modals/hotel_refund/edit_transaction_modal.php'; ?>



<script>
function toggleHbDropdown(btn) {
    const menu = btn.nextElementSibling;
    document.querySelectorAll('.hb-dropdown-menu').forEach(m => {
        if (m !== menu) m.classList.remove('open');
    });
    menu.classList.toggle('open');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.hb-dropdown-wrap')) {
        document.querySelectorAll('.hb-dropdown-menu').forEach(m => m.classList.remove('open'));
    }
});
</script>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../assets/plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="../js/hotel_refund/transaction_manager.js<?= '?v=' . time() ?>"></script>
<script src="../js/hotel_refund/hotel_management.js<?= '?v=' . time() ?>"></script>

<?php include '../includes/admin_footer.php'; ?>

</body>
</html>
