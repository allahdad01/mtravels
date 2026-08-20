<?php
/**
 * permissions.php
 * Granular per-user permission system layered on top of role-based access.
 *
 * Model:
 *   - super_admin / tenant_super_admin / admin always have full access (bypass).
 *   - Any other user keeps their role's DEFAULT permission set until an admin
 *     customizes them in the Users panel. Once customized, ONLY the granted
 *     keys apply (deny-by-default). Role defaults stop applying.
 *   - Custom permissions are stored in the `user_permissions` table.
 *
 * Usage (once wired into pages/menus):
 *   user_can('tickets.delete');        // current session user
 *   user_can_all(['umrah.view', 'umrah.edit']);
 *   user_can_for($user_id, $tenant_id, 'users.edit');
 */

if (!function_exists('permission_catalog')) {
    /**
     * Catalog of grantable permission keys, grouped by module for the UI.
     * EVERY entry is a single, distinct action — nothing is bundled.
     * Keys are "<module>.<action>"; the special 'module' entry holds the
     * key prefix for the group.
     */
    function permission_catalog(): array {
        return [
            'Dashboard' => [
                'module' => 'dashboard',
                'view'   => 'Access dashboard',
            ],
            'Users & Access' => [
                'module' => 'users',
                'view'        => 'Access user management',
                'create'      => 'Create users',
                'edit'        => 'Edit users',
                'permissions' => 'Manage permissions',
                'delete'      => 'Delete users',
            ],
            'Tickets' => [
                'module' => 'tickets',
                'view'        => 'Access tickets',
                'book'        => 'Book tickets',
                'edit'        => 'Edit tickets',
                'date_change' => 'Date-change tickets',
                'weights'     => 'Ticket weights',
                'reserve'     => 'Reserve tickets',
                'refund'      => 'Refund tickets',
                'transactions'=> 'Manage ticket payments',
                'delete'      => 'Delete tickets',
            ],
            'Hotels' => [
                'module' => 'hotels',
                'view'   => 'Access hotels',
                'book'   => 'Book hotels',
                'edit'   => 'Edit hotels',
                'refund' => 'Refund hotels',
                'delete' => 'Delete hotels',
                'transactions'=> 'Manage hotel payments',
            ],
            'Umrah' => [
                'module' => 'umrah',
                'view'             => 'Access umrah',
                'member_create'    => 'Create members',
                'member_edit'      => 'Edit members',
                'payment_record'   => 'Record payments',
                'fulfill'          => 'Fulfill services',
                'package_manage'   => 'Manage packages',
                'service_manage'   => 'Manage services',
                'hotel_manage'     => 'Manage hotels',
                'transport_manage' => 'Manage transport',
                'finance_view'     => 'View umrah finance',
                'reports_view'     => 'View umrah reports',
                'refund'           => 'Umrah refunds',
                'delete'           => 'Delete umrah records',
            ],
            'Visa' => [
                'module' => 'visa',
                'view'   => 'Access visa',
                'create' => 'Create visa bookings',
                'edit'   => 'Edit visa bookings',
                'refund' => 'Refund visas',
                'delete' => 'Delete visas',
            ],
            'Finance' => [
                'module'             => 'finance',
                'view'               => 'Access finance pages',
                'accounts'           => 'Accounts',
                'payments'           => 'Payments journal',
                'debtors'            => 'Debtors',
                'creditors'          => 'Creditors',
                'sarafi'             => 'Sarafi',
                'expenses'           => 'Expenses',
                'budget'             => 'Budget allocations',
                'additional_payments'=> 'Additional payments',
                'jv'                 => 'JV payments',
                'cash_settlement'    => 'Cash settlement',
                'cash_settlement_approve' => 'Approve cash settlements',
                'wallet'             => 'Finance wallet',
                'edit'               => 'Edit finance entries',
                'delete'             => 'Delete finance records',
            ],
            'Reports' => [
                'module'   => 'reports',
                'view'     => 'Access reports',
                'generate' => 'Generate reports',
                'export'   => 'Export reports',
                'tax'      => 'Quarterly tax report',
                'delete'   => 'Delete reports',
            ],
            'HR & Payroll' => [
                'module'             => 'hr',
                'view'               => 'Access HR pages',
                'employees'          => 'Employee management',
                'performance'        => 'Performance reviews',
                'attendance'         => 'Attendance (manage)',
                'attendance_settings'=> 'Attendance settings',
                'salary'             => 'Salary management',
                'salary_pay'         => 'Process salary payments',
                'letters'            => 'Employee letters',
                'reports'            => 'HR reports',
                'terminate'          => 'Terminate employees',
                'delete'             => 'Delete employee records',
            ],
            'Operations' => [
                'module'       => 'operations',
                'view'         => 'Access operations pages',
                'suppliers'    => 'Suppliers',
                'clients'      => 'Clients',
                'maktobs'      => 'Manage letters (Maktobs)',
                'assets'       => 'Assets',
                'excel_import' => 'Excel import',
                'search'       => 'Search',
                'edit'         => 'Edit operations records',
                'delete'       => 'Delete operations records',
            ],
            'Communication' => [
                'module'        => 'communication',
                'view'          => 'Access chat & email',
                'chat_settings' => 'Chat settings',
                'peering'       => 'Tenant/branch peering',
                'email'         => 'Email analytics',
                'delete'        => 'Delete messages',
            ],
            'Security & Monitoring' => [
                'module'   => 'security',
                'view'     => 'Access activity log & audit',
                'settings' => 'Security settings',
                'delete'   => 'Delete security records',
            ],
            'Support' => [
                'module' => 'support',
                'view'   => 'Access support tickets & tutorials',
                'manage' => 'Manage support tickets',
                'delete' => 'Delete support tickets',
            ],
        ];
    }
}

