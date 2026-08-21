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
        Schema::create('destruction_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->text('review_feedback')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            // Snapshot of the coordinator who confirmed the destruction, kept for
            // the legally required destruction report even if the account is removed.
            $table->string('coordinator_name')->nullable();
            $table->string('coordinator_function')->nullable();
            $table->string('destruction_method')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destruction_lists');
    }
};
