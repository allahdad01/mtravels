# Umrah Module — Complete Feature Documentation

## Overview

The Umrah module in M.Travels manages family-based Umrah pilgrimage bookings. It supports the full lifecycle: family registration → member enrollment → payment tracking → document generation → date changes → cancellations/refunds → completion.

---

## 1. Family Management

| Feature | Description |
|---------|-------------|
| **Create Family** | Form in `create_family_modal.php` — head of family, contact, address, province, district, package type, location, Tazmin status, visa status |
| **Edit Family** | Full edit of all fields via `edit_family_modal.php` |
| **Delete Family** | Confirmation dialog, cascade handling for member bookings |
| **Card-based Listing** | Responsive grid cards with financial dashboard, filter pills (All/Not Applied/Applied/Issued/Refunded/Cancelled), search by name/contact/passport, pagination |
| **AJAX Refresh** | `refresh-families.js` — search/filter/pagination without full page reload |
| **Package Types** | Full Package, Visa, Services, Ticket+Visa, Visa+Services, Visa+Transport |

---

## 2. Member (Booking) Management

### Adding Members
- **Multi-member form** (`add_member_multi_refactored.js`) — single submission for multiple pilgrims
- **Common fields** applied to all members: Sold To (client), Paid To (main account)
- **Per-member fields**: name, father name, grandfather name, relation, DOB, gender, passport number/expiry, ID type, flight/return dates, duration, room type, price, currency (USD/AFS), remarks
- **Mahram info** required for female pilgrims
- **Passport expiry validation** — minimum 6 months validity
- **Default entry date** set to today

### Services Grid
- Dynamic add/remove service rows per member
- Service types: All Services, Ticket, Visa, Hotel, Transport
- Supplier selection with auto-populated currency
- Base price / sold price with auto-calculated profit
- Discount field with profit recalculation

### Edit / Delete / View
- **Edit** (`edit_member_modal.php`) — full edit of all booking fields + dynamic services grid
- **Delete** — remove individual member from a family
- **View Details** (`member_details_modal.php`) — personal info, photo, travel info, account info, services breakdown

### Booking Approval
- Confirmation dialog → creates supplier & client transactions → status changes from `pending` to `active`
- Handled by `approve_umrah_booking.php`

---

## 3. Financial Management

### Individual Transactions
- **Modal**: `transaction_modal.php` — multi-currency (USD/AFS/EUR/AED)
- **Dynamic exchange rate** with contextual examples
- **Transaction history** table with date, description, receipt, transaction_to, amount
- **Add transactions** — payment date, transaction_to, currency, exchange rate, receipt, amount
- **Payment validation** — amount checked against remaining due; receipt required for Bank/Internal Account
- **Edit/Delete** transactions

### Family-Level Transactions
- **Modal**: `family_transaction_modal.php`
- **Family financial summary**: total price, total paid, total due
- **Per-member breakdown** table
- **Add family payments** allocated across members

### Refunds
- **Modal**: `refund_modal.php` — full or partial refund
- Displays original amount and profit
- Refund amount field with max validation
- Reason required
- Currency displayed in original booking currency
- AJAX processing with loading states

---

## 4. Document Generation (PDF)

### Agreements
| Document | Files |
|----------|-------|
| Individual Umrah Agreement | `generate_umrah_agreement.php` |
| Tazmin (Guarantor) Agreement | `tazmin_agreement_template.php` — prompts for guarantor name |
| Family Agreement | `generate_family_agreement.php` — language selection (English/Dari/Pashto) |
| Refund Agreement | `umrah_refund_agreement_template.php` |

### Receipts
| Document | Files |
|----------|-------|
| Bank Receipt | `bank_receipt_modal.php` + `generate_bank_recipt.php` (Dari/Pashto) |
| Umrah Receipt | `print_umrah_receipt.php` |
| Family Receipt | `generate_family_receipt.php` |

