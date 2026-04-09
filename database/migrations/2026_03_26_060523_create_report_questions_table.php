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
        Schema::create('report_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('order'); // 1-10
            $table->text('question');
            $table->boolean('is_active')->default(true);
            $table->string('placeholder_value', 50)->nullable(); // Voor XX placeholders
            $table->timestamps();

            $table->unique(['municipality_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_questions');
    }
};
