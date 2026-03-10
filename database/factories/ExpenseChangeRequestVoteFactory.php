<?php

namespace Database\Factories;

use App\Models\ExpenseChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseChangeRequestVoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'expense_change_request_id' => ExpenseChangeRequest::inRandomOrder()->first()?->id ?? ExpenseChangeRequest::factory(),
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'vote' => $this->faker->randomElement(['approved', 'rejected']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
