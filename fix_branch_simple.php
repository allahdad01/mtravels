<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

require_once __DIR__ . '/includes/db.php';

echo "<h2>Fixing Branch Assignments</h2>";

// Assign user 7 (Matiullah) to branch 1
echo "<h3>Assigning User 7 (Matiullah Rahimi) to branch 1</h3>";
$stmt = $pdo->prepare("UPDATE users SET branch_id = 1 WHERE id = 7");
if ($stmt->execute()) {
    echo "✓ User 7 (Matiullah) assigned to branch 1<br>";
} else {
    echo "✗ Failed to update user 7<br>";
}

echo "<h3>User 14 (ALLAH DAD) - Super Admin</h3>";
echo "✓ Super admin does not need branch assignment (SaaS owner)<br>";

// Verify the fix
echo "<h3>Current User Status</h3>";
$stmt = $pdo->query("SELECT id, name, role, tenant_id, branch_id FROM users WHERE deleted_at IS NULL ORDER BY id");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Tenant</th><th>Branch</th><th>Status</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['role'] === 'super_admin') {
        $status = "✓ Super Admin (no branch needed)";
        $statusColor = 'green';
    } else {
        $status = ($row['branch_id'] && $row['tenant_id']) ? "✓ OK" : "✗ Missing";
        $statusColor = ($row['branch_id'] && $row['tenant_id']) ? 'green' : 'red';
    }
    echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['role']}</td><td>{$row['tenant_id']}</td><td>{$row['branch_id']}</td><td style='color:{$statusColor};'>{$status}</td></tr>";
}
echo "</table>";

echo "<h2>✓ Fix Complete!</h2>";
?>
