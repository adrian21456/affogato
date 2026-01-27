<?php

use Zchted\Affogato\Command;
use Illuminate\Support\Facades\Artisan;

Artisan::command('add:config {config} {columns} {type=default}', function () {
    $config = $this->argument('config');
    $columns = $this->argument('columns');
    $type = $this->argument('type');

    Command::boot();
    $response = Command::makeConfig($config, $columns, $type);
    console($this, $response);
});
Artisan::command('rollback:config {config}', function ($config) {
    Command::boot();
    $response = Command::rollbackConfig($config);
    console($this, $response);
});

Artisan::command('add:column {config} {columns}', function ($config, $columns) {
    Command::boot();
    $response = Command::makeColumns($config, $columns);
    console($this, $response);
});

Artisan::command('delete:column {config} {columns}', function ($config, $columns) {
    Command::boot();
    $response = Command::deleteColumns($config, $columns);
    console($this, $response);
});

Artisan::command('configurator', function () {
    Command::boot();
    $response = Command::configurator();
    console($this, $response);

    // Run Vue builder interpreter after configurator completes
    $this->info('Running Vue builder interpreter...');
    $output = shell_exec('node vue/utils/builder/interpreter.js --all 2>&1');
    if ($output) {
        $this->line($output);
    }
    $this->info('Vue builder interpreter completed.');
});
