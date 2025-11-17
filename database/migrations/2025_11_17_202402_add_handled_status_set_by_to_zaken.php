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
        Schema::table('zaken', function (Blueprint $table) {
            $table->unsignedBigInteger('handled_status_set_by_user_id')->nullable()->after('reference_data')->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zaken', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handled_status_set_by_user_id');
            $table->dropColumn('handled_status_set_by_user_id');
        });
    }
};
