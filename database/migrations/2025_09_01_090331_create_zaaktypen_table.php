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
        Schema::create('zaaktypen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('oz_url')->unique();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zaaktypen');
    }
};
