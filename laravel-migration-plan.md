# MTravels — Laravel Migration Plan

## Project Overview

| Attribute | Value |
|---|---|
| Current Stack | Pure PHP 8.3, MySQL, jQuery, Bootstrap |
| Target Stack | Laravel 12+, MySQL, Blade + jQuery (incremental), Livewire (new features) |
| Database Tables | 60+ |
| PHP Files | ~330+ |
| User Panels | 5 (super_admin, tenant_super_admin, admin, client, sales_agent) |
| Tests | 0 |
| Lines in `functions.php` | 2,014 |
| Largest Directory | `admin/` (146 files) |

---

## Strategy: Strangler Fig (Incremental Migration)

A Laravel app is placed alongside the legacy app. A route fallback proxies unknown URLs to legacy PHP files. Each page is migrated one-by-one. The system is fully functional at every step.

```
htdocs/
├── mtravels/              # ← legacy app (unchanged during migration)
│   ├── admin/
│   ├── super_admin/
│   ├── tenant_super_admin/
│   ├── includes/
│   ├── api/
│   └── ...
│
└── mtravels-laravel/      # ← new Laravel app
    ├── app/
    ├── bootstrap/
    ├── config/
    ├── database/
    ├── public/
    │   └── index.php      # ← entry point, proxies legacy routes
    ├── resources/
    └── routes/
```

---

## Phase 0: Foundation (Weeks 1-2)

### 0.1 — Laravel Installation

```bash
composer create-project laravel/laravel ../mtravels-laravel
cd ../mtravels-laravel
```

### 0.2 — Environment Setup

Copy `.env` from legacy project, add:

```
APP_URL=http://localhost/mtravels-laravel/public
LEGACY_PATH=../mtravels
```

### 0.3 — Database Migrations

Generate one migration per existing table using `Schema::hasTable()` checks:

```php
// database/migrations/2026_01_01_000001_create_tenants_table.php
public function up()
{
    if (!Schema::hasTable('tenants')) {
        Schema::create('tenants', function (Blueprint $table) {
            // ... same schema as legacy
        });
    }
}
```

**All 60+ migrations** should be created. Run them — they'll be no-ops on the existing DB but provide a clean schema for tests.

### 0.4 — Eloquent Models

Generate one model per table:

```bash
php artisan make:model Tenant
php artisan make:model User
php artisan make:model Branch
php artisan make:model TicketBooking
# ... etc
```

Define **relationships, scopes, and mutators** on each model. The multi-tenancy scope is critical:

```php
// app/Models/Scopes/TenantScope.php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (session()->has('tenant_id')) {
            $builder->where('tenant_id', session('tenant_id'));
        }
    }
}
```

### 0.5 — Legacy Route Proxy

The **critical piece** that makes incremental migration possible:

```php
// routes/web.php

use Illuminate\Support\Facades\Route;

// ── Health check ──
Route::get('/laravel-status', fn() => 'Laravel is running');

// ── Migrated routes will go here ──
// Route::middleware(['web', 'auth', 'tenant'])->group(function () {
//     Route::resource('tenants', App\Http\Controllers\TenantController::class);
// });

// ── Legacy catch-all ──
Route::any('/{any?}', function ($path = '') {
    $legacyRoot = base_path(config('app.legacy_path', '../mtravels'));
    $safePath = ltrim($path, '/');
    
    // If the file doesn't exist, try index.php (SPA-like fallback for legacy)
    $target = $safePath ? realpath($legacyRoot . '/' . $safePath) : $legacyRoot . '/index.php';
    $target = $target ?: $legacyRoot . '/index.php';
    
    if (!file_exists($target) || is_dir($target)) {
        abort(404, "Page not found: /{$safePath}");
    }
    
    // Make PDO instance available to legacy code
    if (!isset($GLOBALS['pdo'])) {
        $GLOBALS['pdo'] = DB::connection()->getPdo();
    }
    
    // Capture output from legacy script
    ob_start();
    chdir(dirname($target));
    $_SERVER['SCRIPT_NAME'] = '/' . $safePath;
    require $target;
    return ob_get_clean();
})->where('any', '.*');
```

