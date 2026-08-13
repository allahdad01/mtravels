<?php
/**
 * Umrah Permissions — Phase 29 (umrah_plan.md)
 * Granular capability-based permissions layered on top of the existing
 * role-based enforce_auth() checks.
 *
 * Capabilities per role:
 *   admin           -> everything
 *   umrah           -> everything (Umrah Manager)
 *   sales           -> member_create, payment_record, view
 *   operations      -> fulfill (assign supplier, fulfill services), view
 *   hotel_manager   -> hotel_manage (hotels, rooms, contracts, inventory), transport_manage, view
 *   finance         -> payment_record, finance_view (payments, costs, profit, payable), view
 *   staff           -> view
 *   viewer          -> view (read-only)
 *
 * Usage:
 *   enforce_auth();                       // existing role gate
 *   umrah_require('hotel_manage');        // capability gate (JSON 403 for APIs)
 *   umrah_require('hotel_manage', 'page');// redirect for pages
 */

if (!function_exists('umrah_role_capabilities')) {
    /**
     * Capability set for a role. Unknown/read-only roles default to ['view'].
     */
    function umrah_role_capabilities(?string $role = null): array {
        $role = $role ?? ($_SESSION['role'] ?? '');
        $map = [
            'admin'         => ['view', 'member_create', 'member_edit', 'payment_record', 'fulfill', 'hotel_manage', 'transport_manage', 'finance_view', 'reports_view', 'package_manage', 'service_manage'],
            'umrah'         => ['view', 'member_create', 'member_edit', 'payment_record', 'fulfill', 'hotel_manage', 'transport_manage', 'finance_view', 'reports_view', 'package_manage', 'service_manage'],
            'sales'         => ['view', 'member_create', 'member_edit', 'payment_record'],
            'operations'    => ['view', 'fulfill'],
            'hotel_manager' => ['view', 'hotel_manage', 'transport_manage'],
            'finance'       => ['view', 'payment_record', 'finance_view', 'reports_view'],
            'staff'         => ['view'],
            'viewer'        => ['view'],
            'client'        => ['view'],
        ];
        return $map[$role] ?? ['view'];
    }
}

if (!function_exists('umrah_can')) {
    /**
     * True when the current session role holds the given capability.
     */
    function umrah_can(string $capability): bool {
        return in_array($capability, umrah_role_capabilities(), true);
    }
}

if (!function_exists('umrah_require')) {
    /**
     * Enforce a capability. Defaults to a JSON 403 (API use); pass 'page'
     * as the second argument to redirect to access_denied.php instead.
     */
    function umrah_require(string $capability, string $mode = 'json'): void {
        if (umrah_can($capability)) return;
        if ($mode === 'page') {
            header('Location: ../access_denied.php');
            exit;
        }
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: insufficient permissions.']);
        exit;
    }
}

if (!function_exists('umrah_roles_with')) {
    /**
     * Roles that hold a given capability (for nav filtering).
     */
    function umrah_roles_with(string $capability): array {
        $roles = [];
        foreach (['admin', 'umrah', 'sales', 'operations', 'hotel_manager', 'finance', 'staff', 'viewer', 'client'] as $role) {
            if (in_array($capability, umrah_role_capabilities($role), true)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }
}
