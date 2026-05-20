<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // source_url was VARCHAR(255) but Google Maps URLs with URL-encoded
            // non-ASCII place names can easily exceed 255 chars. The validation
            // rule already allows up to 2000, so the column must match.
            $table->string('source_url', 2000)->nullable()->change();

            // note was VARCHAR(255) but the validation rule allows max:500.
            $table->string('note', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('source_url')->nullable()->change();
            $table->string('note')->nullable()->change();
        });
    }
};
