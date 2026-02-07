# Affogato

Composer Plugin for Laravel RDBMS Development

## 🔧 Laravel 12 Installation

1. Create a new Laravel app:

   ```bash
   laravel new affogato
   ```

2. Run the custom API install script:

   ```bash
   php artisan install:api
   ```

3. Add `use HasApiTokens` in App/Models/User.php

4. Link storage:

   ```bash
   php artisan storage:link
   ```

---

## 📦 Steps to Install Affogato

1. **Install Affogato via Composer**

   ```bash
   composer require zchted/affogato:dev-main
   ```

2. **Register the Service Provider**

   In `config/app.php` or your custom provider loader (like `bootstrap/providers.php`):

   ```php
   Zchted\Affogato\AffogatoServiceProvider::class,
   ```

3. **Extend AffogatoController in Your Controller**

   ```php
   use Zchted\Affogato\AffogatoController;

   class MyController extends AffogatoController
   {
       //
   }
   ```

4. **Run Seeder in `DatabaseSeeder.php`**

   ```php
   use Zchted\Affogato\Command;

   public function run()
   {
       Command::runSeeder($this->command);
   }
   ```

5. **Register Middlewares and Exception Handler to `bootstrap/app.php`**

   This is needed to force JSON Response on the APIs.

   ```php
   <?php

    use Illuminate\Foundation\Application;
    use Illuminate\Foundation\Configuration\Exceptions;
    use Illuminate\Foundation\Configuration\Middleware;

    return Application::configure(basePath: dirname(__DIR__))
        ->withRouting(
            web: __DIR__.'/../routes/web.php',
            api: __DIR__.'/../routes/api.php',
            commands: __DIR__.'/../routes/console.php',
            health: '/up',
        )
        ->withMiddleware(function (Middleware $middleware) {
            /* Register Middlewares */
            $middleware->append(\Zchted\Affogato\ExpireSanctumTokens::class);
            $middleware->append(\Zchted\Affogato\ForceJsonResponse::class);
        })
        ->withExceptions(function (Exceptions $exceptions): void {
            /* Register Exception Handler */
            handleExceptions($exceptions);
        })->create();
   ```

6. **Copy `mods/api.php` to `routes/api.php`**

   Merge or replace your `routes/api.php` file with the one from Affogato.

7. **(Optional) Adopt the basic routes in `api.php`**

   ```php
   <?php

   use Zchted\Affogato\LoginController;
   use Zchted\Affogato\ConfiguratorController;
   use Zchted\Affogato\ExpireSanctumTokens;
   use Zchted\Affogato\ForceJsonResponse;
   use Illuminate\Http\Request;
   use Illuminate\Support\Facades\Route;

   // Public routes
   Route::post('/login', [LoginController::class, 'login']);

   // Protected routes
   Route::middleware(['auth:sanctum', ExpireSanctumTokens::class, ForceJsonResponse::class])->group(function () {
      // Get current user
      Route::get('/user', function (Request $request) {
         return $request->user();
      });

      if (file_exists(__DIR__ . '/mods.php')) {
         require __DIR__ . '/mods.php';
      }

      // Logout
      Route::post('/logout', [LoginController::class, 'logout']);

      Route::post('config/{config}', [ConfiguratorController::class, 'getConfig']);
   });

   ```

---

## 🛠️ Artisan Commands

Affogato provides several artisan commands to help you create and manage your database configurations.

### Create a New Config

```bash
php artisan add:config {config_name} {columns} {type=default}
```

**Example:**
```bash
php artisan add:config product "name,price,description"
```

This creates a new `product.json` configuration file in the `core/` directory with the specified columns.

The optional `type` parameter sets the primary key type (default is `int`).

### Add Columns to Existing Config

```bash
php artisan add:column {config_name} {columns}
```

**Example:**
```bash
php artisan add:column product "e_contact_email,n_stock_quantity"
```

### Delete Columns from Config

```bash
php artisan delete:column {config_name} {columns}
```

**Example:**
```bash
php artisan delete:column product "old_field"
```

### Generate Models, Migrations & Factories

