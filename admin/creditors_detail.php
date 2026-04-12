<?php
// Include database security module for input validation
require_once 'includes/db_security.php';

// Include security module
require_once 'security.php';

// Include secure headers helper
require_once 'includes/set_secure_headers.php';

// Include language helper
require_once '../includes/language_helpers.php';

// Enforce authentication
enforce_auth();

// Check if user is logged in with proper role
$allowed_roles = ['admin', 'finance'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $allowed_roles)) {
    // Log unauthorized access attempt
    header('Location: ../login.php');
    exit();
}

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/db.php';

// Initialize variables
$creditorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$creditorData = null;
$transactions = [];
$error = null;

// Check if ID is provided
if (!$creditorId) {
    $error = "No creditor ID provided";
} else {
    // Get creditor details
    $creditorQuery = "SELECT * FROM creditors WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    $stmt = $pdo->prepare($creditorQuery);
    $stmt->execute([$creditorId, $tenant_id, $branch_id]);
    $creditorData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$creditorData) {
        $error = "Creditor not found";
    } else {
        // Get transactions related to this creditor
        $transactionsQuery = "SELECT
                'Creditor Payment' AS transaction_type,
                ct.id,
                ct.creditor_id,
                ct.amount,
                ct.currency,
                ct.transaction_type,
                ct.description,
                ct.payment_date,
                ct.reference_number,
                ct.created_at
            FROM creditor_transactions ct
            WHERE ct.creditor_id = ?
            AND ct.tenant_id = ? AND ct.branch_id = ?
            ORDER BY ct.payment_date DESC";

        $stmt = $pdo->prepare($transactionsQuery);
        $stmt->execute([$creditorId, $tenant_id, $branch_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

include '../includes/header.php';
?>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/creditors/styles.css">

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

<style>
:root {
  --primary:#4099ff;--primary-dark:#2563eb;--primary-light:#60a5fa;
  --accent:#2ed8b6;--accent-dark:#14b8a6;--accent-light:#5eead4;
  --violet:#7c3aed;--violet-light:#a78bfa;--indigo:#4f46e5;
  --sky:#0ea5e9;--emerald:#10b981;--amber:#f59e0b;
  --rose:#f43f5e;--orange:#f97316;--pink:#ec4899;--teal:#14b8a6;
  --bg:#f8fafc;--surface:#ffffff;--surface2:#f1f5f9;--surface3:#e2e8f0;
  --border:rgba(0,0,0,0.08);
  --text:#1e293b;--text-muted:#64748b;
  --grad-start:#4099ff;--grad-end:#2ed8b6;--grad:linear-gradient(135deg,var(--grad-start) 0%,var(--grad-end) 100%);
}
.pcoded-main-container{background:var(--bg)!important;display:flex;flex-direction:column;min-height:100vh;}
.pcoded-wrapper{display:flex;flex-direction:column;flex:1;}
.pcoded-content,.pcoded-inner-content{background:transparent!important;flex:1;display:flex;flex-direction:column;}
.main-body{flex:1;display:flex;flex-direction:column;}
.page-wrapper{flex:1;display:flex;flex-direction:column;}
.container{padding-left:20px;padding-right:20px;}
.dash-wrap{font-family:'Plus Jakarta Sans',sans-serif;color:var(--text);padding:28px 20px;position:relative;}

@media (max-width: 768px) {
  .container {
    padding-left: 16px;
    padding-right: 16px;
  }
  .dash-wrap {
    padding: 20px 16px;
  }
}

@media (max-width: 480px) {
  .container {
    padding-left: 12px;
    padding-right: 12px;
  }
  .dash-wrap {
    padding: 16px 12px;
  }
}
.dash-wrap::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background:radial-gradient(ellipse 80% 60% at 10% 0%,rgba(124,58,237,.15) 0%,transparent 60%),radial-gradient(ellipse 60% 50% at 90% 10%,rgba(14,165,233,.12) 0%,transparent 55%),radial-gradient(ellipse 50% 40% at 50% 100%,rgba(16,185,129,.08) 0%,transparent 50%);}
.dash-inner{position:relative;z-index:1;}
.sec-label{font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-muted);margin-bottom:14px;display:flex;align-items:center;gap:8px;}
.sec-label::after{content:'';flex:1;height:1px;background:var(--border);}
.d-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:24px;margin-bottom:22px;}
.d-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;}
.d-card-title{font-size:15px;font-weight:800;display:flex;align-items:center;gap:10px;color:var(--text);}
.ci{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:13px;}
.ci-violet{background:rgba(124,58,237,.2);color:var(--violet-light);}
.ci-sky{background:rgba(14,165,233,.2);color:var(--sky);}
.ci-emerald{background:rgba(16,185,129,.2);color:var(--emerald);}
.ci-amber{background:rgba(245,158,11,.2);color:var(--amber);}
.ci-rose{background:rgba(244,63,94,.2);color:var(--rose);}
.dbtn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:10px;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;border:none;transition:all .2s;text-decoration:none;}
.dbtn-ghost{background:var(--surface2);color:var(--text);border:1px solid var(--border);}
.dbtn-ghost:hover{background:var(--surface3);transform:translateY(-1px);color:var(--text);}
.dbtn-primary{background:var(--grad);color:#fff;box-shadow:0 4px 20px rgba(64,153,255,.35);}
.dbtn-primary:hover{transform:translateY(-2px);color:#fff;}
.d-alert{border-radius:14px;padding:16px 20px;margin-bottom:12px;display:flex;align-items:flex-start;gap:14px;border:1px solid;animation:slideIn .4s ease;}
@keyframes slideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.d-alert-warning{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.3);}
.d-alert-danger{background:rgba(244,63,94,.1);border-color:rgba(244,63,94,.3);}

/* Creditor cards grid */
.creditor-grid{display:flex;flex-direction:column;gap:12px;margin-bottom:22px;}
.creditor-card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:16px;position:relative;overflow:hidden;transition:transform .2s,border-color .2s,box-shadow .2s;cursor:pointer;display:grid;grid-template-columns:auto 1fr auto auto auto 1fr auto;align-items:center;gap:16px;}
.creditor-card:hover{transform:translateY(-2px);border-color:rgba(64,153,255,.4);box-shadow:0 8px 30px rgba(64,153,255,.12);}
.creditor-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad);}

