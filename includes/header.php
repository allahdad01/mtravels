<?php
/**
 * header.php
 * Main header / navigation partial.
 *
 * Usage (at the top of every protected page):
 *   require_once('header.php');
 *
 * This file:
 *   1. Bootstraps auth, DB, features, settings via auth_check.php
 *   2. Outputs the <html>, <head>, pre-loader, mobile button, and sidebar nav
 *   3. Renders the floating chat widget if the feature is enabled
 *   4. Includes the floating tasks widget
 */

require_once(__DIR__ . '/auth_check.php');

$header_unread_notifications = [];
$header_read_notifications = [];
$header_unread_count = 0;
if (($user['role'] ?? '') === 'admin' && isset($pdo, $tenant_id)) {
    try {
        $header_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE LOWER(status) = 'unread' AND tenant_id = ?");
        $header_count_stmt->execute([$tenant_id]);
        $header_unread_count = (int) $header_count_stmt->fetchColumn();

        $header_notification_select = "
            SELECT n.*,
                   CASE WHEN n.transaction_type='visa' THEN va.applicant_name
                        WHEN n.transaction_type='supplier' THEN s.name
                        WHEN n.transaction_type='umrah' THEN ub.name
                        ELSE NULL END AS related_name,
                   CASE WHEN n.transaction_type='visa' THEN va.base
                        WHEN n.transaction_type='supplier' THEN st.amount
                        WHEN n.transaction_type='umrah' THEN ub.sold_price
                        WHEN n.transaction_type='cash_settlement' THEN cs.amount
                        ELSE 0 END AS transaction_amount,
                   CASE WHEN n.transaction_type='visa' THEN va.currency
                        WHEN n.transaction_type='supplier' THEN s.currency
                        WHEN n.transaction_type='cash_settlement' THEN cs.currency
                        ELSE NULL END AS transaction_currency,
                   CASE WHEN n.transaction_type='umrah' THEN ut.transaction_to
                        ELSE NULL END AS umrah_transaction_to
            FROM notifications n
            LEFT JOIN visa_applications va ON n.transaction_id=va.id AND n.transaction_type='visa'
            LEFT JOIN umrah_bookings ub ON n.transaction_id=ub.booking_id AND n.transaction_type='umrah'
            LEFT JOIN umrah_transactions ut ON n.transaction_id=ut.id AND n.transaction_type='umrah'
            LEFT JOIN supplier_transactions st ON n.transaction_id=st.id AND n.transaction_type='supplier'
            LEFT JOIN suppliers s ON st.supplier_id=s.id OR va.supplier=s.id
            LEFT JOIN cash_settlements cs ON n.transaction_id=cs.id AND n.transaction_type='cash_settlement'
            WHERE LOWER(n.status)=? AND n.tenant_id=?
        ";

        $header_unread_stmt = $pdo->prepare($header_notification_select . " ORDER BY n.created_at DESC");
        $header_unread_stmt->execute(['unread', $tenant_id]);
        $header_unread_notifications = $header_unread_stmt->fetchAll(PDO::FETCH_ASSOC);

        $header_read_stmt = $pdo->prepare($header_notification_select . " AND DATE(n.created_at)=? ORDER BY n.created_at DESC");
        $header_read_stmt->execute(['read', $tenant_id, date('Y-m-d')]);
        $header_read_notifications = $header_read_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Header notification error: ' . $e->getMessage());
    }
}

