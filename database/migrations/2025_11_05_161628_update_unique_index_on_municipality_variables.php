<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('municipality_variables', function (Blueprint $table) {

            $table->dropForeign(['municipality_id']);
            $table->dropUnique(['municipality_id', 'key']);

            if (config('database.default') === 'mysql') {
                // mysql doesn't support partial indexes, so we need to use a virtual column
                DB::statement('ALTER TABLE municipality_variables ADD not_deleted_virtual BOOLEAN GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) VIRTUAL;');
                $table->unique(['municipality_id', 'key', 'not_deleted_virtual'], 'municipality_variables_unique_key_municipality_not_deleted');
            } else {
                DB::statement('CREATE UNIQUE INDEX "municipality_variables_unique_key_municipality_not_deleted" ON municipality_variables (municipality_id, key) WHERE deleted_at IS NULL;');
            }

            $table->foreign('municipality_id')->references('id')->on('municipalities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('municipality_variables', function (Blueprint $table) {
            $table->dropUnique('municipality_variables_unique_key_municipality_not_deleted');

            $table->unique(['municipality_id', 'key']);
        });
    }
};
