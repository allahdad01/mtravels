<?php
require_once '../includes/db.php';
require_once 'security.php';
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../login.php');
    exit();
}

// Initialize messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : null;
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : null;

// Clear session messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Fetch customers with their total balances
$stmt = $pdo->prepare("
    SELECT
        c.*,
        COALESCE(SUM(w.balance), 0) as current_balance,
        w.currency
    FROM customers c
    LEFT JOIN customer_wallets w ON c.id = w.customer_id AND w.tenant_id = c.tenant_id AND w.branch_id = c.branch_id
    WHERE c.status = 'active' AND c.tenant_id = ? AND c.branch_id = ?
    GROUP BY c.id, w.currency
    ORDER BY c.created_at DESC
");
$stmt->bindParam(1, $tenant_id, PDO::PARAM_INT);
$stmt->bindParam(2, $branch_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();
$customers = [];

// Organize customer data
foreach ($result as $row) {
    $customerId = $row['id'];
    if (!isset($customers[$customerId])) {
        $customers[$customerId] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'created_at' => $row['created_at'],
            'balances' => []
        ];
    }
    if ($row['currency']) {
        $customers[$customerId]['balances'][$row['currency']] = $row['current_balance'];
    }
}

// Calculate summary stats
$total_customers = count($customers);
$customers_with_balance = 0;
$customers_with_debt = 0;
foreach ($customers as $c) {
    $has_positive = false;
    $has_negative = false;
    foreach ($c['balances'] as $bal) {
        if (floatval($bal) > 0) $has_positive = true;
        if (floatval($bal) < 0) $has_negative = true;
    }
    if ($has_positive) $customers_with_balance++;
    if ($has_negative) $customers_with_debt++;
}

$isRTL = in_array($_SESSION['lang'] ?? 'en', ['fa', 'ps']);

// Currency colors
$currencyColors = [
    'USD' => ['bg' => '#e8f8f0', 'text' => '#1e8449', 'border' => '#27ae60'],
    'EUR' => ['bg' => '#ebf5fb', 'text' => '#2471a3', 'border' => '#2980b9'],
    'AFS' => ['bg' => '#f4ecf7', 'text' => '#7d3c98', 'border' => '#8e44ad'],
    'DARHAM' => ['bg' => '#fef9e7', 'text' => '#b7950b', 'border' => '#d4ac17'],
    'PKR' => ['bg' => '#fdf2e9', 'text' => '#ca6f1e', 'border' => '#d35400'],
    'INR' => ['bg' => '#fdedec', 'text' => '#a93226', 'border' => '#c0392b'],
];
?>

<?php include '../includes/header.php'; ?>

<link rel="stylesheet" href="../css/general/modal-styles.css">

<style>
/* ============================================
   CUSTOMERS LIST PAGE - REDESIGNED
   ============================================ */

:root {
    --primary: #4680ff;
    --primary-light: #ebf0ff;
    --success: #2ecc71;
    --success-light: #e8f8f0;
    --warning: #e67e22;
    --warning-light: #fef5e7;
    --danger: #e74c3c;
    --danger-light: #fde8e8;
    --info: #3498db;
    --info-light: #ebf5fb;
    --surface: #f4f6f9;
    --white: #ffffff;
    --border: #e9ecef;
    --text-primary: #2d3436;
    --text-secondary: #636e72;
    --text-muted: #adb5bd;
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
    --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.08);
}

/* Page Container */
.customers-page {
    padding: 0;
}

/* Page Header */
.page-header-section {
    margin-bottom: 24px;
}

.page-header-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.page-header-left h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header-left h2 i {
    color: var(--primary);
    font-size: 1.3rem;
}

.page-header-subtitle {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0;
}

.btn-add-customer {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, var(--primary), #6c5ce7);
    color: white;
    border: none;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(70, 128, 255, 0.25);
}

.btn-add-customer:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(70, 128, 255, 0.35);
    color: white;
    text-decoration: none;
}

.btn-add-customer i {
    font-size: 0.9rem;
}

