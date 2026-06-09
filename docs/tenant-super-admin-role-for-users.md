# Tenant Super Admin Role — Guide for Clients

## What is a Tenant Super Admin?

Think of the **Tenant Super Admin** as the **headquarters manager** for your entire travel agency. While each branch has its own admin who manages day-to-day operations, the Tenant Super Admin can see **everything happening in every branch** and manage settings that apply to the whole company.

---

## What Can a Tenant Super Admin Do?

### 1. See All Branches at Once

You get a **dashboard** that shows KPIs for your entire agency:
- How many branches are active
- Total users across all branches
- Total bookings (tickets, hotels, visas, umrah)
- Total revenue and profit (in USD and AFS)
- Revenue trends over time
- How each branch compares to others

You can filter to see data for **one branch** or **all branches combined**.

### 2. Manage Branches

- **Create new branches** — add offices in new locations
- **Edit branch details** — update address, phone, manager
- **Activate or deactivate** branches

> **Note:** You can only create as many branches as your subscription plan allows. If you need more, request a **Branch Add-on**.

### 3. Manage Users Across All Branches

- **Create users** for any branch
- **Assign roles:** admin, finance, sales, umrah, staff, or even another tenant super admin
- **See usage stats** — how many users you've created vs. your plan limit

> **Note:** If you need more users than your plan allows, request a **User Add-on**.

### 4. Manage Your Subscription & Payments

- **View current plan** — name, price, billing cycle
- **Payment history** — all past payments and their status
- **Download invoices**
- **See add-on costs** — how much branch, user, and communication add-ons are costing

### 5. Configure Company-Wide Settings

- **Company branding** — set your agency name, logo, favicon, tax ID, registration number
- **Notification settings** — enable/disable email notifications
- **SMTP configuration** — set up email sending (requires Communication Add-on)
- **Localization** — choose language, timezone, date format, currency

### 6. Configure Your Own Profile

- **Update your name, email, phone, address**
- **Change password** — must enter current password
- **Enable 2FA/TOTP** — two-factor authentication for extra security
- **Set preferences** — language, timezone, date format, currency

### 7. View Everything Across All Branches

You can see (but not edit) records from every branch:

| Module | What You Can See |
|--------|-----------------|
| Tickets | All bookings, reservations, refunds, date changes, ticket weights |
| Hotels | All hotel bookings |
| Visas | All visa applications |
| Umrah | All umrah bookings |
| Accounts | Account balances and transactions |
| Clients | All client records |
| Suppliers | All supplier records |
| Debtors | All debtor records |
| Creditors | All creditor records |
| Expenses | All expense records |
| Salary | Employee salary information |
| Sarafi | Money exchange records |
| Attendance | Staff attendance across all branches |
| Activity Logs | Who did what, when |
| Email Analytics | Email sending stats |

Each view has a **branch filter** so you can narrow down to a specific branch.

### 8. Generate Reports

Create **multi-branch reports** that compare branches side by side:

- **Date range** — pick any start/end dates
- **Branch filter** — one branch or all
- **Service breakdown** — tickets, hotels, visas, umrah, additional payments, refunds, date changes
- **Top clients** — who brings the most revenue
- **Top suppliers** — who costs the most
- **Branch comparison table** — see how each branch is performing
- **Export to PDF or Excel** — download and share reports

You can also **schedule monthly reports** to be automatically generated and emailed to recipients.

### 9. Manage Templates

Create and manage **notification, email, and print templates** that all branches will use. This ensures consistent communication across your entire agency.

### 10. Configure WhatsApp

- **Set up WhatsApp provider** for sending notifications
- **Toggle auto-notifications** on/off
- **View analytics** — messages sent, delivery rates, opt-ins/opt-outs

> **Note:** Requires a **WhatsApp Communication Add-on**.

### 11. Request Add-ons

Need more capacity? Request additional resources right from the system:

| Add-on | What It Gives You | Typical Cost |
|--------|------------------|-------------|
| **Branch Add-on** | Create more branches | ~$50/month |
| **User Add-on** | Create more users | ~$25/month |
| **Communication Add-on** | Enable WhatsApp + SMTP email | ~$30-50/month |

The request goes to the platform super admin for approval.

### 12. Switch to Any Branch

You can **temporarily switch your session** to a specific branch. This lets you use that branch's admin pages as if you were logged in as that branch's admin — useful for helping branch staff or checking settings.

---

## What a Tenant Super Admin CANNOT Do

- ❌ Create another tenant super admin (only platform super admin can)
- ❌ Change platform-level settings
- ❌ Manage other tenants
- ❌ Create/change subscription plans or pricing
- ❌ Approve add-on requests (platform super admin does this)
- ❌ Delete the platform

---

## Security Features

| Feature | Detail |
|---------|--------|
| **Session timeout** | Auto-logout after 30 minutes of inactivity |
| **CSRF protection** | All forms are protected against cross-site request forgery |
| **Password policy** | Minimum 12 characters |
| **Login attempt limits** | 5 wrong attempts = 30-minute lockout |
| **Two-factor auth (2FA)** | Optional extra security layer |
| **Suspension enforcement** | If subscription is overdue, access is restricted to payment page only |

---

## How This Role Fits in Your Company

| Role | Scope | Typical Person |
|------|-------|---------------|
| **Platform Super Admin** | All tenants, global settings | System administrator (MTravels) |
| **Tenant Super Admin** | Your entire agency | Owner / CEO / General Manager |
| **Branch Admin** | One branch | Branch manager |
| **Finance** | One branch | Accountant |
| **Sales** | One branch | Sales agent |
| **Umrah Staff** | One branch | Umrah coordinator |
| **Staff** | One branch | Front desk / support |

---

## Summary

The **Tenant Super Admin** is the **most powerful role within your agency**. It gives you complete visibility and control across all branches, while still protecting platform-level settings that belong to the system administrator. If you're an agency owner or general manager, this is the role you want — it lets you see everything, manage everything, and grow your business without needing the platform admin for every little change.
