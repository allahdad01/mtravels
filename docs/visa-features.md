# Visa Module — Complete Feature Documentation

## Overview

The Visa Management module handles the full lifecycle of visa applications: creation, status tracking (pending/approved/issued/rejected/cancelled/withdrawn/refunded), payment transactions, document uploads, refunds, re-applications, and multi-visa invoicing. Multi-tenant and multi-branch.

---

## 1. Application Lifecycle

**Admin page:** `admin/visa.php` | **Client portal:** `client/visa.php`

### Create Application
- **Modal:** `modals/visa/add_visa_modal.php`
- **Fields:** supplier, sold-to client, paid-via account, applicant name, passport number, country, visa type, title (Mr/Mrs/Child), gender, phone, dates (receive/applied/issued), base/sold amounts (auto profit calc), currency (auto from supplier), remarks
- **WhatsApp notification** sent on creation

### Edit Application
- **Modal:** `modals/visa/edit_visa_modal.php` — same fields as create, profit recalculated on change

### Delete Application
- Hard delete with SweetAlert2 confirmation

### Status Tracking
- **Statuses:** Pending, Approved, Issued, Rejected, Cancelled, Withdrawn, Refunded
- **Color-coded badges** (green/amber/red) with left-edge card stripes
- **Filter chips:** All, Pending, Approved, Issued, Rejected, Cancelled (client-side instant filter)

### Status Operations
| Operation | Action | API |
|-----------|--------|-----|
| Approve | Pending → Approved | `api/visa/approve_visa.php` |
| Cancel/Reject/Withdraw | Status change with reason | `api/visa/visa_cancellation.php` |
| Re-apply | Restore to Pending/Approved, restore profit | `api/visa/visa_reapply.php` |

---

## 2. Detail View (Passport-Document Theme)

**Admin page:** `admin/visa_detail.php` | **Client portal:** `client/visa_detail.php`

- **Passport-document UI** — Navy header, gradient globe emblem, gold corner brackets, perforated edge, MRZ strip, hologram shimmer
- **Status stamp** — Rotated circular stamp (approved=green, processing=amber, rejected=red, pending=blue)
- **Sections:** Personal details, visa details, financial band (sold/base/profit), party cards (client/supplier with email/phone), remarks, transaction ledger with filter pills (client/supplier/main-account)

---

## 3. Financial Management

**Modal:** `modals/visa/transaction_modal.php` | **JS:** `js/visa/transaction_manager.js`

| Feature | Details |
|---------|---------|
| **Auto Profit** | Profit = Sold - Base, calculated as user types |
| **Multi-Currency** | USD, EUR, AFS (Afghani), DARHAM |
| **Exchange Rates** | Conversion for non-USD currencies |
| **Payment CRUD** | Add/edit/delete payments with date, time, amount, currency, exchange rate, receipt, description |
| **Payment Progress Bar** | Visual indicator on cards: green (paid), amber (partial with %), red (unpaid) |
| **Financial Ledger** | Unified transaction history with client/supplier/main-account filter pills |
| **Color-coded Profit** | Green (positive), red (negative) |

---

## 4. Refund Management

**Admin page:** `admin/visa_refunds.php`

| Feature | Details |
|---------|---------|
| **Full Refund** | Refund entire amount, profit set to 0 |
| **Partial Refund** | Proportional refund with partial profit recalculation |
| **Process Refund** | Create refund record + trigger refund transaction (agency clients) |
| **Refund Transactions** | Manage refund payments with multi-currency, exchange rates |
| **Print Refund Receipt** | Printable refund receipt |
| **Print Refund Agreement** | Agreement letter PDF with letterhead via `visa_refund_agreement_template.php` |
| **Delete Refund** | With confirmation |
| **Refund List** | Card-based view with applicant, passport, country, reason, amount, processed/pending status |

---

## 5. Document Management

**Modal:** `modals/visa/documents_modal.php` | **JS:** `js/visa/document_manager.js`

| Feature | Details |
|---------|---------|
| **Upload** | Drag-and-drop or click-to-browse; formats: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX; max 10MB; multiple files |
| **Document Types** | Pre-defined types with live-search select; option for custom names; required documents marked with red dot |
| **Preview** | Images shown inline, PDFs embedded, status badges (pending/approved/rejected) |
| **Delete** | Remove uploaded documents |
| **API** | `api/visa_document_types.php` for type CRUD, `api/visa_documents_upload.php` for file uploads |

