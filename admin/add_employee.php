<?php
require_once '../includes/language_helpers.php';
require_once '../includes/db.php';
require_once '../includes/UserAddonManager.php';
require_once '../admin/security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'];
$branch_id = $_SESSION['branch_id'];

// Initialize UserAddonManager
$userAddonManager = new UserAddonManager($pdo, $tenant_id);

// Check user limits
$usageStats = $userAddonManager->getUsageStats();
$canAddUser = $usageStats['can_add_more'];

// Check if user is tenant super admin (can request additional users)
$isTenantSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';

// Get tenant's currency for pricing display
$plan = $usageStats['plan'];
$currency = $plan['currency'] ?? 'USD';

// Get pricing for addon display
$addonPricing = $userAddonManager->getAddonPricing();


// Load input validation helper
require_once '../includes/InputValidator.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = __('invalid_csrf_token');
    } else {
        $name = InputValidator::getString($_POST['name'] ?? '', 255);
        $email = InputValidator::getEmail($_POST['email'] ?? '');
        $phone = InputValidator::getPhone($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = InputValidator::getEnum(
            $_POST['role'] ?? '',
            ['admin', 'staff', 'sales', 'umrah', 'finance', 'operations', 'hotel_manager', 'viewer'],
            ''
        );
        $hire_date = InputValidator::getDate($_POST['hire_date'] ?? '', 'Y-m-d', '');
        $address = InputValidator::getString($_POST['address'] ?? '', 500);

        // Validation
        $errors = [];

        // Check if tenant can add more users
        if (!$canAddUser) {
            $errors[] = __('user_limit_reached') . ' ' . sprintf(__('max_users_allowed'), $usageStats['max_users']);
        }

        if (empty($name)) {
            $errors[] = __('name_required');
        }

        if (empty($email)) {
            $errors[] = __('email_required');
        } elseif (!$email) {
            $errors[] = __('invalid_email_format');
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ? And branch_id = ?");
            $stmt->execute([$email, $tenant_id, $branch_id]);
            if ($stmt->fetch()) {
                $errors[] = __('email_already_exists');
            }
        }

        if (empty($password)) {
            $errors[] = __('password_required');
        } elseif (strlen($password) < 12) {
            // Enforce stronger password requirement (12+ chars)
            $errors[] = __('password_too_short');
        }

        if (empty($role)) {
            $errors[] = __('role_required');
        }

        if (empty($hire_date)) {
            $errors[] = __('hire_date_required');
        }

        if (empty($errors)) {
            try {
                // Start transaction
                $pdo->beginTransaction();

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        name, email, phone, password, role, hire_date, address,
                        tenant_id, created_at, updated_at, branch_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
                ");
                $stmt->execute([$name, $email, $phone, $hashed_password, $role, $hire_date, $address, $tenant_id, $branch_id]);

                $user_id = $pdo->lastInsertId();

                // Log the action
                $logStmt = $pdo->prepare("
                    INSERT INTO activity_log (
                        user_id, action, table_name, record_id,
                        old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id
                    ) VALUES (?, 'add_employee', 'users', ?, ?, ?, ?, ?, NOW(), ?, ?)
                ");

                $new_values = json_encode([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                    'hire_date' => $hire_date,
                    'address' => $address
                ]);

                $logStmt->execute([
                    $_SESSION['user_id'],
                    $user_id,
                    'null',
                    $new_values,
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT'],
                    $tenant_id,
                    $branch_id
                ]);

                $pdo->commit();

                $success = __('employee_added_successfully');
                // Reset form
                $name = $email = $phone = $password = $role = $hire_date = $address = '';

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = __('error_adding_employee') . ': ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

$page_title = __('add_employee');
include '../includes/header.php';
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&display=swap');

/* ─── tokens ─────────────────────────────────────────────────── */
:root {
  --bg:       #f1f5fb;
  --surface:  #ffffff;
  --border:   #e3e9f4;
  --text:     #0d1321;
  --muted:    #5a6482;
  --faint:    #9aa3be;
  --blue:     #4099ff;
  --indigo:   #2ed8b6;
  --cyan:     #00b4d8;
  --green:    #00c896;
  --amber:    #f9a825;
  --rose:     #ff4d6d;
  --violet:   #7c3aed;
  --font:     'Sora', sans-serif;
  --r:        18px;
}

* { font-family: var(--font); box-sizing: border-box; }

/* override pcoded bg */
.pcoded-content,
.pcoded-inner-content { background: var(--bg) !important; }

/* ─── page layout ─────────────────────────────────────────────── */
.ae-page { padding: 24px 28px 40px; }

/* ─── TOP BANNER ─────────────────────────────────────────────── */
.ae-banner {
  position: relative;
  border-radius: 22px;
  margin-bottom: 22px;
  background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
  padding: 30px 36px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-wrap: wrap;
  min-height: 120px;
}

/* decorative circles */
.ae-banner::before,
.ae-banner::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
.ae-banner::before {
  width: 340px; height: 340px;
  background: radial-gradient(circle, rgba(108,92,231,.25) 0%, transparent 70%);
  top: -100px; right: 80px;
}
.ae-banner::after {
  width: 180px; height: 180px;
  background: radial-gradient(circle, rgba(79,110,247,.3) 0%, transparent 70%);
  bottom: -60px; right: 30%;
}

.ae-banner-dot-grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(rgba(255,255,255,.06) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}

.ae-banner-left { position: relative; z-index: 1; }
.ae-banner-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.18);
  color: rgba(255,255,255,.75);
  font-size: .65rem;
  font-weight: 700;
  letter-spacing: .12em;
  text-transform: uppercase;
  padding: 4px 11px;
  border-radius: 20px;
  margin-bottom: 12px;
}
.ae-banner-tag i { font-size: .7rem; }

.ae-banner-h1 {
  font-size: 1.7rem;
  font-weight: 800;
  color: #fff;
  margin: 0 0 4px;
  letter-spacing: -.03em;
  line-height: 1.15;
}
.ae-banner-sub {
  font-size: .78rem;
  color: rgba(255,255,255,.5);
  margin: 0;
  font-weight: 500;
}

/* back button */
.ae-banner-right {
  display: flex;
  align-items: center;
  gap: 12px;
  position: relative;
  z-index: 1;
}
.ae-banner-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 18px;
  background: rgba(255,255,255,.1);
  color: #fff;
  border: 1px solid rgba(255,255,255,.15);
  border-radius: 10px;
  font-size: .83rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s;
  text-decoration: none;
}
.ae-banner-back-btn:hover {
  background: rgba(255,255,255,.17);
  border-color: rgba(255,255,255,.25);
  color: #fff;
}
.ae-banner-back-btn i { font-size: .8rem; }

/* ─── FORM CARD ──────────────────────────────────────────────── */
.ae-form-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 24px rgba(13,19,33,.07);
  margin-bottom: 20px;
}

.ae-form-card-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(180deg, rgba(64,153,255,.04) 0%, transparent 100%);
}

