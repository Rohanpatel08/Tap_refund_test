<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Payment;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'charge_id' => 'chg_' . $this->faker->unique()->regexify('[A-Za-z0-9]{20}'),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'AED']),
            'status' => $this->faker->randomElement(['pending', 'succeeded', 'failed']),
            'payment_method' => $this->faker->randomElement(['credit_card', 'debit_card', 'bank_transfer']),
            'tap_response' => [
                'id' => 'chg_' . $this->faker->regexify('[A-Za-z0-9]{20}'),
                'object' => 'charge',
                'live_mode' => false,
                'api_version' => 'v2',
            ],
        ];
    }

    /**
     * Indicate that the payment is succeeded.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
        ]);
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the payment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }
}