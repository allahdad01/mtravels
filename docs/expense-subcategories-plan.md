# Expense Sub-Categories — Implementation Plan

**Client request:** Add sub-categories to the expense management module.
**Date:** 2026-08-05
**Status:** Implemented — Phases 1–4 complete, Phase 5 core items complete (see §8)

---

## 1. Goal

Allow categories in `expense_management.php` to have child (sub) categories. Each expense can optionally be tagged with a sub-category. This enables a two-level hierarchy:

```
Transport  (parent category)
├── Fuel
├── Airfare
└── Local Travel
```

- Parent category card shows aggregated totals of all its children (grand-total).
- Child (sub) categories appear nested/indented under their parent, each with its own expenses, totals, edit/delete/print actions.
- Expenses can be assigned to a parent (top-level) OR a sub-category.

---

## 2. Current Architecture (reference)

| Area | Current state |
|---|---|
| Table `expense_categories` | `id, tenant_id, name, created_at, branch_id` — no parent column |
| Table `expenses` | `category_id` only, FK → `expense_categories(id)` |
| Table `budget_allocations` | `category_id` FK → `expense_categories(id)` (per-category budget) |
| Category CRUD | `api/expense/expense_actions.php` → `save_category`, `delete_category` (delete blocked if expenses or budget allocations exist) |
| Category modal | `modals/expense/category_modal.php` — single name field |
| Expense modal | `modals/expense/expense_modal.php` — flat category dropdown (`#expenseCategory`) |
| Edit expense modal | `modals/expense/edit_expense_modal.php` — category is a **hidden field** (not changeable) |
| Page rendering | `admin/expense_management.php:154-253` — loop over flat `$categories`, one card per category |
| JS handlers | `js/expense/event_handlers.js` — category form submit (133–163), edit/delete (255–292), expense form submit (165–214) |
| Reports (group by category) | `api/expense/get_financial_data.php`, `export_comprehensive_report.php`, `admin/expense_category_report.php`, `export_expenses.php`, `export_financial_data.php`, `api/report/*`, `admin/quarterly_tax_report.php`, budget allocation pages |

---

## 3. Recommended Design

### 3.1 Data model (2 options — pick one)

#### Option A — `parent_id` on categories + `sub_category_id` on expenses (Recommended)

- `expense_categories.parent_id INT NULL` — NULL = top-level category, value = parent category id.
- `expenses.sub_category_id INT NULL` — optional link to a sub-category. When set, the sub-category's parent IS the expense's `category_id`.
- Simple, one-level hierarchy, backward compatible. Expense already has `category_id` (the parent) so existing data keeps working; `sub_category_id` is additive.

Migration SQL (add to a new `database_migration_*.sql` + update `database_structure.sql`):

```sql
ALTER TABLE `expense_categories`
  ADD COLUMN `parent_id` int(11) DEFAULT NULL AFTER `name`,
  ADD KEY `idx_expense_categories_parent` (`parent_id`);

ALTER TABLE `expenses`
  ADD COLUMN `sub_category_id` int(11) DEFAULT NULL AFTER `category_id`,
  ADD KEY `idx_expenses_sub_category` (`sub_category_id`);

-- Guard: sub-category must reference a category in the same tenant/branch
-- (enforced in application logic; FK omitted intentionally because
--  expense_categories is shared across tenant+branch)
```

#### Option B — separate `expense_sub_categories` table

```sql
CREATE TABLE `expense_sub_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- Cleaner separation, but requires more joins everywhere, a new FK on `expenses`, and bigger changes to reports. Only worth it if the client may later want **nested 3+ level hierarchies**.

> **Recommendation: Option A.** One level of sub-category matches the request, keeps all existing `expense_categories` queries working, and minimizes report changes.

### 3.2 Business rules

1. A sub-category must belong to the same `tenant_id` + `branch_id` as its parent.
2. A sub-category **cannot have its own sub-category** (one level only, enforce in API).
3. A parent category **cannot be deleted** while it has children.
4. A sub-category **cannot be deleted** if it has expenses (mirrors existing rule for categories).
5. Moving a sub-category's parent is allowed in edit modal (re-parent).
6. Existing categories remain top-level (NULL `parent_id`) — zero data migration for current records.
7. Deleting/renaming a category does not orphan sub-categories: block delete of parent with children; on rename, children are untouched.

### 3.3 UI behavior

**Category modal (`category_modal.php`)**
- Add new field: "Parent Category" — a select (`#categoryParent`) listing top-level categories only (no parents in list).
- On **edit of a top-level category**: parent select is empty/disabled, with hint "This is a top-level category".
- On **edit of a sub-category**: parent select shows current parent; changing it re-parents.
- Toggle label: `Add Category` → `Add Sub-Category` when a parent is selected (optional nicety).

**Expense modal (`expense_modal.php`)**
- Category dropdown (`#expenseCategory`) shows top-level categories. On selecting a category, a second "Sub-Category" dropdown (`#expenseSubCategory`, optional) populates via AJAX with that category's children; empty value = "No sub-category".
- Sub-category select is disabled/hidden when the chosen category has no children.
- Validation: if a sub-category is chosen, it must belong to the selected parent category (server-side check too).

