# Tazmin Agreement Template Management

This document explains the implementation of customizable Tazmin agreement templates with multi-language support (Pashto and Dari).

## Overview

Tenants can now:
- Customize their Tazmin agreement template for both Pashto and Dari versions
- Template changes are saved per-tenant and per-language in the database
- Fallback to default templates if no custom version exists
- Choose language when generating the agreement (Pashto or Dari)

## Architecture

### Database Changes

**New Table: `tenant_templates`**
```sql
- id: INT PRIMARY KEY AUTO_INCREMENT
- tenant_id: INT (FK to tenants)
- template_name: VARCHAR(100) - Name of the template (e.g., 'tazmin_agreement')
- language: ENUM('ps', 'dari') - Language code
- template_content: LONGTEXT - HTML content with placeholders
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
- UNIQUE constraint on (tenant_id, template_name, language)
```

### Files Added/Modified

#### New Files:
1. **`migrations/add_tenant_templates.sql`** - Database migration
2. **`includes/TemplateManager.php`** - Core class for template management
3. **`api/umrah/tazmin_default_templates.php`** - Default templates in both languages
4. **`api/tenant_super_admin/manage_templates.php`** - API endpoint for template operations
5. **`tenant_super_admin/manage_templates.php`** - UI for editing templates

#### Modified Files:
1. **`api/umrah/tazmin_agreement_template.php`** - Updated to use TemplateManager
2. **`js/umrah/generations.js`** - Added language selection prompt
3. **`tenant_super_admin/header.php`** - Added menu link

## How It Works

### 1. Template Generation Flow

```
User clicks "Generate Family Tazmin"
    ↓
Language Selection Dialog (Pashto/Dari)
    ↓
Enter Guarantor Name
    ↓
tazmin_agreement_template.php receives:
    - pilgrim_ids
    - guarantor_name
    - language (ps or dari)
    ↓
TemplateManager fetches template from DB
    (Falls back to default if not customized)
    ↓
Placeholders replaced ({{agency_name}}, {{duration}})
    ↓
PDF rendered in new window
```

### 2. Template Customization Flow

```
Admin goes to: Tenant Super Admin → Template Management
    ↓
Selects Language Tab (Pashto/Dari)
    ↓
Edits Template Content
    ↓
Saves to Database via manage_templates API
    ↓
Changes apply immediately to all new generations
```

## TemplateManager Class

Located in: `includes/TemplateManager.php`

### Methods:

```php
__construct($pdo, $tenant_id)
// Initialize the manager

getTemplate($template_name, $language, $default_content)
// Get template, returns default if custom doesn't exist

saveTemplate($template_name, $language, $content)
// Save or update custom template

getTemplatesByName($template_name)
// Get all language versions of a template

deleteTemplate($template_name, $language)
// Delete custom template (falls back to default)
```

## API Endpoint

**URL:** `api/tenant_super_admin/manage_templates.php`

### Actions:

#### GET Template
```
GET /manage_templates.php?action=get&template_name=tazmin_agreement&language=ps
```

Response:
```json
{
  "success": true,
  "template_name": "tazmin_agreement",
  "language": "ps",
  "content": "..."
}
```

#### Save Template
```
POST /manage_templates.php
- action: save
- template_name: tazmin_agreement
- language: ps|dari
- content: template HTML
```

#### Reset to Default
```
POST /manage_templates.php
- action: reset
- language: ps|dari
```

#### List Templates
```
GET /manage_templates.php?action=list
```

## Template Placeholders

Both default and custom templates can use these placeholders:

| Placeholder | Replaced With |
|---|---|
| `{{agency_name}}` | Agency name from settings |
| `{{duration}}` | Pilgrim duration (days) |

Example in template:
```html
<li>د {{agency_name}} شرکت مکلفیت لری...</li>
```

## Language Codes

- `ps` - Pashto (پشتو)
- `dari` - Dari (دری)

## UI Components

### Template Editor (manage_templates.php)
- Two tabs: Pashto and Dari
- Rich textarea for editing
- Placeholder info box
- Save button to store changes
- Reset button to revert to default

### Language Selection Dialog
- Radio buttons for Pashto/Dari
- Appears when clicking "Generate Family Tazmin"
- Must enter guarantor name before generating

## Security

- Template editing restricted to `tenant_super_admin` role
- Per-tenant isolation (templates only apply to own tenant)
- Input sanitization in templates (use htmlspecialchars)
- CSRF protection on API endpoints

## Default Templates

### Pashto Version (`ps`)
Contains 18 clauses covering:
- Company responsibility for visa and accommodation
- Visa pricing
- Return procedures
- Overstay penalties
- Illegal activity consequences
- Flight changes
- Passport handling
- Transportation safety
- Mental fitness requirements
- Payment terms
- Company representation
- Passport return procedures
- Guarantor pledge

### Dari Version (`dari`)
Same 18 clauses translated to Dari language

Both versions include proper placeholders for:
- Agency name ({{agency_name}})
- Duration in days ({{duration}})

## Usage Example

### For Admin (Tenant Super Admin)
1. Log in to tenant_super_admin dashboard
2. Click "Template Management" in sidebar (under Settings & Tools)
3. Select language tab (Pashto or Dari)
4. Edit template text
5. Click "Save Changes"
6. Click "Reset to Default" anytime to revert

### For Staff (General Users)
1. In Umrah Management, select a family
2. Click "Generate Family Tazmin"
3. Choose language (Pashto or Dari)
4. Enter guarantor name
5. Click Generate
6. PDF opens with selected language template

## Database Query Performance

The table has:
- PRIMARY KEY on `id`
- UNIQUE INDEX on `(tenant_id, template_name, language)`
- KEY on `tenant_id`

This ensures fast lookups by tenant and template.

## Fallback Behavior

If a custom template doesn't exist:
1. Check database (returns NULL)
2. Load default from `tazmin_default_templates.php`
3. Display to user

This ensures:
- New tenants don't need manual setup
- Templates always available even if DB fails
- Easy reset to defaults

## Migration

Run this to create the table:
```bash
php run_migration.php
```

Or manually:
```sql
CREATE TABLE tenant_templates (...)
```

## Troubleshooting

### Template not showing custom version
- Check if custom template is saved in DB
- Verify tenant_id in session matches template's tenant_id
- Check language parameter in URL matches ('ps' or 'dari')

### Language selection not appearing
- Ensure `generations.js` is loaded
- Check browser console for JavaScript errors
- Verify SweetAlert2 library is included

### API returning error
- Check user role is 'tenant_super_admin'
- Verify tenant_id in session
- Check database connection

## Future Enhancements

Possible additions:
- Visual template builder (WYSIWYG)
- Template versioning/history
- More languages (Arabic, English)
- Additional templates (guarantor agreement, visa application)
- Template variables for dynamic content
- Template preview before saving
