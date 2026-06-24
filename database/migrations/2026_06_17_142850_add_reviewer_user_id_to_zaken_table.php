<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zaken', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewer_user_id')->nullable()->after('handled_status_set_by_user_id');
            $table->foreign('reviewer_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('zaken', function (Blueprint $table) {
            $table->dropForeign(['reviewer_user_id']);
            $table->dropColumn('reviewer_user_id');
        });
    }
};
