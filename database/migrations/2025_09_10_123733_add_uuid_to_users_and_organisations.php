<?php

use App\Models\Organisation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Update users without UUIDs using raw DB query to avoid model scopes
        $users = DB::select('SELECT id FROM users WHERE uuid IS NULL');
        foreach ($users as $user) {
            DB::update('UPDATE users SET uuid = ? WHERE id = ?', [(string) Str::uuid(), $user->id]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        Organisation::withTrashed()->whereNull('uuid')->get()->each(function (Organisation $organisation) {
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
