<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

require_once __DIR__ . '/includes/db.php';

echo "<h2>Fixing Branch Assignments</h2>";

// Fix user ID 7: Assign to branch 2 (Al Wali - Main Branch)
echo "<h3>1. Assigning User 7 (Matiullah Rahimi) to branch 2</h3>";
$stmt = $pdo->prepare("UPDATE users SET branch_id = 2 WHERE id = 7");
if ($stmt->execute()) {
    echo "✓ User 7 assigned to branch 2<br>";
} else {
    echo "✗ Failed to update user 7<br>";
}

// Fix user ID 14: Assign tenant 1 and branch 1 (they have no tenant)
echo "<h3>2. Assigning User 14 (ALLAH DAD MUHAMMADI) to tenant 1, branch 1</h3>";
$stmt = $pdo->prepare("UPDATE users SET tenant_id = 1, branch_id = 1 WHERE id = 14");
if ($stmt->execute()) {
    echo "✓ User 14 assigned to tenant 1, branch 1<br>";
} else {
    echo "✗ Failed to update user 14<br>";
}

// Verify the fix
echo "<h3>3. Verification</h3>";
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN branch_id IS NULL OR branch_id = 0 THEN 1 ELSE 0 END) as no_branch,
    SUM(CASE WHEN branch_id IS NOT NULL AND branch_id > 0 THEN 1 ELSE 0 END) as with_branch
FROM users WHERE deleted_at IS NULL");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Total Users: " . $stats['total_users'] . "<br>";
echo "Without Branch: <span style='color:" . ($stats['no_branch'] > 0 ? 'red' : 'green') . ";'>" . $stats['no_branch'] . "</span><br>";
echo "With Branch: <span style='color:green;'>" . $stats['with_branch'] . "</span><br>";

echo "<h3>4. Current User Status</h3>";
$stmt = $pdo->query("SELECT id, name, role, tenant_id, branch_id FROM users WHERE deleted_at IS NULL ORDER BY id");
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Tenant</th><th>Branch</th><th>Status</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $status = ($row['branch_id'] && $row['tenant_id']) ? "✓ OK" : "✗ Missing";
    $statusColor = ($row['branch_id'] && $row['tenant_id']) ? 'green' : 'red';
    echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['role']}</td><td>{$row['tenant_id']}</td><td>{$row['branch_id']}</td><td style='color:{$statusColor};'>{$status}</td></tr>";
}
echo "</table>";

echo "<h2>✓ Fix Complete!</h2>";
echo "<p>All users now have branch assignments. Refresh the chat page to see branch users.</p>";
?>
