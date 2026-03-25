<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include security module
require_once 'security.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include database connection
include '../includes/db.php';
include '../includes/conn.php';

// Get tenant_id and branch_id from session
$tenant_id = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : 0;
$branch_id = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : 0;

// Include language helper
require_once '../includes/language_helpers.php';

// Initialize variables
$hotelId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$hotelData = null;
$clientTransactions = [];
$supplierTransactions = [];
$mainAccountTransactions = [];
$error = null;

// Check if ID is provided
if (!$hotelId) {
    $error = "No hotel booking ID provided";
} else {
    // Get hotel booking details with related info - filtered by tenant_id and branch_id
    $hotelQuery = "SELECT 
            hb.*,
            c.name AS client_name,
            c.email AS client_email,
            c.phone AS client_phone,
            s.name AS supplier_name,
            s.email AS supplier_email,
            s.phone AS supplier_phone
        FROM hotel_bookings hb
        LEFT JOIN clients c ON hb.sold_to = c.id
        LEFT JOIN suppliers s ON hb.supplier_id = s.id
        WHERE hb.id = ? AND hb.tenant_id = ? AND hb.branch_id = ?";
        
    $stmt = $pdo->prepare($hotelQuery);
    $stmt->execute([$hotelId, $tenant_id, $branch_id]);
    $hotelData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$hotelData) {
        $error = "Hotel booking not found";
    } else {
        // Get main account transactions related to this booking
        $mainAccountTransQuery = "SELECT 
                'Main Account' AS transaction_type,
                mat.id,
                mat.type,
                mat.amount,
                mat.currency,
                mat.description,
                mat.transaction_of,
                mat.created_at AS transaction_date
            FROM main_account_transactions mat
            WHERE mat.reference_id = ? AND mat.transaction_of = 'hotel' AND mat.tenant_id = ? AND mat.branch_id = ?
            ORDER BY mat.created_at DESC";
            
        $stmt = $pdo->prepare($mainAccountTransQuery);
        $stmt->execute([$hotelId, $tenant_id, $branch_id]);
        $mainAccountTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get client transactions related to this booking
        $clientTransQuery = "SELECT 
                'Client' AS transaction_type,
                ct.id,
                ct.type,
                ct.amount,
                ct.currency,
                ct.description,
                ct.transaction_of,
                ct.created_at AS transaction_date
            FROM client_transactions ct
            WHERE ct.reference_id = ? AND ct.transaction_of = 'hotel' AND ct.tenant_id = ? AND ct.branch_id = ?
            ORDER BY ct.created_at DESC";
            
        $stmt = $pdo->prepare($clientTransQuery);
        $stmt->execute([$hotelId, $tenant_id, $branch_id]);
        $clientTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get supplier transactions related to this booking
        $supplierTransQuery = "SELECT 
                'Supplier' AS transaction_type,
                st.id,
                st.transaction_type as type,
                st.amount,
                null as currency,
                st.remarks as description,
                st.transaction_of,
                st.transaction_date AS transaction_date
            FROM supplier_transactions st
            WHERE st.reference_id = ? AND st.transaction_of = 'hotel' AND st.tenant_id = ? AND st.branch_id = ?
            ORDER BY st.transaction_date DESC";
            
        $stmt = $pdo->prepare($supplierTransQuery);
        $stmt->execute([$hotelId, $tenant_id, $branch_id]);
        $supplierTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Include the header
include '../includes/header_client.php';
?>

<link rel="stylesheet" href="../css/general/modal-styles.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">

<!-- Main Content -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">

                        <?php if ($error): ?>
                            <!-- Page Header with Error -->
                            <div class="hb-page-header">
                                <div class="hb-page-header-left">
                                    <h1><i class="fa-solid fa-hotel"></i><?= __('hotel_booking_details') ?></h1>
                                </div>
                                <div class="hb-page-header-right">
                                    <a href="hotel.php" class="hb-btn-back">
                                        <i class="feather icon-arrow-left"></i><?= __('back_to_bookings') ?>
                                    </a>
                                </div>
                            </div>

                            <div class="hb-empty">
                                <i class="feather icon-alert-circle"></i>
                                <h4><?= htmlspecialchars($error) ?></h4>
                                <p><?= __('please_try_again') ?></p>
                            </div>
                        <?php else: ?>
                            <!-- Page Header -->
                            <div class="hb-page-header">
                                <div class="hb-page-header-left">
                                    <h1><i class="fa-solid fa-hotel"></i><?= __('hotel_booking_details') ?></h1>
                                    <p><?= htmlspecialchars($hotelData['order_id'] ?? '—') ?></p>
                                </div>
                                <div class="hb-page-header-right">
                                    <a href="hotel.php" class="hb-btn-back">
                                        <i class="feather icon-arrow-left"></i><?= __('back_to_bookings') ?>
                                    </a>
                                </div>
                            </div>

                            <!-- Main Details Card -->
                            <div class="hb-detail-card">
                                <div class="hb-detail-header">
                                    <div class="hb-detail-title-block">
                                        <h2><?= htmlspecialchars($hotelData['first_name'] . ' ' . $hotelData['last_name']) ?></h2>
                                        <span class="hb-detail-order-id"><?= htmlspecialchars($hotelData['order_id']) ?></span>
                                    </div>
                                    <div class="hb-detail-status">
                                        <span class="hb-status-pill <?= $hotelData['status'] === 'refunded' ? 'hb-status-cancelled' : 'hb-status-confirmed' ?>">
                                            <span class="hb-dot"></span><?= ucfirst($hotelData['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="hb-detail-grid">
                                    <!-- Guest Information -->
                                    <div class="hb-detail-section">
                                        <h3><?= __('guest_information') ?></h3>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('title') ?></span>
                                            <span class="hb-value"><?= htmlspecialchars($hotelData['title'] ?? '—') ?></span>
                                        </div>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('first_name') ?></span>
                                            <span class="hb-value"><?= htmlspecialchars($hotelData['first_name'] ?? '—') ?></span>
                                        </div>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('last_name') ?></span>
                                            <span class="hb-value"><?= htmlspecialchars($hotelData['last_name'] ?? '—') ?></span>
                                        </div>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('gender') ?></span>
                                            <span class="hb-value"><?= htmlspecialchars($hotelData['gender'] ?? '—') ?></span>
                                        </div>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('contact_no') ?></span>
                                            <span class="hb-value"><?= htmlspecialchars($hotelData['contact_no'] ?? '—') ?></span>
                                        </div>
                                    </div>

                                    <!-- Booking Information -->
                                    <div class="hb-detail-section">
                                        <h3><?= __('booking_information') ?></h3>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('check_in_date') ?></span>
                                            <span class="hb-value"><?= !empty($hotelData['check_in_date']) ? date('Y-m-d', strtotime($hotelData['check_in_date'])) : '—' ?></span>
                                        </div>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('check_out_date') ?></span>
                                            <span class="hb-value"><?= !empty($hotelData['check_out_date']) ? date('Y-m-d', strtotime($hotelData['check_out_date'])) : '—' ?></span>
                                        </div>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('accommodation_details') ?></span>
                                            <span class="hb-value"><?= htmlspecialchars($hotelData['accommodation_details'] ?? '—') ?></span>
                                        </div>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('issue_date') ?></span>
                                            <span class="hb-value"><?= !empty($hotelData['issue_date']) ? date('Y-m-d', strtotime($hotelData['issue_date'])) : '—' ?></span>
                                        </div>
                                    </div>

                                    <!-- Financial Information -->
                                    <div class="hb-detail-section">
                                        <h3><?= __('financial_information') ?></h3>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('sold_amount') ?></span>
                                            <span class="hb-value hb-highlight"><?= htmlspecialchars($hotelData['currency'] ?? '—') ?> <?= htmlspecialchars($hotelData['sold_amount'] ?? '—') ?></span>
                                        </div>

                                    </div>

                                    <!-- Client & Supplier Information -->
                                    <div class="hb-detail-section">
                                        <h3><?= __('parties') ?></h3>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('client') ?></span>
                                            <span class="hb-value"><?= htmlspecialchars($hotelData['client_name'] ?? '—') ?></span>
                                        </div>
                                        <div class="hb-detail-row">
                                            <span class="hb-label"><?= __('client_email') ?></span>
                                            <span class="hb-value"><?= htmlspecialchars($hotelData['client_email'] ?? '—') ?></span>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <!-- Transactions Section -->
                            <div class="hb-transactions-container">
                                <div class="hb-transactions-header">
                                    <h2><?= __('transactions') ?></h2>
                                </div>

                                <!-- Client Transactions -->
                                <?php if (!empty($clientTransactions)): ?>
                                <div class="hb-trans-card">
                                    <div class="hb-trans-title">
                                        <i class="feather icon-briefcase"></i><?= __('client_transactions') ?>
                                    </div>
                                    <div class="hb-trans-table">
                                        <div class="hb-trans-row hb-trans-header">
                                            <div><?= __('date') ?></div>
                                            <div><?= __('type') ?></div>
                                            <div><?= __('amount') ?></div>
                                            <div><?= __('description') ?></div>
                                        </div>
                                        <?php foreach ($clientTransactions as $transaction): ?>
                                        <div class="hb-trans-row">
                                            <div><?= date('Y-m-d', strtotime($transaction['transaction_date'])) ?></div>
                                            <div><span class="hb-trans-badge"><?= ucfirst(strtolower($transaction['type'])) ?></span></div>
                                            <div class="<?= (strtolower($transaction['type']) == 'debit') ? 'hb-text-danger' : 'hb-text-success' ?>">
                                                <?= htmlspecialchars($transaction['currency'] ?? '—') ?> <?= htmlspecialchars($transaction['amount'] ?? '—') ?>
                                            </div>
                                            <div><?= htmlspecialchars($transaction['description'] ?? '—') ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>


                                <?php if (empty($clientTransactions)): ?>
                                <div class="hb-empty">
                                    <i class="feather icon-inbox"></i>
                                    <h4><?= __('no_transactions_found') ?></h4>
                                    <p><?= __('no_transactions_for_this_booking') ?></p>
                                </div>
                                <?php endif; ?>
                            </div>

                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --hb-accent:     #1a56db;
    --hb-accent-lt:  #eff3ff;
    --hb-surface:    #ffffff;
    --hb-border:     #e5e7eb;
    --hb-text-1:     #1f2937;
    --hb-text-2:     #6b7280;
    --hb-text-3:     #9ca3af;
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

.hb-page-header-left {
    display: flex;
    flex-direction: column;
    gap: 6px;
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

.hb-btn-new {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    background: var(--hb-accent);
    color: #fff;
    font-size: .875rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s, transform .15s, box-shadow .2s;
    box-shadow: 0 2px 8px rgba(26,86,219,.28);
    font-family: inherit;
}

.hb-btn-new:hover {
    background: #1648c2;
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(26,86,219,.35);
    color: #fff;
    text-decoration: none;
}

/* Detail Card Styles */
.hb-detail-card {
    background: var(--hb-surface);
    border: 1px solid var(--hb-border);
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
}

.hb-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    border-bottom: 1px solid var(--hb-border);
}

.hb-detail-title-block h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--hb-text-1);
}

.hb-detail-order-id {
    display: block;
    font-size: 0.875rem;
    color: var(--hb-text-3);
    margin-top: 4px;
}

.hb-detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    padding: 24px;
}

