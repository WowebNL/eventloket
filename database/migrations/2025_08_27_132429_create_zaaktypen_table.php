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

            $table->string('public_id');
            $table->foreignId('municipality_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');

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
