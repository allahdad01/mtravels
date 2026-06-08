# Attendance Module — Complete Feature Documentation

## Overview

The Attendance module is a full employee time-tracking system with check-in/check-out, automatic status calculation (present/late/half-day/absent), per-branch configurable rules, role-based visibility, and integration with the Salary module for absence-based payroll deductions.

---

## 1. Employee Self-Service: My Attendance

**Page:** `admin/attendance.php` (1546 lines) — accessible to all logged-in users

### Three States
| State | UI | Action |
|-------|----|--------|
| **Not checked in** | Pulsing ring, green "Check In" button, office hours displayed | Check In |
| **Checked in (working)** | Circular SVG progress ring (live-updating every 30s), red "Check Out" button, check-in time shown | Check Out |
| **Completed** | Checkmark icon, check-in/check-out times side by side, total hours badge, status badge | — |

### Features
- Live clock (updates every 1 second)
- Time-of-day greeting (morning/afternoon/evening)
- Monthly overview grid — present/late/half-day/absent counts
- Average daily hours with progress bar
- Total monthly hours
- Weekly view — 7-day grid with status icons
- Office hours info strip (start, end, late threshold, working days)
- Confirmation dialog before check-in/out
- Staggered fade-in/scale-in animations

---

## 2. Check-in / Check-out API

**Endpoint:** `api/attendance/process_attendance.php` — POST, JSON

### Check-in Flow
1. Verifies no existing record for today
2. Inserts record with current time as `check_in_time`, `working_minutes = 0`, status `present`
3. Timezone: `Asia/Kabul`

### Check-out Flow
1. Fetches today's record
2. Verifies not already checked out
3. Calculates `working_minutes = (check_out - check_in) / 60`
4. Determines final status algorithm:
   - `working_minutes >= expected` AND `check_in <= late_threshold` → **present**
   - `working_minutes >= expected` AND `check_in > late_threshold` → **late**
   - `working_minutes >= half_day_minutes` → **half_day**
   - `working_minutes < half_day_minutes` → **absent**

### Error Handling
Already checked in, already checked out, no check-in record, invalid action

---

## 3. Admin: Manage Attendance

**Page:** `admin/manage_attendance.php` (1623 lines) — admin only

### Filters
- Month picker (defaults to current)
- Employee dropdown (active/non-fired users)
- Status pills — All, Present, Late, Half-Day, Absent with counts

### Statistics Cards (6-card grid)
1. Present count + ring chart percentage
2. Late count + ring chart percentage
3. Half-day count + ring chart percentage
4. Absent count + ring chart percentage
5. Unique employees + days recorded
6. Average hours + total hours

### Daily Trend Mini-Bar Chart
- Vertical bars per day; height proportional to attended/total employees
- Today highlighted in green; hover tooltip

### Data Table
| Column | Content |
|--------|---------|
| Date | Day + weekday + month/year |
| Employee | Avatar/initials + name + email |
| Check In | Time badge (green) or dash |
| Check Out | Time badge (red) or dash |
| Duration | Hours/minutes + progress bar (max 8h) |
| Status | Color-coded pill (green/amber/blue/red) |
| Notes | Truncated with tooltip |
| Actions | View + Edit buttons |

### Detail Modal
Fetches HTML from `api/attendance/get_attendance_details.php` — employee info, status, check-in/out times with late/on-time badge, working vs expected hours, notes, office settings

### CSV Export
Triggers `api/attendance/export_attendance.php` with current filter params

### Pagination
25 records per page, ellipsis navigation

---

## 4. Admin: Edit Attendance Record

**Page:** `admin/edit_attendance.php` (875 lines) — admin only

| Feature | Details |
|---------|---------|
| **Form Fields** | Check-in time, check-out time, status (required), notes |
| **Validation** | Status required, check-out must be after check-in, invalid time format detection |
| **Auto Calculation** | Working minutes auto-calculated when both times provided |
| **Activity Logging** | Inserts into `activity_log` with old/new values JSON, IP, user agent |
| **CSRF Protection** | Token validated on POST |

---

## 5. Admin: Attendance Settings

**Page:** `admin/attendance_settings.php` (1601 lines) — admin only

### Office Hours
- Start time input (sunrise icon)
- End time input (sunset icon)
- Visual 24-hour timeline with gradient fill
- Expected hours auto-display
- **Live preview sidebar:** Analog clock, summary cards (opens, late after, closes, total hours)

### Attendance Thresholds
| Setting | Control | Default |
|---------|---------|---------|
| Late threshold | Range slider (1-120 min, step 1) | 15 min |
| Half-day threshold | Range slider (30-480 min, step 10) | 240 min (4h) |

### Working Days
- Visual day chips (M/T/W/T/F/S/S)
- Presets: Mon-Fri, Mon-Sat, Sat-Thu, Every Day
- Radio card-style selector

