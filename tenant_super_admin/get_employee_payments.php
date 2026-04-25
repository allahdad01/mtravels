<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$user_id   = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$page      = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$results_per_page = 20;
$offset    = ($page - 1) * $results_per_page;

// Validate user belongs to tenant
$user_check = $pdo->prepare("SELECT id, name FROM users WHERE id = ? AND tenant_id = ?");
$user_check->execute([$user_id, $tenant_id]);
$employee = $user_check->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    echo '<div class="alert alert-danger">Employee not found or access denied</div>';
    exit();
}

// Get payments with pagination
$query = "SELECT
    sp.*,
    ma.name as account_name
FROM salary_payments sp
LEFT JOIN main_account ma ON sp.main_account_id = ma.id
WHERE sp.user_id = ? AND sp.tenant_id = ?
ORDER BY sp.payment_date DESC, sp.created_at DESC
LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id, $tenant_id, $results_per_page, $offset]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$count_query = "SELECT COUNT(*) as total FROM salary_payments WHERE user_id = ? AND tenant_id = ?";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute([$user_id, $tenant_id]);
$total_payments = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_payments / $results_per_page);

// Get summary
$summary_query = "SELECT
    COUNT(*) as total_count,
    COALESCE(SUM(amount), 0) as total_amount,
    COUNT(CASE WHEN payment_type = 'regular' THEN 1 END) as regular_count,
    COUNT(CASE WHEN payment_type = 'bonus' THEN 1 END) as bonus_count,
    COUNT(CASE WHEN payment_type = 'advance' THEN 1 END) as advance_count
FROM salary_payments
WHERE user_id = ? AND tenant_id = ?";
$summary_stmt = $pdo->prepare($summary_query);
$summary_stmt->execute([$user_id, $tenant_id]);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

$payment_type_colors = [
    'regular' => 'info',
    'bonus'   => 'success',
    'advance' => 'warning',
    'other'   => 'secondary'
];

function currency_symbol($currency) {
    $symbols = [
        'USD'    => '$',
        'AFS'    => '؋',
        'EUR'    => '€',
        'DARHAM' => 'د.إ',
    ];
    return $symbols[strtoupper($currency ?? '')] ?? '';
}
?>

<style>
.pay-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
    padding: 0 24px;
}
@media(max-width:700px){ .pay-summary-grid { grid-template-columns: repeat(2, 1fr); } }
.pay-sum-card {
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.pay-sum-card::after {
    content: '';
    position: absolute;
    right: -8px;
    bottom: -8px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
}
.pay-sum-card.total  { background: linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%); box-shadow: 0 6px 20px rgba(79,70,229,0.25); }
.pay-sum-card.regular{ background: linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%); box-shadow: 0 6px 20px rgba(5,150,105,0.25); }
.pay-sum-card.bonus  { background: linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%); box-shadow: 0 6px 20px rgba(124,58,237,0.25); }
.pay-sum-card.advance{ background: linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%); box-shadow: 0 6px 20px rgba(180,83,9,0.25); }
.pay-sum-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    opacity: .8;
    margin-bottom: 6px;
}
.pay-sum-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 18px;
    font-weight: 800;
    line-height: 1;
}
.pay-table-wrap { padding: 0 24px 24px; }
.pay-table {
    width: 100%;
    border-collapse: collapse;
}
.pay-table thead th {
    background: #f4f7fe;
    padding: 10px 14px;
    font-size: 11px;
    font-weight: 700;
    color: #6b7a99;
    text-transform: uppercase;
    letter-spacing: .6px;
    border-bottom: 1.5px solid #e8edf5;
    white-space: nowrap;
    text-align: left;
}
.pay-table tbody tr { transition: background .15s; }
.pay-table tbody tr:hover { background: #f4f7fe; }
.pay-table tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid #e8edf5;
    font-size: 13px;
    vertical-align: middle;
}
.pay-table tbody tr:last-child td { border-bottom: none; }
.pay-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border-radius: 20px;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}
.pay-amount {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 800;
    color: #059669;
}
.pay-empty {
    text-align: center;
    padding: 50px 20px;
}
.pay-empty i {
    font-size: 44px;
    opacity: .2;
    display: block;
    margin-bottom: 14px;
}
.pay-empty p {
    color: #6b7a99;
    font-size: 14px;
    margin: 0;
}
.pay-pag {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px 24px;
    border-top: 1px solid #e8edf5;
}
.pay-pag-info {
    font-size: 12px;
    color: #6b7a99;
}
.pay-pag-links {
    display: flex;
    gap: 4px;
}
.pay-pag-btn {
    min-width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 1.5px solid #e8edf5;
    background: #fff;
    color: #1a2340;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    padding: 0 8px;
    cursor: pointer;
    transition: all .15s;
}
.pay-pag-btn:hover {
    border-color: #4f46e5;
    color: #4f46e5;
    text-decoration: none;
}
.pay-pag-btn.active {
    background: linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);
    border-color: transparent;
    color: #fff;
}
.pay-pag-btn.disabled {
    opacity: .4;
    pointer-events: none;
}
.pay-pag-dots {
    display: flex;
    align-items: center;
    padding: 0 4px;
    color: #6b7a99;
    font-size: 13px;
}
</style>

