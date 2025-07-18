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
        Schema::create('product_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('Primary UUID identifier for the product account');

            // Foreign key to members table
            $table->uuid('member_id')->index()->comment('Foreign key referencing the member who owns the product account');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');

            $table->string('product_name')->comment('Name of the product associated with the account');
            $table->string('account_number')->comment('Unique account number for the product account');

            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_accounts');
    }
};
