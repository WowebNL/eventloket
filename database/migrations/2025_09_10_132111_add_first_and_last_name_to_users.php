<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('uuid');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // Set the first_name and last_name fields based on the existing name field

        // Check if there are users without first_name using raw DB query
        $usersWithoutFirstName = DB::select('SELECT COUNT(*) as count FROM users WHERE first_name IS NULL')[0]->count;

        if ($usersWithoutFirstName > 0) {
            User::chunk(100, function (Collection $collection) {
                $collection->each(function (User $user) {
                    if ($user->name) {
                        $nameParts = explode(' ', $user->name, 2);
                        $user->first_name = $nameParts[0];
                        $user->last_name = $nameParts[1] ?? null;
                        $user->save();
                    }
                });
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