**Nginx/Apache config** must point document root to `mtravels-laravel/public/`.

### 0.6 — Shared Services

Extract the most-used functionality into Laravel services so both old and new code can use them:

| Legacy File | Laravel Service |
|---|---|
| `includes/functions.php` (sendEmail) | `app/Services/EmailService.php` |
| `includes/InputValidator.php` | Already a class — moves to `app/Helpers/` |
| `includes/CsrfProtection.php` | Replace with Laravel's built-in CSRF |
| `includes/RateLimiter.php` | Replace with Laravel's built-in rate limiter |
| `includes/PasswordValidator.php` | `app/Services/PasswordValidationService.php` |
| `includes/Language.php` | `app/Services/LocalizationService.php` or Laravel's lang |

---

## Phase 1: Authentication (Weeks 2-4)

### 1.1 — Custom User Provider

Laravel's default auth expects certain columns. The `users` table has your custom schema. Create a custom provider:

```php
// app/Providers/AuthServiceProvider.php
public function boot()
{
    Auth::provider('mtravels', function ($app, array $config) {
        return new MTravelsUserProvider($app['hash'], $config['model']);
    });
}
```

```php
// app/Extensions/MTravelsUserProvider.php
class MTravelsUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials)
    {
        // Query by email AND tenant_id
        $user = User::query();
        if (isset($credentials['tenant_id'])) {
            $user->where('tenant_id', $credentials['tenant_id']);
        }
        return $user->where('email', $credentials['email'])->first();
    }
}
```

### 1.2 — Session Middleware

Replicate the custom session logic:

```php
// app/Http/Middleware/SessionSecurity.php
class SessionSecurity
{
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            // 30-minute inactivity timeout
            $lastActivity = session('last_activity', time());
            if (time() - $lastActivity > 1800) {
                Auth::logout();
                return redirect('/login')->with('timeout', 1);
            }
            session(['last_activity' => time()]);

            // IP address verification
            if (session('ip_address') && session('ip_address') !== $request->ip()) {
                Auth::logout();
                return redirect('/login');
            }

            // User-Agent verification
            if (session('user_agent') && session('user_agent') !== $request->userAgent()) {
                Auth::logout();
                return redirect('/login');
            }

            // Periodic session regeneration (every 5 min)
            $lastRegen = session('last_regeneration', 0);
            if (time() - $lastRegen > 300) {
                $request->session()->regenerate();
                session(['last_regeneration' => time()]);
            }
        }
        return $next($request);
    }
}
```

### 1.3 — Role Authorization Middleware

```php
// app/Http/Middleware/CheckRole.php
class CheckRole
{
    public function handle($request, Closure $next, ...$roles)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, $roles)) {
            abort(403, 'Unauthorized');
        }
        return $next($request);
    }
}
```

**Route usage:**
```php
Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->group(function () {
    // Super admin routes
});
Route::middleware(['auth', 'role:admin,tenant_super_admin,finance,sales,umrah,staff'])->prefix('admin')->group(function () {
    // Admin panel routes
});
Route::middleware(['auth', 'role:client'])->prefix('client')->group(function () {
    // Client portal routes
});
```

### 1.4 — TOTP Two-Factor

The legacy app uses `spomky-labs/otphp` (already in composer). Create:

```php
// app/Http/Middleware/TotpVerification.php
class TotpVerification
{
    public function handle($request, Closure $next)
    {
        if (Auth::check() && Auth::user()->totp_enabled) {
            if (!session('totp_verified')) {
                return redirect('/totp/verify');
            }
        }
        return $next($request);
    }
}
```

### 1.5 — Login Controller