.hb-detail-section h3 {
    font-size: 0.975rem;
    font-weight: 600;
    color: var(--hb-text-1);
    margin: 0 0 16px 0;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--hb-accent-lt);
}

.hb-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--hb-border);
}

.hb-detail-row:last-child {
    border-bottom: none;
}

.hb-label {
    font-size: 0.85rem;
    color: var(--hb-text-3);
    font-weight: 500;
}

.hb-value {
    font-size: 0.95rem;
    color: var(--hb-text-1);
    font-weight: 500;
    text-align: right;
}

.hb-highlight {
    color: var(--hb-accent);
    font-weight: 600;
}

.hb-text-success {
    color: var(--hb-green);
}

.hb-text-danger {
    color: var(--hb-red);
}

/* Transactions Styles */
.hb-transactions-container {
    margin-top: 24px;
}

.hb-transactions-header {
    margin-bottom: 16px;
}

.hb-transactions-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--hb-text-1);
    margin: 0;
}

.hb-trans-card {
    background: var(--hb-surface);
    border: 1px solid var(--hb-border);
    border-radius: 12px;
    margin-bottom: 16px;
    overflow: hidden;
}

.hb-trans-title {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 20px;
    background: var(--hb-accent-lt);
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--hb-accent);
    border-bottom: 1px solid var(--hb-border);
}

