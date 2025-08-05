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
        Schema::create('admin_invite_municipality', function (Blueprint $table) {
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_invite_id')->constrained()->cascadeOnDelete();
            $table->primary(['municipality_id', 'admin_invite_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_invite_municipality');
    }
};
