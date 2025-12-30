<?php
require_once 'config.php';
require_once 'includes/db.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Check Database Triggers</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th, table td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        table th { background: #007bff; color: white; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Database Triggers Check</h1>

    <?php
    echo "<h2>Triggers on support_tickets table</h2>";
    try {
        $stmt = $pdo->query("
            SELECT 
                TRIGGER_NAME,
                TRIGGER_SCHEMA,
                EVENT_MANIPULATION,
                EVENT_OBJECT_TABLE,
                ACTION_STATEMENT
            FROM INFORMATION_SCHEMA.TRIGGERS
            WHERE TRIGGER_SCHEMA = 'travelagency_saas'
            AND EVENT_OBJECT_TABLE = 'support_tickets'
        ");
        $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($triggers) > 0) {
            echo "<table>";
            echo "<tr><th>Trigger Name</th><th>Event</th><th>Action</th></tr>";
            foreach ($triggers as $t) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($t['TRIGGER_NAME']) . "</td>";
                echo "<td>" . htmlspecialchars($t['EVENT_MANIPULATION']) . " ON " . htmlspecialchars($t['EVENT_OBJECT_TABLE']) . "</td>";
                echo "<td><pre>" . htmlspecialchars($t['ACTION_STATEMENT']) . "</pre></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: green;'>No triggers found on support_tickets</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    echo "<h2>All triggers in database</h2>";
    try {
        $stmt = $pdo->query("
            SELECT 
                TRIGGER_NAME,
                EVENT_OBJECT_TABLE,
                EVENT_MANIPULATION
            FROM INFORMATION_SCHEMA.TRIGGERS
            WHERE TRIGGER_SCHEMA = 'travelagency_saas'
            ORDER BY EVENT_OBJECT_TABLE
        ");
        $allTriggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($allTriggers) > 0) {
            echo "<table>";
            echo "<tr><th>Table</th><th>Event</th><th>Trigger</th></tr>";
            foreach ($allTriggers as $t) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($t['EVENT_OBJECT_TABLE']) . "</td>";
                echo "<td>" . htmlspecialchars($t['EVENT_MANIPULATION']) . "</td>";
                echo "<td>" . htmlspecialchars($t['TRIGGER_NAME']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: green;'>No triggers found in database</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    ?>

</div>
</body>
</html>
