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
            // Local hoofdzaak link for doorkomst (route passage) zaken. ZGW only
            // relates hoofdzaak/deelzaak within one instance, so cross-instance
            // doorkomst zaken keep their relationship here instead of in ZGW.
            $table->foreignUuid('hoofdzaak_id')->nullable()->after('zaaktype_id')->constrained('zaken')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zaken', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hoofdzaak_id');
        });
    }
};