### Certificates & Forms
| Document | Files |
|----------|-------|
| Individual Completion | `completion_details_modal.php` + `generate_umrah_completion.php` |
| Family Completion | `family_completion_details_modal.php` + `generate_family_completion.php` |
| Individual Cancellation | `cancellation_details_modal.php` + `generate_umrah_cancellation.php` |
| Family Cancellation | `family_cancellation_details_modal.php` + `generate_family_cancellation.php` |
| Document Receipt Form | `generations_received_form.js` — tracks passport, ID card, photos, vaccination cert, marriage cert, birth cert, visa form, mahram declaration |

### Special Documents
| Document | Details |
|----------|---------|
| **Umrah Presidency Letter** | Family head info, father name, ID number, visa/ticket amounts, airline, stay duration (Makkah/Madina), transport services, hotel info — Pashto version available |
| **Group Ticket** | Flight/return dates, airline/PNR, direct or connecting flights, multiple legs with auto stopover calculation, outbound/return journey, member selection with bulk family select |
| **ID Cards** | Up to 8 pilgrims per batch, card title, validity (1-90 days, default 45), 6 card colors, guide contact info, photo upload, floating action button with selection count |
| **Combined Multi-Ticket Invoice** | Select tickets across families, client/currency selection, auto-calculated total, comments |

### Language Support
- **3 languages**: English, Dari (Farsi), Pashto
- Language selection modals for all documents
- Separate template files: `*_en.php`, `*_fa.php`, `*_ps.php`

---

## 5. Date Change Management

| Feature | Details |
|---------|---------|
| **Single-Step Change** | `date_change_modal.php` — one modal from the member dropdown: new flight/return dates (duration auto-calculated), supplier + service penalties with live total, reason; applied in a single save |
| **Direct Apply** | `submit_date_change_request.php` — updates booking dates/prices, debits supplier/client balances for penalties, records history, recalculates family totals (no Pending/Approve/Process workflow) |
| **History Record** | Each change inserts a `Completed` row in `date_change_umrah` for the audit trail (approved_by/processed_by = applying user) |
| **Per-Booking History** | `get_booking_date_changes.php` |

---

## 6. Cancellation & Re-apply

| Feature | Details |
|---------|---------|
| **Cancel** | Sets profit to 0, marks booking as `cancelled` |
| **Re-apply** | Recalculates profit (sold price - base price), restores to `active` |
| **Status-restricted UI** | Cancelled → re-apply only; non-cancelled → cancel only; active (approved) → redirected to refund |
| **Current Values Display** | Base price, sold price, current profit, new profit |
| **Reason Required** | For both cancellation and re-apply |
| **Bulk Processing** | `process_bulk_cancellation_reapply.php` |
| **Family Cancellation** | Per-member document return tracking (passport returned, condition, notes) |

---

## 7. Passport & Document OCR

| Feature | Details |
|---------|---------|
| **Drag-and-drop Upload** | Multi-file upload zones with progress indicators |
| **OCR Pipeline** | Server-side PaddleOCR (primary) → client-side Tesseract.js (fallback) → MRZ parsing |
| **Auto-population** | Form fields auto-filled from extracted passport data |
| **Auto Photo Extraction** | `auto-passport-extractor.js` — server-side photo detection from passport images |
| **Interactive Crop** | `passport-photo-extractor.js` — canvas crop UI with zoom, drag-to-select, preview |
| **File Validation** | Type (JPG/PNG/GIF/PDF) and size (max 5-10MB) checks |

### Member Documents
- **Photo management**: upload (JPG/PNG/GIF, max 5MB), preview, delete with confirmation
- **Passport management**: upload (JPG/PNG/GIF/PDF, max 5MB), inline preview (images) / icon display (PDFs), delete

---

## 8. Flight Status & Details

| Feature | Details |
|---------|---------|
| **Flight Status Badges** | Per-family badges on card: complete (✓ Flight Done), partial (⚠), pending |
| **Status Calculation** | Compares members with flight vs total members via `get_group_ticket_info.php` |
| **Flight Details Modal** | Styled ticket cards, member lists, airline/PNR/date info, loading/error/empty states |

---

## 9. Bulk Operations

