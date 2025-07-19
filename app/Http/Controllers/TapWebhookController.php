<?php

namespace App\Http\Controllers;

use App\Models\TapRefund;
use App\Services\TapWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class TapWebhookController extends Controller
{
    private TapWebhookService $webhookService;

    public function __construct(TapWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle incoming Tap refund webhook
     */
    public function handleRefundWebhook(Request $request): JsonResponse
    {
        try {
            // Log the incoming webhook for debugging
            Log::info('Tap refund webhook received', [
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
                'ip' => $request->ip()
            ]);

            // Verify webhook authenticity
            if (!$this->webhookService->verifyWebhookSignature($request)) {
                Log::warning('Invalid webhook signature', [
                    'payload' => $request->all()
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Process the refund webhook
            $result = $this->webhookService->processRefundWebhook($request->all());

            if ($result['success']) {
                Log::info('Tap refund webhook processed successfully', [
                    'refund_id' => $result['refund_id'] ?? null
                ]);
                return response()->json(['status' => 'success'], 200);
            } else {
                Log::error('Failed to process Tap refund webhook', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'payload' => $request->all()
                ]);
                return response()->json(['error' => $result['error']], 400);
            }

        } catch (Exception $e) {
            Log::error('Exception in Tap refund webhook', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle incoming Tap payment webhook (for future use)
     */
    public function handlePaymentWebhook(Request $request): JsonResponse
    {
        try {
            Log::info('Tap payment webhook received', [
                'payload' => $request->all()
            ]);

            // Verify webhook authenticity
            if (!$this->webhookService->verifyWebhookSignature($request)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // Process the payment webhook
            $result = $this->webhookService->processPaymentWebhook($request->all());

            return response()->json(['status' => 'success'], 200);

        } catch (Exception $e) {
            Log::error('Exception in Tap payment webhook', [
                'message' => $e->getMessage(),
                'payload' => $request->all()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}