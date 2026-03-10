<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OverpaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory(),
            'sum' => $this->faker->randomFloat(2, 100, 5000),
            'notes' => $this->faker->sentence(),
        ];
    }
}
