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
        Schema::table('users', function (Blueprint $table) {
            //
            $table->uuid('branch_id')
                    ->nullable()
                    ->after('id')
                    ->comment('Branch the user is assigned to');

            $table->foreign('branch_id')
                    ->references('id')
                    ->on('branches')
                    ->nullOnDelete(); // Sets branch_id to null if the branch is deleted
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
