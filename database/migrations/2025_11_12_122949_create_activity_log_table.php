<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateActivityLogTable extends Migration
{
    public function up()
    {
        Schema::connection(config('activitylog.database_connection'))->create(config('activitylog.table_name'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');

            // Handle mixed UUID/integer morph for PostgreSQL compatibility
            if (config('database.default') === 'pgsql') {
                $table->string('subject_type')->nullable()->index();
                $table->string('subject_id')->nullable();
                $table->index(['subject_type', 'subject_id'], 'subject');
            } else {
                $table->nullableUuidMorphs('subject', 'subject');
            }

            // Handle mixed UUID/integer morph for PostgreSQL compatibility
            if (config('database.default') === 'pgsql') {
                $table->string('causer_type')->nullable()->index();
                $table->string('causer_id')->nullable();
                $table->index(['causer_type', 'causer_id'], 'causer');
            } else {
                $table->nullableMorphs('causer', 'causer');
            }
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))->dropIfExists(config('activitylog.table_name'));
    }
}
