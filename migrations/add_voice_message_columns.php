<?php
/**
 * Migration: Add voice message columns to chat_messages table
 * 
 * This migration adds support for voice messages in the chat system.
 * It adds the following columns:
 * - message_type: Stores the type of message (text, voice, file, etc.)
 * - duration: Stores the duration of voice messages in seconds
 */

require_once __DIR__ . '/../includes/db.php';

// Check if columns already exist
$checkStmt = $pdo->query("
    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'chat_messages' 
    AND COLUMN_NAME IN ('message_type', 'duration')
");
$existingColumns = $checkStmt ? $checkStmt->fetchAll(PDO::FETCH_COLUMN) : [];

$success = true;
$messages = [];

try {
    // Add message_type column if it doesn't exist
    if (!in_array('message_type', $existingColumns)) {
        $pdo->exec("
            ALTER TABLE chat_messages 
            ADD COLUMN message_type VARCHAR(20) DEFAULT 'text' 
            AFTER content
        ");
        $messages[] = "✓ Added message_type column";
    } else {
        $messages[] = "✓ message_type column already exists";
    }

    // Add duration column if it doesn't exist
    if (!in_array('duration', $existingColumns)) {
        $pdo->exec("
            ALTER TABLE chat_messages 
            ADD COLUMN duration INT DEFAULT 0 
            AFTER message_type
        ");
        $messages[] = "✓ Added duration column";
    } else {
        $messages[] = "✓ duration column already exists";
    }

    // Create voices upload directory if it doesn't exist
    $voicesDir = __DIR__ . '/../uploads/voices';
    if (!is_dir($voicesDir)) {
        mkdir($voicesDir, 0755, true);
        $messages[] = "✓ Created uploads/voices directory";
    } else {
        $messages[] = "✓ uploads/voices directory already exists";
    }

    // Set proper permissions
    @chmod($voicesDir, 0755);
    $messages[] = "✓ Set directory permissions";

    echo "<h2 style='color: green;'>Migration Completed Successfully</h2>";
    echo "<pre style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
    foreach ($messages as $msg) {
        echo $msg . "\n";
    }
    echo "</pre>";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Migration Failed</h2>";
    echo "<pre style='background: #ffe0e0; padding: 15px; border-radius: 5px; color: red;'>";
    echo "Error: " . htmlspecialchars($e->getMessage());
    echo "</pre>";
    $success = false;
}

if ($success) {
    echo "<p style='color: green; font-weight: bold;'>Voice message feature is ready to use!</p>";
    echo "<p>The following changes were made:</p>";
    echo "<ul>";
    echo "<li>Added <code>message_type</code> column to store message types</li>";
    echo "<li>Added <code>duration</code> column to store voice message duration</li>";
    echo "<li>Created <code>uploads/voices</code> directory for voice file storage</li>";
    echo "</ul>";
    echo "<p><a href='../chat-new.php' style='color: blue; text-decoration: none;'>Go to Chat →</a></p>";
}
?>
