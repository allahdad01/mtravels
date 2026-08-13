<?php
/**
 * Umrah Audit Logs — Phase 30 (umrah_plan.md)
 * Central helper writing to the shared activity_log table so every important
 * change records: Who (user_id), When (created_at), What (action + table_name
 * + record_id), Before (old_values JSON), After (new_values JSON).
 *
 * Usage (inside the same DB transaction as the change, so audit stays atomic):
 *   umrah_audit($pdo, 'update', 'umrah_hotels', $hotelId, $oldRow, $newRow);
 *
 * Json-encodes arrays/objects; COALESCE-like behavior mirrors the legacy
 * inline inserts used across visa/sarafi/umrah APIs.
 */

if (!function_exists('umrah_audit')) {
    function umrah_audit(PDO $pdo, string $action, string $tableName, ?int $recordId, array $old = [], array $new = []): void {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log
            (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at, tenant_id, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? 0,
            $action,
            $tableName,
            $recordId,
            json_encode($old),
            json_encode($new),
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $_SESSION['tenant_id'] ?? 0,
            $_SESSION['branch_id'] ?? null,
        ]);
    }
}