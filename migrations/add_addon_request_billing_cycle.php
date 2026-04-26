<?php
/**
 * Migration: add billing_cycle to legacy addon request tables.
 *
 * Usage:
 *   php migrations/add_addon_request_billing_cycle.php
 */
require_once __DIR__ . '/../includes/db.php';

echo "Adding billing_cycle to branch_addon_requests...\n";
try {
    $pdo->exec("
        ALTER TABLE branch_addon_requests
        ADD COLUMN billing_cycle ENUM('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly'
        AFTER requested_additional_branches
    ");
    echo "✓ branch_addon_requests.billing_cycle added\n";
} catch (PDOException $e) {
    if (intval($e->errorInfo[1] ?? 0) === 1060) {
        echo "- branch_addon_requests.billing_cycle already exists\n";
    } else {
        throw $e;
    }
}

echo "Adding billing_cycle to user_addon_requests...\n";
try {
    $pdo->exec("
        ALTER TABLE user_addon_requests
        ADD COLUMN billing_cycle ENUM('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly'
        AFTER requested_additional_users
    ");
    echo "✓ user_addon_requests.billing_cycle added\n";
} catch (PDOException $e) {
    if (intval($e->errorInfo[1] ?? 0) === 1060) {
        echo "- user_addon_requests.billing_cycle already exists\n";
    } else {
        throw $e;
    }
}

echo "Migration completed.\n";

