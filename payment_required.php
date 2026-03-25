<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('includes/language_helpers.php');
$lang = init_language();

if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

require_once 'config.php';
require_once 'includes/db.php';

$tenant_info   = null;
$subscriptions = [];
if (isset($_SESSION['tenant_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name, billing_email, payment_due_date, payment_status FROM tenants WHERE id = ?");
        $stmt->execute([$_SESSION['tenant_id']]);
        $tenant_info = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare("
            SELECT ts.*, p.name as plan_name, p.price as plan_price
            FROM tenant_subscriptions ts
            LEFT JOIN plans p ON ts.plan_id = p.id
            WHERE ts.tenant_id = ?
            ORDER BY ts.created_at DESC
        ");
        $stmt2->execute([$_SESSION['tenant_id']]);
        $subscriptions = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

$is_overdue = $tenant_info && $tenant_info['payment_status'] === 'overdue';
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Required – MTravels</title>
  <link rel="icon" href="assets/images/log.png" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    /* ── Variables ─────────────────────────────────────── */
    :root {
      --brand-from:  #4099ff;
      --brand-to:    #2ed8b6;
      --brand-grad:  linear-gradient(135deg, var(--brand-from), var(--brand-to));

      --warn-from:   #ff6b6b;
      --warn-to:     #f5a623;
      --warn-grad:   linear-gradient(135deg, var(--warn-from), var(--warn-to));

      --bg:          #f4f6fb;
      --surface:     #ffffff;
      --border:      #e4e8f0;
      --text-primary:#1a1f36;
      --text-muted:  #6b7280;
      --text-light:  #9ca3af;

      --radius-sm:   8px;
      --radius-md:   14px;
      --radius-lg:   20px;
      --radius-xl:   28px;

      --shadow:      0 4px 24px rgba(0,0,0,.07), 0 1px 4px rgba(0,0,0,.04);
      --shadow-btn:  0 4px 14px rgba(255,107,107,.35);

      --font-display:'DM Serif Display', Georgia, serif;
      --font-body:   'DM Sans', system-ui, sans-serif;
      --transition:  0.2s ease;
    }

    /* ── Reset ─────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { height: 100%; }
    body {
      font-family: var(--font-body);
      font-size: 15px;
      color: var(--text-primary);
      background: var(--bg);
      min-height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1.25rem;
      -webkit-font-smoothing: antialiased;
    }

    /* ── Page wrapper ──────────────────────────────────── */
    .page-wrap {
      width: 100%;
      max-width: 1200px;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    /* ── Card ──────────────────────────────────────────── */
    .card {
      background: var(--surface);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow);
      overflow: hidden;
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: row;
    }

    /* ── Card top banner ───────────────────────────────── */
    .card-banner {
      background: var(--warn-grad);
      padding: 2.25rem 2rem 2rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: .75rem;
      text-align: center;
      position: relative;
      overflow: hidden;
      min-width: 300px;
      justify-content: center;
    }
    .card-banner::before,
    .card-banner::after {
      content: '';
      position: absolute;
      border-radius: 50%;
      background: rgba(255,255,255,.1);
    }
    .card-banner::before { width: 260px; height: 260px; top: -100px; right: -60px; }
    .card-banner::after  { width: 180px; height: 180px; bottom: -80px; left: -40px; }

    .banner-icon {
      position: relative;
      z-index: 1;
      width: 64px; height: 64px;
      background: rgba(255,255,255,.2);
      border: 2px solid rgba(255,255,255,.4);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      color: #fff;
    }

    .card-banner h1 {
      position: relative;
      z-index: 1;
      font-family: var(--font-display);
      font-size: 1.7rem;
      font-weight: 400;
      color: #fff;
      line-height: 1.1;
    }

    .card-banner p {
      position: relative;
      z-index: 1;
      font-size: .9rem;
      color: rgba(255,255,255,.85);
    }

    /* ── Card body ─────────────────────────────────────── */
    .card-body {
      padding: 2rem;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
      flex: 1;
      min-width: 0;
    }

    /* ── Notice box ────────────────────────────────────── */
    .notice {
      background: #fff8ec;
      border: 1.5px solid #fde68a;
      border-radius: var(--radius-md);
      padding: .875rem 1rem;
      display: flex;
      gap: .65rem;
      align-items: flex-start;
      font-size: .875rem;
      color: #92400e;
      line-height: 1.5;
    }
    .notice i { flex-shrink: 0; margin-top: .1rem; color: #d97706; }

    /* ── Status block ──────────────────────────────────── */
    .status-block {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: .75rem;
      text-align: center;
    }

    .status-block h2 {
      font-size: .75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--text-light);
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .4rem 1rem;
      border-radius: 99px;
      font-size: .8rem;
      font-weight: 600;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .status-badge.overdue    { background:#fff1f2; color:#be123c; border:1.5px solid #fecdd3; }
    .status-badge.suspended  { background:#fef2f2; color:#991b1b; border:1.5px solid #fca5a5; }
    .status-badge.pending    { background:#fffbeb; color:#92400e; border:1.5px solid #fde68a; }
    .status-badge.active     { background:#f0fdf4; color:#166534; border:1.5px solid #bbf7d0; }

    .due-date {
      font-size: .875rem;
      color: var(--text-muted);
    }
    .due-date strong { color: var(--text-primary); }

    /* ── Divider ───────────────────────────────────────── */
    .divider {
      height: 1px;
      background: var(--border);
      border: none;
    }

    /* ── Section title ─────────────────────────────────── */
    .section-title {
      font-size: .75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--text-light);
      margin-bottom: .75rem;
    }

    /* ── Subscription rows ─────────────────────────────── */
    .sub-list {
      display: flex;
      flex-direction: column;
      gap: .75rem;
    }

    .sub-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      background: var(--bg);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      padding: 1rem 1.125rem;
    }

    .sub-info {
      display: flex;
      flex-direction: column;
      gap: .2rem;
      min-width: 0;
    }

    .sub-name {
      font-weight: 600;
      font-size: .92rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .sub-meta {
      font-size: .8rem;
      color: var(--text-muted);
    }

    .sub-action { flex-shrink: 0; }

    /* ── Buttons ───────────────────────────────────────── */
    .btn-pay {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .55rem 1.1rem;
      background: var(--warn-grad);
      border: none;
      border-radius: var(--radius-sm);
      color: #fff;
      font-family: var(--font-body);
      font-size: .82rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: opacity var(--transition), transform var(--transition);
      box-shadow: var(--shadow-btn);
    }
    .btn-pay:hover { opacity: .88; transform: translateY(-1px); }

    .badge-active {
      display: inline-flex;
      align-items: center;
      gap: .3rem;
      padding: .45rem .85rem;
      background: #f0fdf4;
      color: #166534;
      border: 1.5px solid #bbf7d0;
      border-radius: var(--radius-sm);
      font-size: .8rem;
      font-weight: 600;
    }

    /* ── Next steps ────────────────────────────────────── */
    .steps {
      font-size: .875rem;
      color: var(--text-muted);
      line-height: 1.65;
      text-align: center;
    }

    /* ── Contact block ─────────────────────────────────── */
    .contact-block {
      background: var(--bg);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      padding: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: .875rem;
    }

    .contact-block .section-title { margin-bottom: 0; }

    .contact-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .75rem;
    }

    @media (max-width: 420px) {
      .contact-grid { grid-template-columns: 1fr; }
    }

    .contact-item {
      display: flex;
      flex-direction: column;
      gap: .2rem;
    }

    .contact-item .label {
      font-size: .72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: var(--text-light);
    }

    .contact-item a,
    .contact-item span {
      font-size: .875rem;
      font-weight: 500;
      color: var(--text-primary);
      text-decoration: none;
      word-break: break-all;
    }

    .contact-item a { color: var(--brand-from); }
    .contact-item a:hover { opacity: .75; }

    /* ── Logout button ─────────────────────────────────── */
    .btn-logout {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      width: 100%;
      height: 50px;
      background: var(--bg);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-md);
      font-family: var(--font-body);
      font-size: .9rem;
      font-weight: 600;
      color: var(--text-muted);
      text-decoration: none;
      cursor: pointer;
      transition: background var(--transition), color var(--transition), border-color var(--transition);
    }
    .btn-logout:hover {
      background: #fff1f2;
      color: #be123c;
      border-color: #fecdd3;
    }

    /* ── Footer note ───────────────────────────────────── */
    .footer-note {
      text-align: center;
      font-size: .78rem;
      color: var(--text-light);
    }

    @media (max-width: 768px) {
      .page-wrap { flex-direction: column; }
      .card { flex-direction: column; }
      .card-banner { min-width: auto; }
      .footer-note { margin-top: 1rem; }
    }
  </style>
</head>
<body>

<div class="page-wrap">

  <div class="card">
    <!-- Banner -->
    <div class="card-banner">
      <div class="banner-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h1>Payment Required</h1>
      <p>Your account access has been suspended.</p>
    </div>

    <!-- Body -->
    <div class="card-body">

      <!-- Notice -->
      <div class="notice">
        <i class="fas fa-info-circle"></i>
        <span><strong>Action needed:</strong> Your account has been temporarily suspended due to an outstanding subscription payment. Please settle the balance to restore access.</span>
      </div>

      <!-- Account status -->
      <?php if ($tenant_info): ?>
        <div class="status-block">
          <h2>Account Status</h2>
          <span class="status-badge <?= htmlspecialchars($tenant_info['payment_status']) ?>">
            <i class="fas fa-circle" style="font-size:.5rem;"></i>
            <?= ucfirst(htmlspecialchars($tenant_info['payment_status'])) ?>
          </span>
          <?php if ($tenant_info['payment_due_date']): ?>
            <p class="due-date">
              Payment due <strong><?= date('F j, Y', strtotime($tenant_info['payment_due_date'])) ?></strong>
            </p>
          <?php endif; ?>
        </div>
        <hr class="divider">
      <?php endif; ?>

      <!-- Subscriptions -->
      <?php if (count($subscriptions) > 0): ?>
        <div>
          <p class="section-title">Your Subscriptions</p>
          <div class="sub-list">
            <?php foreach ($subscriptions as $sub): ?>
              <div class="sub-row">
                <div class="sub-info">
                  <span class="sub-name"><?= htmlspecialchars($sub['plan_name'] ?? 'Subscription') ?></span>
                  <span class="sub-meta">
                    <?= number_format($sub['amount'], 2) ?> <?= htmlspecialchars($sub['currency']) ?>
                    &nbsp;·&nbsp; <?= ucfirst($sub['status']) ?>
                  </span>
                </div>
                <div class="sub-action">
                  <?php if ($sub['status'] !== 'active'): ?>
                    <form method="post" action="tenant_super_admin/process_subscription_payment.php">
                      <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                      <input type="hidden" name="amount"          value="<?= $sub['amount'] ?>">
                      <input type="hidden" name="currency"        value="<?= $sub['currency'] ?>">
                      <button type="submit" class="btn-pay">
                        <i class="fas fa-credit-card"></i> Pay Now
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="badge-active"><i class="fas fa-check"></i> Active</span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <hr class="divider">
      <?php endif; ?>

      <!-- Next steps -->
      <p class="steps">
        To restore access, contact our billing team to arrange payment.
        Your account will be reactivated automatically once payment is confirmed.
      </p>

      <!-- Contact -->
      <div class="contact-block">
        <p class="section-title"><i class="fas fa-headset" style="margin-right:.4rem;"></i>Billing Contact</p>
        <div class="contact-grid">
          <div class="contact-item">
            <span class="label">Email</span>
            <?php
              $email = ($tenant_info && $tenant_info['billing_email'])
                ? $tenant_info['billing_email']
                : 'allahdadmuahmmadi01@gmail.com';
            ?>
            <a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a>
          </div>
          <div class="contact-item">
            <span class="label">Phone</span>
            <a href="tel:+93780310431">+93 78 031 0431</a>
          </div>
        </div>
      </div>

      <!-- Logout -->
      <a href="logout.php" class="btn-logout">
        <i class="fas fa-sign-out-alt"></i> Sign out
      </a>

    </div><!-- /card-body -->
  </div><!-- /card -->

  <p class="footer-note">
    For urgent assistance, contact us immediately.
  </p>

</div>

</body>
</html>