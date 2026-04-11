<?php
/**
 * Get suppliers with low balances
 * USD threshold: 500, AFS threshold: 20000
 * @return array Array of suppliers with low balances
 */
function getSuppliersWithLowBalance() {
    global $pdo, $tenant_id, $branch_id;

    $query = "SELECT id, name, currency, balance
              FROM suppliers
              WHERE (
                    (currency = 'USD' AND balance >= 0 AND balance < 500)
                    OR (currency = 'AFS' AND balance >= 0 AND balance < 20000)
                  )
                AND status = 'active'
                AND tenant_id = ? AND branch_id = ?
              ORDER BY balance ASC";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tenant_id, $branch_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Fetch suppliers with low balance
$suppliersWithLowBalance = getSuppliersWithLowBalance();
?>