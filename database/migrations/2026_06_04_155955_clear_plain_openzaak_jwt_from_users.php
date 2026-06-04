<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('openzaak_jwt')->update([
            'openzaak_jwt' => null,
            'openzaak_jwt_valid_till' => null,
        ]);
    }

    public function down(): void
    {
        // Plain-text JWTs are intentionally wiped and cannot be restored
    }
};
