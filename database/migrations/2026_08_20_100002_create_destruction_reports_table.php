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
        Schema::create('destruction_reports', function (Blueprint $table) {
            $table->id();
            // No cascade: the destruction report is a permanent legal record.
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->foreignId('destruction_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number')->unique();
            $table->string('coordinator_name');
            $table->string('coordinator_function')->nullable();
            $table->foreignId('coordinator_user_id')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->string('destruction_method');
            $table->timestamp('destruction_date');
            $table->jsonb('items');
            $table->string('pdf_path')->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('deleted_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamps();
        });

        Schema::table('destruction_lists', function (Blueprint $table) {
            $table->foreignId('destruction_report_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('destruction_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destruction_report_id');
        });

        Schema::dropIfExists('destruction_reports');
    }
};