$onboarding_guide = null;
$onboarding_data = [];
$onboarding_video = null;
if (isset($pdo, $tenant_id) && in_array($user['role'] ?? '', ['admin', 'finance', 'sales', 'umrah'])) {
    require_once __DIR__ . '/OnboardingGuide.php';
    $guide = new OnboardingGuide($pdo, (int)$tenant_id, (int)($_SESSION['branch_id'] ?? 0));
    if ($guide->shouldShow()) {
        $onboarding_guide = $guide;
        $onboarding_data = [
            'current_step' => $guide->getCurrentStep(),
            'percent'      => $guide->getProgressPercent(),
            'progress'     => $guide->getProgress(),
            'step_label'   => OnboardingGuide::getStepLabel($guide->getCurrentStep() ?? ''),
            'step_desc'    => OnboardingGuide::getStepDescription($guide->getCurrentStep() ?? ''),
            'step_page'    => OnboardingGuide::getStepPage($guide->getCurrentStep() ?? ''),
        ];

        // Check if we should show full-screen onboarding video
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page === 'dashboard.php' && empty($_SESSION['onboarding_video_dismissed'])) {
            try {
                $vid_stmt = $pdo->prepare("
                    SELECT * FROM tutorials
                    WHERE status = 1 AND video_id != ''
                    AND (
                        category IN ('Setup', 'System Setup')
                        OR page = 'onboarding'
                        OR FIND_IN_SET('onboarding', REPLACE(page, ' ', ''))
                    )
                    ORDER BY
                        CASE WHEN page = 'onboarding' THEN 0 ELSE 1 END,
                        sort_order ASC,
                        id ASC
                ");
                $vid_stmt->execute();
                $user_role = $user['role'] ?? '';
                while ($vid_row = $vid_stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (empty($vid_row['video_id'])) {
                        continue;
                    }
                    $roles = json_decode($vid_row['roles'], true);
                    if (!is_array($roles)) {
                        $roles = ['all'];
                    }
                    if (in_array('all', $roles, true) || in_array($user_role, $roles, true)) {
                        $onboarding_video = $vid_row;
                        break;
                    }
                }
            } catch (PDOException $e) {
                error_log('Onboarding video query error: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('renderHeaderNotifications')) {
    function renderHeaderNotifications(array $notifications, string $status): void {
        if (empty($notifications)) {
            $empty_key = $status === 'unread' ? 'no_unread_notifications_available' : 'no_read_notifications_for_selected_date';
            echo '<div class="app-notification-menu__empty"><i class="feather icon-bell-off"></i><div>' . h(__($empty_key)) . '</div></div>';
            return;
        }

        $by_date = [];
        foreach ($notifications as $row) {
            $date = date('Y-m-d', strtotime($row['created_at']));
            $by_date[$date][] = $row;
        }

        foreach ($by_date as $date => $rows) {
            $label = ($date === date('Y-m-d')) ? __('today') : (($date === date('Y-m-d', strtotime('-1 day'))) ? __('yesterday') : date('l, F j, Y', strtotime($date)));
            echo '<div class="tl-date-group"><div class="tl-date-hdr">' . h($label) . '</div>';

            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $raw_msg = (string) ($row['message'] ?? '');
                $receipt_value = '';
                if (preg_match('/Receipt:\s*([^\.\|]+)/i', $raw_msg, $receipt_match)) {
                    $receipt_value = trim($receipt_match[1]);
                }
                $display_msg = trim(preg_replace('/\s*Receipt:\s*[^\.\|]+\.?/i', '', $raw_msg));
                $message = h($display_msg !== '' ? $display_msg : $raw_msg);
                $name = h($row['related_name'] ?? '');
                $amount = $row['transaction_amount'] ?? 0;
                $currency = h($row['transaction_currency'] ?? '');
                $type = h($row['transaction_type'] ?? '');
                $umrah_transaction_to = (string) ($row['umrah_transaction_to'] ?? '');
                $time = date('g:i A', strtotime($row['created_at']));
                $symbol = ($currency === 'USD') ? '$' : (($currency === 'AFS') ? '؋' : (($currency === 'EUR') ? '€' : ''));
                $dot_class = 'tld-default';
                $icon = 'fa-bell';
                $icon_prefix = 'fas';

                switch ((string) ($row['transaction_type'] ?? '')) {
                    case 'visa': $dot_class = 'tld-visa'; $icon = 'fa-passport'; break;
                    case 'supplier': $dot_class = 'tld-supplier'; $icon = 'fa-truck'; break;
                    case 'umrah': $dot_class = 'tld-umrah'; $icon = 'icon-map-pin'; $icon_prefix = 'feather'; break;
                    case 'ticket': $dot_class = 'tld-ticket'; $icon = 'fa-ticket-alt'; break;
                    case 'refund': $dot_class = 'tld-refund'; $icon = 'fa-undo-alt'; break;
                    case 'expense':
                    case 'expense_update':
                    case 'expense_delete': $dot_class = 'tld-expense'; $icon = 'fa-receipt'; break;
                    case 'hotel': $dot_class = 'tld-hotel'; $icon = 'fa-hotel'; break;
                    case 'deposit_sarafi':
                    case 'hawala_sarafi':
                    case 'withdrawal_sarafi': $dot_class = 'tld-sarafi'; $icon = 'fa-exchange-alt'; break;
                    case 'cash_settlement': $dot_class = 'tld-settle'; $icon = 'fa-hand-holding-usd'; break;
                }

                $is_deleted = stripos($raw_msg, 'deleted') !== false;
                $has_receipt = $receipt_value !== '';
                $is_bank_transaction = ($row['transaction_type'] ?? '') === 'umrah' && $umrah_transaction_to === 'Bank';
                $read_only = in_array(($row['transaction_type'] ?? ''), ['deposit_sarafi','hawala_sarafi','withdrawal_sarafi','supplier_fund','client_fund','expense','expense_update','expense_delete','refund','ticket_refund','cash_settlement'], true) || $is_deleted || $is_bank_transaction || $has_receipt;

                echo '<div class="tl-item notification-' . h($status) . '" data-id="' . $id . '">';
                echo '<div class="tl-dot ' . h($dot_class) . ($status === 'unread' ? ' unread' : '') . '"><i class="' . h($icon_prefix . ' ' . $icon) . '"></i></div>';
                echo '<div class="tl-body">';
                echo '<div class="tl-top"><span class="tl-type">' . $type . '</span><span class="tl-time">' . h($time) . '</span></div>';
                echo '<div class="tl-msg">' . $message . '</div>';

                if ($name || $amount || $has_receipt) {
                    echo '<div class="tl-meta">';
                    if ($name) echo '<span class="tl-chip"><i class="fas fa-user"></i>' . $name . '</span>';
                    if ($amount && !$has_receipt) echo '<span class="tl-chip"><i class="fas fa-credit-card"></i>' . h($symbol . number_format((float) $amount, 2)) . '</span>';
                    if ($has_receipt) echo '<span class="tl-chip"><i class="fas fa-receipt"></i>' . h($receipt_value) . '</span>';
                    echo '</div>';
                }

                if ($status === 'unread') {
                    echo '<div class="tl-actions">';
                    if (!$read_only) {
                        echo '<button class="tl-btn tl-btn-receive approve-button" data-id="' . $id . '" data-amount="' . h((string) $amount) . '" data-currency="' . $currency . '" data-type="' . $type . '"><i class="fas fa-check"></i>' . h(__('received')) . '</button>';
                    }
                    echo '<button class="tl-btn tl-btn-read read-button" data-id="' . $id . '"><i class="fas fa-eye"></i>' . h(__('mark_as_read')) . '</button>';
                    echo '</div>';
                }

                echo '</div></div>';
            }

            echo '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= get_current_lang() ?>" dir="<?= get_lang_dir() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <title><?= h($settings['agency_name'] ?? 'Dashboard') ?></title>

    <!-- Favicon -->
    <link rel="icon" href="../uploads/logo/<?= h($settings['logo'] ?? '') ?>" type="image/x-icon">

    <!-- Vendor CSS -->
    <link rel="stylesheet" href="../assets/fonts/fontawesome/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="../assets/plugins/animation/css/animate.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Header / sidebar / RTL styles (external file – browser-cached) -->
    <link rel="stylesheet" href="../assets/css/header-styles.css">

    <!-- Dashboard styles (badges, cards, layout) -->
    <link rel="stylesheet" href="../css/dashboard/dashboard-styles.css">
    <style>
    :root {
        --app-gradient: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        --app-gradient-soft: linear-gradient(135deg, rgba(64,153,255,.12) 0%, rgba(46,216,182,.12) 100%);
        --app-header-height: 64px;
        --app-sidebar-width: 260px;
        --app-sidebar-collapsed-width: 68px;
        --app-bg-page: #f0f4f8;
        --app-bg-sidebar: #0f1b2d;
        --app-bg-header: #ffffff;
        --app-text-primary: #1a2332;
        --app-text-secondary: #6b7a90;
        --app-text-sidebar: #a8b8cc;
        --app-border: #e4eaf2;
        --app-shadow-sm: 0 1px 4px rgba(0,0,0,.06);
        --app-shadow-lg: 0 8px 32px rgba(64,153,255,.18);
        --app-radius-sm: 6px;
        --app-transition: .28s cubic-bezier(.4,0,.2,1);
    }

    body { background: var(--app-bg-page) !important; overflow-x: hidden; }

    .app-shell-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(10,20,40,.45);
        backdrop-filter: blur(2px);
        z-index: 1030;
        opacity: 0;
        transition: opacity var(--app-transition);
    }
    .app-shell-overlay.active { display: block; opacity: 1; }

    .app-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: var(--app-header-height);
        background: var(--app-bg-header);
        border-bottom: 1px solid var(--app-border);
        box-shadow: var(--app-shadow-sm);
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0 1.5rem;
        z-index: 1020;
    }

    .app-header__toggle {
        width: 38px;
        height: 38px;
        border-radius: var(--app-radius-sm);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 5px;
        background: transparent;
        border: 0;
        cursor: pointer;
        transition: background var(--app-transition);
        flex-shrink: 0;
    }
    .app-header__toggle:hover { background: var(--app-gradient-soft); }
    .app-header__toggle span {
        display: block;
        width: 20px;
        height: 2px;
        background: var(--app-text-secondary);
        border-radius: 2px;
        transition: var(--app-transition);
    }
    body.sidebar-open .app-header__toggle span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
    body.sidebar-open .app-header__toggle span:nth-child(2) { opacity: 0; }
    body.sidebar-open .app-header__toggle span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

    .app-header__brand {
        display: flex;
        align-items: center;
        gap: .625rem;
        min-width: 0;
        flex-shrink: 0;
        color: var(--app-text-primary);
    }
    .app-header__brand-logo,
    .pcoded-navbar .b-bg {
        width: 36px !important;
        height: 36px !important;
        border-radius: var(--app-radius-sm) !important;
        background: var(--app-gradient) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: var(--app-shadow-lg) !important;
        overflow: hidden;
        flex-shrink: 0;
    }
    .app-header__brand-logo img,
    .pcoded-navbar .b-bg img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover;
    }
    .app-header__brand-name,
    .pcoded-navbar .b-title {
        font-size: 1.05rem !important;
        font-weight: 700 !important;
        background: var(--app-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .app-header__spacer { flex: 1; min-width: 1rem; }
    .app-header__search { flex: 1; max-width: 400px; position: relative; }
    .app-header__search i {
        position: absolute;
        left: .85rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--app-text-secondary);
        pointer-events: none;
    }
    .app-header__search input {
        width: 100%;
        height: 38px;
        padding: 0 1rem 0 2.5rem;
        border: 1.5px solid var(--app-border);
        border-radius: 999px;
        background: var(--app-bg-page);
        font-size: .875rem;
        color: var(--app-text-primary);
        outline: none;
    }

    .app-header__actions { margin-left: auto; display: flex; align-items: center; gap: .625rem; }
    .app-header__icon-btn {
        width: 38px;
        height: 38px;
        border-radius: var(--app-radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--app-text-secondary);
        background: transparent;
        border: 0;
        position: relative;
    }
    .app-header__icon-btn:hover { background: var(--app-gradient-soft); color: #4099ff; }
    .app-header__badge {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 8px;
        height: 8px;
        background: #ff4d6d;
        border-radius: 50%;
        border: 2px solid #fff;
    }
    .app-header__avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--app-gradient);
        box-shadow: var(--app-shadow-lg);
        flex-shrink: 0;
    }
    .app-header__avatar img { width: 100%; height: 100%; object-fit: cover; }

    .app-header__notification {
        position: relative;
    }

    .app-header__notification-count {
        position: absolute;
        top: -5px;
        right: -5px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        background: #ef4444;
        color: #fff;
        border: 2px solid #fff;
        font-size: 10px;
        font-weight: 700;
        line-height: 14px;
        text-align: center;
    }

    .app-notification-menu {
        position: absolute;
        top: calc(100% + 12px);
        right: 0;
        width: min(560px, calc(100vw - 24px));
        max-height: 620px;
        background: #fff;
        border: 1px solid var(--app-border);
        border-radius: 10px;
        box-shadow: 0 18px 50px rgba(15, 27, 45, .18);
        overflow: hidden;
        display: none;
        z-index: 1100;
    }

    .app-notification-menu.open {
        display: block;
    }

    .app-notification-menu__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px;
        border-bottom: 1px solid var(--app-border);
    }

    .app-notification-menu__title {
        font-size: 13px;
        font-weight: 700;
        color: var(--app-text-primary);
    }

    .app-notification-menu__list {
        max-height: 480px;
        overflow-y: auto;
    }

    .app-notification-tabs {
        display: flex;
        gap: 8px;
        padding: 10px 14px;
        border-bottom: 1px solid var(--app-border);
    }

    .app-notification-tabs .notif-tab-btn {
        border: 0;
        border-radius: 999px;
        padding: 7px 12px;
        background: #f1f5f9;
        color: var(--app-text-secondary);
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .app-notification-tabs .notif-tab-btn.active {
        background: var(--app-gradient);
        color: #fff;
    }

    .app-notification-tabs .nbadge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        border-radius: 999px;
        background: rgba(255,255,255,.24);
        color: inherit;
        padding: 0 6px;
        margin-left: 4px;
        font-size: 11px;
    }

    .app-notification-menu .read-filter {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        background: #f8fafc;
        border-bottom: 1px solid var(--app-border);
    }

    .app-notification-menu .read-filter label {
        margin: 0;
        color: var(--app-text-secondary);
        font-size: 12px;
        font-weight: 700;
    }

    .app-notification-menu .dep-date-input {
        height: 32px;
        border: 1px solid var(--app-border);
        border-radius: 8px;
        padding: 0 8px;
        font-size: 12px;
    }

    .app-notification-menu .dbtn {
        height: 32px;
        border: 0;
        border-radius: 8px;
        padding: 0 10px;
        background: var(--app-gradient);
        color: #fff;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .app-notification-menu .tl-date-hdr {
        padding: 10px 14px 6px;
        color: var(--app-text-secondary);
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
        background: #f8fafc;
    }

    .app-notification-menu .tl-item {
        display: flex;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid #eef2f7;
    }

    .app-notification-menu .tl-dot {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        background: var(--app-gradient-soft);
        color: #4099ff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 32px;
    }

    .app-notification-menu .tl-dot.unread {
        box-shadow: inset 0 0 0 1px rgba(64,153,255,.28);
    }

    .app-notification-menu .tl-body {
        flex: 1;
        min-width: 0;
    }

    .app-notification-menu .tl-top {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 4px;
    }

    .app-notification-menu .tl-type {
        color: #4099ff;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .app-notification-menu .tl-time {
        color: var(--app-text-secondary);
        font-size: 11px;
        white-space: nowrap;
    }

    .app-notification-menu .tl-msg {
        color: var(--app-text-primary);
        font-size: 12px;
        line-height: 1.45;
    }

    .app-notification-menu .tl-meta {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 7px;
    }

    .app-notification-menu .tl-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        background: #f1f5f9;
        color: var(--app-text-secondary);
        padding: 3px 7px;
        font-size: 11px;
        font-weight: 600;
    }

    .app-notification-menu .tl-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .app-notification-menu .tl-btn {
        border: 0;
        border-radius: 7px;
        padding: 5px 8px;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
    }

    .app-notification-menu .tl-btn i {
        margin-right: 4px;
    }

    .app-notification-menu .tl-btn-read {
        background: #4099ff;
    }

    .app-notification-menu .tl-btn-receive {
        background: #10b981;
    }

    .app-notification-item {
        display: flex;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid #eef2f7;
    }

    .app-notification-item__icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: var(--app-gradient-soft);
        color: #4099ff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 30px;
    }

    .app-notification-item__body {
        min-width: 0;
        flex: 1;
    }

    .app-notification-item__msg {
        color: var(--app-text-primary);
        font-size: 12px;
        line-height: 1.45;
    }

    .app-notification-item__meta {
        margin-top: 4px;
        color: var(--app-text-secondary);
        font-size: 11px;
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }

    .app-notification-menu__empty {
        padding: 28px 16px;
        text-align: center;
        color: var(--app-text-secondary);
        font-size: 13px;
    }

    .app-notification-menu__foot {
        padding: 10px 14px;
        border-top: 1px solid var(--app-border);
        font-size: 12px;
        color: #4099ff;
        font-weight: 600;
        text-align: center;
    }

    .pcoded-navbar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: var(--app-sidebar-width) !important;
        height: 100vh !important;
        background: var(--app-bg-sidebar) !important;
        z-index: 1040 !important;
        display: flex !important;
        flex-direction: column !important;
        transform: translateX(0) !important;
        transition: transform var(--app-transition), width var(--app-transition) !important;
        overflow: hidden !important;
        box-shadow: none !important;
    }
    .pcoded-navbar .navbar-wrapper { display: flex; flex-direction: column; width: 100%; height: 100%; position: relative; z-index: 1; }
    .pcoded-navbar .navbar-brand.header-logo {
        height: var(--app-header-height);
        display: flex !important;
        align-items: center !important;
        padding: 0 1.5rem !important;
        margin: 0 !important;
        gap: .625rem !important;
        border-bottom: 1px solid rgba(255,255,255,.06) !important;
        background: transparent !important;
        border-radius: 0 !important;
        flex-shrink: 0;
    }
    .pcoded-navbar .b-brand { display: flex !important; align-items: center !important; gap: .625rem !important; min-width: 0; }
    .pcoded-navbar .language-selector { margin-left: auto; padding: 0 !important; }
    .pcoded-navbar .language-selector select {
        height: 28px;
        border-radius: 999px !important;
        padding: 0 8px !important;
        font-size: 11px !important;
    }
    .pcoded-navbar .navbar-content {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem .625rem 6.5rem !important;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,255,255,.12) transparent;
    }

    .pcoded-navbar .pcoded-inner-navbar,
    .pcoded-navbar .pcoded-inner-navbar > li { width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .pcoded-navbar .pcoded-menu-caption { padding: 1rem .625rem .375rem !important; margin: 0 !important; }
    .pcoded-navbar .pcoded-menu-caption label {
        font-size: .67rem !important;
        font-weight: 600 !important;
        letter-spacing: .1em !important;
        text-transform: uppercase !important;
        color: rgba(168,184,204,.45) !important;
        margin: 0 !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link,
    html[dir="ltr"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link,
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link {
        display: flex !important;
        align-items: center !important;
        gap: .625rem !important;
        width: 100% !important;
        min-width: 0 !important;
        padding: .62rem .625rem !important;
        margin: .125rem 0 !important;
        box-sizing: border-box !important;
        color: var(--app-text-sidebar) !important;
        background: transparent !important;
        transform: none !important;
        box-shadow: none !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link:hover {
        background: rgba(255,255,255,.07) !important;
        color: #fff !important;
        padding: .62rem .625rem !important;
        transform: none !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li.active > a.nav-link {
        background: var(--app-gradient) !important;
        color: #fff !important;
        box-shadow: 0 4px 14px rgba(64,153,255,.35) !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-micon,
    html[dir="ltr"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-micon,
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-micon {
        position: static !important;
        order: 0 !important;
        flex: 0 0 22px !important;
        width: 22px !important;
        min-width: 22px !important;
        max-width: 22px !important;
        height: 22px !important;
        margin: 0 !important;
        padding: 0 !important;
        transform: none !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: inherit !important;
    }
    .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext,
    html[dir="ltr"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext,
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext {
        position: static !important;
        order: 0 !important;
        flex: 1 1 auto !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
        color: inherit !important;
    }

    .pcoded-navbar .user-profile-section {
        position: absolute !important;
        bottom: 0 !important;
        left: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: .75rem !important;
        border-top: 1px solid rgba(255,255,255,.06) !important;
        background: var(--app-bg-sidebar) !important;
        border-radius: 0 !important;
    }
    .pcoded-main-container {
        margin-left: var(--app-sidebar-width) !important;
        margin-right: 0 !important;
        padding-top: var(--app-header-height) !important;
        min-height: 100vh;
        transition: margin-left var(--app-transition), margin-right var(--app-transition) !important;
    }

    body.sidebar-collapsed .pcoded-navbar { width: var(--app-sidebar-collapsed-width) !important; }
    body.sidebar-collapsed .pcoded-main-container { margin-left: var(--app-sidebar-collapsed-width) !important; }
    body.sidebar-collapsed .pcoded-navbar .b-title,
    body.sidebar-collapsed .pcoded-navbar .language-selector,
    body.sidebar-collapsed .pcoded-navbar .pcoded-menu-caption label,
    body.sidebar-collapsed .pcoded-navbar .pcoded-mtext,
    body.sidebar-collapsed .pcoded-navbar .user-profile-section div div:not(:first-child),
    body.sidebar-collapsed .pcoded-navbar .user-profile-section a.nav-link { display: none !important; }
    body.sidebar-collapsed .pcoded-navbar .navbar-brand.header-logo,
    body.sidebar-collapsed .pcoded-navbar li a.nav-link {
        justify-content: center !important;
        padding-left: .5rem !important;
        padding-right: .5rem !important;
    }

    html[dir="rtl"] .app-header__search i { left: auto; right: .85rem; }
    html[dir="rtl"] .app-header__search input { padding: 0 2.5rem 0 1rem; }
    html[dir="rtl"] .app-header__actions { margin-left: 0; margin-right: auto; }
    html[dir="rtl"] .app-notification-menu { right: auto; left: 0; }
    html[dir="rtl"] .app-header__notification-count { right: auto; left: -5px; }
    html[dir="rtl"] .pcoded-navbar { right: 0 !important; left: auto !important; }
    html[dir="rtl"] .pcoded-main-container { margin-right: var(--app-sidebar-width) !important; margin-left: 0 !important; }
    html[dir="rtl"] body.sidebar-collapsed .pcoded-main-container { margin-right: var(--app-sidebar-collapsed-width) !important; margin-left: 0 !important; }
    html[dir="rtl"] .pcoded-navbar .language-selector { margin-left: 0; margin-right: auto; }
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link { flex-direction: row-reverse !important; }
    html[dir="rtl"] .pcoded-navbar .pcoded-inner-navbar li > a.nav-link .pcoded-mtext { text-align: right !important; }

    @media (min-width: 1024px) {
        .app-header {
            left: var(--app-sidebar-width);
            transition: left var(--app-transition), right var(--app-transition);
        }
        .app-header__brand { display: none !important; }
        body.sidebar-collapsed .app-header { left: var(--app-sidebar-collapsed-width); }
        body.sidebar-collapsed .pcoded-navbar:hover { width: var(--app-sidebar-width) !important; }
        body.sidebar-collapsed .pcoded-navbar:hover .navbar-brand.header-logo,
        body.sidebar-collapsed .pcoded-navbar:hover li a.nav-link {
            justify-content: flex-start !important;
            padding-left: .625rem !important;
            padding-right: .625rem !important;
        }
        body.sidebar-collapsed .pcoded-navbar:hover .b-title,
        body.sidebar-collapsed .pcoded-navbar:hover .language-selector,
        body.sidebar-collapsed .pcoded-navbar:hover .pcoded-menu-caption label,
        body.sidebar-collapsed .pcoded-navbar:hover .pcoded-mtext { display: inline-block !important; }
        body.sidebar-collapsed .pcoded-navbar:hover .user-profile-section div div:not(:first-child) { display: block !important; }
        body.sidebar-collapsed .pcoded-navbar:hover .user-profile-section a.nav-link { display: flex !important; }
        html[dir="rtl"] .app-header { left: 0; right: var(--app-sidebar-width); }
        html[dir="rtl"] body.sidebar-collapsed .app-header { right: var(--app-sidebar-collapsed-width); }
    }

    @media (max-width: 1023px) {
        .app-header { left: 0; right: 0; padding: 0 1rem; }
        .app-header__brand-name { max-width: 180px; }
        .pcoded-navbar {
            transform: translateX(-100%) !important;
            width: var(--app-sidebar-width) !important;
            max-width: 85vw;
        }
        body.sidebar-open .pcoded-navbar { transform: translateX(0) !important; }
        html[dir="rtl"] .pcoded-navbar { transform: translateX(100%) !important; }
        html[dir="rtl"] body.sidebar-open .pcoded-navbar { transform: translateX(0) !important; }
        .pcoded-main-container,
        html[dir="rtl"] .pcoded-main-container,
        body.sidebar-collapsed .pcoded-main-container,
        html[dir="rtl"] body.sidebar-collapsed .pcoded-main-container {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }
        .mobile-menu-float { display: none !important; }
    }

    @media (max-width: 639px) {
        .app-header__search { display: none; }
        .app-header__brand-name { max-width: 130px; }
        .app-header__actions { gap: .25rem; }
        .app-header__icon-btn { width: 34px; height: 34px; }
    }
    </style>
</head>
<body>

<?php include __DIR__ . '/impersonation_banner.php'; ?>

<!-- [ Pre-loader ] start -->
<div class="loader-bg">
    <div class="loader-track">
        <div class="loader-fill"></div>
    </div>
</div>
<!-- [ Pre-loader ] End -->

<?php
// Check if tenant is in trial mode and show banner
if (isset($tenant_id) && $tenant_id) {
    try {
        $trial_stmt = $pdo->prepare("SELECT status, trial_days, trial_end_date FROM tenants WHERE id = ?");
        $trial_stmt->execute([$tenant_id]);
        $trial_info = $trial_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($trial_info && $trial_info['status'] === 'trial' && !empty($trial_info['trial_end_date'])) {
            $trial_end = $trial_info['trial_end_date'];
            $today = date('Y-m-d');
            $days_left = max(0, (int)((strtotime($trial_end) - strtotime($today)) / 86400));
            $is_expired = $trial_end < $today;
            $urgency_class = $is_expired ? 'trial-banner-expired' : ($days_left <= 3 ? 'trial-banner-urgent' : 'trial-banner-active');
?>
<!-- Trial Period Banner -->
<div class="trial-banner <?= $urgency_class ?>" id="trialBanner">
    <div class="trial-banner-content">
        <div class="trial-banner-icon">
            <i class="feather icon-clock"></i>
        </div>
        <div class="trial-banner-text">
            <?php if ($is_expired): ?>
                <strong>Trial Expired!</strong> Your trial period ended on <?= date('M d, Y', strtotime($trial_end)) ?>. Please contact your administrator to activate the subscription.
            <?php else: ?>
                <strong>Trial Period:</strong> You have <strong><?= $days_left ?> day<?= $days_left !== 1 ? 's' : '' ?></strong> remaining in your free trial. Trial ends on <strong><?= date('M d, Y', strtotime($trial_end)) ?></strong>.
            <?php endif; ?>
        </div>
        <button class="trial-banner-close" onclick="closeTrialBanner();">&times;</button>
    </div>
</div>
<script>
function closeTrialBanner() {
    var banner = document.getElementById('trialBanner');
    if (banner) banner.style.display = 'none';
    document.body.classList.remove('has-trial-banner');
    sessionStorage.setItem('trialBannerClosed', 'true');
}
if (sessionStorage.getItem('trialBannerClosed') === 'true') {
    closeTrialBanner();
} else {
    document.body.classList.add('has-trial-banner');
}
</script>
<style>
.trial-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    padding: 0;
    font-family: 'Inter', system-ui, sans-serif;
}
.trial-banner-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 10px 20px;
    font-size: 0.9rem;
    color: #fff;
}
.trial-banner-active .trial-banner-content {
    background: linear-gradient(135deg, #3b82f6, #2ed8b6);
}
.trial-banner-urgent .trial-banner-content {
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    animation: trial-pulse 2s ease-in-out infinite;
}
.trial-banner-expired .trial-banner-content {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}
.trial-banner-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
}
.trial-banner-text {
    flex: 1;
    text-align: center;
}
.trial-banner-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    font-size: 1.3rem;
    cursor: pointer;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    line-height: 1;
}
.trial-banner-close:hover {
    background: rgba(255,255,255,0.4);
}
@keyframes trial-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.85; }
}
body.has-trial-banner .app-header {
    top: 48px;
}
</style>
<?php
        }
    } catch (Exception $e) {
        // Silently ignore
    }
}

// ── Payment overdue/warning banner ────────────────────────────────
if (isset($tenant_id) && $tenant_id) {
    try {
        $pay_stmt = $pdo->prepare("SELECT payment_status, payment_due_date, next_billing_date FROM tenants WHERE id = ?");
        $pay_stmt->execute([$tenant_id]);
        $pay_info = $pay_stmt->fetch(PDO::FETCH_ASSOC);

        if ($pay_info && in_array($pay_info['payment_status'], ['warning', 'overdue'])) {
            $pay_status = $pay_info['payment_status'];
            $pay_due = $pay_info['payment_due_date'] ?: $pay_info['next_billing_date'];
            $pay_urgency = $pay_status === 'overdue' ? 'pay-banner-overdue' : 'pay-banner-warning';
?>
<!-- Payment Reminder Banner -->
<div class="pay-banner <?= $pay_urgency ?>" id="payBanner">
    <div class="pay-banner-content">
        <div class="pay-banner-icon">
            <i class="feather icon-credit-card"></i>
        </div>
        <div class="pay-banner-text">
            <?php if ($pay_status === 'overdue'): ?>
                <strong>Payment Overdue!</strong> Your subscription payment is past due<?= $pay_due ? ' (due: ' . date('M d, Y', strtotime($pay_due)) . ')' : '' ?>. Please contact your administrator to settle the balance.
            <?php else: ?>
                <strong>Payment Due Soon:</strong> Your subscription payment is due <strong><?= $pay_due ? date('M d, Y', strtotime($pay_due)) : 'soon' ?></strong>. Please arrange payment to avoid service interruption.
            <?php endif; ?>
        </div>
        <button class="pay-banner-close" onclick="closePayBanner();">&times;</button>
    </div>
</div>
<script>
function closePayBanner() {
    var banner = document.getElementById('payBanner');
    if (banner) banner.style.display = 'none';
    document.body.classList.remove('has-pay-banner');
    sessionStorage.setItem('payBannerClosed', 'true');
}
if (sessionStorage.getItem('payBannerClosed') === 'true') {
    closePayBanner();
} else {
    document.body.classList.add('has-pay-banner');
}
</script>
<style>
.pay-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9998;
    padding: 0;
    font-family: 'Inter', system-ui, sans-serif;
}
.pay-banner-content {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 10px 20px;
    font-size: 0.9rem;
    color: #fff;
}
.pay-banner-warning .pay-banner-content {
    background: linear-gradient(135deg, #f59e0b, #eab308);
}
.pay-banner-overdue .pay-banner-content {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}
.pay-banner-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
}
.pay-banner-text {
    flex: 1;
    text-align: center;
}
.pay-banner-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    font-size: 1.3rem;
    cursor: pointer;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    line-height: 1;
}
.pay-banner-close:hover {
    background: rgba(255,255,255,0.4);
}
body.has-pay-banner .app-header {
    top: 48px;
}
</style>
<?php
        }
    } catch (Exception $e) {
        // Silently ignore
    }
}
?>
<style>
.app-header__help { position: relative; }
.app-help-menu {
    position: absolute; top: calc(100% + 8px); right: 0; width: 360px; max-height: 420px;
    background: #fff; border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,.15);
    display: none; flex-direction: column; overflow: hidden; z-index: 1100;
}
.app-help-menu.open { display: flex; }
.app-help-menu__head {
    padding: 14px 16px; font-weight: 600; font-size: .9rem; color: var(--app-text-primary);
    border-bottom: 1px solid var(--app-border); display: flex; align-items: center;
}
.app-help-menu__subhead {
    padding: 8px 16px; font-size: .8rem; color: var(--app-text-secondary);
    background: #f8f9fa; border-bottom: 1px solid var(--app-border);
}
.app-help-menu__list {
    flex: 1; overflow-y: auto; padding: 8px;
}
.app-help-menu__empty {
    text-align: center; padding: 24px 16px; color: var(--app-text-secondary); font-size: .85rem;
}
.app-help-menu__item {
    display: flex; gap: 12px; padding: 10px 12px; border-radius: 8px; cursor: pointer;
    transition: background .15s; border: 1px solid transparent; margin-bottom: 4px;
}
.app-help-menu__item:hover { background: var(--app-gradient-soft); border-color: #4099ff; }
.app-help-menu__item-icon {
    width: 40px; height: 40px; border-radius: 8px; background: var(--app-gradient-soft);
    display: flex; align-items: center; justify-content: center; color: #4099ff; flex-shrink: 0;
}
.app-help-menu__item-body { flex: 1; min-width: 0; }
.app-help-menu__item-title { font-weight: 600; font-size: .85rem; color: var(--app-text-primary); margin-bottom: 2px; }
.app-help-menu__item-desc { font-size: .78rem; color: var(--app-text-secondary); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.app-help-menu__item-chapters { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.app-help-menu__chapter {
    font-size: .7rem; padding: 2px 8px; border-radius: 4px; background: #e8f0fe; color: #1967d2;
    font-family: monospace; white-space: nowrap;
}
.app-help-menu__item-play {
    font-size: .75rem; color: #4099ff; display: flex; align-items: center; gap: 4px; margin-top: 4px; font-weight: 500;
}
.app-help-menu__foot {
    padding: 10px 16px; border-top: 1px solid var(--app-border); text-align: center;
}
.app-help-menu__foot a { font-size: .82rem; color: #4099ff; font-weight: 500; text-decoration: none; }
.app-help-menu__foot a:hover { text-decoration: underline; }
</style>

<div class="app-shell-overlay" id="appShellOverlay"></div>

<header class="app-header" role="banner">
    <button class="app-header__toggle" id="mobile-collapse" type="button" aria-label="Toggle sidebar" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>

    <a href="dashboard.php" class="app-header__brand">
        <span class="app-header__brand-logo">
            <img src="../uploads/logo/<?= h($settings['logo'] ?? '') ?>" alt="<?= h($settings['agency_name'] ?? '') ?>">
        </span>
        <span class="app-header__brand-name"><?= h($settings['agency_name'] ?? '') ?></span>
    </a>

    <div class="app-header__spacer"></div>

    <div class="app-header__actions">
        <?php if (($user['role'] ?? '') === 'admin'): ?>
        <div class="app-header__notification">
            <button class="app-header__icon-btn" type="button" id="appNotificationToggle" aria-label="Notifications" aria-expanded="false">
                <i class="feather icon-bell"></i>
                <?php if ($header_unread_count > 0): ?>
                <span class="app-header__notification-count" id="headerNotifBadge"><?= $header_unread_count > 99 ? '99+' : $header_unread_count ?></span>
                <?php endif; ?>
            </button>
            <div class="app-notification-menu" id="appNotificationMenu" aria-hidden="true">
                <div class="app-notification-menu__head">
                    <div class="app-notification-menu__title"><?= h(__('recent_notifications')) ?></div>
                    <span class="app-notification-menu__title"><?= (int) $header_unread_count ?></span>
                </div>
                <div class="notif-tabs-row app-notification-tabs">
                    <button class="notif-tab-btn active" type="button" onclick="switchNotifTab(this,'ntab-unread')">
                        <?= h(__('unread')) ?> <span class="nbadge" id="unreadNotifCount"><?= (int) $header_unread_count ?></span>
                    </button>
                    <button class="notif-tab-btn" type="button" onclick="switchNotifTab(this,'ntab-read')"><?= h(__('read')) ?></button>
                </div>
                <div class="app-notification-menu__list" id="notificationsContent">
                    <div id="ntab-unread">
                        <?php renderHeaderNotifications($header_unread_notifications, 'unread'); ?>
                    </div>
                    <div id="ntab-read" style="display:none;">
                        <div class="read-filter">
                            <label><?= h(__('filter')) ?>:</label>
                            <input type="date" class="dep-date-input" id="readNotificationsDate" value="<?= date('Y-m-d') ?>">
                            <button class="dbtn dbtn-ghost" id="applyReadDateFilter" type="button">
                                <i class="fas fa-filter"></i> <?= h(__('filter')) ?>
                            </button>
                        </div>
                        <div id="readNotificationsBody">
                            <?php renderHeaderNotifications($header_read_notifications, 'read'); ?>
                        </div>
                    </div>
                </div>
                <div class="app-notification-menu__foot"><?= h(__('recent_notifications')) ?></div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
        <a class="app-header__icon-btn" href="../chat.php" aria-label="Messages">
            <i class="feather icon-message-circle"></i>
            <span class="app-header__badge"></span>
        </a>
        <?php endif; ?>
        <div class="app-header__help" id="pageHelpContainer">
            <button class="app-header__icon-btn" type="button" id="pageHelpToggle" aria-label="Page tutorials" aria-expanded="false">
                <i class="feather icon-help-circle"></i>
            </button>
            <div class="app-help-menu" id="pageHelpMenu" aria-hidden="true">
                <div class="app-help-menu__head">
                    <i class="feather icon-book mr-2"></i>
                    <span><?= h(__('tutorials')) ?></span>
                </div>
                <div class="app-help-menu__subhead" id="pageHelpSubhead">Loading...</div>
                <div class="app-help-menu__list" id="pageHelpList">
                    <div class="app-help-menu__empty">No tutorials for this page</div>
                </div>
                <div class="app-help-menu__foot">
                    <a href="tutorial.php"><?= h(__('view_all_tutorials')) ?> <i class="feather icon-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <a class="app-header__avatar" href="profile.php" aria-label="Profile">
            <img src="<?= $imagePath ?>" alt="<?= h($user['name'] ?? 'User') ?>">
        </a>
    </div>
</header>

<!-- [ navigation menu ] start -->
<aside class="pcoded-navbar" id="sidebar" role="navigation" aria-label="Main navigation">
    <div class="navbar-wrapper">

        <!-- Brand / logo -->
        <div class="navbar-brand header-logo">
            <a href="dashboard.php" class="b-brand">
                <div class="b-bg">
                    <img class="rounded-circle" style="width:40px;"
                         src="../uploads/logo/<?= h($settings['logo'] ?? '') ?>"
                         alt="<?= h($settings['agency_name'] ?? '') ?>">
                </div>
                <span class="b-title"><?= h($settings['agency_name'] ?? '') ?></span>
            </a>

            <!-- Language switcher -->
            <div class="language-selector" style="padding:5px 15px;text-align:center;">
                <select onchange="window.location.href='../language_switcher.php?lang='+this.value"
                        style="background:linear-gradient(135deg,#4099ff 0%,#2ed8b6 100%);color:#fff;border:none;border-radius:4px;padding:2px 5px;font-size:11px;cursor:pointer;">
                    <option value="en" <?= get_current_lang() === 'en' ? 'selected' : '' ?> style="background:#4099ff;color:#fff;">EN</option>
                    <option value="fa" <?= get_current_lang() === 'fa' ? 'selected' : '' ?> style="background:#4099ff;color:#fff;">دری</option>
                    <option value="ps" <?= get_current_lang() === 'ps' ? 'selected' : '' ?> style="background:#4099ff;color:#fff;">پښتو</option>
                </select>
            </div>

        </div>
        <!-- /brand -->

        <!-- Sidebar menu -->
        <div class="navbar-content scroll-div" style="padding-bottom:100px;">
            <ul class="nav pcoded-inner-navbar">

                <?php include __DIR__ . '/nav_items.php'; ?>

            </ul>
        </div>
        <!-- /sidebar menu -->

        <!-- Sticky user profile strip at the bottom of the sidebar -->
        <div class="navbar-brand user-profile-section"
             style="position:absolute;bottom:0;width:100%;border-top:1px solid rgba(255,255,255,0.1);background:#4099ff;z-index:10;">
            <div style="padding:8px 15px;display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0;">
                    <a href="profile.php" style="text-decoration:none;flex-shrink:0;">
                        <img class="rounded-circle"
                             style="width:28px;height:28px;cursor:pointer;transition:opacity 0.3s ease;"
                             onmouseover="this.style.opacity='0.8'"
                             onmouseout="this.style.opacity='1'"
                             src="<?= $imagePath ?>"
                             alt="user-avatar">
                    </a>
                    <div style="flex:1;min-width:0;overflow:hidden;">
                        <div style="color:#fff;font-size:11px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;">
                            <?= h($user['name'] ?? 'User') ?>
                        </div>
                        <div style="color:rgba(255,255,255,0.7);font-size:9px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;">
                            <?= h($user['email'] ?? '') ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:1px;flex-shrink:0;">
                    <a href="profile.php" class="nav-link"
                       style="padding:4px;border-radius:3px;color:#fff;transition:all 0.3s ease;"
                       onmouseover="this.style.background='rgba(255,255,255,0.15)'"
                       onmouseout="this.style.background='transparent'">
                        <i class="feather icon-person" style="font-size:12px;"></i>
                    </a>
                    <a href="logout.php" class="nav-link"
                       style="padding:4px;border-radius:3px;color:#fff;transition:all 0.3s ease;"
                       onmouseover="this.style.background='rgba(255,255,255,0.15)'"
                       onmouseout="this.style.background='transparent'">
                        <i class="feather icon-log-out" style="font-size:12px;"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- /user profile strip -->

    </div>
</aside>
<!-- [ navigation menu ] end -->

<?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
<!-- ── Floating Chat Widget ─────────────────────────────────── -->
<div id="alqChatFab" class="alq-chat-fab" title="Chat">
    <i class="feather icon-message-circle"></i>
    <span class="unread-badge" id="alqChatUnreadBadge">0</span>
    <span class="sr-only">Open chat</span>
</div>
<div id="alqChatPanel" class="alq-chat-panel" aria-hidden="true">
    <div class="alq-chat-panel__header">
        <span>Chat</span>
        <div class="alq-chat-panel__actions">
            <button id="alqChatOpenFull" class="alq-chat-btn" title="Open full page">
                <i class="feather icon-external-link"></i>
            </button>
            <button id="alqChatClose" class="alq-chat-btn" title="Close">
                <i class="feather icon-x"></i>
            </button>
        </div>
    </div>
    <iframe id="alqChatFrame" class="alq-chat-iframe"
            src="../chat.php?embed=1" loading="lazy" referrerpolicy="no-referrer"></iframe>
</div>
<?php endif; ?>

<!-- ── Floating Tasks Widget ──────────────────────────────────── -->
<?php include_once 'floating_tasks.php'; ?>
<?php include_once __DIR__ . '/../modals/dashboard/receipt_modal.php'; ?>

<!-- ── Scripts ────────────────────────────────────────────────── -->
<script>
var currentUserId = <?= (int)($user['id'] ?? 0) ?>;
var onLoadTutorialsQueue = [];
var isPlayingOnLoadTutorial = false;
var helpAutoSeekIndex = -1;

document.addEventListener('DOMContentLoaded', function () {
    var shellToggle = document.getElementById('mobile-collapse');
    var shellOverlay = document.getElementById('appShellOverlay');

    function isModernShellDesktop() {
        return window.innerWidth >= 1024;
    }

    function closeModernShellSidebar() {
        document.body.classList.remove('sidebar-open');
        shellOverlay && shellOverlay.classList.remove('active');
        shellToggle && shellToggle.setAttribute('aria-expanded', 'false');
    }

    if (shellToggle) {
        shellToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();

            if (isModernShellDesktop()) {
                document.body.classList.toggle('sidebar-collapsed');
                closeModernShellSidebar();
                return;
            }

            var open = document.body.classList.toggle('sidebar-open');
            shellOverlay && shellOverlay.classList.toggle('active', open);
            shellToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    shellOverlay && shellOverlay.addEventListener('click', closeModernShellSidebar);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModernShellSidebar();
    });
    window.addEventListener('resize', function() {
        if (isModernShellDesktop()) closeModernShellSidebar();
    });

    var notificationToggle = document.getElementById('appNotificationToggle');
    var notificationMenu = document.getElementById('appNotificationMenu');

    if (notificationToggle && notificationMenu) {
        notificationToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var isOpen = notificationMenu.classList.toggle('open');
            notificationMenu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            notificationToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function(e) {
            if (!notificationMenu.contains(e.target) && !notificationToggle.contains(e.target)) {
                notificationMenu.classList.remove('open');
                notificationMenu.setAttribute('aria-hidden', 'true');
                notificationToggle.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                notificationMenu.classList.remove('open');
                notificationMenu.setAttribute('aria-hidden', 'true');
                notificationToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // ── Page Help / Tutorial button ─────────────────────────
    var helpToggle = document.getElementById('pageHelpToggle');
    var helpMenu = document.getElementById('pageHelpMenu');
    var helpList = document.getElementById('pageHelpList');
    var helpSubhead = document.getElementById('pageHelpSubhead');
    var currentPage = window.location.pathname.split('/').pop();

    function loadPageTutorials() {
        if (!helpList || !helpSubhead) return;
        helpSubhead.textContent = 'Loading tutorials for ' + currentPage + '...';
        helpList.innerHTML = '<div class="app-help-menu__empty"><i class="feather icon-loader" style="animation:spin 1s linear infinite;display:inline-block;"></i> Loading...</div>';

        fetch('../api/tutorials/list.php?page=' + encodeURIComponent(currentPage))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.tutorials || !data.tutorials.length) {
                    helpSubhead.textContent = currentPage;
                    helpList.innerHTML = '<div class="app-help-menu__empty"><i class="feather icon-book-open" style="display:block;font-size:2rem;margin-bottom:8px;"></i>No tutorials for this page</div>';
                    return;
                }
                helpSubhead.textContent = data.tutorials.length + ' tutorial' + (data.tutorials.length > 1 ? 's' : '') + ' for ' + currentPage;
                helpList.innerHTML = data.tutorials.map(function(t) {
                    var chapters = [];
                    try { chapters = JSON.parse(t.chapters || '[]'); } catch(e) {}
                    var chapterHtml = chapters.length ? '<div class="app-help-menu__item-chapters">'
                        + chapters.map(function(c) { return '<span class="app-help-menu__chapter">' + esc(c.time) + ' ' + esc(c.label) + '</span>'; }).join('')
                        + '</div>' : '';
                    return '<div class="app-help-menu__item" data-tutorial="' + encodeURIComponent(JSON.stringify(t)) + '">'
                        + '<div class="app-help-menu__item-icon"><i class="feather icon-play-circle"></i></div>'
                        + '<div class="app-help-menu__item-body">'
                        + '<div class="app-help-menu__item-title">' + esc(t.title) + '</div>'
                        + '<div class="app-help-menu__item-desc">' + esc(t.description || '') + '</div>'
                        + chapterHtml
                        + '<div class="app-help-menu__item-play"><i class="feather icon-play"></i> Watch tutorial</div>'
                        + '</div></div>';
                }).join('');
            })
            .catch(function() {
                helpSubhead.textContent = currentPage;
                helpList.innerHTML = '<div class="app-help-menu__empty">Failed to load tutorials</div>';
            });
    }

    if (helpToggle && helpMenu) {
        helpToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var isOpen = helpMenu.classList.toggle('open');
            helpMenu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            helpToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (isOpen) loadPageTutorials();
        });

        helpList.addEventListener('click', function(e) {
            var item = e.target.closest('.app-help-menu__item');
            if (item) {
                var tutorial = JSON.parse(decodeURIComponent(item.getAttribute('data-tutorial')));
                openTutorialVideo(tutorial);
            }
        });

        document.addEventListener('click', function(e) {
            if (helpMenu && !helpMenu.contains(e.target) && helpToggle && !helpToggle.contains(e.target)) {
                helpMenu.classList.remove('open');
                helpMenu.setAttribute('aria-hidden', 'true');
                helpToggle.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && helpMenu) {
                helpMenu.classList.remove('open');
                helpMenu.setAttribute('aria-hidden', 'true');
                if (helpToggle) helpToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
    // ── End Help button ─────────────────────────────────────

    // ── Auto-load on-page-load tutorials ────────────────────
    function checkOnLoadTutorials() {
        var pageName = window.location.pathname.split('/').pop();
        if (!pageName) return;
        fetch('../api/tutorials/check_on_load.php?page=' + encodeURIComponent(pageName))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.tutorials && data.tutorials.length) {
                    onLoadTutorialsQueue = data.tutorials;
                    playNextOnLoadTutorial();
                }
            })
            .catch(function() {});
    }

    function playNextOnLoadTutorial() {
        if (!onLoadTutorialsQueue.length) return;
        var tut = onLoadTutorialsQueue.shift();
        helpAutoSeekIndex = -1;
        var pageName = window.location.pathname.split('/').pop();
        if (pageName === 'accounts.php' && tut.chapters) {
            try {
                var chapters = JSON.parse(tut.chapters);
                if (chapters.length) {
                    fetch('../api/onboarding/check.php')
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            if (d.success && d.current_step === 'main_account') {
                                for (var i = 0; i < chapters.length; i++) {
                                    var label = (chapters[i].label || '').toLowerCase();
                                    if (label.indexOf('add') !== -1 || label.indexOf('create') !== -1 || label.indexOf('new') !== -1) {
                                        helpAutoSeekIndex = i;
                                        seekHelpVideo(helpAutoSeekIndex);
                                        break;
                                    }
                                }
                            }
                        })
                        .catch(function() {});
                }
            } catch(e) {}
        }
        isPlayingOnLoadTutorial = true;
        var btn = document.getElementById('helpVideoMarkLearned');
        if (btn) btn.style.display = 'inline-flex';
        openTutorialVideo(tut);
    }

    checkOnLoadTutorials();
    // ── End auto-load tutorials ─────────────────────────────

    if (typeof window.switchNotifTab !== 'function') {
        window.switchNotifTab = function(btn, id) {
            var menu = btn.closest('.app-notification-menu') || btn.closest('.d-card');
            if (!menu) return;
            menu.querySelectorAll('.notif-tab-btn').forEach(function(tab) {
                tab.classList.remove('active');
            });
            btn.classList.add('active');
            ['ntab-unread', 'ntab-read'].forEach(function(tabId) {
                var panel = menu.querySelector('#' + tabId);
                if (panel) panel.style.display = tabId === id ? 'block' : 'none';
            });
        };
    }

    document.addEventListener('click', function(e) {
        var readButton = e.target.closest('#appNotificationMenu .read-button');
        if (!readButton) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var notificationId = readButton.getAttribute('data-id');
        var formData = new FormData();
        formData.append('notification_id', notificationId);
        formData.append('status', 'read');
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?= h($_SESSION['csrf_token'] ?? '') ?>');

        fetch('../api/dashboard/update_notification_status.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data || !data.success) return;

            var item = readButton.closest('.tl-item');
            if (item) item.remove();

            var countEl = document.getElementById('unreadNotifCount');
            if (countEl) {
                var currentCount = parseInt(countEl.textContent, 10) || 0;
                countEl.textContent = Math.max(0, currentCount - 1);
            }

            var badge = document.getElementById('headerNotifBadge');
            if (badge) {
                var badgeCount = parseInt(badge.textContent, 10) || 0;
                var nextCount = Math.max(0, badgeCount - 1);
                if (nextCount > 0) badge.textContent = nextCount > 99 ? '99+' : nextCount;
                else badge.remove();
            }
        })
        .catch(function(error) { console.error('Notification update error:', error); });
    }, true);

    document.addEventListener('click', function(e) {
        var approveButton = e.target.closest('#appNotificationMenu .approve-button');
        if (!approveButton) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var hiddenNotification = document.getElementById('hiddenNotificationId');
        if (hiddenNotification) hiddenNotification.value = approveButton.getAttribute('data-id') || '';

        // Try jQuery/Bootstrap modal first, then vanilla JS fallback
        if (window.jQuery && jQuery('#receiptModal').length && jQuery.fn.modal) {
            jQuery('#receiptModal').modal('show');
        } else {
            var modal = document.getElementById('receiptModal');
            if (modal) {
                modal.classList.add('show');
                modal.style.display = 'block';
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                // Create backdrop
                if (!document.getElementById('receiptModalBackdrop')) {
                    var backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.id = 'receiptModalBackdrop';
                    document.body.appendChild(backdrop);
                }
            }
        }
    }, true);

    // ── Receipt modal submit handler (vanilla JS, works on all pages) ──
    var submitReceiptBtn = document.getElementById('dashboardSubmitReceipt');
    if (submitReceiptBtn) {
        submitReceiptBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var receiptNumber = document.getElementById('dashboardReceiptNumber').value;
            var remarks = document.getElementById('dashboardRemarks').value;
            var notificationId = document.getElementById('hiddenNotificationId').value;
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?= h($_SESSION['csrf_token'] ?? '') ?>';

            if (!receiptNumber || !remarks) {
                // Show simple warning toast
                var warningToast = document.createElement('div');
                warningToast.className = 'toast-notification bg-warning';
                warningToast.innerHTML = '<i class="feather icon-alert-triangle mr-2"></i><span>Please enter both receipt number and remarks.</span><button type="button" class="close ml-2 text-white">&times;</button>';
                warningToast.style.cssText = 'position:fixed;top:20px;right:20px;padding:16px 20px;border-radius:10px;display:flex;align-items:center;gap:10px;color:#fff;font-size:14px;font-weight:500;z-index:9999;box-shadow:0 10px 25px rgba(0,0,0,0.2);opacity:0;transform:translateX(400px);transition:all 0.3s ease;';
                document.body.appendChild(warningToast);
                setTimeout(function() { warningToast.style.opacity = '1'; warningToast.style.transform = 'translateX(0)'; }, 100);
                setTimeout(function() { warningToast.style.opacity = '0'; warningToast.style.transform = 'translateX(400px)'; setTimeout(function() { warningToast.remove(); }, 300); }, 3000);
                return;
            }

            var formData = new FormData();
            formData.append('notification_id', notificationId);
            formData.append('receipt_number', receiptNumber);
            formData.append('remarks', remarks);
            formData.append('csrf_token', csrfToken);

            fetch('../api/dashboard/approve_notification.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data && data.status === 'success') {
                    // Show success toast
                    var successToast = document.createElement('div');
                    successToast.className = 'toast-notification bg-success';
                    successToast.innerHTML = '<i class="feather icon-check-circle mr-2"></i><span>' + (data.message || 'Notification approved successfully') + '</span><button type="button" class="close ml-2 text-white">&times;</button>';
                    successToast.style.cssText = 'position:fixed;top:20px;right:20px;padding:16px 20px;border-radius:10px;display:flex;align-items:center;gap:10px;color:#fff;font-size:14px;font-weight:500;z-index:9999;box-shadow:0 10px 25px rgba(0,0,0,0.2);opacity:0;transform:translateX(400px);transition:all 0.3s ease;';
                    document.body.appendChild(successToast);
                    setTimeout(function() { successToast.style.opacity = '1'; successToast.style.transform = 'translateX(0)'; }, 100);
                    setTimeout(function() { successToast.style.opacity = '0'; successToast.style.transform = 'translateX(400px)'; setTimeout(function() { successToast.remove(); }, 300); }, 3000);

                    // Close the modal
                    if (window.jQuery && jQuery.fn.modal) {
                        jQuery('#receiptModal').modal('hide');
                    } else if (window.closeReceiptModal) {
                        window.closeReceiptModal();
                    }

                    // Remove the notification item from the UI
                    var notifItem = document.querySelector('[data-id="' + notificationId + '"]');
                    if (notifItem) {
                        var tlItem = notifItem.closest('.tl-item') || notifItem;
                        tlItem.style.transition = 'opacity 0.4s ease';
                        tlItem.style.opacity = '0';
                        setTimeout(function() { tlItem.remove(); }, 400);

                        // Update unread count
                        var countEl = document.getElementById('unreadNotifCount');
                        if (countEl) {
                            var currentCount = parseInt(countEl.textContent, 10) || 0;
                            countEl.textContent = Math.max(0, currentCount - 1);
                        }
                        var badge = document.getElementById('headerNotifBadge');
                        if (badge) {
                            var badgeCount = parseInt(badge.textContent, 10) || 0;
                            var nextCount = Math.max(0, badgeCount - 1);
                            if (nextCount > 0) badge.textContent = nextCount > 99 ? '99+' : nextCount;
                            else badge.remove();
                        }
                    }

                    // Clear form fields
                    document.getElementById('dashboardReceiptNumber').value = '';
                    document.getElementById('dashboardRemarks').value = '';
                } else {
                    // Show error toast
                    var errorToast = document.createElement('div');
                    errorToast.className = 'toast-notification bg-danger';
                    errorToast.innerHTML = '<i class="feather icon-alert-circle mr-2"></i><span>' + (data.message || 'Failed to approve notification') + '</span><button type="button" class="close ml-2 text-white">&times;</button>';
                    errorToast.style.cssText = 'position:fixed;top:20px;right:20px;padding:16px 20px;border-radius:10px;display:flex;align-items:center;gap:10px;color:#fff;font-size:14px;font-weight:500;z-index:9999;box-shadow:0 10px 25px rgba(0,0,0,0.2);opacity:0;transform:translateX(400px);transition:all 0.3s ease;';
                    document.body.appendChild(errorToast);
                    setTimeout(function() { errorToast.style.opacity = '1'; errorToast.style.transform = 'translateX(0)'; }, 100);
                    setTimeout(function() { errorToast.style.opacity = '0'; errorToast.style.transform = 'translateX(400px)'; setTimeout(function() { errorToast.remove(); }, 300); }, 3000);
                }
            })
            .catch(function(error) {
                console.error('Receipt submit error:', error);
                var errToast = document.createElement('div');
                errToast.className = 'toast-notification bg-danger';
                errToast.innerHTML = '<i class="feather icon-alert-circle mr-2"></i><span>An error occurred while processing your request.</span><button type="button" class="close ml-2 text-white">&times;</button>';
                errToast.style.cssText = 'position:fixed;top:20px;right:20px;padding:16px 20px;border-radius:10px;display:flex;align-items:center;gap:10px;color:#fff;font-size:14px;font-weight:500;z-index:9999;box-shadow:0 10px 25px rgba(0,0,0,0.2);opacity:0;transform:translateX(400px);transition:all 0.3s ease;';
                document.body.appendChild(errToast);
                setTimeout(function() { errToast.style.opacity = '1'; errToast.style.transform = 'translateX(0)'; }, 100);
                setTimeout(function() { errToast.style.opacity = '0'; errToast.style.transform = 'translateX(400px)'; setTimeout(function() { errToast.remove(); }, 300); }, 3000);
            });
        });
    }

    // ── Read notifications date filter ──────────────────────────────
    var applyReadDateFilterBtn = document.getElementById('applyReadDateFilter');
    if (applyReadDateFilterBtn) {
        applyReadDateFilterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var selectedDate = document.getElementById('readNotificationsDate').value;
            if (!selectedDate) return;

            var readBody = document.getElementById('readNotificationsBody');
            if (readBody) readBody.innerHTML = '<div style="text-align:center;padding:28px;color:var(--app-text-secondary);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

            var formData = new FormData();
            formData.append('date', selectedDate);
            formData.append('status', 'read');

            fetch('../api/dashboard/get_filtered_notifications.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data && data.status === 'success' && data.html) {
                    if (readBody) readBody.innerHTML = data.html;
                } else {
                    if (readBody) readBody.innerHTML = '<div style="text-align:center;padding:28px;color:var(--app-text-secondary);"><i class="feather icon-inbox"></i><div>No read notifications for selected date</div></div>';
                }
            })
            .catch(function(error) {
                console.error('Read filter error:', error);
                if (readBody) readBody.innerHTML = '<div style="text-align:center;padding:28px;color:#ef4444;">Error loading notifications</div>';
            });
        });
    }

    // ── Mobile sidebar ────────────────────────────────────────────
    var mobileFloat = document.querySelector('.mobile-menu-float');
    var mobileToggle = document.getElementById('mobile-collapse');

    function openSidebar() {
        var navbar  = document.querySelector('.pcoded-navbar');
        var overlay = document.querySelector('.mobile-menu-overlay');
        if (!navbar) return;
        navbar.classList.add('mobile-overlay', 'open');
        mobileFloat && mobileFloat.classList.add('active');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'mobile-menu-overlay';
            overlay.addEventListener('click', closeSidebar);
            document.body.appendChild(overlay);
        }
        overlay.classList.add('show');
    }

    function closeSidebar() {
        var navbar  = document.querySelector('.pcoded-navbar');
        var overlay = document.querySelector('.mobile-menu-overlay');
        if (!navbar) return;
        navbar.classList.remove('open');
        mobileFloat && mobileFloat.classList.remove('active');
        overlay && overlay.classList.remove('show');
    }

    function toggleSidebar(e) {
        e.preventDefault();
        e.stopPropagation();
        if (window.innerWidth >= 992) return; // desktop – let the theme handle it
        var navbar = document.querySelector('.pcoded-navbar');
        navbar && navbar.classList.contains('open') ? closeSidebar() : openSidebar();
    }

    mobileFloat  && mobileFloat.addEventListener('click', toggleSidebar);
    mobileToggle && mobileToggle.addEventListener('click', toggleSidebar);

    // ── Preserve sidebar scroll position across page loads ────────
    var sidebarScroller = document.querySelector('.pcoded-navbar .navbar-content');
    if (sidebarScroller) {
        var SIDEBAR_SCROLL_KEY = 'mtravels_sidebar_scroll_top';
        var sidebarSaveTimer = null;

        var saveSidebarScroll = function() {
            try { sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(sidebarScroller.scrollTop)); } catch(e) {}
        };

        sidebarScroller.addEventListener('scroll', function() {
            if (sidebarSaveTimer) return;
            sidebarSaveTimer = setTimeout(function() {
                sidebarSaveTimer = null;
                saveSidebarScroll();
            }, 150);
        }, { passive: true });

        window.addEventListener('pagehide', saveSidebarScroll);

        var savedSidebarTop = 0;
        try { savedSidebarTop = parseInt(sessionStorage.getItem(SIDEBAR_SCROLL_KEY) || '0', 10) || 0; } catch(e) {}

        var syncSlimScrollbar = function() {
            if (!window.jQuery || !sidebarScroller) return;
            var wrapper = sidebarScroller.parentNode;
            if (!wrapper || !wrapper.className || wrapper.className.indexOf('slimScrollDiv') === -1) return;
            var bar = wrapper.querySelector('.slimScrollBar');
            if (!bar) return;
            var maxBarTop = wrapper.clientHeight - bar.offsetHeight;
            var maxScroll = sidebarScroller.scrollHeight - sidebarScroller.clientHeight;
            var barTop = maxScroll > 0 ? Math.round((sidebarScroller.scrollTop / maxScroll) * maxBarTop) : 0;
            bar.style.top = Math.min(Math.max(barTop, 0), maxBarTop) + 'px';
        };

        if (savedSidebarTop > 0) {
            var applySidebarScroll = function() {
                if (!sidebarScroller) return;
                var maxScroll = sidebarScroller.scrollHeight - sidebarScroller.clientHeight;
                var target = Math.min(savedSidebarTop, Math.max(maxScroll, 0));
                if (sidebarScroller.scrollTop !== target) {
                    sidebarScroller.scrollTop = target;
                }
                syncSlimScrollbar();
            };
            window.addEventListener('load', function() { setTimeout(applySidebarScroll, 60); });
            window.addEventListener('pageshow', function() { setTimeout(applySidebarScroll, 60); });
            setTimeout(applySidebarScroll, 150);
        }
    }

    // ── Session timeout ───────────────────────────────────────────
    var remainingTime     = <?= (int) $remaining_time ?>;
    var SESSION_TIMEOUT   = <?= (int) $session_timeout ?>;
    var lastActivityTime  = Date.now();
    var warningShown5Min  = false;
    var warningShown1Min  = false;
    var warningTimeout    = null; // non-blocking toast placeholder

    function showSessionWarning(message) {
        // Use a non-blocking toast/banner instead of alert()
        // Replace the body of this function with your preferred toast library call.
        var banner = document.getElementById('session-warning-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'session-warning-banner';
            banner.style.cssText = [
                'position:fixed;top:0;left:0;right:0;z-index:9999;',
                'background:#f59e0b;color:#1c1917;text-align:center;',
                'padding:10px 20px;font-weight:600;font-size:14px;',
                'box-shadow:0 2px 8px rgba(0,0,0,0.2);'
            ].join('');
            document.body.appendChild(banner);
        }
        banner.textContent = message;
        banner.style.display = 'block';
        clearTimeout(warningTimeout);
        warningTimeout = setTimeout(function () {
            if (banner) banner.style.display = 'none';
        }, 8000);
    }

    function hideBanner() {
        var banner = document.getElementById('session-warning-banner');
        if (banner) banner.style.display = 'none';
        clearTimeout(warningTimeout);
    }

    function checkServerSession() {
        fetch('../api/session_check.php', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (res) {
            if (res.status === 401 || res.status === 403) {
                window.location.href = '../login.php?timeout=1';
                return null;
            }
            return res.json();
        })
        .then(function (data) {
            if (data && !data.authenticated) {
                window.location.href = '../login.php?timeout=1';
            }
        })
        .catch(function (err) { console.error('Session check error:', err); });
    }

    // Re-validate with server whenever the tab becomes visible after a pause
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            var away = (Date.now() - lastActivityTime) / 1000;
            if (away > 30) checkServerSession();
            lastActivityTime = Date.now();
        }
    });

    // Countdown tick
    setInterval(function () {
        if (remainingTime <= 0) {
            window.location.href = '../logout.php';
            return;
        }
        if (remainingTime <= 300 && !warningShown5Min) {
            showSessionWarning('Your session will expire in 5 minutes. Please save your work.');
            warningShown5Min = true;
        }
        if (remainingTime <= 60 && !warningShown1Min) {
            showSessionWarning('Your session will expire in 1 minute. Please save your work.');
            warningShown1Min = true;
        }
        remainingTime--;
    }, 1000);

    // Debounced activity reset (fires at most once per 10 s to avoid flooding)
    var activityDebounce;
    ['mousedown', 'keypress', 'scroll', 'touchstart', 'click'].forEach(function (ev) {
        document.addEventListener(ev, function () {
            clearTimeout(activityDebounce);
            activityDebounce = setTimeout(function () {
                lastActivityTime  = Date.now();
                remainingTime     = SESSION_TIMEOUT;
                warningShown5Min  = false;
                warningShown1Min  = false;
                hideBanner();
            }, 10000);
        }, { passive: true, capture: true });
    });
});
</script>