.ae-form-card-header h5 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text);
  font-family: var(--font);
}

.ae-form-card-body {
  padding: 28px;
}

.ae-form-group {
  margin-bottom: 20px;
}

.ae-form-group label {
  display: block;
  font-size: .85rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 8px;
  font-family: var(--font);
}

.ae-form-group input,
.ae-form-group select,
.ae-form-group textarea {
  display: block;
  width: 100%;
  padding: 11px 14px;
  font-size: .84rem;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-family: var(--font);
  transition: border-color .2s, box-shadow .2s;
  background: var(--surface);
  color: var(--text);
}

.ae-form-group input:focus,
.ae-form-group select:focus,
.ae-form-group textarea:focus {
  outline: none;
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(64,153,255,.1);
}

.ae-form-group textarea {
  resize: none;
}

.ae-required {
  color: var(--rose);
  font-weight: 700;
}

.ae-hint {
  display: block;
  font-size: .76rem;
  color: var(--muted);
  margin-top: 6px;
}

.ae-form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
}

.ae-form-actions {
  display: flex;
  gap: 12px;
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}

.ae-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 24px;
  border: none;
  border-radius: 10px;
  font-size: .85rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  text-decoration: none;
  font-family: var(--font);
}

.ae-btn-primary {
  background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
  color: #fff;
}

