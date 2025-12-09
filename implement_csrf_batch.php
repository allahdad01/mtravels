<?php
/**
 * Batch CSRF Implementation Script
 * Adds CSRF token validation to all API handlers
 * 
 * Status: Implementation Helper
 * Date: December 9, 2025
 */

// List of all API handlers that need CSRF validation
// Format: 'file_path' => 'pattern_type' (form, json, or both)

$handlers_to_update = [
    // Accounts Module - CRITICAL
    'api/accounts/transfer_balance.php' => 'json',
    'api/accounts/add_supplier_bonus.php' => 'form',
    'api/accounts/fundClient.php' => 'both',
    'api/accounts/add_main_account.php' => 'form',
    'api/accounts/edit_main_account.php' => 'form',
    'api/accounts/delete_supplier_transaction.php' => 'json',
    'api/accounts/delete_client_transaction.php' => 'json',
    'api/accounts/delete_main_account_transaction.php' => 'json',
    
    // Supplier Module
    'api/supplier/add_supplier.php' => 'form',
    'api/supplier/update_supplier.php' => 'form',
    'api/supplier/delete_supplier.php' => 'json',
    
    // Client Module
    'api/client/add_clients.php' => 'form',
    'api/client/update_client.php' => 'json',
    
    // Hotel Module - CRITICAL
    'api/hotel/add_hotel_transaction.php' => 'form',
    'api/hotel/add_hotel_booking.php' => 'form',
    'api/hotel/update_hotel_booking.php' => 'form',
    'api/hotel/delete_hotel_booking.php' => 'form',
    'api/hotel/delete_hotel_transaction.php' => 'form',
    'api/hotel/update_hotel_transaction.php' => 'form',
    'api/hotel/process_hotel_refund.php' => 'form',
    
    // Visa Module - CRITICAL
    'api/visa/add_visa.php' => 'form',
    'api/visa/add_visa_transaction.php' => 'form',
    'api/visa/update_visa.php' => 'form',
    'api/visa/delete_visa.php' => 'form',
    'api/visa/delete_visa_transaction.php' => 'form',
    'api/visa/process_visa_refund.php' => 'form',
    
    // Umrah Module - CRITICAL
    'api/umrah/add_umrah.php' => 'form',
    'api/umrah/add_umrah_transaction.php' => 'form',
    'api/umrah/delete_umrah_transaction.php' => 'form',
    'api/umrah/delete_booking.php' => 'form',
    'api/umrah/create_family.php' => 'form',
    'api/umrah/update_family.php' => 'form',
    'api/umrah/process_umrah_refund.php' => 'form',
    
    // Ticket Module - CRITICAL
    'api/ticket/add_ticket_payment.php' => 'form',
    'api/ticket/update_ticket.php' => 'form',
    'api/ticket/delete_ticket.php' => 'form',
    'api/ticket/save_ticket.php' => 'form',
    
    // Expense Module
    'api/expense/expense_actions.php' => 'form',
    
    // Additional Payment
    'api/additional_payment/add_additional_payment.php' => 'form',
    'api/additional_payment/update_additional_payment_base.php' => 'form',
    'api/additional_payment/delete_additional_payment.php' => 'form',
    'api/additional_payment/add_additional_payment_transaction.php' => 'form',
    
    // Debtor & Creditor
    'api/debtor/debtors_handler.php' => 'form',
    'api/creditor/creditor_handler.php' => 'form',
];

echo "=== CSRF Batch Implementation Helper ===\n\n";
echo "Total handlers to update: " . count($handlers_to_update) . "\n\n";

echo "Follow these steps to implement CSRF validation:\n\n";

$form_count = 0;
$json_count = 0;
$both_count = 0;

echo "📋 FORM DATA HANDLERS (" . count(array_filter($handlers_to_update, fn($p) => $p === 'form' || $p === 'both')) . " total):\n";
echo "────────────────────────────────────────\n";

foreach ($handlers_to_update as $file => $pattern) {
    if ($pattern === 'form' || $pattern === 'both') {
        echo "\n✓ $file\n";
        echo "  Pattern: Form Data (\$_POST)\n";
        echo "  Add after enforce_auth():\n";
        echo "  ────────────────────────────────────\n";
        echo "  if (!verify_csrf_token()) {\n";
        echo "      http_response_code(403);\n";
        echo "      echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);\n";
        echo "      exit;\n";
        echo "  }\n";
        echo "  ────────────────────────────────────\n";
        $form_count++;
    }
}

echo "\n\n📋 JSON DATA HANDLERS (" . count(array_filter($handlers_to_update, fn($p) => $p === 'json' || $p === 'both')) . " total):\n";
echo "────────────────────────────────────────\n";

foreach ($handlers_to_update as $file => $pattern) {
    if ($pattern === 'json' || $pattern === 'both') {
        echo "\n✓ $file\n";
        echo "  Pattern: JSON Input\n";
        echo "  Add after json_decode():\n";
        echo "  ────────────────────────────────────\n";
        echo "  if (!verify_csrf_token(\$data['csrf_token'] ?? null)) {\n";
        echo "      http_response_code(403);\n";
        echo "      echo json_encode(['success' => false, 'message' => 'CSRF validation failed']);\n";
        echo "      exit;\n";
        echo "  }\n";
        echo "  ────────────────────────────────────\n";
        $json_count++;
    }
}

echo "\n\n=== SUMMARY ===\n";
echo "Form handlers (single pattern): $form_count\n";
echo "JSON handlers (single pattern): $json_count\n";
echo "Dual handlers (both patterns): $both_count\n";
echo "Total: " . count($handlers_to_update) . "\n\n";

echo "=== IMPLEMENTATION ORDER (RECOMMENDED) ===\n\n";
echo "1. PHASE 1 - ACCOUNTS (Critical financial operations) - 8 files\n";
echo "2. PHASE 2 - SUPPLIER, CLIENT, HOTEL - 7 files\n";
echo "3. PHASE 3 - VISA, UMRAH, TICKET - 17 files\n";
echo "4. PHASE 4 - REMAINING - 10 files\n\n";

echo "=== TESTING CHECKLIST ===\n";
echo "[ ] Test Phase 1 handlers with valid CSRF tokens\n";
echo "[ ] Test Phase 1 handlers without CSRF tokens (should return 403)\n";
echo "[ ] Test Phase 1 handlers with invalid tokens (should return 403)\n";
echo "[ ] Monitor error logs for any issues\n";
echo "[ ] Proceed to Phase 2 after Phase 1 passes testing\n\n";

echo "See API_CSRF_VALIDATION_GUIDE.md for implementation patterns and examples.\n";
?>
