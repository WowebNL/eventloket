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
        Schema::create('threads', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('zaak_id')->constrained('zaken');
            $table->string('type');

            $table->string('title');
            //            $table->text('body')->nullable();

            $table->foreignId('advisory_id')->nullable()->constrained();
            $table->string('advice_status')->nullable();
            $table->timestamp('advice_due_at')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
