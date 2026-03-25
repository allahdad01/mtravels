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
$error    = '';
$success  = '';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        $tenant_name    = trim($_POST['tenant_name']    ?? '');
        $tenant_email   = trim($_POST['tenant_email']   ?? '');
        $tenant_phone   = trim($_POST['tenant_phone']   ?? '');
        $tenant_address = trim($_POST['tenant_address'] ?? '');
        $contact_person = trim($_POST['contact_person'] ?? '');
        $plan           = $_POST['plan']          ?? 'basic';
        $billing_cycle  = $_POST['billing_cycle'] ?? 'monthly';
        $start_date     = $_POST['start_date']    ?? date('Y-m-d');
        $notes          = substr(trim($_POST['notes'] ?? ''), 0, 500);

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
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE name = ?");
            $stmt->execute([$tenant_name]);
            if ($stmt->fetch()['count'] > 0) {
                $error = "A tenant with this name already exists. Please use a different name.";
            }
        }

        if (empty($error)) {
            try {
                $pdo->beginTransaction();

                $identifier          = strtolower(preg_replace('/[^a-z0-9-]/', '-', $tenant_name));
                $identifier          = substr($identifier, 0, 100);
                $counter             = 1;
                $original_identifier = $identifier;
                while (true) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tenants WHERE identifier = ?");
                    $stmt->execute([$identifier]);
                    if ($stmt->fetch()['count'] == 0) break;
                    $identifier = $original_identifier . '-' . $counter;
                    $counter++;
                }

                $stmt = $pdo->prepare("INSERT INTO tenants (name, identifier, status, plan, billing_email, created_at, updated_at) VALUES (?, ?, 'active', ?, ?, NOW(), NOW())");
                $stmt->execute([$tenant_name, $identifier, $plan, $tenant_email]);
                $tenant_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("SELECT price, currency FROM plans WHERE name = ? LIMIT 1");
                $stmt->execute([$plan]);
                $plan_data  = $stmt->fetch();
                $plan_price = $plan_data['price']    ?? 0;
                $currency   = $plan_data['currency'] ?? 'USD';

                $stmt = $pdo->prepare("INSERT INTO subscriptions (tenant_id, plan, billing_cycle, start_date, status, amount, currency, sales_agent_id, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$tenant_id, $plan, $billing_cycle, $start_date, $plan_price, $currency, $agent_id]);

                $stmt = $pdo->prepare("INSERT INTO sales_agent_tenants (sales_agent_id, tenant_id, subscription_start_date, status, created_at, updated_at) VALUES (?, ?, ?, 'active', NOW(), NOW())");
                $stmt->execute([$agent_id, $tenant_id, $start_date]);

                $default_password = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 10);
                $hashed_password  = password_hash($default_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, 'tenant_super_admin', NOW(), NOW())");
                $stmt->execute([$tenant_id, $contact_person ?: $tenant_name, $tenant_email, $hashed_password]);

                $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, 'create_tenant_subscription', 'tenant', ?, ?, ?, NOW())");
                $details = json_encode(['tenant_name' => $tenant_name, 'plan' => $plan, 'billing_cycle' => $billing_cycle, 'sales_agent_id' => $agent_id, 'created_by_agent' => $agent['name']]);
                $stmt->execute([$_SESSION['user_id'], $tenant_id, $details, $_SERVER['REMOTE_ADDR']]);

                $pdo->commit();

                require_once '../includes/functions.php';
                sendNewTenantNotificationToAdmin($agent['name'], $agent['email'], $tenant_name, $tenant_email, $plan, $billing_cycle, $contact_person, $default_password);

                $success = "Tenant <strong>" . htmlspecialchars($tenant_name) . "</strong> created on the <strong>" . ucfirst($plan) . "</strong> plan. Login credentials have been sent to <strong>" . htmlspecialchars($tenant_email) . "</strong>.";
                error_log("TENANT_CREATED: Agent {$agent_id} ({$agent['name']}) created tenant {$tenant_id} ({$tenant_name}) plan {$plan}");

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error creating tenant: " . $e->getMessage();
                error_log("Error creating tenant: " . $e->getMessage());
            }
        }
    }
}

