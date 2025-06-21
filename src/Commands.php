<?php

use Zchted\Affogato\Command;
use Illuminate\Support\Facades\Artisan;

Artisan::command('add:mod {name}', function ($name) {
    $response = Command::makeMod($name);
    console($this, $response);
});

Artisan::command('add:config {config} {columns} {type=default}', function () {
    $config = $this->argument('config');
    $columns = $this->argument('columns');
    $type = $this->argument('type');

    $response = Command::makeConfig($config, $columns, $type);
    console($this, $response);
});
Artisan::command('rollback:config {config}', function ($config) {
    $response = Command::rollbackConfig($config);
    console($this, $response);
});

Artisan::command('add:column {config} {columns}', function ($config, $columns) {
    $response = Command::makeColumns($config, $columns);
    console($this, $response);
});

Artisan::command('delete:column {config} {columns}', function ($config, $columns) {
    $response = Command::deleteColumns($config, $columns);
    console($this, $response);
});

Artisan::command('add:attribute {config} {columns}', function ($config, $columns) {
    $response = Command::makeAttribute($config, $columns);
    console($this, $response);
});

Artisan::command('add:class {config} {columns}', function ($config, $columns) {
    $response = Command::makeClass($config, $columns);
    console($this, $response);
});

Artisan::command('add:validation {config} {columns}', function ($config, $columns) {
    $response = Command::makeValidation($config, $columns);
    console($this, $response);
});

Artisan::command('configurator', function () {
    $response = Command::configurator();
    console($this, $response);
});
