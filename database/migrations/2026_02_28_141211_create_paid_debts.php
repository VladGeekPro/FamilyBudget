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
        Schema::create('paid_debts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete();
            $table->date('change_debt_date');
            $table->foreignId('paid_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('payment_status', ['partial', 'paid']);
            $table->decimal('paid_sum', 12, 2);

            $table->index('debt_id');
            $table->index('paid_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paid_debts');
    }
};