// Get available plans
$stmt = $pdo->prepare("SELECT id, name, price, currency, description FROM plans WHERE status = 'active' ORDER BY price ASC");
$stmt->execute();
$plans = $stmt->fetchAll();
?>
<?php include 'includes/header_sales_agent.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Sora:wght@400;600;700&display=swap" rel="stylesheet">

<style>
/* ─────────────────────────────────────────
   CSS VARIABLES  (matches dashboard.php)
───────────────────────────────────────── */
:root {
  --teal:       #2ed8b6;
  --blue:       #4099ff;
  --grad:       linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
  --grad-soft:  linear-gradient(135deg, rgba(64,153,255,0.07) 0%, rgba(46,216,182,0.07) 100%);

  --bg:         #F0F4F8;
  --bg-2:       #E8EDF3;
  --surface:    #FFFFFF;
  --surface-2:  #F7F9FC;

  --teal-dim:   rgba(46,216,182,0.12);
  --blue-dim:   rgba(64,153,255,0.12);
  --green:      #10B981;
  --green-dim:  rgba(16,185,129,0.08);
  --red-dim:    rgba(239,68,68,0.08);

  --border:     rgba(0,0,0,0.07);
  --border-2:   rgba(0,0,0,0.04);

  --text-1:     #0F172A;
  --text-2:     #475569;
  --text-3:     #94A3B8;

  --radius:     14px;
  --radius-sm:  9px;
  --shadow-sm:  0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  --shadow:     0 4px 16px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.04);
  --shadow-md:  0 8px 30px rgba(64,153,255,0.10), 0 2px 8px rgba(0,0,0,0.05);
}

/* ─────────────────────────────────────────
   BASE
───────────────────────────────────────── */
.sa-page {
  background: var(--bg);
  padding: 28px 24px 48px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  color: var(--text-1);
  min-height: 100vh;
}

