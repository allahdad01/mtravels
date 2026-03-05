<?php
session_start();
require_once '../includes/db.php';

// Set secure headers
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check session timeout
$sessionTimeout = 30 * 60;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    error_log("Unauthorized access attempt to manage_salary_payments.php: " . ($_SESSION['user_id'] ?? 'unknown') . " - IP: " . $_SERVER['REMOTE_ADDR']);
    header('Location: ../login.php');
    exit();
}

// Handle salary payment creation/update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_salary_payment'])) {
    $agent_id = intval($_POST['agent_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'paid';
    $notes = $_POST['notes'] ?? '';

    if ($agent_id <= 0 || $amount <= 0) {
        $error = "Please provide valid agent and amount.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO sales_agent_salary_payments 
                                   (sales_agent_id, amount, payment_date, status, notes, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$agent_id, $amount, $payment_date, $status, $notes]);
            $message = "Salary payment recorded successfully.";
            
            // Log action
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                   VALUES (?, 'create_salary_payment', 'salary_payment', ?, ?, ?, NOW())");
            $details = json_encode(['agent_id' => $agent_id, 'amount' => $amount, 'payment_date' => $payment_date]);
            $stmt->execute([$_SESSION['user_id'], $agent_id, $details, $_SERVER['REMOTE_ADDR']]);
        } catch (Exception $e) {
            $error = "Error recording salary payment: " . $e->getMessage();
            error_log("Error recording salary payment: " . $e->getMessage());
        }
    }
}

// Pagination
$items_per_page = 15;
$current_page = intval($_GET['page'] ?? 1);
$agent_filter = intval($_GET['agent'] ?? 0);
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($agent_filter > 0) {
    $where_conditions[] = "sp.sales_agent_id = ?";
    $params[] = $agent_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "sp.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_query = "SELECT COUNT(*) as total FROM sales_agent_salary_payments sp " . $where_clause;
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);
$current_page = max(1, min($current_page, $total_pages));
$offset = ($current_page - 1) * $items_per_page;

// Fetch salary payments
$query = "SELECT sp.*, sa.name as agent_name FROM sales_agent_salary_payments sp 
          JOIN sales_agents sa ON sp.sales_agent_id = sa.id 
          $where_clause
          ORDER BY sp.payment_date DESC LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$salary_payments = $stmt->fetchAll();

// Get summary stats
$summary_query = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
                    SUM(CASE WHEN status = 'processing' THEN amount ELSE 0 END) as processing,
                    COUNT(DISTINCT sales_agent_id) as agents_paid
                  FROM sales_agent_salary_payments 
                  WHERE status IN ('paid', 'processing')";
$stmt = $pdo->prepare($summary_query);
$stmt->execute();
$summary = $stmt->fetch();

// Get all agents with salary
$stmt = $pdo->prepare("SELECT id, name, salary_type FROM sales_agents WHERE salary_type IN ('salary', 'both') ORDER BY name ASC");
$stmt->execute();
$all_agents = $stmt->fetchAll();
?>

<?php include '../includes/header_super_admin.php'; ?>

