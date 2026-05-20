<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('share_token', 32)->nullable()->unique()->after('slug');
        });

        // Backfill: every existing trip gets a token
        foreach (DB::table('trips')->whereNull('share_token')->pluck('id') as $id) {
            DB::table('trips')->where('id', $id)->update([
                'share_token' => Str::lower(Str::random(12)),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
