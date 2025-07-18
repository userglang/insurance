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
        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Primary key: UUID');

            $table->string('branch_number')->unique()->comment('System-generated or official branch number');
            $table->string('branch_name')->comment('Descriptive name of the branch');
            $table->string('address')->nullable()->comment('Full address of the branch');
            $table->string('code')->unique()->comment('Short reference code for branch (e.g. for dropdowns or reports)');
            $table->boolean('is_active')->default(true)->comment('Indicates if the branch is active');

            // Timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
