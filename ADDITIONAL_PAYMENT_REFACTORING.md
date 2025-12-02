# Additional Payment Module Refactoring

This document outlines the reorganization of the Additional Payment module to follow a cleaner architecture.

## Overview

The Additional Payment feature has been reorganized into modular components for better maintainability and separation of concerns.

## Directory Structure

```
mtravels/
├── admin/
│   └── additional_payments.php (Main page - updated)
├── modals/
│   └── additional_payment/
│       ├── add_payment_modal.php
│       ├── edit_payment_modal.php
│       ├── add_transaction_modal.php
│       └── edit_transaction_modal.php
├── js/
│   └── additional_payment/
│       ├── transaction-manager.js (Transaction AJAX and logic)
│       ├── payment-handlers.js (Form handlers and profit calculation)
│       └── button-protection.js (Button state management)
└── api/
    └── additional_payment/
        ├── add_additional_payment.php (Create payment endpoint)
        └── delete_additional_payment.php (Delete payment endpoint)
```

## Files Created

### Modal Files
- **modals/additional_payment/add_payment_modal.php** - Form to add new payment
- **modals/additional_payment/edit_payment_modal.php** - Form to edit existing payment
- **modals/additional_payment/add_transaction_modal.php** - Payment transaction management interface
- **modals/additional_payment/edit_transaction_modal.php** - Edit individual transaction

### JavaScript Files
- **js/additional_payment/transaction-manager.js** - Handles transaction AJAX operations and calculations
- **js/additional_payment/payment-handlers.js** - Payment form handlers and profit calculations
- **js/additional_payment/button-protection.js** - Button disable/enable logic to prevent duplicate submissions

### API Files
- **api/additional_payment/add_additional_payment.php** - Endpoint for creating new payment
- **api/additional_payment/delete_additional_payment.php** - Endpoint for deleting payment

## Files Updated

### admin/additional_payments.php
- Updated form action from `includes/add_additional_payment.php` to `../api/additional_payment/add_additional_payment.php`
- Updated delete endpoint from `includes/delete_additional_payment.php` to `../api/additional_payment/delete_additional_payment.php`
- Added includes for new JavaScript files

## API Endpoints

### Add Payment
- **URL:** `/api/additional_payment/add_additional_payment.php`
- **Method:** POST
- **Required Fields:**
  - csrf_token
  - payment_type
  - main_account_id
  - base_amount
  - sold_amount
  - profit (calculated)
  - currency
  - is_from_supplier (optional)
  - supplier_id (if is_from_supplier)
  - is_for_client (optional)
  - client_id (if is_for_client)

### Delete Payment
- **URL:** `/api/additional_payment/delete_additional_payment.php`
- **Method:** POST
- **Required Fields:**
  - action: 'delete'
  - id: payment id

## JavaScript Functions

### transactionManager Object
- `formatDate(dateString)` - Format date/time for display
- `loadTransactionHistory(paymentId)` - Load and display transactions
- `editTransaction(id, description, amount, created_at, currency, exchange_rate)` - Open edit modal with transaction data
- `deleteTransaction(id, amount)` - Delete transaction via AJAX

### Payment Handlers
- `calculateProfit()` - Calculate profit for add form
- `calculateEditProfit()` - Calculate profit for edit form
- Event handlers for:
  - Save Payment
  - Delete Payment
  - Add Transaction
  - Form validation and checkbox toggles

### Button Protection
- Prevents double submission by disabling buttons during processing
- Shows loading spinner while processing
- Re-enables buttons on AJAX errors
- Restores original button text on error

## Migration Notes

- Old files in `admin/includes/` should be kept for backward compatibility or removed after confirming no other modules use them
- The modals are now separate PHP files but can be included or converted to templates as needed
- All API endpoints now follow RESTful convention in `/api/` directory
- JavaScript is now modularized for easier maintenance and testing

## Future Enhancements

1. Convert modals to template system (e.g., Blade, Twig)
2. Create dedicated modal loader
3. Implement API documentation (Swagger/OpenAPI)
4. Add unit tests for transaction calculations
5. Implement caching for exchange rate lookups
