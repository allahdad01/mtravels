<?php
/**
 * Branch Addon Payments Management
 * Super Admin interface for recording and viewing branch addon payments
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout (30 minutes)
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Database connection
require_once '../config.php';
require_once '../includes/db.php';

// Include security module
require_once 'security.php';

// Check if user is a super admin (system administrator, not tenant-based)
enforce_super_admin();

require_once '../includes/BranchAddonManager.php';

$addon_manager = new BranchAddonManager($pdo);

// Helper function to get currency symbol
function getAddonCurrencySymbol($currencyCode) {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'AFN' => '؋',
        'AED' => 'د.إ',
        'INR' => '₹',
        'PKR' => '₨',
    ];
    return $symbols[$currencyCode] ?? $currencyCode;
}

// Handle form submission for recording payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        header('Location: branch_addon_payments.php?error=csrf');
        exit();
    }
    
    $addon_id = intval($_POST['addon_id']);
    $amount = floatval($_POST['amount']);
    $currency = $_POST['currency'];
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'] ?? '';
    $transaction_id = $_POST['transaction_id'] ?? '';
    $receipt_number = $_POST['receipt_number'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $processed_by = $_SESSION['user_id'];
    
    try {
        $result = $addon_manager->recordAddonPayment($addon_id, 0, $amount, $currency, $payment_method, $transaction_id);
        
        if ($result['success']) {
            // Update addon next renewal date
            $stmt = $pdo->prepare("UPDATE branch_addons SET next_renewal_date = DATE_ADD(?, INTERVAL 1 MONTH) WHERE id = ?");
            $stmt->execute([$payment_date, $addon_id]);
            
            header('Location: branch_addon_payments.php?success=1');
            exit();
        } else {
            $error_message = $result['message'];
        }
    } catch (Exception $e) {
        // Never expose error details to client
        $error_message = "An error occurred while recording the payment. Please try again.";
    }
}

// Get all active branch addons
$active_addons = $addon_manager->getAllBranchAddons('active');

// Get payment history for each addon
$addon_payments = [];
foreach ($active_addons as $addon) {
    $stmt = $pdo->prepare("
        SELECT bap.*, t.name as tenant_name, ba.additional_branches
        FROM branch_addon_payments bap
        JOIN branch_addons ba ON bap.addon_id = ba.id
        JOIN tenants t ON ba.tenant_id = t.id
        WHERE bap.addon_id = ?
        ORDER BY bap.payment_date DESC
    ");
    $stmt->execute([$addon['id']]);
    $addon_payments[$addon['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once '../includes/header_super_admin.php';
?>

<style>
/* ─── ROOT VARIABLES ──────────────────────────────────────────── */
:root {
    --muted: #999;
    --red: #ef4444;
    --amber: #f59e0b;
    --blue: #4099ff;
    --grad-start: #4099ff;
    --grad-end: #2ed8b6;
    --grad: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    --radius: 10px;
}

