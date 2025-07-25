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
        Schema::create('admin_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->uuid('token')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_invites');
    }
};
