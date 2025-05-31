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
        // Step 1: Add a temporary column to store the converted integer array
        DB::statement('ALTER TABLE workers ADD COLUMN services_id_temp integer[];');

        // Step 2: Convert json/jsonb data to integer array and store in temp column
        DB::statement("
            UPDATE workers
            SET services_id_temp = (
                SELECT ARRAY_AGG(CAST(element AS INTEGER))
                FROM json_array_elements_text(services_id::json) AS element
                WHERE element ~ '^[0-9]+$'  -- Ensure only valid integers are included
            );
        ");

        // Step 3: Drop the old services_id column
        DB::statement('ALTER TABLE workers DROP COLUMN services_id;');

        // Step 4: Rename the temp column to services_id
        DB::statement('ALTER TABLE workers RENAME COLUMN services_id_temp TO services_id;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add a temporary column to store the json data
        DB::statement('ALTER TABLE workers ADD COLUMN services_id_temp json;');

        // Step 2: Convert integer array back to json
        DB::statement("
            UPDATE workers
            SET services_id_temp = array_to_json(services_id)::json;
        ");

        // Step 3: Drop the integer array column
        DB::statement('ALTER TABLE workers DROP COLUMN services_id;');

        // Step 4: Rename the temp column to services_id
        DB::statement('ALTER TABLE workers RENAME COLUMN services_id_temp TO services_id;');
    }
};