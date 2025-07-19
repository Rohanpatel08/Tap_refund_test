<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Payment;


class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Payment::create([
            'charge_id' => 'ch_test_fakeid12345', // fake or test charge_id
            'amount' => 100.00,
            'currency' => 'KWD',
            'status' => 'paid',
            'payment_method' => 'visa',
            'tap_response' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
