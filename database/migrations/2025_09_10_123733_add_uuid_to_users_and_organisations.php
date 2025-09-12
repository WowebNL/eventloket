<?php

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        User::whereNull('uuid')->get()->each(function (User $user) {
            $user->uuid = (string) Str::uuid();
            $user->save();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        Organisation::whereNull('uuid')->get()->each(function (Organisation $organisation) {
            $organisation->uuid = (string) Str::uuid();
            $organisation->save();
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
