<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Please log in first');
}

require_once __DIR__ . '/includes/db.php';

$userId = $_SESSION['user_id'];

// Get current user info
$stmt = $pdo->prepare("SELECT id, name, tenant_id, branch_id, role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Your Account Info</h2>";
echo "User ID: " . $me['id'] . "<br>";
echo "Name: " . $me['name'] . "<br>";
echo "Tenant ID: " . $me['tenant_id'] . "<br>";
echo "Branch ID: " . ($me['branch_id'] ? $me['branch_id'] : "<strong style='color:red;'>NULL (NOT SET)</strong>") . "<br>";
echo "Role: " . $me['role'] . "<br>";

echo "<h2>Users in Your Tenant</h2>";
$stmt = $pdo->prepare("SELECT id, name, role, branch_id FROM users WHERE tenant_id = ? AND deleted_at IS NULL");
$stmt->execute([$me['tenant_id']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Branch ID</th></tr>";
foreach ($users as $u) {
    $branchDisplay = $u['branch_id'] ? $u['branch_id'] : "<span style='color:red;'>NULL</span>";
    echo "<tr><td>{$u['id']}</td><td>{$u['name']}</td><td>{$u['role']}</td><td>{$branchDisplay}</td></tr>";
}
echo "</table>";

echo "<h2>What the API Will Show You</h2>";
echo "Based on the query in api/contacts.php:<br>";
echo "Will show users where: (tenant_id = " . $me['tenant_id'] . " AND branch_id = " . ($me['branch_id'] ?: 'NULL') . ") OR tenant_id != " . $me['tenant_id'];

// Simulate what the API query does
if ($me['branch_id']) {
    $stmt = $pdo->prepare("
        SELECT id, name, role, branch_id, tenant_id 
        FROM users 
        WHERE (tenant_id = ? AND branch_id = ?) 
        AND id != ? 
        AND deleted_at IS NULL
        AND fired != 1
    ");
    $stmt->execute([$me['tenant_id'], $me['branch_id'], $userId]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, name, role, branch_id, tenant_id 
        FROM users 
        WHERE tenant_id = ? 
        AND id != ? 
        AND deleted_at IS NULL
        AND fired != 1
    ");
    $stmt->execute([$me['tenant_id'], $userId]);
}

$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Contacts That Will Be Shown</h3>";
if (empty($contacts)) {
    echo "<strong style='color:red;'>NO CONTACTS FOUND!</strong>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Branch ID</th><th>Tenant ID</th></tr>";
    foreach ($contacts as $c) {
        echo "<tr><td>{$c['id']}</td><td>{$c['name']}</td><td>{$c['role']}</td><td>{$c['branch_id']}</td><td>{$c['tenant_id']}</td></tr>";
    }
    echo "</table>";
}

echo "<h2>Possible Issues</h2>";
echo "<ul>";
if (!$me['branch_id']) {
    echo "<li><strong style='color:red;'>❌ Your branch_id is NULL!</strong> This is the problem. Users need branch_id assigned.</li>";
}
if (empty($contacts)) {
    echo "<li><strong style='color:red;'>❌ No contacts found!</strong> Branch users may not be assigned.</li>";
}
echo "</ul>";
?>
