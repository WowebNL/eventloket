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
        Schema::create('status_resultaat_colors', function (Blueprint $table) {
            $table->id();
            $table->string('status_name');
            $table->string('resultaat')->nullable();
            $table->string('color');
            $table->timestamps();

            $table->unique(['status_name', 'resultaat']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_resultaat_colors');
    }
};
