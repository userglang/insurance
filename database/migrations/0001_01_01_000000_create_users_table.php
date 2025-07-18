<?php

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
        Schema::create('users', function (Blueprint $table) {
            // UUID as primary key
            $table->uuid('id')->primary()->comment('Primary key: UUID');

            // User's full name
            $table->string('name')->comment('Full name of the user');

            // Unique email for login
            $table->string('email')->unique()->comment('Unique email address for the user');

            // Timestamp when email was verified
            $table->timestamp('email_verified_at')->nullable()->comment('Date and time when email was verified');

            // Hashed password
            $table->string('password')->comment('Hashed password');

            // Account active status
            $table->boolean('is_active')->default(true)->comment('Indicates if the user account is active');

            // Remember token for "remember me" functionality
            $table->rememberToken()->comment('Token for remembering the user session');
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index(); // Changed to uuid
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade'); // Optional FK
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