```bash
php artisan configurator
```

This command reads all config files in `core/` and generates:
- Eloquent Models
- Database Migrations
- Model Factories
- API Controllers

After generation, it automatically runs the Vue builder interpreter:
```bash
node vue/utils/builder/interpreter.js --all
```

### Rollback Config Changes

```bash
php artisan rollback:config {config_name}
```

Restores the config to its most recent backup from `core/backups/`.

---

## 📋 Column Type Prefixes

When creating columns, you can use prefixes to automatically set the backend type and form control.

### Basic Types

| Prefix | Type | Backend Type | Form Control | Example |
|--------|------|--------------|--------------|---------|
| `i_` | Integer | int | text | `i_quantity` → `quantity` |
| `s_` | String | string | text | `s_title` → `title` |
| `b_` | Boolean | boolean | text | `b_is_active` → `is_active` |

### Date & Time Controls

| Prefix | Type | Backend Type | Form Control | Example |
|--------|------|--------------|--------------|---------|
| `d_` | Date | date | date | `d_birth_date` → `birth_date` |
| `t_` | Time | time | time | `t_start_time` → `start_time` |
| `dt_` | DateTime | datetime | datetime | `dt_created` → `created` |
| `dr_` | Date Range | string | daterange | `dr_period` → `period` |

### File Controls

| Prefix | Type | Backend Type | Form Control | Multiple |
|--------|------|--------------|--------------|----------|
| `f_` | File | string | file | No |
| `fm_` | File Multiple | string | file | Yes |

### Text Input Controls

| Prefix | Type | Backend Type | Form Control | Example |
|--------|------|--------------|--------------|---------|
| `e_` | Email | string | email | `e_user_email` → `user_email` |
| `p_` | Password | string | password | `p_secret` → `secret` |
| `ta_` | Textarea | text | textarea | `ta_description` → `description` |

### Numeric Controls

| Prefix | Type | Backend Type | Form Control | Example |
|--------|------|--------------|--------------|---------|
| `n_` | Number | int | number | `n_age` → `age` |
| `rng_` | Range | int | range | `rng_rating` → `rating` |

### Selection Controls

| Prefix | Type | Backend Type | Form Control | Example |
|--------|------|--------------|--------------|---------|
| `sel_` | Select | string | select | `sel_status` → `status` |
| `msel_` | Multi-Select | string | multiselect | `msel_tags` → `tags` |
| `r_` | Radio | string | radio | `r_gender` → `gender` |
| `cb_` | Checkbox | boolean | checkbox | `cb_agree` → `agree` |
| `cbg_` | Checkbox Group | string | checkbox_group | `cbg_options` → `options` |
| `tgl_` | Toggle | boolean | toggle | `tgl_notifications` → `notifications` |

### Foreign Keys

Columns ending with `_id` are automatically detected as foreign keys:

```bash
php artisan add:column order "customer_id,product_id"
```

---

## 📁 Column Structure

Each column in a config file follows this structure:

```json
{
    "name": "column_name",
    "backend": {
        "name": "column_name",
        "type": "string",
        "primary": false,
        "foreign": false,
        "fillable": true,
        "nullable": true,
        "length": null,
        "default": "",
        "options": [],
        "required": true,
        "unique": false,
        "file_types": ""
    },
    "frontend": {
        "form_control": "text",
        "display": {
            "view": true,
            "table": true,
            "form": true
        },
        "text": {
            "label": "Column Name",
            "caption": "",
            "description": "",
            "defaultValue": null
        },
        "attributes": {
            "readonly": false,
            "disabled": false
        },
        "layout": {
            "type": "top",
            "weight": 6,
            "icon": "fa-user",
            "text": null
        },
        "classes": {
            "label": "",
            "input": "",
            "container": "",
            "caption": "",
            "error": "",
            "view_label": "",
            "view_value": ""
        },
        "table": {
            "key": "column_name",
            "label": "Column Name",
            "value_type": "text",
            "visible": true,
            "sortable": true,
            "searchable": true,
            "hideable": true
        },
        "view": {
            "key": "column_name",
            "label": "Column Name",
            "view_type": "text",
            "alignment": "left",
            "section": "default",
            "input_type": "textfield"
        },
        "controlSettings": {
            // Control-specific settings
        }
    }
}
```

