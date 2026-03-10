<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class DebtFactory extends Factory
{
    private static int $dateOffset = 0;

    public function definition(): array
    {
        // Генерируем уникальные даты путем смещения на количество дней
        $date = Carbon::now()->subDays(300 + self::$dateOffset)->format('Y-m-d');
        self::$dateOffset += 15;

        return [
            'date' => $date,
            'user_id' => User::inRandomOrder()->first()?->id,
            'debt_sum' => $this->faker->randomFloat(2, 100, 5000),
            'overpayment_id' => null,
            'notes' => $this->faker->sentence(),
            'payment_status' => $this->faker->randomElement(['unpaid', 'partial', 'paid']),
            'partial_sum' => 0,
            'date_paid' => null,
        ];
    }
}
