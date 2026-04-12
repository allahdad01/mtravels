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
    header('Location: ../login.php');
    exit();
}

$payment_id = intval($_GET['id'] ?? 0);

// Fetch payment
$stmt = $pdo->prepare("SELECT sp.*, sa.name as agent_name FROM sales_agent_salary_payments sp 
                       JOIN sales_agents sa ON sp.sales_agent_id = sa.id 
                       WHERE sp.id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    header('Location: manage_salary_payments.php?error=payment_not_found');
    exit();
}

$message = '';
$error = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_salary_payment'])) {
    $amount = floatval($_POST['amount'] ?? 0);
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $status = $_POST['status'] ?? 'paid';
    $notes = $_POST['notes'] ?? '';

    if ($amount <= 0) {
        $error = "Please provide a valid amount.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE sales_agent_salary_payments 
                                   SET amount = ?, payment_date = ?, status = ?, notes = ?, updated_at = NOW()
                                   WHERE id = ?");
            $stmt->execute([$amount, $payment_date, $status, $notes, $payment_id]);
            $message = "Salary payment updated successfully.";
            
            // Reload payment data
            $stmt = $pdo->prepare("SELECT sp.*, sa.name as agent_name FROM sales_agent_salary_payments sp 
                                   JOIN sales_agents sa ON sp.sales_agent_id = sa.id 
                                   WHERE sp.id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();

            // Log action
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                   VALUES (?, 'update_salary_payment', 'salary_payment', ?, ?, ?, NOW())");
            $details = json_encode(['amount' => $amount, 'status' => $status]);
            $stmt->execute([$_SESSION['user_id'], $payment_id, $details, $_SERVER['REMOTE_ADDR']]);
        } catch (Exception $e) {
            $error = "Error updating salary payment: " . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && $_GET['delete'] == 1) {
    try {
        $stmt = $pdo->prepare("DELETE FROM sales_agent_salary_payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        
        // Log action
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                               VALUES (?, 'delete_salary_payment', 'salary_payment', ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $payment_id, '{}', $_SERVER['REMOTE_ADDR']]);
        
        header('Location: manage_salary_payments.php?message=deleted');
        exit();
    } catch (Exception $e) {
        $error = "Error deleting payment: " . $e->getMessage();
    }
}
?>

<?php include '../includes/header_super_admin.php'; ?>

<style>
  :root {
    --brand: #2563EB;
    --border: #E2E8F0;
    --bg: #F8FAFC;
    --surface: #FFFFFF;
    --text-primary: #0F172A;
    --text-secondary: #64748B;
    --radius: 12px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06);
  }

  .sa-page { background: var(--bg); padding: 24px; }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); margin-bottom: 24px; }
  .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); }
  .card-header h6 { margin: 0; font-size: .88rem; font-weight: 700; color: var(--text-primary); }
  .card-body { padding: 20px; }

  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-size: .85rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
  .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: .85rem; }
  .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; font-size: .85rem; transition: all .15s; }
  .btn-primary { background: var(--brand); color: white; }
  .btn-danger { background: #DC2626; color: white; }
  .btn-primary:hover { background: #1D4ED8; }
  .btn-danger:hover { background: #991B1B; }

  .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: .85rem; }
  .alert-success { background: #F0FDF4; color: #16A34A; border: 1px solid #16A34A; }
  .alert-danger { background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626; }

  .info-box { background: #ECFEFF; color: #0891B2; border: 1px solid #0891B2; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: .85rem; }
  .button-group { display: flex; gap: 12px; margin-top: 24px; }
</style>

<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">
        <div class="page-header">
          <div class="page-block">
            <div class="row align-items-center">
              <div class="col-md-12">
                <div class="page-header-title">
                  <h5 class="m-b-10">Edit Salary Payment</h5>
                </div>
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                  <li class="breadcrumb-item"><a href="manage_salary_payments.php">Salary Payments</a></li>
                  <li class="breadcrumb-item"><a href="#!">Edit</a></li>
                </ul>
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

            <div class="info-box">
              <strong>Agent:</strong> <?= htmlspecialchars($payment['agent_name']) ?><br>
              <small>Created: <?= date('M d, Y H:i', strtotime($payment['created_at'])) ?></small>
            </div>

            <div class="card">
              <div class="card-header">
                <h6>Payment Details</h6>
              </div>
              <div class="card-body">
                <form method="POST" action="">
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                      <label for="amount">Amount *</label>
                      <input type="number" id="amount" name="amount" step="0.01" min="0" value="<?= $payment['amount'] ?>" required>
                    </div>

                    <div class="form-group">
                      <label for="payment_date">Payment Date *</label>
                      <input type="date" id="payment_date" name="payment_date" value="<?= $payment['payment_date'] ?>" required>
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                      <option value="paid" <?= $payment['status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                      <option value="processing" <?= $payment['status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Add any notes..."></textarea>
                  </div>

                  <input type="hidden" name="update_salary_payment" value="1">

                  <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                      Update Payment
                    </button>
                    <a href="manage_salary_payments.php" class="btn" style="background: var(--border); color: var(--text-primary);">Cancel</a>
                    <a href="?id=<?= $payment_id ?>&delete=1" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this payment?');">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      Delete
                    </a>
                  </div>
                </form>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
