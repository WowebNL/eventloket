<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic, typed relation table between two zaken. The reading
     * direction is fixed per type (see ZaakRelatieType): `zaak_id` is the
     * subject, `gerelateerde_zaak_id` the object. First consumer is the
     * "vervangt_vooraankondiging" relation of issue #10.
     *
     * `cascadeOnDelete` only fires on hard deletes; `Zaak` uses soft
     * deletes, so readers that must ignore relations to a soft-deleted
     * zaak (e.g. the calendar filter) have to check `deleted_at` of the
     * related zaak themselves.
     */
    public function up(): void
    {
        Schema::create('zaak_relaties', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('zaak_id')->constrained('zaken')->cascadeOnDelete();
            $table->foreignUuid('gerelateerde_zaak_id')->constrained('zaken')->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();

            $table->unique(['zaak_id', 'gerelateerde_zaak_id', 'type']);
            $table->index(['gerelateerde_zaak_id', 'type']);
        });

        // Self-reference guard at the database level; the model repeats
        // this check so a violation fails early with a clear message.
        DB::statement('ALTER TABLE zaak_relaties ADD CONSTRAINT zaak_relaties_no_self_reference CHECK (zaak_id <> gerelateerde_zaak_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('zaak_relaties');
    }
};