<!-- Summary -->
<div class="pay-summary-grid">
    <div class="pay-sum-card total">
        <div class="pay-sum-label">Total Payments</div>
        <div class="pay-sum-value"><?= number_format($summary['total_count'] ?? 0) ?></div>
    </div>
    <div class="pay-sum-card regular">
        <div class="pay-sum-label">Regular</div>
        <div class="pay-sum-value"><?= number_format($summary['regular_count'] ?? 0) ?></div>
    </div>
    <div class="pay-sum-card bonus">
        <div class="pay-sum-label">Bonuses</div>
        <div class="pay-sum-value"><?= number_format($summary['bonus_count'] ?? 0) ?></div>
    </div>
    <div class="pay-sum-card advance">
        <div class="pay-sum-label">Advances</div>
        <div class="pay-sum-value"><?= number_format($summary['advance_count'] ?? 0) ?></div>
    </div>
</div>

<!-- Table -->
<div class="pay-table-wrap">
    <?php if (empty($payments)): ?>
    <div class="pay-empty">
        <i class="feather icon-credit-card"></i>
        <p>No salary payments found for this employee.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="pay-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>For Month</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Currency</th>
                    <th>Account</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p):
                    $typeColor = $payment_type_colors[$p['payment_type']] ?? 'secondary';
                ?>
                <tr>
                    <td><?= $p['payment_date'] ? date('M d, Y', strtotime($p['payment_date'])) : 'N/A' ?></td>
                    <td><?= $p['payment_for_month'] ? date('F Y', strtotime($p['payment_for_month'])) : 'N/A' ?></td>
                    <td>
                        <span class="pay-badge badge-<?= $typeColor ?>">
                            <i class="feather icon-<?= $p['payment_type'] === 'bonus' ? 'gift' : ($p['payment_type'] === 'advance' ? 'arrow-up-circle' : 'check-circle') ?>"></i>
                            <?= ucfirst($p['payment_type']) ?>
                        </span>
                    </td>
                    <td class="pay-amount"><?= currency_symbol($p['currency']) ?><?= number_format($p['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($p['currency']) ?></td>
                    <td><?= htmlspecialchars($p['account_name'] ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($p['description'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php if ($total_pages > 1): ?>
<div class="pay-pag">
    <div class="pay-pag-info">Page <?= $page ?> of <?= $total_pages ?></div>
    <div class="pay-pag-links">
        <button class="pay-pag-btn <?= $page <= 1 ? 'disabled' : '' ?>" onclick="loadEmployeePayments(<?= $user_id ?>, 1)"><i class="feather icon-chevrons-left"></i></button>
        <button class="pay-pag-btn <?= $page <= 1 ? 'disabled' : '' ?>" onclick="loadEmployeePayments(<?= $user_id ?>, <?= $page - 1 ?>)"><i class="feather icon-chevron-left"></i></button>

        <?php
        $sp = max(1, $page - 2);
        $ep = min($total_pages, $page + 2);
        if ($sp > 1) {
            echo '<button class="pay-pag-btn" onclick="loadEmployeePayments(' . $user_id . ', 1)">1</button>';
            if ($sp > 2) echo '<span class="pay-pag-dots">...</span>';
        }
        for ($i = $sp; $i <= $ep; $i++) {
            echo '<button class="pay-pag-btn ' . ($i == $page ? 'active' : '') . '" onclick="loadEmployeePayments(' . $user_id . ', ' . $i . ')">' . $i . '</button>';
        }
        if ($ep < $total_pages) {
            if ($ep < $total_pages - 1) echo '<span class="pay-pag-dots">...</span>';
            echo '<button class="pay-pag-btn" onclick="loadEmployeePayments(' . $user_id . ', ' . $total_pages . ')">' . $total_pages . '</button>';
        }
        ?>

        <button class="pay-pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" onclick="loadEmployeePayments(<?= $user_id ?>, <?= $page + 1 ?>)"><i class="feather icon-chevron-right"></i></button>
        <button class="pay-pag-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" onclick="loadEmployeePayments(<?= $user_id ?>, <?= $total_pages ?>)"><i class="feather icon-chevrons-right"></i></button>
    </div>
</div>
<?php endif; ?>

<script>
function loadEmployeePayments(userId, page) {
    document.getElementById('paymentsContent').innerHTML =
        '<div class="txn-loading"><div class="spinner"></div><p style="color:var(--text-sub);font-size:13px;margin:0;">Loading payment history...</p></div>';
    fetch('get_employee_payments.php?user_id=' + userId + '&page=' + page)
        .then(r => r.text())
        .then(html => { document.getElementById('paymentsContent').innerHTML = html; })
        .catch(err => {
            document.getElementById('paymentsContent').innerHTML =
                '<div style="padding:20px;color:#ef4444;">Error: ' + err.message + '</div>';
        });
}
</script>