<?php if (hasFeature('inter_tenant_chat', $allowed_features)): ?>
<script>
(function () {
    var fab        = document.getElementById('alqChatFab');
    var panel      = document.getElementById('alqChatPanel');
    var closeBtn   = document.getElementById('alqChatClose');
    var openFull   = document.getElementById('alqChatOpenFull');
    var badge      = document.getElementById('alqChatUnreadBadge');
    var unreadCount = 0;

    if (!fab || !panel) return;

    function togglePanel(forceOpen) {
        var isOpen = panel.classList.contains('open');
        if (forceOpen === true || !isOpen) {
            panel.classList.add('open');
            panel.setAttribute('aria-hidden', 'false');
            if (unreadCount > 0) markAsSeen();
        } else {
            panel.classList.remove('open');
            panel.setAttribute('aria-hidden', 'true');
        }
    }

    function updateBadge(count) {
        unreadCount = count;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.add('show');
        } else {
            badge.classList.remove('show');
        }
    }

    function fetchUnreadCount() {
        fetch('../api/unread_count.php', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (d.total_unread !== undefined) updateBadge(d.total_unread); })
            .catch(function (e) { console.error('Unread count error:', e); });
    }

    function markAsSeen() {
        var frame = document.getElementById('alqChatFrame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.postMessage({ type: 'markAsSeen' }, '*');
        }
    }

    fetchUnreadCount();
    setInterval(fetchUnreadCount, 30000);

    window.addEventListener('message', function (e) {
        if (e.data && e.data.type === 'unreadCountUpdate') updateBadge(e.data.count);
    });

    fab.addEventListener('click',     function (e) { e.preventDefault(); e.stopPropagation(); togglePanel(); });
    closeBtn && closeBtn.addEventListener('click', function (e) { e.preventDefault(); togglePanel(false); });
    openFull && openFull.addEventListener('click', function (e) { e.preventDefault(); window.location.href = '../chat.php'; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && panel.classList.contains('open')) togglePanel(false); });
}());
</script>
<?php endif; ?>

