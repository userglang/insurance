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
        Schema::table('product_accounts', function (Blueprint $table) {
            $table->uuid('product_id')
                ->nullable()
                ->after('member_id')
                ->comment('Optional UUID reference to a product (no foreign key constraint)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_accounts', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
    }
};
