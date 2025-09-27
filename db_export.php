<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (assuming there's a login system)
// Uncomment this if you have authentication
/*
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Structure Export</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .format-options {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #45a049;
        }
        .format-info {
            margin-top: 20px;
            background: #f9f9f9;
            padding: 15px;
            border-left: 4px solid #4CAF50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Structure Export</h1>
        <p>Select a format to export the database structure:</p>
        
        <div class="format-options">
            <a href="db_structure.php?format=sql" class="btn">Download as SQL</a>
            <a href="db_structure.php?format=csv" class="btn">Download as CSV</a>
            <a href="db_structure.php?format=json" class="btn">Download as JSON</a>
        </div>
        
        <div class="format-info">
            <h3>Format Information:</h3>
            <ul>
                <li><strong>SQL</strong> - Standard SQL CREATE TABLE statements. Ideal for database migration or recreation.</li>
                <li><strong>CSV</strong> - Comma-separated values format. Can be imported into spreadsheets like Excel or Google Sheets.</li>
                <li><strong>JSON</strong> - Structured data in JavaScript Object Notation. Good for programmatic processing or documentation.</li>
            </ul>
        </div>
        
        <p><a href="index.php">← Back to main page</a></p>
    </div>
</body>
</html> 