.hb-trans-table {
    padding: 12px 0;
}

.hb-trans-row {
    display: grid;
    grid-template-columns: 110px 120px 120px 1fr;
    gap: 16px;
    padding: 12px 20px;
    align-items: center;
    border-bottom: 1px solid var(--hb-border);
    font-size: 0.875rem;
}

.hb-trans-row:last-child {
    border-bottom: none;
}

.hb-trans-header {
    background: #f9fafb;
    font-weight: 600;
    color: var(--hb-text-2);
}

.hb-trans-badge {
    display: inline-block;
    padding: 4px 10px;
    background: var(--hb-accent-lt);
    color: var(--hb-accent);
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.8rem;
}

/* Responsive */
@media (max-width: 768px) {
    .hb-detail-grid {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .hb-trans-row {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .hb-detail-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
}

/* Empty State */
.hb-empty {
    background: var(--hb-surface);
    border: 1px dashed var(--hb-border);
    border-radius: 12px;
    padding: 64px 24px;
    text-align: center;
    color: var(--hb-text-3);
}

.hb-empty .feather {
    font-size: 2.5rem;
    display: block;
    margin: 0 auto 14px;
}

.hb-empty h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--hb-text-2);
    margin-bottom: 6px;
}

.hb-empty p {
    font-size: 0.875rem;
}

/* Status Pills */
.hb-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.hb-status-confirmed {
    background: #dbeafe;
    color: var(--hb-accent);
}

.hb-status-cancelled {
    background: #fecaca;
    color: var(--hb-red);
}

.hb-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}
</style>
<!-- Vendor scripts (keep order matching original) -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<?php
// Include the footer
include '../includes/admin_footer.php';
?>
