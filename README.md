# Affogato

Composer Plugin for Laravel RDBMS Development

## 🔧 Laravel Installation

1. Create a new Laravel app:

   ```bash
   laravel new test-app
   ```

2. Run the custom API install script:

   ```bash
   php install:api
   ```

3. Link storage:

   ```bash
   php artisan storage:link
   ```

4. Install Laravel Sanctum:
   ```bash
   composer require laravel/sanctum
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

5. **Add `mods/bootstrap.txt` to `bootstrap/app.php`**

   Load or require the `mods/bootstrap.txt` if needed for boot logic.

6. **Copy `mods/api.php` to `routes/api.php`**

   Merge or replace your `routes/api.php` file with the one from Affogato.

7. **(Optional) Reference a custom `LoginController` in `api.php`**
   ```php
   Route::post('/login', [LoginController::class, 'login']);
   ```

---

## ✅ Notes

- Make sure your `app/Http/Kernel.php` has `api` middleware properly set.
- If using broadcasting, register event and channel files as needed.
