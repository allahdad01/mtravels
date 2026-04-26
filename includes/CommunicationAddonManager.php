<?php
/**
 * CommunicationAddonManager
 *
 * Manages WhatsApp and SMTP add-on request/approval workflow for tenants.
 */
class CommunicationAddonManager {
    private $conn;
    private $tenant_id;

    private const DEFAULT_PRICING = [
        'whatsapp' => [
            'monthly' => 30.00,
            'quarterly' => 90.00,
            'yearly' => 360.00
        ],
        'smtp' => [
            'monthly' => 20.00,
            'quarterly' => 60.00,
            'yearly' => 240.00
        ]
    ];

    public function __construct($connection, $tenant_id = null) {
        $this->conn = $connection;
        $this->tenant_id = $tenant_id;
    }

    public static function isValidAddonType($addon_type) {
        return in_array($addon_type, ['whatsapp', 'smtp'], true);
    }

    public function hasActiveAddon($tenant_id = null, $addon_type = 'whatsapp') {
        $id = $tenant_id ?? $this->tenant_id;
        if (!$id || !self::isValidAddonType($addon_type)) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count
                FROM communication_addons
                WHERE tenant_id = ? AND addon_type = ? AND status = 'active'
            ");
            $stmt->execute([$id, $addon_type]);
            $row = $stmt->fetch();

            return intval($row['count'] ?? 0) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getActiveAddons($tenant_id = null) {
        $id = $tenant_id ?? $this->tenant_id;
        if (!$id) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT *
                FROM communication_addons
                WHERE tenant_id = ? AND status = 'active'
                ORDER BY addon_type ASC, created_at DESC
            ");
            $stmt->execute([$id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function getAddonPricing($tenant_id = null, $addon_type = 'whatsapp') {
        if (!self::isValidAddonType($addon_type)) {
            return self::DEFAULT_PRICING['whatsapp'];
        }

        $id = $tenant_id ?? $this->tenant_id;
        if (!$id) {
            return self::DEFAULT_PRICING[$addon_type];
        }

        $prefix = $addon_type . '_addon_';
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    {$prefix}monthly_price AS monthly_price,
                    {$prefix}quarterly_price AS quarterly_price,
                    {$prefix}yearly_price AS yearly_price
                FROM settings
                WHERE tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
        } catch (Exception $e) {
            $row = [];
        }

        $defaults = self::DEFAULT_PRICING[$addon_type];
        return [
            'monthly' => floatval($row['monthly_price'] ?? $defaults['monthly']),
            'quarterly' => floatval($row['quarterly_price'] ?? $defaults['quarterly']),
            'yearly' => floatval($row['yearly_price'] ?? $defaults['yearly']),
        ];
    }

    public function calculateAddonCost($addon_type, $billing_cycle = 'monthly', $tenant_id = null) {
        $pricing = $this->getAddonPricing($tenant_id, $addon_type);
        return floatval($pricing[$billing_cycle] ?? $pricing['monthly']);
    }

    public function requestAddon($tenant_id, $addon_type, $billing_cycle = 'monthly') {
        if (!self::isValidAddonType($addon_type)) {
            return ['success' => false, 'message' => 'Invalid addon type'];
        }

        if ($this->hasActiveAddon($tenant_id, $addon_type)) {
            return ['success' => false, 'message' => ucfirst($addon_type) . ' addon is already active'];
        }

        try {
            $pending = $this->conn->prepare("
                SELECT id
                FROM communication_addon_requests
                WHERE tenant_id = ? AND addon_type = ? AND status = 'pending'
                LIMIT 1
            ");
            $pending->execute([$tenant_id, $addon_type]);
            if ($pending->fetch()) {
                return ['success' => false, 'message' => 'A pending request already exists for ' . $addon_type];
            }

            $cost = $this->calculateAddonCost($addon_type, $billing_cycle, $tenant_id);

            $currencyStmt = $this->conn->prepare("
                SELECT ts.currency
                FROM tenant_subscriptions ts
                WHERE ts.tenant_id = ? AND ts.status = 'active'
                ORDER BY ts.start_date DESC
                LIMIT 1
            ");
            $currencyStmt->execute([$tenant_id]);
            $currencyRow = $currencyStmt->fetch();
            $currency = $currencyRow['currency'] ?? 'USD';

            $stmt = $this->conn->prepare("
                INSERT INTO communication_addon_requests
                    (tenant_id, addon_type, billing_cycle, estimated_monthly_cost, currency, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$tenant_id, $addon_type, $billing_cycle, $cost, $currency]);
            return [
                'success' => true,
                'request_id' => $this->conn->lastInsertId(),
                'estimated_cost' => $cost,
                'currency' => $currency
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to create request: ' . $e->getMessage()];
        }
    }

    public function approveRequest($request_id, $approved_by_user_id, $approval_notes = '') {
        try {
            $stmt = $this->conn->prepare("
                SELECT *
                FROM communication_addon_requests
                WHERE id = ? AND status = 'pending'
                LIMIT 1
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();

            if (!$request) {
                return ['success' => false, 'message' => 'Request not found or already processed'];
            }

            $tenant_id = intval($request['tenant_id']);
            $addon_type = $request['addon_type'];
            if (!self::isValidAddonType($addon_type)) {
                return ['success' => false, 'message' => 'Invalid addon type'];
            }

            $this->conn->beginTransaction();

            // Suspend existing active row for the same addon type to keep one active record.
            $deactivate = $this->conn->prepare("
                UPDATE communication_addons
                SET status = 'inactive', updated_at = NOW()
                WHERE tenant_id = ? AND addon_type = ? AND status = 'active'
            ");
            $deactivate->execute([$tenant_id, $addon_type]);

            $price = $this->calculateAddonCost($addon_type, $request['billing_cycle'], $tenant_id);
            $insert = $this->conn->prepare("
                INSERT INTO communication_addons
                    (tenant_id, addon_type, addon_price, billing_cycle, currency, status, created_by)
                VALUES (?, ?, ?, ?, ?, 'active', ?)
            ");
            $insert->execute([
                $tenant_id,
                $addon_type,
                $price,
                $request['billing_cycle'],
                $request['currency'],
                $approved_by_user_id
            ]);
            $addon_id = $this->conn->lastInsertId();

            $updateRequest = $this->conn->prepare("
                UPDATE communication_addon_requests
                SET status = 'approved', approved_by = ?, approved_at = NOW(), approval_notes = ?
                WHERE id = ?
            ");
            $updateRequest->execute([$approved_by_user_id, $approval_notes, $request_id]);

            $this->conn->commit();
            return ['success' => true, 'addon_id' => $addon_id];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Approval failed: ' . $e->getMessage()];
        }
    }

    public function rejectRequest($request_id, $rejected_by_user_id, $reason = '') {
        try {
            $stmt = $this->conn->prepare("
                UPDATE communication_addon_requests
                SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ?
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$rejected_by_user_id, $reason, $request_id]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Request not found or already processed'];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Reject failed: ' . $e->getMessage()];
        }
    }

    public function suspendAddon($addon_id) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE communication_addons
                SET status = 'inactive', updated_at = NOW()
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$addon_id]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Addon not found or already inactive'];
            }

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Suspend failed: ' . $e->getMessage()];
        }
    }

    public function reactivateAddon($addon_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT tenant_id, addon_type
                FROM communication_addons
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$addon_id]);
            $addon = $stmt->fetch();

            if (!$addon) {
                return ['success' => false, 'message' => 'Addon not found'];
            }

            $this->conn->beginTransaction();

            $deactivate = $this->conn->prepare("
                UPDATE communication_addons
                SET status = 'inactive', updated_at = NOW()
                WHERE tenant_id = ? AND addon_type = ? AND id != ? AND status = 'active'
            ");
            $deactivate->execute([$addon['tenant_id'], $addon['addon_type'], $addon_id]);

            $reactivate = $this->conn->prepare("
                UPDATE communication_addons
                SET status = 'active', updated_at = NOW()
                WHERE id = ?
            ");
            $reactivate->execute([$addon_id]);

            $this->conn->commit();
            return ['success' => true];
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return ['success' => false, 'message' => 'Reactivation failed: ' . $e->getMessage()];
        }
    }

    public function getTenantAddonRequests($tenant_id, $status = null) {
        $sql = "SELECT * FROM communication_addon_requests WHERE tenant_id = ?";
        $params = [$tenant_id];
        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY created_at DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function getPendingAddonRequests() {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    car.*,
                    t.name AS tenant_name,
                    p.name AS plan_name
                FROM communication_addon_requests car
                JOIN tenants t ON car.tenant_id = t.id
                LEFT JOIN tenant_subscriptions ts ON ts.tenant_id = t.id AND ts.status = 'active'
                LEFT JOIN plans p ON ts.plan_id = p.id
                WHERE car.status = 'pending'
                ORDER BY car.created_at ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function getAllAddons($status = null) {
        $sql = "
            SELECT
                ca.*,
                t.name AS tenant_name
            FROM communication_addons ca
            JOIN tenants t ON ca.tenant_id = t.id
            WHERE 1 = 1
        ";
        $params = [];
        if ($status !== null) {
            $sql .= " AND ca.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY ca.created_at DESC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}
