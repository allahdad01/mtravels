# ⏰ M.Travels Attendance System — Features

Track employee attendance with check-in/check-out, automatic status calculation, configurable office rules, and seamless integration with payroll.

---

## 👤 My Attendance (Employee Self-Service)

Every employee can check in and out right from their dashboard.

- **Check In** — One click to start your workday; the system records the exact time
- **Check Out** — One click to end your workday; the system automatically calculates your status (present, late, half-day)
- **Live Progress Ring** — While checked in, see a circular progress bar showing how much of your workday is complete, updating every 30 seconds
- **Real-Time Clock** — Live clock display with time-of-day greeting (good morning/afternoon/evening)
- **Monthly Overview** — See your present, late, half-day, and absent counts for the month
- **Weekly View** — 7-day grid showing your daily status at a glance
- **Average & Total Hours** — Track your average daily hours and total monthly hours
- **Office Hours Info** — Always visible reminder of office start time, end time, late threshold, and working days

---

## 📊 Manage Attendance (Admin)

A complete attendance dashboard for management.

- **Monthly Filters** — View attendance for any month; filter by employee or status (All, Present, Late, Half-Day, Absent)
- **Statistics Cards** — 6 cards showing: present count, late count, half-day count, absent count (all with ring chart percentages), unique employees, days recorded, and average/total hours
- **Daily Trend Chart** — Mini bar chart showing attendance rate per day; today highlighted in green
- **Detailed Table** — Employee name, check-in time, check-out time, duration (with progress bar), status (color-coded pill), notes, and action buttons
- **View Details** — Click to see full attendance info: employee details, check-in/out times with late/on-time badges, working vs expected hours
- **Edit Records** — Fix any attendance record: update times, change status, add notes; all changes are logged
- **Export to CSV** — Download filtered attendance data as a spreadsheet
- **Pagination** — 25 records per page

---

## ⚙️ Attendance Settings (Admin)

Configure how attendance works for your branch — all visual and interactive.

### Office Hours
- Set start and end time for the workday
- Visual 24-hour timeline shows your configured hours with a gradient fill
- Live preview sidebar with an analog clock showing the start time

### Attendance Thresholds
- **Late Threshold** — Drag a slider to set how many minutes after start time is still considered "on time" (1-120 minutes)
- **Half-Day Threshold** — Drag a slider to set the minimum minutes to count as a half-day instead of absent (30-480 minutes)

### Working Days
- Choose from presets: Mon-Fri, Mon-Sat, Sat-Thu, Every Day
- Visual day chips highlight based on your selection

### How Status Is Calculated
The system automatically determines each employee's daily status:
- ✅ **Present** — Checked in on time (before the late threshold) and worked full hours
- ⏰ **Late** — Checked in after the grace period but worked full hours
- 🌗 **Half-Day** — Worked at least the half-day minimum but less than full hours
- ❌ **Absent** — Worked less than the half-day minimum (or didn't check in at all)

---

## 👑 Super Admin: Branch Attendance

- **Cross-Branch View** — See attendance across all branches in one place
- **Filters** — By month, branch, employee, and status
- **Statistics Strip** — Present/late/half-day/absent counts
- **Export** — Download CSV with branch filter

---

## 🔗 Salary Module Integration

Attendance automatically feeds into payroll:

- **Absence Deductions** — When processing salary, the system shows how many days the employee was absent and calculates the deduction amount: `(base salary ÷ 30) × absent days`
- **One-Click Deduction** — Apply the absence deduction with a single click; the system automatically adds it to salary deductions
- **Prevents Double Deduction** — Already-deducted absences are clearly marked
- **Payroll Reports** — Printed payroll includes a full attendance summary: total days, present, absent, late, half-day, and total working minutes

---

## 📋 Dashboard Widgets

- **Admin Dashboard** — Quick attendance widget showing your current status (not checked in / checked in / completed), office hours, and one-click check-in/out
- **Staff Dashboard** — Monthly attendance summary with attendance rate percentage and progress bar

---

## 🛡️ Security & Access Control

- **Role-Based Access** — Admin sees everything; employees see only their own records
- **Tenant & Branch Isolation** — Each branch manages its own attendance settings and records
- **Activity Logging** — All edits are logged with old/new values for audit
- **CSRF Protection** — All form submissions are protected

---

## 💻 What You Get

| Resource | Count |
|----------|-------|
| Admin Pages | 4 dedicated + 6 integrated (dashboard, salary) |
| API Endpoints | 3 |
| Super Admin Pages | 1 |
| Database Tables | 2 |
| Attendance Statuses | 4 (Present, Late, Half-Day, Absent) |
| Configurable Thresholds | 2 (Late grace, Half-day minimum) |
| Working Day Presets | 4 (Mon-Fri, Mon-Sat, Sat-Thu, Every Day) |
| Export Formats | CSV |
| Languages | 3 (English, Dari, Pashto) |
| Total Code | ~8,200+ lines |

---

*Ready to track attendance? All features are available from the Attendance dashboard.*
