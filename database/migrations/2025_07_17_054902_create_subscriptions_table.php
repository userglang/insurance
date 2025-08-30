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
        Schema::create('subscriptions', function (Blueprint $table) {
            // Primary UUID
            $table->uuid('id')->primary()->comment('Primary UUID identifier for the subscription record');

            // Foreign key to members table
            $table->uuid('member_id')->comment('Reference to the member who owns this subscription');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');

            // Foreign key to insurance table
            $table->uuid('insurance_id')->comment('Reference to the insurance for this subscription');
            $table->foreign('insurance_id')->references('id')->on('insurances')->onDelete('cascade');

            // Foreign key to product_accounts table
            $table->uuid('product_account_id')->comment('Reference to the product account for this subscription');
            $table->foreign('product_account_id')->references('id')->on('product_accounts')->onDelete('cascade');

            // Subscription amount
            $table->decimal('amount', 15, 2)->default(0)->comment('Subscription amount or fee');
            $table->date('payment_date')->comment('Date when subscription paid');

            // Activation and expiration timestamps
            $table->date('activated_at')->nullable()->comment('Date and time when subscription became active');
            $table->date('expires_at')->nullable()->comment('Date and time when subscription will expire');

            // Optional remarks
            $table->text('remark')->nullable()->comment('Additional notes or remarks about the subscription');

            // Audit fields
            $table->uuid('created_by')->nullable()->comment('User ID who created the record');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            $table->uuid('updated_by')->nullable()->comment('User ID who last updated the record');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('cascade');

            // Laravel timestamps
            $table->timestamps();

            // Indexes
            $table->index('activated_at');
            $table->index('expires_at');

            // Optional: enforce one subscription per member-product pair
//            $table->unique(['member_id', 'insurance_id', 'product_account_id'], 'unique_member_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