/* Summary Stats */
.summary-stats {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.summary-card {
    flex: 1;
    min-width: 180px;
    background: var(--white);
    border-radius: var(--radius-md);
    padding: 18px 20px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 14px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.summary-card-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.summary-card-icon.total {
    background: var(--primary-light);
    color: var(--primary);
}

.summary-card-icon.positive {
    background: var(--success-light);
    color: var(--success);
}

.summary-card-icon.negative {
    background: var(--danger-light);
    color: var(--danger);
}

.summary-card-info {
    display: flex;
    flex-direction: column;
}

.summary-card-value {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.summary-card-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
    font-weight: 500;
}

/* Flash Messages */
.flash-message {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 16px;
    animation: flashSlideDown 0.3s ease;
}

@keyframes flashSlideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.flash-message.flash-success {
    background: var(--success-light);
    color: #1e8449;
    border: 1px solid rgba(46, 204, 113, 0.2);
}

.flash-message.flash-error {
    background: var(--danger-light);
    color: #c0392b;
    border: 1px solid rgba(231, 76, 60, 0.2);
}

.flash-message .flash-close {
    margin-left: auto;
    background: none;
    border: none;
    color: inherit;
    opacity: 0.6;
    cursor: pointer;
    font-size: 1.1rem;
    padding: 0 4px;
    transition: opacity 0.2s;
}

.flash-message .flash-close:hover {
    opacity: 1;
}

/* Search & Filter Bar */
.filter-bar {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 14px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-sm);
}

.search-input-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 8px 14px;
    flex: 1;
    min-width: 240px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.search-input-wrap:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(70, 128, 255, 0.1);
}

.search-input-wrap i {
    color: var(--text-muted);
    font-size: 0.9rem;
    flex-shrink: 0;
}

.search-input-wrap input {
    background: none;
    border: none;
    outline: none;
    color: var(--text-primary);
    font-size: 0.85rem;
    width: 100%;
}

.search-input-wrap input::placeholder {
    color: var(--text-muted);
}

.filter-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border);
    background: var(--white);
    color: var(--text-secondary);
    transition: all 0.2s ease;
}

.filter-badge:hover,
.filter-badge.active {
    background: var(--primary-light);
    color: var(--primary);
    border-color: var(--primary);
}

.filter-badge .filter-count {
    background: var(--surface);
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 700;
}

.filter-badge.active .filter-count {
    background: rgba(70, 128, 255, 0.15);
}

/* Customers Table Card */
.table-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}

.table-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 12px;
}

.table-card-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-card-title h5 {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.table-card-title i {
    color: var(--primary);
}

.record-count-badge {
    background: var(--surface);
    border: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 0.7rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    letter-spacing: 0.3px;
}

/* Table Styles */
.table-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.customers-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.customers-table thead th {
    padding: 12px 16px;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--text-muted);
    background: #fafbfc;
    border-bottom: 2px solid var(--border);
    font-weight: 600;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 1;
}

.customers-table thead th:first-child {
    padding-left: 24px;
}

.customers-table thead th:last-child {
    padding-right: 24px;
    text-align: center;
}

.customers-table tbody tr {
    transition: background-color 0.15s ease;
}

.customers-table tbody tr:hover {
    background-color: #f8f9fe;
}

.customers-table tbody td {
    padding: 14px 16px;
    vertical-align: middle;
    font-size: 0.85rem;
    border-bottom: 1px solid #f5f5f5;
    color: var(--text-primary);
}

.customers-table tbody tr:last-child td {
    border-bottom: none;
}

.customers-table tbody td:first-child {
    padding-left: 24px;
}

.customers-table tbody td:last-child {
    padding-right: 24px;
}

/* Customer Cell */
.customer-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.customer-avatar-sm {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), #6c5ce7);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    flex-shrink: 0;
    letter-spacing: 0.5px;
}

.customer-cell-info {
    display: flex;
    flex-direction: column;
}

.customer-cell-name {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.875rem;
    line-height: 1.2;
}

.customer-cell-name a {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s;
}

.customer-cell-name a:hover {
    color: var(--primary);
}

