<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('password');
            $table->string('department')->nullable()->after('role');
        });

        User::factory()->create([
            'name' => env('TEST_USER_NAME'),
            'email' => env('TEST_USER_EMAIL'),
            'password' => bcrypt(env('TEST_USER_PASSWORD')),
            'role' => 'Administrator',
            'department' => 'Administrators',
        ]);

        User::factory()->create([
            'name' => 'Administrator 2',
            'email' => 'dumaopa@gmail.com',
            'password' => bcrypt(env('TEST_USER_PASSWORD')),
            'role' => 'Administrator',
            'department' => 'Administrators',
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'department']);
        });
    }

};
