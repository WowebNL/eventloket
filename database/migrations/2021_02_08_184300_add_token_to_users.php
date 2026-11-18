<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Previously shipped by the woweb/openzaak package. Kept under the same file
 * name so databases that already ran it see it as migrated, while a fresh
 * database still gets the columns the users table and later migrations expect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('openzaak_jwt')->nullable();
            $table->dateTime('openzaak_jwt_valid_till')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('openzaak_jwt');
            $table->dropColumn('openzaak_jwt_valid_till');
        });
    }
};
