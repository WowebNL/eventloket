<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per zaak whose Eventloket data was destroyed.
     *
     * Deliberately not the activity log: that table is pruned, and the
     * destruction itself empties it for the zaak in question. These rows are
     * the sole evidence that Eventloket removed its own data, and the nightly
     * report is built from them, so they live on their own terms.
     *
     * Every column is a snapshot. By the time the row is written the zaak is
     * gone from both the zaaksysteem and Eventloket, so nothing can be joined
     * back to.
     */
    public function up(): void
    {
        Schema::create('zaak_destruction_logs', function (Blueprint $table) {
            $table->id();
            // No cascade: this is a permanent record, like the report built from it.
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zgw_connection');
            // Unique: a replayed notification must not log the destruction twice.
            $table->string('zgw_zaak_url')->unique();
            $table->string('zaaknummer')->nullable();
            $table->string('zaaktype_naam')->nullable();
            $table->timestamp('destroyed_at');
            // Set once the nightly command has rolled this row into a report.
            $table->foreignId('destruction_report_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            // The nightly command reads exactly this: not yet reported, per municipality.
            $table->index(['reported_at', 'municipality_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zaak_destruction_logs');
    }
};
