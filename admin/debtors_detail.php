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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_permission('finance.debtors');

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];
require_once '../includes/db.php';

// Initialize variables
$debtorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$debtorData = null;
$transactions = [];
$error = null;

// Check if ID is provided
if (!$debtorId) {
    $error = "No debtor ID provided";
} else {
    // Get debtor details
    $debtorQuery = "SELECT * FROM debtors WHERE id = ? AND tenant_id = ? AND branch_id = ?";
    
        $stmt = $pdo->prepare($debtorQuery);
        $stmt->execute([$debtorId, $tenant_id, $branch_id]);
    $debtorData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$debtorData) {
        $error = "Debtor not found";
    } else {
        // Get transactions related to this debtor
        $transactionsQuery = "SELECT
                        dt.id,
                        dt.debtor_id,
                        dt.amount,
                        dt.currency,
                        dt.transaction_type,
                        dt.description,
                        dt.reference_number,
                        dt.payment_date AS transaction_date,
                        dt.created_at
                    FROM debtor_transactions dt
                    WHERE dt.debtor_id = ? AND dt.tenant_id = ? AND dt.branch_id = ?
                    ORDER BY dt.payment_date DESC";
        
                $stmt = $pdo->prepare($transactionsQuery);
                $stmt->execute([$debtorId, $tenant_id, $branch_id]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

 include '../includes/header.php'; 
 ?>
<link rel="stylesheet" href="../css/general/modal-styles.css">
<link rel="stylesheet" href="../css/debtors/styles.css">

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
.d-alert-success{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3);}

/* Detailed view styles */
.detail-info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}

.detail-info-box {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 20px;
  margin-bottom: 16px;
}

.detail-info-box:last-child {
  margin-bottom: 0;
}

.detail-info-label {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-muted);
  letter-spacing: 0.8px;
  margin-bottom: 8px;
}

.detail-info-value {
  font-size: 16px;
  font-weight: 600;
  color: var(--text);
  word-break: break-word;
}

.detail-info-value.amount {
  font-family: 'JetBrains Mono', monospace;
  font-size: 18px;
  font-weight: 800;
}

.detail-info-value.positive {
  color: var(--emerald);
}

.detail-info-value.negative {
  color: var(--rose);
}

/* Transactions table styling */
.trans-table {
  background: var(--surface);
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--border);
}

.trans-table table {
  margin: 0;
}

.trans-table thead {
  background: var(--surface2);
  border-bottom: 2px solid var(--border);
}

.trans-table th {
  font-weight: 700;
  font-size: 12px;
  text-transform: uppercase;
  color: var(--text-muted);
  letter-spacing: 0.8px;
  padding: 16px;
  border: none;
}

.trans-table td {
  padding: 16px;
  border-color: var(--border);
}

.trans-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background .2s;
}

.trans-table tbody tr:hover {
  background: var(--surface2);
}

.trans-table tbody tr:last-child {
  border-bottom: none;
}
</style>

