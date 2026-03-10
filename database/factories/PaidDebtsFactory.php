<?php

namespace Database\Factories;

use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaidDebtsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'debt_id' => Debt::inRandomOrder()->first()?->id ?? Debt::factory(),
            'changed_debt_date' => $this->faker->dateTime(),
            'paid_by_user_id' => User::inRandomOrder()->first()?->id,
            'payment_status' => $this->faker->randomElement(['partial', 'paid']),
            'paid_sum' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
}
