<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include language system
require_once('includes/language_helpers.php');
$lang = init_language();

// Process language change if requested via GET
if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

// Database connection
require_once 'config.php';
require_once 'includes/conn.php';
require_once 'includes/db.php';

// Get tenant information if available
$tenant_info = null;
$subscriptions = [];
if (isset($_SESSION['tenant_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name, billing_email, payment_due_date, payment_status FROM tenants WHERE id = ?");
        $stmt->execute([$_SESSION['tenant_id']]);
        $tenant_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch subscriptions
        $stmt2 = $pdo->prepare("
            SELECT ts.*, p.name as plan_name, p.price as plan_price
            FROM tenant_subscriptions ts
            LEFT JOIN plans p ON ts.plan_id = p.id
            WHERE ts.tenant_id = ?
            ORDER BY ts.created_at DESC
        ");
        $stmt2->execute([$_SESSION['tenant_id']]);
        $subscriptions = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Ignore database errors
    }
}
?>

<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Payment Required - MTravels</title>

    <!-- Favicon icon -->
    <link rel="icon" href="assets/images/log.png" type="image/x-icon">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- Custom CSS -->
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .payment-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }

        .card-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            text-align: center;
            padding: 2rem 1rem;
        }

        .card-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .card-body {
            padding: 2rem;
        }

        .alert-warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
            border-radius: 10px;
        }

        .contact-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .btn-logout {
            background: #6c757d;
            border: none;
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-overdue {
            background: #fee;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }

        .status-suspended {
            background: #ffeaea;
            color: #c0392b;
            border: 1px solid #c0392b;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="payment-card">
                    <div class="card-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3 class="mb-0">Payment Required</h3>
                        <p class="mb-0">Account Access Suspended</p>
                    </div>

                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Important:</strong> Your account has been temporarily suspended due to an outstanding subscription payment.
                        </div>

                        <?php if ($tenant_info): ?>
                        <div class="text-center mb-4">
                            <h5 class="mb-3">Account Status</h5>
                            <span class="status-badge status-<?= $tenant_info['payment_status'] ?>">
                                <?= ucfirst($tenant_info['payment_status']) ?>
                            </span>

                            <?php if ($tenant_info['payment_due_date']): ?>
                            <p class="mt-3 mb-0">
                                <strong>Payment Due Date:</strong><br>
                                <?= date('F d, Y', strtotime($tenant_info['payment_due_date'])) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (count($subscriptions) > 0): ?>
                        <div class="mb-4">
                            <h5 class="text-center mb-3">Your Subscriptions</h5>
                            <?php foreach ($subscriptions as $subscription): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6 class="mb-1"><?= htmlspecialchars($subscription['plan_name'] ?? 'Subscription') ?></h6>
                                                <p class="text-muted mb-1">Amount: $<?= number_format($subscription['amount'], 2) ?> <?= htmlspecialchars($subscription['currency']) ?></p>
                                                <small class="text-muted">Status: <?= ucfirst($subscription['status']) ?></small>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <?php if ($subscription['status'] !== 'active'): ?>
                                                    <form method="post" action="admin/process_subscription_payment.php">
                                                        <input type="hidden" name="subscription_id" value="<?php echo $subscription['id']; ?>">
                                                        <input type="hidden" name="amount" value="<?php echo $subscription['amount']; ?>">
                                                        <input type="hidden" name="currency" value="<?php echo $subscription['currency']; ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-credit-card mr-1"></i>Pay Now
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="text-center mb-4">
                            <h5>What happens next?</h5>
                            <p class="text-muted">
                                To restore access to your account, please contact our billing department to arrange payment.
                                Once payment is processed, your account will be reactivated automatically.
                            </p>
                        </div>

                        <div class="contact-info">
                            <h6 class="mb-3"><i class="fas fa-envelope mr-2"></i>Contact Information</h6>
                            <div class="row">
                                <div class="col-sm-6 mb-2">
                                    <strong>Email:</strong><br>
                                    <?php if ($tenant_info && $tenant_info['billing_email']): ?>
                                        <a href="mailto:<?= htmlspecialchars($tenant_info['billing_email']) ?>">
                                            <?= htmlspecialchars($tenant_info['billing_email']) ?>
                                        </a>
                                    <?php else: ?>
                                        allahdadmuahmmadi01@gmail.com
                                    <?php endif; ?>
                                </div>
                                <div class="col-sm-6 mb-2">
                                    <strong>Phone:</strong><br>
                                    +93 78 031 0431
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <p class="text-muted small mb-3">
                                For urgent assistance, please contact us immediately.
                            </p>
                            <a href="logout.php" class="btn btn-logout">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>