<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\VerificationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser redirect target for PSPs: GET|POST /api/v1/gateways/{provider}/callback/{payment}.
 * The PSP's callback fields are only a hint; the outcome comes from PSP verification.
 */
class GatewayCallbackController extends Controller
{
    public function __construct(private readonly VerificationService $verification) {}

    public function __invoke(Request $request, string $provider, string $paymentId): Response
    {
        $payment = Payment::with(['client', 'latestAttempt.provider'])->where('public_id', $paymentId)->first();

        if ($payment === null) {
            return response()->view('pay.message', [
                'title' => 'Payment not found',
                'message' => 'We could not find this payment.',
            ], 404);
        }

        // Only scalar fields, size-limited. Query and form body are both accepted.
        $data = array_filter(
            array_slice($request->query() + $request->post(), 0, 50, true),
            fn ($v, $k) => is_string($k) && strlen($k) <= 64 && is_scalar($v) && strlen((string) $v) <= 2048,
            ARRAY_FILTER_USE_BOTH,
        );

        $payment = $this->verification->handleCallback($provider, $payment, $data);

        return $this->redirectToClient($payment);
    }

    private function redirectToClient(Payment $payment): Response
    {
        if (empty($payment->return_url)) {
            return response()->view('pay.show', ['payment' => $payment->load('merchant'), 'redirect' => null, 'nonce' => null]);
        }

        // Status in the redirect is informational only; clients must confirm via API or webhook.
        $query = http_build_query([
            'payment_id' => $payment->public_id,
            'order_id' => $payment->order_id,
            'status' => $payment->status->value,
        ]);
        $separator = str_contains($payment->return_url, '?') ? '&' : '?';

        return redirect()->away($payment->return_url.$separator.$query, 303);
    }
}