<div id="helpVideoModal" class="help-video-modal" style="display:none;">
    <div class="help-video-modal-content">
        <div class="help-video-modal-header">
            <span class="help-video-modal-title" id="helpVideoTitle">Tutorial</span>
            <div>
                <button class="help-video-learn-btn" id="helpVideoMarkLearned" onclick="markTutorialLearned()" style="display:none;" title="Mark as learned so it won't auto-play again">
                    <i class="feather icon-check-circle"></i> Mark as Learned
                </button>
                <span class="help-video-modal-close" onclick="closeHelpVideo()">&times;</span>
            </div>
        </div>
        <div class="help-video-container">
            <iframe id="helpVideoPlayer" src="" allow="autoplay; fullscreen; picture-in-picture"></iframe>
        </div>
        <div class="help-video-chapters" id="helpVideoChapters" style="display:none;">
            <div class="help-video-chapters-title">Chapters</div>
            <div class="help-video-chapters-list" id="helpVideoChaptersList"></div>
        </div>
    </div>
</div>

<?php if ($onboarding_video): ?>
<div id="onboardingVideoOverlay" class="onboarding-video-overlay">
    <div class="onboarding-video-modal">
        <button class="onboarding-video-close" onclick="dismissOnboardingVideo()" aria-label="Close">&times;</button>
        <div class="onboarding-video-header">
            <h2><?= h(__('welcome_to') ?? 'Welcome') ?> <?= h($settings['agency_name'] ?? '') ?>!</h2>
            <p><?= h($onboarding_video['title'] ?? 'Watch this quick guide to set up your system') ?></p>
        </div>
        <div class="onboarding-video-player">
            <iframe id="onboardingVideoPlayer"
                    src="https://player.vimeo.com/video/<?= h($onboarding_video['video_id']) ?>?autoplay=1&title=0&byline=0&portrait=0"
                    allow="autoplay; fullscreen; picture-in-picture"
                    loading="lazy"></iframe>
        </div>
        <div class="onboarding-video-footer">
            <button class="onboarding-video-skip" onclick="dismissOnboardingVideo()">
                <i class="feather icon-play-circle"></i> <?= h(__('got_it_setup') ?? 'I understand, let\'s begin setup') ?>
                <i class="feather icon-arrow-right"></i>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($onboarding_guide): ?>
