<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zaken', function (Blueprint $table) {
            $table->jsonb('form_state_snapshot')->nullable()->after('reference_data');
        });
    }

    public function down(): void
    {
        Schema::table('zaken', function (Blueprint $table) {
            $table->dropColumn('form_state_snapshot');
        });
    }
};