.customer-cell-id {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Contact Cell */
.contact-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.contact-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.contact-item i {
    font-size: 0.75rem;
    color: var(--text-muted);
    width: 14px;
    text-align: center;
}

.contact-item.email-item {
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Balance Cell */
.balance-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.balance-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.balance-tag.positive {
    background: var(--success-light);
    color: #1e8449;
}

.balance-tag.negative {
    background: var(--danger-light);
    color: #c0392b;
}

.balance-tag.zero {
    background: var(--surface);
    color: var(--text-muted);
}

.no-balance-text {
    font-size: 0.8rem;
    color: var(--text-muted);
    font-style: italic;
}

/* Date Cell */
.date-cell {
    white-space: nowrap;
}

.date-main {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.8rem;
}

.date-relative {
    font-size: 0.7rem;
    color: var(--text-muted);
    display: block;
    margin-top: 2px;
}

/* Action Buttons */
.action-cell {
    display: flex;
    gap: 6px;
    justify-content: center;
    flex-wrap: nowrap;
}

.action-btn-icon {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    background: var(--white);
    color: var(--text-muted);
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    font-size: 0.85rem;
}

.action-btn-icon:hover {
    transform: scale(1.08);
    text-decoration: none;
}

.action-btn-icon.action-view:hover {
    color: var(--info);
    border-color: var(--info);
    background: var(--info-light);
}

.action-btn-icon.action-edit:hover {
    color: var(--primary);
    border-color: var(--primary);
    background: var(--primary-light);
}

.action-btn-icon.action-print:hover {
    color: var(--success);
    border-color: var(--success);
    background: var(--success-light);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 24px;
}

.empty-state-icon {
    font-size: 3rem;
    color: var(--text-muted);
    opacity: 0.4;
    margin-bottom: 16px;
}

.empty-state-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 6px;
}

.empty-state-text {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 20px;
}

.empty-state-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.empty-state-btn:hover {
    background: #3a6fd8;
    transform: translateY(-1px);
}

/* Modal Overrides */
.modal .modal-content {
    border: none;
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.modal .modal-header {
    background: linear-gradient(135deg, var(--primary), #6c5ce7);
    color: white;
    border: none;
    padding: 18px 24px;
}

.modal .modal-header .modal-title {
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    color: white;
}

.modal .modal-header .close {
    color: white;
    opacity: 0.8;
    text-shadow: none;
}

.modal .modal-header .close:hover {
    opacity: 1;
}

.modal .modal-body {
    padding: 24px;
}

.modal .modal-body .form-group {
    margin-bottom: 16px;
}

.modal .modal-body .form-group label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    margin-bottom: 6px;
    display: block;
}

.modal .modal-body .form-control,
.modal .modal-body select,
.modal .modal-body textarea {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text-primary);
    font-size: 0.85rem;
    padding: 10px 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
    width: 100%;
}

.modal .modal-body .form-control:focus,
.modal .modal-body select:focus,
.modal .modal-body textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(70, 128, 255, 0.1);
    outline: none;
}

.modal .modal-footer {
    border-top: 1px solid var(--border);
    padding: 14px 24px;
    background: #fafbfc;
}

