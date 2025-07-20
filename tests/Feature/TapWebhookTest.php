<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\Config;

class TapWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set a test webhook secret
        Config::set('services.tap.webhook_secret', 'test_webhook_secret');
    }

    /**
     * Generate a valid webhook signature
     */
    private function generateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, 'test_webhook_secret');
    }

    /**
     * Test webhook with invalid signature
     */
    public function test_webhook_rejects_invalid_signature()
    {
        $payload = json_encode(['event_type' => 'refund.created']);
        
        $response = $this->postJson('/api/tap/webhook', json_decode($payload, true), [
            'x-tap-signature' => 'invalid_signature'
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test webhook with missing signature
     */
    public function test_webhook_rejects_missing_signature()
    {
        $payload = ['event_type' => 'refund.created'];
        
        $response = $this->postJson('/api/tap/webhook', $payload);

        $response->assertStatus(401);
    }

    /**
     * Test refund created webhook
     */
    public function test_refund_created_webhook()
    {
        // Create a payment first
        $payment = Payment::factory()->create([
            'charge_id' => 'chg_test123',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'succeeded'
        ]);

        $payload = [
            'event_type' => 'refund.created',
            'data' => [
                'id' => 'ref_test123',
                'charge' => ['id' => 'chg_test123'],
                'amount' => 50.00,
                'currency' => 'USD',
                'description' => 'Test refund',
                'reason' => 'requested_by_customer',
                'status' => 'pending'
            ]
        ];

        $payloadJson = json_encode($payload);
        $signature = $this->generateSignature($payloadJson);

        $response = $this->postJson('/api/tap/webhook', $payload, [
            'x-tap-signature' => $signature
        ]);

        $response->assertStatus(200);
        
        // Check if refund was created
        $this->assertDatabaseHas('refunds', [
            'refund_id' => 'ref_test123',
            'charge_id' => 'chg_test123',
            'amount' => 50.00,
            'status' => 'pending'
        ]);
    }

    /**
     * Test refund succeeded webhook
     */
    public function test_refund_succeeded_webhook()
    {
        // Create a refund first
        $refund = Refund::factory()->create([
            'refund_id' => 'ref_test123',
            'charge_id' => 'chg_test123',
            'status' => 'pending'
        ]);

        $payload = [
            'event_type' => 'refund.succeeded',
            'data' => [
                'id' => 'ref_test123',
                'status' => 'succeeded'
            ]
        ];

        $payloadJson = json_encode($payload);
        $signature = $this->generateSignature($payloadJson);

        $response = $this->postJson('/api/tap/webhook', $payload, [
            'x-tap-signature' => $signature
        ]);

        $response->assertStatus(200);
        
        // Check if refund status was updated
        $this->assertDatabaseHas('refunds', [
            'refund_id' => 'ref_test123',
            'status' => 'succeeded'
        ]);
    }

    /**
     * Test refund failed webhook
     */
    public function test_refund_failed_webhook()
    {
        // Create a refund first
        $refund = Refund::factory()->create([
            'refund_id' => 'ref_test123',
            'charge_id' => 'chg_test123',
            'status' => 'pending'
        ]);

        $payload = [
            'event_type' => 'refund.failed',
            'data' => [
                'id' => 'ref_test123',
                'status' => 'failed'
            ]
        ];

        $payloadJson = json_encode($payload);
        $signature = $this->generateSignature($payloadJson);

        $response = $this->postJson('/api/tap/webhook', $payload, [
            'x-tap-signature' => $signature
        ]);

        $response->assertStatus(200);
        
        // Check if refund status was updated
        $this->assertDatabaseHas('refunds', [
            'refund_id' => 'ref_test123',
            'status' => 'failed'
        ]);
    }

    /**
     * Test charge updated webhook
     */
    public function test_charge_updated_webhook()
    {
        // Create a payment first
        $payment = Payment::factory()->create([
            'charge_id' => 'chg_test123',
            'status' => 'pending'
        ]);

        $payload = [
            'event_type' => 'charge.updated',
            'data' => [
                'id' => 'chg_test123',
                'status' => 'succeeded'
            ]
        ];

        $payloadJson = json_encode($payload);
        $signature = $this->generateSignature($payloadJson);

        $response = $this->postJson('/api/tap/webhook', $payload, [
            'x-tap-signature' => $signature
        ]);

        $response->assertStatus(200);
        
        // Check if payment status was updated
        $this->assertDatabaseHas('payments', [
            'charge_id' => 'chg_test123',
            'status' => 'succeeded'
        ]);
    }

    /**
     * Test unknown event type webhook
     */
    public function test_unknown_event_type_webhook()
    {
        $payload = [
            'event_type' => 'unknown.event',
            'data' => []
        ];

        $payloadJson = json_encode($payload);
        $signature = $this->generateSignature($payloadJson);

        $response = $this->postJson('/api/tap/webhook', $payload, [
            'x-tap-signature' => $signature
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Event type not handled']);
    }
}