/* Mobile Responsive */
@media (max-width: 768px) {
  .creditor-card{
    grid-template-columns: auto 1fr;
    gap: 12px;
    padding: 14px;
  }
  .creditor-card > div:nth-child(2),
  .creditor-card > div:nth-child(3),
  .creditor-card > div:nth-child(4),
  .creditor-card > div:nth-child(5),
  .creditor-card > div:nth-child(6) {
    grid-column: 1 / -1;
  }
  .cc-actions {
    grid-column: 1 / -1;
    margin-top: 8px;
    justify-content: flex-start;
  }
  .cc-stats {
    grid-column: 1 / -1;
    flex-wrap: wrap;
    gap: 12px;
  }
}

@media (max-width: 480px) {
  .creditor-card{
    grid-template-columns: 1fr;
    gap: 10px;
    padding: 12px;
  }
  .creditor-card > div {
    grid-column: 1 / -1;
  }
  .cc-icon {
    width: 32px;
    height: 32px;
    font-size: 14px;
  }
  .cc-label {
    font-size: 9px;
  }
  .cc-name {
    font-size: 13px;
  }
  .cc-balance {
    font-size: 14px;
  }
  .cc-btn {
    padding: 6px 10px;
    font-size: 11px;
  }
  .cc-btn span {
    display: none;
  }
  .cc-btn i {
    margin-right: 0;
  }
}
.cc-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;background:rgba(64,153,255,.15);color:var(--sky);flex-shrink:0;}
.cc-label{font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;}
.cc-name{font-size:14px;font-weight:700;color:var(--text);word-break:break-word;}
.cc-balance{font-size:16px;font-weight:800;font-family:'JetBrains Mono',monospace;letter-spacing:-.5px;white-space:nowrap;}
.cc-currency{font-size:12px;color:var(--text-muted);font-weight:600;white-space:nowrap;}
.cc-status{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;white-space:nowrap;}
.cc-status.active{background:rgba(16,185,129,.15);color:var(--emerald);}
.cc-status.inactive{background:rgba(244,63,94,.15);color:var(--rose);}
.cc-actions{display:flex;gap:6px;flex-wrap:wrap;flex-shrink:0;align-items:center;}
.cc-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:600;border:none;cursor:pointer;transition:all .2s;font-family:inherit;flex-shrink:0;white-space:nowrap;}
.cc-btn span{display:inline-block;}
.cc-btn-primary{background:var(--grad);color:#fff;}
.cc-btn-primary:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(64,153,255,.3);}
.cc-btn-info{background:rgba(14,165,233,.15);color:var(--sky);border:1px solid rgba(14,165,233,.3);}
.cc-btn-info:hover{background:rgba(14,165,233,.25);border-color:rgba(14,165,233,.5);}
.cc-btn-warning{background:rgba(245,158,11,.15);color:var(--amber);border:1px solid rgba(245,158,11,.3);}
.cc-btn-warning:hover{background:rgba(245,158,11,.25);border-color:rgba(245,158,11,.5);}
.cc-btn-danger{background:rgba(244,63,94,.15);color:var(--rose);border:1px solid rgba(244,63,94,.3);}
.cc-btn-danger:hover{background:rgba(244,63,94,.25);border-color:rgba(244,63,94,.5);}

