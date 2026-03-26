<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zaaktypen', function (Blueprint $table) {
            $table->boolean('triggers_route_check')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('zaaktypen', function (Blueprint $table) {
            $table->dropColumn('triggers_route_check');
        });
    }
};
