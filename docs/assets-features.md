# Assets Module — Complete Feature Documentation

## Overview

A self-contained, single-page CRUD application (`admin/assets.php`) for managing company assets with full lifecycle tracking (active/maintenance/sold/disposed), depreciation calculation, document upload, Chart.js analytics, multi-currency value tracking, and client-side filtering. No dedicated API, modal, or JS files — everything is inline.

---

## 1. Asset CRUD

**Page:** `admin/assets.php` (all logic inline)

| Action | Trigger | Detail |
|--------|---------|--------|
| **Add** | `add_asset` POST | Validated via `InputValidator` (`getString`, `getDate`, `getEnum`). Optional document upload via `SecureFileUpload` (10MB max, jpg/png/gif/pdf/doc/txt/xls). |
| **Edit** | `edit_asset` POST | Update all fields. Optional document replacement — old file cleaned up via `unlink()`. |
| **Delete** | `delete_asset` POST | Removes DB record + document file. Path traversal prevention via `realpath()` check. |
| **View** | JavaScript eye icon | Inline modal built dynamically via JS — shows all fields, depreciation bar, document link |
| **Status Change** | `change_status` POST | Updates to any valid enum value |
| **Deactivate** | `deactivate_asset` POST | Sets `status = 'inactive'` |
| **Reactivate** | `reactivate_asset` POST | Sets `status = 'active'` |

---

## 2. Asset Fields

| Field | Type | Detail |
|-------|------|--------|
| name | varchar(255) | Required |
| category | varchar(100) | Electronics, Furniture, Vehicle, Office Equipment, Real Estate, Software, Other |
| purchase_date | date | Required |
| purchase_value | decimal(15,2) | Required |
| current_value | decimal(15,2) | Required — auto-filled = purchase value in JS |
| currency | varchar(10) | USD, EUR, AFS, DARHAM, PKR, INR |
| description | text | Optional |
| location | varchar(255) | Physical location |
| serial_number | varchar(100) | |
| warranty_expiry | date | |
| status | enum | active, inactive, maintenance, sold, disposed |
| assigned_to | varchar(255) | Employee/person assigned |
| condition_state | varchar(100) | New, Excellent, Good, Fair, Poor |
| document | varchar(255) | Uploaded file path |

---

## 3. Status Lifecycle

```
active ──► inactive
active ──► maintenance
active ──► sold
active ──► disposed
```

Each status transition is confirmed via SweetAlert2 dialog.

---

## 4. Depreciation Tracking

**Formula:** `max(0, 100 - (current_value / purchase_value * 100))`

Displayed as a progress bar in both table rows and the View Details modal:

| Depreciation | Color |
|-------------|-------|
| < 25% | Green |
| 25% – 50% | Yellow |
| 50% – 75% | Orange |
| > 75% | Red |

---

## 5. Charts (Chart.js v4.4.0)

| Chart | Type | Purpose |
|-------|------|---------|
| Category Distribution | Doughnut | Assets grouped by category |
| Status Distribution | Bar | Count per status (active/inactive/maintenance/sold/disposed) |

---

## 6. Filtering

### Server-Side (Tab-based)
Status tabs: All, Active, Maintenance, Inactive, Sold, Disposed

### Client-Side (JS inline)
| Filter | Input Type |
|--------|-----------|
| Category | Dropdown |
| Location | Text input |
| Purchase Date | Date range (from/to) |
| Condition | Dropdown (New/Excellent/Good/Fair/Poor) |
| Free-text Search | Searches name, serial_number, category |

---

## 7. Dashboard / KPI Cards

| Card | Detail |
|------|--------|
| Total Assets | Count |
| Active Assets | Count |
| In Maintenance | Count |
| Inactive/Disposed | Combined count |
| Value by Currency | Pills showing total current value per currency |

---

## 8. Document Upload

**Class:** `includes/SecureFileUpload.php`

| Setting | Value |
|---------|-------|
| Max size | 10MB |
| Allowed types | jpg, jpeg, png, gif, pdf, doc, docx, txt, xls, xlsx |
| Upload path | `uploads/assets/` |
| Security | Path traversal prevention, MIME validation |

---

## 9. Database Table

### `assets`
| Column | Type | Detail |
|--------|------|--------|
| id | int(11) PK | Auto-increment |
| tenant_id | int(11) FK | CASCADE on delete |
| name | varchar(255) | |
| category | varchar(100) | 7 predefined |
| purchase_date | date | |
| purchase_value | decimal(15,2) | |
| current_value | decimal(15,2) | |
| currency | varchar(10) | 6 supported |
| description | text | |
| location | varchar(255) | |
| serial_number | varchar(100) | |
| warranty_expiry | date | |
| status | enum(5 values) | active/inactive/maintenance/sold/disposed |
| assigned_to | varchar(255) | |
| condition_state | varchar(100) | |
| document | varchar(255) | |
| branch_id | bigint(20) | |

---

## 10. Role & Permission Model

| Role | Access |
|------|--------|
| `admin` | Full CRUD, status changes |
| `finance` | Full CRUD, status changes |
| Others | No access |

**Feature gate:** `assets` under `business_operations` in pricing-helper.php.

---

## 11. Languages

| Language | File |
|----------|------|
| English | `includes/languages/en/common.php` (~50+ keys) |
| Dari/Farsi | `includes/languages/fa/common.php` |
| Pashto | `includes/languages/ps/common.php` |

---

## 12. Security

| Measure | Detail |
|---------|--------|
| CSRF | `validateCsrf()` on all POST actions |
| Input validation | `InputValidator` class (`getString`, `getDate`, `getEnum`) |
| File upload | `SecureFileUpload` with path traversal prevention |
| Auth | Session authentication + role check |
| Tenant isolation | `tenant_id` + `branch_id` in all queries |
| Delete safety | `realpath()` check before `unlink()` |

---

## 13. Dependencies

| File / CDN | Purpose |
|------------|---------|
| `includes/InputValidator.php` | Input sanitization |
| `includes/SecureFileUpload.php` | Document upload |
| `includes/db_security.php` | DB security utilities |
| Chart.js v4.4.0 (CDN) | Charts |
| Font Awesome v6.5.0 (CDN) | Icons |
| SweetAlert2 (CDN) | Confirmation dialogs |
| Google Fonts (Syne, DM Sans) | Typography |

---

## 14. File Map

```
admin/
  assets.php                    # Single-file module (all CRUD + UI + JS + charts)
css/
  assets/
    styles.css                  # Legacy asset styling
includes/
  InputValidator.php            # Input validation
  SecureFileUpload.php          # File upload handler
uploads/
  tutorials/assets_tutorials/
    assets.png                  # Tutorial screenshot
    add-assets.png              # Tutorial screenshot
  assets/                       # Upload directory (referenced, may be empty)
```
