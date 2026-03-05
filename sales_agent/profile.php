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
$stmt = $pdo->prepare("SELECT sa.*, u.email as user_email 
                       FROM sales_agents sa
                       JOIN users u ON sa.user_id = u.id
                       WHERE sa.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$agent = $stmt->fetch();

if (!$agent) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Check for rate limiting (max 5 attempts per 15 minutes)
        $rate_limit_key = 'pwd_change_attempt_' . $_SESSION['user_id'];
        $attempts = $_SESSION[$rate_limit_key] ?? [];
        $now = time();
        $attempts = array_filter($attempts, fn($time) => $now - $time < 900); // 15 minutes
        
        if (count($attempts) >= 5) {
            $error = "Too many password change attempts. Please try again in 15 minutes.";
            error_log("Password change rate limit exceeded for user {$_SESSION['user_id']}");
        } elseif (empty($current_password) || empty($new_password)) {
            $error = "Please provide all required fields.";
        } elseif (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters.";
        } elseif (strlen($new_password) > 128) {
            $error = "Password must be less than 128 characters.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Get current password hash
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($current_password, $user['password'])) {
                // Generic error message to prevent user enumeration
                $error = "Authentication failed. Please try again.";
                
                // Log failed attempt
                $attempts[] = $now;
                $_SESSION[$rate_limit_key] = $attempts;
                error_log("Failed password change attempt for user {$_SESSION['user_id']} from IP {$_SERVER['REMOTE_ADDR']}");
            } else {
                // Password is correct, update it
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed, $_SESSION['user_id']]);
                
                // Log successful password change
                error_log("Password changed for sales agent {$_SESSION['user_id']} from IP {$_SERVER['REMOTE_ADDR']}");
                
                // Clear rate limit attempts on successful change
                unset($_SESSION[$rate_limit_key]);
                
                $message = "Password changed successfully.";
            }
        }
    }
}
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

  /* ── Info Cards Grid ── */
  .info-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 16px; margin-bottom: 24px;
  }
  @media (max-width: 768px) { .info-grid { grid-template-columns: 1fr; } }

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

  /* Profile fields as chips */
  .field-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
  }
  .field-chip {
    background: var(--bg); border: 1px solid var(--border);
    border-radius: var(--radius-sm); padding: 10px 14px;
  }
  .field-chip .fc-label {
    font-size: .7rem; font-weight: 600; text-transform: uppercase;
    letter-spacing: .06em; color: var(--text-muted); margin-bottom: 3px;
  }
  .field-chip .fc-value {
    font-size: .88rem; font-weight: 600; color: var(--text-primary);
  }
  .badge-active {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--success-light); color: var(--success);
    font-size: .75rem; font-weight: 600; padding: 3px 10px; border-radius: 20px;
  }
  .badge-active span { width: 6px; height: 6px; background: var(--success); border-radius: 50%; }
  .badge-inactive {
    display: inline-flex; align-items: center; gap: 5px;
    background: #F1F5F9; color: var(--text-secondary);
    font-size: .75rem; font-weight: 600; padding: 3px 10px; border-radius: 20px;
  }

  /* Form styling */
  .form-group {
    margin-bottom: 16px;
  }
  .form-group label {
    display: block; font-size: .85rem; font-weight: 600; color: var(--text-primary);
    margin-bottom: 6px;
  }
  .form-group input, .form-group textarea {
    width: 100%; padding: 10px 12px; border: 1px solid var(--border);
    border-radius: var(--radius-sm); font-size: .85rem; color: var(--text-primary);
    font-family: inherit;
  }
  .form-group input:focus, .form-group textarea:focus {
    outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  }
  .form-group small {
    display: block; margin-top: 4px; font-size: .75rem; color: var(--text-muted);
  }

  .btn {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 20px; border-radius: var(--radius-sm); font-weight: 600;
    border: none; cursor: pointer; text-decoration: none; font-size: .85rem;
    transition: all .15s;
  }
  .btn-primary {
    background: var(--brand); color: white;
  }
  .btn-primary:hover {
    background: var(--brand-dark); transform: translateY(-1px); box-shadow: var(--shadow);
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

  .info-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 0; border-bottom: 1px solid var(--border);
  }
  .info-row:last-child { border-bottom: none; }
  .info-label {
    font-size: .85rem; font-weight: 600; color: var(--text-secondary);
  }
  .info-value {
    font-size: .88rem; font-weight: 600; color: var(--text-primary);
  }
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
                  <h5 class="m-b-10">My Profile</h5>
                </div>
                <ul class="breadcrumb">
                  <li class="breadcrumb-item"><a href="dashboard.php"><i class="feather icon-home"></i></a></li>
                  <li class="breadcrumb-item"><a href="#!">Profile</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="main-body">
          <div class="page-wrapper sa-dashboard">

            <?php if (!empty($message)): ?>
            <div class="alert alert-success">
              ✓ <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
              ✗ <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- ── Profile Information ── -->
            <p class="section-label">Profile Information</p>
            <div class="info-grid">

              <!-- Profile Card -->
              <div class="card-saas">
                <div class="card-saas-header">
                  <h6>Agent Details</h6>
                </div>
                <div class="card-saas-body">
                  <div class="info-row">
                    <span class="info-label">Name</span>
                    <span class="info-value"><?= htmlspecialchars($agent['name']) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value" style="font-size:.8rem;"><?= htmlspecialchars($agent['email']) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?= htmlspecialchars($agent['phone'] ?? 'Not provided') ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Province</span>
                    <span class="info-value"><?= htmlspecialchars($agent['province']) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Region</span>
                    <span class="info-value"><?= htmlspecialchars($agent['region'] ?? 'Not specified') ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Status</span>
                    <span>
                      <?php if ($agent['status'] === 'active'): ?>
                        <span class="badge-active"><span></span> Active</span>
                      <?php else: ?>
                        <span class="badge-inactive"><?= ucfirst($agent['status']) ?></span>
                      <?php endif; ?>
                    </span>
                  </div>
                  <p style="margin-top: 16px; font-size:.75rem; color:var(--text-muted);">
                    <i class="feather icon-info" style="width:14px;height:14px;"></i> Contact admin to update profile information
                  </p>
                </div>
              </div>

              <!-- Compensation Card -->
              <div class="card-saas">
                <div class="card-saas-header">
                  <h6>Compensation Details</h6>
                </div>
                <div class="card-saas-body">
                  <div class="info-row">
                    <span class="info-label">Commission Rate</span>
                    <span class="info-value"><?= $agent['commission_rate'] ?>%</span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Salary Type</span>
                    <span class="info-value"><?= ucfirst($agent['salary_type']) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Base Salary</span>
                    <span class="info-value"><?= $agent['base_salary'] ? '$' . number_format($agent['base_salary'], 2) : 'N/A' ?></span>
                  </div>
                  <p style="margin-top: 16px; font-size:.75rem; color:var(--text-muted);">
                    <i class="feather icon-lock" style="width:14px;height:14px;"></i> These values are set by admin and cannot be changed here
                  </p>
                </div>
              </div>
            </div>

            <!-- ── Change Password ── -->
            <p class="section-label">Account Security</p>
            <div class="info-grid">

              <!-- Password Change -->
              <div class="card-saas">
                <div class="card-saas-header">
                  <h6>Change Password</h6>
                </div>
                <div class="card-saas-body">
                  <form method="POST" action="profile.php">
                    <input type="hidden" name="change_password" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="form-group">
                      <label for="current_password">Current Password *</label>
                      <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                      <label for="new_password">New Password *</label>
                      <input type="password" id="new_password" name="new_password" required minlength="8">
                      <small>Minimum 8 characters</small>
                    </div>

                    <div class="form-group">
                      <label for="confirm_password">Confirm Password *</label>
                      <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>

                    <button type="submit" class="btn btn-primary">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                      Update Password
                    </button>
                  </form>
                </div>
              </div>

              <!-- Account Information -->
              <div class="card-saas">
                <div class="card-saas-header">
                  <h6>Account Information</h6>
                </div>
                <div class="card-saas-body">
                  <div class="info-row">
                    <span class="info-label">User ID</span>
                    <span class="info-value"><?= $agent['user_id'] ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Agent ID</span>
                    <span class="info-value"><?= $agent['id'] ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Role</span>
                    <span class="info-value"><span class="badge-active"><span></span>Sales Agent</span></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?= date('F d, Y', strtotime($agent['created_at'])) ?></span>
                  </div>
                  <div class="info-row">
                    <span class="info-label">Last Updated</span>
                    <span class="info-value"><?= $agent['updated_at'] ? date('F d, Y H:i', strtotime($agent['updated_at'])) : 'Never' ?></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── Security Tips ── -->
            <p class="section-label">Security Tips</p>
            <div class="card-saas">
              <div class="card-saas-header">
                <h6>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="18" height="18" style="margin-right:6px;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  Account Security Recommendations
                </h6>
              </div>
              <div class="card-saas-body">
                <ul style="margin:0;padding-left:20px;font-size:.85rem;color:var(--text-secondary);line-height:1.6;">
                  <li>Use a strong password with uppercase, lowercase, numbers, and special characters</li>
                  <li>Never share your password with anyone</li>
                  <li>Change your password regularly (at least every 90 days)</li>
                  <li>Log out when using shared computers</li>
                  <li>Report any suspicious activity to your admin immediately</li>
                </ul>
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
