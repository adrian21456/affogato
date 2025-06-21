<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->string('module');
            $table->string('action');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Adds a foreign key constraint to the users table
            $table->text('data_before')->nullable();
            $table->text('data_after')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};
