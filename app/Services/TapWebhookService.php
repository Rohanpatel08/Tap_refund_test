<?php

namespace App\Services;

use App\Models\TapRefund;
use App\Models\Payment;
use App\Jobs\ProcessTapRefundWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class TapWebhookService
{
    /**
     * Verify webhook signature from Tap
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('X-Tap-Signature');
        $payload = $request->getContent();
        $secret = config('services.tap.webhook_secret');

        if (!$signature || !$secret) {
            Log::warning('Missing signature or webhook secret');
            return false;
        }

        // Tap uses HMAC SHA256 for webhook signatures
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($signature, $expectedSignature);
    }

    /**
     * Process refund webhook from Tap
     */
    public function processRefundWebhook(array $payload): array
    {
        try {
            // Extract refund data from webhook payload
            $refundData = $this->extractRefundData($payload);
            
            if (!$refundData) {
                return ['success' => false, 'error' => 'Invalid refund data'];
            }

            // Check if refund already exists
            $existingRefund = TapRefund::where('refund_id', $refundData['refund_id'])->first();
            
            if ($existingRefund) {
                // Update existing refund with webhook data
                $this->updateRefundFromWebhook($existingRefund, $refundData);
                
                return [
                    'success' => true, 
                    'refund_id' => $refundData['refund_id'],
                    'action' => 'updated'
                ];
            } else {
                // Create new refund record from webhook
                $refund = $this->createRefundFromWebhook($refundData);
                
                return [
                    'success' => true, 
                    'refund_id' => $refund->refund_id,
                    'action' => 'created'
                ];
            }

        } catch (\Exception $e) {
            Log::error('Error processing refund webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process payment webhook from Tap (for future use)
     */
    public function processPaymentWebhook(array $payload): array
    {
        try {
            // Dispatch job for async processing
            ProcessTapRefundWebhook::dispatch($payload);
            
            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Error processing payment webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract refund data from webhook payload
     */
    private function extractRefundData(array $payload): ?array
    {
        // Check if this is a refund webhook
        if (!isset($payload['object']) || $payload['object'] !== 'refund') {
            return null;
        }

        $refund = $payload['data'] ?? $payload;

        return [
            'refund_id' => $refund['id'] ?? null,
            'charge_id' => $refund['charge']['id'] ?? $refund['charge_id'] ?? null,
            'amount' => isset($refund['amount']) ? $refund['amount'] / 100 : null, // Convert from fils to main currency
            'currency' => $refund['currency'] ?? null,
            'status' => $refund['status'] ?? 'pending',
            'reason' => $refund['reason'] ?? null,
            'description' => $refund['description'] ?? null,
            'reference' => $refund['reference'] ?? null,
            'metadata' => $refund['metadata'] ?? [],
            'tap_response' => $refund,
            'webhook_url' => request()->url(),
            'refund_date' => isset($refund['created']) ? 
                \Carbon\Carbon::createFromTimestamp($refund['created']) : now()
        ];
    }

    /**
     * Create new refund record from webhook data
     */
    private function createRefundFromWebhook(array $refundData): TapRefund
    {
        // Determine refund type based on amount
        $originalPayment = Payment::where('charge_id', $refundData['charge_id'])->first();
        $refundType = 'partial';
        
        if ($originalPayment && $refundData['amount'] >= $originalPayment->amount) {
            $refundType = 'full';
        }

        $refund = TapRefund::create([
            'refund_id' => $refundData['refund_id'],
            'charge_id' => $refundData['charge_id'],
            'amount' => $refundData['amount'],
            'currency' => $refundData['currency'],
            'type' => $refundType,
            'status' => $refundData['status'],
            'description' => $refundData['description'],
            'reason' => $refundData['reason'],
            'reference' => $refundData['reference'],
            'metadata' => $refundData['metadata'],
            'tap_response' => $refundData['tap_response'],
            'webhook_url' => $refundData['webhook_url'],
            'refund_date' => $refundData['refund_date']
        ]);

        Log::info('Refund created from webhook', [
            'refund_id' => $refund->refund_id,
            'charge_id' => $refund->charge_id,
            'amount' => $refund->amount,
            'status' => $refund->status
        ]);

        return $refund;
    }

    /**
     * Update existing refund with webhook data
     */
    private function updateRefundFromWebhook(TapRefund $refund, array $refundData): void
    {
        $refund->update([
            'status' => $refundData['status'],
            'tap_response' => $refundData['tap_response'],
            'refund_date' => $refundData['refund_date']
        ]);

        Log::info('Refund updated from webhook', [
            'refund_id' => $refund->refund_id,
            'new_status' => $refundData['status']
        ]);
    }

    /**
     * Get webhook events that should trigger processing
     */
    public function getSupportedEvents(): array
    {
        return [
            'refund.created',
            'refund.updated',
            'refund.succeeded',
            'refund.failed'
        ];
    }
}