<style>
  :root {
    --brand: #2563EB;
    --success: #16A34A;
    --warning: #D97706;
    --border: #E2E8F0;
    --bg: #F8FAFC;
    --surface: #FFFFFF;
    --text-primary: #0F172A;
    --text-secondary: #64748B;
    --text-muted: #94A3B8;
    --radius: 12px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06);
  }

  .sa-page { background: var(--bg); padding: 24px; }
  .section-label { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 12px; }
  
  .metric-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
  @media (max-width: 992px) { .metric-grid { grid-template-columns: repeat(2,1fr); } }
  @media (max-width: 576px) { .metric-grid { grid-template-columns: 1fr; } }

  .metric-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; }
  .metric-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
  .metric-card.blue::before { background: var(--brand); }
  .metric-card.green::before { background: var(--success); }
  .metric-card.warning::before { background: var(--warning); }

  .metric-value { font-size: 1.75rem; font-weight: 800; color: var(--text-primary); letter-spacing: -.02em; }
  .metric-label { font-size: .83rem; color: var(--text-secondary); font-weight: 500; margin-top: 8px; }

  .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); margin-bottom: 24px; }
  .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
  .card-header h6 { margin: 0; font-size: .88rem; font-weight: 700; color: var(--text-primary); }
  .card-body { padding: 20px; }

  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-size: .85rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
  .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: .85rem; font-family: inherit; }
  .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; font-size: .85rem; transition: all .15s; }
  .btn-primary { background: var(--brand); color: white; }
  .btn-primary:hover { background: #1D4ED8; transform: translateY(-1px); }

  .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .85rem; }
  .alert-success { background: #F0FDF4; color: var(--success); border: 1px solid var(--success); }
  .alert-danger { background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626; }

  table { width: 100%; border-collapse: collapse; }
  th { background: var(--bg); padding: 12px; text-align: left; font-weight: 600; font-size: .85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border); }
  td { padding: 12px; border-bottom: 1px solid var(--border); font-size: .85rem; }
  tr:hover { background: var(--bg); }

  .pill { display: inline-block; font-size: .7rem; font-weight: 600; padding: 2px 9px; border-radius: 20px; }
  .pill-paid { background: #F0FDF4; color: var(--success); }
  .pill-processing { background: #FFFBEB; color: var(--warning); }

  .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; }
  .modal.show { display: flex; }
  .modal-content { background: var(--surface); border-radius: var(--radius); padding: 24px; max-width: 500px; width: 90%; }
  .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .modal-header h5 { margin: 0; font-size: 1.2rem; font-weight: 700; }
  .modal-close { background: none; border: none; cursor: pointer; font-size: 1.5rem; color: var(--text-muted); }

  /* ─── PAGE HEADER ─────────────────────────────────────────── */
  .page-header.card {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    color: white;
    border: none;
    box-shadow: 0 4px 12px rgba(64, 153, 255, 0.2);
    padding: 24px;
    margin-bottom: 24px;
  }

  .page-header-content { padding: 0.5rem 0; }

  .page-title {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.5px;
    display: flex;
    align-items: center;
    line-height: 1.2;
  }

  .page-title i { font-size: 2rem; margin-right: 0.75rem; opacity: 0.95; }

  .page-subtitle { font-size: 0.95rem; opacity: 0.85; font-weight: 400; letter-spacing: 0.3px; }

  .page-header-actions { display: flex; gap: 10px; align-items: center; justify-content: flex-end; width: 100%; }

  .btn-header-primary {
    background: rgba(255,255,255,0.15) !important;
    color: #ffffff !important;
    border: 1.5px solid rgba(255,255,255,0.40) !important;
    border-radius: 6px;
    padding: 0.65rem 1.25rem !important;
    font-size: 0.9rem !important;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .btn-header-primary:hover {
    background: rgba(255,255,255,0.25) !important;
    border-color: rgba(255,255,255,0.60) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
  }

  /* ─── CARDS ───────────────────────────────────────────────── */
  .sa-card { background: white; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
  .sa-card-body { padding: 16px; }

  /* ─── SEARCH FILTER ───────────────────────────────────────── */
  .sa-search-filter { display: flex; }
  .sa-search-group { display: flex; gap: 8px; width: 100%; flex-wrap: wrap; }
  .sa-search-input {
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.9rem;
    flex: 1;
    min-width: 150px;
  }
  .sa-search-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1); }

  /* ─── SECTION HEADER ──────────────────────────────────────── */
  .sa-shdr { display: flex; justify-content: space-between; align-items: center; }
  .sa-shdr h2 { font-size: 1.25rem; font-weight: 600; margin: 0; color: var(--text-primary); }

  /* ─── BUTTONS ─────────────────────────────────────────────── */
  .sa-btn {
    padding: 8px 12px;
    border: 1px solid;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
  }
  .sa-btn:hover { transform: translateY(-1px); }
  .sa-btn-primary { background: var(--brand); border-color: var(--brand); color: white; }
  .sa-btn-primary:hover { background: #3a89ff; border-color: #3a89ff; }
  .sa-btn-ghost { background: transparent; border-color: var(--border); color: var(--text-primary); }
  .sa-btn-ghost:hover { background: var(--bg); border-color: #999; }
  .sa-btn-small { padding: 6px 12px; font-size: 0.75rem; }

  /* ─── PAGINATION ──────────────────────────────────────────── */
  .sa-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    padding: 14px;
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    flex-wrap: wrap;
  }
  .sa-pagination-item {
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg);
    color: var(--text-primary);
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
  }
  .sa-pagination-item:hover:not(.active):not(.disabled) {
    background: rgba(64, 153, 255, 0.1);
    border-color: var(--brand);
    color: var(--brand);
  }
  .sa-pagination-item.active {
    background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
    border-color: var(--brand);
    color: white;
  }
  .sa-pagination-ellipsis { color: var(--text-muted); font-size: 0.8rem; }
  .sa-pagination-info { font-size: 0.8rem; color: var(--text-muted); margin-left: auto; }

  /* ─── PAYMENT LIST ─────────────────────────────────────────── */
  .sa-payment-list { display: flex; flex-direction: column; gap: 16px; }

  .sa-payment-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 20px;
    transition: all 0.2s ease;
  }
  .sa-payment-card:hover {
    border-color: rgba(64, 153, 255, 0.3);
    box-shadow: 0 4px 16px rgba(64, 153, 255, 0.15);
  }

  .spc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
  }

  .spc-info h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: var(--text-primary);
  }

  .spc-date {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .spc-amount { text-align: right; }

  .amount-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--success);
  }

  .spc-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
  }

  .spc-detail-item { display: flex; flex-direction: column; gap: 4px; }

  .spc-detail-label {
    font-size: 0.7rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .spc-detail-value {
    font-size: 0.95rem;
    font-weight: 500;
    color: var(--text-primary);
  }

  .spc-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    padding-top: 12px;
    border-top: 1px solid var(--border);
  }

  /* ─── RESPONSIVE ──────────────────────────────────────────── */
  @media (max-width: 768px) {
    .spc-header { flex-direction: column; }
    .spc-amount { margin-top: 12px; text-align: left; }
    .spc-details { grid-template-columns: repeat(2, 1fr); }
    .spc-actions { flex-direction: column; }
    .spc-actions .sa-btn { width: 100%; justify-content: center; }
  }
