<?php

use Zchted\Affogato\Command;
use Illuminate\Support\Facades\Artisan;

Artisan::command('add config {config} {columns} {type=default}', function () {
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

Artisan::command('add column {config} {columns}', function ($config, $columns) {
    Command::boot();
    $response = Command::makeColumns($config, $columns);
    console($this, $response);
});

Artisan::command('delete:column {config} {columns}', function ($config, $columns) {
    Command::boot();
    $response = Command::deleteColumns($config, $columns);
    console($this, $response);
});

Artisan::command('add attribute {config} {columns}', function ($config, $columns) {
    Command::boot();
    $response = Command::makeAttribute($config, $columns);
    console($this, $response);
});

Artisan::command('add class {config} {columns}', function ($config, $columns) {
    Command::boot();
    $response = Command::makeClass($config, $columns);
    console($this, $response);
});

Artisan::command('add validation {config} {columns}', function ($config, $columns) {
    Command::boot();
    $response = Command::makeValidation($config, $columns);
    console($this, $response);
});

Artisan::command('configurator', function () {
    Command::boot();
    $response = Command::configurator();
    console($this, $response);
});
