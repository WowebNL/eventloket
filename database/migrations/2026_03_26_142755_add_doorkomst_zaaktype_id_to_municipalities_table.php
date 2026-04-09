<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            $table->uuid('doorkomst_zaaktype_id')->nullable()->after('brk_uuid');
            $table->foreign('doorkomst_zaaktype_id')->references('id')->on('zaaktypen')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropForeign(['doorkomst_zaaktype_id']);
            $table->dropColumn('doorkomst_zaaktype_id');
        });
    }
};