.btn-modal-save {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    background: linear-gradient(135deg, var(--primary), #6c5ce7);
    color: white;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-modal-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(70, 128, 255, 0.3);
}

.btn-modal-cancel {
    padding: 8px 18px;
    background: var(--surface);
    color: var(--text-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-modal-cancel:hover {
    background: var(--border);
    color: var(--text-primary);
}

/* Toast Notifications */
.toast-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    max-width: 420px;
    padding: 14px 18px;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.85rem;
    font-weight: 500;
    transform: translateX(120%);
    transition: transform 0.3s ease;
}

.toast-notification.show {
    transform: translateX(0);
}

.toast-notification.toast-success {
    background: var(--success);
    color: white;
}

.toast-notification.toast-error {
    background: var(--danger);
    color: white;
}

/* Search Highlight */
.search-highlight {
    background: #fff3cd;
    padding: 1px 3px;
    border-radius: 3px;
    font-weight: 600;
}

/* RTL Support */
<?php if ($isRTL): ?>
.page-header-top {
    flex-direction: row-reverse;
}

.customer-cell {
    flex-direction: row-reverse;
}

.contact-item {
    flex-direction: row-reverse;
}

.action-cell {
    flex-direction: row-reverse;
}

.search-input-wrap {
    flex-direction: row-reverse;
}

.flash-message .flash-close {
    margin-left: 0;
    margin-right: auto;
}

.customers-table thead th:first-child {
    padding-left: 16px;
    padding-right: 24px;
}

.customers-table thead th:last-child {
    padding-right: 16px;
    padding-left: 24px;
}

.customers-table tbody td:first-child {
    padding-left: 16px;
    padding-right: 24px;
}

.customers-table tbody td:last-child {
    padding-right: 16px;
    padding-left: 24px;
}
<?php endif; ?>

/* Responsive */
@media (max-width: 991px) {
    .summary-stats {
        gap: 10px;
    }
    
    .summary-card {
        min-width: calc(50% - 8px);
    }
}

@media (max-width: 767px) {
    .page-header-top {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn-add-customer {
        justify-content: center;
    }
    
    .summary-card {
        min-width: 100%;
    }
    
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-input-wrap {
        min-width: 100%;
    }
    
    .filter-badges-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .customers-table thead th,
    .customers-table tbody td {
        padding: 10px 12px;
    }
}

@media (max-width: 576px) {
    .summary-stats {
        gap: 8px;
    }
    
    .action-cell {
        gap: 4px;
    }
    
    .action-btn-icon {
        width: 30px;
        height: 30px;
        font-size: 0.75rem;
    }
}

/* Print */
@media print {
    .page-header-section,
    .filter-bar,
    .summary-stats,
    .action-cell,
    .flash-message,
    .btn-add-customer {
        display: none !important;
    }
    
    .table-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <div class="customers-page">

                            <!-- Page Header -->
                            <div class="page-header-section">
                                <div class="page-header-top">
                                    <div class="page-header-left">
                                        <h2>
                                            <i class="feather icon-users"></i>
                                            <?= __('customer_management') ?>
                                        </h2>
                                        <p class="page-header-subtitle">
                                            <?= __('manage_customer_wallets') ?? 'Manage your customers and their wallets' ?>
                                        </p>
                                    </div>
                                    <button class="btn-add-customer" data-toggle="modal" data-target="#customerModal">
                                        <i class="feather icon-user-plus"></i>
                                        <?= __('new_customer') ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Flash Messages -->
                            <?php if (isset($success_message)): ?>
                            <div class="flash-message flash-success" id="flashSuccess">
                                <i class="feather icon-check-circle"></i>
                                <span><?= htmlspecialchars($success_message) ?></span>
                                <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($error_message)): ?>
                            <div class="flash-message flash-error" id="flashError">
                                <i class="feather icon-alert-circle"></i>
                                <span><?= htmlspecialchars($error_message) ?></span>
                                <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                            </div>
                            <?php endif; ?>

                            <!-- Summary Stats -->
                            <div class="summary-stats">
                                <div class="summary-card">
                                    <div class="summary-card-icon total">
                                        <i class="feather icon-users"></i>
                                    </div>
                                    <div class="summary-card-info">
                                        <span class="summary-card-value"><?= $total_customers ?></span>
                                        <span class="summary-card-label"><?= __('total_customers') ?? 'Total Customers' ?></span>
                                    </div>
                                </div>
                                <div class="summary-card">
                                    <div class="summary-card-icon positive">
                                        <i class="feather icon-trending-up"></i>
                                    </div>
                                    <div class="summary-card-info">
                                        <span class="summary-card-value"><?= $customers_with_balance ?></span>
                                        <span class="summary-card-label"><?= __('with_balance') ?? 'With Balance' ?></span>
                                    </div>
                                </div>
                                <div class="summary-card">
                                    <div class="summary-card-icon negative">
                                        <i class="feather icon-trending-down"></i>
                                    </div>
                                    <div class="summary-card-info">
                                        <span class="summary-card-value"><?= $customers_with_debt ?></span>
                                        <span class="summary-card-label"><?= __('with_debt') ?? 'With Debt' ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Search & Filter Bar -->
                            <div class="filter-bar">
                                <div class="search-input-wrap">
                                    <i class="feather icon-search"></i>
                                    <input type="text" id="customerSearch" placeholder="<?= __('search_customers') ?? 'Search by name, phone, or email...' ?>">
                                </div>
                                <div class="filter-badges-row">
                                    <span class="filter-badge active" data-filter="all">
                                        <?= __('all') ?? 'All' ?>
                                        <span class="filter-count"><?= $total_customers ?></span>
                                    </span>
                                    <span class="filter-badge" data-filter="has-balance">
                                        <?= __('has_balance') ?? 'Has Balance' ?>
                                        <span class="filter-count"><?= $customers_with_balance ?></span>
                                    </span>
                                    <span class="filter-badge" data-filter="has-debt">
                                        <?= __('has_debt') ?? 'Has Debt' ?>
                                        <span class="filter-count"><?= $customers_with_debt ?></span>
                                    </span>
                                </div>
                            </div>

                            <!-- Customers Table -->
                            <div class="table-card">
                                <div class="table-card-header">
                                    <div class="table-card-title">
                                        <i class="feather icon-list"></i>
                                        <h5><?= __('customers') ?></h5>
                                    </div>
                                    <span class="record-count-badge" id="visibleCount">
                                        <?= $total_customers ?> <?= __('records') ?? 'records' ?>
                                    </span>
                                </div>

                                <?php if (!empty($customers)): ?>
                                <div class="table-scroll">
                                    <table class="customers-table" id="customersTable">
                                        <thead>
                                            <tr>
                                                <th><?= __('customer_name') ?></th>
                                                <th><?= __('customer_contact') ?></th>
                                                <th><?= __('customer_current_balance') ?></th>
                                                <th><?= __('customer_created') ?></th>
                                                <th><?= __('customer_actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customers as $customer): 
                                                // Determine balance status for filtering
                                                $has_positive = false;
                                                $has_negative = false;
                                                foreach ($customer['balances'] as $bal) {
                                                    if (floatval($bal) > 0) $has_positive = true;
                                                    if (floatval($bal) < 0) $has_negative = true;
                                                }
                                            ?>
                                            <tr data-has-balance="<?= $has_positive ? '1' : '0' ?>" 
                                                data-has-debt="<?= $has_negative ? '1' : '0' ?>">
                                                <!-- Customer Name + Avatar -->
                                                <td>
                                                    <div class="customer-cell">
                                                        <div class="customer-avatar-sm">
                                                            <?= strtoupper(mb_substr($customer['name'], 0, 2)) ?>
                                                        </div>
                                                        <div class="customer-cell-info">
                                                            <div class="customer-cell-name">
                                                                <a href="customer_detail.php?id=<?= $customer['id'] ?>">
                                                                    <?= htmlspecialchars($customer['name']) ?>
                                                                </a>
                                                            </div>
                                                            <div class="customer-cell-id">#<?= $customer['id'] ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <!-- Contact -->
                                                <td>
                                                    <div class="contact-cell">
                                                        <div class="contact-item">
                                                            <i class="feather icon-phone"></i>
                                                            <span><?= htmlspecialchars($customer['phone']) ?></span>
                                                        </div>
                                                        <?php if ($customer['email']): ?>
                                                        <div class="contact-item email-item">
                                                            <i class="feather icon-mail"></i>
                                                            <span><?= htmlspecialchars($customer['email']) ?></span>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <!-- Balance -->
                                                <td>
                                                    <div class="balance-cell">
                                                        <?php if (!empty($customer['balances'])): ?>
                                                            <?php foreach ($customer['balances'] as $currency => $balance): 
                                                                $bal_float = floatval($balance);
                                                                if ($bal_float == 0) continue;
                                                                $bal_class = $bal_float > 0 ? 'positive' : 'negative';
                                                                $colors = $currencyColors[$currency] ?? ['bg' => '#f0f0f0', 'text' => '#666', 'border' => '#ccc'];
                                                            ?>
                                                            <span class="balance-tag <?= $bal_class ?>" 
                                                                  style="background: <?= $colors['bg'] ?>; color: <?= $colors['text'] ?>;">
                                                                <?= number_format($balance, 2) ?> <?= htmlspecialchars($currency) ?>
                                                            </span>
                                                            <?php endforeach; ?>
                                                            <?php 
                                                            // Check if all balances are zero
                                                            $all_zero = true;
                                                            foreach ($customer['balances'] as $b) {
                                                                if (floatval($b) != 0) { $all_zero = false; break; }
                                                            }
                                                            if ($all_zero): ?>
                                                                <span class="no-balance-text"><?= __('no_balance') ?? 'No balance' ?></span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="no-balance-text"><?= __('no_balance') ?? 'No balance' ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                
                                                <!-- Created Date -->
                                                <td>
                                                    <div class="date-cell">
                                                        <span class="date-main">
                                                            <?= date('M d, Y', strtotime($customer['created_at'])) ?>
                                                        </span>
                                                        <span class="date-relative">
                                                            <?php
                                                            $created = new DateTime($customer['created_at']);
                                                            $now = new DateTime();
                                                            $diff = $now->diff($created);
                                                            if ($diff->days == 0) {
                                                                echo __('today') ?? 'Today';
                                                            } elseif ($diff->days == 1) {
                                                                echo __('yesterday') ?? 'Yesterday';
                                                            } elseif ($diff->days < 30) {
                                                                echo $diff->days . ' ' . (__('days_ago') ?? 'days ago');
                                                            } elseif ($diff->m < 12) {
                                                                echo $diff->m . ' ' . (__('months_ago') ?? 'months ago');
                                                            } else {
                                                                echo $diff->y . ' ' . (__('years_ago') ?? 'years ago');
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                
                                                <!-- Actions -->
                                                <td>
                                                    <div class="action-cell">
                                                        <a href="customer_detail.php?id=<?= $customer['id'] ?>" 
                                                           class="action-btn-icon action-view" 
                                                           data-toggle="tooltip" title="<?= __('view_customer') ?>">
                                                            <i class="feather icon-eye"></i>
                                                        </a>
                                                        <button class="action-btn-icon action-edit" 
                                                                onclick="editCustomer(<?= $customer['id'] ?>)"
                                                                data-toggle="tooltip" title="<?= __('edit_customer') ?>">
                                                            <i class="feather icon-edit-2"></i>
                                                        </button>
                                                        <a href="print_statement.php?id=<?= $customer['id'] ?>" 
                                                           target="_blank" 
                                                           class="action-btn-icon action-print"
                                                           data-toggle="tooltip" title="<?= __('print_statement') ?>">
                                                            <i class="feather icon-printer"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <!-- Empty State -->
                                <div class="empty-state">
                                    <i class="feather icon-users empty-state-icon"></i>
                                    <div class="empty-state-title"><?= __('no_customers_yet') ?? 'No customers yet' ?></div>
                                    <div class="empty-state-text"><?= __('add_first_customer') ?? 'Add your first customer to get started' ?></div>
                                    <button class="empty-state-btn" data-toggle="modal" data-target="#customerModal">
                                        <i class="feather icon-user-plus"></i>
                                        <?= __('new_customer') ?>
                                    </button>
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

<!-- Include Customer Modal -->
<?php include 'includes/sarafi_modals.php'; ?>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editCustomerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="feather icon-edit-2"></i>
                    <?= __('edit_customer') ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?= __('close') ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editCustomerForm" method="POST" action="handlers/edit_customer.php">
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="edit_customer_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_name"><?= __('customer_name') ?></label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_phone"><?= __('customer_phone') ?></label>
                                <input type="text" class="form-control" id="edit_phone" name="phone" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="edit_email"><?= __('customer_email') ?></label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="edit_address"><?= __('customer_address') ?></label>
                        <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" data-dismiss="modal">
                        <?= __('close') ?>
                    </button>
                    <button type="submit" class="btn-modal-save">
                        <i class="feather icon-save"></i>
                        <?= __('update_customer') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>
<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Auto-dismiss flash messages
    setTimeout(function() {
        document.querySelectorAll('.flash-message').forEach(function(msg) {
            msg.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-10px)';
            setTimeout(() => msg.remove(), 500);
        });
    }, 5000);

    // ── Search Functionality ──
    const searchInput = document.getElementById('customerSearch');
    const tableBody = document.querySelector('#customersTable tbody');
    const visibleCountEl = document.getElementById('visibleCount');
    
    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            filterAndSearch();
        });
    }

    // ── Filter Badges ──
    const filterBadges = document.querySelectorAll('.filter-badge');
    let activeFilter = 'all';
    
    filterBadges.forEach(badge => {
        badge.addEventListener('click', function() {
            filterBadges.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeFilter = this.dataset.filter;
            filterAndSearch();
        });
    });

    function filterAndSearch() {
        if (!tableBody) return;
        
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const rows = tableBody.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            // Search match
            const name = row.querySelector('.customer-cell-name') ? 
                         row.querySelector('.customer-cell-name').textContent.toLowerCase() : '';
            const contact = row.querySelector('.contact-cell') ? 
                           row.querySelector('.contact-cell').textContent.toLowerCase() : '';
            const searchMatch = !query || name.includes(query) || contact.includes(query);

            // Filter match
            let filterMatch = true;
            if (activeFilter === 'has-balance') {
                filterMatch = row.dataset.hasBalance === '1';
            } else if (activeFilter === 'has-debt') {
                filterMatch = row.dataset.hasDebt === '1';
            }

            if (searchMatch && filterMatch) {
                row.style.display = '';
                visibleCount++;
                
                // Handle search highlighting
                if (query) {
                    highlightText(row, query);
                } else {
                    removeHighlight(row);
                }
            } else {
                row.style.display = 'none';
                removeHighlight(row);
            }
        });

        // Update visible count
        if (visibleCountEl) {
            visibleCountEl.textContent = visibleCount + ' <?= __("records") ?? "records" ?>';
        }
    }

    function highlightText(row, searchText) {
        const targets = row.querySelectorAll('.customer-cell-name a, .contact-item span');
        targets.forEach(el => {
            const original = el.textContent;
            const regex = new RegExp(`(${escapeRegex(searchText)})`, 'gi');
            const highlighted = original.replace(regex, '<span class="search-highlight">$1</span>');
            if (original !== highlighted) {
                el.innerHTML = highlighted;
            }
        });
    }

    function removeHighlight(row) {
        const highlights = row.querySelectorAll('.search-highlight');
        highlights.forEach(hl => {
            hl.replaceWith(hl.textContent);
        });
    }

    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
});

