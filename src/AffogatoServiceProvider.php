<?php

namespace Zchted\Affogato;

use Illuminate\Support\ServiceProvider;
use Zchted\Affogato\CRUDEvent;
use Illuminate\Support\Facades\Event;


class AffogatoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        //
        // If you want to bind services or config later, put it here
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->loadConsoleCommands();
        }
    }

    /**
     * Load closure-based Artisan commands from the package.
     */
    protected function loadConsoleCommands()
    {
        $commandPath = __DIR__ . '/Commands.php';

        if (file_exists($commandPath)) {
            require $commandPath;
        }
    }
}