```php
// app/Http/Controllers/Auth/LoginController.php
class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        // Check brute force (RateLimiter)
        // Validate credentials with tenant_id from email domain or separate field
        // If TOTP enabled, redirect to TOTP form
        // Store ip_address, user_agent in session
        // Log login_history
        // Regenerate session ID
        // Redirect to appropriate dashboard based on role
    }
}
```

### 1.6 — Blade Login View (Optional)

The legacy `login.php` has a beautiful design. Either:
- Keep the legacy login page (let the proxy serve it), OR
- Recreate it as a Blade view with the same HTML/CSS

**Recommendation:** Keep legacy login until Phase 1 is complete, then convert to Blade.

---

## Phase 2: Super Admin Panel (Weeks 4-6)

### 2.1 — Controllers (8 resource controllers)

| Legacy Files | Laravel Controller | Notes |
|---|---|---|
| `manage_tenants.php`, `create_tenant.php`, `edit_tenant.php`, `delete_tenant.php` | `TenantController` | Add duplicate email validation (already done) |
| `manage_plans.php`, `create_plan.php`, `edit_plan.php`, `delete_plan.php` | `PlanController` | Simple CRUD |
| `manage_subscriptions.php`, `create_subscription.php` | `SubscriptionController` | Has payment integration |
| `manage_users.php`, `create_user.php`, `edit_user.php`, `delete_user.php` | `AdminUserController` | Super admin users |
| `manage_sales_agents.php`, `create_sales_agent.php` | `SalesAgentController` | Sales agent CRUD |
| `manage_blog_posts.php` | `BlogPostController` | Simple CMS |
| `manage_testimonials.php` | `TestimonialController` | Simple CMS |
| `manage_demo_requests.php` | `DemoRequestController` | Read-only + status update |
| `manage_tutorials.php` | `TutorialController` | CRUD |
| `platform_settings.php` | `SettingsController` | Key-value settings |

### 2.2 — Form Requests (validation)

Create a Form Request for each create/update action:

```php
// app/Http/Requests/StoreTenantRequest.php
class StoreTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'billing_email' => ['required', 'email', 'unique:tenants,billing_email'],
            'plan' => ['required', 'exists:plans,name'],
            'agency_name' => ['required', 'string', 'max:255'],
            'trial_days' => ['integer', 'min:1', 'max:365'],
        ];
    }
}
```

### 2.3 — Blade Views

One directory per controller:

```
resources/views/
├── super-admin/
│   ├── tenants/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── edit.blade.php
│   │   └── show.blade.php
│   ├── plans/
│   ├── subscriptions/
│   └── ...
├── admin/
├── tenant-admin/
├── client/
└── layouts/
    ├── super-admin.blade.php
    ├── admin.blade.php
    └── client.blade.php
```

### 2.4 — Layouts

Create a layout per panel type:

```
resources/views/layouts/
├── super-admin.blade.php    # includes super_admin sidebar
├── admin.blade.php          # includes admin sidebar (from nav_items.php)
├── tenant-admin.blade.php   # includes tenant_super_admin sidebar
└── client.blade.php         # minimal layout for client portal
```

The sidebar navigation logic from `includes/nav_items.php` (559 lines) becomes a Blade component:

```php
// app/View/Components/AdminSidebar.php
class AdminSidebar extends Component
{
    public function __construct(
        public User $user,
        public array $allowedFeatures
    ) {}

    public function render()
    {
        return view('components.admin-sidebar');
    }
}
```

---

## Phase 3: Tenant Super Admin Panel (Weeks 6-8)

### 3.1 — Controllers (6 resource controllers)

| Legacy Files | Laravel Controller | Notes |
|---|---|---|
| `branches.php` | `BranchController` | Simple CRUD, manager assignment |
| `users.php` | `TenantUserController` | User CRUD per tenant, role assignment |
| `settings.php`, `tenant_settings.php` | `TenantSettingsController` | Per-tenant settings |
| `clients.php` | `ClientController` | Per-tenant client management |
| `suppliers.php` | `SupplierController` | Per-tenant supplier management |
| `dashboard.php` | `DashboardController` | Aggregated stats |
| `profile.php`, `update_profile.php` | `ProfileController` | User profile |

