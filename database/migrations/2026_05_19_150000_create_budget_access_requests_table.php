<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('budget_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // One request per (trip, requester)
            $table->unique(['trip_id', 'user_id']);
            $table->index(['trip_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_access_requests');
    }
};
