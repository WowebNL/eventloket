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
        Schema::create('municipality_form_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('order');
            $table->string('type', 20); // text | radio | checkboxes
            $table->text('label');
            $table->text('helper_text')->nullable();
            $table->json('options')->nullable(); // list<string>, only for radio and checkboxes
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            // Which aanvraag paths this question applies to. Null or an empty list
            // means: every path. Values match DetermineAanvraagType's constants:
            // 'vergunning', 'melding', 'vooraankondiging'.
            $table->json('show_for_aanvraag_types')->nullable();
            $table->timestamps();

            // Mirrors report_questions so the reordering trait can be reused.
            $table->unique(['municipality_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipality_form_questions');
    }
};
