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
        Schema::create('municipality_invites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('role');
            $table->uuid('token')->unique();
            $table->timestamps();
        });

        Schema::create('municipality_municipality_invite', function (Blueprint $table) {
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_invite_id')->constrained()->cascadeOnDelete();
            $table->primary(['municipality_id', 'municipality_invite_id']);
        });

        Schema::dropIfExists('admin_invite_municipality');

        Schema::table('admin_invites', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipality_invites');
        Schema::dropIfExists('municipality_municipality_invite');

        Schema::table('admin_invites', function (Blueprint $table) {
            $table->string('role')->after('email');
        });

        Schema::create('admin_invite_municipality', function (Blueprint $table) {
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_invite_id')->constrained()->cascadeOnDelete();
            $table->primary(['municipality_id', 'admin_invite_id']);
        });
    }
};
