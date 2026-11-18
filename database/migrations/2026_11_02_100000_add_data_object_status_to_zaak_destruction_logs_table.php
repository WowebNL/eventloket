<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What happened to the form submission object of the zaak: deleted, not
     * present, refused or failed. Nullable, because rows written before this
     * column existed never recorded it.
     */
    public function up(): void
    {
        Schema::table('zaak_destruction_logs', function (Blueprint $table) {
            $table->string('data_object_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('zaak_destruction_logs', function (Blueprint $table) {
            $table->dropColumn('data_object_status');
        });
    }
};
