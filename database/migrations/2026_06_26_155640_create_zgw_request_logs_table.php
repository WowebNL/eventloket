<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Request-level audit log for ZGW calls (metadata only, never bodies),
     * fed by the package's ZgwRequestSent event. The URI is stored without its
     * query string because ZGW filters can carry personal data.
     *
     * municipality_id is derived from the connection name ("gemeente_{id}"), so
     * a call on the shared "main" connection is not attributable to a single
     * municipality and stays null.
     */
    public function up(): void
    {
        Schema::create('zgw_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('connection');
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 10);
            $table->text('resource');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('failed')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['municipality_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zgw_request_logs');
    }
};