### Backend Properties

| Property | Type | Description |
|----------|------|-------------|
| `type` | string | Database column type (`string`, `int`, `boolean`, `date`, `datetime`, `time`, `text`) |
| `primary` | boolean | Is this the primary key? |
| `foreign` | boolean | Is this a foreign key? |
| `fillable` | boolean | Can be mass-assigned |
| `nullable` | boolean | Allows NULL values |
| `length` | int/null | Max length for string columns |
| `default` | mixed | Default value |
| `options` | array | Enum options for select fields |
| `required` | boolean | Required in forms |
| `unique` | boolean | Must be unique in database |
| `file_types` | string | Allowed file extensions (e.g., `"jpg|png|pdf"`) |

### Frontend Properties

| Property | Description |
|----------|-------------|
| `form_control` | The input type (`text`, `email`, `password`, `number`, `select`, `multiselect`, `radio`, `checkbox`, `checkbox_group`, `textarea`, `file`, `date`, `datetime`, `time`, `daterange`, `range`, `toggle`) |
| `display` | Controls visibility in view, table, and form |
| `text` | Labels and captions |
| `attributes` | HTML attributes like readonly/disabled |
| `layout` | Grid layout settings |
| `classes` | CSS classes for styling |
| `table` | Table display settings |
| `view` | Detail view settings |
| `controlSettings` | Control-specific configurations |

### Control Settings

Each form control has specific settings in `controlSettings`:

```json
{
    "controlSettings": {
        "file": {
            "fileAccept": "",
            "fileMultiple": true,
            "fileMaxFiles": 100,
            "fileMaxSize": 999999999
        },
        "select": {
            "selectOptions": [{"value": null, "label": "Please select"}],
            "selectSearchable": true,
            "selectClearable": true
        },
        "number": {
            "numberMin": 0,
            "numberMax": 100,
            "numberStep": 1
        },
        "textarea": {
            "textareaRows": 5,
            "textareaCols": 50,
            "textareaResize": "vertical"
        },
        "password": {
            "passwordStrengthRequirements": {
                "minLength": 8,
                "requireUppercase": true,
                "requireLowercase": true,
                "requireNumbers": true,
                "requireSymbols": true
            },
            "passwordShowStrengthIndicator": true
        }
    }
}
```

---

## 🔗 Relationships

Relationships between configs are defined in the `relationship` property. They are **auto-populated** when `php artisan configurator` runs by scanning all configs for foreign key columns (columns ending in `_id`).

### Config JSON Format

```json
"relationship": {
    "<related_table_name>": "one" | "many"
}
```

### How Auto-Fill Works

When a config has a foreign key column (e.g., `customer_id`), `fillAllRelationships()` writes to both sides:

- The **FK-owning table** (has the `_id` column) gets `"<parent_table>": "many"`
- The **referenced table** (parent) gets `"<child_table>": "one"`

### Generated Eloquent Code

| Config Value | FK Column in Model? | Generated Relationship |
|---|---|---|
| `"category": "one"` | No | `HasOne` |
| `"category": "many"` | No | `HasMany` |
| Any value | Yes (`category_id` in columns) | `BelongsTo` |

Each relationship also:
- Adds a `use App\Models\<RelatedModel>;` import
- Adds the key to `public array $join = [...]`

### Example

Given an `order` config with a `customer_id` foreign key column:

**order.json** (auto-filled):
```json
"relationship": {
    "customer": "many"
}
```

Since `order` has the `customer_id` column, this generates a `BelongsTo`:
```php
public function customer(): BelongsTo {
    return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
}
public array $join = ['customer'];
```

**customer.json** (auto-filled on the reverse side):
```json
"relationship": {
    "order": "one"
}
```

Since `customer` does NOT have an `order_id` column, this generates a `HasOne`:
```php
public function order(): HasOne {
    return $this->hasOne(Order::class, 'customer_id', 'customer_id');
}
```

