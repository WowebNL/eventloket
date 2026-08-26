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
        Schema::create('destruction_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destruction_list_id')->constrained()->cascadeOnDelete();
            // Nulled when the zaak is destroyed; the snapshot columns below stay behind.
            $table->foreignUuid('zaak_id')->nullable()->constrained('zaken')->nullOnDelete();
            $table->string('zgw_zaak_url');
            $table->string('zaaknummer');
            $table->string('zaaktype_naam')->nullable();
            $table->string('naam_evenement')->nullable();
            $table->string('archiefnominatie')->nullable();
            $table->date('archiefactiedatum')->nullable();
            $table->string('archiefstatus')->nullable();
            $table->string('selectielijstklasse')->nullable();
            $table->string('selectielijst_categorie')->nullable();
            $table->string('bewaartermijn')->nullable();
            $table->string('brondatum_archiefprocedure')->nullable();
            $table->string('status')->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('destroyed_at')->nullable();
            $table->timestamps();

            $table->unique(['destruction_list_id', 'zgw_zaak_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destruction_list_items');
    }
};
