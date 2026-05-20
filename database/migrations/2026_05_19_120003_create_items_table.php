<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()
                ->constrained('sections')->nullOnDelete();
            $table->string('title');
            $table->string('note')->nullable();
            $table->enum('category', ['transport', 'lodging', 'food', 'activity', 'other'])
                ->nullable();
            // store in smallest unit OR raw number; use unsigned bigInteger
            $table->unsignedBigInteger('price')->default(0);
            $table->string('time_start', 5)->nullable(); // "09:00"
            $table->string('time_end', 5)->nullable();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->string('source_url')->nullable();
            $table->string('photo_url')->nullable();
            $table->timestamp('story_tagged_at')->nullable();
            $table->enum('status', ['planned', 'spent', 'cancelled'])->nullable();
            $table->enum('source', ['manual', 'story', 'extension'])->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
