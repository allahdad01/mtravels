<?php
require 'includes/db.php';
foreach (['debtors','creditors','debtor_transactions','creditor_transactions'] as $table) {
    echo "=== $table ===\n";
    $q = $pdo->query("DESCRIBE $table");
    $cols = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "\n";
}
