<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->string('destination');
            $table->string('currency', 3); // USD, JPY, IDR, ...
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_url')->nullable();
            $table->enum('budget_visibility', ['public', 'hidden', 'request'])
                ->default('public');
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index('destination');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
