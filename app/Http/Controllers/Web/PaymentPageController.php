<?php

namespace App\Http\Controllers\Web;

use App\Enums\PaymentStatus;
use App\Gateways\Data\RedirectInstruction;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\PaymentStateMachine;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Customer-facing page: GET /pay/{payment_id}. Shows the site name, amount, description and
 * status, then forwards the customer to the PSP. No credentials or internal ids are rendered.
 */
class PaymentPageController extends Controller
{
    public function __construct(private readonly PaymentStateMachine $stateMachine) {}

    public function show(string $paymentId): Response
    {
        $payment = Payment::with(['client', 'merchant', 'latestAttempt'])->where('public_id', $paymentId)->first();

        if ($payment === null) {
            return response()->view('pay.message', ['title' => 'پرداخت یافت نشد', 'message' => 'این پرداخت وجود ندارد.'], 404);
        }

        if ($payment->status->isPayable() && $payment->expires_at?->isPast()) {
            $payment = DB::transaction(function () use ($payment) {
                $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

                return $locked->status->isPayable()
                    ? $this->stateMachine->transition($locked, PaymentStatus::Expired, 'system')
                    : $locked;
            })->load(['client', 'merchant', 'latestAttempt']);
        }

        $redirect = null;
        $payload = $payment->latestAttempt?->redirect_payload;

        if ($payment->status->isPayable() && is_array($payload)) {
            $redirect = RedirectInstruction::fromArray($payload);

            if ($payment->status === PaymentStatus::Pending) {
                DB::transaction(function () use ($payment) {
                    $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

                    if ($locked->status === PaymentStatus::Pending) {
                        $this->stateMachine->transition($locked, PaymentStatus::Redirected, 'customer', attempt: $payment->latestAttempt);
                    }
                });
            }
        }

        $nonce = base64_encode(random_bytes(16));
        $formAction = "'self'";

        if ($redirect !== null) {
            $parts = parse_url($redirect->url);
            $formAction .= ' '.$parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        }

        return response()
            ->view('pay.show', ['payment' => $payment, 'redirect' => $redirect, 'nonce' => $nonce])
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-{$nonce}'; img-src 'self' data:; form-action {$formAction}; frame-ancestors 'none'; base-uri 'none'")
            ->header('Cache-Control', 'no-store');
    }
}