.cc-stats{display:flex;gap:16px;font-size:12px;}
.cc-stat{white-space:nowrap;}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="pcoded-inner-content">
            <div class="main-body">
                <div class="page-wrapper">
                    <div class="dash-wrap">
                        <div class="dash-inner">
                            <?php if ($error): ?>
                                <div class="d-alert d-alert-danger">
                                    <i class="feather icon-alert-circle"></i>
                                    <div>
                                        <strong><?= __("error") ?></strong>
                                        <p><?php echo h($error); ?></p>
                                    </div>
                                </div>
                                <div>
                                    <a href="creditors.php" class="dbtn dbtn-ghost">
                                        <i class="feather icon-arrow-left"></i>
                                        <span><?= __("back_to_creditors") ?></span>
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- Creditor Details Header -->
                                <div class="sec-label">
                                    <i class="feather icon-user"></i>
                                    <?= __("creditor_information") ?>
                                </div>

                                <!-- Creditor Information Card -->
                                <div class="d-card">
                                    <div class="d-card-header">
                                        <div class="d-card-title">
                                            <div class="ci ci-sky">
                                                <i class="feather icon-user"></i>
                                            </div>
                                            <?php echo htmlspecialchars($creditorData['name']); ?>
                                        </div>
                                        <span class="cc-status <?php echo strtolower($creditorData['status']); ?>">
                                            <?php echo htmlspecialchars($creditorData['status']); ?>
                                        </span>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                                        <div>
                                            <div class="cc-label"><?= __("email") ?></div>
                                            <div class="cc-name"><?php echo htmlspecialchars($creditorData['email'] ?: '—'); ?></div>
                                        </div>
                                        <div>
                                            <div class="cc-label"><?= __("phone") ?></div>
                                            <div class="cc-name"><?php echo htmlspecialchars($creditorData['phone'] ?: '—'); ?></div>
                                        </div>
                                        <div>
                                            <div class="cc-label"><?= __("address") ?></div>
                                            <div class="cc-name"><?php echo htmlspecialchars($creditorData['address'] ?: '—'); ?></div>
                                        </div>
                                        <div>
                                            <div class="cc-label"><?= __("balance") ?></div>
                                            <div class="cc-balance" style="color: <?php echo ($creditorData['balance'] > 0) ? 'var(--rose)' : 'var(--emerald)'; ?>">
                                                <?php echo number_format($creditorData['balance'], 2); ?> <span class="cc-currency"><?php echo htmlspecialchars($creditorData['currency']); ?></span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="cc-label"><?= __("currency") ?></div>
                                            <div class="cc-name"><?php echo htmlspecialchars($creditorData['currency']); ?></div>
                                        </div>
                                        <div>
                                            <div class="cc-label"><?= __("created_at") ?></div>
                                            <div class="cc-name"><?php echo date('Y-m-d H:i', strtotime($creditorData['created_at'])); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Transactions History -->
                                <div class="sec-label" style="margin-top: 32px;">
                                    <i class="feather icon-activity"></i>
                                    <?= __("transaction_history") ?>
                                </div>

                                <div class="d-card">
                                    <div class="d-card-header">
                                        <div class="d-card-title">
                                            <div class="ci ci-violet">
                                                <i class="feather icon-activity"></i>
                                            </div>
                                            <?= __("transactions") ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($transactions)): ?>
                                        <div style="overflow-x: auto;">
                                            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                                                <thead>
                                                    <tr style="border-bottom: 2px solid var(--border);">
                                                        <th style="padding: 12px 16px; text-align: left; color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 10px;"><?= __("date") ?></th>
                                                        <th style="padding: 12px 16px; text-align: left; color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 10px;"><?= __("amount") ?></th>
                                                        <th style="padding: 12px 16px; text-align: left; color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 10px;"><?= __("type") ?></th>
                                                        <th style="padding: 12px 16px; text-align: left; color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 10px;"><?= __("reference_number") ?></th>
                                                        <th style="padding: 12px 16px; text-align: left; color: var(--text-muted); font-weight: 700; text-transform: uppercase; font-size: 10px;"><?= __("description") ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($transactions as $transaction): ?>
                                                    <tr style="border-bottom: 1px solid var(--border);">
                                                        <td style="padding: 12px 16px; color: var(--text);"><?php echo date('Y-m-d', strtotime($transaction['payment_date'])); ?></td>
                                                        <td style="padding: 12px 16px; color: <?php echo ($transaction['amount'] > 0) ? 'var(--emerald)' : 'var(--rose)'; ?>; font-weight: 600;">
                                                            <?php echo htmlspecialchars($transaction['currency']) . ' ' . number_format(abs($transaction['amount']), 2); ?>
                                                        </td>
                                                        <td style="padding: 12px 16px; color: var(--text);"><?php echo isset($transaction['transaction_type']) ? htmlspecialchars($transaction['transaction_type']) : '—'; ?></td>
                                                        <td style="padding: 12px 16px; color: var(--text);"><?php echo isset($transaction['reference_number']) ? htmlspecialchars($transaction['reference_number']) : '—'; ?></td>
                                                        <td style="padding: 12px 16px; color: var(--text-muted);"><?php echo isset($transaction['description']) ? htmlspecialchars($transaction['description']) : '—'; ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-alert d-alert-warning" style="margin: 0;">
                                            <i class="feather icon-info"></i>
                                            <div>
                                                <p><?= __("no_transactions_found") ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Buttons -->
                                <div style="display: flex; gap: 12px; margin-top: 20px;">
                                    <a href="creditors.php" class="dbtn dbtn-ghost">
                                        <i class="feather icon-arrow-left"></i>
                                        <span><?= __("back_to_creditors") ?></span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>
<!-- Vendor scripts (keep order matching original) -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
<script src="../js/creditor/transaction_update.js"></script>
<script src="../js/creditor/print_receipt.js"></script>
</body>
</html>