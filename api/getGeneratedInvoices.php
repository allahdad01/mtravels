<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security module and database connection
require_once '../admin/security.php';
require_once '../includes/db.php';

// Enforce authentication
enforce_auth();

// Check if user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Function to get generated invoices
function getGeneratedInvoices() {
    $invoicesDir = '../uploads/invoices/';
    
    // Check if directory exists
    if (!is_dir($invoicesDir)) {
        return [
            'status' => 'error',
            'message' => 'Invoices directory not found'
        ];
    }

    // Get all PDF files in the directory
    $invoiceFiles = glob($invoicesDir . '*.pdf');
    
    // Sort files by modification time (newest first)
    usort($invoiceFiles, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    // Prepare invoices data
    $invoices = [];
    foreach ($invoiceFiles as $file) {
        // Extract filename
        $fileName = basename($file);
        
        // Try to extract invoice number from filename (adjust as needed)
        preg_match('/INV-(\d+)-(\d+)/', $fileName, $matches);
        $invoiceNumber = $matches[0] ?? $fileName;

        $invoices[] = [
            'file_name' => $fileName,
            // Use a relative path that works with the web server
            'file_path' => 'uploads/invoices/' . $fileName,
            'invoice_number' => $invoiceNumber,
            'date_generated' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }

    return [
        'status' => 'success',
        'invoices' => $invoices
    ];
}

// Output JSON response
header('Content-Type: application/json');
echo json_encode(getGeneratedInvoices());
exit();
?>