.ae-btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(64,153,255,.25);
}

.ae-btn-secondary {
  background: var(--surface);
  color: var(--text);
  border: 1.5px solid var(--border);
}

.ae-btn-secondary:hover {
  background: var(--bg);
  border-color: var(--muted);
}

.ae-info-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 4px 24px rgba(13,19,33,.07);
}

.ae-info-card-header {
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(180deg, rgba(64,153,255,.04) 0%, transparent 100%);
}

.ae-info-card-header h5 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text);
  font-family: var(--font);
}

.ae-info-card-body {
  padding: 20px 24px;
}

.ae-info-section h6 {
  font-size: .9rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 12px;
  font-family: var(--font);
}

.ae-info-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.ae-info-list li {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: .83rem;
  color: var(--muted);
  margin-bottom: 8px;
  font-family: var(--font);
}

.ae-info-list li:last-child {
  margin-bottom: 0;
}

.ae-info-list i {
  margin-top: 2px;
  font-size: .85rem;
}

.ae-divider {
  margin: 16px 0;
  border: none;
  border-top: 1px solid var(--border);
}

.ae-alert {
  padding: 14px 16px;
  border-radius: 10px;
  margin-bottom: 16px;
  font-size: .84rem;
  font-family: var(--font);
  border: 1px solid;
}

.ae-alert-warning {
  background: #fffbf0;
  border-color: #fde68a;
  color: #92400e;
}

.ae-alert-success {
  background: #ecfdf5;
  border-color: #a7f3d0;
  color: #065f46;
}

.ae-alert-danger {
  background: #fef2f2;
  border-color: #fecaca;
  color: #7f1d1d;
}

.ae-alert h6 {
  margin: 0 0 8px;
  font-weight: 700;
  font-size: .9rem;
}

.ae-alert p {
  margin: 0 0 8px;
  line-height: 1.4;
}

.ae-alert p:last-child {
  margin-bottom: 0;
}

.ae-usage-bar {
  background: var(--bg);
  height: 8px;
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 12px;
}

.ae-usage-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--green) 0%, var(--indigo) 100%);
  border-radius: 4px;
  transition: width .3s ease;
}

.ae-usage-text {
  font-size: .8rem;
  color: var(--muted);
  margin: 0;
}