<div class="pcoded-main-container">
    <div class="pcoded-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10"><?= __('debtor_details') ?></h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php"><i class="feather icon-home"></i></a></li>
                            <li class="breadcrumb-item"><a href="debtors.php"><?= __('debtors') ?></a></li>
                            <li class="breadcrumb-item"><a href="javascript:"><?= __('debtor_details') ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="dash-wrap">
            <div class="dash-inner">
                <?php if ($error): ?>
                    <div class="d-alert d-alert-danger">
                        <div style="flex: 1;">
                            <strong><?= __('error') ?>:</strong> <?php echo h($error); ?>
                            <br><a href="debtors.php" class="dbtn dbtn-ghost" style="margin-top: 12px;">
                                <i class="feather icon-arrow-left"></i> <?= __('back_to_debtors') ?>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Debtor Information Cards -->
                    <div class="sec-label">
                        <i class="feather icon-user"></i> <?= __('debtor_information') ?>
                    </div>
                    
                    <div class="detail-info-grid">
                        <!-- Left Column -->
                        <div>
                            <div class="detail-info-box">
                                <div class="detail-info-label"><?= __('name') ?></div>
                                <div class="detail-info-value"><?php echo isset($debtorData['name']) ? htmlspecialchars($debtorData['name']) : '—'; ?></div>
                            </div>
                            <div class="detail-info-box">
                                <div class="detail-info-label"><?= __('email') ?></div>
                                <div class="detail-info-value"><?php echo isset($debtorData['email']) ? htmlspecialchars($debtorData['email']) : '—'; ?></div>
                            </div>
                        </div>

                        <!-- Middle Column -->
                        <div>
                            <div class="detail-info-box">
                                <div class="detail-info-label"><?= __('phone') ?></div>
                                <div class="detail-info-value"><?php echo isset($debtorData['phone']) ? htmlspecialchars($debtorData['phone']) : '—'; ?></div>
                            </div>
                            <div class="detail-info-box">
                                <div class="detail-info-label"><?= __('address') ?></div>
                                <div class="detail-info-value"><?php echo isset($debtorData['address']) ? htmlspecialchars($debtorData['address']) : '—'; ?></div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div>
                            <div class="detail-info-box">
                                <div class="detail-info-label"><?= __('balance') ?></div>
                                <div class="detail-info-value amount <?php echo (isset($debtorData['balance']) && $debtorData['balance'] > 0) ? 'negative' : 'positive'; ?>">
                                    <?php 
                                    if (isset($debtorData['currency']) && isset($debtorData['balance'])) {
                                        echo htmlspecialchars($debtorData['currency']) . ' ' . number_format($debtorData['balance'], 2);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="detail-info-box">
                                <div class="detail-info-label"><?= __('status') ?></div>
                                <div style="margin-top: 8px;">
                                    <span class="dc-status <?php echo isset($debtorData['status']) ? strtolower($debtorData['status']) : 'unknown'; ?>">
                                        <?php echo isset($debtorData['status']) ? htmlspecialchars($debtorData['status']) : 'Unknown'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timestamps -->
                    <div class="d-card">
                        <div class="d-card-header">
                            <div class="d-card-title">
                                <i class="feather icon-clock"></i> <?= __('record_dates') ?>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                            <div>
                                <div class="detail-info-label"><?= __('created_at') ?></div>
                                <div class="detail-info-value"><?php echo isset($debtorData['created_at']) ? date('Y-m-d H:i', strtotime($debtorData['created_at'])) : '—'; ?></div>
                            </div>
                            <div>
                                <div class="detail-info-label"><?= __('updated_at') ?></div>
                                <div class="detail-info-value"><?php echo isset($debtorData['updated_at']) ? date('Y-m-d H:i', strtotime($debtorData['updated_at'])) : '—'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions History -->
                    <div class="sec-label" style="margin-top: 28px;">
                        <i class="feather icon-activity"></i> <?= __('transaction_history') ?>
                    </div>
                    
                    <div class="d-card">
                        <?php if (!empty($transactions)): ?>
                        <div class="trans-table">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('type') ?></th>
                                        <th><?= __('amount') ?></th>
                                        <th><?= __('description') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <span style="font-weight: 600; color: var(--text);">
                                                <?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="dc-status <?php 
                                                echo (isset($transaction['transaction_type']) && strtolower($transaction['transaction_type']) == 'payment') ? 'active' : 'inactive'; 
                                            ?>">
                                                <?php echo isset($transaction['transaction_type']) ? ucfirst(strtolower($transaction['transaction_type'])) : '—'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="detail-info-value amount <?php echo (isset($transaction['transaction_type']) && strtolower($transaction['transaction_type']) == 'payment') ? 'positive' : 'negative'; ?>">
                                                <?php 
                                                if (isset($transaction['currency']) && isset($transaction['amount'])) {
                                                    echo htmlspecialchars($transaction['currency']) . ' ' . number_format($transaction['amount'], 2);
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td style="color: var(--text-muted);">
                                            <?php 
                                            if (isset($transaction['description']) && !empty($transaction['description'])) {
                                                echo htmlspecialchars($transaction['description']);
                                            } elseif (isset($transaction['reference_number']) && !empty($transaction['reference_number'])) {
                                                echo '<strong>Ref:</strong> ' . htmlspecialchars($transaction['reference_number']);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="d-alert d-alert-warning">
                            <i class="feather icon-info" style="margin-top: 2px;"></i>
                            <div><?= __('no_transactions_found_for_this_debtor') ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 12px; margin-top: 28px; flex-wrap: wrap;">
                        <a href="debtors.php" class="dbtn dbtn-ghost">
                            <i class="feather icon-arrow-left"></i> <?= __('back_to_debtors') ?>
                        </a>
                        
                        <a href="debtors.php" class="dbtn dbtn-primary">
                            <i class="feather icon-dollar-sign"></i> <?= __('record_payment') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Required Js -->
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>

<!-- Include Admin Footer -->
<?php include '../includes/admin_footer.php'; ?>

</body>
</html> 