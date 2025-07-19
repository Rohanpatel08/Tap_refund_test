<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Refund;
use App\Models\Payment;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payment = Payment::factory()->create();
        
        return [
            'charge_id' => $payment->charge_id,
            'refund_id' => 'ref_' . $this->faker->unique()->regexify('[A-Za-z0-9]{20}'),
            'amount' => $this->faker->randomFloat(2, 1, $payment->amount),
            'currency' => $payment->currency,
            'description' => $this->faker->sentence(),
            'reason' => $this->faker->randomElement([
                'requested_by_customer',
                'duplicate',
                'fraudulent',
                'subscription_canceled'
            ]),
            'status' => $this->faker->randomElement(['pending', 'succeeded', 'failed']),
            'response' => [
                'id' => 'ref_' . $this->faker->regexify('[A-Za-z0-9]{20}'),
                'object' => 'refund',
                'live_mode' => false,
                'api_version' => 'v2',
            ],
        ];
    }

    /**
     * Indicate that the refund is succeeded.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
        ]);
    }

    /**
     * Indicate that the refund is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the refund failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Create a refund for a specific payment.
     */
    public function forPayment(Payment $payment): static
    {
        return $this->state(fn (array $attributes) => [
            'charge_id' => $payment->charge_id,
            'currency' => $payment->currency,
            'amount' => $this->faker->randomFloat(2, 1, $payment->amount),
        ]);
    }
}