### Status Explanation Sidebar
- **Present** — Checked in on time before late threshold
- **Late** — Checked in after grace period
- **Half-day** — Worked less than minimum hours
- **Absent** — No attendance recorded

### JavaScript
- `updateTimeline()` — recalculates timeline bar and preview
- `updateLateValue()` / `updateHalfDayValue()` — slider counters + fill gradients
- `updateDaysVisual()` — toggles day chip classes
- `updateClockHands()` — rotates analog clock hands
- Auto-dismiss success alert after 4s

---

## 6. Super Admin: Branch Attendance

**Page:** `tenant_super_admin/branch_attendance.php`

- Month pill header
- Summary stat strip (present/late/half-day/absent)
- Filters: Month, Branch, Employee, Status
- Data table with branch pill, employee, check-in/out, working minutes, status, notes, view action
- Export button with branch filter
- Detail modal via same `get_attendance_details.php` API

---

## 7. Dashboard Integration

### Admin Dashboard (`admin/dashboard.php`)
- **Not checked in** — Shows office hours + "Attendance" button
- **Checked in** — Shows check-in time, live working time counter, "Check Out" button
- **Completed** — Shows check-out time and total hours
- Gradient purple widget with pulse animations

### Staff Dashboard (`staff_dashboard_view.php`)
- Monthly attendance summary (total/present/absent/leave counts)
- Attendance rate percentage with progress bar
- Link to `attendance.php`

---

## 8. Salary Module Integration

### Absence Data for Payments (`admin/get_salary_details.php`)
- Checks if `attendance` feature enabled
- Counts absent days for the payment month
- Checks if already deducted (via `salary_deductions` type `absence`)
- Returns `has_attendance_feature`, `absent_days`, `absence_already_deducted`

### Absence Warning in Payment Form (`admin/salary_payment.php`)
- Warning banner with potential deduction: `(base_salary / 30) * absent_days`
- "Apply Deduction" button calls `deduct_absence.php`

### Auto Deduction (`admin/deduct_absence.php`)
- Counts absent days from `attendance` table
- Prevents double deduction
- Calculates: `deduction_per_day = base_salary / 30`
- Inserts `salary_deductions` record with type `absence`

### Payroll Print (`admin/print_payroll.php`)
- Attendance summary block: total days, present, absent, late, half-day, total working minutes

---

## 9. Database Tables

| Table | Purpose |
|-------|---------|
| `attendance` | Daily attendance records — user_id, date, check_in/out times, working_minutes, status |
| `attendance_settings` | Per-branch configuration — office hours, late/half-day thresholds, working days |

---

## 10. Technical Architecture

### Admin Pages: 4 dedicated + 6 integrated
| Page | Lines | Purpose |
|------|-------|---------|
| `admin/attendance.php` | 1546 | Self-service check-in/out |
| `admin/manage_attendance.php` | 1623 | Admin management table |
| `admin/edit_attendance.php` | 875 | Edit single record |
| `admin/attendance_settings.php` | 1601 | Branch configuration |
| `admin/dashboard.php` | — | Attendance widget |
| `admin/staff_dashboard_view.php` | — | Staff attendance summary |
| `admin/get_salary_details.php` | — | Absence data for salary |
| `admin/deduct_absence.php` | 129 | Salary deduction for absences |
| `admin/salary_payment.php` | — | Absence warning in payments |
| `admin/print_payroll.php` | — | Attendance in payroll PDF |

### API Endpoints: 3 files
| File | Purpose |
|------|---------|
| `api/attendance/process_attendance.php` | Check-in/out JSON API (141 lines) |
| `api/attendance/get_attendance_details.php` | Detail modal HTML (409 lines) |
| `api/attendance/export_attendance.php` | CSV export (92 lines) |

### Super Admin: 1 file
`tenant_super_admin/branch_attendance.php`

### Access Roles
| Role | Access |
|------|--------|
| admin | Full — all pages |
| staff/sales/umrah/finance | My Attendance only |
| tenant_super_admin | Cross-branch attendance view |

### Feature Gating
- `attendance` feature flag controls all attendance visibility
- Defined in `features-helper.php` as "HR & Attendance" with `hr` icon key

### Status Calculation
```
expected_minutes = office_end - office_start
late_threshold = office_start + late_after_minutes

present  → working_minutes >= expected AND check_in <= late_threshold
late     → working_minutes >= expected AND check_in > late_threshold
half_day → working_minutes >= half_day_minutes
absent   → working_minutes < half_day_minutes
```

### Security
- CSRF on edit_attendance POST
- Role enforcement on all pages
- Multi-tenant/branch isolation on all queries
- Activity logging for edits

### Multilingual
English, Dari (fa), Pashto (ps) — ~50 attendance keys each

### Total Code
~8,200+ lines across dedicated attendance files
