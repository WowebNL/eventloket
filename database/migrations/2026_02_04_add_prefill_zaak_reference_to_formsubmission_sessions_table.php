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
        Schema::table('formsubmission_sessions', function (Blueprint $table) {
            $table->uuid('prefill_zaak_reference')->nullable()->after('organisation_id');
            $table->foreign('prefill_zaak_reference')
                ->references('id')
                ->on('zaken')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('formsubmission_sessions', function (Blueprint $table) {
            $table->dropForeign(['prefill_zaak_reference']);
            $table->dropColumn('prefill_zaak_reference');
        });
    }
};