if (!function_exists('permission_keys')) {
    /**
     * Flat list of every grantable permission key (for endpoint validation).
     */
    function permission_keys(): array {
        $keys = [];
        foreach (permission_catalog() as $cfg) {
            $module = $cfg['module'] ?? null;
            if (!$module) {
                continue;
            }
            foreach ($cfg as $suffix => $label) {
                if ($suffix === 'module') {
                    continue;
                }
                $keys[] = $module . '.' . $suffix;
            }
        }
        return $keys;
    }
}

if (!function_exists('role_default_permissions')) {
    /**
     * Default permission set for a role. Applied only when the user has NO
     * custom permission rows (i.e. the admin never customized them).
     */
    function role_default_permissions(?string $role = null): array {
        $role = $role ?? ($_SESSION['role'] ?? '');
        $full = permission_keys();

        $byModule = function (string $module) use ($full): array {
            return array_values(array_filter(
                $full,
                function (string $k) use ($module): bool {
                    return substr($k, 0, strlen($module) + 1) === $module . '.';
                }
            ));
        };

        $map = [
            'super_admin'       => $full,
            'tenant_super_admin'=> $full,
            'admin'             => $full,
            'finance' => [
                'dashboard.view',
                'tickets.view', 'tickets.book', 'tickets.edit', 'tickets.date_change',
                'tickets.weights', 'tickets.reserve', 'tickets.refund', 'tickets.delete',
                'tickets.transactions',
                'hotels.view', 'hotels.book', 'hotels.edit', 'hotels.refund', 'hotels.delete',
                'hotels.transactions',
                'umrah.view', 'umrah.member_create', 'umrah.member_edit',
                'umrah.payment_record', 'umrah.finance_view', 'umrah.reports_view',
                'umrah.refund',
                'visa.view', 'visa.create', 'visa.edit', 'visa.refund', 'visa.delete',
                'finance.view', 'finance.accounts', 'finance.payments', 'finance.debtors',
                'finance.creditors', 'finance.sarafi', 'finance.expenses', 'finance.budget',
                'finance.additional_payments', 'finance.jv', 'finance.cash_settlement',
                'finance.cash_settlement_approve',
                'finance.wallet', 'finance.edit', 'finance.delete',
                'reports.view', 'reports.generate',
                'hr.salary', 'hr.salary_pay', 'hr.delete',
                'operations.suppliers', 'operations.clients', 'operations.assets',
                'operations.maktobs', 'operations.search', 'operations.edit',
                'operations.excel_import',
                'communication.view', 'communication.chat_settings', 'communication.peering',
                'communication.email',
                'support.view',
            ],
            'sales' => [
                'dashboard.view',
                'tickets.view', 'tickets.book', 'tickets.edit', 'tickets.date_change',
                'tickets.weights', 'tickets.reserve', 'tickets.refund', 'tickets.delete',
                'tickets.transactions',
                'hotels.view', 'hotels.book', 'hotels.edit', 'hotels.refund', 'hotels.delete',
                'hotels.transactions',
                'umrah.view', 'umrah.member_create', 'umrah.member_edit',
                'umrah.payment_record', 'umrah.refund',
                'visa.view', 'visa.create', 'visa.edit', 'visa.refund', 'visa.delete',
                'reports.view',
                'hr.salary',
                'communication.view', 'communication.email',
                'support.view',
            ],
            'umrah' => array_merge([
                'dashboard.view',
                'reports.view',
                'hr.salary',
                'communication.view', 'communication.email',
                'support.view',
            ], $byModule('umrah')),
            'operations' => [
                'dashboard.view',
                'communication.view',
            ],
            'hotel_manager' => [
                'dashboard.view',
                'communication.view',
            ],
            'staff' => [
                'dashboard.view',
                'hr.attendance',
                'hr.salary',
                'communication.view', 'communication.email',
            ],
            'viewer' => [
                'dashboard.view',
                'communication.view',
            ],
            'client' => [
                'dashboard.view',
            ],
        ];

        return $map[$role] ?? [];
    }
}

