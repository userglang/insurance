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
        Schema::create('insurances', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Primary UUID identifier for the insurance record');

            $table->string('insurance_name', 255)->unique()->comment('Unique name of the insurance plan or product');
            $table->enum('insurance_type', ['Life','Health', 'Auto', 'Home', 'Business', 'Travel', 'Other'])->comment('Type of insurance: Life or Non Life');

            $table->text('description')->nullable()->comment('Detailed description of the insurance plan');

            $table->decimal('amount', 15, 2)->nullable()->comment('Cost or premium of the insurance plan');

            $table->boolean('is_active')->default(true)->comment('Indicates whether the insurance is currently active');

            $table->timestamps();

            // Indexes for faster filtering
            $table->index('insurance_type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurances');
    }
};
