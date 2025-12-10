<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

require_once __DIR__ . '/includes/db.php';

echo "<h2>Fixing Tenant Owner Configuration</h2>";

// Remove branch_id from user 7 (Matiullah - tenant owner)
echo "<h3>Removing branch_id from User 7 (Matiullah - super_tenant_admin)</h3>";
$stmt = $pdo->prepare("UPDATE users SET branch_id = NULL WHERE id = 7");
if ($stmt->execute()) {
    echo "✓ User 7 branch_id removed (tenant owner doesn't need branch)<br>";
} else {
    echo "✗ Failed to update user 7<br>";
}

echo "<h3>Current User Status</h3>";
$stmt = $pdo->query("SELECT id, name, role, tenant_id, branch_id FROM users WHERE deleted_at IS NULL ORDER BY id");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Tenant</th><th>Branch</th><th>Status</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['role'] === 'super_tenant_admin') {
        $status = "✓ Tenant Owner (branch not needed)";
        $statusColor = 'green';
    } elseif ($row['role'] === 'super_admin') {
        $status = "✓ SaaS Owner (no branch needed)";
        $statusColor = 'green';
    } else {
        $status = ($row['branch_id'] && $row['tenant_id']) ? "✓ OK" : "✗ Missing branch";
        $statusColor = ($row['branch_id'] && $row['tenant_id']) ? 'green' : 'red';
    }
    echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['role']}</td><td>{$row['tenant_id']}</td><td>{$row['branch_id']}</td><td style='color:{$statusColor};'>{$status}</td></tr>";
}
echo "</table>";

echo "<h2>✓ Configuration Complete!</h2>";
echo "<p><strong>Tenant Owner (Matiullah):</strong> Can see all users in their tenant (no branch filtering)</p>";
echo "<p><strong>Regular Users:</strong> Can see same branch + approved peer tenants</p>";
?>
