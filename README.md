# Affogato

Composer Plugin for Laravel RDBMS Development

## 🔧 Laravel 12 Installation

1. Create a new Laravel app:

   ```bash
   laravel new test-app
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