@media (max-width: 768px) {
  .ae-page { padding: 16px 16px 32px; }
  .ae-banner { padding: 20px 24px; min-height: auto; }
  .ae-form-row { grid-template-columns: 1fr; }
  .ae-form-card-body { padding: 20px; }
}
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
  <div class="pcoded-wrapper">
    <div class="pcoded-content">
      <div class="pcoded-inner-content">
        <div class="main-body">
          <div class="page-wrapper">
            <div class="main-content">

              <div class="ae-page">

                <!-- Banner -->
                <div class="ae-banner">
                  <div class="ae-banner-dot-grid"></div>
                  <div class="ae-banner-left">
                    <div class="ae-banner-tag">
                      <i class="feather icon-user-plus"></i>
                      <?php echo __('add_employee'); ?>
                    </div>
                    <h1 class="ae-banner-h1"><?php echo __('add_new_team_member'); ?></h1>
                    <p class="ae-banner-sub"><?php echo __('fill_in_the_details_below'); ?></p>
                  </div>
                  <div class="ae-banner-right">
                    <a href="employee_management.php" class="ae-banner-back-btn">
                      <i class="feather icon-arrow-left"></i>
                      <?php echo __('back'); ?>
                    </a>
                  </div>
                </div>

                <div class="row">
                  <div class="col-lg-8">

                    <!-- Form Card -->
                    <div class="ae-form-card">
                      <div class="ae-form-card-header">
                        <h5><?php echo __('employee_information'); ?></h5>
                      </div>
                      <div class="ae-form-card-body">

                        <!-- Alerts -->
                        <?php if (isset($error)): ?>
                          <div class="ae-alert ae-alert-danger">
                            <h6><i class="feather icon-alert-circle"></i> <?php echo __('error'); ?></h6>
                            <p><?php echo $error; ?></p>
                          </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                          <div class="ae-alert ae-alert-success">
                            <h6><i class="feather icon-check-circle"></i> <?php echo __('success'); ?></h6>
                            <p><?php echo $success; ?></p>
                          </div>
                        <?php endif; ?>

                        <?php if (!$canAddUser): ?>
                          <div class="ae-alert ae-alert-warning">
                            <h6><i class="feather icon-alert-triangle"></i> <?php echo __('user_limit_reached_title'); ?></h6>
                            <p><?php echo sprintf(__('current_usage_message'), $usageStats['current_users'], $usageStats['max_users']); ?></p>
                            <?php if ($isTenantSuperAdmin): ?>
                              <p><a href="request_user_addon.php" class="ae-btn ae-btn-primary" style="padding: 6px 14px; font-size: .78rem; margin-top: 8px; display: inline-flex;">
                                <i class="feather icon-plus-circle"></i>
                                <?php echo __('request_additional_users'); ?>
                              </a></p>
                            <?php else: ?>
                              <p><i class="feather icon-info"></i> <?php echo __('contact_owner_for_more_users'); ?></p>
                            <?php endif; ?>
                          </div>
                        <?php endif; ?>

                        <!-- Form -->
                        <form method="POST" action=""<?php echo !$canAddUser ? ' style="opacity:0.6;pointer-events:none;"' : ''; ?>>
                          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                          <div class="ae-form-row">
                            <div class="ae-form-group">
                              <label for="name"><?php echo __('full_name'); ?> <span class="ae-required">*</span></label>
                              <input type="text" class="form-control" id="name" name="name"
                                value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                            </div>
                            <div class="ae-form-group">
                              <label for="email"><?php echo __('email'); ?> <span class="ae-required">*</span></label>
                              <input type="email" class="form-control" id="email" name="email"
                                value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                          </div>

                          <div class="ae-form-row">
                            <div class="ae-form-group">
                              <label for="phone"><?php echo __('phone'); ?></label>
                              <input type="tel" class="form-control" id="phone" name="phone"
                                value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                            </div>
                            <div class="ae-form-group">
                              <label for="role"><?php echo __('role'); ?> <span class="ae-required">*</span></label>
                              <select class="form-control" id="role" name="role" required>
                                <option value=""><?php echo __('select_role'); ?></option>
                                <option value="admin" <?php echo ($role ?? '') === 'admin' ? 'selected' : ''; ?>><?php echo __('admin'); ?></option>
                                <option value="finance" <?php echo ($role ?? '') === 'finance' ? 'selected' : ''; ?>><?php echo __('finance'); ?></option>
                                <option value="sales" <?php echo ($role ?? '') === 'sales' ? 'selected' : ''; ?>><?php echo __('sales'); ?></option>
                                <?php if (hasFeature('umrah_bookings', $allowed_features ?? [])): ?>
                                <option value="umrah" <?php echo ($role ?? '') === 'umrah' ? 'selected' : ''; ?>><?php echo __('umrah'); ?></option>
                                <option value="operations" <?php echo ($role ?? '') === 'operations' ? 'selected' : ''; ?>><?php echo __('operations'); ?></option>
                                <option value="hotel_manager" <?php echo ($role ?? '') === 'hotel_manager' ? 'selected' : ''; ?>><?php echo __('hotel_manager'); ?></option>
                                <option value="viewer" <?php echo ($role ?? '') === 'viewer' ? 'selected' : ''; ?>><?php echo __('viewer'); ?></option>
                                <?php endif; ?>
                                <option value="staff" <?php echo ($role ?? '') === 'staff' ? 'selected' : ''; ?>><?php echo __('staff'); ?></option>
                              </select>
                            </div>
                          </div>

                          <div class="ae-form-row">
                            <div class="ae-form-group">
                              <label for="hire_date"><?php echo __('hire_date'); ?> <span class="ae-required">*</span></label>
                              <input type="date" class="form-control" id="hire_date" name="hire_date"
                                value="<?php echo htmlspecialchars($hire_date ?? ''); ?>" required>
                            </div>
                            <div class="ae-form-group">
                              <label for="password"><?php echo __('password'); ?> <span class="ae-required">*</span></label>
                               <input type="password" class="form-control" id="password" name="password"
                                 required minlength="12">
                              <span class="ae-hint"><?php echo __('password_min_length'); ?></span>
                            </div>
                          </div>

                          <div class="ae-form-group">
                            <label for="address"><?php echo __('address'); ?></label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($address ?? ''); ?></textarea>
                          </div>

                          <div class="ae-form-actions">
                            <button type="submit" class="ae-btn ae-btn-primary">
                              <i class="feather icon-save"></i>
                              <?php echo __('add_employee'); ?>
                            </button>
                            <a href="employee_management.php" class="ae-btn ae-btn-secondary">
                              <i class="feather icon-x"></i>
                              <?php echo __('cancel'); ?>
                            </a>
                          </div>
                        </form>

                      </div>
                    </div>

                  </div>

                  <!-- Sidebar Info -->
                  <div class="col-lg-4">

                    <!-- Help Card -->
                    <div class="ae-info-card">
                      <div class="ae-info-card-header">
                        <h5><i class="feather icon-info"></i> <?php echo __('help_information'); ?></h5>
                      </div>
                      <div class="ae-info-card-body">

                        <div class="ae-info-section">
                          <h6><?php echo __('required_fields'); ?></h6>
                          <ul class="ae-info-list">
                            <li><i class="feather icon-check text-success"></i><?php echo __('full_name_required'); ?></li>
                            <li><i class="feather icon-check text-success"></i><?php echo __('email_required'); ?></li>
                            <li><i class="feather icon-check text-success"></i><?php echo __('password_required'); ?></li>
                            <li><i class="feather icon-check text-success"></i><?php echo __('role_required'); ?></li>
                            <li><i class="feather icon-check text-success"></i><?php echo __('hire_date_required'); ?></li>
                          </ul>
                        </div>

                        <hr class="ae-divider">

                        <div class="ae-info-section">
                          <h6><?php echo __('password_requirements'); ?></h6>
                          <ul class="ae-info-list">
                            <li><i class="feather icon-info"></i><?php echo __('minimum_6_characters'); ?></li>
                            <li><i class="feather icon-info"></i><?php echo __('use_strong_password'); ?></li>
                          </ul>
                        </div>

                        <hr class="ae-divider">

                        <!-- Usage Card -->
                        <div class="ae-info-section">
                          <h6><?php echo __('user_usage'); ?></h6>
                          <div class="ae-usage-bar">
                            <div class="ae-usage-fill" style="width: <?php echo $usageStats['usage_percentage']; ?>%"></div>
                          </div>
                          <p class="ae-usage-text"><?php echo sprintf(__('usage_of_max_users'), $usageStats['current_users'], $usageStats['max_users']); ?></p>
                          <?php if ($usageStats['additional_users'] > 0): ?>
                            <p class="ae-usage-text" style="color: var(--green); margin-top: 8px;">
                              <i class="feather icon-plus-circle"></i>
                              <?php echo sprintf(__('additional_users_from_addons'), $usageStats['additional_users']); ?>
                            </p>
                          <?php endif; ?>
                        </div>

                      </div>
                    </div>

                  </div>

                </div><!-- /.row -->

              </div><!-- /.ae-page -->

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- [ Main Content ] end -->

<!-- Required Js -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
    <script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
<?php include '../includes/admin_footer.php'; ?>