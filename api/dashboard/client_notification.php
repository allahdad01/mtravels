<?php
/**
 * Get clients with low/negative balances
 * USD threshold: -1000, AFS threshold: -20000
 * @return array Array of clients with low balances
 */
function getClientsWithLowBalance() {
    global $pdo, $tenant_id, $branch_id;

    $query = "SELECT id, name, usd_balance, afs_balance, status
              FROM clients
              WHERE (
                    (usd_balance <= -1000)
                    OR (afs_balance <= -20000)
                  )
                AND status = 'active'
                AND tenant_id = ? AND branch_id = ?
              ORDER BY usd_balance ASC, afs_balance ASC";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$tenant_id, $branch_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting clients with low balance: " . $e->getMessage());
        return [];
    }
}

// Fetch clients with low balance
$clientsWithLowBalance = getClientsWithLowBalance();
?>
