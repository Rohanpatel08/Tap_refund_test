<?php

namespace App\Services;

use App\Models\TapRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class RefundService
{
    private TapPaymentService $tapService;

    public function __construct(TapPaymentService $tapService)
    {
        $this->tapService = $tapService;
    }

    /**
     * Process a full refund
     */
    public function processFullRefund(string $chargeId, float $amount, string $currency, array $options = []): array
    {
        // Validate that this is truly a full refund
        $existingRefunds = $this->getChargeRefunds($chargeId);

        if ($existingRefunds->where('status', 'refunded')->count() > 0) {
            return [
                'success' => false,
                'error' => 'This charge has already been refunded'
            ];
        }

        return $this->processRefund($chargeId, $amount, $currency, 'full', $options);
    }

    /**
     * Process a partial refund
     */
    public function processPartialRefund(string $chargeId, float $amount, string $currency, array $options = []): array
    {
        // Check if partial refunds exceed original amount
        $totalRefunded = TapRefund::where('charge_id', $chargeId)
            ->where('status', 'refunded')
            ->sum('amount');

        $originalAmount = $options['original_amount'] ?? $this->getChargeAmount($chargeId);

        if (!$originalAmount) {
            return [
                'success' => false,
                'error' => 'Unable to verify original charge amount'
            ];
        }

        if (($totalRefunded + $amount) > $originalAmount) {
            return [
                'success' => false,
                'error' => 'Refund amount exceeds remaining refundable amount. Available: ' .
                    ($originalAmount - $totalRefunded)
            ];
        }

        return $this->processRefund($chargeId, $amount, $currency, 'partial', $options);
    }

    /**
     * Process refund (private method)
     */
    private function processRefund(string $chargeId, float $amount, string $currency, string $type, array $options = []): array
    {
        DB::beginTransaction();

        try {
            // Prepare refund data for Tap API
            $refundData = [
                'charge_id' => $chargeId,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $options['description'] ?? 'Refund request',
                'reason' => $options['reason'] ?? 'requested_by_customer',
                'reference' => [
                    'merchant' => $options['merchant_reference'] ?? 'refund_' . time()
                ],
                'metadata' => $options['metadata'] ?? [],
                'post' => [
                    'url' => $options['webhook_url'] ?? route('tap.webhook.refund')
                ]
            ];

            // Call Tap API
            $response = $this->tapService->createRefund($refundData);

            if (!$response['success']) {
                DB::rollBack();
                return $response;
            }

            $tapResponse = $response['data'];

            // Store refund in database
            $refund = TapRefund::create([
                'refund_id' => $tapResponse['id'],
                'charge_id' => $chargeId,
                'amount' => $amount,
                'currency' => $currency,
                'type' => $type,
                'status' => strtolower($tapResponse['status']),
                'description' => $options['description'] ?? 'Refund request',
                'reason' => $options['reason'] ?? 'requested_by_customer',
                'reference' => $refundData['reference'],
                'metadata' => $refundData['metadata'],
                'tap_response' => $tapResponse,
                'webhook_url' => $refundData['post']['url'],
                'refund_date' => $tapResponse['status'] === 'REFUNDED' ? now() : null
            ]);

            DB::commit();

            Log::info('Refund processed successfully', [
                'refund_id' => $refund->refund_id,
                'charge_id' => $chargeId,
                'amount' => $amount,
                'type' => $type,
                'status' => $refund->status
            ]);

            return [
                'success' => true,
                'data' => [
                    'refund' => $refund,
                    'tap_response' => $tapResponse
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Refund processing failed', [
                'charge_id' => $chargeId,
                'amount' => $amount,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process refund: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get refund status and update if needed
     */
    public function getRefundStatus(string $refundId): array
    {
        $refund = TapRefund::where('refund_id', $refundId)->first();

        if (!$refund) {
            return [
                'success' => false,
                'error' => 'Refund not found'
            ];
        }

        // Get latest status from Tap if not already completed
        if ($refund->isPending()) {
            $response = $this->tapService->getRefund($refundId);

            if ($response['success']) {
                $tapData = $response['data'];

                // Update local status if different
                if (strtolower($tapData['status']) !== $refund->status) {
                    $refund->update([
                        'status' => strtolower($tapData['status']),
                        'tap_response' => $tapData,
                        'refund_date' => $tapData['status'] === 'REFUNDED' ? now() : $refund->refund_date
                    ]);
                }
            }
        }

        return [
            'success' => true,
            'data' => $refund
        ];
    }

    /**
     * Get all refunds for a charge
     */
    public function getChargeRefunds(string $chargeId): Collection
    {
        return TapRefund::where('charge_id', $chargeId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Calculate total refunded amount for a charge
     */
    public function getTotalRefundedAmount(string $chargeId): float
    {
        return TapRefund::where('charge_id', $chargeId)
            ->where('status', 'refunded')
            ->sum('amount');
    }

    /**
     * Calculate remaining refundable amount
     */
    public function getRemainingRefundableAmount(string $chargeId, float $originalAmount): float
    {
        $totalRefunded = $this->getTotalRefundedAmount($chargeId);
        return max(0, $originalAmount - $totalRefunded);
    }

    /**
     * Check if charge can be refunded
     */
    public function canRefund(string $chargeId): array
    {
        // Get charge details from Tap
        $chargeResponse = $this->tapService->getCharge($chargeId);

        if (!$chargeResponse['success']) {
            return [
                'can_refund' => false,
                'reason' => 'Unable to verify charge details'
            ];
        }

        $charge = $chargeResponse['data'];

        // Check if charge is captured/completed
        if (strtolower($charge['status']) !== 'captured') {
            return [
                'can_refund' => false,
                'reason' => 'Charge is not captured/completed'
            ];
        }

        // Check if already fully refunded
        $originalAmount = $charge['amount'];
        $totalRefunded = $this->getTotalRefundedAmount($chargeId);

        if ($totalRefunded >= $originalAmount) {
            return [
                'can_refund' => false,
                'reason' => 'Charge has already been fully refunded'
            ];
        }

        return [
            'can_refund' => true,
            'original_amount' => $originalAmount,
            'total_refunded' => $totalRefunded,
            'remaining_amount' => $originalAmount - $totalRefunded
        ];
    }

    /**
     * Get charge amount from Tap API
     */
    private function getChargeAmount(string $chargeId): ?float
    {
        $response = $this->tapService->getCharge($chargeId);

        if ($response['success']) {
            return $response['data']['amount'] ?? null;
        }

        return null;
    }

    /**
     * Update refund status from webhook
     */
    public function updateRefundFromWebhook(array $webhookData): bool
    {
        try {
            if (!isset($webhookData['id']) || !isset($webhookData['status'])) {
                return false;
            }

            $refund = TapRefund::where('refund_id', $webhookData['id'])->first();

            if (!$refund) {
                Log::warning('Refund not found for webhook', ['refund_id' => $webhookData['id']]);
                return false;
            }

            $refund->update([
                'status' => strtolower($webhookData['status']),
                'tap_response' => $webhookData,
                'refund_date' => $webhookData['status'] === 'REFUNDED' ? now() : $refund->refund_date
            ]);

            Log::info('Refund updated from webhook', [
                'refund_id' => $refund->refund_id,
                'old_status' => $refund->getOriginal('status'),
                'new_status' => $refund->status
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update refund from webhook', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData
            ]);

            return false;
        }
    }
}
