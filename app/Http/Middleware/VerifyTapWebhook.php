<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyTapWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $webhookSecret = config('services.tap.webhook_secret');
        
        if (!$webhookSecret) {
            Log::warning('Tap webhook secret not configured');
            return response()->json(['error' => 'Webhook not configured'], 500);
        }

        $signature = $request->header('x-tap-signature');
        $payload = $request->getContent();
        
        if (!$signature) {
            Log::warning('Missing webhook signature');
            return response()->json(['error' => 'Missing signature'], 401);
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Invalid webhook signature', [
                'expected' => $expectedSignature,
                'received' => $signature
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}