<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Payment; // Assuming you have a Payment model
use App\Models\Refund;
use Illuminate\Support\Facades\Http;

class TapRefundController extends Controller
{
    public function index()
    {
        $payments = Payment::all(); // Fetch all payments
        return view('payments.index', compact('payments'));
    }

    public function showRefundForm($chargeId)
    {
        $payment = Payment::where('charge_id', $chargeId)->firstOrFail();
        return view('payments.refund', compact('payment'));
    }

    public function processRefund(Request $request, $chargeId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $payment = Payment::where('charge_id', $chargeId)->firstOrFail();
        // Build refund payload
        $refundData = [
            'charge_id'   => $chargeId,
            'amount'      => $request->input('amount'),
            'currency'    => $payment->currency,
            'description' => $request->input('description') ?? null,
            'reason'      => $request->input('reason') ?? 'requested_by_customer',
        ];
        // dd($refundData);
        try {
            $response = Http::withToken(env('TAP_SECRET_KEY'))
                ->post(config('services.tap.base_url') . 'refunds', $refundData);

            $refundResponse = $response->json();
            $status = $refundResponse['status'] ?? 'unknown';

            // Store in refunds table
            Refund::create([
                'charge_id'  => $chargeId,
                'refund_id'  => $refundResponse->id ?? null,
                'amount'     => $request->input('amount'),
                'currency'   => $payment->currency,
                'description' => $request->input('description'),
                'reason'     => $request->input('reason'),
                'status'     => $status,
                'response'   => json_encode($refundResponse),
            ]);

            // (Optional) Update payment status if needed

            return redirect()->route('admin.refunds.index')->with('success', 'Refund processed!');
        } catch (\Exception $e) {
            // Log, display error
            return back()->withErrors('Refund failed. ' . $e->getMessage());
        }
    }

    public function refundsIndex()
    {
        $refunds = Refund::latest()->paginate(20);
        return view('refunds.index', compact('refunds'));
    }
}
