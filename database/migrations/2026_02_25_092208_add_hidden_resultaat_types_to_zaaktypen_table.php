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
        Schema::table('zaaktypen', function (Blueprint $table) {
            $table->json('hidden_resultaat_types')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zaaktypen', function (Blueprint $table) {
            $table->dropColumn('hidden_resultaat_types');
        });
    }
};
