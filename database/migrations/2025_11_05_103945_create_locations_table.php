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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');

            $table->string('postal_code', 6);
            $table->string('house_number', 255);
            $table->string('house_letter', 255)->nullable();
            $table->string('house_number_addition', 255)->nullable();
            $table->string('street_name', 255);
            $table->string('city_name', 255)->nullable();

            $table->boolean('active');

            $table->json('geometry')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
