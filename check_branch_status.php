<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

require_once __DIR__ . '/includes/db.php';

echo "<h2>Branch Assignment Status</h2>";

// Check how many users have branch_id assigned
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN branch_id IS NULL OR branch_id = 0 THEN 1 ELSE 0 END) as no_branch,
    SUM(CASE WHEN branch_id IS NOT NULL AND branch_id > 0 THEN 1 ELSE 0 END) as with_branch
FROM users WHERE deleted_at IS NULL");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<pre>";
echo "Total Users: " . $stats['total_users'] . "\n";
echo "Without Branch: " . $stats['no_branch'] . "\n";
echo "With Branch: " . $stats['with_branch'] . "\n";
echo "</pre>";

// Show users without branch
echo "<h3>Users without Branch Assignment:</h3>";
$stmt = $pdo->query("SELECT id, name, email, role, tenant_id, branch_id FROM users WHERE (branch_id IS NULL OR branch_id = 0) AND deleted_at IS NULL LIMIT 20");
echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Tenant</th><th>Branch</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['email']}</td><td>{$row['role']}</td><td>{$row['tenant_id']}</td><td>{$row['branch_id']}</td></tr>";
}
echo "</table>";

// Show branch structure
echo "<h3>Branch Structure:</h3>";
$stmt = $pdo->query("SELECT id, name FROM branches LIMIT 20");
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
echo json_encode($branches, JSON_PRETTY_PRINT);
echo "</pre>";
?>