<div id="onboardingGuide" class="og-panel"<?= $onboarding_video ? ' style="display:none"' : '' ?>>
    <div class="og-header">
        <div class="og-title"><i class="feather icon-zap"></i> Getting Started</div>
        <button class="og-close" onclick="dismissOnboarding()" title="Dismiss">&times;</button>
    </div>
    <div class="og-progress-wrap">
        <div class="og-progress-bar" id="ogProgressBar" style="width:<?= $onboarding_data['percent'] ?>%"></div>
    </div>
    <div class="og-steps" id="ogSteps">
        <div class="og-step <?= $onboarding_data['progress']['main_account'] ? 'og-done' : ($onboarding_data['current_step'] === 'main_account' ? 'og-current' : '') ?>" data-step="main_account">
            <div class="og-step-icon"><?= $onboarding_data['progress']['main_account'] ? '<i class="feather icon-check-circle"></i>' : '<span>1</span>' ?></div>
            <div class="og-step-body">
                <div class="og-step-label">Create a Main Account</div>
                <div class="og-step-status"><?= $onboarding_data['progress']['main_account'] ? 'Done' : ($onboarding_data['current_step'] === 'main_account' ? 'Current' : 'Pending') ?></div>
            </div>
        </div>
        <div class="og-step <?= $onboarding_data['progress']['supplier'] ? 'og-done' : ($onboarding_data['current_step'] === 'supplier' ? 'og-current' : '') ?>" data-step="supplier">
            <div class="og-step-icon"><?= $onboarding_data['progress']['supplier'] ? '<i class="feather icon-check-circle"></i>' : '<span>2</span>' ?></div>
            <div class="og-step-body">
                <div class="og-step-label">Add a Supplier</div>
                <div class="og-step-status"><?= $onboarding_data['progress']['supplier'] ? 'Done' : ($onboarding_data['current_step'] === 'supplier' ? 'Current' : 'Pending') ?></div>
            </div>
        </div>
        <div class="og-step <?= $onboarding_data['progress']['client'] ? 'og-done' : ($onboarding_data['current_step'] === 'client' ? 'og-current' : '') ?>" data-step="client">
            <div class="og-step-icon"><?= $onboarding_data['progress']['client'] ? '<i class="feather icon-check-circle"></i>' : '<span>3</span>' ?></div>
            <div class="og-step-body">
                <div class="og-step-label">Add a Client</div>
                <div class="og-step-status"><?= $onboarding_data['progress']['client'] ? 'Done' : ($onboarding_data['current_step'] === 'client' ? 'Current' : 'Pending') ?></div>
            </div>
        </div>
    </div>
    <div class="og-action" id="ogAction">
        <div class="og-action-label" id="ogActionLabel"><?= htmlspecialchars($onboarding_data['step_label']) ?></div>
        <div class="og-action-desc" id="ogActionDesc"><?= htmlspecialchars($onboarding_data['step_desc']) ?></div>
        <a class="og-action-btn" id="ogActionBtn" href="<?= htmlspecialchars($onboarding_data['step_page']) ?>">
            <i class="feather icon-arrow-right"></i> Go
        </a>
    </div>
