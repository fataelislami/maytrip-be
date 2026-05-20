<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->nullable()->after('email');
            $table->string('avatar_url')->nullable()->after('username');
            $table->string('bio', 500)->nullable()->after('avatar_url');
            $table->string('location')->nullable()->after('bio');
            $table->string('link')->nullable()->after('location');
            $table->string('google_id')->nullable()->unique()->after('link');
        });

        // Allow null password (OAuth users)
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'avatar_url',
                'bio',
                'location',
                'link',
                'google_id',
            ]);
        });
    }
};
