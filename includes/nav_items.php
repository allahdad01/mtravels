<?php
/**
 * nav_items.php
 * Sidebar navigation items partial.
 * Requires: $user, $allowed_features (both set by auth_check.php)
 *
 * Uses a tiny helper to shorten active-state checks.
 */

// Current page for active-state detection
$currentPage = basename($_SERVER['PHP_SELF']);

// Communication add-on state (used for SMTP-linked menu items).
$has_smtp_addon = false;
if (isset($pdo, $tenant_id)) {
    require_once __DIR__ . '/CommunicationAddonManager.php';
    $communicationAddonManager = new CommunicationAddonManager($pdo, (int) $tenant_id);
    $has_smtp_addon = $communicationAddonManager->hasActiveAddon((int) $tenant_id, 'smtp');
}

/**
 * Returns 'active' when the current page is in the given list.
 */
if (!function_exists('navActive')) {
    function navActive(string ...$pages): string {
        global $currentPage;
        return in_array($currentPage, $pages, true) ? 'active' : '';
    }
}

/**
 * Returns 'active pcoded-trigger' when the current page is in the given list.
 */
if (!function_exists('navTrigger')) {
    function navTrigger(string ...$pages): string {
        global $currentPage;
        return in_array($currentPage, $pages, true) ? 'active pcoded-trigger' : '';
    }
}
?>

<!-- Navigation caption -->
<li class="nav-item pcoded-menu-caption">
    <label><?= __('navigation') ?></label>
</li>

<!-- Dashboard -->
<li class="nav-item <?= navActive('dashboard.php') ?>">
    <a href="dashboard.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
        <span class="pcoded-mtext"><?= __('dashboard') ?></span>
    </a>
</li>

<!-- Pages caption -->
<li class="nav-item pcoded-menu-caption">
    <label><?= __('pages') ?></label>
</li>