**Edit expense modal (`edit_expense_modal.php`)**
- Category is currently a hidden field; keep it hidden but **pre-populate and send** `sub_category_id` on edit. (Optional enhancement: make category+sub-category editable here, but that expands scope — ask client.)

**Main page (`expense_management.php`)**
- Build a nested structure in PHP:
  1. Query categories ordered by `parent_id, name` (or fetch twice: parents then children).
  2. Render each top-level category card as today, **then its children cards nested** (indented, slightly different background / left border, `category-card--child` CSS class), each child with its own expense list, totals, and print/edit/delete actions.
  3. Parent card header totals = own expenses + sum of children totals (grand total).
- Print button on a parent card should include children expenses (see §5).

**CSS (`css/expenses/style.css`)**
- Add `.category-card--child` styles: left margin/indent (e.g. `margin-left: 32px`), lighter border, subdued header color.
- Add a small "Sub" badge or parent breadcrumb in child card header (e.g. `Transport › Fuel`).

---

## 4. File-by-file change list

### Database
| File | Change |
|---|---|
| New `database_migration_*.sql` | Migration SQL from §3.1 |
| `database_structure.sql` | Update `expense_categories` + `expenses` table definitions (mirror of migration) |

### Backend API
| File | Change |
|---|---|
| `api/expense/expense_actions.php` — `save_category` | Accept `parentId`; INSERT/UPDATE `parent_id`; validate: parent exists in same tenant+branch, parent is top-level (no grand-parenting), can't set own id as parent, one level max. Log `parent_id` in activity log new_values. |
| `api/expense/expense_actions.php` — `delete_category` | Block if category has children (count `parent_id = ?`); block if it's a sub-category with expenses (existing check already covers via `category_id`). |
| `api/expense/expense_actions.php` — `save_expense` | Accept `expenseSubCategory`; validate it belongs to the selected `expenseCategory` (parent match, same tenant/branch); store `sub_category_id` on INSERT/UPDATE. |
| New endpoint (or extend `expense_actions.php`) | `get_sub_categories`: given `categoryId`, return children JSON for the expense modal dropdown. |

### Frontend
| File | Change |
|---|---|
| `modals/expense/category_modal.php` | Add parent select (`#categoryParent`), populated with top-level categories. |
| `modals/expense/expense_modal.php` | Add optional sub-category select (`#expenseSubCategory`), populated via AJAX on category change. |
| `modals/expense/edit_expense_modal.php` | Add hidden `#editExpenseSubCategory`; populate from expense row on edit click. |
| `admin/expense_management.php` | Nested rendering of category cards (parents + children with grand totals); pass `data-parent` on edit buttons; pass sub-category data on expense edit buttons. |
| `js/expense/event_handlers.js` | Category form submit → send `parentId`; edit-category click → populate parent select + disable for top-level; expense form submit → send `expenseSubCategory`; category change → AJAX-load sub-categories; edit-expense click → prefill sub-category. |
| `css/expenses/style.css` | Child card styles + badges (§3.3). |
| `js/expense/README.md` | Update load-order/notes if new JS file is added (optional). |

### Reports & integrations (group by category)
| File | Change |
|---|---|
| `api/expense/get_financial_data.php` | Chart grouping: include sub-category in labels or roll children up into parent (ask client: chart per sub-category or aggregated). |
| `admin/expense_category_report.php` | Printable HTML report: parent includes children expenses; child prints only its own. (Replaced `api/expense/generate_category_pdf.php`.) |
| `api/expense/export_expenses.php` | Add "Sub-Category" column (`ec2.name` join). |
| `api/expense/export_financial_data.php` | Optionally add sub-category breakdown rows. |
| `api/expense/export_comprehensive_report.php` | P&L "expenses by category" sheet: group children under parents or list both levels (confirm with client). |
| `api/report/fetch_report_data.php`, `export_report.php`, `export_statement.php` | Include sub-category in expense record type / export columns (optional, low priority). |
| `admin/quarterly_tax_report.php` + handler | Category filter: optionally filter by sub-category. |
| `admin/budget_allocations.php`, `api/allocation/*` | Decision needed: should budgets apply to parents only, or per sub-category? (See §6 open questions — default: keep budgets on categories only, no change.) |
| `admin/expense_detail.php`, `api/expense/print_expense.php` | Show sub-category name in the detail view / print. |

### Translations
- Add keys: `sub_category`, `parent_category`, `add_sub_category`, `select_sub_category`, `no_sub_category`, `cannot_delete_category_with_children`, etc. in the language files used by `__()` (locate the translation file referenced by `__()` — likely `includes/language/` or similar).

---

## 5. Implementation order (phased)

### Phase 1 — Data layer (foundation)
1. Write migration SQL (Option A) + update `database_structure.sql`.
2. Run migration on dev DB; verify with existing data (no changes to existing rows).

### Phase 2 — Category CRUD (sub-category creation)
3. `expense_actions.php` `save_category`: add `parentId` handling + validation.
4. `category_modal.php`: add parent select.
5. `event_handlers.js`: send `parentId`, populate + disable parent select on edit.
6. `delete_category`: block parent with children.
7. Test: create/edit/delete top-level + sub-categories; re-parenting; delete guards.

