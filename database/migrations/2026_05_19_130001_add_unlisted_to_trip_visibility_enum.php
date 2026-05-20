<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Expand the enum to include 'unlisted' (link-only access)
        DB::statement(
            "ALTER TABLE trips MODIFY COLUMN trip_visibility "
            . "ENUM('public', 'unlisted', 'private') NOT NULL DEFAULT 'public'"
        );
    }

    public function down(): void
    {
        // Map unlisted → public on revert (safer than losing rows)
        DB::statement("UPDATE trips SET trip_visibility = 'public' WHERE trip_visibility = 'unlisted'");
        DB::statement(
            "ALTER TABLE trips MODIFY COLUMN trip_visibility "
            . "ENUM('public', 'private') NOT NULL DEFAULT 'public'"
        );
    }
};