You can manually change `"one"` to `"many"` in the customer config to generate `HasMany` instead.

---

## 📐 Attributes (Computed Accessors)

Attributes define computed Laravel accessors that are automatically appended to JSON serialization (API responses).

### Config JSON Format

```json
"attributes": {
    "<attribute_name>": "<template string using {column_name}>"
}
```

The `{column_name}` placeholders are converted to `{$this->column_name}` in the generated code.

### Example

```json
"attributes": {
    "full_name": "{first_name} {last_name}",
    "display_label": "{title} ({code})"
}
```

Generates in `ModModel.php`:
```php
public function getFullNameAttribute(): string {
    return "{$this->first_name} {$this->last_name}";
}

public function getDisplayLabelAttribute(): string {
    return "{$this->title} ({$this->code})";
}

public $appends = ['full_name', 'display_label'];
```

Because they are added to `$appends`, these computed values appear automatically in all API responses alongside regular columns.

---

## 🚀 Usage Examples

### Creating a Complete Module

```bash
# 1. Create config with columns
php artisan add:config customer "name,e_email,p_password,d_birth_date,sel_status,fm_documents"

# 2. Generate all files (also runs Vue builder automatically)
php artisan configurator

# 3. Run migrations
php artisan migrate

# 4. Seed data
php artisan db:seed
```

### Config File Location

All configuration files are stored in the `core/` directory:

```
core/
├── customer.json
├── product.json
├── order.json
├── backups/
│   └── customer-20240101120000.json
├── logs/
└── __roles.json
```

### Helper Functions

```php
// Get a specific column from config
$column = getColumn('customer', 'email');

// Get entire config
$config = getConfig('customer');

// Check if column allows multiple files
$isMultiple = isFileMultiple($column);
```

---

## 🔄 Document Parser Runner – Systemd Service Installation Guide

This guide explains how to install and run `document_parser_runner` as a **systemd service** on Ubuntu so it starts automatically on boot.

### 📁 Script Details

- **Service name:** `document_parser_runner`
- **Script path:** `/development/document_parser/runner.sh`
- **Run user:** `ubuntu`

### 1️⃣ Ensure the Script Is Executable

```bash
chmod +x /development/document_parser/runner.sh
```

Make sure the script starts with a shebang:

```bash
#!/bin/bash
```

### 2️⃣ Create the Systemd Service File

Create the service definition:

```bash
sudo nano /etc/systemd/system/document_parser_runner.service
```

Paste the following:

```ini
[Unit]
Description=Document Parser Runner
After=network.target

[Service]
Type=simple
User=ubuntu
WorkingDirectory=/development/document_parser
ExecStart=/development/document_parser/runner.sh
Restart=always
RestartSec=5
StandardOutput=append:/var/log/document_parser_runner.log
StandardError=append:/var/log/document_parser_runner.log

[Install]
WantedBy=multi-user.target
```

Save and exit.

### 3️⃣ Reload Systemd and Enable the Service

```bash
sudo systemctl daemon-reload
sudo systemctl enable document_parser_runner
sudo systemctl start document_parser_runner
```

### 4️⃣ Verify Service Status

```bash
systemctl status document_parser_runner
```

Expected result:

```
Active: active (running)
```

### 5️⃣ View Logs

#### Log file
```bash
tail -f /var/log/document_parser_runner.log
```

#### systemd journal
```bash
journalctl -u document_parser_runner -f
```

### 6️⃣ Test on Reboot

```bash
sudo reboot
```

After reboot:

```bash
systemctl status document_parser_runner
```

### ⚠️ Important Notes

- Do **NOT** background the script (`&`) when running under systemd
- Use **absolute paths** inside `runner.sh`
- If the script exits, systemd will restart it automatically
- For long-running workers, keep the script alive (loop or blocking process)

### ✅ Uninstall / Disable Service

```bash
sudo systemctl stop document_parser_runner
sudo systemctl disable document_parser_runner
sudo rm /etc/systemd/system/document_parser_runner.service
sudo systemctl daemon-reload
```

---
