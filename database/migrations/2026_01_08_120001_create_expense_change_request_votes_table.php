<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_change_request_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_change_request_id')->constrained('expense_change_requests')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('vote', ['approved', 'rejected']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['expense_change_request_id', 'user_id'], 'unique_vote_per_user');
            $table->index('expense_change_request_id');
            $table->index(['user_id', 'vote']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_change_request_votes');
    }
};