// ── Edit Customer ──
function editCustomer(customerId) {
    // Show loading in modal
    $('#editCustomerModal').modal('show');
    const form = document.getElementById('editCustomerForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Reset form
    form.reset();
    
    fetch(`handlers/get_customer.php?id=${customerId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_customer_id').value = data.customer.id;
                document.getElementById('edit_name').value = data.customer.name;
                document.getElementById('edit_phone').value = data.customer.phone;
                document.getElementById('edit_email').value = data.customer.email || '';
                document.getElementById('edit_address').value = data.customer.address || '';
            } else {
                showToast('error', '<?= __("error_fetching_customer") ?>');
                $('#editCustomerModal').modal('hide');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', '<?= __("error_fetching_customer") ?>');
            $('#editCustomerModal').modal('hide');
        });
}

// ── Form Submission ──
document.getElementById('editCustomerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalHtml = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<i class="feather icon-loader" style="animation: spin 1s linear infinite;"></i> <?= __("saving") ?? "Saving..." ?>';
    submitBtn.disabled = true;
    
    fetch('handlers/edit_customer.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        showToast(data.success ? 'success' : 'error', data.message);
        if (data.success) {
            setTimeout(() => {
                $('#editCustomerModal').modal('hide');
                location.reload();
            }, 1000);
        } else {
            submitBtn.innerHTML = originalHtml;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', '<?= __("error_updating_customer") ?>');
        submitBtn.innerHTML = originalHtml;
        submitBtn.disabled = false;
    });
});

// ── Toast Notification ──
function showToast(type, message) {
    document.querySelectorAll('.toast-notification').forEach(t => t.remove());
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <i class="feather icon-${type === 'success' ? 'check-circle' : 'alert-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    
    toast.offsetHeight; // Force reflow
    
    setTimeout(() => {
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }, 50);
}

// Spin animation for loading
const spinStyle = document.createElement('style');
spinStyle.textContent = `@keyframes spin { to { transform: rotate(360deg); } }`;
document.head.appendChild(spinStyle);
</script>

</body>
</html>