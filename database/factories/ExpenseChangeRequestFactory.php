<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseChangeRequestFactory extends Factory
{
    public function definition(): array
    {
        $actionType = $this->faker->randomElement(['create', 'edit', 'delete']);
        $expense = Expense::inRandomOrder()->first();

        return [
            'expense_id' => $expense?->id,
            'user_id' => User::inRandomOrder()->first()?->id,
            'action_type' => $actionType,
            
            'current_date' => $expense?->date,
            'current_user_id' => $expense?->user_id,
            'current_category_id' => $expense?->category_id,
            'current_supplier_id' => $expense?->supplier_id,
            'current_sum' => $expense?->sum,
            'current_notes' => $expense?->notes,

            'requested_date' => $actionType !== 'delete' ? $this->faker->dateTime() : null,
            'requested_user_id' => $actionType !== 'delete' ? User::inRandomOrder()->first()?->id : null,
            'requested_category_id' => $actionType !== 'delete' ? Category::inRandomOrder()->first()?->id : null,
            'requested_supplier_id' => $actionType !== 'delete' ? Supplier::inRandomOrder()->first()?->id : null,
            'requested_sum' => $actionType !== 'delete' ? $this->faker->randomFloat(2, 10, 1000) : null,
            'requested_notes' => $actionType !== 'delete' ? $this->faker->optional()->sentence() : null,
            
            'notes' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['pending', 'rejected', 'completed']),
            'applied_at' => null,
        ];
    }
}
