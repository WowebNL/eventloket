<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-municipality blueprint that maps each logical Eventloket role to a
     * concrete ZGW zaaktype (by identificatie) plus the eigenschap names and
     * flow-blocker selectors used for that zaaktype. Replaces the name/string
     * matching heuristics. Every column is nullable: a missing value (or a
     * missing row) falls back to the original heuristic, so behaviour is
     * unchanged until a koppeling beheerder fills it in.
     */
    public function up(): void
    {
        Schema::create('municipality_zaaktype_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->string('role');

            // Links to the logical Zaaktype.identificatie (stable across versions).
            $table->string('zaaktype_identificatie')->nullable();

            // Logical eigenschap key -> ZGW eigenschap naam in this catalogus.
            $table->json('eigenschap_map')->nullable();

            // Flow-blocker selectors, stored as the stable logical label
            // (omschrijving / omschrijvingGeneriek) so they survive a zaaktype
            // version bump; resolved against the live catalogus at call time.
            $table->string('initial_statustype')->nullable();
            $table->string('eind_statustype')->nullable();
            $table->string('initiator_roltype')->nullable();
            $table->string('ingetrokken_resultaattype')->nullable();
            $table->string('bijlage_informatieobjecttype')->nullable();

            $table->timestamps();

            $table->unique(['municipality_id', 'role']);
            // Explicit, shortened index name: the auto-generated name exceeds
            // MySQL's 64-character identifier limit (PostgreSQL truncates it
            // silently, MySQL errors), which would break the migration there.
            $table->index(['municipality_id', 'zaaktype_identificatie'], 'municipality_zaaktype_map_identificatie_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_zaaktype_mappings');
    }
};
