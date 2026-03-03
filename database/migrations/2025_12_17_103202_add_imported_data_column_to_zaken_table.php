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
            $table->json('imported_data')->after('reference_data')->nullable();

            $table->string('public_id')->nullable()->change();
            $table->string('zgw_zaak_url')->nullable()->change();
            $table->foreignUuid('zaaktype_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zaken', function (Blueprint $table) {
            $table->dropColumn('imported_data');

            $table->string('public_id')->nullable(false)->change();
            $table->string('zgw_zaak_url')->nullable(false)->change();
            $table->foreignUuid('zaaktype_id')->nullable(false)->change();
        });
    }
};
