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
        Schema::table('admin_invites', function (Blueprint $table) {
            $table->unique('email');
        });

        Schema::table('advisory_invites', function (Blueprint $table) {
            $table->unique(['advisory_id', 'email']);
        });

        Schema::table('municipality_invites', function (Blueprint $table) {
            $table->unique('email');
        });

        Schema::table('organisation_invites', function (Blueprint $table) {
            $table->unique(['organisation_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_invites', function (Blueprint $table) {
            $table->dropUnique('email');
        });

        Schema::table('advisory_invites', function (Blueprint $table) {
            $table->dropUnique(['advisory_id', 'email']);
        });

        Schema::table('municipality_invites', function (Blueprint $table) {
            $table->dropUnique('email');
        });

        Schema::table('organisation_invites', function (Blueprint $table) {
            $table->dropUnique(['organisation_id', 'email']);
        });
    }
};