| Feature | Details |
|---------|---------|
| **Bulk Flight Date Update** | Multi-select families, apply common flight/return dates, confirmation |
| **Bulk Cancellation/Re-apply** | Process multiple bookings at once |
| **Bulk Family Selection** | For group tickets and ID cards — select all families with one click |

---

## 10. Security

| Feature | Details |
|---------|---------|
| **CSRF Protection** | Token generation and validation on all POST requests |
| **Role-based Access** | Allowed roles: admin, finance, sales, umrah |
| **Tenant Isolation** | All queries filtered by `tenant_id` |
| **Branch Isolation** | All queries filtered by `branch_id` |
| **Session Timeout** | 30-minute inactivity timeout |
| **Rate Limiting** | IP-based per endpoint |
| **Security Headers** | CSP, XSS protection, clickjacking, HSTS, referrer policy |
| **SQL Injection Prevention** | All queries use prepared statements with PDO |

---

## 11. UI/UX Features

| Feature | Details |
|---------|---------|
| **Modern Design System** | CSS custom properties, gradient headers, card shadows |
| **Financial Dashboard** | Revenue, collected, outstanding stats |
| **Filter Pills** | Color-coded with badges |
| **Search** | Enhanced input with icon, clear button |
| **Family Cards** | Responsive grid, expandable members section, animations |
| **Floating Action Buttons** | Group ticket & ID card selection with badge counts |
| **Empty States** | Icon + message + call-to-action |
| **Responsive** | Mobile breakpoints at 768px and 480px |
| **Print Styles** | Hide interactive elements, break-avoid for cards |

---

## 12. Technical Architecture

### Database Tables (8)
| Table | Purpose |
|-------|---------|
| `families` | Family/group registrations |
| `umrah_bookings` | Individual pilgrim bookings |
| `umrah_booking_services` | Service breakdown per booking (ticket/visa/hotel/transport) |
| `umrah_transactions` | Payment transactions |
| `umrah_refunds` | Refund records |
| `umrah_agreements` | Stored agreement document records |
| `date_change_umrah` | Date change request tracking |
| `family_cancellations` | Family-level cancellation records |

### API Endpoints: 81 files in `api/umrah/`
| Category | Count | Key Examples |
|----------|-------|--------------|
| Family CRUD | 7 | `create_family.php`, `load_family_members.php` |
| Booking CRUD | 12 | `add_umrah_multi.php`, `approve_umrah_booking.php` |
| Transactions | 10 | `add_umrah_transaction.php`, `get_family_transaction_data.php` |
| Refunds | 6 | `process_umrah_refund.php`, `print_umrah_refund.php` |
| Date Changes | 7 | `submit_date_change_request.php` |
| Cancellation/Re-apply | 5 | `process_cancellation_reapply.php` |
| Document Generation | 20+ | `generate_family_agreement.php`, `generate_id_cards.php`, `generate_group_ticket.php` |
| Document Templates | 9 | Language-specific (en/fa/ps) |
| Passport/Photo | 4 | `extract_passport_photo.php`, `extract_text.php` |
| Group Tickets | 2 | `save_group_ticket.php` |

### JavaScript: 31 files in `js/umrah/`
Key files: `add_member_multi_refactored.js` (1160 lines), `transaction_manager.js` (810 lines), `document-upload-handler.js` (679 lines), `passport-photo-extractor.js` (567 lines)

### Modals: 30 files in `modals/umrah/`

---

## 13. End-to-End Workflow

1. **Create Family** → form in `create_family_modal.php` → `api/umrah/create_family.php`
2. **Add Members** → `umrah_modal.php` → upload passport docs (OCR auto-fills data) → add services with supplier pricing → `add_umrah_multi.php`
3. **Approve Booking** → confirmation dialog → `approve_umrah_booking.php` creates supplier/client transactions
4. **Manage Transactions** → `transaction_modal.php` → view history → add/edit/delete payments in multiple currencies
5. **Generate Documents** → agreements, receipts, certificates, ID cards, group tickets, presidency letters
6. **Date Changes** → submit request → admin approves/rejects → audit trail
7. **Cancellation/Refund** → cancel with re-apply option OR process full/partial refund
8. **Close Out** → completion certificate → document return tracking
