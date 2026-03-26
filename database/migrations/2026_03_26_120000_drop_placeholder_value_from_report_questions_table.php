<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_questions', function (Blueprint $table) {
            $table->dropColumn('placeholder_value');
        });
    }

    public function down(): void
    {
        Schema::table('report_questions', function (Blueprint $table) {
            $table->string('placeholder_value', 50)->nullable()->after('is_active');
        });
    }
};
