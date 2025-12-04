<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run this migration for PostgreSQL databases
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Convert JSON columns to JSONB for better performance and indexing support
        // This preserves all existing data

        DB::statement('ALTER TABLE locations ALTER COLUMN geometry TYPE JSONB USING geometry::jsonb');
        DB::statement('ALTER TABLE settings ALTER COLUMN payload TYPE JSONB USING payload::jsonb');
        DB::statement('ALTER TABLE municipalities ALTER COLUMN geometry TYPE JSONB USING geometry::jsonb');
        DB::statement('ALTER TABLE messages ALTER COLUMN documents TYPE JSONB USING documents::jsonb');
        DB::statement('ALTER TABLE notification_preferences ALTER COLUMN channels TYPE JSONB USING channels::jsonb');
        DB::statement('ALTER TABLE zaken ALTER COLUMN reference_data TYPE JSONB USING reference_data::jsonb');
        DB::statement('ALTER TABLE activity_log ALTER COLUMN properties TYPE JSONB USING properties::jsonb');
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE JSONB USING data::jsonb');
        DB::statement('ALTER TABLE municipality_variables ALTER COLUMN value TYPE JSONB USING value::jsonb');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only run this migration for PostgreSQL databases
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Convert JSONB columns back to JSON

        DB::statement('ALTER TABLE locations ALTER COLUMN geometry TYPE JSON USING geometry::json');
        DB::statement('ALTER TABLE settings ALTER COLUMN payload TYPE JSON USING payload::json');
        DB::statement('ALTER TABLE municipalities ALTER COLUMN geometry TYPE JSON USING geometry::json');
        DB::statement('ALTER TABLE messages ALTER COLUMN documents TYPE JSON USING documents::json');
        DB::statement('ALTER TABLE notification_preferences ALTER COLUMN channels TYPE JSON USING channels::json');
        DB::statement('ALTER TABLE zaken ALTER COLUMN reference_data TYPE JSON USING reference_data::json');
        DB::statement('ALTER TABLE activity_log ALTER COLUMN properties TYPE JSON USING properties::json');
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE JSON USING data::json');
        DB::statement('ALTER TABLE municipality_variables ALTER COLUMN value TYPE JSON USING value::json');
    }
};
