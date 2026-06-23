<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De `formsubmission_sessions`-tabel was de opslag van de koppeling
 * user+organisation ↔ OF-submission-UUID (kenmerk), zodat OF via
 * GET /api/formsessions z'n sessie-context kon ophalen. Met de nieuwe
 * Filament-flow leeft de draft als een `event_form_drafts`-rij en is
 * er geen OF-sessie meer te tracken — deze tabel is daarmee dood.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('formsubmission_sessions');
    }

    public function down(): void
    {
        Schema::create('formsubmission_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_uuid')->index();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organisation_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['submission_uuid', 'user_id', 'organisation_id']);
        });
    }
};