### 3.2 — Tenant Scoping

All tenant-level controllers automatically scope queries:

```php
// app/Http/Controllers/TenantAdmin/BranchController.php
class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::where('tenant_id', tenant_id())->get();
        return view('tenant-admin.branches.index', compact('branches'));
    }
}
```

Helper function:
```php
// app/helpers.php
function tenant_id(): ?int
{
    return session('tenant_id');
}
```

---

## Phase 4: Admin Panel — Core Travel (Weeks 8-16)

This is the largest phase. The `admin/` directory has 146 files. Migrate by business domain.

### 4.1 — Priority 1: Ticket Management (Weeks 8-10)

**Legacy files involved:**
- `admin/ticket.php` (1,021 lines)
- `admin/ticket_detail.php`
- `admin/view_tickets.php`
- `admin/refund_ticket.php`
- `admin/date_change.php`
- `admin/ticket_reserve.php`
- `admin/ticket_weights.php`
- `api/ticket/ticket_handler.php` (204 lines)
- `js/ticket/` (feature-specific JS)
- `modals/ticket/` (modal components)
- `css/ticket/` (feature-specific CSS)

**Laravel structure:**
```
app/Http/Controllers/Admin/
├── TicketController.php        # CRUD
├── TicketRefundController.php  # Refunds
├── TicketDateChangeController.php # Date changes
├── TicketReservationController.php  # Reservations
├── TicketWeightController.php  # Weight management

app/Http/Requests/
├── StoreTicketRequest.php
├── UpdateTicketRequest.php
├── RefundTicketRequest.php

app/Models/
├── TicketBooking.php       (with relationships)
├── TicketRefund.php
├── DateChangeTicket.php
├── TicketWeight.php
├── TicketReservation.php

app/Services/
├── TicketService.php       # Business logic for ticket operations
├── ProfitCalculationService.php  # Multi-currency profit calculations

resources/views/admin/tickets/
├── index.blade.php
├── create.blade.php
├── edit.blade.php
├── show.blade.php
├── refund.blade.php
├── date-change.blade.php
└── partials/
    ├── search-form.blade.php
    ├── table.blade.php
    └── modals.blade.php
```

**Key complexity:**
- Multi-currency (USD/AFS) profit calculations
- Supplier/client/payment account relationships
- PNR search across airlines
- Status workflow: Booked → Date Changed → Refunded
- Invoice generation
- Notification creation on each transaction

### 4.2 — Priority 1: Client Management (Weeks 10-11)

**Legacy files:** `admin/client.php`, `admin/client_detail.php`, `api/client/`

**Laravel:**
- `ClientController` — CRUD + transactions view
- `ClientTransactionController` — Credit/debit transactions
- `ClientWalletController` — Multi-currency wallets
- Blade views for client listing, detail, transactions

### 4.3 — Priority 1: Supplier Management (Week 11-12)

**Legacy files:** `admin/supplier.php`, `admin/supplier_detail.php`, `api/supplier/`

**Laravel:**
- `SupplierController` — CRUD
- `SupplierTransactionController` — Fund management
- Supplier balance tracking

### 4.4 — Priority 2: Umrah, Hotel, Visa (Weeks 12-14)

Each follows the same pattern as Tickets but with simpler workflows:

| Domain | Controller | Models | Key Relationships |
|---|---|---|---|
| **Umrah** | `UmrahBookingController` | `UmrahBooking`, `Family`, `UmrahTransaction` | Families, suppliers, clients, accounts |
| **Hotel** | `HotelBookingController` | `HotelBooking`, `HotelRefund` | Suppliers, clients, accounts |
| **Visa** | `VisaApplicationController` | `VisaApplication` | Suppliers, clients, documents |

