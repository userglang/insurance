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
        Schema::create('members', function (Blueprint $table) {
            // Primary Key
            $table->uuid('id')->primary()->comment('Primary UUID identifier for the member');

            // Basic Information
            $table->string('cid')->nullable()->comment('Custom Member Code or Customer ID');
            $table->string('branch_number')->comment('Reference to the associated branch');

            $table->string('first_name')->comment('First name of the member');
            $table->string('last_name')->comment('Last name of the member');
            $table->string('middle_name')->nullable()->comment('Middle name of the member');
            $table->string('suffix')->nullable()->comment('Name suffix (e.g., Jr., III)');

            $table->date('birth_date')->nullable()->comment('Date of birth');
            $table->text('birth_place')->nullable()->comment('Place of Birth');
            $table->string('email')->nullable()->unique()->comment('Email address (must be unique if provided)');
            $table->string('contact_number')->nullable()->comment('Primary contact number');

            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable()->comment('Gender identity');
            $table->enum('marital_status', ['Single', 'Married', 'Separated', 'Widowed'])->nullable()->comment('Current marital status');

            // Government IDs
            $table->string('sss_gsis')->nullable()->comment('SSS or GSIS number');
            $table->string('tin')->nullable()->comment('Tax Identification Number');

            // Residential Address
            $table->text('house_number')->nullable()->comment('House or unit number');
            $table->text('street')->nullable()->comment('Street name');
            $table->text('barangay')->nullable()->comment('Barangay');
            $table->text('city')->nullable()->comment('City or municipality');
            $table->text('province')->nullable()->comment('Province');
            $table->text('zipcode')->nullable()->comment('Postal code');

            // Employment Information
            $table->string('occupation')->nullable()->comment('Job title or occupation');
            $table->string('name_of_employer')->nullable()->comment('Employer name');
            $table->string('employment_status')->nullable()->comment('Employment status (e.g., Regular, Contractual)');
            $table->string('office_contact_number')->nullable()->comment('Employer’s contact number');
            $table->text('office_address')->nullable()->comment('Complete office address');

            // Status & Remarks
            $table->boolean('is_active')->default(true)->comment('True if the member is active');
            $table->text('remark')->nullable()->comment('Additional notes or remarks about the member');

            // Timestamps
            $table->timestamps();

            // Foreign Keys
            $table->foreign('branch_number')->references('branch_number')->on('branches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