<!-- ── Chat ──────────────────────────────────────────────────────────── -->
<?php if (hasFeature('inter_tenant_chat', $allowed_features) && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item pcoded-hasmenu <?= navTrigger('chat.php', 'send_messages.php', 'chat_settings.php', 'tenant_peering.php', 'branch_peering.php') ?>">
    <a href="javascript:" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-message-circle"></i></span>
        <span class="pcoded-mtext"><?= __('chat') ?></span>
    </a>
    <ul class="pcoded-submenu">
        <li class="<?= navActive('chat.php') ?>">
            <a href="../chat.php"><i class="feather icon-users"></i> <?= __('chat') ?></a>
        </li>
        <li class="<?= navActive('chat_settings.php') ?>">
            <a href="chat_settings.php"><i class="feather icon-settings"></i> Chat Settings</a>
        </li>
        <li class="<?= navActive('tenant_peering.php') ?>">
            <a href="tenant_peering.php"><i class="feather icon-users"></i> Tenant Peering</a>
        </li>
        <li class="<?= navActive('branch_peering.php') ?>">
            <a href="branch_peering.php"><i class="feather icon-users"></i> Branch Peering</a>
        </li>
    </ul>
</li>
<?php endif; ?>

<!-- ── Accounts ──────────────────────────────────────────────────────── -->
<?php if (staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('accounts.php') ?>">
    <a href="accounts.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-briefcase"></i></span>
        <span class="pcoded-mtext"><?= __('accounts') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── Debtors ───────────────────────────────────────────────────────── -->
<?php if (hasFeature('debtors', $allowed_features) && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('debtors.php') ?>">
    <a href="debtors.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
        <span class="pcoded-mtext"><?= __('debtors') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── Creditors ─────────────────────────────────────────────────────── -->
<?php if (hasFeature('creditors', $allowed_features) && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('creditors.php') ?>">
    <a href="creditors.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
        <span class="pcoded-mtext"><?= __('creditors') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── Sarafi ────────────────────────────────────────────────────────── -->
<?php if (hasFeature('sarafi', $allowed_features) && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('sarafi.php') ?>">
    <a href="sarafi.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-credit-card"></i></span>
        <span class="pcoded-mtext"><?= __('sarafi') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── Salary ────────────────────────────────────────────────────────── -->
<?php if (hasFeature('salary', $allowed_features)): ?>
    <?php if (staffCanSeeMenu($user['role'])): ?>
    <!-- Admin / manager: full salary submenu -->
    <li class="nav-item pcoded-hasmenu <?= navTrigger('salary_management.php', 'salary_payment.php', 'salary_payments.php') ?>">
        <a href="javascript:" class="nav-link">
            <span class="pcoded-micon"><i class="fas fa-dollar-sign"></i></span>
            <span class="pcoded-mtext"><?= __('salary_management') ?></span>
        </a>
        <ul class="pcoded-submenu">
            <li class="<?= navActive('salary_management.php') ?>">
                <a href="salary_management.php"><?= __('employee_salaries') ?></a>
            </li>
            <li class="<?= navActive('salary_payment.php') ?>">
                <a href="salary_payment.php"><?= __('process_payment') ?></a>
            </li>
            <li class="<?= navActive('salary_payments.php') ?>">
                <a href="salary_payments.php"><i class="feather icon-user mr-2"></i><?= __('my_payments') ?></a>
            </li>
        </ul>
    </li>
    <?php else: ?>
    <!-- Staff: My Payments only -->
    <li class="nav-item <?= navActive('salary_payments.php') ?>">
        <a href="salary_payments.php" class="nav-link">
            <span class="pcoded-micon"><i class="fas fa-dollar-sign"></i></span>
            <span class="pcoded-mtext"><?= __('my_payments') ?></span>
        </a>
    </li>
    <?php endif; ?>
<?php endif; ?>

<!-- ── HR Management ─────────────────────────────────────────────────── -->
<?php if (hasFeature('salary', $allowed_features)): ?>
    <?php
    $hrPages = ['hr.php', 'employee_management.php', 'employee_performance.php',
                'attendance.php', 'manage_attendance.php', 'attendance_settings.php',
                'hr_reports.php', 'add_employee.php', 'edit_employee.php', 'employee_details.php'];
    ?>
    <?php if (staffCanSeeMenu($user['role'])): ?>
    <li class="nav-item pcoded-hasmenu <?= navTrigger(...$hrPages) ?>">
        <a href="javascript:" class="nav-link">
            <span class="pcoded-micon"><i class="feather icon-users"></i></span>
            <span class="pcoded-mtext"><?= __('hr_management') ?></span>
        </a>
        <ul class="pcoded-submenu">
            <li class="<?= navActive('hr.php') ?>">
                <a href="hr.php"><?= __('hr_dashboard') ?></a>
            </li>
            <li class="<?= navActive('employee_management.php') ?>">
                <a href="employee_management.php"><?= __('employee_management') ?></a>
            </li>
            <li class="<?= navActive('employee_performance.php') ?>">
                <a href="employee_performance.php"><?= __('performance_reviews') ?></a>
            </li>
            <?php if (hasFeature('attendance', $allowed_features)): ?>
            <li class="<?= navActive('attendance.php') ?>">
                <a href="attendance.php"><i class="feather icon-clock mr-2"></i><?= __('my_attendance') ?></a>
            </li>
            <li class="<?= navActive('manage_attendance.php') ?>">
                <a href="manage_attendance.php"><i class="feather icon-calendar mr-2"></i><?= __('manage_attendance') ?></a>
            </li>
            <li class="<?= navActive('attendance_settings.php') ?>">
                <a href="attendance_settings.php"><i class="feather icon-settings mr-2"></i><?= __('attendance_settings') ?></a>
            </li>
            <?php endif; ?>
        </ul>
    </li>
    <?php else: ?>
    <!-- Staff: My Attendance only -->
    <li class="nav-item <?= navActive('attendance.php') ?>">
        <a href="attendance.php" class="nav-link">
            <span class="pcoded-micon"><i class="feather icon-clock"></i></span>
            <span class="pcoded-mtext"><?= __('my_attendance') ?></span>
        </a>
    </li>
    <?php endif; ?>
<?php endif; ?>

<!-- ── Ticket Management ──────────────────────────────────────────────── -->
<?php
$showTickets = hasFeature('ticket_bookings',     $allowed_features)
           || hasFeature('ticket_reservations',  $allowed_features)
           || hasFeature('refunded_tickets',      $allowed_features)
           || hasFeature('date_change_tickets',   $allowed_features)
           || hasFeature('ticket_weights',        $allowed_features);
?>
<?php if ($showTickets && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item pcoded-hasmenu <?= navTrigger('ticket.php','refund_ticket.php','date_change.php','ticket_reserve.php','ticket_weights.php') ?>">
    <a href="javascript:" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-calendar"></i></span>
        <span class="pcoded-mtext"><?= __('ticket_management') ?></span>
    </a>
    <ul class="pcoded-submenu">
        <?php if (hasFeature('ticket_bookings', $allowed_features)): ?>
        <li class="<?= navActive('ticket.php') ?>">
            <a href="ticket.php"><?= __('book_tickets') ?></a>
        </li>
        <?php endif; ?>
        <?php if (hasFeature('refunded_tickets', $allowed_features)): ?>
        <li class="<?= navActive('refund_ticket.php') ?>">
            <a href="refund_ticket.php"><?= __('refund_tickets') ?></a>
        </li>
        <?php endif; ?>
        <?php if (hasFeature('date_change_tickets', $allowed_features)): ?>
        <li class="<?= navActive('date_change.php') ?>">
            <a href="date_change.php"><?= __('date_changed_tickets') ?></a>
        </li>
        <?php endif; ?>
        <?php if (hasFeature('ticket_weights', $allowed_features)): ?>
        <li class="<?= navActive('ticket_weights.php') ?>">
            <a href="ticket_weights.php"><?= __('ticket_weights') ?></a>
        </li>
        <?php endif; ?>
        <?php if (hasFeature('ticket_reservations', $allowed_features)): ?>
        <li class="<?= navActive('ticket_reserve.php') ?>">
            <a href="ticket_reserve.php"><?= __('ticket_reservations') ?></a>
        </li>
        <?php endif; ?>
    </ul>
</li>
<?php endif; ?>

<!-- ── Hotel Management ───────────────────────────────────────────────── -->
<?php
$showHotel = hasFeature('hotel_bookings', $allowed_features) || hasFeature('hotel_refunds', $allowed_features);
?>
<?php if ($showHotel && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item pcoded-hasmenu <?= navTrigger('hotel.php', 'hotel_refunds.php') ?>">
    <a href="javascript:" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-home"></i></span>
        <span class="pcoded-mtext"><?= __('hotel_management') ?></span>
    </a>
    <ul class="pcoded-submenu">
        <?php if (hasFeature('hotel_bookings', $allowed_features)): ?>
        <li class="<?= navActive('hotel.php') ?>">
            <a href="hotel.php"><?= __('hotel_bookings') ?></a>
        </li>
        <?php endif; ?>
        <?php if (hasFeature('hotel_refunds', $allowed_features)): ?>
        <li class="<?= navActive('hotel_refunds.php') ?>">
            <a href="hotel_refunds.php"><?= __('hotel_refund') ?></a>
        </li>
        <?php endif; ?>
    </ul>
</li>
<?php endif; ?>

<!-- ── Umrah Management ───────────────────────────────────────────────── -->
<?php if (hasFeature('umrah_bookings', $allowed_features) && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item pcoded-hasmenu <?= navTrigger('umrah.php','umrah_services.php','umrah_refunds.php','umrah_date_changes.php') ?>">
    <a href="javascript:" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-map"></i></span>
        <span class="pcoded-mtext"><?= __('umrah_management') ?></span>
    </a>
    <ul class="pcoded-submenu">
        <li class="<?= navActive('umrah.php') ?>">
            <a href="umrah.php"><?= __('umrah_bookings') ?></a>
        </li>
        <li class="<?= navActive('umrah_services.php') ?>">
            <a href="umrah_services.php"><?= __('umrah_services') ?></a>
        </li>
        <?php if (hasFeature('umrah_refunds', $allowed_features)): ?>
        <li class="<?= navActive('umrah_refunds.php') ?>">
            <a href="umrah_refunds.php"><?= __('umrah_refunds') ?></a>
        </li>
        <?php endif; ?>
        <li class="<?= navActive('umrah_date_changes.php') ?>">
            <a href="umrah_date_changes.php"><?= __('umrah_date_changes') ?></a>
        </li>
    </ul>
</li>
<?php endif; ?>

<!-- ── Visa Management ────────────────────────────────────────────────── -->
<?php
$showVisa = hasFeature('visa_applications', $allowed_features) || hasFeature('visa_refunds', $allowed_features);
?>
<?php if ($showVisa && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item pcoded-hasmenu <?= navTrigger('visa.php', 'visa_refunds.php') ?>">
    <a href="javascript:" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-globe"></i></span>
        <span class="pcoded-mtext"><?= __('visa_management') ?></span>
    </a>
    <ul class="pcoded-submenu">
        <?php if (hasFeature('visa_applications', $allowed_features)): ?>
        <li class="<?= navActive('visa.php') ?>">
            <a href="visa.php"><?= __('visa_bookings') ?></a>
        </li>
        <?php endif; ?>
        <?php if (hasFeature('visa_refunds', $allowed_features)): ?>
        <li class="<?= navActive('visa_refunds.php') ?>">
            <a href="visa_refunds.php"><?= __('visa_refund') ?></a>
        </li>
        <?php endif; ?>
    </ul>
</li>
<?php endif; ?>

<!-- ── Additional Payments ────────────────────────────────────────────── -->
<?php if (hasFeature('additional_payments', $allowed_features) && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('additional_payments.php') ?>">
    <a href="additional_payments.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
        <span class="pcoded-mtext"><?= __('additional_payments') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── JV Payments ────────────────────────────────────────────────────── -->
<?php if (hasFeature('jv_payments', $allowed_features) && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('jv_payments.php') ?>">
    <a href="jv_payments.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
        <span class="pcoded-mtext"><?= __('jv_payments') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── Manage Letters (Maktobs) ───────────────────────────────────────── -->
<?php if (hasFeature('manage_maktobs', $allowed_features) && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('manage_maktobs.php') ?>">
    <a href="manage_maktobs.php" class="nav-link">
        <span class="pcoded-micon"><i class="fas fa-file-alt"></i></span>
        <span class="pcoded-mtext"><?= __('manage_letters') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── Assets ────────────────────────────────────────────────────────── -->
<?php if (hasFeature('assets', $allowed_features) && staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('assets.php') ?>">
    <a href="assets.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
        <span class="pcoded-mtext"><?= __('assets') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── Supplier / Client ──────────────────────────────────────────────── -->
<?php if (staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('supplier.php') ?>">
    <a href="supplier.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-users"></i></span>
        <span class="pcoded-mtext"><?= __('supplier') ?></span>
    </a>
</li>
<li class="nav-item <?= navActive('client.php') ?>">
    <a href="client.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-user"></i></span>
        <span class="pcoded-mtext"><?= __('client') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── Expense Management ────────────────────────────────────────────── -->
<?php if (staffCanSeeMenu($user['role'])): ?>
<li class="nav-item pcoded-hasmenu <?= navTrigger('expense_management.php', 'budget_allocations.php', 'global_budget_allocation.php') ?>">
    <a href="javascript:" class="nav-link">
        <span class="pcoded-micon"><i class="fas fa-dollar-sign"></i></span>
        <span class="pcoded-mtext"><?= __('expense_management') ?></span>
    </a>
    <ul class="pcoded-submenu">
        <li class="<?= navActive('expense_management.php') ?>">
            <a href="expense_management.php"><i class="feather icon-credit-card mr-2"></i><?= __('expense_management') ?></a>
        </li>
        <li class="<?= navActive('budget_allocations.php') ?>">
            <a href="budget_allocations.php"><i class="feather icon-trending-up mr-2"></i>Budget Allocation</a>
        </li>
        <li class="<?= navActive('global_budget_allocation.php') ?>">
            <a href="global_budget_allocation.php"><i class="feather icon-globe mr-2"></i>Global Budget Allocation</a>
        </li>
    </ul>
</li>
<?php endif; ?>

<!-- ── Finance Tracker (Finance Admin Only) ────────────────────────── -->
<?php if ($user['role'] === 'finance'): ?>
<li class="nav-item <?= navActive('finance_tracker.php') ?>">
    <a href="finance_tracker.php" class="nav-link">
        <span class="pcoded-micon"><i class="fas fa-chart-line"></i></span>
        <span class="pcoded-mtext">Finance Wallet</span>
    </a>
</li>
<?php endif; ?>

<!-- ── Reports ────────────────────────────────────────────────────────── -->
<?php if (staffCanSeeMenu($user['role'])): ?>
<li class="nav-item pcoded-hasmenu <?= navTrigger('report.php', 'quarterly_tax_report.php') ?>">
    <a href="javascript:" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-file"></i></span>
        <span class="pcoded-mtext"><?= __('reports') ?></span>
    </a>
    <ul class="pcoded-submenu">
        <li class="<?= navActive('report.php') ?>">
            <a href="report.php"><?= __('reports') ?></a>
        </li>
        <?php if ($user['role'] === 'admin'): ?>
        <li class="<?= navActive('quarterly_tax_report.php') ?>">
            <a href="quarterly_tax_report.php"><i class="feather icon-calendar mr-2"></i>Quarterly Tax Report</a>
        </li>
        <?php endif; ?>
    </ul>
</li>
<?php endif; ?>

<!-- ── Excel Import ───────────────────────────────────────────────────── -->
<?php if (staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('excel_import.php') ?>">
    <a href="excel_import.php" class="nav-link">
        <span class="pcoded-micon"><i class="fas fa-file-excel"></i></span>
        <span class="pcoded-mtext"><?= __('excel_import') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── 2FA ────────────────────────────────────────────────────────────── -->
<li class="nav-item <?= navActive('totp.php') ?>">
    <a href="../totp_setup.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-shield"></i></span>
        <span class="pcoded-mtext"><?= __('2fa') ?></span>
    </a>
</li>

<!-- ── Search ────────────────────────────────────────────────────────── -->
<?php if (staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('search.php') ?>">
    <a href="search.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-search"></i></span>
        <span class="pcoded-mtext"><?= __('search') ?></span>
    </a>
</li>
<?php endif; ?>

<!-- ── Activity Log / Email Analytics ───────────────────────────────── -->
<?php if (staffCanSeeMenu($user['role'])): ?>
<li class="nav-item <?= navActive('activity_log.php') ?>">
    <a href="activity_log.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-activity"></i></span>
        <span class="pcoded-mtext"><?= __('activity_log') ?></span>
    </a>
</li>
<?php if ($has_smtp_addon): ?>
<li class="nav-item <?= navActive('email_analytics.php') ?>">
    <a href="email_analytics.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-mail"></i></span>
        <span class="pcoded-mtext"><?= __('email_analytics') ?></span>
    </a>
</li>
<?php endif; ?>
<?php endif; ?>

<!-- ── Support Tickets ────────────────────────────────────────────────── -->
<?php if (staffCanSeeMenu($user['role'])): ?>
<li class="nav-item pcoded-hasmenu <?= navTrigger('support_tickets.php','support_ticket_create.php','support_ticket_detail.php') ?>">
    <a href="javascript:" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-help-circle"></i></span>
        <span class="pcoded-mtext"><?= __('support_tickets') ?></span>
    </a>
    <ul class="pcoded-submenu">
        <li class="<?= navActive('support_tickets.php', 'support_ticket_detail.php') ?>">
            <a href="support_tickets.php"><?= __('my_tickets') ?></a>
        </li>
        <li class="<?= navActive('support_ticket_create.php') ?>">
            <a href="support_ticket_create.php"><?= __('submit_new_ticket') ?></a>
        </li>
    </ul>
</li>

<!-- ── Tutorials ──────────────────────────────────────────────────────── -->
<li class="nav-item <?= navActive('tutorial.php') ?>">
    <a href="tutorial.php" class="nav-link">
        <span class="pcoded-micon"><i class="feather icon-book"></i></span>
        <span class="pcoded-mtext"><?= __('tutorials') ?></span>
    </a>
</li>
<?php endif; ?>
