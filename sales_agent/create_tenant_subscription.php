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

// Check if user is a sales agent
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'sales_agent') {
    header('Location: ../login.php');
    exit();
}

// Get sales agent info
$stmt = $pdo->prepare("SELECT id, name, email FROM sales_agents WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: ../login.php');
    exit();
}

$agent_id = $agent['id'];
$error = '';
$success = '';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
    $tenant_name = trim($_POST['tenant_name'] ?? '');
    $tenant_email = trim($_POST['tenant_email'] ?? '');
    $tenant_phone = trim($_POST['tenant_phone'] ?? '');
    $tenant_address = trim($_POST['tenant_address'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $plan = $_POST['plan'] ?? 'basic';
    $billing_cycle = $_POST['billing_cycle'] ?? 'monthly';
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $notes = substr(trim($_POST['notes'] ?? ''), 0, 500); // Limit to 500 characters
    
    // Validation
    if (empty($tenant_name)) {
        $error = "Agency/Tenant name is required.";
    } elseif (strlen($tenant_name) > 255) {
        $error = "Tenant name must be less than 255 characters.";
    } elseif (empty($tenant_email) || !filter_var($tenant_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Valid email is required.";
    } elseif (strlen($tenant_email) > 255) {
        $error = "Email must be less than 255 characters.";
    } elseif (!empty($tenant_phone) && strlen($tenant_phone) > 20) {
        $error = "Phone number is too long.";
    } elseif (!in_array($plan, ['basic', 'pro', 'enterprise'])) {
        $error = "Invalid plan selected.";
    } elseif (empty($start_date)) {
        $error = "Subscription start date is required.";
    } else {
        // Check if tenant email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE name = ?");
        $stmt->execute([$tenant_name]);
        if ($stmt->fetch()['count'] > 0) {
            $error = "A tenant with this name already exists. Please use a different name.";
        }
    }

    if (empty($error)) {
        try {
            $pdo->beginTransaction();

            // Create unique identifier for tenant
            $identifier = strtolower(preg_replace('/[^a-z0-9-]/', '-', $tenant_name));
            $identifier = substr($identifier, 0, 100);
            
            // Ensure unique identifier
            $counter = 1;
            $original_identifier = $identifier;
            while (true) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE identifier = ?");
                $stmt->execute([$identifier]);
                if ($stmt->fetch()['count'] == 0) break;
                $identifier = $original_identifier . '-' . $counter;
                $counter++;
            }

            // Create tenant
            $stmt = $pdo->prepare("INSERT INTO tenants 
                                   (name, identifier, status, plan, billing_email, created_at, updated_at)
                                   VALUES (?, ?, 'active', ?, ?, NOW(), NOW())");
            $stmt->execute([$tenant_name, $identifier, $plan, $tenant_email]);
            $tenant_id = $pdo->lastInsertId();

            // Get plan pricing
            $stmt = $pdo->prepare("SELECT price, currency FROM plans WHERE name = ? LIMIT 1");
            $stmt->execute([$plan]);
            $plan_data = $stmt->fetch();
            $plan_price = $plan_data['price'] ?? 0;
            $currency = $plan_data['currency'] ?? 'USD';

            // Create subscription
            $stmt = $pdo->prepare("INSERT INTO subscriptions 
                                   (tenant_id, plan, billing_cycle, start_date, status, amount, currency, 
                                    sales_agent_id, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$tenant_id, $plan, $billing_cycle, $start_date, $plan_price, $currency, $agent_id]);
            $subscription_id = $pdo->lastInsertId();

            // Create sales_agent_tenants link
            $stmt = $pdo->prepare("INSERT INTO sales_agent_tenants 
                                   (sales_agent_id, tenant_id, subscription_start_date, status, created_at, updated_at)
                                   VALUES (?, ?, ?, 'active', NOW(), NOW())");
            $stmt->execute([$agent_id, $tenant_id, $start_date]);

            // Create tenant super admin user (default credentials)
            $default_password = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 10);
            $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users 
                                   (tenant_id, name, email, password, role, created_at, updated_at)
                                   VALUES (?, ?, ?, ?, 'tenant_super_admin', NOW(), NOW())");
            $stmt->execute([$tenant_id, $contact_person ?: $tenant_name, $tenant_email, $hashed_password]);
            $tenant_admin_id = $pdo->lastInsertId();

            // Log action
            $stmt = $pdo->prepare("INSERT INTO audit_logs 
                                   (user_id, action, entity_type, entity_id, details, ip_address, created_at)
                                   VALUES (?, 'create_tenant_subscription', 'tenant', ?, ?, ?, NOW())");
            $details = json_encode([
                'tenant_name' => $tenant_name,
                'plan' => $plan,
                'billing_cycle' => $billing_cycle,
                'sales_agent_id' => $agent_id,
                'created_by_agent' => $agent['name']
            ]);
            $stmt->execute([$_SESSION['user_id'], $tenant_id, $details, $_SERVER['REMOTE_ADDR']]);

            $pdo->commit();

            // Send notification email to super admin with credentials
            require_once '../includes/functions.php';
            sendNewTenantNotificationToAdmin($agent['name'], $agent['email'], $tenant_name, $tenant_email, 
                                            $plan, $billing_cycle, $contact_person, $default_password);

            // Success message (password sent via email only, never displayed)
            $success = "✓ Tenant created successfully!\n";
            $success .= "Tenant: " . htmlspecialchars($tenant_name) . "\n";
            $success .= "Plan: " . ucfirst($plan) . "\n";
            $success .= "Login Email: " . htmlspecialchars($tenant_email) . "\n";
            $success .= "Note: Temporary login credentials have been sent to the tenant's email.";

            error_log("TENANT_CREATED: Sales Agent {$agent_id} ({$agent['name']}) created tenant {$tenant_id} ({$tenant_name}) with plan {$plan}");
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error creating tenant: " . $e->getMessage();
            error_log("Error creating tenant: " . $e->getMessage());
        }
    }
    }
}

// Get available plans
$stmt = $pdo->prepare("SELECT id, name, price, description FROM plans WHERE status = 'active' ORDER BY price ASC");
$stmt->execute();
$plans = $stmt->fetchAll();
?>

<?php include 'includes/header_sales_agent.php'; ?>

<style>
  /* ── CSS Variables ── */
  :root {
    --brand:        #2563EB;
    --brand-light:  #EFF6FF;
    --brand-dark:   #1D4ED8;
    --success:      #16A34A;
    --success-light:#F0FDF4;
    --warning:      #D97706;
    --warning-light:#FFFBEB;
    --info:         #0891B2;
    --info-light:   #ECFEFF;
    --purple:       #7C3AED;
    --purple-light: #F5F3FF;
    --surface:      #FFFFFF;
    --bg:           #F8FAFC;
    --border:       #E2E8F0;
    --text-primary: #0F172A;
    --text-secondary:#64748B;
    --text-muted:   #94A3B8;
    --radius:       12px;
    --radius-sm:    8px;
    --shadow-sm:    0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow:       0 4px 6px -1px rgba(0,0,0,.07), 0 2px 4px -1px rgba(0,0,0,.04);
    --shadow-md:    0 10px 15px -3px rgba(0,0,0,.08), 0 4px 6px -2px rgba(0,0,0,.04);
  }

  /* ── Layout ── */
  .sa-dashboard { background: var(--bg); padding: 24px; }

  /* ── Section label ── */
  .section-label {
    font-size: .7rem; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase; color: var(--text-muted);
    margin-bottom: 12px;
  }

  /* ── Card SAAS ── */
  .card-saas {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius); box-shadow: var(--shadow-sm);
    overflow: hidden;
  }
  .card-saas-header {
    padding: 16px 20px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
  }
  .card-saas-header h6 {
    margin: 0; font-size: .88rem; font-weight: 700; color: var(--text-primary);
  }
  .card-saas-body { padding: 20px; }

  /* Form styling */
  .form-group {
    margin-bottom: 16px;
  }
  .form-group label {
    display: block; font-size: .85rem; font-weight: 600; color: var(--text-primary);
    margin-bottom: 6px;
  }
  .form-group input, .form-group textarea, .form-group select {
    width: 100%; padding: 10px 12px; border: 1px solid var(--border);
    border-radius: var(--radius-sm); font-size: .85rem; color: var(--text-primary);
    font-family: inherit;
  }
  .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  .form-group small {
    display: block; margin-top: 4px; font-size: .75rem; color: var(--text-muted);
  }

  .form-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
  }
  @media (max-width: 576px) { .form-row { grid-template-columns: 1fr; } }

  .btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: var(--radius-sm); font-weight: 600;
    border: none; cursor: pointer; text-decoration: none; font-size: .85rem;
    transition: all .15s;
  }
  .btn-primary {
    background: var(--brand); color: white; width: 100%;
  }
  .btn-primary:hover {
    background: var(--brand-dark); transform: translateY(-1px); box-shadow: var(--shadow);
  }
  .btn-outline {
    background: var(--surface); color: var(--text-primary); border: 1px solid var(--border); width: 100%;
  }
  .btn-outline:hover {
    background: var(--bg); border-color: var(--brand); color: var(--brand);
  }

  .alert {
    padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 16px;
    font-size: .85rem;
  }
  .alert-success {
    background: var(--success-light); color: var(--success); border: 1px solid var(--success);
  }
  .alert-danger {
    background: #FEE2E2; color: #DC2626; border: 1px solid #DC2626;
  }

  /* Info cards */
  .info-card {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 16px;
    text-align: center;
  }
  .info-card-icon {
    font-size: 2rem; margin-bottom: 8px;
  }
  .info-card h6 {
    margin: 0 0 4px; font-size: .85rem; font-weight: 600; color: var(--text-primary);
  }
  .info-card p {
    margin: 0; font-size: .75rem; color: var(--text-secondary);
  }

  .info-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
  }
  @media (max-width: 768px) { .info-grid { grid-template-columns: 1fr; } }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">

        <!-- Breadcrumb -->
        <div class="page-header">
          <div class="page-block">
            <div class="row align-items-center">
              <div class="col-md-12">
                <div class="page-header-title">
                  <h5 class="m-b-10">Create New Tenant & Subscription</h5>
                </div>
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                  <li class="breadcrumb-item"><a href="tenants.php">Tenants</a></li>
                  <li class="breadcrumb-item"><a href="#!">Create New</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="main-body">
          <div class="page-wrapper sa-dashboard">

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
              ✗ <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
              <strong>✓ Success!</strong><br>
              <?= nl2br(htmlspecialchars($success)) ?>
              <br><br>
              <a href="tenants.php" class="btn btn-primary" style="width:auto;">View My Tenants</a>
            </div>
            <?php endif; ?>

            <!-- ── Form Section ── -->
            <p class="section-label">New Tenant Information</p>
            <div class="card-saas" style="margin-bottom: 24px;">
              <div class="card-saas-header">
                <h6>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18" style="margin-right:6px;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                  Register New Travel Agency
                </h6>
              </div>
              <div class="card-saas-body">
                <form method="POST" action="create_tenant_subscription.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                  <!-- Tenant Info Section -->
                  <div style="margin-bottom: 24px;">
                    <h6 style="font-size:.85rem;font-weight:700;color:var(--text-primary);margin-bottom:12px;">Agency Information</h6>
                    
                    <div class="form-row">
                      <div class="form-group">
                        <label for="tenant_name">Agency Name *</label>
                        <input type="text" id="tenant_name" name="tenant_name" placeholder="e.g., XYZ Travel Agency" required>
                      </div>
                      <div class="form-group">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person" placeholder="Manager name (optional)">
                      </div>
                    </div>

                    <div class="form-row">
                      <div class="form-group">
                        <label for="tenant_email">Email Address *</label>
                        <input type="email" id="tenant_email" name="tenant_email" placeholder="agency@example.com" required>
                        <small>This will be the login email</small>
                      </div>
                      <div class="form-group">
                        <label for="tenant_phone">Phone Number</label>
                        <input type="tel" id="tenant_phone" name="tenant_phone" placeholder="+1234567890">
                      </div>
                    </div>

                    <div class="form-group">
                      <label for="tenant_address">Address</label>
                      <textarea id="tenant_address" name="tenant_address" rows="2" placeholder="Agency address (optional)"></textarea>
                    </div>
                  </div>

                  <hr style="border:none;border-top:1px solid var(--border);margin:24px 0;">

                  <!-- Subscription Info Section -->
                  <div style="margin-bottom: 24px;">
                    <h6 style="font-size:.85rem;font-weight:700;color:var(--text-primary);margin-bottom:12px;">Subscription Details</h6>
                    
                    <div class="form-row">
                      <div class="form-group">
                        <label for="plan">Select Plan *</label>
                        <select id="plan" name="plan" required>
                          <option value="">-- Select a Plan --</option>
                          <?php foreach ($plans as $p): ?>
                          <option value="<?= $p['name'] ?>">
                            <?= ucfirst($p['name']) ?> - $<?= number_format($p['price'], 2) ?>/month
                          </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group">
                        <label for="billing_cycle">Billing Cycle *</label>
                        <select id="billing_cycle" name="billing_cycle" required>
                          <option value="monthly">Monthly</option>
                          <option value="annual">Annual (10% discount)</option>
                        </select>
                      </div>
                    </div>

                    <div class="form-group">
                      <label for="start_date">Subscription Start Date *</label>
                      <input type="date" id="start_date" name="start_date" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                      <label for="notes">Notes (Optional)</label>
                      <textarea id="notes" name="notes" rows="3" placeholder="Any special notes about this tenant..."></textarea>
                    </div>
                  </div>

                  <!-- Actions -->
                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <button type="submit" class="btn btn-primary">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                      Create Tenant
                    </button>
                    <a href="tenants.php" class="btn btn-outline">Cancel</a>
                  </div>
                </form>
              </div>
            </div>

            <!-- ── Info Cards ── -->
            <p class="section-label">What Happens Next</p>
            <div class="info-grid">
              <div class="info-card">
                <div class="info-card-icon">⚡</div>
                <h6>Instant Activation</h6>
                <p>Tenant goes live immediately</p>
              </div>
              <div class="info-card">
                <div class="info-card-icon">🔔</div>
                <h6>Instant Notification</h6>
                <p>Admin is notified automatically</p>
              </div>
              <div class="info-card">
                <div class="info-card-icon">📈</div>
                <h6>Track Commission</h6>
                <p>You earn commission automatically</p>
              </div>
            </div>

          </div><!-- /.page-wrapper -->
        </div><!-- /.main-body -->
      </div>
    </div>
  </div>
</div>
<!-- [ Main Content ] end -->

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