### 4.5 — Priority 2: Finance & Accounting (Weeks 14-15)

| Domain | Controller | Key Legacy Files |
|---|---|---|
| **Accounts** | `MainAccountController` | `admin/accounts.php` |
| **Account Transactions** | `AccountTransactionController` | Embedded in accounts |
| **Expenses** | `ExpenseController` | `admin/expense_management.php`, `api/expense/` |
| **Expense Categories** | `ExpenseCategoryController` | `admin/expense_management.php` |
| **Budget** | `BudgetController` | `admin/budget_allocations.php` |
| **Additional Payments** | `AdditionalPaymentController` | `admin/additional_payments.php` |

### 4.6 — Priority 2: Sarafi (Money Exchange) (Week 15)

**Legacy files:** `admin/sarafi.php`, `api/finance/`, `js/sarafi/`

This is a **domain-specific module** with:
- Deposits, withdrawals, hawala transfers
- Exchange rate management
- General ledger integration
- Commission tracking

**Laravel:**
- `SarafiController`
- `ExchangeTransactionController`
- `HawalaTransferController`
- `GeneralLedgerController`
- `SarafiService` — core business logic

### 4.7 — Priority 3: HR, Attendance, Salary (Weeks 15-16)

| Domain | Controller | Key Legacy Files |
|---|---|---|
| **Employees** | `EmployeeController` | `admin/employee_management.php`, `api/employee/` |
| **Attendance** | `AttendanceController` | `admin/attendance.php`, `admin/manage_attendance.php`, `api/attendance/` |
| **Salary** | `SalaryController` | `admin/salary_management.php`, `admin/salary_payments.php` |
| **Payroll** | `PayrollController` | `admin/salary_management.php` |
| **Performance** | `PerformanceController` | `admin/employee_performance.php` |
| **Bonuses/Deductions** | `BonusController`, `DeductionController` | `admin/manage_bonuses.php`, `admin/manage_deductions.php` |
| **Terminations** | `TerminationController` | `admin/fire_user.php` |

### 4.8 — Priority 3: Creditors, Debtors, JV Payments (Week 16)

| Domain | Controller |
|---|---|
| **Creditors** | `CreditorController`, `CreditorTransactionController` |
| **Debtors** | `DebtorController`, `DebtorTransactionController` |
| **JV Payments** | `JvPaymentController`, `JvTransactionController` |

### 4.9 — Priority 3: Assets, Maktobs, Misc (Week 16-17)

| Domain | Controller |
|---|---|
| **Assets** | `AssetController` |
| **Maktobs (Letters)** | `MaktobController` |
| **Floating Tasks** | `FloatingTaskController` |
| **Search** | `SearchController` |
| **Compliance Report** | `ComplianceReportController` |

---

## Phase 5: Client Portal (Week 17-18)

### 5.1 — Controllers

| Legacy File | Laravel Controller |
|---|---|
| `client/dashboard.php` | `Client\DashboardController` |
| `client/ticket.php` | `Client\TicketController` (read-only) |
| `client/ticket_detail.php` | `Client\TicketController@show` |
| `client/umrah.php` | `Client\UmrahController` |
| `client/hotel.php` | `Client\HotelController` |
| `client/visa.php` | `Client\VisaController` |
| `client/date_change.php` | `Client\DateChangeController` |
| `client/refund_ticket.php` | `Client\RefundController` |
| `client/profile.php` | `Client\ProfileController` |
| `client/report.php` | `Client\ReportController` |
| `client/security.php` | `Client\SecurityController` (TOTP setup) |

### 5.2 — Client Auth

The `clients` table has its own auth (separate from `users`). Create a dedicated guard:

```php
// config/auth.php
'guards' => [
    'client' => [
        'driver' => 'session',
        'provider' => 'clients',
    ],
],
'providers' => [
    'clients' => [
        'driver' => 'eloquent',
        'model' => App\Models\Client::class,
    ],
],
```