---

## 6. Invoicing

| Feature | Details |
|---------|---------|
| **Multi-Visa Invoice** | FAB button on listing → select multiple visas → generate combined invoice (USD/AFS) |
| **Single Receipt** | Print individual visa receipt via `print_visa_receipt.php` |

---

## 7. Search & Filtering

| Feature | Details |
|---------|---------|
| **Search** | By passport number, applicant name, or phone |
| **Status Filter Chips** | All, Pending, Approved, Issued, Rejected, Cancelled — client-side instant |
| **AJAX Search** | Dedicated endpoint at `admin/ajax/search_visas.php` |
| **Pagination** | 10 per page |

---

## 8. Client Portal

| Page | Purpose |
|------|---------|
| `client/visa.php` | Dashboard with summary stats, status pipeline bar, table of applications |
| `client/visa_detail.php` | Passport-document themed single visa view (client-only fields) |
| `client/visa_refunds.php` | Refund dashboard with summary cards, processed/pending split |

---

## 9. Super Admin

**Page:** `tenant_super_admin/visa_applications.php`

- Cross-branch view of all visa applications
- Filter by branch, search by applicant/passport/country/visa type
- Details modal with Summary + Application Details tabs, financial strip

---

## 10. Database Tables

| Table | Purpose |
|-------|---------|
| `visa_applications` | Core visa applications with full applicant/financial/status fields |
| `visa_refunds` | Refund records with type, amount, reason, processed flag |
| `visa_transactions` | Legacy/secondary payment transaction records |
| `visa_document_types` | Pre-defined document type categories |

---

## 11. Technical Architecture

### Admin Pages: 4 files
`admin/visa.php`, `admin/visa_detail.php`, `admin/visa_refunds.php`, `admin/ajax/search_visas.php`

### Client Portal: 3 files
`client/visa.php`, `client/visa_detail.php`, `client/visa_refunds.php`

### Super Admin: 1 file
`tenant_super_admin/visa_applications.php`

### Modal Files: 14 files
- `modals/visa/` (10) — add, edit, details, refund, reapply, cancellation, documents, transaction, edit_transaction, multi_visa
- `modals/visa_refund/` (2) — transaction, edit_transaction

### JavaScript: 16 files
- `js/visa/` (12) — add, edit, details, refund, transaction_manager, document_manager, cancel_reapply, invoice, search, profit_calc, supplier_currency, select2, toast
- `js/visa_refund/` (3) — visa_delete, refund_management, transaction_manager

### API Endpoints: 28 files under `api/visa/`

| Category | Count | Key Endpoints |
|----------|-------|--------------|
| Application CRUD | 3 | `add_visa.php`, `update_visa.php`, `delete_visa.php` |
| Status Operations | 4 | `approve_visa.php`, `visa_cancellation.php`, `visa_reapply.php` |
| Transactions | 6 | `add_visa_transaction.php`, `update_visa_transaction.php`, `delete_visa_transaction.php`, `fetch_visa_transactions.php` |
| Refunds | 6 | `process_visa_refund.php`, `delete_visa_refund.php`, `get_visa_refund_transactions.php`, `process_visa_refund_transaction.php` |
| Printing | 3 | `print_visa_receipt.php`, `print_visa_refund.php`, `print_visa_refund_receipt.php` |
| Invoicing | 2 | `generate_multi_visa_invoice.php`, `fetch_visa_for_invoice.php` |
| Documents | 2 | `visa_document_types.php`, `visa_documents_upload.php` |
| Fetch | 3 | `fetch_visa_by_id.php`, `get_visa_details.php`, `get_visa_transaction.php` |

### Feature Flags
- `visa_applications` — main visa page and submenu
- `visa_refunds` — refund page and submenu
- `$showVisa` — parent "Visa Management" nav visibility

### Notifications
- **WhatsApp** notification sent on new visa creation via `WhatsAppManager`

### Multilingual
English, Dari (fa), Pashto (ps) for all UI strings
