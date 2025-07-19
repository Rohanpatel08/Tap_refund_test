<?php

namespace App\Http\Controllers;

use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
    private RefundService $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    /**
     * Create a full refund
     */
    public function createFullRefund(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'charge_id' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.001',
                'currency' => 'required|string|size:3',
                'description' => 'nullable|string|max:500',
                'reason' => 'nullable|string|in:duplicate,fraudulent,requested_by_customer,other',
                'merchant_reference' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
                'metadata.*' => 'string|max:255'
            ]);

            $result = $this->refundService->processFullRefund(
                $validatedData['charge_id'],
                $validatedData['amount'],
                $validatedData['currency'],
                [
                    'description' => $validatedData['description'] ?? null,
                    'reason' => $validatedData['reason'] ?? 'requested_by_customer',
                    'merchant_reference' => $validatedData['merchant_reference'] ?? null,
                    'metadata' => $validatedData['metadata'] ?? []
                ]
            );

            $statusCode = $result['success'] ? 201 : 400;

            return response()->json($result, $statusCode);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Full refund creation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create a partial refund
     */
    public function createPartialRefund(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'charge_id' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.001',
                'currency' => 'required|string|size:3',
                'original_amount' => 'nullable|numeric|min:0.001',
                'description' => 'nullable|string|max:500',
                'reason' => 'nullable|string|in:duplicate,fraudulent,requested_by_customer,other',
                'merchant_reference' => 'nullable|string|max:255',
                'metadata' => 'nullable|array',
                'metadata.*' => 'string|max:255'
            ]);

            $result = $this->refundService->processPartialRefund(
                $validatedData['charge_id'],
                $validatedData['amount'],
                $validatedData['currency'],
                [
                    'original_amount' => $validatedData['original_amount'] ?? null,
                    'description' => $validatedData['description'] ?? null,
                    'reason' => $validatedData['reason'] ?? 'requested_by_customer',
                    'merchant_reference' => $validatedData['merchant_reference'] ?? null,
                    'metadata' => $validatedData['metadata'] ?? []
                ]
            );

            $statusCode = $result['success'] ? 201 : 400;

            return response()->json($result, $statusCode);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Partial refund creation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get refund status
     */
    public function getRefundStatus(string $refundId): JsonResponse
    {
        try {
            $result = $this->refundService->getRefundStatus($refundId);

            $statusCode = $result['success'] ? 200 : 404;

            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Get refund status failed', [
                'refund_id' => $refundId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error'
            ], 500);
        }
    }
}
