<?php

use App\Models\Municipality;
use Database\Seeders\SouthLimburgMunicipalitiesSeeder;
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
        $runSeeder = Municipality::count() ? true : false;
        Schema::table('municipalities', function (Blueprint $table) {
            $table->string('brk_identification')->after('name')->nullable()->unique()->startingValue('GM');
            $table->uuid('brk_uuid')->after('brk_identification')->nullable();
            $table->json('geometry')->after('brk_identification')->nullable();
        });

        if ($runSeeder) {
            (new SouthLimburgMunicipalitiesSeeder)->run();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            $table->dropUnique(['brk_identification']);
            $table->dropColumn(['brk_identification', 'geometry', 'brk_uuid']);
        });
    }
};
