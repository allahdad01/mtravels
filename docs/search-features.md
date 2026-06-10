# Search Module — Complete Feature Documentation

## Overview

Global cross-entity search across 9 record types (Ticket, Ticket Reservation, Visa, Hotel, Umrah, Additional Payment, Expense, Creditor, Debtor) with transaction panel expansion, type-based filter chips, and direct detail-page links.

---

## 1. Page

**File:** `admin/search.php` (990 lines — single-file module)

| Aspect | Detail |
|--------|--------|
| **Method** | POST form submission |
| **CSRF** | Token included in hidden field (`csrf_token`) |
| **Input validation** | `DbSecurity::validateInput()` — string, max 255 chars |
| **Search fields** | Single free-text search box; searches name, PNR/passport number, and phone per entity |

---

## 2. Searchable Entities (9 types)

| # | Record Type | Table | Search Fields | Detail Page Link |
|---|-------------|-------|---------------|-----------------|
| 1 | **Ticket** | `ticket_bookings` | passenger_name, pnr, phone | `ticket_detail.php?id=` |
| 2 | **Ticket Reservation** | `ticket_reservations` | passenger_name, pnr, phone | `ticket_reservation_detail.php?id=` |
| 3 | **Visa** | `visa_applications` | applicant_name, passport_number, phone | `visa_detail.php?id=` |
| 4 | **Hotel** | `hotel_bookings` | CONCAT(first_name, last_name), order_id, contact_no | `hotel_detail.php?id=` |
| 5 | **Umrah** | `umrah_bookings` | name, passport_number, id_type | `umrah_detail.php?id=` |
| 6 | **Additional Payment** | `additional_payments` | description, payment_type | `additional_payments_detail.php?id=` |
| 7 | **Expense** | `expenses` + `expense_categories` | e.description, ec.name | `expense_detail.php?id=` |
| 8 | **Creditor** | `creditors` | cr.name, cr.email, cr.phone | `creditors_detail.php?id=` |
| 9 | **Debtor** | `debtors` | db.name, db.email, db.phone | `debtors_detail.php?id=` |

---

## 3. Query Pattern

All 9 queries follow the same structure:
- `LEFT JOIN clients c ON sold_to = c.id AND c.branch_id = ?`
- `LEFT JOIN suppliers s ON supplier = s.id AND s.branch_id = ?`
- `WHERE tenant_id = ? AND branch_id = ? AND (field1 LIKE ? OR field2 LIKE ? OR field3 LIKE ?)`
- `LIKE %searchTerm%` (case-insensitive by default MySQL)

---

## 4. Transaction Lookup

For each result, a secondary query fetches related `main_account_transactions`:

```php
$txnOf = match($record_type) {
    'Ticket'             => 'ticket_sale',       // OR ticket_refund OR date_change
    'Ticket Reservation' => 'ticket_reservation',
    'Visa'               => 'visa_sale',
    'Hotel'              => 'hotel_booking',
    'Umrah'              => 'umrah_booking',
    'Additional Payment' => 'additional_payment',
    'Expense'            => 'expense',
    'Creditor'           => 'creditor',
    'Debtor'             => 'debtor',
};
```

Ticket type matches 3 transaction_of values: `ticket_sale`, `ticket_refund`, `date_change`.

---

## 5. UI Features

| Feature | Detail |
|--------|--------|
| **Hero Search Bar** | Gradient background, pill-shaped input with icon, responsive |
| **Stats Bar** | Result count + type filter chips (dynamic — shows only types present in results) |
| **Filter Chips** | Client-side toggle via JS `data-filter` attribute. Options: All + each record type found |
| **Result Cards** | Color-coded by type (9 color schemes), gradient icons, info grid |
| **Transaction Panel** | Expandable per card — toggle button with chevron rotation. Shows table: type, transaction, amount, description, date. "View All Transactions" link to detail page |
| **Empty State** | Initial state (before search) + no-results state |
| **Responsive** | 3 breakpoints: 768px, 480px — grid adjusts, actions reflow |

---

## 6. Color Coding

| Record Type | Icon Color | Badge Color |
|-------------|-----------|-------------|
| Ticket | Blue `#4099ff` | Light blue bg |
| Ticket Reservation | Purple `#7c5cfc` | Light purple bg |
| Visa | Orange `#f97316` | Light orange bg |
| Hotel | Teal `#2ed8b6` | Light green bg |
| Umrah | Amber `#f59e0b` | Light yellow bg |
| Additional Payment | Cyan `#06b6d4` | Light cyan bg |
| Expense | Gray `#6b7a99` | Light gray bg |
| Creditor | Red `#ef4444` | Light red bg |
| Debtor | Violet `#8b5cf6` | Light violet bg |

---

## 7. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full access |
| `finance` | Full access |
| Others | No access (redirect to login) |

**Feature gate:** Not feature-gated. Menu entry not present in `nav_items.php` (accessible only via direct URL or bookmark).

---

## 8. Languages

| Language | Key | Translation |
|----------|-----|-------------|
| English | `search` | Search |
| English | `search_for_people` | Search for people, bookings, transactions... |
| English | `search_by_name_passport_number_phone_number_or_any_other_identifier` | Search by name, passport number, phone number... |
| Dari/Farsi | `search` | جستجو |
| Pashto | `search` | لټون |

---

## 9. Dependencies

| File / CDN | Purpose |
|------------|---------|
| `admin/security.php` | `enforce_auth()` |
| `admin/includes/db_security.php` | `DbSecurity::validateInput()` |
| `includes/db.php` | PDO connection |
| `includes/header.php` | Admin header |
| `includes/admin_footer.php` | Admin footer |
| `includes/language_helpers.php` | `__()` translation |
| Font Awesome 6.4.0 | Icons |
| Google Fonts (Plus Jakarta Sans, DM Mono) | Typography |

---

## 10. File Map

```
admin/
  search.php                    # Single-file search module (990 lines)
  security.php                  # Auth enforcement
  includes/db_security.php      # Input validation
includes/
  db.php                        # Database connection
  header.php                    # Admin header
  admin_footer.php              # Admin footer
  language_helpers.php          # Translation
  languages/en/common.php       # Search translation keys
  languages/fa/common.php       # Search translation keys
  languages/ps/common.php       # Search translation keys
```
