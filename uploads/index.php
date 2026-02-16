<?php
// Uploads directory - Static files only
// This file prevents directory listing but allows file access

// Log any PHP execution attempts
error_log("Uploads directory index accessed from IP: {$_SERVER['REMOTE_ADDR']}");

// Display message about uploads directory
?>
<!DOCTYPE html>
<html>
<head>
    <title>Uploads - MTravels</title>
</head>
<body>
    <h1>Uploads Directory</h1>
    <p>This directory contains user uploads and static files.</p>
    <p>For the main application, <a href="../index.php">click here</a>.</p>
</body>
</html>