</div>
<?php endif; ?>

<style>
.help-video-modal {
    position: fixed; z-index: 12000; left: 0; top: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.7); align-items: center; justify-content: center;
}
.help-video-modal.show { display: flex !important; }
.help-video-modal-content {
    position: relative; background: #000; width: 90%; max-width: 900px; border-radius: 8px;
    max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
}
.help-video-modal-header {
    background: #1a1a2e; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between;
}
.help-video-modal-title { color: #fff; font-weight: 600; font-size: .9rem; }
.help-video-modal-close { color: #fff; font-size: 24px; cursor: pointer; line-height: 1; margin-left: 8px; }
.help-video-modal-close:hover { color: #ff5370; }
.help-video-learn-btn {
    display: inline-flex; align-items: center; gap: 5px; background: #10b981; color: #fff;
    border: none; border-radius: 5px; padding: 5px 10px; font-size: .78rem; font-weight: 600;
    cursor: pointer; transition: all .2s;
}
.help-video-learn-btn:hover { background: #059669; }
.help-video-learn-btn:disabled { opacity: .6; cursor: not-allowed; }
.help-video-container { position: relative; width: 100%; aspect-ratio: 16 / 9; flex-shrink: 0; }
.help-video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
.help-video-chapters { background: #1a1a2e; padding: 12px 16px; border-top: 1px solid #333; overflow-y: auto; flex-shrink: 1; min-height: 0; }
.help-video-chapters-title { color: #aaa; font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; flex-shrink: 0; }
.help-video-chapters-list { display: flex; flex-wrap: wrap; gap: 6px; }
.help-video-chapter-item {
    display: flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.08); border-radius: 5px;
    padding: 5px 10px; cursor: pointer; transition: all .2s; border: 1px solid transparent;
}
.help-video-chapter-item:hover { background: rgba(70,128,255,0.2); border-color: #4680ff; }
.help-video-chapter-time { font-size: .75rem; font-weight: 700; color: #4680ff; font-family: monospace; }
.help-video-chapter-label { font-size: .8rem; color: #e0e0e0; }

/* ── Onboarding Full-Screen Video Overlay ─────── */
.onboarding-video-overlay {
    position: fixed; z-index: 99999; inset: 0;
    background: rgba(0,0,0,0.85);
    display: flex; align-items: center; justify-content: center;
    backdrop-filter: blur(4px);
    animation: ovFadeIn .35s ease;
    overflow-y: auto;
    padding: 16px;
}
body.onboarding-video-open {
    overflow: hidden;
}
@keyframes ovFadeIn { from { opacity: 0; } to { opacity: 1; } }
.onboarding-video-modal {
    background: #1a1a2e; border-radius: 16px;
    width: 92%; max-width: 820px;
    overflow: hidden; position: relative;
    box-shadow: 0 24px 80px rgba(0,0,0,0.5);
    animation: ovScaleIn .4s cubic-bezier(.34,1.56,.64,1);
}
@keyframes ovScaleIn { from { transform: scale(.92); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.onboarding-video-close {
    position: absolute; top: 12px; right: 14px;
    background: rgba(255,255,255,0.12); border: none;
    color: #fff; font-size: 22px; cursor: pointer;
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    z-index: 10; transition: all .2s; line-height: 1;
}
.onboarding-video-close:hover { background: rgba(255,255,255,0.25); transform: rotate(90deg); }
.onboarding-video-header {
    padding: 28px 32px 14px; text-align: center;
}
.onboarding-video-header h2 {
    color: #fff; font-size: 22px; font-weight: 700; margin: 0 0 6px;
}
.onboarding-video-header p {
    color: rgba(255,255,255,0.55); font-size: 14px; margin: 0;
}
.onboarding-video-player {
    position: relative; width: 100%; padding-bottom: 56.25%;
    height: 0; overflow: hidden;
}
.onboarding-video-player iframe {
    position: absolute; inset: 0; width: 100%; height: 100%; border: 0;
}
.onboarding-video-footer {
    padding: 16px 32px 24px; text-align: center;
}
.onboarding-video-skip {
    background: linear-gradient(135deg, #4099ff, #2ed8b6);
    color: #fff; border: none; border-radius: 10px;
    padding: 12px 28px; font-size: 15px; font-weight: 600;
    cursor: pointer; transition: all .25s; font-family: inherit;
    display: inline-flex; align-items: center; gap: 8px;
    box-shadow: 0 4px 20px rgba(64,153,255,.35);
}
.onboarding-video-skip:hover {
    transform: translateY(-2px); box-shadow: 0 8px 28px rgba(64,153,255,.5);
}
.onboarding-video-skip i.feather { font-size: 16px; }

@media (max-width: 640px) {
    .onboarding-video-modal { width: 96%; border-radius: 12px; }
    .onboarding-video-header { padding: 20px 16px 10px; }
    .onboarding-video-header h2 { font-size: 17px; }
    .onboarding-video-header p { font-size: 13px; }
    .onboarding-video-footer { padding: 12px 16px 18px; }
    .onboarding-video-skip { padding: 10px 20px; font-size: 14px; }
}

/* ── Onboarding Guide ─────────────────────────── */
.og-panel {
    position: fixed; bottom: 24px; right: 24px; z-index: 1050;
    width: 340px; background: #fff; border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,.15), 0 2px 8px rgba(0,0,0,.08);
    overflow: hidden; font-family: 'DM Sans', sans-serif;
    animation: ogSlideUp .35s ease-out;
}
@keyframes ogSlideUp {
    from { transform: translateY(30px); opacity: 0; }
    to   { transform: translateY(0); opacity: 1; }
}
.og-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; background: linear-gradient(135deg,#4099ff,#2ed8b6); color: #fff;
}
.og-title { font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 7px; }
.og-close { background: none; border: none; color: rgba(255,255,255,.7); font-size: 20px; cursor: pointer; padding: 0; line-height: 1; }
.og-close:hover { color: #fff; }
.og-progress-wrap { height: 4px; background: #e9ecef; }
.og-progress-bar { height: 100%; background: linear-gradient(90deg,#4099ff,#2ed8b6); border-radius: 0 2px 2px 0; transition: width .5s ease; }
.og-steps { padding: 14px 18px 0; display: flex; flex-direction: column; gap: 6px; }
.og-step { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; transition: all .2s; }
.og-step.og-current { background: #f0f7ff; }
.og-step.og-done { opacity: .6; }
.og-step-icon {
    width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; flex-shrink: 0;
    background: #e9ecef; color: #6c757d;
}
.og-step.og-current .og-step-icon { background: #4099ff; color: #fff; }
.og-step.og-done .og-step-icon { background: #2ed8b6; color: #fff; }
.og-step.og-done .og-step-icon i { font-size: 14px; }
.og-step-body { flex: 1; min-width: 0; }
.og-step-label { font-size: 12.5px; font-weight: 600; color: #212529; }
.og-step-status { font-size: 10.5px; color: #6c757d; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
.og-step.og-current .og-step-status { color: #4099ff; }
.og-step.og-done .og-step-status { color: #2ed8b6; }
.og-action { padding: 12px 18px 16px; border-top: 1px solid #e9ecef; margin-top: 6px; }
.og-action-label { font-size: 13px; font-weight: 700; color: #212529; margin-bottom: 3px; }
.og-action-desc { font-size: 12px; color: #6c757d; line-height: 1.4; margin-bottom: 10px; }
.og-action-btn {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px;
    background: linear-gradient(135deg,#4099ff,#2ed8b6); color: #fff; border-radius: 8px;
    font-size: 13px; font-weight: 600; text-decoration: none; transition: opacity .2s;
}
.og-action-btn:hover { opacity: .9; color: #fff; text-decoration: none; }

/* ── Nav highlight ────────────────────────────── */
.onboarding-nav-highlight {
    animation: ogNavPulse 2s ease-in-out infinite !important;
    position: relative;
}
.onboarding-nav-highlight::after {
    content: ''; position: absolute; inset: 0;
    border-radius: inherit; pointer-events: none;
    box-shadow: 0 0 0 0 rgba(64,153,255,.4);
    animation: ogNavRing 2s ease-in-out infinite;
}
@keyframes ogNavPulse {
    0%, 100% { background: rgba(64,153,255,.08); }
    50% { background: rgba(64,153,255,.18); }
}
  @keyframes ogNavRing {
      0%, 100% { box-shadow: 0 0 0 0 rgba(64,153,255,.4); }
      50% { box-shadow: 0 0 0 4px rgba(64,153,255,.15); }
  }
  .onboarding-btn-highlight {
      animation: ogBtnPulse 1.5s ease-in-out infinite !important;
      position: relative;
      z-index: 1;
  }
  @keyframes ogBtnPulse {
      0%, 100% { box-shadow: 0 0 0 0 rgba(64,153,255,.6); transform: scale(1); }
      50% { box-shadow: 0 0 0 6px rgba(64,153,255,.25); transform: scale(1.03); }
  }
  .onboarding-btn-hint {
      position: fixed; z-index: 1060;
      background: #4099ff; color: #fff;
      padding: 8px 16px; border-radius: 8px;
      font-size: 13px; font-weight: 500;
      white-space: nowrap; pointer-events: none;
      box-shadow: 0 4px 12px rgba(64,153,255,.35);
      font-family: 'DM Sans', sans-serif;
      display: flex; align-items: center; gap: 8px;
  }
  .onboarding-btn-hint::after {
      content: ''; position: absolute;
      width: 0; height: 0;
      border: 6px solid transparent;
  }
  .onboarding-btn-hint.og-hint-below::after {
      top: -12px; left: 50%; margin-left: -6px;
      border-bottom-color: #4099ff;
  }
  .onboarding-btn-hint.og-hint-above::after {
      bottom: -12px; left: 50%; margin-left: -6px;
      border-top-color: #4099ff;
  }
  </style>

<script>
function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

var helpCurrentVideoId = null;
var helpCurrentVideoType = null;
var helpCurrentChapters = [];
var helpYtPlayer = null;
var helpVimeoPlayer = null;
var helpYtApiLoaded = false;
var helpVimeoApiLoaded = false;

var helpCurrentTutorialId = null;

function openTutorialVideo(tutorial) {
    var type = tutorial.video_type || 'vimeo';
    var id = tutorial.video_id || '';
    if (!id) return;

    document.getElementById('helpVideoTitle').textContent = tutorial.title || 'Tutorial';
    helpCurrentVideoId = id;
    helpCurrentVideoType = type;
    helpCurrentTutorialId = tutorial.id;
    helpYtPlayer = null;
    helpVimeoPlayer = null;
    try { helpCurrentChapters = JSON.parse(tutorial.chapters || '[]'); } catch(e) { helpCurrentChapters = []; }

    var url = type === 'youtube'
        ? 'https://www.youtube.com/embed/' + id + '?autoplay=1&rel=0&enablejsapi=1'
        : 'https://player.vimeo.com/video/' + id + '?autoplay=1';

    document.getElementById('helpVideoPlayer').src = url;
    document.getElementById('helpVideoModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    renderHelpChapters();
    loadHelpPlayerApi();
}

function loadHelpPlayerApi() {
    var iframe = document.getElementById('helpVideoPlayer');
    if (helpCurrentVideoType === 'youtube') {
        if (!helpYtApiLoaded) {
            helpYtApiLoaded = true;
            var tag = document.createElement('script');
            tag.src = 'https://www.youtube.com/iframe_api';
            var first = document.getElementsByTagName('script')[0];
            first.parentNode.insertBefore(tag, first);
        }
        var checkYt = setInterval(function() {
            if (typeof YT !== 'undefined' && YT.loaded) {
                clearInterval(checkYt);
                if (!helpYtPlayer) {
                    try { helpYtPlayer = new YT.Player('helpVideoPlayer', {}); } catch(e) {}
                }
                if (helpAutoSeekIndex >= 0) seekHelpVideo(helpAutoSeekIndex);
            }
        }, 500);
    } else if (helpCurrentVideoType === 'vimeo') {
        if (!helpVimeoApiLoaded) {
            helpVimeoApiLoaded = true;
            var tag = document.createElement('script');
            tag.src = 'https://player.vimeo.com/api/player.js';
            var first = document.getElementsByTagName('script')[0];
            first.parentNode.insertBefore(tag, first);
        }
        var checkVimeo = setInterval(function() {
            if (typeof Vimeo !== 'undefined' && Vimeo.Player) {
                clearInterval(checkVimeo);
                if (!helpVimeoPlayer) {
                    try { helpVimeoPlayer = new Vimeo.Player(iframe); } catch(e) {}
                }
                if (helpAutoSeekIndex >= 0) seekHelpVideo(helpAutoSeekIndex);
            }
        }, 500);
    }
}

function renderHelpChapters() {
    var container = document.getElementById('helpVideoChapters');
    var list = document.getElementById('helpVideoChaptersList');
    if (!helpCurrentChapters.length) {
        container.style.display = 'none';
        return;
    }
    container.style.display = 'block';
    list.innerHTML = helpCurrentChapters.map(function(ch, i) {
        return '<div class="help-video-chapter-item" onclick="seekHelpVideo(' + i + ')">'
            + '<span class="help-video-chapter-time">' + esc(ch.time) + '</span>'
            + '<span class="help-video-chapter-label">' + esc(ch.label) + '</span>'
            + '</div>';
    }).join('');
}

function seekHelpVideo(index) {
    var ch = helpCurrentChapters[index];
    if (!ch) return;
    var seconds = parseInt(ch.seconds, 10) || 0;
    if (helpCurrentVideoType === 'youtube' && helpYtPlayer && typeof helpYtPlayer.seekTo === 'function') {
        helpYtPlayer.seekTo(seconds, true);
    } else if (helpCurrentVideoType === 'vimeo' && helpVimeoPlayer && typeof helpVimeoPlayer.setCurrentTime === 'function') {
        helpVimeoPlayer.setCurrentTime(seconds).catch(function() {});
    }
}

function markTutorialLearned() {
    var tutorialId = helpCurrentTutorialId;
    if (!tutorialId) return;

    var btn = document.getElementById('helpVideoMarkLearned');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="feather icon-loader"></i> Saving...';
    }

    var formData = new FormData();
    formData.append('tutorial_id', tutorialId);

    fetch('../api/tutorials/mark_learned.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                try { localStorage.setItem('tutorial_learned_' + currentUserId + '_' + tutorialId, '1'); } catch(e) {}
                closeHelpVideo();
                if (isPlayingOnLoadTutorial) {
                    isPlayingOnLoadTutorial = false;
                    playNextOnLoadTutorial();
                }
            }
        })
        .catch(function() {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="feather icon-check-circle"></i> Mark as Learned';
            }
        });
}

function closeHelpVideo() {
    document.getElementById('helpVideoModal').classList.remove('show');
    document.getElementById('helpVideoPlayer').src = '';
    document.body.style.overflow = 'auto';
    helpCurrentChapters = [];
    helpYtPlayer = null;
    helpVimeoPlayer = null;
    var btn = document.getElementById('helpVideoMarkLearned');
    if (btn) {
        btn.style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = '<i class="feather icon-check-circle"></i> Mark as Learned';
    }
    if (isPlayingOnLoadTutorial) {
        isPlayingOnLoadTutorial = false;
    }
}

document.addEventListener('click', function(e) {
    if (e.target === document.getElementById('helpVideoModal')) closeHelpVideo();
    if (e.target === document.getElementById('onboardingVideoOverlay')) dismissOnboardingVideo();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('helpVideoModal').classList.contains('show')) closeHelpVideo();
    if (e.key === 'Escape' && document.getElementById('onboardingVideoOverlay')) dismissOnboardingVideo();
});

/* ── Onboarding Guide ─────────────────────────── */
<?php if ($onboarding_guide): ?>
var ogTimer = null;

function highlightOnboardingNav(page) {
    document.querySelectorAll('.onboarding-nav-highlight').forEach(function(el) {
        el.classList.remove('onboarding-nav-highlight');
    });
    if (!page) return;
    var link = document.querySelector('.pcoded-inner-navbar a[href="' + page + '"]');
    if (link) {
        var li = link.closest('.nav-item');
        if (li) li.classList.add('onboarding-nav-highlight');
    }
}

function highlightOnboardingButton(page) {
    document.querySelectorAll('.onboarding-btn-highlight').forEach(function(el) {
        el.classList.remove('onboarding-btn-highlight');
    });
    var oldHint = document.getElementById('ogBtnHint');
    if (oldHint) oldHint.remove();
    if (!page) return;
    var sel;
    if (page === 'accounts.php') sel = '#addMainAccountBtn';
    else if (page === 'supplier.php') sel = '[data-target="#addSupplierModal"]';
    else if (page === 'client.php') sel = '#addClientBtn';
    else return;
    var btn = document.querySelector(sel);
    if (!btn) return;
    btn.classList.add('onboarding-btn-highlight');
    var rect = btn.getBoundingClientRect();
    var ogPanel = document.getElementById('onboardingGuide');
    var ogRect = ogPanel ? ogPanel.getBoundingClientRect() : null;
    var overlapY = 0;
    if (ogRect && rect.bottom + 40 > ogRect.top && rect.right + 40 > ogRect.left) {
        overlapY = rect.bottom - ogRect.top + 40;
    }
    var targetScrollY = Math.max(0, window.scrollY + rect.top - 120 - overlapY);
    var scrollDelta = window.scrollY - targetScrollY;
    window.scrollTo({ top: targetScrollY, behavior: 'smooth' });
    var hint = document.createElement('div');
    hint.id = 'ogBtnHint';
    hint.className = 'onboarding-btn-hint';
    hint.innerHTML = 'Click here <i class="feather icon-arrow-right"></i>';
    var hintTop = rect.top - scrollDelta;
    var hintBottom = rect.bottom - scrollDelta;
    if (window.innerHeight - hintBottom >= 50) {
        hint.classList.add('og-hint-below');
        hint.style.left = Math.max(8, Math.min(rect.left + rect.width / 2 - 60, window.innerWidth - 170)) + 'px';
        hint.style.top = (hintBottom + 12) + 'px';
    } else {
        hint.classList.add('og-hint-above');
        hint.style.left = Math.max(8, Math.min(rect.left + rect.width / 2 - 60, window.innerWidth - 170)) + 'px';
        hint.style.top = (hintTop - 48) + 'px';
    }
    document.body.appendChild(hint);
}

function refreshOnboardingGuide() {
    var og = document.getElementById('onboardingGuide');
    if (!og || og.style.display === 'none') return;
    var ogBar = document.getElementById('ogProgressBar');
    var ogSteps = document.getElementById('ogSteps');
    var ogActionLabel = document.getElementById('ogActionLabel');
    var ogActionDesc = document.getElementById('ogActionDesc');
    var ogActionBtn = document.getElementById('ogActionBtn');

    fetch('../api/onboarding/check.php')
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) return;
            if (!d.should_show) {
                og.style.display = 'none';
                highlightOnboardingNav(null);
                highlightOnboardingButton(null);
                return;
            }
            ogBar.style.width = d.percent + '%';

            var steps = ['main_account', 'supplier', 'client'];
            steps.forEach(function(s) {
                var el = ogSteps.querySelector('[data-step="' + s + '"]');
                if (!el) return;
                el.className = 'og-step';
                if (d.progress[s]) {
                    el.classList.add('og-done');
                    el.querySelector('.og-step-icon').innerHTML = '<i class="feather icon-check-circle"></i>';
                    el.querySelector('.og-step-status').textContent = 'Done';
                } else if (s === d.current_step) {
                    el.classList.add('og-current');
                    el.querySelector('.og-step-icon').innerHTML = '<span>' + (steps.indexOf(s) + 1) + '</span>';
                    el.querySelector('.og-step-status').textContent = 'Current';
                } else {
                    el.querySelector('.og-step-icon').innerHTML = '<span>' + (steps.indexOf(s) + 1) + '</span>';
                    el.querySelector('.og-step-status').textContent = 'Pending';
                }
            });

            ogActionLabel.textContent = d.step_label || '';
            ogActionDesc.textContent = d.step_description || '';
            ogActionBtn.href = d.step_page || '#';
            highlightOnboardingNav(d.step_page);
            highlightOnboardingButton(d.step_page);
        });
}

var og = document.getElementById('onboardingGuide');
if (og) {
    highlightOnboardingNav('<?= $onboarding_data['step_page'] ?>');
    highlightOnboardingButton('<?= $onboarding_data['step_page'] ?>');
    ogTimer = setInterval(refreshOnboardingGuide, 5000);
}
<?php endif; ?>

function dismissOnboardingVideo() {
    var overlay = document.getElementById('onboardingVideoOverlay');
    if (overlay) {
        overlay.style.transition = 'opacity .4s ease';
        overlay.style.opacity = '0';
        setTimeout(function() { overlay.style.display = 'none'; }, 400);
    }
    document.body.classList.remove('onboarding-video-open');
    var iframe = document.getElementById('onboardingVideoPlayer');
    if (iframe) iframe.src = '';
    fetch('../api/onboarding/dismiss_video.php').catch(function(){});
    var og = document.getElementById('onboardingGuide');
    if (og) {
        og.style.display = '';
        og.style.opacity = '1';
        og.style.transform = '';
    }
}

<?php if ($onboarding_video): ?>
document.body.classList.add('onboarding-video-open');
<?php endif; ?>

function dismissOnboarding() {
    var og = document.getElementById('onboardingGuide');
    if (og) {
        og.style.transition = 'transform .3s ease, opacity .3s ease';
        og.style.transform = 'translateY(30px)';
        og.style.opacity = '0';
        setTimeout(function() { og.style.display = 'none'; }, 300);
    }
    document.querySelectorAll('.onboarding-nav-highlight, .onboarding-btn-highlight').forEach(function(el) {
        el.classList.remove('onboarding-nav-highlight', 'onboarding-btn-highlight');
    });
    var hint = document.getElementById('ogBtnHint');
    if (hint) hint.remove();
}
</script>