---

## Phase 6: Sales Agent Panel (Week 18)

### 6.1 — Controllers

| Legacy File | Laravel Controller |
|---|---|
| `sales_agent/dashboard.php` | `SalesAgent\DashboardController` |
| `sales_agent/tenants.php` | `SalesAgent\TenantController` |
| `sales_agent/create_tenant_subscription.php` | `SalesAgent\TenantSubscriptionController` |
| `sales_agent/commissions.php` | `SalesAgent\CommissionController` |
| `sales_agent/payments.php` | `SalesAgent\PaymentController` |
| `sales_agent/salary_payments.php` | `SalesAgent\SalaryController` |
| `sales_agent/statements.php` | `SalesAgent\StatementController` |
| `sales_agent/profile.php` | `SalesAgent\ProfileController` |

---

## Phase 7: API Consolidation (Weeks 18-19)

### 7.1 — Existing API Structure

The `api/` directory has 52+ endpoints scattered across subdirectories. In Laravel:

**Before (legacy):**
```
api/ticket/ticket_handler.php        # ?action=list|create|update|delete
api/client/client_handler.php
api/umrah/umrah_handler.php
api/hotel/hotel_handler.php
```

**After (Laravel):**
```php
// routes/api.php
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::apiResource('tickets', Api\TicketController::class);
    Route::apiResource('clients', Api\ClientController::class);
    Route::apiResource('umrah', Api\UmrahBookingController::class);
    Route::apiResource('hotels', Api\HotelBookingController::class);
    // ...
});
```

### 7.2 — API Resources

```php
// app/Http/Resources/TicketResource.php
class TicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'pnr' => $this->pnr,
            'passenger_name' => $this->passenger_name,
            'airline' => $this->airline,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'departure_date' => $this->departure_date,
            'status' => $this->status,
            'currency' => $this->currency,
            'sold' => $this->sold,
            'profit' => $this->profit,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'created_at' => $this->created_at,
        ];
    }
}
```

### 7.3 — Rate Limiting

Replace custom `RateLimiter.php` (989 lines) with Laravel's built-in:

```php
// routes/api.php
Route::middleware(['throttle:100,1'])->group(function () {
    // API routes
});
```

---

## Phase 8: Chat & Real-time Features (Weeks 19-20)

### 8.1 — Chat Migration

**Legacy:** `api/messages.php`, `api/chat_prefs.php`, `api/group_chats.php`, `api/group_messages.php`, `api/typing.php`, `api/voice_messages.php`, `api/message_reactions.php`

**Laravel:**
- Use Laravel Echo + WebSockets (Laravel Reverb or Pusher)
- `ChatMessage` model with encryption
- `ChatRoom` model for group chats
- `MessageReaction` model
- `ChatAuditLog` model

### 8.2 — Encryption

The legacy app has custom encryption key management (`encryption_keys` table, key rotation). In Laravel:
- Use Laravel's built-in encryption for message content
- Keep the `encryption_keys` table for audit/compliance
- Create `EncryptionService` for backward compatibility

### 8.3 — WhatsApp

- `TenantSuperAdmin\WhatsAppSettingsController`
- `WhatsAppAnalyticsController`
- Queue WhatsApp messages via Laravel Horizon/jobs

---

## Phase 9: Reporting & Exports (Weeks 20-21)

### 9.1 — Reports

**Legacy:** `admin/report.php`, `admin/generate_report.php`, `admin/hr_reports.php`, `admin/download_report.php`, `admin/compliance_report.php`, `admin/quarterly_tax_report.php`

**Laravel:**
- `ReportController` with report types enum
- Use existing packages (PhpSpreadsheet, DomPDF, mpdf) already in composer
- Queue heavy report generation with Laravel Jobs
- Report export as Excel, PDF, CSV

### 9.2 — Dashboard Widgets