### Phase 3 — Expense assignment
8. `expense_actions.php` `save_expense`: `sub_category_id` + validation.
9. `expense_modal.php`: sub-category dropdown + AJAX loader.
10. `edit_expense_modal.php`: hidden sub-category field, prefilled on edit.
11. `event_handlers.js`: wire dropdowns + submit.
12. Test: add/edit expense with/without sub-category; server-side validation mismatch case.

### Phase 4 — UI rendering
13. `expense_management.php`: nested card rendering, grand totals on parent cards.
14. `style.css`: child card styles.
15. `event_handlers.js`: print/edit buttons carry correct parent/child data.
16. Test: filter (date range) applies to children correctly; totals aggregation.

### Phase 5 — Reports & integrations
17. `admin/expense_category_report.php`: printable HTML page; parent prints children too (replaced `generate_category_pdf.php`).
18. `expense_detail.php` + `print_expense.php`: show sub-category.
19. Exports (`export_expenses.php`, `export_financial_data.php`, `export_comprehensive_report.php`): sub-category column/grouping.
20. `get_financial_data.php` chart grouping decision (parent vs sub-category level).
21. Translations for all new strings.
22. Full regression test across module + reports.

---

## 6. Open questions for the client

1. **Budget allocations** — should budgets be assignable per sub-category, or stay category-level only? (Default: keep category-level, no changes.)
2. **Charts/reports** — show sub-category-level breakdown, or roll everything up into the parent category?
3. **Edit expense** — allow changing category/sub-category on an existing expense? (Currently category is locked in edit modal.)
4. **Hierarchy depth** — is one level enough, or do they want nested sub-sub-categories (would force Option B)?
5. **Filters** — should the date-filter page have a "filter by sub-category" dropdown?
6. **Import** — does the Excel/CSV expense import need a sub-category column?

---

## 8. Implementation status (2026-08-05)

### Done
- **Migration:** `database_migration_expense_subcategories.sql` created; applied to local DB; `database_structure.sql` updated (tables + indexes).
- **API:** `save_category` accepts `parentId` with validation (parent exists, same tenant/branch, no grand-parenting, no self-parent, no re-parent of a category that has children); `delete_category` blocks deletion of a parent with children; `save_expense` accepts `expenseSubCategory` validated against the selected category; new `get_sub_categories` endpoint.
- **Modals:** parent selector in `category_modal.php`; sub-category dropdown in `expense_modal.php` + `edit_expense_modal.php` (category stays locked on edit).
- **JS (`event_handlers.js`):** sends `parentId`; resets form on Add; edit-category pre-fills + disables parent for categories with children; AJAX sub-category loader on category change / modal open / edit-open.
- **Rendering (`expense_management.php`):** nested parent → child cards, child cards indented with badge, parent header shows grand totals (own + children); edit buttons carry `data-parent` / `data-has-children`; expense rows carry `data-sub-category`.
- **CSS (`style.css`):** `.category-card--child` + `.sub-category-badge`.
- **Reports:** `admin/expense_category_report.php` (printable HTML, replaces `generate_category_pdf.php`) — parent prints children, child prints only its own; `expense_detail.php` + `print_expense.php` show sub-category; `export_expenses.php` adds a Sub-Category column.
- **Translations:** en / ps / fa added (sub_category, parent_category, top_level_category, leave_empty_for_top_level, no_sub_category).
- **Tests:** migration applied; page render harness (10/10 checks) and API validation harness (9/9 checks) passed; journal `modal_render.php` path verified; all modified PHP files pass `php -l`; JS passes `node --check`.

### Not done / pending client decision (§6)
- Budget allocations per sub-category (default: category-level only — no change).
- Chart (`get_financial_data.php`) sub-category-level breakdown (default: rolled up under parent — works because sub-category expenses keep the parent `category_id`).
- Editable category on expense edit.
- Sub-category filter on the date-filter panel.
- Excel/CSV expense import sub-category column.
- `export_comprehensive_report.php` / `export_financial_data.php` sub-category breakdown rows (current: sub-category expenses count under their parent).

---

## 7. Risks & notes

- **Not all files in §4 need changes immediately** — Phases 1–4 deliver the core feature; Phase 5 items are polish/reporting. Get client sign-off on §6 before Phase 5.
- `budget_allocations` FK cascades on category delete — the new delete guard (no delete when children exist) prevents orphaning allocations indirectly.
- `expenses` FK `expenses_ibfk_1` is on `category_id`; `sub_category_id` will have **no FK** (by design, tenant/branch scoping), so always validate in `save_expense` server-side.
- `js/expense/expense_actions.js` is an **older duplicate** of event_handlers.js and is not loaded on the page — do not edit it; keep changes in `event_handlers.js`.
- `system_expense_categories` (super-admin) is a **separate** table — out of scope.
- Performance: parent-card queries currently run per category; adding children doubles the query count for categories with children. Acceptable at current scale; can be optimized later with a single grouped query.
