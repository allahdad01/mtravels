<?php
// Connect to the database
require_once 'includes/db.php';

// Determine export format from URL parameter (default to SQL)
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'sql';

// Set headers for file download based on format
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="database_structure.csv"');
} elseif ($format === 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="database_structure.json"');
} else {
    // Default to SQL
    $format = 'sql';
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="database_structure.sql"');
}
header('Pragma: no-cache');

try {
    // Get all tables
    $tables_query = $pdo->query("SHOW TABLES");
    $tables = $tables_query->fetchAll(PDO::FETCH_COLUMN);
    
    if ($format === 'sql') {
        // Output SQL file header with timestamp
        echo "-- Database structure export\n";
        echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
        
        foreach ($tables as $table) {
            echo "-- Table structure for table `$table`\n";
            
            // Get CREATE TABLE statement
            $create_query = $pdo->query("SHOW CREATE TABLE `$table`");
            $create_table = $create_query->fetch(PDO::FETCH_ASSOC);
            
            // Output the CREATE TABLE statement
            if (isset($create_table['Create Table'])) {
                echo $create_table['Create Table'] . ";\n\n";
            }
            
            // Count rows in table
            $count_query = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_query->fetch(PDO::FETCH_ASSOC);
            echo "-- Total rows in `$table`: {$count['count']}\n\n";
        }
    } elseif ($format === 'csv') {
        // For CSV format, output table structure as field definitions
        // First line is a header
        echo "Table,Field,Type,Null,Key,Default,Extra\n";
        
        foreach ($tables as $table) {
            // Get columns for this table
            $columns_query = $pdo->query("DESCRIBE `$table`");
            $columns = $columns_query->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($columns as $column) {
                // Format as CSV line
                echo '"' . $table . '",';
                echo '"' . str_replace('"', '""', $column['Field']) . '",';
                echo '"' . str_replace('"', '""', $column['Type']) . '",';
                echo '"' . str_replace('"', '""', $column['Null']) . '",';
                echo '"' . str_replace('"', '""', $column['Key']) . '",';
                echo '"' . str_replace('"', '""', ($column['Default'] === null ? 'NULL' : $column['Default'])) . '",';
                echo '"' . str_replace('"', '""', $column['Extra']) . '"' . "\n";
            }
        }
    } elseif ($format === 'json') {
        // For JSON format, create a structured representation
        $database = [
            'generated_on' => date('Y-m-d H:i:s'),
            'tables' => []
        ];
        
        foreach ($tables as $table) {
            // Get columns for this table
            $columns_query = $pdo->query("DESCRIBE `$table`");
            $columns = $columns_query->fetchAll(PDO::FETCH_ASSOC);
            
            // Get CREATE TABLE statement for reference
            $create_query = $pdo->query("SHOW CREATE TABLE `$table`");
            $create_table = $create_query->fetch(PDO::FETCH_ASSOC);
            
            // Count rows in table
            $count_query = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_query->fetch(PDO::FETCH_ASSOC);
            
            // Add table to the database structure
            $database['tables'][] = [
                'name' => $table,
                'columns' => $columns,
                'row_count' => $count['count'],
                'create_statement' => $create_table['Create Table'] ?? null
            ];
        }
        
        // Output JSON (pretty-printed)
        echo json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
} catch (PDOException $e) {
    // If there's an error, return it in the appropriate format
    if ($format === 'csv') {
        echo '"Error","' . str_replace('"', '""', $e->getMessage()) . '","","","","",""' . "\n";
    } elseif ($format === 'json') {
        echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
    } else {
        echo "-- Error: " . $e->getMessage() . "\n";
    }
}
?>