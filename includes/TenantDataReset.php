<?php
/**
 * Tenant data reset helper.
 *
 * Wipes all tenant business/operational records while preserving
 * configuration, billing, security and audit data.
 *
 * FK constraints between wiped tables (e.g. salary_payments -> main_account)
 * are handled by disabling FOREIGN_KEY_CHECKS for the duration of the wipe.
 * Child tables that carry no tenant_id column (chat_group_members,
 * chat_group_messages, chat_group_message_reads, whatsapp_delivery_status,
 * ticket_notifications, chat_messages) are cleaned via a cascade pass keyed
 * by their wiped parent ids.
 *
 * Tables that are NEVER touched:
 *   - Billing:        tenants, plans, tenant_subscriptions, subscription_payments,
 *                     payment_sessions, *_addons, *_addon_payments, *_addon_requests,
 *                     sales_agent_tenants
 *   - Access/config:  users, settings, branches, tenant_templates, exchange_rates,
 *                     destinations, expense_categories, expense_report_config,
 *                     attendance_settings, salary_management, ticket_weights,
 *                     ticket_sla_rules, ticket_categories, visa_document_types,
 *                     whatsapp_settings, whatsapp_templates, branch_chat_settings
 *   - Security/audit: audit_logs, branch_audit_log, chat_audit_log, chat_audit_log_archive,
 *                     security_audit_log, activity_log, login_history, login_attempts,
 *                     password_resets, password_reset_requests, totp_secrets,
 *                     totp_recovery_codes, encryption_keys, encryption_audit,
 *                     encryption_key_rotations, ip_blacklist, rate_limits,
 *                     rate_limit_violations, advanced_rate_limits,
 *                     advanced_rate_limit_requests, user_online_sessions
 *   - Global/system:  platform_settings, api_versions, ssl_certificates,
 *                     system_revenue, system_profit_loss_reports,
 *                     system_profit_loss_by_category, tax_reports,
 *                     tax_report_specifications, notifications, email_tracking,
 *                     contact_messages, demo_requests, custom_plan_requests,
 *                     v_tickets_with_sla (view - cannot delete)
 */

if (!function_exists('getTenantWipeTables')) {
    /**
     * Returns all tables having a `tenant_id` column that should be wiped,
     * excluding the protected tables listed above.
     */
    function getTenantWipeTables(PDO $pdo): array
    {
        $protected = [
            // Billing / subscriptions
            'tenants', 'plans', 'platform_settings', 'tenant_subscriptions',
            'subscription_payments', 'payment_sessions',
            'branch_addons', 'branch_addon_payments', 'branch_addon_requests',
            'user_addons', 'user_addon_payments', 'user_addon_requests',
            'communication_addons', 'communication_addon_requests',
            'sales_agent_tenants',
            // Access / configuration
            'users', 'settings', 'branches', 'tenant_templates',
            'exchange_rates', 'destinations', 'expense_categories',
            'expense_report_config', 'attendance_settings', 'salary_management',
            'ticket_weights', 'ticket_sla_rules', 'ticket_categories',
            'visa_document_types', 'whatsapp_settings', 'whatsapp_templates',
            'branch_chat_settings',
            // Security / audit
            'audit_logs', 'branch_audit_log', 'chat_audit_log',
            'chat_audit_log_archive', 'security_audit_log', 'activity_log',
            'login_history', 'login_attempts', 'password_resets',
            'password_reset_requests', 'totp_secrets', 'totp_recovery_codes',
            'encryption_keys', 'encryption_audit', 'encryption_key_rotations',
            'ip_blacklist', 'rate_limits', 'rate_limit_violations',
            'advanced_rate_limits', 'advanced_rate_limit_requests',
            'user_online_sessions', 'user_typing_status', 'user_mutes',
            'user_blocks',
            // Global / system
            'api_versions', 'ssl_certificates', 'system_revenue',
            'system_profit_loss_reports', 'system_profit_loss_by_category',
            'tax_reports', 'tax_report_specifications', 'notifications',
            'email_tracking', 'contact_messages', 'demo_requests',
            'custom_plan_requests', 'v_tickets_with_sla',
        ];

        $stmt = $pdo->query(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'tenant_id'
             ORDER BY TABLE_NAME"
        );
        $tables = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (!in_array($table, $protected, true)) {
                $tables[] = $table;
            }
        }
        return $tables;
    }
}

if (!function_exists('wipeTenantData')) {
    /**
     * Deletes all business records for the given tenant across every
     * wipeable table. Runs inside a transaction - if any delete fails,
     * nothing is changed.
     *
     * @return array{table: int, total: int} table => rows deleted
     * @throws Exception
     */
    function wipeTenantData(PDO $pdo, int $tenant_id): array
    {
        $report = ['tables' => [], 'total' => 0];

        $pdo->beginTransaction();
        try {
            // FK constraints exist between wiped tables (e.g. salary_payments -> main_account).
            // Since the whole tenant dataset is removed at once, checks are disabled for the wipe.
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            // Cascade cleanup: child tables that have no tenant_id column but
            // belong to wiped parents (keyed by parent id). Must run BEFORE the
            // parent tables are deleted so the subqueries still see them.
            $cascade = [
                ['chat_group_members', 'DELETE FROM chat_group_members WHERE group_id IN (SELECT id FROM chat_groups WHERE tenant_id = ?)', [$tenant_id]],
                ['chat_group_messages', 'DELETE FROM chat_group_messages WHERE group_id IN (SELECT id FROM chat_groups WHERE tenant_id = ?)', [$tenant_id]],
                ['chat_group_message_reads', 'DELETE FROM chat_group_message_reads WHERE message_id IN (SELECT id FROM chat_group_messages WHERE group_id IN (SELECT id FROM chat_groups WHERE tenant_id = ?))', [$tenant_id]],
                ['whatsapp_delivery_status', 'DELETE FROM whatsapp_delivery_status WHERE message_id IN (SELECT id FROM whatsapp_messages WHERE tenant_id = ?)', [$tenant_id]],
                ['ticket_notifications', 'DELETE FROM ticket_notifications WHERE ticket_id IN (SELECT id FROM support_tickets WHERE tenant_id = ?)', [$tenant_id]],
                ['chat_messages', 'DELETE FROM chat_messages WHERE tenant_id_from = ?', [$tenant_id]],
            ];
            foreach ($cascade as [$table, $sql, $params]) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->rowCount();
                if ($rows > 0) {
                    $report['tables'][$table] = $rows;
                    $report['total'] += $rows;
                }
            }

            foreach (getTenantWipeTables($pdo) as $table) {
                $safe_table = preg_replace('/[^a-z0-9_]/i', '', $table);
                if ($safe_table === '') {
                    continue;
                }
                $stmt = $pdo->prepare("DELETE FROM `{$safe_table}` WHERE tenant_id = ?");
                $stmt->execute([$tenant_id]);
                $rows = $stmt->rowCount();
                if ($rows > 0) {
                    $report['tables'][$table] = $rows;
                    $report['total'] += $rows;
                }
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $report;
    }
}