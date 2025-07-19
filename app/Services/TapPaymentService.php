<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class TapPaymentService
{
    private string $secretKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.tap.secret_key');
        $this->baseUrl = config('services.tap.base_url');
    }

    /**
     * Create a refund
     */
    public function createRefund(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/refunds', $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('Tap refund creation failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'request_data' => $data
            ]);

            return [
                'success' => false,
                'error' => $this->extractErrorMessage($response),
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Tap refund exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $data
            ]);

            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get refund details
     */
    public function getRefund(string $refundId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/refunds/' . $refundId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('Tap get refund failed', [
                'refund_id' => $refundId,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => $this->extractErrorMessage($response),
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Tap get refund exception', [
                'refund_id' => $refundId,
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * List refunds for a charge
     */
    public function listRefunds(string $chargeId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/refunds', [
                'charge_id' => $chargeId
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('Tap list refunds failed', [
                'charge_id' => $chargeId,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => $this->extractErrorMessage($response),
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Tap list refunds exception', [
                'charge_id' => $chargeId,
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get charge details
     */
    public function getCharge(string $chargeId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/charges/' . $chargeId);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $this->extractErrorMessage($response),
                'status_code' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Tap get charge exception', [
                'charge_id' => $chargeId,
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Extract error message from response
     */
    private function extractErrorMessage(Response $response): string
    {
        $responseData = $response->json();

        if (isset($responseData['errors'])) {
            if (is_array($responseData['errors'])) {
                return implode(', ', $responseData['errors']);
            }
            return $responseData['errors'];
        }

        if (isset($responseData['message'])) {
            return $responseData['message'];
        }

        return 'Unknown error occurred';
    }
}
