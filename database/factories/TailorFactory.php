<?php

namespace Database\Factories;

use App\Models\Tailor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tailor>
 */
class TailorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'payment_period' => fake()->randomElement(['weekly', 'monthly']),
            'payment_day' => 'Sabtu',
            'bank_name' => 'BCA',
            'bank_account_number' => fake()->numerify('##########'),
            'bank_account_holder' => fake()->name(),
            'status' => 'active',
            'notes' => fake()->sentence(),
        ];
    }
}