</style>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">
        <div class="page-header card">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="page-header-content">
                        <h5 class="page-title mb-0">
                            <i class="feather icon-dollar-sign mr-2"></i>Salary Payments
                        </h5>
                        <p class="page-subtitle mb-0 mt-2">
                            Manage sales agent salary payments
                        </p>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="page-header-actions">
                        <button type="button" class="btn btn-header-primary" onclick="document.getElementById('addPaymentModal').classList.add('show');">
                            <i class="feather icon-plus mr-1"></i>Record Payment
                        </button>
                    </div>
                </div>
            </div>
        </div>


        <div class="main-body">
          <div class="sa-page">

            <?php if (!empty($message)): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">✗ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Summary Stats -->
            <p class="section-label">Summary</p>
            <div class="metric-grid">
              <div class="metric-card blue">
                <div class="metric-value">$<?= number_format($summary['total_paid'] ?? 0, 2) ?></div>
                <div class="metric-label">Total Paid</div>
              </div>
              <div class="metric-card warning">
                <div class="metric-value">$<?= number_format($summary['processing'] ?? 0, 2) ?></div>
                <div class="metric-label">Processing</div>
              </div>
              <div class="metric-card green">
                <div class="metric-value"><?= $summary['total_payments'] ?? 0 ?></div>
                <div class="metric-label">Total Payments</div>
              </div>
              <div class="metric-card blue">
                <div class="metric-value"><?= $summary['agents_paid'] ?? 0 ?></div>
                <div class="metric-label">Agents Paid</div>
              </div>
            </div>

            <!-- Salary Payments Header -->
            <div class="sa-shdr" style="margin-bottom: 16px;">
                <div>
                    <h2>All Payments</h2>
                    <p style="margin: 4px 0 0 0; font-size: 0.75rem; color: var(--text-muted);">Total: <?= $total_items ?> payments</p>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="sa-card" style="margin-bottom: 20px;">
                <div class="sa-card-body">
                    <form method="GET" action="manage_salary_payments.php" class="sa-search-filter">
                        <div class="sa-search-group">
                            <select class="sa-search-input" name="agent" style="flex: 0 0 auto;" onchange="this.form.submit()">
                                <option value="">All Agents</option>
                                <?php foreach ($all_agents as $agent): ?>
                                <option value="<?= $agent['id'] ?>" <?= $agent_filter == $agent['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($agent['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="sa-search-input" name="status" style="flex: 0 0 auto;" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="paid" <?= $status_filter == 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="processing" <?= $status_filter == 'processing' ? 'selected' : '' ?>>Processing</option>
                            </select>
                            <?php if ($agent_filter > 0 || !empty($status_filter)): ?>
                            <a href="manage_salary_payments.php" class="sa-btn sa-btn-ghost">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Salary Payments Cards -->
            <?php if (empty($salary_payments)): ?>
            <div class="sa-card">
                <div class="sa-card-body" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <div style="font-size: 2rem; margin-bottom: 12px;">💰</div>
                    <div style="font-weight: 600; margin-bottom: 4px;">No Salary Payments Found</div>
                    <div style="font-size: 0.8rem;"><?= !empty($agent_filter) || !empty($status_filter) ? 'Try adjusting your filters.' : 'No salary payments recorded yet.' ?></div>
                </div>
            </div>
            <?php else: ?>
            <div class="sa-payment-list">
                <?php foreach ($salary_payments as $payment): ?>
                <div class="sa-payment-card">
                    <div class="spc-header">
                        <div class="spc-info">
                            <h4><?= htmlspecialchars($payment['agent_name']) ?></h4>
                            <p class="spc-date">
                                <i class="feather icon-calendar"></i>
                                <?= date('M d, Y', strtotime($payment['payment_date'])) ?>
                            </p>
                        </div>
                        <div class="spc-amount">
                            <span class="amount-value">$<?= number_format($payment['amount'], 2) ?></span>
                        </div>
                    </div>
                    
                    <div class="spc-details">
                        <div class="spc-detail-item">
                            <span class="spc-detail-label">Status</span>
                            <span class="pill pill-<?= $payment['status'] ?>"><?= ucfirst($payment['status']) ?></span>
                        </div>
                        <div class="spc-detail-item">
                            <span class="spc-detail-label">Notes</span>
                            <span class="spc-detail-value"><?= $payment['notes'] ? htmlspecialchars($payment['notes']) : '-' ?></span>
                        </div>
                    </div>
                    
                    <div class="spc-actions">
                        <a href="edit_salary_payment.php?id=<?= $payment['id'] ?>" class="sa-btn sa-btn-small sa-btn-primary">
                            <i class="feather icon-edit"></i> Edit
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="sa-pagination">
                <?php 
                $query_string = '';
                if ($agent_filter > 0) $query_string .= '&agent=' . $agent_filter;
                if (!empty($status_filter)) $query_string .= '&status=' . urlencode($status_filter);
                
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                ?>
                
                <?php if ($current_page > 1): ?>
                <a href="?page=1<?= $query_string ?>" class="sa-pagination-item">First</a>
                <a href="?page=<?= $current_page - 1 ?><?= $query_string ?>" class="sa-pagination-item">← Prev</a>
                <?php endif; ?>
                
                <?php if ($start_page > 1): ?>
                <span class="sa-pagination-ellipsis">...</span>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?= $i ?><?= $query_string ?>" class="sa-pagination-item <?= $i === $current_page ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                <span class="sa-pagination-ellipsis">...</span>
                <?php endif; ?>
                
                <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?= $current_page + 1 ?><?= $query_string ?>" class="sa-pagination-item">Next →</a>
                <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="sa-pagination-item">Last</a>
                <?php endif; ?>
                
                <span class="sa-pagination-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
            </div>
            <?php endif; ?>


          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Payment Modal -->
<div id="addPaymentModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h5>Record Salary Payment</h5>
      <button type="button" class="modal-close" onclick="document.getElementById('addPaymentModal').classList.remove('show');">&times;</button>
    </div>
    <form method="POST" action="">
      <div class="form-group">
        <label for="agent_id">Sales Agent *</label>
        <select id="agent_id" name="agent_id" required>
          <option value="">-- Select Agent --</option>
          <?php foreach ($all_agents as $agent): ?>
          <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="amount">Amount *</label>
        <input type="number" id="amount" name="amount" step="0.01" min="0" placeholder="0.00" required>
      </div>

      <div class="form-group">
        <label for="payment_date">Payment Date *</label>
        <input type="date" id="payment_date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
      </div>

      <div class="form-group">
        <label for="status">Status *</label>
        <select id="status" name="status" required>
          <option value="paid">Paid</option>
          <option value="processing">Processing</option>
        </select>
      </div>

      <div class="form-group">
        <label for="notes">Notes (Optional)</label>
        <textarea id="notes" name="notes" rows="3" placeholder="Add any notes..."></textarea>
      </div>

      <input type="hidden" name="add_salary_payment" value="1">
      <button type="submit" class="btn btn-primary" style="width: 100%;">Record Payment</button>
    </form>
  </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
