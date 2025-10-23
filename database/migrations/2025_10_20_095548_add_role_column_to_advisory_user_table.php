<?php

use App\Enums\AdvisoryRole;
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
        Schema::table('advisory_user', function (Blueprint $table) {
            $table->string('role')->after('user_id')->nullable();
        });

        DB::table('advisory_user')->update(['role' => AdvisoryRole::Member->value]);

        Schema::table('advisory_user', function (Blueprint $table) {
            $table->string('role')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advisory_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
