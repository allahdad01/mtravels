<?php
/**
 * Client Dashboard
 * Displays overview of client's tickets, hotels, visa, umrah, and additional payments
 * All data filtered by client_id only - read-only view
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db.php';
require_once '../includes/language_helpers.php';
require_once '../includes/session_check.php';

$tenant_id = $_SESSION['tenant_id'];
$client_id = $_SESSION['client_id'] ?? $_SESSION['user_id'];
$lang = init_language();

if (isset($_GET['lang'])) {
    set_language($_GET['lang'], true);
}

// Fetch client info
try {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$client_id, $tenant_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$client) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Client fetch error: " . $e->getMessage());
    $client = [];
}

// Fetch settings
try {
    $settingStmt = $pdo->prepare("SELECT * FROM settings WHERE tenant_id = ?");
    $settingStmt->execute([$tenant_id]);
    $settings = $settingStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $settings = [];
}

// ── Dashboard Statistics ───────────────────────────────────
$stats = [
    'tickets'  => 0,
    'hotels'   => 0,
    'visas'    => 0,
    'umrah'    => 0,
    'payments' => 0,
    'balance'  => ['usd' => 0, 'afs' => 0],
];

$statQueries = [
    'tickets'  => ["SELECT COUNT(*) AS c FROM ticket_bookings   WHERE sold_to = ? AND tenant_id = ?", [$client_id, $tenant_id]],
    'hotels'   => ["SELECT COUNT(*) AS c FROM hotel_bookings    WHERE sold_to = ? AND tenant_id = ?", [strval($client_id), $tenant_id]],
    'visas'    => ["SELECT COUNT(*) AS c FROM visa_applications WHERE sold_to = ? AND tenant_id = ?", [$client_id, $tenant_id]],
    'umrah'    => ["SELECT COUNT(*) AS c FROM umrah_bookings    WHERE sold_to = ? AND tenant_id = ?", [$client_id, $tenant_id]],
    'payments' => ["SELECT COUNT(*) AS c FROM additional_payments WHERE client_id = ? AND tenant_id = ?", [$client_id, $tenant_id]],
];

foreach ($statQueries as $key => [$sql, $params]) {
    try {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        $stats[$key] = (int)($s->fetchColumn() ?? 0);
    } catch (PDOException $e) {
        error_log("Stats[$key] error: " . $e->getMessage());
    }
}

// Balance
try {
    $s = $pdo->prepare("SELECT usd_balance, afs_balance FROM clients WHERE id = ? AND tenant_id = ?");
    $s->execute([$client_id, $tenant_id]);
    $bal = $s->fetch(PDO::FETCH_ASSOC);
    if ($bal) {
        $stats['balance']['usd'] = (float)($bal['usd_balance'] ?? 0);
        $stats['balance']['afs'] = (float)($bal['afs_balance'] ?? 0);
    }
} catch (PDOException $e) {
    error_log("Balance error: " . $e->getMessage());
}

// ── Recent Items ───────────────────────────────────────────
$recent = ['tickets' => [], 'hotels' => [], 'visas' => [], 'umrah' => [], 'payments' => []];

try {
    $s = $pdo->prepare("
        SELECT id, pnr, passenger_name, origin, destination, sold, created_at
        FROM ticket_bookings
        WHERE sold_to = ? AND tenant_id = ?
        ORDER BY created_at DESC LIMIT 5
    ");
    $s->execute([$client_id, $tenant_id]);
    $recent['tickets'] = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Recent tickets: " . $e->getMessage()); }

try {
    $s = $pdo->prepare("
        SELECT id, order_id AS hotel_name,
               DATEDIFF(check_out_date, check_in_date) AS total_nights,
               sold_amount AS total_amount, check_in_date, created_at
        FROM hotel_bookings
        WHERE sold_to = ? AND tenant_id = ?
        ORDER BY created_at DESC LIMIT 5
    ");
    $s->execute([strval($client_id), $tenant_id]);
    $recent['hotels'] = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Recent hotels: " . $e->getMessage()); }

try {
    $s = $pdo->prepare("
        SELECT id, visa_type, country AS destination, sold AS total_cost, status, created_at
        FROM visa_applications
        WHERE sold_to = ? AND tenant_id = ?
        ORDER BY created_at DESC LIMIT 5
    ");
    $s->execute([$client_id, $tenant_id]);
    $recent['visas'] = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Recent visas: " . $e->getMessage()); }

try {
    $s = $pdo->prepare("
        SELECT booking_id AS id,
               CONCAT(name, ' b. ', fname) AS passenger_name,
               flight_date AS umrah_start_date,
               sold_price AS total_price, status, created_at
        FROM umrah_bookings
        WHERE sold_to = ? AND tenant_id = ?
        ORDER BY created_at DESC LIMIT 5
    ");
    $s->execute([$client_id, $tenant_id]);
    $recent['umrah'] = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Recent umrah: " . $e->getMessage()); }

try {
    $s = $pdo->prepare("
        SELECT id, sold_amount AS amount, payment_type, currency, created_at AS payment_date
        FROM additional_payments
        WHERE client_id = ? AND tenant_id = ?
        ORDER BY created_at DESC LIMIT 5
    ");
    $s->execute([$client_id, $tenant_id]);
    $recent['payments'] = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Recent payments: " . $e->getMessage()); }

// ── Helpers ────────────────────────────────────────────────
$profilePic = !empty($client['profile_pic']) ? $client['profile_pic'] : 'default-avatar.jpg';
$imagePath  = '../assets/images/user/' . htmlspecialchars($profilePic);

$totalBookings = $stats['tickets'] + $stats['hotels'] + $stats['visas'] + $stats['umrah'];

function greetClient(): string {
    $h = (int)date('G');
    if ($h < 12) return 'Good Morning';
    if ($h < 17) return 'Good Afternoon';
    return 'Good Evening';
}
?>
<?php include '../includes/header_client.php'; ?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <title><?= htmlspecialchars($settings['agency_name'] ?? 'MTravels') ?> – Dashboard</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <link rel="icon" href="../uploads/logo/<?= htmlspecialchars($settings['logo'] ?? '') ?>" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        /* ─── Root Variables ──────────────────────────────── */
        :root {
            --primary:      #4099ff;
            --accent:       #2ed8b6;
            --grad:         linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            --grad-warm:    linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
            --grad-purple:  linear-gradient(135deg, #7c3aed 0%, #4099ff 100%);
            --success:      #22c55e;
            --warning:      #f59e0b;
            --danger:       #ef4444;
            --umrah:        #d97706;
            --bg-page:      #f4f7fa;
            --bg-card:      #ffffff;
            --border:       #e9ecef;
            --text:         #2d3748;
            --text-light:   #718096;
            --radius-lg:    16px;
            --radius-md:    12px;
            --radius-sm:    8px;
            --shadow-sm:    0 2px 8px rgba(0,0,0,0.06);
            --shadow-md:    0 4px 20px rgba(0,0,0,0.09);
            --shadow-hero:  0 8px 40px rgba(64,153,255,0.25);
            --transition:   all 0.22s ease;
        }

        body { background: var(--bg-page); color: var(--text); }

        /* ─── Hero Welcome Banner ─────────────────────────── */
        .hero-banner {
            background: var(--grad);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            margin-bottom: 26px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: var(--shadow-hero);
            position: relative;
            overflow: hidden;
        }
        .hero-banner::before {
            content: '';
            position: absolute;
            right: -60px; top: -60px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
            pointer-events: none;
        }
        .hero-banner::after {
            content: '';
            position: absolute;
            right: 60px; bottom: -80px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
            pointer-events: none;
        }
        .hero-left { position: relative; z-index: 1; }
        .hero-greeting {
            font-size: 0.88rem; font-weight: 600;
            color: rgba(255,255,255,0.8);
            margin-bottom: 4px;
            display: flex; align-items: center; gap: 6px;
        }
        .hero-greeting .greeting-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #a7f3d0;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.5; transform: scale(1.4); }
        }
        .hero-name {
            font-size: 1.6rem; font-weight: 800;
            color: #fff;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .hero-sub {
            font-size: 0.86rem; color: rgba(255,255,255,0.75);
            display: flex; align-items: center; gap: 6px;
        }
        .hero-total-chip {
            background: rgba(255,255,255,0.18);
            padding: 3px 12px; border-radius: 20px;
            font-size: 0.82rem; font-weight: 600; color: #fff;
        }

        .hero-right {
            display: flex; align-items: center; gap: 16px;
            position: relative; z-index: 1; flex-shrink: 0;
        }
        .hero-avatar {
            width: 60px; height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.4);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
        }
        .hero-date {
            text-align: right; color: rgba(255,255,255,0.8);
            font-size: 0.82rem; line-height: 1.5;
        }
        .hero-date strong { display: block; font-size: 1.1rem; font-weight: 700; color: #fff; }

        /* ─── Balance Cards ───────────────────────────────── */
        .balance-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 26px;
        }
        .balance-card {
            border-radius: var(--radius-lg);
            padding: 22px 24px;
            display: flex; align-items: center; gap: 16px;
            box-shadow: var(--shadow-md);
            position: relative; overflow: hidden;
        }
        .balance-card.usd { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .balance-card.afs { background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%); }
        .balance-card::after {
            content: '';
            position: absolute;
            right: -30px; top: -30px;
            width: 110px; height: 110px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            pointer-events: none;
        }
        .balance-icon {
            width: 52px; height: 52px;
            border-radius: var(--radius-md);
            background: rgba(255,255,255,0.18);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: #fff; flex-shrink: 0;
            position: relative; z-index: 1;
        }
        .balance-info { position: relative; z-index: 1; }
        .balance-info .b-label {
            font-size: 0.78rem; font-weight: 600;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .balance-info .b-value {
            font-size: 1.7rem; font-weight: 800;
            color: #fff; line-height: 1.1;
            font-family: 'Courier New', monospace;
        }
        .balance-info .b-currency {
            font-size: 0.82rem; font-weight: 500;
            color: rgba(255,255,255,0.7); margin-top: 2px;
        }

        /* ─── Stat Cards Grid ─────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            display: flex; flex-direction: column; align-items: center;
            text-align: center;
            transition: var(--transition);
            text-decoration: none; color: inherit;
            cursor: pointer;
        }
        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
            text-decoration: none; color: inherit;
        }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: var(--radius-md);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: #fff;
            margin-bottom: 12px;
        }
        .stat-icon.tickets  { background: linear-gradient(135deg, #4099ff, #0ea5e9); }
        .stat-icon.hotels   { background: linear-gradient(135deg, #059669, #2ed8b6); }
        .stat-icon.visas    { background: linear-gradient(135deg, #7c3aed, #6366f1); }
        .stat-icon.umrah    { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .stat-icon.payments { background: linear-gradient(135deg, #db2777, #ec4899); }
        .stat-icon.total    { background: var(--grad); }

        .stat-value {
            font-size: 2rem; font-weight: 800;
            color: var(--text); line-height: 1;
            margin-bottom: 6px;
            font-family: 'Courier New', monospace;
        }
        .stat-label {
            font-size: 0.72rem; font-weight: 700;
            color: var(--text-light);
            text-transform: uppercase; letter-spacing: 0.6px;
        }
        .stat-link {
            margin-top: 10px;
            font-size: 0.75rem; color: var(--primary);
            font-weight: 600;
            display: flex; align-items: center; gap: 3px;
        }

        /* ─── Section Cards ───────────────────────────────── */
        .section-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 22px;
        }
        .section-head {
            display: flex; align-items: center; gap: 12px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--border);
        }
        .section-head .sec-icon {
            width: 34px; height: 34px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem; color: #fff;
            flex-shrink: 0;
        }
        .sec-icon.tickets  { background: linear-gradient(135deg, #4099ff, #0ea5e9); }
        .sec-icon.hotels   { background: linear-gradient(135deg, #059669, #2ed8b6); }
        .sec-icon.visas    { background: linear-gradient(135deg, #7c3aed, #6366f1); }
        .sec-icon.umrah    { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .sec-icon.payments { background: linear-gradient(135deg, #db2777, #ec4899); }

        .section-head h6 {
            font-size: 0.97rem; font-weight: 700;
            margin: 0; flex: 1; color: var(--text);
        }
        .section-head .rec-count {
            font-size: 0.76rem; font-weight: 600;
            color: var(--text-light);
            background: #f1f3f5;
            padding: 3px 10px; border-radius: 20px;
        }
        .view-all-btn {
            display: inline-flex; align-items: center; gap: 4px;
            background: transparent; color: var(--primary);
            border: 1px solid var(--primary);
            border-radius: var(--radius-sm);
            padding: 6px 14px;
            font-size: 0.78rem; font-weight: 600;
            text-decoration: none; transition: var(--transition);
        }
        .view-all-btn:hover { background: var(--primary); color: #fff; text-decoration: none; }

        /* ─── Recent Tables ───────────────────────────────── */
        .recent-table { width: 100%; border-collapse: collapse; }
        .recent-table thead th {
            padding: 11px 16px;
            font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-light); background: #f9fafb;
            border-bottom: 1px solid var(--border);
            white-space: nowrap; text-align: left;
        }
        .recent-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        .recent-table tbody tr:last-child { border-bottom: none; }
        .recent-table tbody tr:hover { background: #fafbfc; }
        .recent-table tbody td {
            padding: 12px 16px;
            font-size: 0.86rem; color: var(--text);
            vertical-align: middle;
        }

        /* Cell helpers */
        .pnr-badge {
            font-family: 'Courier New', monospace;
            font-weight: 700; font-size: 0.82rem;
            background: #e0f2fe; color: #0369a1;
            padding: 3px 9px; border-radius: 6px;
            display: inline-block;
        }
        .route-cell {
            display: flex; align-items: center; gap: 5px;
            font-size: 0.84rem;
        }
        .route-arr { color: var(--text-light); font-size: 0.8rem; }
        .amount-text { font-weight: 700; color: var(--success); font-size: 0.9rem; }
        .date-text   { font-size: 0.78rem; color: var(--text-light); }
        .nights-badge {
            background: #f3eeff; color: #7c3aed;
            padding: 3px 9px; border-radius: 20px;
            font-size: 0.78rem; font-weight: 700;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .country-chip {
            background: #e0f2fe; color: #0369a1;
            padding: 3px 9px; border-radius: 6px;
            font-size: 0.8rem; font-weight: 600;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .visa-type-cell {
            display: flex; align-items: center; gap: 7px;
        }
        .visa-type-dot {
            width: 8px; height: 8px;
            border-radius: 50%; background: #7c3aed;
            flex-shrink: 0;
        }
        .status-pill {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600;
        }
        .sp-confirmed { background: #eafaf1; color: #1e7e34; }
        .sp-pending   { background: #fff8e1; color: #856404; }
        .sp-completed { background: #e0f2fe; color: #0369a1; }
        .sp-cancelled { background: #fdecea; color: #b02a37; }
        .sp-approved  { background: #eafaf1; color: #1e7e34; }
        .sp-rejected  { background: #fdecea; color: #b02a37; }

        .payment-type-chip {
            background: #fce7f3; color: #db2777;
            padding: 3px 9px; border-radius: 6px;
            font-size: 0.8rem; font-weight: 600;
            display: inline-block;
        }
        .currency-chip {
            background: #f3eeff; color: #7c3aed;
            padding: 3px 9px; border-radius: 6px;
            font-size: 0.78rem; font-weight: 700;
        }

        /* ─── Empty mini-state ────────────────────────────── */
        .empty-mini {
            text-align: center; padding: 32px 20px;
        }
        .empty-mini i { font-size: 2rem; color: var(--border); display: block; margin-bottom: 10px; }
        .empty-mini p { font-size: 0.84rem; color: var(--text-light); margin: 0; }

        /* ─── Quick Links ─────────────────────────────────── */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }
        .quick-link-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 16px 14px;
            text-align: center;
            text-decoration: none;
            display: flex; flex-direction: column; align-items: center; gap: 10px;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        .quick-link-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
            text-decoration: none;
        }
        .ql-icon {
            width: 42px; height: 42px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #fff;
        }
        .ql-label {
            font-size: 0.78rem; font-weight: 700;
            color: var(--text); text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ─── Section Divider ─────────────────────────────── */
        .section-divider {
            display: flex; align-items: center; gap: 14px;
            margin: 28px 0 20px;
        }
        .section-divider h6 {
            font-size: 0.85rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: var(--text-light); white-space: nowrap; margin: 0;
        }
        .section-divider::before,
        .section-divider::after {
            content: ''; flex: 1;
            height: 1px; background: var(--border);
        }

        /* ─── Responsive ──────────────────────────────────── */
        @media (max-width: 768px) {
            .hero-banner  { flex-direction: column; align-items: flex-start; padding: 22px; }
            .hero-right   { width: 100%; justify-content: space-between; }
            .balance-row  { grid-template-columns: 1fr; }
            .stats-grid   { grid-template-columns: repeat(2, 1fr); }
            .quick-links  { grid-template-columns: repeat(3, 1fr); }
            .hero-date    { text-align: left; }
        }
        @media (max-width: 480px) {
            .stats-grid   { grid-template-columns: repeat(2, 1fr); }
            .quick-links  { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<div class="pcoded-main-container">
    <div class="pcoded-content">

        <!-- ── Hero Welcome Banner ────────────────────────── -->
        <div class="hero-banner">
            <div class="hero-left">
                <div class="hero-greeting">
                    <span class="greeting-dot"></span>
                    <?= greetClient() ?>
                </div>
                <div class="hero-name"><?= htmlspecialchars($client['name'] ?? 'Client') ?></div>
                <div class="hero-sub">
                    Here's your travel overview &nbsp;
                    <span class="hero-total-chip"><?= number_format($totalBookings) ?> total bookings</span>
                </div>
            </div>
            <div class="hero-right">
                <div class="hero-date">
                    <strong><?= date('d M Y') ?></strong>
                    <?= date('l') ?>
                </div>
                <img src="<?= $imagePath ?>" alt="Profile" class="hero-avatar"
                     onerror="this.src='../assets/images/user/default-avatar.jpg'">
            </div>
        </div>

        <!-- ── Account Balances ───────────────────────────── -->
        <div class="balance-row">
            <div class="balance-card usd">
                <div class="balance-icon"><i class="feather icon-dollar-sign"></i></div>
                <div class="balance-info">
                    <div class="b-label">USD Balance</div>
                    <div class="b-value"><?= number_format($stats['balance']['usd'], 2) ?></div>
                    <div class="b-currency">United States Dollar</div>
                </div>
            </div>
            <div class="balance-card afs">
                <div class="balance-icon"><i class="feather icon-credit-card"></i></div>
                <div class="balance-info">
                    <div class="b-label">AFS Balance</div>
                    <div class="b-value"><?= number_format($stats['balance']['afs'], 2) ?></div>
                    <div class="b-currency">Afghan Afghani</div>
                </div>
            </div>
        </div>

        <!-- ── Stat Cards ─────────────────────────────────── -->
        <div class="stats-grid">

            <a href="ticket.php" class="stat-card">
                <div class="stat-icon tickets"><i class="feather icon-send"></i></div>
                <div class="stat-value"><?= number_format($stats['tickets']) ?></div>
                <div class="stat-label">Tickets</div>
                <div class="stat-link">View all <i class="feather icon-arrow-right"></i></div>
            </a>

            <a href="hotel.php" class="stat-card">
                <div class="stat-icon hotels"><i class="feather icon-home"></i></div>
                <div class="stat-value"><?= number_format($stats['hotels']) ?></div>
                <div class="stat-label">Hotels</div>
                <div class="stat-link">View all <i class="feather icon-arrow-right"></i></div>
            </a>

            <a href="visa.php" class="stat-card">
                <div class="stat-icon visas"><i class="feather icon-globe"></i></div>
                <div class="stat-value"><?= number_format($stats['visas']) ?></div>
                <div class="stat-label">Visas</div>
                <div class="stat-link">View all <i class="feather icon-arrow-right"></i></div>
            </a>

            <a href="umrah.php" class="stat-card">
                <div class="stat-icon umrah"><i class="feather icon-map-pin"></i></div>
                <div class="stat-value"><?= number_format($stats['umrah']) ?></div>
                <div class="stat-label">Umrah</div>
                <div class="stat-link">View all <i class="feather icon-arrow-right"></i></div>
            </a>

            <a href="additional_payments.php" class="stat-card">
                <div class="stat-icon payments"><i class="feather icon-credit-card"></i></div>
                <div class="stat-value"><?= number_format($stats['payments']) ?></div>
                <div class="stat-label">Payments</div>
                <div class="stat-link">View all <i class="feather icon-arrow-right"></i></div>
            </a>

            <div class="stat-card" style="cursor:default;">
                <div class="stat-icon total"><i class="feather icon-layers"></i></div>
                <div class="stat-value"><?= number_format($totalBookings) ?></div>
                <div class="stat-label">All Bookings</div>
                <div class="stat-link" style="color:var(--text-light); cursor:default;">Combined total</div>
            </div>

        </div>

        <!-- ── Quick Links ────────────────────────────────── -->
        <div class="section-divider">
            <h6>Quick Access</h6>
        </div>
        <div class="quick-links">
            <a href="ticket.php" class="quick-link-card">
                <div class="ql-icon" style="background:linear-gradient(135deg,#4099ff,#0ea5e9);"><i class="feather icon-send"></i></div>
                <span class="ql-label">Tickets</span>
            </a>
            <a href="ticket_reservations.php" class="quick-link-card">
                <div class="ql-icon" style="background:linear-gradient(135deg,#0284c7,#0ea5e9);"><i class="feather icon-bookmark"></i></div>
                <span class="ql-label">Reservations</span>
            </a>
            <a href="hotel.php" class="quick-link-card">
                <div class="ql-icon" style="background:linear-gradient(135deg,#059669,#2ed8b6);"><i class="feather icon-home"></i></div>
                <span class="ql-label">Hotels</span>
            </a>
            <a href="visa.php" class="quick-link-card">
                <div class="ql-icon" style="background:linear-gradient(135deg,#7c3aed,#6366f1);"><i class="feather icon-globe"></i></div>
                <span class="ql-label">Visas</span>
            </a>
            <a href="umrah.php" class="quick-link-card">
                <div class="ql-icon" style="background:linear-gradient(135deg,#d97706,#f59e0b);"><i class="feather icon-map-pin"></i></div>
                <span class="ql-label">Umrah</span>
            </a>
            <a href="additional_payments.php" class="quick-link-card">
                <div class="ql-icon" style="background:linear-gradient(135deg,#db2777,#ec4899);"><i class="feather icon-credit-card"></i></div>
                <span class="ql-label">Payments</span>
            </a>
        </div>

        <!-- ── Recent Activity ────────────────────────────── -->
        <div class="section-divider">
            <h6>Recent Activity</h6>
        </div>

        <!-- Recent Tickets -->
        <div class="section-card">
            <div class="section-head">
                <span class="sec-icon tickets"><i class="feather icon-send"></i></span>
                <h6>Recent Tickets</h6>
                <span class="rec-count"><?= count($recent['tickets']) ?> shown</span>
                <a href="ticket.php" class="view-all-btn">View All <i class="feather icon-arrow-right"></i></a>
            </div>
            <?php if (!empty($recent['tickets'])): ?>
                <div style="overflow-x:auto;">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>PNR</th>
                                <th>Passenger</th>
                                <th>Route</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent['tickets'] as $t): ?>
                            <tr>
                                <td><span class="pnr-badge"><?= htmlspecialchars($t['pnr']) ?></span></td>
                                <td style="font-weight:600;"><?= htmlspecialchars($t['passenger_name']) ?></td>
                                <td>
                                    <div class="route-cell">
                                        <span><?= htmlspecialchars($t['origin']) ?></span>
                                        <span class="route-arr"><i class="feather icon-arrow-right"></i></span>
                                        <span><?= htmlspecialchars($t['destination']) ?></span>
                                    </div>
                                </td>
                                <td><span class="amount-text">$<?= number_format((float)$t['sold'], 2) ?></span></td>
                                <td><span class="date-text"><?= date('d M Y', strtotime($t['created_at'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-mini">
                    <i class="feather icon-send"></i>
                    <p>No recent ticket bookings</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Hotels -->
        <div class="section-card">
            <div class="section-head">
                <span class="sec-icon hotels"><i class="feather icon-home"></i></span>
                <h6>Recent Hotels</h6>
                <span class="rec-count"><?= count($recent['hotels']) ?> shown</span>
                <a href="hotel.php" class="view-all-btn">View All <i class="feather icon-arrow-right"></i></a>
            </div>
            <?php if (!empty($recent['hotels'])): ?>
                <div style="overflow-x:auto;">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Check-in</th>
                                <th>Nights</th>
                                <th>Amount</th>
                                <th>Booked</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent['hotels'] as $h): ?>
                            <tr>
                                <td style="font-weight:600; color:var(--primary);"><?= htmlspecialchars($h['hotel_name']) ?></td>
                                <td><span class="date-text"><?= htmlspecialchars($h['check_in_date']) ?></span></td>
                                <td>
                                    <?php if ((int)$h['total_nights'] > 0): ?>
                                        <span class="nights-badge"><i class="feather icon-moon"></i> <?= (int)$h['total_nights'] ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-light);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="amount-text">$<?= number_format((float)$h['total_amount'], 2) ?></span></td>
                                <td><span class="date-text"><?= date('d M Y', strtotime($h['created_at'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-mini">
                    <i class="feather icon-home"></i>
                    <p>No recent hotel bookings</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Visas -->
        <div class="section-card">
            <div class="section-head">
                <span class="sec-icon visas"><i class="feather icon-globe"></i></span>
                <h6>Recent Visa Applications</h6>
                <span class="rec-count"><?= count($recent['visas']) ?> shown</span>
                <a href="visa.php" class="view-all-btn">View All <i class="feather icon-arrow-right"></i></a>
            </div>
            <?php if (!empty($recent['visas'])): ?>
                <div style="overflow-x:auto;">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Visa Type</th>
                                <th>Destination</th>
                                <th>Cost</th>
                                <th>Status</th>
                                <th>Applied</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent['visas'] as $v):
                                $vs = strtolower($v['status'] ?? 'pending');
                                $vClass = match($vs) {
                                    'approved' => 'sp-approved',
                                    'rejected' => 'sp-rejected',
                                    'issued'   => 'sp-completed',
                                    default    => 'sp-pending',
                                };
                            ?>
                            <tr>
                                <td>
                                    <div class="visa-type-cell">
                                        <span class="visa-type-dot"></span>
                                        <span style="font-weight:600;"><?= htmlspecialchars($v['visa_type']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="country-chip">
                                        <i class="feather icon-map-pin" style="font-size:0.75rem;"></i>
                                        <?= htmlspecialchars($v['destination']) ?>
                                    </span>
                                </td>
                                <td><span class="amount-text">$<?= number_format((float)$v['total_cost'], 2) ?></span></td>
                                <td><span class="status-pill <?= $vClass ?>"><?= ucfirst($vs) ?></span></td>
                                <td><span class="date-text"><?= date('d M Y', strtotime($v['created_at'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-mini">
                    <i class="feather icon-globe"></i>
                    <p>No recent visa applications</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Umrah -->
        <div class="section-card">
            <div class="section-head">
                <span class="sec-icon umrah"><i class="feather icon-map-pin"></i></span>
                <h6>Recent Umrah Packages</h6>
                <span class="rec-count"><?= count($recent['umrah']) ?> shown</span>
                <a href="umrah.php" class="view-all-btn">View All <i class="feather icon-arrow-right"></i></a>
            </div>
            <?php if (!empty($recent['umrah'])): ?>
                <div style="overflow-x:auto;">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Pilgrim</th>
                                <th>Flight Date</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Booked</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent['umrah'] as $u):
                                $us = strtolower($u['status'] ?? 'confirmed');
                                $uClass = match($us) {
                                    'confirmed'  => 'sp-confirmed',
                                    'completed'  => 'sp-completed',
                                    'cancelled'  => 'sp-cancelled',
                                    default      => 'sp-pending',
                                };
                            ?>
                            <tr>
                                <td style="font-weight:600;"><?= htmlspecialchars($u['passenger_name']) ?></td>
                                <td>
                                    <span style="display:flex;align-items:center;gap:5px;font-size:0.84rem;">
                                        <i class="feather icon-send" style="font-size:0.75rem;color:var(--umrah);"></i>
                                        <?= htmlspecialchars($u['umrah_start_date']) ?>
                                    </span>
                                </td>
                                <td><span class="amount-text">$<?= number_format((float)$u['total_price'], 2) ?></span></td>
                                <td><span class="status-pill <?= $uClass ?>"><?= ucfirst($us) ?></span></td>
                                <td><span class="date-text"><?= date('d M Y', strtotime($u['created_at'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-mini">
                    <i class="feather icon-map-pin"></i>
                    <p>No recent Umrah bookings</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Payments -->
        <div class="section-card">
            <div class="section-head">
                <span class="sec-icon payments"><i class="feather icon-credit-card"></i></span>
                <h6>Recent Payments</h6>
                <span class="rec-count"><?= count($recent['payments']) ?> shown</span>
                <a href="additional_payments.php" class="view-all-btn">View All <i class="feather icon-arrow-right"></i></a>
            </div>
            <?php if (!empty($recent['payments'])): ?>
                <div style="overflow-x:auto;">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Currency</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent['payments'] as $p): ?>
                            <tr>
                                <td>
                                    <span class="payment-type-chip"><?= htmlspecialchars(ucfirst($p['payment_type'])) ?></span>
                                </td>
                                <td><span class="amount-text">$<?= number_format((float)$p['amount'], 2) ?></span></td>
                                <td><span class="currency-chip"><?= htmlspecialchars($p['currency']) ?></span></td>
                                <td><span class="date-text"><?= date('d M Y', strtotime($p['payment_date'])) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-mini">
                    <i class="feather icon-credit-card"></i>
                    <p>No recent payments</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="../assets/js/vendor-all.min.js"></script>
<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../assets/js/pcoded.min.js"></script>
</body>
</html>