/* ─────────────────────────────────────────
   UTILITIES
───────────────────────────────────────── */
.grad-text {
  background: var(--grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* ─────────────────────────────────────────
   PAGE TOPBAR
───────────────────────────────────────── */
.page-topbar {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 28px;
  gap: 16px;
  flex-wrap: wrap;
}
.page-eyebrow {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  background: var(--grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 5px;
}
.page-title {
  font-family: 'Sora', sans-serif;
  font-size: 22px;
  font-weight: 700;
  color: var(--text-1);
}
.page-sub { font-size: 12px; color: var(--text-3); margin-top: 3px; }

.btn-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-2);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 9px 16px;
  text-decoration: none;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
  white-space: nowrap;
}
.btn-back:hover { background: var(--bg-2); color: var(--text-1); border-color: rgba(0,0,0,0.12); text-decoration: none; }
.btn-back svg { width: 13px; height: 13px; }

/* ─────────────────────────────────────────
   ALERTS
───────────────────────────────────────── */
.alert {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px 18px;
  border-radius: var(--radius);
  margin-bottom: 24px;
  font-size: 13px;
  font-weight: 500;
  line-height: 1.6;
}
.alert-icon { flex-shrink: 0; width: 20px; height: 20px; margin-top: 1px; }
.alert-success { background: var(--green-dim); border: 1px solid rgba(16,185,129,0.20); color: #065f46; }
.alert-danger  { background: var(--red-dim);   border: 1px solid rgba(239,68,68,0.20);  color: #7f1d1d; }
.alert-title   { font-weight: 700; margin-bottom: 3px; }
.alert-actions { margin-top: 12px; }
.btn-view-tenants {
  display: inline-flex; align-items: center; gap: 7px;
  font-size: 12px; font-weight: 700;
  background: var(--grad); color: #fff;
  border: none; border-radius: var(--radius-sm);
  padding: 9px 18px; text-decoration: none;
  transition: opacity 0.15s;
}
.btn-view-tenants:hover { opacity: 0.88; color: #fff; text-decoration: none; }

/* ─────────────────────────────────────────
   LAYOUT GRID
───────────────────────────────────────── */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 20px;
  align-items: start;
}
@media (max-width: 900px) { .form-grid { grid-template-columns: 1fr; } }

/* ─────────────────────────────────────────
   PANELS
───────────────────────────────────────── */
.panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  margin-bottom: 16px;
}
.panel:last-child { margin-bottom: 0; }

.panel-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 15px 20px 13px;
  border-bottom: 1px solid var(--border-2);
  background: var(--surface-2);
}
.panel-head-icon {
  width: 30px; height: 30px;
  border-radius: 8px;
  background: var(--grad-soft);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.panel-head-icon svg { width: 14px; height: 14px; }
.panel-title    { font-size: 13px; font-weight: 700; color: var(--text-1); }
.panel-subtitle { font-size: 11px; color: var(--text-3); margin-top: 1px; }
.panel-body     { padding: 20px; }

/* ─────────────────────────────────────────
   FORM ELEMENTS
───────────────────────────────────────── */
.form-group { margin-bottom: 14px; }
.form-group:last-child { margin-bottom: 0; }

.form-label {
  display: block;
  font-size: 12px; font-weight: 600;
  color: var(--text-1);
  margin-bottom: 5px;
}
.form-label .req { color: var(--teal); margin-left: 2px; }

.form-control {
  width: 100%;
  height: 38px;
  padding: 0 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px; font-weight: 500;
  color: var(--text-1);
  background: var(--surface);
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  appearance: none;
  -webkit-appearance: none;
}
.form-control:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(64,153,255,0.10);
}
.form-control::placeholder { color: var(--text-3); }

textarea.form-control {
  height: auto;
  padding: 10px 12px;
  resize: vertical;
  line-height: 1.6;
}
select.form-control {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394A3B8' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  background-size: 14px;
  padding-right: 34px;
  cursor: pointer;
}
.form-hint {
  font-size: 10px; color: var(--text-3);
  margin-top: 4px; line-height: 1.4;
}
.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
@media (max-width: 576px) { .form-row-2 { grid-template-columns: 1fr; } }
.form-divider {
  border: none;
  border-top: 1px solid var(--border-2);
  margin: 18px 0;
}

/* ─────────────────────────────────────────
   PLAN SELECTOR CARDS
───────────────────────────────────────── */
.plan-cards { display: flex; flex-direction: column; gap: 8px; }

.plan-card-label {
  display: flex;
  align-items: center;
  gap: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  padding: 12px 14px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
  position: relative;
}
.plan-card-label:hover { border-color: rgba(64,153,255,0.30); background: var(--grad-soft); }
.plan-card-label input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
.plan-card-label:has(input:checked)  { border-color: var(--blue); background: var(--blue-dim); }

.plan-radio-dot {
  width: 16px; height: 16px;
  border-radius: 50%;
  border: 1.5px solid var(--border);
  flex-shrink: 0;
  transition: background 0.15s, border-color 0.15s;
  background: var(--surface);
  position: relative;
}
.plan-card-label:has(input:checked) .plan-radio-dot {
  background: var(--grad);
  border-color: transparent;
}
.plan-card-info { flex: 1; min-width: 0; }
.plan-card-name { font-size: 12px; font-weight: 700; color: var(--text-1); }
.plan-card-desc { font-size: 10px; color: var(--text-3); margin-top: 1px; }
.plan-card-price {
  font-family: 'Sora', sans-serif;
  font-size: 14px; font-weight: 700;
  color: var(--blue);
  white-space: nowrap;
  flex-shrink: 0;
}

/* ─────────────────────────────────────────
   BUTTONS
───────────────────────────────────────── */
.btn-submit {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  height: 44px;
  background: var(--grad);
  color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 13px; font-weight: 700;
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(64,153,255,0.25);
  transition: opacity 0.15s, transform 0.15s;
  margin-bottom: 10px;
}
.btn-submit:hover { opacity: 0.88; transform: translateY(-1px); }
.btn-submit svg { width: 15px; height: 15px; }

.btn-cancel {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  width: 100%;
  height: 38px;
  background: var(--surface);
  color: var(--text-2);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 12px; font-weight: 600;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  cursor: pointer;
  text-decoration: none;
  transition: background 0.15s, color 0.15s;
}
.btn-cancel:hover { background: var(--bg-2); color: var(--text-1); text-decoration: none; }

/* ─────────────────────────────────────────
   INFO TILES
───────────────────────────────────────── */
.info-tile {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 13px 0;
  border-bottom: 1px solid var(--border-2);
}
.info-tile:last-child { border-bottom: none; padding-bottom: 0; }
.info-tile:first-child { padding-top: 0; }
.info-tile-icon {
  width: 34px; height: 34px;
  border-radius: 9px;
  background: var(--grad-soft);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.info-tile-icon svg { width: 15px; height: 15px; }
.info-tile-title { font-size: 12px; font-weight: 700; color: var(--text-1); margin-bottom: 2px; }
.info-tile-sub   { font-size: 11px; color: var(--text-3); line-height: 1.45; }

/* agent chip */
.agent-chip {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 18px;
}
.agent-avatar {
  width: 34px; height: 34px;
  border-radius: 50%;
  background: var(--grad-soft);
  border: 1px solid rgba(64,153,255,0.15);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 800;
  color: var(--blue);
  flex-shrink: 0;
}
.agent-name  { font-size: 12px; font-weight: 700; color: var(--text-1); }
.agent-email { font-size: 10px; color: var(--text-3); }
.agent-label { font-size: 9px; font-weight: 700; letter-spacing: .10em; text-transform: uppercase; color: var(--text-3); margin-bottom: 6px; }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">

        <div class="main-body">
          <div class="page-wrapper sa-page">

            <!-- PAGE TOPBAR -->
            <div class="page-topbar">
              <div>
                <div class="page-eyebrow">Tenant Management</div>
                <div class="page-title">Register New Travel Agency</div>
                <div class="page-sub">Add a new tenant and configure their subscription.</div>
              </div>
              <a href="tenants.php" class="btn-back">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back to Tenants
              </a>
            </div>

            <!-- ALERTS -->
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
              <svg class="alert-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <div>
                <div class="alert-title">Something went wrong</div>
                <?= htmlspecialchars($error) ?>
              </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success">
              <svg class="alert-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <div>
                <div class="alert-title">Tenant Created Successfully!</div>
                <?= $success ?>
                <div class="alert-actions">
                  <a href="tenants.php" class="btn-view-tenants">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m4-4a4 4 0 100-8 4 4 0 000 8z"/></svg>
                    View My Tenants
                  </a>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <!-- FORM + SIDEBAR -->
            <form method="POST" action="create_tenant_subscription.php">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

              <div class="form-grid">

                <!-- ── LEFT: MAIN FORM ── -->
                <div>

                  <!-- Agency Information -->
                  <div class="panel">
                    <div class="panel-head">
                      <div class="panel-head-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="url(#g-agency)" stroke-width="2">
                          <defs><linearGradient id="g-agency" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4099ff"/><stop offset="100%" stop-color="#2ed8b6"/></linearGradient></defs>
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                      </div>
                      <div>
                        <div class="panel-title">Agency Information</div>
                        <div class="panel-subtitle">Basic details about the travel agency</div>
                      </div>
                    </div>
                    <div class="panel-body">
                      <div class="form-row-2">
                        <div class="form-group">
                          <label class="form-label">Agency Name <span class="req">*</span></label>
                          <input type="text" name="tenant_name" class="form-control"
                                 placeholder="e.g., Sunrise Travel Co."
                                 value="<?= htmlspecialchars($_POST['tenant_name'] ?? '') ?>"
                                 required maxlength="255">
                        </div>
                        <div class="form-group">
                          <label class="form-label">Contact Person</label>
                          <input type="text" name="contact_person" class="form-control"
                                 placeholder="Manager or owner name"
                                 value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">
                        </div>
                      </div>
                      <div class="form-row-2">
                        <div class="form-group">
                          <label class="form-label">Email Address <span class="req">*</span></label>
                          <input type="email" name="tenant_email" class="form-control"
                                 placeholder="agency@example.com"
                                 value="<?= htmlspecialchars($_POST['tenant_email'] ?? '') ?>"
                                 required maxlength="255">
                          <div class="form-hint">This will be their login email.</div>
                        </div>
                        <div class="form-group">
                          <label class="form-label">Phone Number</label>
                          <input type="tel" name="tenant_phone" class="form-control"
                                 placeholder="+1 234 567 8900"
                                 value="<?= htmlspecialchars($_POST['tenant_phone'] ?? '') ?>"
                                 maxlength="20">
                        </div>
                      </div>
                      <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="tenant_address" class="form-control" rows="2"
                                  placeholder="Agency street address (optional)"><?= htmlspecialchars($_POST['tenant_address'] ?? '') ?></textarea>
                      </div>
                    </div>
                  </div>

                  <!-- Subscription Details -->
                  <div class="panel">
                    <div class="panel-head">
                      <div class="panel-head-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="url(#g-sub)" stroke-width="2">
                          <defs><linearGradient id="g-sub" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4099ff"/><stop offset="100%" stop-color="#2ed8b6"/></linearGradient></defs>
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                      </div>
                      <div>
                        <div class="panel-title">Subscription Details</div>
                        <div class="panel-subtitle">Plan and billing configuration</div>
                      </div>
                    </div>
                    <div class="panel-body">

                      <!-- Plan selector -->
                      <div class="form-group">
                        <label class="form-label">Select Plan <span class="req">*</span></label>
                        <div class="plan-cards">
                          <?php foreach ($plans as $p): ?>
                            <label class="plan-card-label">
                              <input type="radio" name="plan" value="<?= htmlspecialchars($p['name']) ?>"
                                     <?= (($_POST['plan'] ?? '') === $p['name']) ? 'checked' : '' ?> required>
                              <span class="plan-radio-dot"></span>
                              <span class="plan-card-info">
                                <span class="plan-card-name"><?= ucfirst($p['name']) ?></span>
                                <?php if (!empty($p['description'])): ?>
                                  <span class="plan-card-desc"><?= htmlspecialchars($p['description']) ?></span>
                                <?php endif; ?>
                              </span>
                              <span class="plan-card-price">
                                <?php 
                                  $currencies = [
                                      'USD' => '$',
                                      'AFN' => '؋',
                                      'AFS' => '؋',
                                      'EUR' => '€',
                                      'GBP' => '£',
                                      'INR' => '₹',
                                      'JPY' => '¥',
                                      'CNY' => '¥',
                                      'AUD' => 'A$',
                                      'CAD' => 'C$',
                                      'CHF' => 'CHF',
                                      'SEK' => 'kr',
                                      'NZD' => 'NZ$'
                                  ];
                                  $curr = $p['currency'] ?? 'USD';
                                  echo isset($currencies[$curr]) ? htmlspecialchars($currencies[$curr]) : htmlspecialchars($curr);
                                ?><?= number_format($p['price'], 2) ?><span style="font-size:10px;font-weight:500;color:var(--text-3);">/mo</span>
                              </span>
                            </label>
                          <?php endforeach; ?>
                        </div>
                      </div>

                      <hr class="form-divider">

                      <div class="form-row-2">
                        <div class="form-group">
                          <label class="form-label">Billing Cycle <span class="req">*</span></label>
                          <select name="billing_cycle" class="form-control" required>
                            <option value="monthly" <?= (($_POST['billing_cycle'] ?? 'monthly') === 'monthly') ? 'selected' : '' ?>>Monthly</option>
                            <option value="annual"  <?= (($_POST['billing_cycle'] ?? '') === 'annual')  ? 'selected' : '' ?>>Annual (10% off)</option>
                          </select>
                        </div>
                        <div class="form-group">
                          <label class="form-label">Start Date <span class="req">*</span></label>
                          <input type="date" name="start_date" class="form-control"
                                 value="<?= htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                      </div>

                      <div class="form-group">
                        <label class="form-label">Notes <span style="font-weight:500;color:var(--text-3);">(optional)</span></label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Any special notes about this tenant…"
                                  maxlength="500"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        <div class="form-hint">Max 500 characters.</div>
                      </div>
                    </div>
                  </div>

                </div><!-- /.left -->

                <!-- ── RIGHT: SIDEBAR ── -->
                <div>

                  <!-- Submit -->
                  <div class="panel">
                    <div class="panel-head">
                      <div class="panel-title">Ready to create?</div>
                    </div>
                    <div class="panel-body">
                      <button type="submit" class="btn-submit">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Create Tenant &amp; Subscription
                      </button>
                      <a href="tenants.php" class="btn-cancel">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        Cancel
                      </a>
                    </div>
                  </div>

                  <!-- What happens next -->
                  <div class="panel">
                    <div class="panel-head">
                      <div class="panel-title">What happens next?</div>
                    </div>
                    <div class="panel-body">
                      <div class="info-tile">
                        <div class="info-tile-icon">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="url(#g-bolt)" stroke-width="2">
                            <defs><linearGradient id="g-bolt" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4099ff"/><stop offset="100%" stop-color="#2ed8b6"/></linearGradient></defs>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                          </svg>
                        </div>
                        <div>
                          <div class="info-tile-title">Instant Activation</div>
                          <div class="info-tile-sub">Account goes live immediately after creation.</div>
                        </div>
                      </div>
                      <div class="info-tile">
                        <div class="info-tile-icon">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="url(#g-mail)" stroke-width="2">
                            <defs><linearGradient id="g-mail" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4099ff"/><stop offset="100%" stop-color="#2ed8b6"/></linearGradient></defs>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                          </svg>
                        </div>
                        <div>
                          <div class="info-tile-title">Credentials Emailed</div>
                          <div class="info-tile-sub">Temporary password sent to the tenant automatically.</div>
                        </div>
                      </div>
                      <div class="info-tile">
                        <div class="info-tile-icon">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="url(#g-chart)" stroke-width="2">
                            <defs><linearGradient id="g-chart" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#4099ff"/><stop offset="100%" stop-color="#2ed8b6"/></linearGradient></defs>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                          </svg>
                        </div>
                        <div>
                          <div class="info-tile-title">Commission Tracked</div>
                          <div class="info-tile-sub">Your commission is calculated automatically.</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Registering as agent -->
                  <div class="panel">
                    <div class="panel-body" style="padding:14px 18px;">
                      <div class="agent-label">Registering as</div>
                      <div class="agent-chip" style="padding:0;">
                        <div class="agent-avatar"><?= strtoupper(substr($agent['name'], 0, 2)) ?></div>
                        <div>
                          <div class="agent-name"><?= htmlspecialchars($agent['name']) ?></div>
                          <div class="agent-email"><?= htmlspecialchars($agent['email']) ?></div>
                        </div>
                      </div>
                    </div>
                  </div>

                </div><!-- /.sidebar -->
              </div><!-- /.form-grid -->
            </form>

          </div><!-- /.sa-page -->
        </div><!-- /.main-body -->
      </div>
    </div>
  </div>
</div>
<!-- [ Main Content ] end -->

<script>
  window.csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
</script>
<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>