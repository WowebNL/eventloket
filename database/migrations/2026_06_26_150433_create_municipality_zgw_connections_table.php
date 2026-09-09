<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-municipality ZGW connection (one-to-one with municipalities). When a
     * municipality has a row here, ZgwConnectionResolver registers it as the
     * runtime connection "gemeente_{id}", inheriting any unset value from the
     * global "main" connection. Without a row the municipality keeps using main.
     *
     * The client_secret is stored encrypted (see the model's encrypted cast).
     * Every connection column is nullable so a partial row still inherits the
     * remaining values from main.
     */
    public function up(): void
    {
        Schema::create('municipality_zgw_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->unique()->constrained()->cascadeOnDelete();

            // The six ZGW API base URLs (full URL incl. version path + trailing slash).
            $table->string('zaken_url')->nullable();
            $table->string('catalogi_url')->nullable();
            $table->string('documenten_url')->nullable();
            $table->string('besluiten_url')->nullable();
            $table->string('autorisaties_url')->nullable();
            $table->string('notificaties_url')->nullable();

            // Target ZGW standard release (1.5 / 1.6 / 1.7); null inherits main.
            $table->string('version')->nullable();

            $table->string('client_id')->nullable();
            // Encrypted at rest by the model cast; text to fit the ciphertext.
            $table->text('client_secret')->nullable();
            $table->string('user_id')->nullable();
            $table->string('user_representation')->nullable();

            // Extra origins (besides the six URLs) the connection may fetch from.
            $table->json('allowed_hosts')->nullable();

            // Application-level technical parameters (decision 7), null inherits main.
            $table->string('bronorganisatie_rsin')->nullable();
            $table->json('vertrouwelijkheid_map')->nullable();
            $table->string('eigenschap_date_format')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipality_zgw_connections');
    }
};