if (!function_exists('user_custom_permissions')) {
    /**
     * Granted custom permission keys for a user.
     *
     * @return array|null  Array of granted keys, or NULL when the user has no
     *                     custom rows at all (role defaults still apply).
     */
    function user_custom_permissions(?int $user_id = null, ?int $tenant_id = null): ?array {
        static $cached_user = null, $cached_tenant = null, $cached_result = null, $loaded = false;

        $user_id   = $user_id   ?? ($_SESSION['user_id'] ?? 0);
        $tenant_id = $tenant_id ?? ($_SESSION['tenant_id'] ?? 0);

        if ($loaded && $cached_user === $user_id && $cached_tenant === $tenant_id) {
            return $cached_result;
        }

        $cached_user   = $user_id;
        $cached_tenant = $tenant_id;
        $loaded        = true;
        $cached_result = null;

        if (!$user_id || !$tenant_id) {
            return $cached_result;
        }

        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            require_once __DIR__ . '/db.php';
            if (isset($pdo) && $pdo instanceof PDO) {
                $GLOBALS['pdo'] = $pdo;
            } else {
                return $cached_result;
            }
        }

        try {
            $stmt = $GLOBALS['pdo']->prepare(
                "SELECT permission_key, granted FROM user_permissions
                 WHERE user_id = ? AND tenant_id = ?"
            );
            $stmt->execute([$user_id, $tenant_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Rows exist => customized (possibly with nothing granted => []).
            // No rows    => not customized => NULL (role defaults apply).
            if (count($rows) > 0) {
                $cached_result = [];
                foreach ($rows as $row) {
                    if ((int) $row['granted'] === 1) {
                        $cached_result[] = $row['permission_key'];
                    }
                }
            }
        } catch (PDOException $e) {
            $cached_result = null;
        }

        return $cached_result;
    }
}

if (!function_exists('user_can')) {
    /**
     * True when the current session user holds the given permission.
     * super_admin / tenant_super_admin / admin always pass.
     */
    function user_can(string $key): bool {
        $role = $_SESSION['role'] ?? '';
        if (in_array($role, ['super_admin', 'tenant_super_admin', 'admin'], true)) {
            return true;
        }
        $custom = user_custom_permissions();
        if ($custom !== null) {
            return in_array($key, $custom, true);
        }
        return in_array($key, role_default_permissions($role), true);
    }
}

if (!function_exists('user_can_all')) {
    /**
     * True when the user holds ALL of the given permission keys.
     */
    function user_can_all(array $keys): bool {
        foreach ($keys as $key) {
            if (!user_can($key)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('user_can_for')) {
    /**
     * True when the given user (not the session user) holds a permission.
     * Bypass roles always pass.
     */
    function user_can_for(int $user_id, int $tenant_id, string $key): bool {
        static $cache = [];
        $cache_key = $tenant_id . ':' . $user_id;
        if (!isset($cache[$cache_key])) {
            try {
                $stmt = $GLOBALS['pdo']->prepare("SELECT role FROM users WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$user_id, $tenant_id]);
                $role = $stmt->fetchColumn();
            } catch (PDOException $e) {
                $role = false;
            }
            if (!$role) {
                return false;
            }
            if (in_array($role, ['super_admin', 'tenant_super_admin', 'admin'], true)) {
                $cache[$cache_key] = true; // sentinel: full access
            } else {
                $cache[$cache_key] = user_custom_permissions($user_id, $tenant_id) ?? role_default_permissions($role);
            }
        }
        if ($cache[$cache_key] === true) {
            return true;
        }
        return is_array($cache[$cache_key]) && in_array($key, $cache[$cache_key], true);
    }
}

if (!function_exists('permission_label')) {
    /**
     * Human label for a permission key (fallback: the key itself).
     */
    function permission_label(string $key): string {
        $parts = explode('.', $key);
        if (count($parts) === 2) {
            list($module, $suffix) = $parts;
            foreach (permission_catalog() as $cfg) {
                if (($cfg['module'] ?? null) === $module && isset($cfg[$suffix])) {
                    return $cfg[$suffix];
                }
            }
        }
        return $key;
    }
}

if (!function_exists('page_permission_map')) {
    /**
     * Maps admin page basename -> permission key required to open that page.
     * Pages absent from the map are NOT permission-gated (dashboard, profile,
     * tutorial, totp setup, index, logout, etc.).
     */
    function page_permission_map(): array {
        return [
            // Users & Access
            'users.php'                      => 'users.view',
            'save_user.php'                  => 'users.edit',
            'get_user.php'                   => 'users.view',
            'get_user_data.php'              => 'users.view',
            'delete_user.php'                => 'users.delete',
            'save_permissions.php'           => 'users.permissions',
            // Tickets
            'ticket.php'                     => 'tickets.view',
            'ticket_detail.php'              => 'tickets.view',
            'refund_ticket.php'              => 'tickets.refund',
            'date_change.php'                => 'tickets.date_change',
            'ticket_reserve.php'             => 'tickets.reserve',
            'ticket_reservation_detail.php'  => 'tickets.reserve',
            'ticket_weights.php'             => 'tickets.weights',
            // Hotels
            'hotel.php'                      => 'hotels.view',
            'hotel_detail.php'               => 'hotels.view',
            'hotel_refunds.php'              => 'hotels.refund',
            // Umrah
            'umrah.php'                      => 'umrah.view',
            'umrah_detail.php'               => 'umrah.view',
            'umrah_refunds.php'              => 'umrah.refund',
            'umrah_catalog.php'              => 'umrah.package_manage',
            'umrah_hotels.php'               => 'umrah.hotel_manage',
            'umrah_transport.php'            => 'umrah.transport_manage',
            'umrah_finance.php'              => 'umrah.finance_view',
            // Visa
            'visa.php'                       => 'visa.view',
            'visa_detail.php'                => 'visa.view',
            'visa_refunds.php'               => 'visa.refund',
            // Finance
            'accounts.php'                   => 'finance.accounts',
            'fetch_mainAccounts.php'         => 'finance.accounts',
            'get_main_account_id_by_name.php'=> 'finance.accounts',
            'payment_journal.php'            => 'finance.payments',
            'payment_detail.php'             => 'finance.payments',
            'get_payment.php'                => 'finance.payments',
            'fetch_statement.php'            => 'finance.payments',
            'debtors.php'                    => 'finance.debtors',
            'debtors_detail.php'             => 'finance.debtors',
            'creditors.php'                  => 'finance.creditors',
            'creditors_detail.php'           => 'finance.creditors',
            'sarafi.php'                     => 'finance.sarafi',
            'view_sarafi_transaction.php'    => 'finance.sarafi',
            'delete_sarafi_deposit.php'      => 'finance.sarafi',
            'delete_sarafi_exchange.php'     => 'finance.sarafi',
            'delete_sarafi_hawala.php'       => 'finance.sarafi',
            'delete_sarafi_withdrawal.php'   => 'finance.sarafi',
            'update_sarafi_deposit_transaction.php'   => 'finance.sarafi',
            'update_sarafi_exchange_transaction.php'  => 'finance.sarafi',
            'update_sarafi_hawala_transaction.php'    => 'finance.sarafi',
            'update_sarafi_withdrawal_transaction.php'=> 'finance.sarafi',
            'expense_management.php'         => 'finance.expenses',
            'expense_detail.php'             => 'finance.expenses',
            'expense_category_report.php'    => 'finance.expenses',
            'budget_allocations.php'         => 'finance.budget',
            'budget_rollover.php'            => 'finance.budget',
            'global_budget_allocation.php'   => 'finance.budget',
            'global_allocation_actions.php'  => 'finance.budget',
            'additional_payments.php'        => 'finance.additional_payments',
            'additional_payments_detail.php' => 'finance.additional_payments',
            'jv_payments.php'                => 'finance.jv',
            'get_jv_payment.php'             => 'finance.jv',
            'update_jv_payment.php'          => 'finance.jv',
            'process_client_supplier_jv.php' => 'finance.jv',
            'process_client_supplier_jv_delete.php' => 'finance.jv',
            'cash_settlement.php'            => 'finance.cash_settlement',
            'finance_tracker.php'            => 'finance.wallet',
            // Reports
            'report.php'                     => 'reports.view',
            'generate_report.php'            => 'reports.generate',
            'download_report.php'            => 'reports.export',
            'compliance_report.php'          => 'reports.view',
            'quarterly_tax_report.php'       => 'reports.tax',
            // HR & Payroll
            'hr.php'                         => 'hr.view',
            'employee_management.php'        => 'hr.employees',
            'add_employee.php'               => 'users.create',
            'edit_employee.php'              => 'hr.employees',
            'employee_details.php'           => 'hr.employees',
            'employee_performance.php'       => 'hr.performance',
            'attendance.php'                 => 'hr.attendance',
            'manage_attendance.php'          => 'hr.attendance',
            'edit_attendance.php'            => 'hr.attendance',
            'attendance_settings.php'        => 'hr.attendance_settings',
            'deduct_absence.php'             => 'hr.attendance',
            'hr_reports.php'                 => 'hr.reports',
            'salary_management.php'          => 'hr.salary',
            'salary_adjustment.php'          => 'hr.salary',
            'salary_advances.php'            => 'hr.salary',
            'salary_payment.php'             => 'hr.salary_pay',
            'salary_payments.php'            => 'hr.salary',
            'print_payroll.php'              => 'hr.salary',
            'print_salary_advance_receipt.php'=> 'hr.salary',
            'manage_bonuses.php'             => 'hr.salary',
            'edit_bonus.php'                 => 'hr.salary',
            'delete_bonus.php'               => 'hr.salary',
            'manage_deductions.php'          => 'hr.salary',
            'edit_deduction.php'             => 'hr.salary',
            'delete_deduction.php'           => 'hr.salary',
            'get_salary_details.php'         => 'hr.salary',
            'delete_salary_payment.php'      => 'hr.delete',
            'update_salary_payment.php'      => 'hr.salary',
            'fire_user.php'                  => 'hr.terminate',
            'delete_document.php'            => 'hr.delete',
            // Operations
            'supplier.php'                   => 'operations.suppliers',
            'supplier_detail.php'            => 'operations.suppliers',
            'get_supplier_transactions.php'  => 'operations.suppliers',
            'get_supplier_transactions_filter.php' => 'operations.suppliers',
            'fetch_supplier_currency.php'    => 'operations.suppliers',
            'client.php'                     => 'operations.clients',
            'client_detail.php'              => 'operations.clients',
            'customers.php'                  => 'operations.clients',
            'customer_detail.php'            => 'operations.clients',
            'fetch_client_data.php'          => 'operations.clients',
            'get_customer_id_by_name.php'    => 'operations.clients',
            'manage_maktobs.php'             => 'operations.maktobs',
            'assets.php'                     => 'operations.assets',
            'excel_import.php'               => 'operations.excel_import',
            'excel_import_handler.php'       => 'operations.excel_import',
            'generate_excel_template.php'    => 'operations.excel_import',
            'search.php'                     => 'operations.search',
            // Communication
            'chat_settings.php'              => 'communication.chat_settings',
            'tenant_peering.php'             => 'communication.peering',
            'branch_peering.php'             => 'communication.peering',
            'email_analytics.php'            => 'communication.email',
            'send_test_email.php'            => 'communication.email',
            'update_message.php'             => 'communication.view',
            'delete_message.php'             => 'communication.delete',
            // Security & Monitoring
            'activity_log.php'               => 'security.view',
            'audit_logs.php'                 => 'security.view',
            'ip_blacklist.php'               => 'security.settings',
            'rate_limits.php'                => 'security.settings',
            // Support
            'support_tickets.php'            => 'support.view',
            'support_ticket_create.php'      => 'support.view',
            'support_ticket_detail.php'      => 'support.view',
            'submit_ticket.php'              => 'support.view',
            // ── API mutation endpoints (JSON 403 when denied) ──
            // Tickets
            'save_ticket.php'                => 'tickets.book',
            'insert_ticket_record.php'       => 'tickets.book',
            'insert_ticket_record_dc.php'    => 'tickets.date_change',
            'update_ticket.php'              => 'tickets.edit',
            'add_ticket_payment.php'         => 'tickets.edit',
            'update_ticket_payment.php'      => 'tickets.transactions',
            'delete_ticket.php'              => 'tickets.delete',
            'delete_ticket_payment.php'      => 'tickets.transactions',
            'save_weight.php'                => 'tickets.weights',
            'update_weight.php'              => 'tickets.weights',
            'save_weight_transaction.php'    => 'tickets.transactions',
            'update_weight_transaction.php'  => 'tickets.transactions',
            'delete_weight.php'              => 'tickets.weights',
            'delete_weight_transaction.php'  => 'tickets.transactions',
            'get_weight_transactions.php'    => 'tickets.transactions',
            // Date change
            'add_date_change_ticket_payment.php' => 'tickets.transactions',
            'update_date_change.php'         => 'tickets.date_change',
            'update_date_change_transaction.php' => 'tickets.transactions',
            'delete_date_change_ticket_transaction.php' => 'tickets.transactions',
            'delete_ticket_dc.php'           => 'tickets.date_change',
            'get_date_change_ticket_transactions.php' => 'tickets.transactions',
            'get_date_change_ticket_bookings.php' => 'tickets.transactions',
            'get_date_change_transaction.php' => 'tickets.transactions',
            // Ticket refunds
            'add_refund_ticket_payment.php'  => 'tickets.transactions',
            'update_refund_penalties.php'    => 'tickets.refund',
            'update_refund_transaction.php'  => 'tickets.transactions',
            'delete_refund_ticket_transaction.php' => 'tickets.transactions',
            'delete_ticket_rf.php'           => 'tickets.refund',
            'get_refund_ticket_transactions.php' => 'tickets.transactions',
            'get_refund_ticket_bookings.php' => 'tickets.transactions',
            'get_refund_transaction_details.php' => 'tickets.transactions',
            // Ticket reservations
            'save_ticket_reserve.php'        => 'tickets.reserve',
            'update_ticket_reserve.php'      => 'tickets.reserve',
            'add_ticket_reserve_payment.php' => 'tickets.transactions',
            'update_ticket_reserve_payment.php' => 'tickets.transactions',
            'delete_ticket_reserve.php'      => 'tickets.reserve',
            'delete_ticket_reserve_payment.php' => 'tickets.transactions',
            'get_ticket_reserve_transactions.php' => 'tickets.transactions',
            'get_ticket_reservations.php'    => 'tickets.transactions',
            // Hotels
            'add_hotel_booking.php'          => 'hotels.book',
            'update_hotel_booking.php'       => 'hotels.edit',
            'delete_hotel_booking.php'       => 'hotels.delete',
            'add_hotel_transaction.php'      => 'hotels.transactions',
            'update_hotel_transaction.php'   => 'hotels.transactions',
            'delete_hotel_transaction.php'   => 'hotels.transactions',
            'get_hotel_transactions.php'     => 'hotels.transactions',
            'process_hotel_refund.php'       => 'hotels.refund',
            'refund_hotel_transaction.php'   => 'hotels.refund',
            'add_hotel_refund_transaction.php' => 'hotels.refund',
            'update_hotel_refund.php'        => 'hotels.refund',
            'update_refund_hotel_transaction.php' => 'hotels.refund',
            'delete_hotel_refund.php'        => 'hotels.refund',
            'delete_hotel_refund_transactions.php' => 'hotels.refund',
            // Visa
            'add_visa.php'                   => 'visa.create',
            'update_visa.php'                => 'visa.edit',
            'update_visa_payment.php'        => 'visa.edit',
            'approve_visa.php'               => 'visa.edit',
            'visa_cancellation.php'          => 'visa.edit',
            'visa_reapply.php'               => 'visa.edit',
            'add_visa_transaction.php'       => 'visa.edit',
            'update_visa_transaction.php'    => 'visa.edit',
            'delete_visa.php'                => 'visa.delete',
            'delete_visa_transaction.php'    => 'visa.delete',
            'process_visa_refund.php'        => 'visa.refund',
            'process_visa_refund_transaction.php' => 'visa.refund',
            'update_visa_refund.php'         => 'visa.refund',
            'delete_visa_refund.php'         => 'visa.refund',
            'delete_visa_refund_transaction.php' => 'visa.refund',
            // Umrah
            'add_umrah.php'                  => 'umrah.member_create',
            'add_umrah_multi.php'            => 'umrah.member_create',
            'create_family.php'              => 'umrah.member_create',
            'create_group.php'               => 'umrah.member_create',
            'update_umrah_member.php'        => 'umrah.member_edit',
            'update_family.php'              => 'umrah.member_edit',
            'update_group.php'               => 'umrah.member_edit',
            'update_umrah_payment.php'       => 'umrah.payment_record',
            'add_umrah_transaction.php'      => 'umrah.payment_record',
            'add_family_umrah_transactions.php' => 'umrah.payment_record',
            'save_fulfillment.php'           => 'umrah.fulfill',
            'save_multi_fulfillment.php'     => 'umrah.fulfill',
            'apply_supplier_to_services.php' => 'umrah.fulfill',
            'submit_date_change_request.php' => 'umrah.member_edit',
            'save_brn.php'                   => 'umrah.member_edit',
            'save_passport_document.php'     => 'umrah.member_edit',
            'upload_member_documents.php'    => 'umrah.member_edit',
            'process_cancellation_reapply.php' => 'umrah.member_edit',
            'process_bulk_cancellation_reapply.php' => 'umrah.member_edit',
            'add_umrah_refund_transactoin.php' => 'umrah.refund',
            'process_umrah_refund.php'       => 'umrah.refund',
            'update_umrah_refund.php'        => 'umrah.refund',
            'refund_umrah_transaction.php'   => 'umrah.refund',
            'update_refund_umrah_transaction.php' => 'umrah.refund',
            'delete_umrah_refund.php'        => 'umrah.refund',
            'delete_umrah_refund_transactions.php' => 'umrah.refund',
            'delete_umrah_transaction.php'   => 'umrah.delete',
            'delete_booking.php'             => 'umrah.delete',
            'delete_family.php'              => 'umrah.delete',
            'delete_group.php'               => 'umrah.delete',
            'delete_member_document.php'     => 'umrah.delete',
            'save_contract.php'              => 'umrah.hotel_manage',
            'save_hotel.php'                 => 'umrah.hotel_manage',
            'save_package.php'               => 'umrah.package_manage',
            'save_service.php'               => 'umrah.service_manage',
            'save_transport_contract.php'    => 'umrah.transport_manage',
            // Finance / Accounts
            'add_main_account.php'           => 'finance.edit',
            'edit_main_account.php'          => 'finance.edit',
            'toggle_account_status.php'      => 'finance.edit',
            'toggle_client_status.php'       => 'finance.edit',
            'toggle_supplier_status.php'     => 'finance.edit',
            'transfer_balance.php'           => 'finance.edit',
            'fund_main_account.php'          => 'finance.edit',
            'fund_supplier.php'              => 'finance.edit',
            'fundClient.php'                 => 'finance.edit',
            'withdraw_main_fund.php'         => 'finance.edit',
            'withdraw_fund.php'              => 'finance.edit',
            'withdraw_client.php'            => 'finance.edit',
            'update_transaction.php'         => 'finance.edit',
            'update_receipt.php'             => 'finance.edit',
            'delete_main_account.php'        => 'finance.delete',
            'delete_main_account_transaction.php' => 'finance.delete',
            'delete_client_transaction.php'  => 'finance.delete',
            'delete_supplier_transaction.php'=> 'finance.delete',
            'update_debtor_transaction.php'  => 'finance.edit',
            'update_creditor_transaction.php'=> 'finance.edit',
            'delete_debtor.php'              => 'finance.delete',
            'delete_debtor_transaction.php'  => 'finance.delete',
            'add_additional_payment.php'     => 'finance.edit',
            'add_additional_payment_transaction.php' => 'finance.edit',
            'update_additional_payment_base.php' => 'finance.edit',
            'update_additional_payment_transaction.php' => 'finance.edit',
            'delete_additional_payment.php'  => 'finance.delete',
            'delete_additional_payment_transaction.php' => 'finance.delete',
            'allocation_actions.php'         => 'finance.budget',
            'expense_actions.php'            => 'finance.expenses',
            'cash_settlements.php'           => 'finance.cash_settlement',
            'finance_tracker_actions.php'    => 'finance.wallet',
            // Reports
            'generateStatement.php'          => 'reports.generate',
            'export_report.php'              => 'reports.view',
            'export_statement.php'           => 'reports.export',
            // HR
            'edit_employee.php'              => 'hr.employees',
            'process_attendance.php'         => 'hr.attendance',
            'terminate_employee.php'         => 'hr.terminate',
            // Operations
            'add_clients.php'                => 'operations.edit',
            'update_client.php'              => 'operations.edit',
            'delete_client.php'              => 'operations.delete',
            'add_supplier.php'               => 'operations.edit',
            'update_supplier.php'            => 'operations.edit',
            'activate_supplier.php'          => 'operations.edit',
            'deactivate_supplier.php'        => 'operations.edit',
            'delete_supplier.php'            => 'operations.delete',
            'manage.php'                     => 'operations.maktobs',
            'update_maktob.php'              => 'operations.edit',
            'update_status.php'              => 'operations.edit',
            'delete_maktob.php'              => 'operations.delete',
            // Support / Tutorials
            'add.php'                        => 'support.manage',
            'update.php'                     => 'support.manage',
            'delete.php'                     => 'support.manage',
        ];
    }
}

if (!function_exists('require_permission')) {
    /**
     * Abort the request when the current session user lacks the given
     * permission. API/XHR requests get a JSON 403; page requests are
     * redirected to access_denied.php.
     */
    function require_permission(string $key): void {
        if (user_can($key)) {
            return;
        }
        $self    = $_SERVER['PHP_SELF'] ?? '';
        $isApi   = strpos($self, '/api/') !== false
                || (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        if ($isApi) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Access denied: missing permission']);
            exit();
        }
        header('Location: ../access_denied.php');
        exit();
    }
}

if (!function_exists('require_page_permission')) {
    /**
     * Permission gate for the current page, based on its basename.
     * No-op when the page is not in page_permission_map().
     */
    function require_page_permission(): void {
        $page = basename($_SERVER['PHP_SELF'] ?? '');
        $key  = page_permission_map()[$page] ?? null;
        if ($key) {
            require_permission($key);
        }
    }
}