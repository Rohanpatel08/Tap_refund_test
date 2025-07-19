<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Models\Payment;
use App\Models\Refund;
use Symfony\Component\HttpFoundation\Response;

class TapWebhookController extends Controller
{
    /**
     * Handle Tap webhook notifications
     */
    public function handleWebhook(Request $request)
    {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Invalid webhook signature from Tap', [
                'headers' => $request->headers->all(),
                'payload' => $request->getContent()
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();
        
        Log::info('Tap webhook received', [
            'event_type' => $payload['event_type'] ?? 'unknown',
            'payload' => $payload
        ]);

        try {
            $eventType = $payload['event_type'] ?? null;
            
            switch ($eventType) {
                case 'refund.created':
                    return $this->handleRefundCreated($payload);
                    
                case 'refund.updated':
                    return $this->handleRefundUpdated($payload);
                    
                case 'refund.succeeded':
                    return $this->handleRefundSucceeded($payload);
                    
                case 'refund.failed':
                    return $this->handleRefundFailed($payload);
                    
                case 'charge.updated':
                    return $this->handleChargeUpdated($payload);
                    
                default:
                    Log::info('Unhandled webhook event type', ['event_type' => $eventType]);
                    return response()->json(['message' => 'Event type not handled'], 200);
            }
        } catch (\Exception $e) {
            Log::error('Error processing Tap webhook', [
                'error' => $e->getMessage(),
                'payload' => $payload,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookSecret = config('services.tap.webhook_secret');
        
        if (!$webhookSecret) {
            Log::warning('Tap webhook secret not configured');
            return false;
        }

        $signature = $request->header('x-tap-signature');
        $payload = $request->getContent();
        
        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle refund created event
     */
    private function handleRefundCreated(array $payload)
    {
        $refundData = $payload['data'] ?? [];
        $chargeId = $refundData['charge']['id'] ?? null;
        $refundId = $refundData['id'] ?? null;

        if (!$chargeId || !$refundId) {
            Log::warning('Missing charge_id or refund_id in refund.created webhook', $payload);
            return response()->json(['error' => 'Missing required data'], 400);
        }

        // Find or create refund record
        $refund = Refund::where('refund_id', $refundId)->first();
        
        if (!$refund) {
            // Create new refund record if it doesn't exist
            Refund::create([
                'charge_id' => $chargeId,
                'refund_id' => $refundId,
                'amount' => $refundData['amount'] ?? 0,
                'currency' => $refundData['currency'] ?? 'USD',
                'description' => $refundData['description'] ?? null,
                'reason' => $refundData['reason'] ?? 'requested_by_customer',
                'status' => $refundData['status'] ?? 'pending',
                'response' => $refundData,
            ]);

            Log::info('Refund created via webhook', ['refund_id' => $refundId, 'charge_id' => $chargeId]);
        }

        return response()->json(['message' => 'Refund created processed'], 200);
    }

    /**
     * Handle refund updated event
     */
    private function handleRefundUpdated(array $payload)
    {
        $refundData = $payload['data'] ?? [];
        $refundId = $refundData['id'] ?? null;

        if (!$refundId) {
            Log::warning('Missing refund_id in refund.updated webhook', $payload);
            return response()->json(['error' => 'Missing refund_id'], 400);
        }

        $refund = Refund::where('refund_id', $refundId)->first();

        if ($refund) {
            $refund->update([
                'status' => $refundData['status'] ?? $refund->status,
                'response' => $refundData,
            ]);

            Log::info('Refund updated via webhook', [
                'refund_id' => $refundId,
                'status' => $refundData['status'] ?? 'unknown'
            ]);
        } else {
            Log::warning('Refund not found for update webhook', ['refund_id' => $refundId]);
        }

        return response()->json(['message' => 'Refund updated processed'], 200);
    }

    /**
     * Handle refund succeeded event
     */
    private function handleRefundSucceeded(array $payload)
    {
        $refundData = $payload['data'] ?? [];
        $refundId = $refundData['id'] ?? null;

        if (!$refundId) {
            Log::warning('Missing refund_id in refund.succeeded webhook', $payload);
            return response()->json(['error' => 'Missing refund_id'], 400);
        }

        $refund = Refund::where('refund_id', $refundId)->first();

        if ($refund) {
            $refund->update([
                'status' => 'succeeded',
                'response' => $refundData,
            ]);

            // Optional: Update related payment status if needed
            $payment = Payment::where('charge_id', $refund->charge_id)->first();
            if ($payment) {
                // You might want to update payment status to indicate partial/full refund
                // This depends on your business logic
            }

            Log::info('Refund succeeded via webhook', ['refund_id' => $refundId]);
        } else {
            Log::warning('Refund not found for succeeded webhook', ['refund_id' => $refundId]);
        }

        return response()->json(['message' => 'Refund succeeded processed'], 200);
    }

    /**
     * Handle refund failed event
     */
    private function handleRefundFailed(array $payload)
    {
        $refundData = $payload['data'] ?? [];
        $refundId = $refundData['id'] ?? null;

        if (!$refundId) {
            Log::warning('Missing refund_id in refund.failed webhook', $payload);
            return response()->json(['error' => 'Missing refund_id'], 400);
        }

        $refund = Refund::where('refund_id', $refundId)->first();

        if ($refund) {
            $refund->update([
                'status' => 'failed',
                'response' => $refundData,
            ]);

            Log::info('Refund failed via webhook', ['refund_id' => $refundId]);
        } else {
            Log::warning('Refund not found for failed webhook', ['refund_id' => $refundId]);
        }

        return response()->json(['message' => 'Refund failed processed'], 200);
    }

    /**
     * Handle charge updated event (for payment status changes)
     */
    private function handleChargeUpdated(array $payload)
    {
        $chargeData = $payload['data'] ?? [];
        $chargeId = $chargeData['id'] ?? null;

        if (!$chargeId) {
            Log::warning('Missing charge_id in charge.updated webhook', $payload);
            return response()->json(['error' => 'Missing charge_id'], 400);
        }

        $payment = Payment::where('charge_id', $chargeId)->first();

        if ($payment) {
            $payment->update([
                'status' => $chargeData['status'] ?? $payment->status,
                'tap_response' => json_encode($chargeData),
            ]);

            Log::info('Payment updated via webhook', [
                'charge_id' => $chargeId,
                'status' => $chargeData['status'] ?? 'unknown'
            ]);
        } else {
            Log::warning('Payment not found for charge update webhook', ['charge_id' => $chargeId]);
        }

        return response()->json(['message' => 'Charge updated processed'], 200);
    }
}