<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once('../includes/db.php');
$tenant_id = $_SESSION['tenant_id'];
// Function to load translation if it exists
if (!function_exists('__')) {
    function __($key) {
        global $translations;
        return $translations[$key] ?? $key;
    }
}

try {
    // Query to get all distinct expense categories from the database
    $stmt = $pdo->query("SELECT * FROM expense_categories where tenant_id = ? ORDER BY name", [$tenant_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [];
    
    // If we have categories, format them
    if ($categories) {
        // First add "all categories" option
        $data[] = ['id' => 'all', 'name' => __('all_categories')];
        
        // Then add each category from the database
        foreach ($categories as $category) {
            if (!empty($category['name'])) {
                $data[] = [
                    'id' => $category['id'],
                    'name' => $category['name']
                ];
            }
        }
    }
    
    // Return success response with categories
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data], [$tenant_id]);
    
} catch (PDOException $e) {
    // Log the error
    error_log("Database Error: " . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error loading categories: ' . $e->getMessage()]);
} 