/* ─── PAGE HEADER ─────────────────────────────────────────── */
.page-header.card {
    background: var(--grad) !important;
    color: #fff; border: none !important; margin-bottom: 24px;
    padding: 22px 28px !important; box-shadow: 0 4px 20px rgba(64,153,255,0.3);
    border-radius: 12px; position: relative; overflow: hidden;
}
.page-header.card::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}
.page-header.card h5 { color: #fff !important; margin: 0; font-weight: 700; font-size: 1.15rem; position: relative; z-index: 1; }
.page-header.card .row { display: flex; align-items: center; justify-content: space-between; width: 100%; position: relative; z-index: 2; }
.page-header.card .col-md-6:last-child { text-align: right; margin-left: auto; }
.page-desc { color: rgba(255,255,255,0.8); margin: 4px 0 0; font-size: 14px; }

/* ─── ALERTS ──────────────────────────────────────────────── */
.sa-alert {
    display: flex; align-items: flex-start; gap: 12px; padding: 12px 16px;
    border-radius: var(--radius); border: 1px solid #e0e0e0;
    margin-bottom: 16px;
}
.sa-alert-success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
.sa-alert-danger { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
.sa-alert-icon { flex-shrink: 0; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; }
.sa-alert-icon svg { width: 20px; height: 20px; }
.sa-alert-content { flex: 1; align-self: center; }
.sa-alert-close { flex-shrink: 0; background: none; border: none; cursor: pointer; color: inherit; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
.sa-alert-close:hover { opacity: 0.7; }

/* ─── BUTTONS ─────────────────────────────────────────────── */
.sa-btn {
    padding: 0.75rem 1.5rem; border-radius: 8px; border: none;
    font-weight: 500; cursor: pointer; transition: all 0.3s ease;
    text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 0.9rem;
}
.sa-btn-primary { background: var(--grad); color: white; }
.sa-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(64,153,255,0.3); }
.sa-btn-ghost { background: #f0f0f0; color: #333; border: 1px solid #e0e0e0; }
.sa-btn-ghost:hover { background: #e8e8e8; border-color: #d0d0d0; }

/* ─── SECTION HEADER ──────────────────────────────────────── */
.sa-section-header {
    display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;
}
.sa-section-header h2 { font-size: 1.35rem; font-weight: 700; margin: 0; color: #333; }
.sa-section-header p { margin: 4px 0 0 0; font-size: 0.8rem; color: var(--muted); }

/* ─── DATA TABLE ──────────────────────────────────────────── */
.sa-table-wrap {
    background: white; border: 1px solid #e0e0e0; border-radius: 10px; overflow-x: auto;
}
.sa-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.sa-table thead th {
    text-align: left; padding: 14px 16px; font-size: 0.65rem; font-weight: 700;
    color: #999; text-transform: uppercase; letter-spacing: 0.06em;
    background: #fafafa; border-bottom: 1px solid #e0e0e0; white-space: nowrap;
}
.sa-table tbody tr { transition: background 0.15s; }
.sa-table tbody tr:hover { background: #f8faff; }
.sa-table tbody td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
.sa-table tbody tr:last-child td { border-bottom: none; }
.sa-td-tenant { display: flex; align-items: center; gap: 12px; }
.sa-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; }
.sa-tenant-meta { display: flex; flex-direction: column; }
.sa-tenant-name { font-weight: 600; color: #333; }
.sa-tenant-id { font-size: 0.75rem; color: #999; margin-top: 2px; }
.sa-td-date { white-space: nowrap; color: #999; font-size: 0.8rem; }

/* ─── EMPTY STATE ─────────────────────────────────────────── */
.sa-empty { text-align: center; padding: 48px 20px; background: white; border: 1px solid #e0e0e0; border-radius: 10px; color: #ccc; }
.sa-empty-title { font-weight: 600; color: #999; margin-top: 12px; font-size: 1rem; }
.sa-empty-desc { font-size: 0.85rem; color: #bbb; margin-top: 4px; }

/* ─── PILLS ───────────────────────────────────────────────── */
.pill { font-size: 0.62rem; font-weight: 700; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; display: inline-block; }
.pill-green { background: rgba(16,185,129,0.12); color: #10b981; }
.pill-amber { background: rgba(245,158,11,0.12); color: #f59e0b; }
.pill-gray { background: #f5f5f5; color: #999; }

/* ─── MODALS ──────────────────────────────────────────────── */
.sa-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
.sa-modal-wrap { background: white; border-radius: 14px; width: 480px; max-width: 94vw; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.sa-modal-header { display: flex; align-items: center; gap: 12px; padding: 20px 24px 0; }
.sa-modal-header h3 { flex: 1; font-size: 1.1rem; font-weight: 700; margin: 0; color: #333; }
.sa-modal-close { width: 32px; height: 32px; border: none; background: #f5f5f5; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #999; transition: all 0.2s; }
.sa-modal-close:hover { background: #e0e0e0; color: #333; }
.sa-modal-body { padding: 16px 24px 0; }
.sa-modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 16px 24px 20px; }

/* ─── FORM ELEMENTS ───────────────────────────────────────── */
.sa-field { margin-bottom: 16px; }
.sa-field-label { display: block; font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 6px; }
.sa-form-input {
    width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #ced4da; border-radius: 8px;
    font-size: 0.85rem; font-family: inherit; transition: border-color 0.15s; box-sizing: border-box;
}
.sa-form-input:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 0.2rem rgba(64,153,255,0.25); }
.sa-form-select {
    width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #ced4da; border-radius: 8px;
    font-size: 0.85rem; font-family: inherit; background: white; cursor: pointer; box-sizing: border-box;
}
.sa-form-select:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 0.2rem rgba(64,153,255,0.25); }
.sa-textarea { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #ced4da; border-radius: 8px; font-size: 0.85rem; font-family: inherit; resize: vertical; transition: border-color 0.15s; box-sizing: border-box; }
.sa-textarea:focus { outline: none; border-color: #4099ff; box-shadow: 0 0 0 0.2rem rgba(64,153,255,0.25); }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <!-- [ breadcrumb ] start -->
                <div class="page-header card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>Branch Addon Payments
                            </h5>
                            <p class="page-desc">Manage and track branch addon payments for all active subscriptions</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <a href="manage_branch_addons.php" class="sa-btn" style="background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.25);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><polyline points="15 18 9 12 15 6"/></svg>Back to Add-ons
                            </a>
                        </div>
                    </div>
                </div>
                <!-- [ breadcrumb ] end -->

                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        
                        <!-- Success/Error Alerts -->
                        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                        <div class="sa-alert sa-alert-success">
                            <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                            <div class="sa-alert-content">Payment recorded successfully!</div>
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($error_message)): ?>
                        <div class="sa-alert sa-alert-danger">
                            <div class="sa-alert-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>
                            <div class="sa-alert-content"><?= htmlspecialchars($error_message) ?></div>
                            <button type="button" class="sa-alert-close" onclick="this.parentElement.style.display='none';"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                        </div>
                        <?php endif; ?>

                        <!-- Section Header -->
                        <div class="sa-section-header">
                            <div>
                                <h2>Payment Records</h2>
                                <p>Manage and track all branch addon payments</p>
                            </div>
                            <button type="button" class="sa-btn sa-btn-primary" onclick="showModal('recordPaymentModal')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Record Payment
                            </button>
                        </div>

                        <!-- Data Table -->
                        <?php if (empty($active_addons)): ?>
                        <div class="sa-empty">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            <div class="sa-empty-title">No Active Branch Addons</div>
                            <div class="sa-empty-desc">Active add-ons with payments will appear here.</div>
                        </div>
                        <?php else: ?>
                        <div class="sa-table-wrap">
                            <table class="sa-table">
                                <thead>
                                    <tr>
                                        <th>Tenant</th>
                                        <th>Branches</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Transaction ID</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_addons as $addon):
                                        $symbol = getAddonCurrencySymbol($addon['currency'] ?? 'USD');
                                        $payments = $addon_payments[$addon['id']] ?? [];
                                        $initial = strtoupper(substr($addon['tenant_name'], 0, 1));
                                        if (empty($payments)): ?>
                                    <tr>
                                        <td class="sa-td-tenant">
                                            <div class="sa-avatar" style="background:#6b7280"><?= $initial ?></div>
                                            <div class="sa-tenant-meta">
                                                <div class="sa-tenant-name"><?= htmlspecialchars($addon['tenant_name'] ?? '') ?></div>
                                            </div>
                                        </td>
                                        <td style="font-weight:600;">+<?= intval($addon['additional_branches']) ?></td>
                                        <td class="sa-td-date" colspan="4" style="color:#999;font-style:italic;">No payments recorded yet</td>
                                        <td><span class="pill pill-gray">—</span></td>
                                    </tr>
                                    <?php else:
                                        foreach ($payments as $payment): ?>
                                    <tr>
                                        <td class="sa-td-tenant">
                                            <div class="sa-avatar" style="background:#10b981"><?= $initial ?></div>
                                            <div class="sa-tenant-meta">
                                                <div class="sa-tenant-name"><?= htmlspecialchars($addon['tenant_name'] ?? '') ?></div>
                                                <div class="sa-tenant-id"><?= $symbol . number_format($addon['total_addon_cost'] ?? 0, 2) ?>/mo</div>
                                            </div>
                                        </td>
                                        <td style="font-weight:600;">+<?= intval($addon['additional_branches']) ?></td>
                                        <td class="sa-td-date"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                        <td style="font-weight:600;"><?= $symbol . number_format($payment['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($payment['payment_method'] ?: '-') ?></td>
                                        <td style="font-size:0.8rem;color:#666;"><?= htmlspecialchars($payment['transaction_id'] ?: '-') ?></td>
                                        <td><span class="pill <?= $payment['status'] === 'completed' ? 'pill-green' : 'pill-amber' ?>"><?= ucfirst($payment['status']) ?></span></td>
                                    </tr>
                                    <?php endforeach;
                                        endif;
                                    endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="recordPaymentModal" class="sa-modal-overlay" style="display:none;">
    <div class="sa-modal-wrap" style="width:520px;">
        <div class="sa-modal-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4099ff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            <h3>Record Branch Addon Payment</h3>
            <button type="button" class="sa-modal-close" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form method="POST">
            <div class="sa-modal-body">
                <input type="hidden" name="action" value="record_payment">

                <div class="sa-field">
                    <label class="sa-field-label">Branch Addon <span style="color:#ef4444;">*</span></label>
                    <select class="sa-form-select" id="addon_id" name="addon_id" required>
                        <option value="">Select Branch Addon</option>
                        <?php foreach ($active_addons as $addon): ?>
                        <?php $symbol = getAddonCurrencySymbol($addon['currency'] ?? 'USD'); ?>
                        <option value="<?= $addon['id'] ?>" data-currency="<?= htmlspecialchars($addon['currency'] ?? 'USD') ?>" data-amount="<?= $addon['total_addon_cost'] ?>">
                            <?= htmlspecialchars($addon['tenant_name']) ?> - +<?= intval($addon['additional_branches']) ?> branches (<?= $symbol . number_format($addon['total_addon_cost'], 2) ?>/month)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sa-field">
                    <label class="sa-field-label">Amount <span style="color:#ef4444;">*</span></label>
                    <div style="display:flex;gap:8px;">
                        <span id="amountSymbol" style="padding:0.6rem 0.75rem;background:#f5f5f5;border:1px solid #ced4da;border-radius:8px;font-weight:700;color:#333;min-width:40px;text-align:center;">$</span>
                        <input type="number" class="sa-form-input" id="amount" name="amount" step="0.01" min="0" required style="flex:1;">
                    </div>
                </div>

                <div class="sa-field">
                    <label class="sa-field-label">Currency <span style="color:#ef4444;">*</span></label>
                    <select class="sa-form-select" id="currency" name="currency" required>
                        <option value="USD">USD ($)</option>
                        <option value="AFN">AFN (؋)</option>
                        <option value="EUR">EUR (€)</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="AED">AED (د.إ)</option>
                        <option value="INR">INR (₹)</option>
                        <option value="PKR">PKR (₨)</option>
                    </select>
                </div>

                <div class="sa-field">
                    <label class="sa-field-label">Payment Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" class="sa-form-input" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="sa-field">
                    <label class="sa-field-label">Payment Method</label>
                    <select class="sa-form-select" id="payment_method" name="payment_method">
                        <option value="">Select Method</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="PayPal">PayPal</option>
                        <option value="Cash">Cash</option>
                        <option value="Check">Check</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div style="display:flex;gap:12px;">
                    <div class="sa-field" style="flex:1;">
                        <label class="sa-field-label">Transaction ID</label>
                        <input type="text" class="sa-form-input" id="transaction_id" name="transaction_id" placeholder="Enter transaction ID">
                    </div>
                    <div class="sa-field" style="flex:1;">
                        <label class="sa-field-label">Receipt Number</label>
                        <input type="text" class="sa-form-input" id="receipt_number" name="receipt_number" placeholder="Enter receipt number">
                    </div>
                </div>

                <div class="sa-field">
                    <label class="sa-field-label">Notes</label>
                    <textarea class="sa-textarea" id="notes" name="notes" rows="2" placeholder="Additional notes"></textarea>
                </div>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-btn sa-btn-ghost" onclick="this.closest('.sa-modal-overlay').style.display='none'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Cancel
                </button>
                <button type="submit" class="sa-btn sa-btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Record Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script>
function showModal(id) { document.getElementById(id).style.display = 'flex'; }

const currencySymbols = {
    'USD': '$', 'EUR': '€', 'GBP': '£', 'JPY': '¥',
    'AFN': '؋', 'AED': 'د.إ', 'INR': '₹', 'PKR': '₨',
};

document.getElementById('addon_id').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const currency = option.getAttribute('data-currency') || 'USD';
    const amount = option.getAttribute('data-amount') || '';
    const symbol = currencySymbols[currency] || currency;
    document.getElementById('currency').value = currency;
    document.getElementById('amount').value = amount;
    document.getElementById('amountSymbol').textContent = symbol;
});

document.getElementById('currency').addEventListener('change', function() {
    const currency = this.value;
    const symbol = currencySymbols[currency] || currency;
    document.getElementById('amountSymbol').textContent = symbol;
});
</script>

<?php include '../includes/admin_footer.php'; ?>
</body>
</html>
