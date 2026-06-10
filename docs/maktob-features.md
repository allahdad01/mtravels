# Maktob (Letter Management) Module — Complete Feature Documentation

## Overview

Multi-tenant, branch-aware official correspondence management system with auto-numbering, multi-language (English/Dari/Pashto) with RTL support, PDF generation, dual file attachments, full audit logging (`maktob_logs`), status workflow (draft → sent → archived), and A4-formatted letter download.

---

## 1. Letter CRUD

**Page:** `admin/manage_maktobs.php` (1,155 lines) | **API:** `api/maktob/manage.php`

| Action | API | Detail |
|--------|-----|--------|
| **Create** | POST to `manage.php` | Form via inline accordion. Fields: maktob_number (auto), subject, content, company_name, maktob_date, language, sender_id, pdf_file (upload), attachment (upload). Logs to `maktob_logs` (action=`create`). |
| **Edit** | `api/maktob/update_maktob.php` | Modal: `modals/maktob/edit_modal.php`. Update number, date, company, subject, content, language, replace files. Logs to `maktob_logs` (action=`edit`). |
| **Delete** | `api/maktob/delete_maktob.php` | Modal: `modals/maktob/delete_modal.php`. Soft-delete with file cleanup from disk. Logs to `maktob_logs` (action=`delete`). |
| **View** | JS modal | Modal: `modals/maktob/view_modal.php`. Shows all fields + file attachment links. |

**JS:** `js/maktob/main.js` (94 lines) — view/edit/delete button handlers, modal data population.

---

## 2. Auto-Numbering System

**Format:** `{tenant_id}-{branch_id}-{YYYYMMDD}-{NNN}`

**API:** `api/maktob/get_next_number.php`

- Sequence auto-increments per tenant + branch + date combination
- Leading zeros: 3 digits (001 → 999)
- Base date from `maktob_date` field
- Fetched via AJAX when date or company changes

---

## 3. Status Workflow

| Status | Description | Transition |
|--------|-------------|------------|
| `draft` | Initial state on creation | → sent via `update_status.php` |
| `sent` | Marked as sent to branch | Cannot be reverted |
| `archived` | Archived for record-keeping | Only from draft |

**API:** `api/maktob/update_status.php`

- Validates: only draft can be sent, only draft can be archived, already-archived cannot be re-archived
- Logs to `maktob_logs` (action=`send` or `archive`)
- On `send`: sends email notification to branch admin

---

## 4. PDF / Letter Download

**API:** `api/maktob/download_maktob.php`

Generates A4-formatted HTML document with:

| Section | Detail |
|---------|--------|
| **Header** | Gradient background, company logo, name, address, phone, email |
| **Metadata** | Branch badge, reference number, date |
| **Title** | "OFFICIAL COMMUNICATION" / "مکتوب رسمی" for Dari/Pashto |
| **Body** | Recipient (To), Subject, letter content |
| **Signature** | Sender name + "Authorized Signatory" |
| **Footer** | Branch info + document reference |

- RTL support for Dari/Pashto (fonts: xwzar, Tahoma, Arial)
- Print button (hidden during browser print)

---

## 5. File Uploads

Per letter, two files can be attached:

| Field | DB Column | Path |
|-------|-----------|------|
| PDF Letter | `pdf_path` | `uploads/maktobs/` |
| Supporting Document | `file_path` | `uploads/maktobs/` |

**Class:** `includes/SecureFileUpload.php`

| Setting | Value |
|---------|-------|
| Max size | 10MB |
| Allowed types | JPEG, PNG, GIF, PDF, DOC, DOCX, TXT, XLS, XLSX |
| Cleanup | Both files deleted from disk on letter delete |

---

## 6. Audit Logging (`maktob_logs`)

| Column | Detail |
|--------|--------|
| action | `create`, `edit`, `delete`, `send`, `archive` |
| old_values | JSON snapshot before change |
| new_values | JSON snapshot after change |
| ip_address | Client IP |
| user_id | Acting user |
| tenant_id / branch_id | Multi-tenant isolation |

FK constraints cascade with `maktobs`, `users`, `tenants`.

---

## 7. Dashboard / KPI Strip

Displayed at top of `manage_maktobs.php`:

| Card | Detail |
|------|--------|
| Total Letters | Count |
| Sent Letters | Count where `status = 'sent'` |
| Draft Letters | Count where `status = 'draft'` |
| Archived Letters | Count where `status = 'archived'` |
| This Month | Letters created this month |

---

## 8. Database Tables

### `maktobs`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| tenant_id | int(11) FK | CASCADE on delete |
| maktob_number | varchar(50) | Auto-generated |
| subject | varchar(255) | |
| content | text | |
| company_name | varchar(255) | Recipient company |
| maktob_date | date | |
| sender_id | int(11) FK | → `users(id)` CASCADE delete |
| status | enum('draft','sent','archived') | Default 'draft' |
| language | varchar(10) | english, dari, pashto |
| file_path | varchar(255) | Attachment |
| pdf_path | varchar(255) | PDF letter file |
| branch_id | bigint(20) | |

### `maktob_logs`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| maktob_id | int(11) FK | → `maktobs(id)` CASCADE delete |
| user_id | int(11) FK | → `users(id)` CASCADE delete |
| action | enum('create','edit','delete','send','archive') | |
| old_values | text | JSON |
| new_values | text | JSON |
| ip_address | varchar(45) | |

---

## 9. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full CRUD, send, archive, delete, download |
| `finance` | Full CRUD, send, archive, delete, download |
| Others | No access |

**Feature gate:** `manage_maktobs` under `business_operations` in pricing-helper.php.

**Menu visibility:** `hasFeature('manage_maktobs')` + `staffCanSeeMenu()`.

---

## 10. Language Support

| Language | File | RTL |
|----------|------|-----|
| English | `includes/languages/en/common.php` | No |
| Dari/Farsi | `includes/languages/fa/common.php` | Yes |
| Pashto | `includes/languages/ps/common.php` | Yes |

PDF download applies `dir="rtl"` and appropriate fonts for Dari/Pashto.

---

## 11. File Map

```
admin/
  manage_maktobs.php                   # Main page (1,155 lines)
api/maktob/
  manage.php                           # GET (list) + POST (create)
  update_maktob.php                    # Edit + file uploads
  delete_maktob.php                    # Soft-delete + file cleanup
  download_maktob.php                  # A4 PDF/HTML view
  update_status.php                    # Draft → Sent → Archived
  get_next_number.php                  # Auto-numbering AJAX
modals/maktob/
  view_modal.php                       # View details
  edit_modal.php                       # Edit form
  delete_modal.php                     # Delete confirmation
js/maktob/
  main.js                              # View/Edit/Delete handlers (94 lines)
css/general/
  modal-styles.css                     # Shared modal styling
includes/
  SecureFileUpload.php                 # File upload handler
  pricing-helper.php                   # Feature gate definition
  features-helper.php                  # Feature description
  nav_items.php                        # Menu entry (line 422)
uploads/
  maktobs/                             # Uploaded letter files
  tutorials/system_tutorials/
    letters-manage.png                 # Tutorial screenshot
    letter-buttons.png                 # Tutorial screenshot
```
