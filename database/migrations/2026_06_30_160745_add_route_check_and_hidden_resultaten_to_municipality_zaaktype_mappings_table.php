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
        Schema::table('municipality_zaaktype_mappings', function (Blueprint $table) {
            // Per-municipality overrides for own-instance zaaktypen. Null means
            // "fall back to the admin-managed value on the zaaktype row".
            $table->boolean('triggers_route_check')->nullable()->after('zaaktype_identificatie');
            $table->json('hidden_resultaat_types')->nullable()->after('triggers_route_check');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('municipality_zaaktype_mappings', function (Blueprint $table) {
            $table->dropColumn(['triggers_route_check', 'hidden_resultaat_types']);
        });
    }
};
