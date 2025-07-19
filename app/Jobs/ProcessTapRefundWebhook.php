<?php

namespace App\Jobs;

use App\Models\TapRefund;
use App\Models\Payment;
use App\Notifications\RefundStatusUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessTapRefundWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $webhookPayload;
    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(array $webhookPayload)
    {
        $this->webhookPayload = $webhookPayload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing Tap refund webhook job', [
                'payload' => $this->webhookPayload
            ]);

            $eventType = $this->webhookPayload['event'] ?? null;
            $refundData = $this->webhookPayload['data'] ?? $this->webhookPayload;

            if (!$this->isRefundEvent($eventType)) {
                Log::info('Skipping non-refund webhook event', ['event' => $eventType]);
                return;
            }

            $refundId = $refundData['id'] ?? null;
            if (!$refundId) {
                Log::error('Missing refund ID in webhook payload');
                return;
            }

            // Find existing refund
            $refund = TapRefund::where('refund_id', $refundId)->first();
            
            if (!$refund) {
                Log::warning('Refund not found for webhook', ['refund_id' => $refundId]);
                return;
            }

            // Process based on event type
            switch ($eventType) {
                case 'refund.succeeded':
                    $this->handleRefundSucceeded($refund, $refundData);
                    break;
                    
                case 'refund.failed':
                    $this->handleRefundFailed($refund, $refundData);
                    break;
                    
                case 'refund.updated':
                    $this->handleRefundUpdated($refund, $refundData);
                    break;
                    
                default:
                    Log::info('Unhandled refund event', ['event' => $eventType]);
                    break;
            }

        } catch (\Exception $e) {
            Log::error('Error processing Tap refund webhook job', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $this->webhookPayload
            ]);
            
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle successful refund
     */
    private function handleRefundSucceeded(TapRefund $refund, array $refundData): void
    {
        $refund->update([
            'status' => 'refunded',
            'tap_response' => $refundData,
            'refund_date' => now()
        ]);

        Log::info('Refund marked as succeeded', [
            'refund_id' => $refund->refund_id,
            'charge_id' => $refund->charge_id
        ]);

        // Send notification to customer
        $this->sendRefundNotification($refund, 'succeeded');

        // Update original payment status if needed
        $this->updateOriginalPaymentStatus($refund);
    }

    /**
     * Handle failed refund
     */
    private function handleRefundFailed(TapRefund $refund, array $refundData): void
    {
        $failureReason = $refundData['failure_reason'] ?? 'Unknown reason';
        
        $refund->update([
            'status' => 'failed',
            'tap_response' => $refundData,
            'reason' => $failureReason
        ]);

        Log::error('Refund marked as failed', [
            'refund_id' => $refund->refund_id,
            'charge_id' => $refund->charge_id,
            'reason' => $failureReason
        ]);

        // Send notification about failed refund
        $this->sendRefundNotification($refund, 'failed');
    }

    /**
     * Handle refund update
     */
    private function handleRefundUpdated(TapRefund $refund, array $refundData): void
    {
        $oldStatus = $refund->status;
        
        $refund->update([
            'status' => $refundData['status'] ?? $refund->status,
            'tap_response' => $refundData
        ]);

        Log::info('Refund status updated', [
            'refund_id' => $refund->refund_id,
            'old_status' => $oldStatus,
            'new_status' => $refund->status
        ]);

        // Send notification if status changed significantly
        if ($oldStatus !== $refund->status && in_array($refund->status, ['refunded', 'failed'])) {
            $this->sendRefundNotification($refund, $refund->status);
        }
    }

    /**
     * Check if this is a refund-related event
     */
    private function isRefundEvent(?string $eventType): bool
    {
        return $eventType && str_starts_with($eventType, 'refund.');
    }

    /**
     * Send refund notification
     */
    private function sendRefundNotification(TapRefund $refund, string $status): void
    {
        try {
            // Find the original payment to get customer details
            $payment = Payment::where('charge_id', $refund->charge_id)->first();
            
            if (!$payment || !$payment->email) {
                Log::warning('Cannot send refund notification - missing payment or email', [
                    'refund_id' => $refund->refund_id,
                    'charge_id' => $refund->charge_id
                ]);
                return;
            }

            // Send notification (you can customize this based on your notification system)
            Notification::route('mail', $payment->email)
                ->notify(new RefundStatusUpdated($refund, $status));

            Log::info('Refund notification sent', [
                'refund_id' => $refund->refund_id,
                'email' => $payment->email,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send refund notification', [
                'refund_id' => $refund->refund_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update original payment status based on refund
     */
    private function updateOriginalPaymentStatus(TapRefund $refund): void
    {
        try {
            $payment = Payment::where('charge_id', $refund->charge_id)->first();
            
            if (!$payment) {
                return;
            }

            // Check if this is a full refund
            if ($refund->type === 'full' || $refund->amount >= $payment->amount) {
                $payment->update(['status' => 'refunded']);
                
                Log::info('Original payment marked as refunded', [
                    'payment_id' => $payment->id,
                    'charge_id' => $payment->charge_id
                ]);
            } else {
                // For partial refunds, you might want to track this differently
                $payment->update(['status' => 'partially_refunded']);
                
                Log::info('Original payment marked as partially refunded', [
                    'payment_id' => $payment->id,
                    'charge_id' => $payment->charge_id
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to update original payment status', [
                'refund_id' => $refund->refund_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Tap refund webhook job failed permanently', [
            'payload' => $this->webhookPayload,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}