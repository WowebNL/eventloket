<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert all existing emails to lowercase
        DB::statement('UPDATE users SET email = LOWER(email)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - we can't restore the original casing
    }
};