The dashboard queries (838 lines in `tenant_super_admin/dashboard.php`, 1,135 lines in `admin/dashboard.php`, 1,622 lines in `super_admin/dashboard.php`) are expensive. In Laravel:
- Cache dashboard results with Redis (Predis already in composer)
- Create `DashboardService` with cached queries
- Use Laravel's scheduled commands for periodic refresh

---

## Phase 10: Functions.php Decomposition (Week 21)

The 2,014-line `functions.php` is a god file. Every function becomes a service:

| Function(s) | Service | Status |
|---|---|---|
| `sendEmail()`, `recordEmailTracking()`, `getTenantSMTPSettings()`, `getPlatformSettingsFormatted()` | `EmailService` | Already partly extracted |
| `sendWelcomeEmail()`, `sendCommissionNotification()`, `sendNewTenantNotificationToAdmin()` | `NotificationService` | Email templates |
| `getBaseUrl()`, `getPlatformSettings()` | `SettingsService` | Cached |
| `sendTicketBookingConfirmation()` | `TicketNotificationService` | Already exists as class |
| `getSetting()`, `formatCurrency()`, `truncateText()` | `HelperService` or global helpers |

---

## Phase 11: Testing (Weeks 21-24, ongoing)

### 11.1 — Test Structure

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── EmailServiceTest.php
│   │   ├── TicketServiceTest.php
│   │   └── ProfitCalculationServiceTest.php
│   ├── Models/
│   │   ├── TenantTest.php
│   │   ├── TicketBookingTest.php
│   │   └── UserTest.php
│   └── Helpers/
├── Feature/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── TenantControllerTest.php
│   │   │   ├── TicketControllerTest.php
│   │   │   └── Auth/
│   │   │       └── LoginControllerTest.php
│   │   └── Middleware/
│   │       ├── SessionSecurityTest.php
│   │       └── CheckRoleTest.php
├── Browser/           # Laravel Dusk for critical UI flows
└── Legacy/            # Characterization tests against legacy behavior
    ├── TicketCreationTest.php
    └── MultiCurrencyTest.php
```

### 11.2 — Characterization Tests

Before migrating each module, write tests that capture current behavior:

```php
// tests/Legacy/TicketCreationTest.php
class TicketCreationTest extends TestCase
{
    /** @test */
    public function legacy_ticket_creation_returns_success()
    {
        // Send POST to legacy admin/ticket.php
        // Assert response contains "Ticket created successfully"
        // Assert DB has new record
        // This locks in current behavior before rewrite
    }
}
```

### 11.3 — CI Pipeline

```yaml
# .github/workflows/tests.yml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install
      - run: cp .env.testing .env
      - run: php artisan migrate --env=testing
      - run: php artisan test
