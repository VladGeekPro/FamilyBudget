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
        Schema::create('expense_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('action_type', ['create', 'edit', 'delete']);
            
            $table->date('current_date')->nullable();
            $table->foreignId('current_user_id')->nullable()->constrained('users');
            $table->foreignId('current_category_id')->nullable()->constrained('categories');
            $table->foreignId('current_supplier_id')->nullable()->constrained('suppliers');
            $table->decimal('current_sum', 12, 2)->nullable();
            $table->text('current_notes')->nullable();

            $table->date('requested_date')->nullable();
            $table->foreignId('requested_user_id')->nullable()->constrained('users');
            $table->foreignId('requested_category_id')->nullable()->constrained('categories');
            $table->foreignId('requested_supplier_id')->nullable()->constrained('suppliers');
            $table->decimal('requested_sum', 12, 2)->nullable();
            $table->text('requested_notes')->nullable();
            
            $table->text('notes');
            $table->enum('status', ['pending', 'rejected', 'completed'])->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
            $table->index('expense_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_change_requests');
    }
};
