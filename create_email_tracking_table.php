<?php
require_once 'includes/conn.php';

$sql = "CREATE TABLE IF NOT EXISTS email_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_id VARCHAR(255) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    email_type VARCHAR(100) NOT NULL,
    tenant_id INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    opened_at TIMESTAMP NULL,
    opened TINYINT(1) DEFAULT 0,
    user_agent TEXT,
    ip_address VARCHAR(45),
    INDEX idx_email_id (email_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_email_type (email_type)
);";

if ($conn->query($sql) === TRUE) {
    echo 'Email tracking table created successfully';
} else {
    echo 'Error creating table: ' . $conn->error;
}

$conn->close();
?>