```

---

## Phase 12: Deployment & Cutover (Weeks 24-26)

### 12.1 — Deployment Strategy

```
1. Legacy app at mtravels.example.com
2. Laravel app at laravel.mtravels.example.com (internal)
3. When a module is migrated, update Nginx to route those URLs to Laravel
4. When all modules are migrated, swap the DNS
```

### 12.2 — Nginx Configuration

```nginx
server {
    listen 80;
    server_name app.mtravels.com;
    root /var/www/mtravels-laravel/public;

    # Migrated routes -> Laravel
    location ~ ^/(super-admin/tenants|super-admin/plans|admin/tickets) {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # All other routes -> Legacy
    location / {
        try_files $uri $uri/ /legacy.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        include fastcgi_params;
    }
}
```

### 12.3 — Zero-Downtime Cutover Checklist

- [ ] All 60+ models created
- [ ] All migrations run
- [ ] Auth fully migrated
- [ ] All controllers migrated
- [ ] All routes defined
- [ ] Feature tests pass for every module
- [ ] Legacy proxy still works for unmigrated pages
- [ ] Load testing complete
- [ ] Rollback plan documented

---

## Appendix A: Complete File Inventory

### A.1 — Files to Migrate (by panel)

| Panel | Directory | Files | Controllers Needed | Priority |
|---|---|---|---|---|
| Super Admin | `super_admin/` | 86 | ~10 | 2 |
| Tenant Super Admin | `tenant_super_admin/` | 56 | ~8 | 3 |
| Admin | `admin/` | 146 | ~25 | 1 |
| Client Portal | `client/` | 27 | ~8 | 5 |
| Sales Agent | `sales_agent/` | 9 | ~6 | 6 |

### A.2 — Infrastructure Files

| File | Purpose | Migration Strategy |
|---|---|---|
| `includes/functions.php` (2,014 lines) | God function file | Break into services |
| `includes/header.php` (1,900 lines) | Auth + HTML + CSS + JS | Layout component |
| `includes/auth_check.php` | Auth bootstrap | Middleware |
| `includes/session_check.php` | Session validation | Middleware |
| `includes/nav_items.php` | Sidebar nav | Blade component |
| `includes/db.php` | DB connection | Laravel DB |
| `includes/helpers.php` | Utility functions | Laravel helpers |
| `config.php` | Config | Laravel config |
| `php_login.php` | Login logic | Auth controller |
| `includes/env_loader.php` | Env loader | Laravel built-in |

---

## Appendix B: Key Architectural Decisions

| Decision | Option A | Option B | Recommendation |
|---|---|---|---|
| Multi-tenancy | `stancl/tenancy` package | Manual `tenant_id` scoping | **Manual** — less overhead, matches current pattern |
| Frontend | Blade + Livewire | Blade + Vue/React | **Blade + existing jQuery first**, Livewire for new features |
| Auth guards | Single guard with role | Multiple guards (user, client) | **Multiple guards** — cleaner separation |
| API auth | Sanctum | Passport | **Sanctum** — simpler for SPA + API |
| Queue | Laravel Horizon | Database queue | **Database first**, Horizon later |
| Cache | Redis (Predis already installed) | File cache | **Redis** — already a dependency |
| Testing | PHPUnit | Pest | **PHPUnit** — more familiar, no new syntax to learn |

---

## Appendix C: Timeline Summary

```
Month 1-2:     Phase 0 (Foundation) + Phase 1 (Auth)
Month 2-3:     Phase 2 (Super Admin)
Month 3-4:     Phase 3 (Tenant Admin)
Month 4-7:     Phase 4 (Admin Panel — Tickets, Clients, Umrah, Hotel, Visa)
Month 7-8:     Phase 4 (Admin — Finance, Sarafi, HR, Salary)
Month 8-9:     Phase 5 (Client Portal) + Phase 6 (Sales Agent)
Month 9-10:    Phase 7 (API) + Phase 8 (Chat)
Month 10-11:   Phase 9 (Reports) + Phase 10 (Functions.php)
Month 11-12:   Phase 11 (Testing) + Phase 12 (Deployment)

Total: ~12 months for 1 senior dev
       ~7-8 months for 2 devs
```

---

## Appendix D: Risk Register

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| **No tests** — regressions undetected | High | Critical | Write characterization tests before each migration |
| **God functions.php** — hidden side effects | High | Critical | Decompose into services BEFORE migrating dependents |
| **Mixed DB drivers** (mysqli + PDO) | Medium | High | All new code uses Eloquent; legacy fallback still works |
| **Custom auth quirks** — TOTP, IP/UA check | Medium | High | Build as middleware; test against legacy behavior |
| **Team unfamiliar with Laravel** | Medium | Medium | Start with simple module (Branches) as training |
| **Client portal auth** — separate `clients` table | Medium | Medium | Custom guard for client auth |
| **Inline CSS/JS in PHP files** | High | Low | Accept during migration; refactor after cutover |
| **Legacy proxy performance** | Low | Medium | Add cache headers; monitor response times |
| **Scope creep** — wanting to redesign UI | High | Medium | Strictly match legacy UI first; redesign is post-migration |